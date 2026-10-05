"""C+ Bed Management with "All Locations": every ward, bed and patient in one read.

Read-only, one GET per unit group, the call the page makes when "All
Locations" is picked in its Location dropdown (value "XXX", see the page's
bed-management.js):

  GET /PM/Global/GetRoomsByStyle?location=XXX&locationType=1&unitGroupId=<2|6|3>
      &roomListStyle=list&isShowPreReservation=false&pageId=<1|2>  (+ empty filters)

ER Management (/pm/Reservation/ErManagement) is the same screen with page 2
and unit group 3 "Emergency (ER)": its locations are the ED's zones (ED - RED
ZONE, ED - YELLOW ZONE, ED - GREEN ZONE, ED - WAITING AREA...), in the same rows.

The answer is the table body, one <tr> per bed. The first cell, "Location /
Room No / Type", is only on the first bed of each room (rowspan), so the
columns are read from the end of that cell, and location, room and bed come
from the hidden inputs every row carries:

  dropLocationID / dropLocationName   the ward (C+ location)
  dropRoomNo / dropRoomName           the room
  dropDoorNo / dropBedName            the bed id and its name, e.g. 12646 / 217-A
  dropBedType                         the "Type" (empty at PHAK)

The columns after the first cell: status icon, bed, "MRN - RN",
gender icon + name, physician, note, detail, menu. An occupied ward bed's menu
links the patient's admission card (/PM//PatientAdmission/PatientAdmissionCard
?patientId=<MRN>&bedId=<bed id>), where C+ keeps the Isolation box
(scraper/admission_card.py). ER Management's rows have no such link.

Bed status is the icon's colour (no field for it):
  #999999 grey   occupied (the row has an MRN)
  #FFC914 yellow occupied too (its meaning is not confirmed; kept as the colour)
  #00FF00 green  available
  #FF3333 red    out of service
"""

import re
from urllib.parse import urlencode

from bs4 import BeautifulSoup

from config import HIS_BASE_URL

AJAX = {"X-Requested-With": "XMLHttpRequest"}
ALL_LOCATIONS = "XXX"
UNIT_GROUPS = {"2": "Ward (IP)", "6": "Day Care Unit (DC)", "3": "Emergency (ER)"}
ER_UNIT_GROUP = "3"

STATUS_BY_COLOUR = {
    "#00FF00": "available",
    "#FF3333": "out_of_service",
}


class BedManagementError(Exception):
    """The bed list could not be read."""


def all_locations_url(unit_group):
    query = urlencode({
        "location": ALL_LOCATIONS,
        "locationType": "1",
        "unitGroupId": str(unit_group),
        "bedStatus": "",
        "roomTypes": "",
        "physicion": "",  # sic - C+ spells it so
        "searchText": "",
        "roomListStyle": "list",
        "isShowPreReservation": "false",
        # 1 = Bed Management, 2 = ER Management
        "pageId": "2" if str(unit_group) == ER_UNIT_GROUP else "1",
    })
    return f"{HIS_BASE_URL}/PM/Global/GetRoomsByStyle?{query}"


def read_all_locations(session, unit_group="2"):
    """Every bed of every location in the unit group, as parsed rows."""
    resp = session.get(all_locations_url(unit_group), headers=AJAX)
    body = resp["stdout"] or ""
    if not resp["success"] or resp["http_code"] != "200":
        raise BedManagementError(f"Bed list answered HTTP {resp['http_code']}: {resp['stderr'].strip()}")
    if "<title>" in body.lower() and "runtime error" in body.lower():
        raise BedManagementError("C+ returned a runtime error for the bed list.")
    beds = parse_rows(body)
    for bed in beds:
        bed["unit_group"] = str(unit_group)
    return beds


def parse_rows(html):
    beds = []
    for tr in BeautifulSoup(html or "", "html.parser").find_all("tr"):
        bed = parse_row(tr)
        if bed:
            beds.append(bed)
    return beds


