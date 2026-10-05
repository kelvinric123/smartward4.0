"""Open C+ Bed Management with the saved session and list its wards.

    venv\\Scripts\\python.exe bed_management.py                  Ward (IP), unit group 2
    venv\\Scripts\\python.exe bed_management.py --unit-group 6   Day Care Unit

Never logs in: when the session is gone it stops and says so (login.py is the
only way in). Read-only, GETs only:

  1. GET /pm/Reservation/BedManagement              the page itself, saved to scratch\\
  2. GET /PM/Global/RetrieveDropDownByJson          its Location dropdown (the wards)
         method=ReservationInpatientLocation, inputParameters[P_UNIT_GROUP]=<unit group>

Ward ids differ per facility; the ones printed are for the facility the
session logged in to.
"""

import argparse
import json
import os
import sys
from urllib.parse import urlencode

from config import BED_MANAGEMENT_URL, SCRATCH_DIR, WARD_LIST_URL
from core.session import HISSession, NoSession, SessionError, is_runtime_error, page_title, session_account

if sys.stdout.encoding != "utf-8":
    sys.stdout.reconfigure(encoding="utf-8")

AJAX = {"X-Requested-With": "XMLHttpRequest"}
UNIT_GROUPS = {"2": "Ward (IP)", "6": "Day Care Unit (DC)"}


def wards(session, unit_group):
    """The page's Location dropdown for a unit group, as [{id, name}]."""
    query = urlencode({
        "method": "ReservationInpatientLocation",
        "firstItemKey": "",
        "firstItemValue": "",
        # jQuery serialises the nested inputParameters object this way.
        "inputParameters[P_UNIT_GROUP]": unit_group,
    })
    resp = session.get(f"{WARD_LIST_URL}?{query}", headers=AJAX)
    try:
        data = json.loads(resp["stdout"])
    except ValueError:
        raise SessionError(f"The ward list did not return JSON (HTTP {resp['http_code']}).")
    rows = data.get("result") if isinstance(data, dict) else data
    return [
        {"id": str(w.get("id") or "").strip(), "name": (w.get("text") or "").strip()}
        for w in (rows or [])
        if str(w.get("id") or "").strip()
    ]


def main():
    ap = argparse.ArgumentParser(description="Check C+ Bed Management with the saved session.")
    ap.add_argument("--unit-group", default="2", choices=sorted(UNIT_GROUPS),
                    help="2 = Ward (IP), 6 = Day Care Unit. Default 2.")
    args = ap.parse_args()

    session = HISSession()
    try:
        resp = session.get(BED_MANAGEMENT_URL)
        html = resp["stdout"] or ""
        if not resp["success"] or resp["http_code"] != "200":
            raise SessionError(f"Bed Management answered HTTP {resp['http_code']}: {resp['stderr'].strip()}")
        if is_runtime_error(html):
            raise SessionError("C+ returned a runtime error for Bed Management.")

        os.makedirs(SCRATCH_DIR, exist_ok=True)
        saved = os.path.join(SCRATCH_DIR, "bed_management.html")
        with open(saved, "w", encoding="utf-8") as fh:
            fh.write(html)

        print(f"[+] Bed Management is open as {session_account()}")
        print(f"    {resp['url']} (HTTP {resp['http_code']}, {page_title(html)!r}, {len(html):,} bytes)")
        print(f"    page saved to {os.path.relpath(saved)} (gitignored)")
        if "/reservation/bedmanagement" not in (resp["url"] or "").lower():
            print("[!] C+ redirected away from Bed Management; see the saved page.")

        found = wards(session, args.unit_group)
    except NoSession as e:
        print(f"[-] {e}")
        return 2
    except SessionError as e:
        print(f"[-] {e}")
        return 1

    print(f"\n=== Location dropdown, unit group {args.unit_group} ({UNIT_GROUPS[args.unit_group]}) ===")
    for w in found:
        print(f"  {w['id']:>6}  {w['name']}")
    print(f"\n  {len(found)} ward(s)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
