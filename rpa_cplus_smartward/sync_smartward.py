"""Keep SmartWard's wards, beds and patients in step with C+ Bed Management.

    venv\\Scripts\\python.exe sync_smartward.py --check       SmartWard address, token and hospital only
    venv\\Scripts\\python.exe sync_smartward.py --dry-run     read C+, save scratch\\last_payload.json, send nothing
    venv\\Scripts\\python.exe sync_smartward.py --once        one sync, then exit
    venv\\Scripts\\python.exe sync_smartward.py               sync now and every SYNC_INTERVAL_MINUTES

Each sync reads Bed Management with "All Locations" (one GET per unit group),
and ER Management's too unless SYNC_ER is off (the ED's zones, unit group 3),
takes each patient's admission date from the Inpatient Daily Census (else a
reservation request's Request Date, see scraper/admissions.py), reads the
Isolation box of the admission cards that are due (scraper/admission_card.py,
unless SYNC_ISOLATION is off), and pushes
every ward, bed and patient to SmartWard's POST /api/cplus/bed-sync, which
creates the wards and beds and admits, moves and discharges patients.

C+ logins: by default this never logs in. It uses the session login.py left in
the cookie jar; when that session is gone, --once exits with 2 and the loop
waits, checking the jar again every interval. --allow-login lets it log in by
itself, under login.py's rules (one attempt, a failure blocks further logins,
at least 15 minutes apart); that is for the person running it unattended,
e.g. in Docker or from Task Scheduler.

Exit codes (--once / --dry-run): 0 done, 1 this sync failed, 2 no C+ session
or logins blocked, 3 settings missing.
"""

import argparse
import json
import logging
import os
import sys
import time
from datetime import datetime

from config import (
    CENSUS_UNIT_GROUPS,
    HIS_FACILITY,
    HIS_FACILITY_ID,
    ISOLATION_CACHE_FILE,
    ISOLATION_MAX_READS,
    ISOLATION_REFRESH_MINUTES,
    LOG_FILE,
    REQUEST_LOOKBACK_DAYS,
    SCRATCH_DIR,
    SYNC_ER,
    SYNC_INTERVAL_MINUTES,
    SYNC_ISOLATION,
    SYNC_UNIT_GROUPS,
)
from core.session import HISSession, LoginBlocked, LoginTooSoon, NoSession, SessionError, session_account
from core.smartward_client import SmartWardClient, SmartWardError
from scraper.admission_card import attach_isolation, strip_card_links
from scraper.admissions import AdmissionsError, attach_admission_dates
from scraper.bed_management import ER_UNIT_GROUP, BedManagementError, read_all_locations, summarise, to_wards

if sys.stdout.encoding != "utf-8":
    sys.stdout.reconfigure(encoding="utf-8")

log = logging.getLogger("sync")


def setup_logging():
    os.makedirs(os.path.dirname(LOG_FILE), exist_ok=True)
    fmt = logging.Formatter("%(asctime)s %(levelname)s %(message)s", "%Y-%m-%d %H:%M:%S")
    for handler in (logging.StreamHandler(sys.stdout), logging.FileHandler(LOG_FILE, encoding="utf-8")):
        handler.setFormatter(fmt)
        log.addHandler(handler)
    log.setLevel(logging.INFO)


def unit_groups():
    """The Bed Management unit groups read, then ER Management's when SYNC_ER is on."""
    groups = list(SYNC_UNIT_GROUPS)
    if SYNC_ER and ER_UNIT_GROUP not in groups:
        groups.append(ER_UNIT_GROUP)
    return groups


def read_cplus(session):
    """The whole Bed Management (and ER Management) census as SmartWard's payload."""
    groups = unit_groups()
    rows = []
    for group in groups:
        rows.extend(read_all_locations(session, group))
    wards = to_wards(rows)
    if not wards:
        raise BedManagementError("C+ listed no beds at all; not sending an empty census.")

    # Without dates SmartWard keeps the admission dates it has, so a census
    # that fails is logged and the beds still go across.
    try:
        # The ER census gives the ED's patients their arrival time
        census_groups = CENSUS_UNIT_GROUPS + ([ER_UNIT_GROUP] if SYNC_ER and ER_UNIT_GROUP not in CENSUS_UNIT_GROUPS else [])
        dates = attach_admission_dates(wards, session, census_groups, SYNC_UNIT_GROUPS, REQUEST_LOOKBACK_DAYS)
        log.info("admission dates: %d from the census entry date, %d from a request date, %d with neither",
                 dates["census"], dates["request"], dates["none"])
    except AdmissionsError as e:
        log.warning("admission dates skipped: %s", e)

    # C+ is SmartWard's main source of isolation. A patient whose card has not
    # been read yet goes without it, and SmartWard leaves their isolation as it is.
    if SYNC_ISOLATION:
        try:
            iso = attach_isolation(wards, session, ISOLATION_CACHE_FILE, ISOLATION_REFRESH_MINUTES, ISOLATION_MAX_READS)
            log.info("isolation: %d admission card(s) read, %d unreadable, %d failed; %d of %d patients known, %d ticked",
                     iso["read"], iso["unread"], iso["failed"], iso["known"], iso["patients"], iso["ticked"])
            if iso["unread"]:
                log.warning("isolation: %d card(s) had no Isolation box with a known icon; the first is in scratch\\",
                            iso["unread"])
        except OSError as e:
            log.warning("isolation skipped: %s", e)
    strip_card_links(wards)

    return {
        "facility": {"id": HIS_FACILITY_ID, "name": HIS_FACILITY},
        "unit_groups": groups,
        "read_at": datetime.now().astimezone().isoformat(timespec="seconds"),
        "wards": wards,
    }