def parse_row(tr):
    hidden = {i.get("id"): (i.get("value") or "").strip() for i in tr.find_all("input", type="hidden") if i.get("id")}
    if not hidden.get("dropDoorNo"):
        return None

    tds = tr.find_all("td", recursive=False)
    first = 1 if tds and "location-info" in (tds[0].get("class") or []) else 0
    cells = tds[first:]
    if len(cells) < 6:
        return None

    icon = cells[0].find("i")
    colour = _colour(icon.get("style") if icon else "")
    mrn, rn = split_mrn_rn(cells[2].get_text(" ", strip=True))

    gender_icon = cells[3].find("i") or cells[3].find("img")
    gender = (gender_icon.get("title") or "").strip() if gender_icon else ""

    label = cells[5].find("label")
    note = ((label.get("title") or "").strip() or label.get_text(" ", strip=True)) if label else cells[5].get_text(" ", strip=True)
    card = tr.find("a", href=re.compile("PatientAdmissionCard", re.I))

    if mrn:
        status = "occupied"
    else:
        status = STATUS_BY_COLOUR.get(colour, "unknown")

    return {
        "location_id": hidden.get("dropLocationID", ""),
        "location_name": hidden.get("dropLocationName", ""),
        "room_no": hidden.get("dropRoomNo", ""),
        "room_name": hidden.get("dropRoomName", ""),
        "bed_id": hidden["dropDoorNo"],
        "bed_name": hidden.get("dropBedName", "") or cells[1].get_text(" ", strip=True),
        "type": hidden.get("dropBedType", ""),
        "status": status,
        "colour": colour,
        "mrn": mrn,
        "rn": rn,
        "patient_name": cells[3].get_text(" ", strip=True) if mrn else "",
        "gender": gender if mrn else "",
        "physician": cells[4].get_text(" ", strip=True) if mrn else "",
        "note": note.strip("()").strip(),
        "card_path": (card.get("href") or "").strip() if card and mrn else "",
    }


def split_mrn_rn(text):
    """'3200112068 - PHAK26IP09000087' -> (mrn, rn). An empty bed shows '-'."""
    text = (text or "").strip()
    if not text or text == "-":
        return "", ""
    m = re.match(r"^(\d+)\s*-\s*([A-Za-z0-9]*)$", text)
    if m:
        return m.group(1), m.group(2)
    bits = [b.strip() for b in text.split("-", 1)]
    return bits[0], (bits[1] if len(bits) > 1 else "")


def _colour(style):
    m = re.search(r"color\s*:\s*(#[0-9A-Fa-f]{3,6}|[a-zA-Z]+)", style or "")
    return m.group(1).upper() if m else ""


def to_wards(beds):
    """Rows grouped by location in C+'s order, in the shape SmartWard's
    /api/cplus/bed-sync takes. The same bed read twice (two unit groups) counts once."""
    wards, seen = {}, set()
    for b in beds:
        if b["bed_id"] in seen:
            continue
        seen.add(b["bed_id"])
        ward = wards.setdefault(b["location_id"], {
            "id": b["location_id"], "name": b["location_name"], "unit_group": b.get("unit_group", ""), "beds": [],
        })
        ward["beds"].append({
            "id": b["bed_id"],
            "name": b["bed_name"],
            "room_no": b["room_no"],
            "type": b["type"],
            "status": b["status"],
            "colour": b["colour"],
            "patient": {
                "mrn": b["mrn"],
                "rn": b["rn"],
                "name": b["patient_name"],
                "gender": b["gender"],
                "physician": b["physician"],
                # Read for the Isolation box, then taken out before the push
                "card": b.get("card_path", ""),
            } if b["mrn"] else None,
        })
    return list(wards.values())


def summarise(wards):
    """Counts per ward, without any patient detail, for the console and log."""
    lines = []
    for w in wards:
        counts = {}
        for b in w["beds"]:
            counts[b["status"]] = counts.get(b["status"], 0) + 1
        detail = ", ".join(f"{n} {s.replace('_', ' ')}" for s, n in sorted(counts.items()))
        lines.append(f"{w['id']:>6}  {w['name'][:40]:40}  {len(w['beds']):3} beds: {detail}")
    return lines
