"""
CM100 (Philips Efficia CM) stream interpreter + profile engine.

The CM100 is a CONTINUOUS monitor: it streams ORU^R01 messages every few
seconds (SpO2/Pulse each cycle, NBP/Temp/RR when measured). One HL7 message is
therefore NOT one clinical record. This module ports the field-proven grouping
rule from gateway/cm100/interpreter.py:

  * readings from one patient (MRN) fold into the SAME open profile —
    continuous SpO2/Pulse are kept as min/max ranges, the latest BP/Temp/RR
    are carried;
  * a profile CLOSES (and becomes one queued vital-sign record) when
        - more than `profile_gap_seconds` pass with no readings (monitoring
          stopped / patient disconnected), OR
        - a DIFFERENT blood pressure is measured (the old BP's context ends), OR
        - the profile reaches `profile_max_seconds` (so a stable long-running
          patient still charts a record at a bounded cadence).

Known CM100 quirks handled here (learned from real traffic):
  * OBX-3 carries the human label in component 2 (e.g. "NBPs", "SpO2", "Pulse");
  * an EMPTY OBX-5 means "no reading this cycle" (result status X), not zero;
  * the device clock is unreliable (ships as 2013-01-01), so `measured_at`
    uses the gateway wall-clock, never MSH-7.
"""

import hashlib
import re
from datetime import datetime
from typing import Dict, List, Optional, Tuple


def _to_float(value: str) -> Optional[float]:
    if value is None:
        return None
    match = re.search(r"-?\d+(?:\.\d+)?", str(value).split("^", 1)[0])
    try:
        return float(match.group(0)) if match else None
    except ValueError:
        return None


def classify(label: str) -> Optional[str]:
    """Map a CM100 OBX label (SpO2, NBPs, Pulse, Temp, ...) to a canonical param."""
    l = (label or "").lower()
    if "spo2" in l or "sao2" in l:
        return "spo2"
    if "nbps" in l or "systolic" in l:
        return "bp_sys"
    if "nbpd" in l or "diastolic" in l:
        return "bp_dia"
    if "nbpm" in l or "mean" in l:
        return "bp_mean"  # tracked but not sent (no API field)
    if "pulse" in l or "heart" in l or l == "hr":
        return "pulse"
    if "temp" in l:
        return "temp"
    if l in ("rr", "resp") or "respir" in l:
        return "rr"
    return None  # Perf and anything else -> ignored for the API summary


def parse_message(hl7: str) -> Tuple[str, str, List[Tuple[str, float]]]:
    """Return (mrn, control_id, [(param, value), ...]) from one ORU message."""
    mrn = control_id = ""
    readings = []
    for seg in (s for s in hl7.replace("\n", "\r").split("\r") if s):
        f = seg.split("|")
        name = f[0].upper()
        if name == "MSH":
            control_id = f[9] if len(f) > 9 else ""
        elif name == "PID":
            mrn = f[3].split("^")[0].strip() if len(f) > 3 else ""
        elif name == "OBX" and len(f) > 5:
            obs_id = f[3].split("^") if len(f) > 3 else []
            label = obs_id[1] if len(obs_id) > 1 else (obs_id[0] if obs_id else "")
            raw_value = f[5]
            if raw_value == "":
                continue  # empty (result status X) = no reading this cycle
            param = classify(label)
            if param is None:
                continue
            value = _to_float(raw_value)
            if value is None:
                continue
            # Temperature may arrive in Fahrenheit depending on device config.
            if param == "temp":
                units = (f[6] if len(f) > 6 else "").lower()
                if "f" in units.replace("deg", "").replace("[", "") and "c" not in units:
                    value = round((value - 32.0) * 5.0 / 9.0, 1)
            readings.append((param, value))
    return mrn, control_id, readings


def incoming_bp(readings) -> Optional[Tuple[int, Optional[int]]]:
    """Return (sys, dia) if this message carries a BP, else None."""
    sys_v = dia_v = None
    for p, v in readings:
        if p == "bp_sys":
            sys_v = int(round(v))
        elif p == "bp_dia":
            dia_v = int(round(v))
    return (sys_v, dia_v) if sys_v is not None else None