def save_payload(payload):
    os.makedirs(SCRATCH_DIR, exist_ok=True)
    path = os.path.join(SCRATCH_DIR, "last_payload.json")
    with open(path, "w", encoding="utf-8") as fh:
        json.dump(payload, fh, indent=1, ensure_ascii=False)
    return path


def sync_once(session, allow_login, dry_run=False):
    try:
        payload = read_cplus(session)
    except NoSession as e:
        if not allow_login:
            raise
        log.info("C+ session gone (%s); logging in once as %s", e, session_account())
        session.login()
        payload = read_cplus(session)

    beds = sum(len(w["beds"]) for w in payload["wards"])
    patients = sum(1 for w in payload["wards"] for b in w["beds"] if b["patient"])
    log.info("C+ %s: %d wards, %d beds, %d patients", session_account(), len(payload["wards"]), beds, patients)
    saved = save_payload(payload)

    if dry_run:
        for line in summarise(payload["wards"]):
            print("  " + line)
        print(f"  payload saved to {os.path.relpath(saved)} (gitignored; it has patient data). Nothing sent.")
        return

    result = SmartWardClient().push(payload)
    keys = ("wards_created", "beds_created", "beds_updated", "admitted", "transferred", "updated", "unchanged",
            "discharged", "ed_admitted_to_ward", "admission_dates_corrected")
    log.info("SmartWard %s: %s", (result.get("hospital") or {}).get("name"), ", ".join(f"{k} {result.get(k, 0)}" for k in keys))
    if result.get("unmatched_physicians"):
        log.info("physicians with no SmartWard consultant of that name: %d", len(result["unmatched_physicians"]))
    for reason in result.get("skipped") or []:
        log.warning("skipped: %s", reason)


def check():
    try:
        info = SmartWardClient().ping()
    except SmartWardError as e:
        print(f"[-] {e}")
        return 1
    hospital = info.get("hospital")
    if not hospital:
        print(f"[-] SmartWard ({info.get('app')}) answered, but has no hospital for C+ wards: "
              "set CPLUS_SYNC_HOSPITAL_ID in its .env.")
        return 1
    print(f"[+] SmartWard ({info.get('app')}) accepts the token of integration user "
          f"{info.get('integration_user')!r}. C+ wards go to {hospital['name']} "
          f"(id {hospital['id']}); {info.get('linked_wards', 0)} ward(s) linked so far.")
    return 0


def main():
    ap = argparse.ArgumentParser(description="Keep SmartWard in step with C+ Bed Management.")
    ap.add_argument("--check", action="store_true", help="test the SmartWard address, token and hospital only")
    ap.add_argument("--dry-run", action="store_true", help="read C+ and save the payload; send nothing")
    ap.add_argument("--once", action="store_true", help="one sync, then exit")
    ap.add_argument("--allow-login", action="store_true",
                    help="log in to C+ by itself when the session is gone (one attempt, see login.py)")
    args = ap.parse_args()

    if args.check:
        return check()

    setup_logging()
    session = HISSession()

    while True:
        try:
            sync_once(session, args.allow_login, dry_run=args.dry_run)
            code = 0
        except (NoSession, LoginBlocked) as e:
            # The loop waits instead of exiting, so a service keeps running and
            # carries on by itself once someone runs login.py (or clears the block).
            log.error("%s%s", e, "" if args.once or args.dry_run else
                      f" Checking again in {SYNC_INTERVAL_MINUTES:g} min.")
            code = 2
        except LoginTooSoon as e:
            log.warning("%s", e)
            code = 1
        except (SessionError, BedManagementError, SmartWardError) as e:
            log.error("sync failed: %s", e)
            code = 3 if "must be set" in str(e) else 1

        if args.once or args.dry_run or code == 3:
            return code
        time.sleep(SYNC_INTERVAL_MINUTES * 60)


if __name__ == "__main__":
    sys.exit(main())
