"""
Observation interpreter for the Comen NC5 gateway (stdlib only).

Turns one HL7 message into a normalized reading whose field names are exactly
the ones POST /api/v1/vital-signs accepts:

    {
        "message_type": str,            # MSH-9, e.g. ORU^R01
        "patient_id":   str,            # PID-3 (fallback PID-2 / PID-4 / PID-18)
        "patient_name": str,            # PID-5, components joined
        "bed":          str,            # PV1-3 bed, for logging/notes only
        "measured_at":  datetime|None,  # newest observation time in the message
        "control_id":   str,            # MSH-10
        "vitals":       {field: float}, # API field names, API units
        "observed_at":  {field: datetime},  # OBX-14 -> OBR-7 -> MSH-7, per value
        "sources":      {field: str},   # e.g. {"blood_pressure_systolic": "ART"}
        "context":      {label: str},   # e.g. {"ACVPU": "Alert", "Temp site": "Ear"}
    }

`observed_at` is per value on purpose: the NC5 repeats the last completed NIBP
in later messages, so the caller needs to know how old each number really is
before charting it.

The NC5 codes observations with IEEE 11073 (MDC) identifiers and sends them as
`code^MNEMONIC^MDC` (e.g. `188740^MDC_LEN_BODY_ACTUAL^MDC`), with units coded
the same way (`263441^MDC_DIM_CENTI_M^MDC`). Matching is therefore done on the
mnemonic *family* first — that survives firmware revisions renumbering codes —
then on well-known numeric/LOINC codes, then on human-readable text. Values are
converted to the units SmartWard stores (degC, kg, cm).
"""

import re
from typing import Dict, Optional, Tuple

from hl7_common import (
    component,
    field,
    parse_hl7_timestamp,
    split_segments,
    subcomponent,
)

# API field -> (min, max) sanity bounds are applied by the caller (they are
# configurable); this module only decides *what* a value is and in which unit.

# IEEE 11073 "value not available" sentinels seen from bedside monitors.
_INVALID_VALUES = {8388607.0, -8388608.0, 2147483647.0, -2147483648.0}

# Temperature sites that represent the patient's body temperature. Skin and
# blood temperatures are accepted too, but only if nothing better is present.
_CORE_TEMP = {
    "MDC_TEMP", "MDC_TEMP_BODY", "MDC_TEMP_CORE", "MDC_TEMP_RECT",
    "MDC_TEMP_TYMP", "MDC_TEMP_ORAL", "MDC_TEMP_AXIL", "MDC_TEMP_EAR",
}

# Well-known fixed codes: LOINC (text-coded senders / the bench simulator) and
# the MDC numerics the NC5 and its Mindray-compatible peers emit.
# value = (api_field, priority, source_label)
_CODE_RULES: Dict[str, Tuple[str, int, Optional[str]]] = {
    # LOINC
    "8480-6": ("blood_pressure_systolic", 0, None),
    "8462-4": ("blood_pressure_diastolic", 0, None),
    "8867-4": ("heart_rate", 0, None),
    "8889-8": ("pulse_rate", 0, None),
    "2710-2": ("spo2", 0, None),
    "2708-6": ("spo2", 0, None),
    "59408-5": ("spo2", 0, None),
    "8310-5": ("temperature", 0, None),
    "9279-1": ("respiratory_rate", 0, None),
    "29463-7": ("weight", 0, None),
    "3141-9": ("weight", 0, None),
    "8302-2": ("height", 0, None),
    "3137-7": ("height", 0, None),
    # IEEE 11073 numerics. The NC5 sends cuff pressures as MDC_PRESS_CUFF_*
    # (1503xx), not the MDC_PRESS_BLD_NONINV_* family other monitors use.
    "150301": ("blood_pressure_systolic", 0, "NIBP"),
    "150302": ("blood_pressure_diastolic", 0, "NIBP"),
    "150017": ("blood_pressure_systolic", 0, "NIBP"),
    "150018": ("blood_pressure_diastolic", 0, "NIBP"),
    "150021": ("blood_pressure_systolic", 1, "ART"),
    "150022": ("blood_pressure_diastolic", 1, "ART"),
    "147842": ("heart_rate", 0, None),
    "149530": ("pulse_rate", 0, None),
    "150456": ("spo2", 0, None),
    "150344": ("temperature", 0, None),
    "151562": ("respiratory_rate", 0, None),
    "188736": ("weight", 0, None),
    "188740": ("height", 0, None),
}

