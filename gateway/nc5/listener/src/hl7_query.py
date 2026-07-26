"""
Patient-query responder for the Comen NC5 gateway (stdlib only).

The NC5 asks the gateway who a patient is before it will admit them to a bed.
Two dialects show up on the wire, and both are answered here from the *same*
SmartWard record so the monitor shows exactly what the ward system holds:

    QRY^R02  ->  ORF^R04   the NC5's own query (patient id in QRD-8)
    QRY^A19  ->  ADR^A19   the Mindray/eGateway ADT query dialect

Message shapes follow what this monitor was observed to accept: the query
segments (QRD, QRF) are echoed back verbatim, PID carries the id in both PID-3
and PID-18, and PV1-3 carries ward/bed in the dialect's own layout.
"""

import threading
from typing import Dict, List, Optional

from hl7_common import component, escape, field, find_segment, hl7_stamp, split_segments

__all__ = ["parse_query", "is_query", "build_response", "format_patient_name"]

_counter_lock = threading.Lock()
_counter = 0


def _next_message_id(prefix: str) -> str:
    global _counter
    with _counter_lock:
        _counter = (_counter + 1) % 100000
        return f"{prefix}{hl7_stamp()}{_counter}"


def is_query(message_type: str) -> bool:
    return message_type.upper().startswith("QRY")


def parse_query(hl7: str) -> Optional[Dict]:
    """Pull everything needed to answer a QRY out of the inbound message."""
    segments = split_segments(hl7)
    if not segments or not segments[0][0].startswith("MSH"):
        return None
    msh = segments[0]
    if not is_query(field(msh, 8)):
        return None

    qrd = find_segment(segments, "QRD")
    qrf: List[str] = ["|".join(seg) for seg in segments if seg and seg[0].upper() == "QRF"]

    # QRD-8 (Who Subject Filter) holds what the nurse typed on the monitor; the
    # PID echoed in the query is a fallback for firmware that leaves QRD-8 empty.
    patient_code = component(field(qrd, 8))
    if not patient_code:
        pid = find_segment(segments, "PID")
        for idx in (3, 18, 2):
            patient_code = component(field(pid, idx))
            if patient_code:
                break

    return {
        "message_type": field(msh, 8),
        "control_id": field(msh, 9),
        "version": field(msh, 11) or "2.6",
        "sending_app": field(msh, 2),
        "sending_facility": field(msh, 3),
        "patient_code": patient_code.strip(),
        "qrd": "|".join(qrd) if qrd else "QRD",
        "qrf": qrf,
    }


def format_patient_name(full_name: str, order: str = "full") -> str:
    """Build PID-5 (Family^Given) from SmartWard's single `name` field.

    Default is `full`: the whole name goes in the family component, which is
    what the monitor displays. Splitting is opt-in because guessing which token
    is the family name mangles names that do not follow Western ordering.
    """
    name = (full_name or "").strip()
    if not name:
        return ""
    parts = name.split()
    if order == "split_last" and len(parts) > 1:
        return f"{escape(parts[-1])}^{escape(' '.join(parts[:-1]))}"
    if order == "split_first" and len(parts) > 1:
        return f"{escape(' '.join(parts[1:]))}^{escape(parts[0])}"
    return f"{escape(name)}^"


def _hl7_dob(value: Optional[str]) -> str:
    """SmartWard sends `Y-m-d` (or an ISO datetime); HL7 PID-7 wants YYYYMMDD."""
    if not value:
        return ""
    digits = "".join(ch for ch in str(value).strip()[:10] if ch.isdigit())
    return digits if len(digits) == 8 else ""


def _hl7_sex(gender: Optional[str]) -> str:
    initial = (gender or "").strip()[:1].upper()
    return initial if initial in ("M", "F", "O", "U") else ""


def _pid_segment(patient: Dict, patient_code: str, name_order: str, wide: bool) -> str:
    """PID with the id in PID-3 and (for the NC5 dialect) PID-18.

    The code echoed back is the one the monitor asked with, not the MRN, so the
    NC5 matches its own pending query; the resolved MRN travels in PID-18 only
    when it is the same identifier the ward uses.
    """
    fields = [""] * (19 if wide else 9)
    fields[0] = "PID"
    fields[3] = escape(patient_code)
    fields[5] = format_patient_name(patient.get("name"), name_order)
    fields[7] = _hl7_dob(patient.get("date_of_birth"))
    fields[8] = _hl7_sex(patient.get("gender"))
    if wide:
        fields[18] = escape(str(patient.get("mrn") or patient_code))
    return "|".join(fields)


def _location(patient: Dict) -> Dict[str, str]:
    return {
        "ward": escape(str(patient.get("ward_name") or "")),
        "bed": escape(str(patient.get("bed_number") or "")),
    }


def _build_orf(query: Dict, status: str, patient: Optional[Dict], settings) -> str:
    """ORF^R04 — the response the NC5 itself asks for with QRY^R02."""
    msh = "|".join([
        "MSH", r"^~\&", settings.ack_app, settings.ack_facility,
        query["sending_app"], query["sending_facility"], hl7_stamp(), "",
        "ORF^R04", _next_message_id("ORF"), "P", query["version"],
    ])

    if status == "not_found":
        return "\r".join([msh, f"MSA|AE|{query['control_id']}|Patient not found", query["qrd"]]) + "\r"
    if status != "found" or not patient:
        return "\r".join([
            msh,
            f"MSA|AE|{query['control_id']}|Patient lookup unavailable",
            query["qrd"],
        ]) + "\r"

    loc = _location(patient)
    lines = [msh, f"MSA|AA|{query['control_id']}", query["qrd"]]
    lines.extend(query["qrf"])
    lines.append(_pid_segment(patient, query["patient_code"], settings.patient_name_order, wide=True))
    lines.append(f"PV1||I|{loc['ward']}^^{loc['bed']}")
    return "\r".join(lines) + "\r"


def _build_adr(query: Dict, status: str, patient: Optional[Dict], settings) -> str:
    """ADR^A19 — the ADT query dialect (Mindray PDS / eGateway compatible)."""
    version = query["version"] or "2.3.1"
    msh = "|".join([
        "MSH", r"^~\&", settings.ack_app, settings.ack_facility,
        query["sending_app"], query["sending_facility"], hl7_stamp(), "",
        "ADR^A19", _next_message_id("ADR"), "P", version,
    ])

    if status == "not_found":
        # The A19 dialect reports a miss as an application accept whose MSA-3
        # carries the reason; an AE here makes some senders retry forever.
        return "\r".join([
            msh,
            f"MSA|AA|{query['control_id']}|The patient is not found!",
            query["qrd"],
        ]) + "\r"
    if status != "found" or not patient:
        return "\r".join([
            msh,
            f"MSA|AE|{query['control_id']}|Patient lookup unavailable",
            query["qrd"],
        ]) + "\r"

    loc = _location(patient)
    return "\r".join([
        msh,
        f"MSA|AA|{query['control_id']}|The Patient is Found",
        query["qrd"],
        _pid_segment(patient, query["patient_code"], settings.patient_name_order, wide=False),
        f"PV1||I|^^{loc['ward']}&{loc['bed']}&0&0&0",
    ]) + "\r"


def build_response(query: Dict, status: str, patient: Optional[Dict], settings) -> str:
    """Answer a parsed query in the dialect it was asked in.

    `status` is the PatientDirectory outcome: 'found', 'not_found' or 'error'.
    """
    if query["message_type"].upper().startswith("QRY^A19"):
        return _build_adr(query, status, patient, settings)
    return _build_orf(query, status, patient, settings)
