"""
Content helpers for TC35 ECG files found on the virtual USB drive (stdlib only).

Two TC35 XML layouts exist:

  * "Study" - what the TC35 POSTs over the network (gateway/ecg): <Study> with
    <PatientID> directly under the root and the report PDF base64 in
    <StudyData>. SmartWard's ECG pages (EcgController / EcgArchive) only
    understand this one: they read $xml->PatientID.
  * Philips native XML - what the TC35 saves to a USB stick: <restingecgdata>
    in the http://www3.medical.philips.com namespace, the patient under
    patient/generalpatientdata, compressed waveforms, and NO PDF.

So a Philips file is re-wrapped into the Study layout (to_study_xml) before it
is sent - the original kept byte-for-byte in <RawData> - and SmartWard files
it exactly like a network upload. Detection / PDF extraction / PatientID for
the Study layout are ported unchanged from gateway/ecg/listener/src/ecg_http.py.

Also new here: completeness checks. Over HTTP the TC35 hands over a whole file
in one request; on a USB drive we may look at a file the machine is still
writing, so a recording is only captured once it is complete.
"""

import base64
import re
import xml.etree.ElementTree as ET
from typing import Dict, Optional, Sequence, Tuple
from xml.sax.saxutils import escape


def detect_file_type(data: bytes) -> str:
    if len(data) < 10:
        return ".bin"
    if data[:4] == b"%PDF":
        return ".pdf"
    if data[:5] == b"<?xml":
        return ".xml"
    if data[:3] == b"\xef\xbb\xbf" and data[3:8] == b"<?xml":
        return ".xml"
    if data[:2] == b"\xff\xfe":
        try:
            text = data[:200].decode("utf-16-le", errors="ignore")
            if "<?xml" in text or "<restingecgdata" in text:
                return ".xml"
        except Exception:
            pass
    if data[:2] == b"\xfe\xff":
        try:
            text = data[:200].decode("utf-16-be", errors="ignore")
            if "<?xml" in text or "<restingecgdata" in text:
                return ".xml"
        except Exception:
            pass
    if b"<html" in data[:1000].lower() or b"<!doctype" in data[:1000].lower():
        return ".html"
    return ".bin"


def decode_xml(data: bytes) -> Optional[str]:
    try:
        if data[:2] == b"\xff\xfe":
            return data.decode("utf-16-le")
        if data[:2] == b"\xfe\xff":
            return data.decode("utf-16-be")
        return data.decode("utf-8-sig")
    except Exception:
        return None


def _parse_xml(xml_content: str):
    # Parsing the already-decoded str makes expat ignore the file's
    # encoding="UTF-16" declaration; only a leftover BOM needs stripping.
    return ET.fromstring(xml_content.lstrip("﻿"))


def extract_pdf_from_xml(xml_data: bytes) -> Optional[bytes]:
    """Return the embedded report PDF (base64 in <StudyData>), if present."""
    xml_content = decode_xml(xml_data)
    if xml_content is None:
        return None
    try:
        root = _parse_xml(xml_content)
        study = root.find(".//StudyData")
        if study is not None and study.text and study.text.strip():
            payload = study.text.strip()
            if payload.startswith("JVBERi"):  # %PDF in base64
                pdf = base64.b64decode(payload)
                if pdf.startswith(b"%PDF"):
                    return pdf
    except Exception:
        pass
    return None


def extract_patient_id(xml_data: bytes) -> str:
    """<PatientID> from the ECG XML - the MRN/RN SmartWard matches on."""
    xml_content = decode_xml(xml_data)
    if not xml_content:
        return ""
    try:
        root = _parse_xml(xml_content)
        node = root.find(".//PatientID")
        if node is not None and node.text:
            return node.text.strip()
    except Exception:
        # Fall back to a regex when the XML is malformed but the tag is there.
        match = re.search(r"<PatientID>\s*([^<]+?)\s*</PatientID>", xml_content)
        if match:
            return match.group(1).strip()
    return ""


# --- Philips native XML (TC35 USB export) ------------------------------------

DEFAULT_PATIENT_ID_FIELDS = ("patientid", "MRN", "uniquepatientid")


def _local(tag: str) -> str:
    return tag.rsplit("}", 1)[-1]


def _child(element, name: str):
    """First child with this local name (namespace ignored, case-insensitive)."""
    if element is None:
        return None
    for child in element:
        if _local(child.tag).lower() == name.lower():
            return child
    return None


def _text(element, *path: str) -> str:
    for name in path:
        element = _child(element, name)
    if element is None or element.text is None:
        return ""
    return element.text.strip()


def philips_root(xml_data: bytes):
    """The parsed <restingecgdata> root if this is a Philips native XML."""
    text = decode_xml(xml_data)
    if not text or "restingecgdata" not in text[:2000]:
        return None
    try:
        root = _parse_xml(text.rstrip("\x00 \t\r\n"))
    except Exception:
        return None
    if _local(root.tag) == "restingecgdata" and _child(root, "patient") is not None:
        return root
    return None