# Last resort for senders that only put a label in OBX-3.2.
_TEXT_RULES = [
    ("blood_pressure_systolic", 2, re.compile(r"\b(NIBP[\s_-]*S(YS)?|SYS(TOLIC)?)\b", re.I)),
    ("blood_pressure_diastolic", 2, re.compile(r"\b(NIBP[\s_-]*D(IA)?|DIA(STOLIC)?)\b", re.I)),
    ("heart_rate", 2, re.compile(r"\b(HR|HEART\s*RATE)\b", re.I)),
    ("pulse_rate", 2, re.compile(r"\b(PR|PULSE(\s*RATE)?)\b", re.I)),
    ("spo2", 2, re.compile(r"\b(SPO2|SAO2|OXYGEN\s*SAT)", re.I)),
    ("temperature", 2, re.compile(r"\b(TEMP(ERATURE)?|T\d)\b", re.I)),
    ("respiratory_rate", 2, re.compile(r"\b(RR|RESP(IRATION)?(\s*RATE)?)\b", re.I)),
    ("weight", 2, re.compile(r"\bWEIGHT\b", re.I)),
    ("height", 2, re.compile(r"\bHEIGHT\b", re.I)),
]

# Mean/MAP pressures have no SmartWard field and must never be mistaken for a
# systolic reading.
_MEAN_TEXT = re.compile(r"\b(MAP|MEAN)\b", re.I)

# COMEN private observations that carry clinical context rather than a number.
# The API has no column for these, so they are surfaced in the reading's notes
# instead of being dropped - ACVPU in particular is part of the NEWS2 score.
_COMEN_CONTEXT = {
    "COMEN_ACVPU": "ACVPU",
    "COMEN_PAIN_LEVEL": "Pain",
    "COMEN_TEMP_POS": "Temp site",
    "COMEN_NIBP_POS": "NIBP site",
    "COMEN_O2_SRC": "O2 source",
    "COMEN_HANDLER": "By",
}
# "Other" is what the monitor sends for "not recorded" - not worth charting.
_CONTEXT_NOISE = {"", "OTHER", "NONE", "N/A", "UNKNOWN"}


def _bp_source(mnemonic: str) -> str:
    if "NONINV" in mnemonic:
        return "NIBP"
    if "PULM" in mnemonic:
        return "PAP"
    if "ART" in mnemonic or "ABP" in mnemonic:
        return "ART"
    return "IBP"


def _match_mnemonic(mnemonic: str) -> Optional[Tuple[str, int, Optional[str]]]:
    """Map an MDC mnemonic onto an API field by nomenclature family."""
    m = mnemonic.strip().upper()
    if not m.startswith("MDC_"):
        return None

    if m.startswith("MDC_PRESS_CUFF") or m.startswith("MDC_PRESS_BLD"):
        # MDC_PRESS_CUFF_* is what the NC5 sends for NIBP; MDC_PRESS_BLD_* is
        # the family other monitors use. A cuff is non-invasive by definition;
        # an arterial line is only used when there is no NIBP in the window.
        source = "NIBP" if m.startswith("MDC_PRESS_CUFF") else _bp_source(m)
        priority = 0 if source == "NIBP" else 1
        if m.endswith("_SYS"):
            return ("blood_pressure_systolic", priority, source)
        if m.endswith("_DIA"):
            return ("blood_pressure_diastolic", priority, source)
        return None  # _MEAN / _PULS_RATE / anything else: no API field
    if "SAT_O2" in m or m.endswith("_SPO2"):
        return ("spo2", 0, None)
    if "HEART_RATE" in m:
        return ("heart_rate", 0, None)
    if "PULS" in m and "RATE" in m:
        return ("pulse_rate", 0, None)
    if m.startswith("MDC_TEMP"):
        if "DELT" in m or "GRAD" in m:
            return None  # delta-T between two probes, not a body temperature
        return ("temperature", 0 if m in _CORE_TEMP else 1, None)
    if "RESP_RATE" in m:
        # Impedance/thoracic RR is the primary; airway and capnography RR are
        # equivalent clinically but only used when the primary is absent.
        primary = m in ("MDC_RESP_RATE", "MDC_TTHOR_RESP_RATE")
        return ("respiratory_rate", 0 if primary else 1, None)
    if m == "MDC_MASS_BODY_ACTUAL":
        return ("weight", 0, None)
    if m == "MDC_LEN_BODY_ACTUAL":
        return ("height", 0, None)
    return None


def _match_observation(identifier: str) -> Optional[Tuple[str, int, Optional[str]]]:
    """Resolve OBX-3 (`code^text^system`) to (field, priority, source)."""
    code = component(identifier, 0)
    text = component(identifier, 1)
    system = component(identifier, 2).upper()

    if system == "99COMEN":
        return None  # private context (ACVPU, pain, probe site) - not a vital

    match = _match_mnemonic(text)
    if match:
        return match
    if code in _CODE_RULES:
        return _CODE_RULES[code]
    if text.upper().startswith("MDC_"):
        # A coded MDC observation we deliberately do not chart (MDC_TEMP_DELTA,
        # MDC_PRESS_CUFF_MEAN, ...). The nomenclature is authoritative; falling
        # through to the fuzzy text rules below would mis-file it.
        return None

    # Text-coded sender: underscores are word separators, so "NBP_SYS" and
    # "NBP Systolic" are matched the same way.
    label = (text or identifier).replace("_", " ")
    if _MEAN_TEXT.search(label):
        return None
    for api_field, priority, pattern in _TEXT_RULES:
        if pattern.search(label):
            return (api_field, priority, None)
    return None


