"""
CM100 -> SmartWard vital-signs interpreter (test-flow stage).

Pipeline:
    Philips CM100 --HL7/MLLP--> hl7_listener --> this interpreter

For every message it writes the raw HL7 to  raw/  (one .hl7 per message), and it
groups the readings into VITAL-SIGN PROFILES written to  interpreted/  (one .json
per profile, in the exact body SmartWard expects at POST /api/v1/vital-signs).

Profiling rule (per patient / MRN):
  * readings that stream in continuously fold into the SAME profile
    (e.g. a BP from one message + the SpO2/Pulse around it = one vital-sign set);
  * a NEW profile starts when either
        - more than CM100_PROFILE_GAP seconds (default 120 = 2 min) pass since
          the last real reading, OR
        - a DIFFERENT blood pressure is measured.
The profile's interpreted file is rewritten in place as more readings arrive, so
it appears immediately and stays current. Continuous SpO2/Pulse are kept as
min/max RANGES; the latest BP / Temp / RR are carried.

NOTHING is sent over the network yet - this is the "test the flow" stage.

Backend contract (smartward4: VitalSignApiV1Controller@receiveVitalSigns):
    patient_code, blood_pressure_systolic/diastolic,
    pulse_rate/_min/_max, spo2/_min/_max, respiratory_rate, temperature,
    measured_at, gateway_id, gateway_event_id

Config via env: CM100_HOST (0.0.0.0), CM100_PORT (2575),
    CM100_GATEWAY_ID (CM100-GW1), CM100_PROFILE_GAP seconds (120).
"""

import json
import os
import re
import threading
from datetime import datetime

from hl7_listener import start_listener

HOST = os.environ.get("CM100_HOST", "0.0.0.0")
PORT = int(os.environ.get("CM100_PORT", "2575"))
GATEWAY_ID = os.environ.get("CM100_GATEWAY_ID", "CM100-GW1")
PROFILE_GAP = int(os.environ.get("CM100_PROFILE_GAP", "120"))  # seconds

BASE = os.path.dirname(os.path.abspath(__file__))
RAW_DIR = os.path.join(BASE, "raw")
INTERP_DIR = os.path.join(BASE, "interpreted")

_lock = threading.Lock()
_profiles = {}       # mrn -> current open profile dict
_profile_seq = 0     # global profile counter


# --- helpers ----------------------------------------------------------------
def _ensure_dirs():
    os.makedirs(RAW_DIR, exist_ok=True)
    os.makedirs(INTERP_DIR, exist_ok=True)


def _safe(s):
    return re.sub(r"[^A-Za-z0-9_.-]", "_", s or "")


def _to_float(s):
    try:
        return float(str(s).strip())
    except (TypeError, ValueError):
        return None


def _to_int(s):
    v = _to_float(s)
    return int(round(v)) if v is not None else None


def _segments(hl7):
    return [seg for seg in hl7.replace("\n", "\r").split("\r") if seg]


def _classify(label):
    """Map a CM100 OBX label (SpO2, NBPs, Pulse, Temp, ...) to a canonical param."""
    l = label.lower()
    if "spo2" in l:
        return "spo2"
    if "nbps" in l or "systolic" in l:
        return "bp_sys"
    if "nbpd" in l or "diastolic" in l:
        return "bp_dia"
    if "nbpm" in l or "mean" in l:
        return "bp_mean"
    if "pulse" in l or "heart" in l or l == "hr":
        return "pulse"
    if "temp" in l:
        return "temp"
    if l in ("rr", "resp") or "respir" in l:
        return "rr"
    return None  # Perf and anything else -> ignored for the API summary


def _parse_message(hl7):
    """Return (mrn, control_id, device_time, [(param, value_str), ...])."""
    mrn = control_id = device_time = ""
    readings = []
    for seg in _segments(hl7):
        f = seg.split("|")
        name = f[0]
        if name == "MSH":
            device_time = f[6] if len(f) > 6 else ""
            control_id = f[9] if len(f) > 9 else ""
        elif name == "PID":
            mrn = f[3].split("^")[0] if len(f) > 3 else ""
        elif name == "OBX" and len(f) > 5:
            obs_id = f[3].split("^") if len(f) > 3 else []
            label = obs_id[1] if len(obs_id) > 1 else (obs_id[0] if obs_id else "")
            value = f[5]
            if value == "":
                continue  # empty (result status X) = no reading this cycle
            param = _classify(label)
            if param:
                readings.append((param, value))
    return mrn, control_id, device_time, readings


# --- profiles ---------------------------------------------------------------
def _new_profile(mrn, now):
    global _profile_seq
    _profile_seq += 1
    seq = _profile_seq
    stamp = now.strftime("%Y%m%d-%H%M%S")
    return {
        "seq": seq,
        "started": now,
        "last_data": now,
        "msg_count": 0,
        "pulse": [], "spo2": [],
        "bp_sys": None, "bp_dia": None, "bp_mean": None, "bp_key": None,
        "temp": None, "rr": None,
        "path": os.path.join(INTERP_DIR, "%s_%s_p%03d.json" % (stamp, _safe(mrn) or "noMRN", seq)),
        "event_id": "CM100-%s-%d-p%03d" % (_safe(mrn), int(now.timestamp()), seq),
    }


