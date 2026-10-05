"""The Isolation box on each patient's C+ admission card, for SmartWard.

C+ keeps isolation on the admission card (Bed Management, the bed's menu,
Patient Admission Card), one page per patient, linked from the bed's row:

  GET /PM//PatientAdmission/PatientAdmissionCard?patientId=<MRN>&bedId=<bed id>

Its Patient Information table lists flags, each an icon cell and a label cell:

  <td><i class="fa fa-times"></i></td><td>Isolation</td>

fa-times is "not ticked" and a check icon (fa-check...) "ticked". C+ records
only that the patient is isolated, not the kind. Any other icon is left unread
(None), and the first such page is saved under scratch\\ to look at.

Reading every card on every sync would be a page per patient every few
minutes, so a card is read again only after ISOLATION_REFRESH_MINUTES, at most
ISOLATION_MAX_READS per sync, never-read patients first. What was read is kept
in ISOLATION_CACHE_FILE by MRN and RN, so a new stay is read afresh.
"""

import json
import os
import time

from bs4 import BeautifulSoup

from config import HIS_BASE_URL, SCRATCH_DIR

UNREAD_SAMPLE = "admission_card_unread.html"


def parse_isolation(html):
    """True / False from the Isolation flag's icon; None when there is no such
    flag or its icon is not one of the two known ones."""
    for label in BeautifulSoup(html or "", "html.parser").find_all("td"):
        if label.get_text(" ", strip=True).lower() != "isolation":
            continue
        icon_cell = label.find_previous_sibling("td")
        icon = icon_cell.find("i") if icon_cell else None
        classes = " ".join(icon.get("class") or []) if icon else ""
        if "fa-check" in classes:
            return True
        if "fa-times" in classes:
            return False
        return None
    return None


def cache_key(patient):
    return f"{patient['mrn']}|{patient.get('rn') or ''}"


def load_cache(path):
    try:
        with open(path, encoding="utf-8") as fh:
            cache = json.load(fh)
        return cache if isinstance(cache, dict) else {}
    except (OSError, ValueError):
        return {}


def save_cache(path, cache):
    tmp = path + ".tmp"
    with open(tmp, "w", encoding="utf-8") as fh:
        json.dump(cache, fh)
    os.replace(tmp, path)


def due_for_reading(patients, cache, now, refresh_minutes, max_reads):
    """The patients whose card is read this sync: never read first, then the
    longest ago past the refresh time, at most max_reads."""
    due = []
    for p in patients:
        if not p.get("card"):
            continue
        entry = cache.get(cache_key(p))
        if entry is None:
            due.append((0, p))
        elif now - entry.get("read_at", 0) >= refresh_minutes * 60:
            due.append((entry.get("read_at", 0), p))
    due.sort(key=lambda item: item[0])
    return [p for _, p in due[:max_reads]]


def card_url(path):
    """The card's address on C+; None for a link that leads anywhere else."""
    if path.startswith("/"):
        return HIS_BASE_URL + path
    return path if path.startswith(HIS_BASE_URL + "/") else None


def strip_card_links(wards):
    """The card links are the RPA's own; SmartWard is not sent them."""
    for w in wards:
        for b in w["beds"]:
            if b["patient"]:
                b["patient"].pop("card", None)


def attach_isolation(wards, session, cache_file, refresh_minutes, max_reads, now=None):
    """Give each patient `isolation` (True / False) where their card has been
    read, reading the cards that are due. Returns counts for the log."""
    now = now if now is not None else time.time()
    patients = [b["patient"] for w in wards for b in w["beds"] if b["patient"]]
    cache = load_cache(cache_file)
    counts = {"read": 0, "unread": 0, "failed": 0}

    for p in due_for_reading(patients, cache, now, refresh_minutes, max_reads):
        url = card_url(p["card"])
        if not url:
            continue
        # A dropped session raises NoSession, which ends the sync as for the bed list
        resp = session.get(url)
        if not resp["success"] or resp["http_code"] != "200":
            counts["failed"] += 1
            continue
        ticked = parse_isolation(resp["stdout"])
        if ticked is None:
            counts["unread"] += 1
            sample = os.path.join(SCRATCH_DIR, UNREAD_SAMPLE)
            if not os.path.exists(sample):
                os.makedirs(SCRATCH_DIR, exist_ok=True)
                with open(sample, "w", encoding="utf-8") as fh:
                    fh.write(resp["stdout"] or "")
            continue
        counts["read"] += 1
        cache[cache_key(p)] = {"isolation": ticked, "read_at": now}

    current = {cache_key(p) for p in patients}
    cache = {key: entry for key, entry in cache.items() if key in current}
    save_cache(cache_file, cache)

    for p in patients:
        entry = cache.get(cache_key(p))
        if entry is not None:
            p["isolation"] = bool(entry["isolation"])
    strip_card_links(wards)

    counts["ticked"] = sum(1 for p in patients if p.get("isolation") is True)
    counts["known"] = sum(1 for p in patients if "isolation" in p)
    counts["patients"] = len(patients)
    return counts