def _to_float(value: str) -> Optional[float]:
    if not value:
        return None
    match = re.search(r"-?\d+(?:\.\d+)?", value.split("^", 1)[0])
    if not match:
        return None
    try:
        number = float(match.group(0))
    except ValueError:
        return None
    return None if number in _INVALID_VALUES else number


def _units_label(raw_units: str) -> str:
    """OBX-6 is `code^MNEMONIC^system` for MDC senders, plain text otherwise."""
    return (component(raw_units, 1) or component(raw_units, 0)).strip().upper()


def _normalize_units(api_field: str, value: float, units: str) -> float:
    u = units.replace("MDC_DIM_", "")
    if api_field == "temperature":
        if u.startswith("FAHR") or (("F" in u) and "C" not in u.replace("DEG", "")):
            return round((value - 32.0) * 5.0 / 9.0, 1)
        return value
    if api_field == "weight":
        if u in ("LB", "POUND", "LBS") or "LB" in u:
            return round(value * 0.45359237, 1)
        if u in ("G", "GRAM"):
            return round(value / 1000.0, 1)
        return value
    if api_field == "height":
        if u in ("M", "METER", "METRE"):
            return round(value * 100.0, 1)
        if u in ("MILLI_M", "MM"):
            return round(value / 10.0, 1)
        if u in ("INCH", "IN", "[IN_I]"):
            return round(value * 2.54, 1)
        return value
    return value


def _bed_from_pv1(seg) -> str:
    """PV1-3 is a PL: point-of-care^room^bed^... The NC5 sends `^^&24`, i.e. the
    bed number as a sub-component of the bed component."""
    location = field(seg, 3)
    if not location:
        return ""
    bed = component(location, 2)
    if "&" in bed:
        parts = [p for p in bed.split("&") if p]
        bed = parts[-1] if parts else ""
    if not bed:
        # Some builds put the bed in the facility sub-component of PV1-3.1.
        poc = component(location, 0)
        bed = subcomponent(poc, 1) if "&" in poc else ""
    return bed.strip()


def parse_message(hl7: str) -> Optional[Dict]:
    """Parse any inbound message into the normalized reading shape.

    Returns None if the message has no MSH. Callers decide what to do with it
    based on `message_type`; only ORU messages carry vitals worth storing.
    """
    segments = split_segments(hl7)
    if not segments or not segments[0][0].startswith("MSH"):
        return None

    msh = segments[0]
    message_time = parse_hl7_timestamp(field(msh, 6))
    reading = {
        "message_type": field(msh, 8),
        "patient_id": "",
        "patient_name": "",
        "bed": "",
        "measured_at": message_time,
        "control_id": field(msh, 9),
        "vitals": {},
        "observed_at": {},
        "sources": {},
        "context": {},
    }
    priorities: Dict[str, int] = {}
    order_time = message_time  # current OBR-7, used when an OBX omits OBX-14

    for seg in segments[1:]:
        name = seg[0].upper()
        if name == "PID":
            for idx in (3, 2, 4, 18):
                candidate = component(field(seg, idx))
                if candidate:
                    reading["patient_id"] = candidate
                    break
            raw_name = field(seg, 5)
            if raw_name:
                family = component(raw_name, 0)
                given = component(raw_name, 1)
                reading["patient_name"] = " ".join(p for p in (given, family) if p)
        elif name == "PV1":
            reading["bed"] = _bed_from_pv1(seg)
        elif name == "OBR":
            ts = parse_hl7_timestamp(field(seg, 7))
            if ts:
                order_time = ts
        elif name == "OBX":
            value_type = field(seg, 2).upper()
            if value_type not in ("", "NM", "ST", "SN"):
                continue
            status = field(seg, 11).upper()
            if status in ("X", "D"):
                continue  # X = cannot obtain, D = deleted
            identifier = field(seg, 3)
            label = _COMEN_CONTEXT.get(component(identifier, 1).upper())
            if label:
                value_text = field(seg, 5).strip()
                if value_text.upper() not in _CONTEXT_NOISE:
                    reading["context"][label] = value_text
                continue
            match = _match_observation(identifier)
            if not match:
                continue
            api_field, priority, source = match
            if api_field in priorities and priorities[api_field] < priority:
                continue  # a better-coded observation already won this field
            value = _to_float(field(seg, 5))
            if value is None:
                continue
            value = _normalize_units(api_field, value, _units_label(field(seg, 6)))
            reading["vitals"][api_field] = value
            priorities[api_field] = priority
            if source:
                reading["sources"][api_field] = source
            else:
                reading["sources"].pop(api_field, None)
            observed = parse_hl7_timestamp(field(seg, 14)) or order_time
            if observed:
                reading["observed_at"][api_field] = observed

    if reading["observed_at"]:
        reading["measured_at"] = max(reading["observed_at"].values())
    elif order_time:
        reading["measured_at"] = order_time

    return reading


def is_observation(message_type: str) -> bool:
    """True for the message types that carry vitals (ORU^R01 and friends)."""
    return message_type.upper().startswith("ORU")