def _incoming_bp(readings):
    """Return (sys, dia) if this message carries a BP, else None."""
    sys_v = dia_v = None
    for p, v in readings:
        if p == "bp_sys":
            sys_v = _to_int(v)
        elif p == "bp_dia":
            dia_v = _to_int(v)
    return (sys_v, dia_v) if sys_v is not None else None


def _accumulate(prof, readings, now, incoming_bp):
    prof["last_data"] = now
    prof["msg_count"] += 1
    for p, v in readings:
        if p == "pulse":
            iv = _to_int(v)
            if iv is not None:
                prof["pulse"].append(iv)
        elif p == "spo2":
            iv = _to_int(v)
            if iv is not None:
                prof["spo2"].append(iv)
        elif p == "bp_sys":
            iv = _to_int(v)
            if iv is not None:
                prof["bp_sys"] = iv
        elif p == "bp_dia":
            iv = _to_int(v)
            if iv is not None:
                prof["bp_dia"] = iv
        elif p == "bp_mean":
            iv = _to_int(v)
            if iv is not None:
                prof["bp_mean"] = iv
        elif p == "temp":
            fv = _to_float(v)
            if fv is not None:
                prof["temp"] = fv
        elif p == "rr":
            iv = _to_int(v)
            if iv is not None:
                prof["rr"] = iv
    if incoming_bp is not None:
        prof["bp_key"] = incoming_bp


def _build_payload(mrn, prof):
    """The exact JSON body that will be POSTed to /api/v1/vital-signs."""
    payload = {"patient_code": mrn, "gateway_id": GATEWAY_ID}
    if prof["bp_sys"] is not None:
        payload["blood_pressure_systolic"] = prof["bp_sys"]
    if prof["bp_dia"] is not None:
        payload["blood_pressure_diastolic"] = prof["bp_dia"]
    if prof["pulse"]:
        payload["pulse_rate"] = prof["pulse"][-1]
        payload["pulse_rate_min"] = min(prof["pulse"])
        payload["pulse_rate_max"] = max(prof["pulse"])
    if prof["spo2"]:
        payload["spo2"] = prof["spo2"][-1]
        payload["spo2_min"] = min(prof["spo2"])
        payload["spo2_max"] = max(prof["spo2"])
    if prof["rr"] is not None:
        payload["respiratory_rate"] = prof["rr"]
    if prof["temp"] is not None:
        payload["temperature"] = prof["temp"]
    # Device clock is unreliable (ships as 2013-01-01); use server wall-clock.
    payload["measured_at"] = prof["last_data"].astimezone().isoformat(timespec="seconds")
    payload["gateway_event_id"] = prof["event_id"]
    return payload


def _write_profile(mrn, prof):
    payload = _build_payload(mrn, prof)
    with open(prof["path"], "w", encoding="utf-8") as fh:
        json.dump(payload, fh, indent=2)
    return prof["path"]


def _live_summary(readings):
    if not readings:
        return "(no numeric readings this cycle)"
    return " ".join("%s=%s" % (p, v) for p, v in readings)


# --- message handler (the on_message callback) ------------------------------
def handle(hl7, now=None):
    now = now or datetime.now()
    mrn, control_id, device_time, readings = _parse_message(hl7)
    _ensure_dirs()

    # 1) raw HL7 -> raw/ (every message, always)
    stamp = now.strftime("%Y%m%d-%H%M%S-") + "%03d" % (now.microsecond // 1000)
    raw_path = os.path.join(
        RAW_DIR, "%s_%s_%s.hl7" % (stamp, _safe(mrn) or "noMRN", _safe(control_id))
    )
    with open(raw_path, "w", encoding="utf-8", newline="") as fh:
        fh.write(hl7 if hl7.endswith("\r") else hl7 + "\r")

    # 2) profile grouping -> interpreted/ (only messages that carry readings)
    interp_path = reason = None
    prof_seq = None
    if readings:
        incoming_bp = _incoming_bp(readings)
        with _lock:
            prof = _profiles.get(mrn)
            if prof is None:
                reason = "first reading"
            elif (now - prof["last_data"]).total_seconds() > PROFILE_GAP:
                reason = ">%ds gap" % PROFILE_GAP
            elif (incoming_bp is not None and prof["bp_key"] is not None
                  and incoming_bp != prof["bp_key"]):
                reason = "different BP"
            if reason:
                prof = _new_profile(mrn, now)
                _profiles[mrn] = prof
            _accumulate(prof, readings, now, incoming_bp)
            interp_path = _write_profile(mrn, prof)
            prof_seq = prof["seq"]

    tail = control_id[-4:] if control_id else "?"
    where = " p%03d%s" % (prof_seq, " NEW(%s)" % reason if reason else "") if prof_seq else ""
    extra = " -> %s" % os.path.basename(interp_path) if interp_path else ""
    print("[%s] msg#%s MRN=%s%s  %s%s" % (
        now.strftime("%H:%M:%S"), tail, mrn or "?", where, _live_summary(readings), extra))


def main():
    _ensure_dirs()
    print("CM100 interpreter listening on %s:%d" % (HOST, PORT))
    print("  gateway     : %s" % GATEWAY_ID)
    print("  profile gap : %ds (new profile after this gap or a different BP)" % PROFILE_GAP)
    print("  raw         : %s" % RAW_DIR)
    print("  interpreted : %s" % INTERP_DIR)
    print("  (Ctrl+C to stop)")
    start_listener(on_message=handle, host=HOST, port=PORT)


if __name__ == "__main__":
    main()