class ProfileEngine:
    """Groups the continuous stream into bounded vital-sign profiles.

    NOT thread-safe by itself — the caller (listener service) serializes access.
    close_* methods return finished payload dicts ready for the outbox.
    """

    def __init__(self, settings):
        self.settings = settings
        self.profiles = {}  # mrn -> open profile dict
        self.seq = 0

    # ---- lifecycle ---------------------------------------------------------

    def _new_profile(self, mrn: str, now: datetime) -> Dict:
        self.seq += 1
        return {
            "mrn": mrn,
            "seq": self.seq,
            "started": now,
            "last_data": now,
            "msg_count": 0,
            "pulse": [], "spo2": [],
            "bp_sys": None, "bp_dia": None, "bp_key": None,
            "temp": None, "rr": None,
        }

    def _accumulate(self, prof: Dict, readings, now: datetime, bp) -> None:
        prof["last_data"] = now
        prof["msg_count"] += 1
        for p, v in readings:
            if p == "pulse":
                prof["pulse"].append(v)
            elif p == "spo2":
                prof["spo2"].append(v)
            elif p == "bp_sys":
                prof["bp_sys"] = int(round(v))
            elif p == "bp_dia":
                prof["bp_dia"] = int(round(v))
            elif p == "temp":
                prof["temp"] = round(v, 1)
            elif p == "rr":
                prof["rr"] = int(round(v))
        if bp is not None:
            prof["bp_key"] = bp

    def ingest(self, mrn: str, readings, now: datetime):
        """Feed one message's readings. Returns (closed_payload|None, new_reason|None)."""
        bp = incoming_bp(readings)
        prof = self.profiles.get(mrn)
        reason = None
        if prof is None:
            reason = "first reading"
        elif (now - prof["last_data"]).total_seconds() > self.settings.profile_gap_seconds:
            reason = f">{self.settings.profile_gap_seconds}s gap"
        elif bp is not None and prof["bp_key"] is not None and bp != prof["bp_key"]:
            reason = "different BP"
        elif (now - prof["started"]).total_seconds() > self.settings.profile_max_seconds:
            reason = f"window >{self.settings.profile_max_seconds}s"

        closed = None
        if reason and prof is not None:
            closed = self.build_payload(prof)
        if reason:
            prof = self._new_profile(mrn, now)
            self.profiles[mrn] = prof
        self._accumulate(prof, readings, now, bp)
        return closed, reason

    def close_expired(self, now: datetime) -> List[Dict]:
        """Close profiles whose gap or max window elapsed with no new data."""
        closed = []
        for mrn in list(self.profiles):
            prof = self.profiles[mrn]
            gap = (now - prof["last_data"]).total_seconds()
            age = (now - prof["started"]).total_seconds()
            if gap > self.settings.profile_gap_seconds or age > self.settings.profile_max_seconds:
                payload = self.build_payload(prof)
                if payload:
                    closed.append(payload)
                del self.profiles[mrn]
        return closed

    def flush_all(self) -> List[Dict]:
        """Close everything (shutdown) so buffered readings are not lost."""
        closed = [p for p in (self.build_payload(v) for v in self.profiles.values()) if p]
        self.profiles.clear()
        return closed

    # ---- payload -----------------------------------------------------------

    def _event_id(self, prof: Dict, measured_at_text: str) -> str:
        parts = [
            self.settings.gateway_id, prof["mrn"],
            prof["started"].strftime("%Y-%m-%d %H:%M:%S"), measured_at_text,
            str(prof["bp_sys"]), str(prof["bp_dia"]), str(prof["temp"]), str(prof["rr"]),
            str(len(prof["pulse"])), str(len(prof["spo2"])),
        ]
        return hashlib.sha1("|".join(parts).encode("utf-8")).hexdigest()

    def build_payload(self, prof: Dict) -> Optional[Dict]:
        """One profile -> the exact body for POST /api/v1/vital-signs.
        Returns None when nothing usable survived validation."""
        s = self.settings
        # measured_at is the gateway wall-clock of the LAST reading — the CM100
        # device clock is unreliable (ships as 2013-01-01).
        measured_at_text = prof["last_data"].strftime("%Y-%m-%d %H:%M:%S")

        payload = {
            "patient_code": prof["mrn"],
            "measured_at": measured_at_text,
            "gateway_id": s.gateway_id,
        }
        if prof["bp_sys"] is not None and 0 < prof["bp_sys"] < 300:
            payload["blood_pressure_systolic"] = prof["bp_sys"]
        if prof["bp_dia"] is not None and 0 < prof["bp_dia"] < 200:
            payload["blood_pressure_diastolic"] = prof["bp_dia"]

        pulse = [v for v in prof["pulse"] if s.pr_range_min <= v <= s.pr_range_max]
        if pulse:
            payload["pulse_rate"] = int(round(pulse[-1]))
            payload["pulse_rate_min"] = int(round(min(pulse)))
            payload["pulse_rate_max"] = int(round(max(pulse)))
        spo2 = [v for v in prof["spo2"] if s.spo2_range_min <= v <= s.spo2_range_max]
        if spo2:
            payload["spo2"] = round(spo2[-1], 1)
            payload["spo2_min"] = int(round(min(spo2)))
            payload["spo2_max"] = int(round(max(spo2)))
        if prof["temp"] is not None and 20 < prof["temp"] < 50:
            payload["temperature"] = prof["temp"]
        if prof["rr"] is not None and 0 < prof["rr"] < 100:
            payload["respiratory_rate"] = prof["rr"]

        vitals = [k for k in payload if k not in ("patient_code", "measured_at", "gateway_id")]
        if not vitals:
            return None
        payload["gateway_event_id"] = self._event_id(prof, measured_at_text)
        return payload
