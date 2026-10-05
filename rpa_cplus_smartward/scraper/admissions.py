"""When each patient in a bed was admitted, for SmartWard's admission date.

Taken from C+ in this order (the user's choice, 2026-09-29):

  1. Entry Date in the Inpatient Daily Census (Bed Management -> Reports), the
     actual admission time. One read-only report per unit group:
       POST /PM/Patient/GetAdmissionVipDailyCensus  {"admissionType": "2"}
     Rows carry PatientID (MRN), RnNumber and EntryDate "DD.MM.YYYY HH:MM:SS".
     A patient in a ward bed can be listed under Day Care (6), so 2 and 6 are read.
  2. Request Date of the patient's reservation request (the Reservation
     Requests panel of Bed Management), for anyone the census does not list:
       GET /PM/Reservation/GetReservationRequest?planDate=DD.MM.YYYY&unitGroupId=2&...
     one GET per plan date from today back, and only while someone is missing.
     Each request's icon title carries "MRN : ..."; its text "Request Date: ...".
  3. Nothing: SmartWard keeps the time its sync first saw the patient.

C+ times are Malaysia local time; they go out as ISO 8601 with that offset.
"""

import json
import re
from datetime import date, datetime, timedelta, timezone
from urllib.parse import urlencode

from bs4 import BeautifulSoup

from config import HIS_BASE_URL, HIS_UTC_OFFSET_HOURS

CENSUS = "/PM/Patient/GetAdmissionVipDailyCensus"
AJAX = {"X-Requested-With": "XMLHttpRequest"}
LOCAL = timezone(timedelta(hours=HIS_UTC_OFFSET_HOURS))


class AdmissionsError(Exception):
    """The census could not be read."""


def parse_his_time(value):
    """'29.09.2026 10:15:00' (the time is optional) -> datetime, else None."""
    value = (value or "").strip()
    for fmt in ("%d.%m.%Y %H:%M:%S", "%d.%m.%Y %H:%M", "%d.%m.%Y"):
        try:
            return datetime.strptime(value, fmt)
        except ValueError:
            pass
    return None


def iso_local(when):
    return when.replace(tzinfo=LOCAL).isoformat(timespec="seconds")


def census_entries(session, unit_groups):
    """{mrn: [(rn, entry time), ...]} for everyone the census lists as still admitted."""
    out = {}
    for group in unit_groups:
        resp = session.post_report(CENSUS, {"admissionType": str(group)})
        try:
            data = json.loads(resp["stdout"] or "")
        except ValueError:
            raise AdmissionsError(f"The census for unit group {group} did not return JSON (HTTP {resp['http_code']}).")
        if not data.get("success"):
            raise AdmissionsError(f"The census for unit group {group} was refused.")
        for row in data.get("data") or []:
            mrn = str(row.get("PatientID") or "").strip()
            entry = parse_his_time(row.get("EntryDate"))
            if mrn and entry and not str(row.get("ExitDate") or "").strip():
                out.setdefault(mrn, []).append((str(row.get("RnNumber") or "").strip(), entry))
    return out


def parse_requests(html):
    """[(mrn, request time)] from one Reservation Requests list."""
    out = []
    for item in BeautifulSoup(html or "", "html.parser").select(".draggable-reservation-request-item"):
        title = " ".join(i.get("title") or "" for i in item.find_all("i", title=True))
        mrn = re.search(r"MRN\s*:\s*(\d+)", title)
        requested = re.search(r"Request Date\s*:\s*(\d{2}\.\d{2}\.\d{4}(?:\s+\d{2}:\d{2}(?::\d{2})?)?)",
                              " ".join(item.stripped_strings))
        when = parse_his_time(requested.group(1)) if requested else None
        if mrn and when:
            out.append((mrn.group(1), when))
    return out


def request_dates(session, unit_groups, wanted, days, today=None):
    """{mrn: request time} for the wanted MRNs, from the newest plan date back
    `days` days. Stops as soon as nobody is missing."""
    wanted, found = set(wanted), {}
    today = today or date.today()
    for back in range(days + 1):
        if not wanted - set(found):
            break
        plan_date = (today - timedelta(days=back)).strftime("%d.%m.%Y")
        for group in unit_groups:
            query = urlencode({"planDate": plan_date, "searchText": "", "unitGroupId": group,
                               "locationId": "", "doctorId": "", "isExitsPreRezervation": "F"})
            resp = session.get(f"{HIS_BASE_URL}/PM/Reservation/GetReservationRequest?{query}", headers=AJAX)
            for mrn, when in parse_requests(resp["stdout"]):
                if mrn in wanted and mrn not in found:
                    found[mrn] = when
    return found


def apply_census(patients, census):
    """Set admitted_at from the census: the row of this stay (same RN), else the
    MRN's latest entry. Returns the patients it had nothing for."""
    missing = []
    for p in patients:
        rows = census.get(p["mrn"]) or []
        entry = next((e for rn, e in rows if rn and rn == p.get("rn")), None) or max((e for _, e in rows), default=None)
        if entry:
            p["admitted_at"], p["admitted_at_source"] = iso_local(entry), "census"
        else:
            missing.append(p)
    return missing


def apply_requests(patients, requested):
    for p in patients:
        if p["mrn"] in requested:
            p["admitted_at"], p["admitted_at_source"] = iso_local(requested[p["mrn"]]), "request"


def attach_admission_dates(wards, session, census_groups, request_groups, request_days):
    """Give the payload's patients `admitted_at` / `admitted_at_source` where
    C+ has a date. Returns how many came from where."""
    patients = [b["patient"] for w in wards for b in w["beds"] if b["patient"]]
    missing = apply_census(patients, census_entries(session, census_groups))
    if missing:
        apply_requests(missing, request_dates(session, request_groups, [p["mrn"] for p in missing], request_days))

    counts = {"census": 0, "request": 0, "none": 0}
    for p in patients:
        counts[p.get("admitted_at_source") or "none"] += 1
    return counts