def philips_info(root, id_fields: Sequence[str] = DEFAULT_PATIENT_ID_FIELDS) -> Dict[str, str]:
    """Patient + acquisition fields of a Philips XML, as the Study layout names them."""
    gpd = _child(_child(root, "patient"), "generalpatientdata")
    patient_id = ""
    for field in id_fields:
        patient_id = _text(gpd, field)
        if patient_id:
            break
    acquisition = _child(root, "dataacquisition")
    date = (acquisition.get("date") or "").strip() if acquisition is not None else ""
    time = (acquisition.get("time") or "").strip() if acquisition is not None else ""
    return {
        "PatientID": patient_id,
        "PatientLastName": _text(gpd, "name", "lastname"),
        "PatientFirstName": _text(gpd, "name", "firstname"),
        "PatientMiddleName": _text(gpd, "name", "middlename"),
        "RefPatientAge": _text(gpd, "age"),
        "RefPatientGender": _text(gpd, "sex"),
        "RefDateAcquired": f"{date} {time}".strip(),
    }


# The TC35 names its USB PDF export  EK_<Patient ID>_[YYYYMMDDHHMMSS]______.pdf
# (Patient ID as typed on the machine, acquisition time from its clock).
TC35_PDF_NAME = re.compile(r"^[A-Za-z]{1,4}_(?P<pid>.*?)_\[(?P<ts>\d{14})\]")


def parse_tc35_pdf_name(filename: str) -> Optional[Tuple[str, str]]:
    """(patient_id, 'YYYY-MM-DD HH:MM:SS') from a TC35 PDF file name, or None."""
    match = TC35_PDF_NAME.match(filename)
    if not match:
        return None
    ts = match.group("ts")
    return match.group("pid").strip(), f"{ts[0:4]}-{ts[4:6]}-{ts[6:8]} {ts[8:10]}:{ts[10:12]}:{ts[12:14]}"


def acquisition_keys(root, info: Dict[str, str]) -> set:
    """(patient id, acquisition time) pairs a Philips XML can be matched to its
    TC35 PDF by - every non-empty ID field, so it works whichever field the
    PDF name took. Both parts must match exactly: a PDF is never attached to
    another patient's or another recording's XML."""
    when = info.get("RefDateAcquired", "")
    if len(re.sub(r"\D", "", when)) != 14:
        return set()
    gpd = _child(_child(root, "patient"), "generalpatientdata")
    ids = {_text(gpd, f) for f in DEFAULT_PATIENT_ID_FIELDS} | {info.get("PatientID", "")}
    return {(pid, when) for pid in ids if pid}


def to_study_xml(original: Optional[bytes], info: Dict[str, str], pdf: Optional[bytes], study_id: str,
                 study_format: str = "PhilipsXML") -> bytes:
    """Build the Study layout of the TC35's network upload, so SmartWard
    matches the patient (<PatientID> under the root) and shows the PDF
    (<StudyData>). A Philips XML travels untouched in <RawData>; a PDF-only
    export (study_format "PDF") has no RawData."""
    fields = [
        ("StudyID", study_id),
        ("StudyData", base64.b64encode(pdf).decode("ascii") if pdf else ""),
        ("RawData", base64.b64encode(original).decode("ascii") if original else ""),
        ("PatientID", info.get("PatientID", "")),
        ("PatientLastName", info.get("PatientLastName", "")),
        ("PatientFirstName", info.get("PatientFirstName", "")),
        ("PatientMiddleName", info.get("PatientMiddleName", "")),
        ("RefPatientAge", info.get("RefPatientAge", "")),
        ("RefPatientGender", info.get("RefPatientGender", "")),
        ("RefDateAcquired", info.get("RefDateAcquired", "")),
        ("StudyTimestamp", info.get("RefDateAcquired", "")),
        ("StudyFormat", study_format),
        ("StudySource", "TC35 USB export via SmartWard ECG USB gateway"),
    ]
    body = "".join(f"  <{name}>{escape(value)}</{name}>\n" for name, value in fields)
    return ('<?xml version="1.0" encoding="utf-8"?>\n<Study>\n' + body + "</Study>\n").encode("utf-8")


def xml_complete(data: bytes) -> bool:
    """True when the bytes are a whole, well-formed XML document.

    A file the TC35 is still writing is truncated (or zero-filled at the tail,
    since FAT allocates whole clusters), so it fails to parse."""
    if detect_file_type(data) != ".xml":
        return False
    text = decode_xml(data)
    if text is None:
        return False
    text = text.rstrip("\x00 \t\r\n")
    try:
        _parse_xml(text)
        return True
    except Exception:
        return False


def pdf_complete(data: bytes) -> bool:
    """True when the bytes look like a whole PDF (header + trailing %%EOF)."""
    return data[:4] == b"%PDF" and b"%%EOF" in data[-2048:]
