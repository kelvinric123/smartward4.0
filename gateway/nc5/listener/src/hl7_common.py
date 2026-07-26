"""
Shared HL7 v2 primitives for the NC5 gateway (stdlib only).

The Comen NC5 talks HL7 2.6 over MLLP with the standard delimiters
(|^~\\&). These helpers are deliberately tolerant: a segment shorter than the
field we ask for yields "" rather than raising, because real devices truncate
trailing empty fields.
"""

import re
from datetime import datetime
from typing import List, Optional

__all__ = [
    "split_segments",
    "find_segment",
    "field",
    "component",
    "subcomponent",
    "parse_hl7_timestamp",
    "hl7_stamp",
    "escape",
]


def split_segments(hl7: str) -> List[List[str]]:
    """Split a message into segments, each a list of |-separated fields."""
    segments = []
    for raw in hl7.replace("\n", "\r").split("\r"):
        raw = raw.strip()
        if raw:
            segments.append(raw.split("|"))
    return segments


def find_segment(segments: List[List[str]], name: str) -> Optional[List[str]]:
    for seg in segments:
        if seg and seg[0].upper() == name:
            return seg
    return None


def field(seg: Optional[List[str]], index: int) -> str:
    if not seg or index >= len(seg):
        return ""
    return seg[index].strip()


def component(value: str, index: int = 0) -> str:
    """First repetition, then the requested ^-component."""
    first_rep = value.split("~", 1)[0]
    parts = first_rep.split("^")
    return parts[index].strip() if index < len(parts) else ""


def subcomponent(value: str, index: int = 0) -> str:
    parts = value.split("&")
    return parts[index].strip() if index < len(parts) else ""


def parse_hl7_timestamp(value: str) -> Optional[datetime]:
    """Parse an HL7 TS (YYYYMMDD[HHMM[SS]] with optional fraction/zone).

    The NC5 sends local time with an offset (20260723224455.6021+0800). The ward
    documents against monitor local time, so the offset and fraction are dropped
    rather than converted.
    """
    if not value:
        return None
    value = value.strip()
    value = re.split(r"[+\-]", value, maxsplit=1)[0].split(".", 1)[0]
    for fmt in ("%Y%m%d%H%M%S", "%Y%m%d%H%M", "%Y%m%d"):
        try:
            return datetime.strptime(value, fmt)
        except ValueError:
            continue
    return None


def hl7_stamp(moment: Optional[datetime] = None) -> str:
    return (moment or datetime.now()).strftime("%Y%m%d%H%M%S")


def escape(value: str) -> str:
    """Escape HL7 delimiters in free text we place into a field."""
    if not value:
        return ""
    return (
        value.replace("\\", "\\E\\")
        .replace("|", "\\F\\")
        .replace("^", "\\S\\")
        .replace("~", "\\R\\")
        .replace("&", "\\T\\")
        .replace("\r", " ")
        .replace("\n", " ")
    )
