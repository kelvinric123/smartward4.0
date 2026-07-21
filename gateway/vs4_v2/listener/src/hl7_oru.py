"""
ORU^R01 interpreter for the VS4 v2 gateway (stdlib only).

Turns one HL7 observation message into a normalized reading:

    {
        "patient_id":   str,            # PID-3 (fallback PID-2 / PID-4)
        "patient_name": str,            # PID-5, components joined
        "measured_at":  datetime|None,  # OBR-7 -> OBX-14 -> MSH-7
        "control_id":   str,            # MSH-10
        "vitals":       {field: float}, # normalized units (degC, kg, cm)
    }

Vitals are matched on the OBX-3 identifier: first on well-known codes
(LOINC + common device codes), then on the human-readable text as a fallback,
so firmware variations in coding do not silently drop readings. Values are
converted to the units the SmartWard API expects (Celsius, kg, cm).
"""

import re
from datetime import datetime
from typing import Dict, List, Optional


# field -> (exact codes, text regex). Checked in order; first match wins.
_VITAL_RULES = [
    ("blood_pressure_systolic", {"8480-6", "0002-4a05", "150017"},
     re.compile(r"\b(SYS|SYSTOLIC|NBP[\s_-]*S)\b", re.I)),
    ("blood_pressure_diastolic", {"8462-4", "0002-4a06", "150018"},
     re.compile(r"\b(DIA|DIASTOLIC|NBP[\s_-]*D)\b", re.I)),
    ("pulse_rate", {"8867-4", "8889-8", "149530", "0002-4182"},
     re.compile(r"\b(HR|PR|PULSE|HEART\s*RATE)\b", re.I)),
    ("spo2", {"2710-2", "59408-5", "2708-6", "150456", "0002-4bb8"},
     re.compile(r"\b(SPO2|SAO2|OXYGEN\s*SAT)", re.I)),
    ("temperature", {"8310-5", "150364", "0002-4b48"},
     re.compile(r"\b(TEMP|TEMPERATURE)\b", re.I)),
    ("respiratory_rate", {"9279-1", "151562", "0002-4a15"},
     re.compile(r"\b(RR|RESP|RESPIRATION|RESP\s*RATE)\b", re.I)),
    ("weight", {"29463-7", "3141-9"},
     re.compile(r"\bWEIGHT\b", re.I)),
    ("height", {"8302-2", "3137-7"},
     re.compile(r"\bHEIGHT\b", re.I)),
]

# Fields where the mean/MAP variant must NOT be mis-matched as systolic.
_MAP_TEXT = re.compile(r"\b(MAP|MEAN)\b", re.I)


def split_segments(hl7: str) -> List[List[str]]:
    """Split a message into segments, each a list of |-separated fields."""
    segments = []
    for raw in hl7.replace("\n", "\r").split("\r"):
        raw = raw.strip()
        if raw:
            segments.append(raw.split("|"))
    return segments


def _field(seg: List[str], index: int) -> str:
    return seg[index].strip() if index < len(seg) else ""


def _component(value: str, index: int = 0) -> str:
    # First repetition, then requested component.
    first_rep = value.split("~", 1)[0]
    parts = first_rep.split("^")
    return parts[index].strip() if index < len(parts) else ""


def parse_hl7_timestamp(value: str) -> Optional[datetime]:
    """Parse an HL7 TS (YYYYMMDD[HHMM[SS]] with optional fraction/zone)."""
    if not value:
        return None
    value = value.strip()
    # Strip timezone offset and fractional seconds; monitor local time is what
    # the ward documents against.
    value = re.split(r"[+\-]", value, maxsplit=1)[0].split(".", 1)[0]
    for fmt in ("%Y%m%d%H%M%S", "%Y%m%d%H%M", "%Y%m%d"):
        try:
            return datetime.strptime(value, fmt)
        except ValueError:
            continue
    return None


def _to_float(value: str) -> Optional[float]:
    if not value:
        return None
    # Value may arrive as a component (e.g. "120^mmHg") or with stray text.
    match = re.search(r"-?\d+(?:\.\d+)?", value.split("^", 1)[0])
    try:
        return float(match.group(0)) if match else None
    except ValueError:
        return None


def _normalize_units(field: str, value: float, units: str) -> float:
    u = (units or "").lower()
    if field == "temperature" and ("f" in u.replace("deg", "").replace("[", "")
                                   and "c" not in u):
        return round((value - 32.0) * 5.0 / 9.0, 1)
    if field == "weight" and ("lb" in u or "pound" in u):
        return round(value * 0.45359237, 1)
    if field == "height":
        if u.startswith("m") and "mm" not in u and "cm" not in u:
            return round(value * 100.0, 1)
        if "in" in u:
            return round(value * 2.54, 1)
    return value


def _match_field(obx_code: str, obx_text: str) -> Optional[str]:
    code = obx_code.strip().lower()
    text = obx_text.strip()
    if _MAP_TEXT.search(text):
        return None  # ignore MAP/mean pressures; the API has no field for them
    for field, codes, pattern in _VITAL_RULES:
        if code and code in codes:
            return field
        if text and pattern.search(text):
            return field
    return None


def parse_oru(hl7: str) -> Optional[Dict]:
    """Parse an ORU^R01 into a normalized reading, or None if not an ORU."""
    segments = split_segments(hl7)
    if not segments or not segments[0][0].startswith("MSH"):
        return None

    msh = segments[0]
    message_type = _field(msh, 8).replace("^", "_")
    if not message_type.upper().startswith("ORU"):
        return None

    reading = {
        "patient_id": "",
        "patient_name": "",
        "measured_at": parse_hl7_timestamp(_field(msh, 6)),
        "control_id": _field(msh, 9),
        "vitals": {},
    }

    for seg in segments[1:]:
        name = seg[0].upper()
        if name == "PID":
            # PID-3 (identifier list) first; some senders use PID-2 or PID-4.
            for idx in (3, 2, 4):
                candidate = _component(_field(seg, idx))
                if candidate:
                    reading["patient_id"] = candidate
                    break
            raw_name = _field(seg, 5)
            if raw_name:
                family = _component(raw_name, 0)
                given = _component(raw_name, 1)
                reading["patient_name"] = " ".join(p for p in (given, family) if p)
        elif name == "OBR":
            ts = parse_hl7_timestamp(_field(seg, 7))
            if ts:
                reading["measured_at"] = ts
        elif name == "OBX":
            obx_type = _field(seg, 2).upper()
            if obx_type not in ("", "NM", "ST", "SN"):
                continue
            identifier = _field(seg, 3)
            field = _match_field(_component(identifier, 0), _component(identifier, 1) or identifier)
            if not field:
                continue
            value = _to_float(_field(seg, 5))
            if value is None:
                continue
            units = _component(_field(seg, 6), 1) or _component(_field(seg, 6), 0)
            reading["vitals"][field] = _normalize_units(field, value, units)
            # OBX-14 (observation datetime) refines the capture time.
            ts = parse_hl7_timestamp(_field(seg, 14))
            if ts and reading["measured_at"] is None:
                reading["measured_at"] = ts

    return reading
