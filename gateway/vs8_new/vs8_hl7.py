#!/usr/bin/env python3
"""
Mindray VS8 - HL7 / MLLP gateway.

Once the VS8 completes its handshake it exposes its HL7 configuration, and from
there it speaks ordinary MLLP rather than the proprietary transport on 9997:

    tcp/2575   HL7 send   - the monitor PUSHES vitals to us (ORU^R01)
    tcp/3502   ADT query  - the monitor ASKS us for patient demographics

Both are monitor -> server, so this process listens on both. Framing is standard
MLLP: 0x0B <message> 0x1C 0x0D.

Vitals arriving on 2575 are parsed into readable observations and acknowledged.
Queries arriving on 3502 are answered from patients.json, which is how ADT data
gets synced onto the machine: the monitor asks for a patient ID and we hand back
the demographics, so the operator does not have to key them in.

Usage:
    py -3 vs8_hl7.py                       # listen on 2575 + 3502
    py -3 vs8_hl7.py --patients my.json
    py -3 vs8_hl7.py --hl7-port 2575 --adt-port 3502
"""

import argparse
import json
import os
import re
import socket
import sys
import threading
import time
from datetime import datetime

VT, FS, CR = 0x0B, 0x1C, 0x0D

# The VS8 sends IHE PCD-01, so OBX-3 and OBX-6 are ISO/IEEE 11073 nomenclature
# triplets of the form <numeric code>^<MDC name>^MDC. Keying on the MDC name is
# the reliable half - the numeric code varies by parameter revision.
FRIENDLY = {
    "MDC_PULS_OXIM_SAT_O2": "SpO2",
    "MDC_PULS_OXIM_PULS_RATE": "Pulse rate",
    "MDC_PULS_OXIM_PLETH_RESP_RATE": "Resp rate (pleth)",
    "MDC_BLD_PERF_INDEX": "Perfusion index",
    "MDC_PRESS_BLD_NONINV_SYS": "NIBP systolic",
    "MDC_PRESS_BLD_NONINV_DIA": "NIBP diastolic",
    "MDC_PRESS_BLD_NONINV_MEAN": "NIBP mean",
    "MDC_TEMP": "Temperature",
    "MDC_TEMP_BODY": "Body temperature",
    "MDC_ECG_HEART_RATE": "Heart rate",
    "MDC_RESP_RATE": "Respiration rate",
}

UNITS = {
    "MDC_DIM_PERCENT": "%",
    "MDC_DIM_BEAT_PER_MIN": "bpm",
    "MDC_DIM_RESP_PER_MIN": "rpm",
    "MDC_DIM_PULS_PER_MIN": "bpm",
    "MDC_DIM_MMHG": "mmHg",
    "MDC_DIM_DEG_C": "degC",
    "MDC_DIM_DEGC": "degC",
    "MDC_DIM_DIMLESS": "",
}


def friendly_unit(value):
    """Render OBX-6 as a symbol.

    Mindray orders the triplet code-first, so component 1 is a bare number like
    262688; the readable half is component 2 (MDC_DIM_PERCENT).
    """
    if not value:
        return ""
    name = component(value, 2) or component(value, 1)
    if name in UNITS:
        return UNITS[name]
    if name.startswith("MDC_DIM_"):
        return name[len("MDC_DIM_"):].lower()
    return name

_print_lock = threading.Lock()


def out(*parts):
    with _print_lock:
        for p in parts:
            print(p)
        sys.stdout.flush()


def now_hl7():
    return datetime.now().strftime("%Y%m%d%H%M%S")


def stamp():
    return datetime.now().strftime("%H:%M:%S.%f")[:-3]


# --------------------------------------------------------------------------
# HL7 parsing
# --------------------------------------------------------------------------

def split_segments(text):
    return [s for s in re.split(r"[\r\n]+", text) if s.strip()]


def field(segment, n):
    """HL7 field accessor.

    MSH is offset by one because MSH-1 *is* the field separator, so MSH-2 lands
    in parts[1] while for every other segment SEG-n lands in parts[n].
    """
    parts = segment.split("|")
    idx = n - 1 if parts[0] == "MSH" else n
    return parts[idx] if 0 <= idx < len(parts) else ""


def component(value, n):
    parts = value.split("^")
    return parts[n - 1] if 0 < n <= len(parts) else ""


def find_segment(segments, name):
    for seg in segments:
        if seg.startswith(name + "|"):
            return seg
    return None


def describe_message(segments):
    msh = find_segment(segments, "MSH")
    if not msh:
        return "?", "?"
    msg_type = field(msh, 9)
    control_id = field(msh, 10)
    return msg_type, control_id


def build_ack(segments, code="AA", text="", app="SMARTWARD", facility="SMARTWARD"):
    """Mirror the sender's identifiers back so the monitor accepts the ACK."""
    msh = find_segment(segments, "MSH") or "MSH|^~\\&"
    sending_app, sending_fac = field(msh, 3), field(msh, 4)
    msg_type, control_id = field(msh, 9), field(msh, 10)
    version = field(msh, 12) or "2.6"
    trigger = component(msg_type, 2)

    ack_type = "ACK^%s" % trigger if trigger else "ACK"
    lines = [
        "MSH|^~\\&|%s|%s|%s|%s|%s||%s|%s|P|%s"
        % (app, facility, sending_app, sending_fac, now_hl7(),
           ack_type, "ACK" + now_hl7(), version),
        "MSA|%s|%s%s" % (code, control_id, ("|" + text) if text else ""),
    ]
    return "\r".join(lines) + "\r"


# --------------------------------------------------------------------------
# Vitals
# --------------------------------------------------------------------------

def show_vitals(segments):
    pid = find_segment(segments, "PID")
    if pid:
        patient_id = component(field(pid, 3), 1)
        name = field(pid, 5).replace("^", " ").strip()
        out("  patient: id=%s name=%s" % (patient_id or "-", name or "-"))

    rows = []
    demo = False
    for seg in segments:
        if not seg.startswith("OBX|"):
            continue
        ident = field(seg, 3)
        code = component(ident, 1)
        mdc = component(ident, 2)
        label = FRIENDLY.get(mdc) or FRIENDLY.get(code) or mdc or code
        value = field(seg, 5)
        units = friendly_unit(field(seg, 6))
        flag = field(seg, 8)
        when = field(seg, 14)
        if flag.upper() == "DEMO":
            demo = True
        if value:
            rows.append((label, value, units, when))

    if rows:
        out("  observations:")
        for label, value, units, when in rows:
            out("    %-22s %10s %-6s  %s" % (label, value, units, when))
    if demo:
        out("  NOTE: OBX-8=DEMO - the monitor is in demo mode, values are simulated.")
    return rows


# --------------------------------------------------------------------------
# ADT query response
# --------------------------------------------------------------------------

# Query trigger -> response message type. The VS8 uses ZV1 (IHE ITI-22, the
# demographics-and-visit query), whose reply is ZV2; the plain PDQ forms are kept
# so a differently configured monitor still gets a valid answer.
RESPONSE_TYPE = {
    "ZV1": "RSP^ZV2^RSP_ZV2",
    "Q22": "RSP^K22^RSP_K21",
    "Q21": "RSP^K21^RSP_K21",
    "ZV2": "RSP^ZV2^RSP_ZV2",
}


def parse_qip(value):
    """Parse an IHE PDQ QPD-3 demographics field.

    Repeats are '~' separated and each one is <@segment.field>^<value>, e.g.
    '@PID.3.1^99999~@PID.5.1^SMITH'. Read plainly, the ID looks like the literal
    string '@PID.3.1', so the value has to come from component 2.
    """
    params = {}
    for rep in value.split("~"):
        key = component(rep, 1)
        if key.startswith("@"):
            params[key] = component(rep, 2)
    return params


def parse_query(segments):
    """Read the search criteria out of whichever query dialect the VS8 used.

    Returns (params, assigning_authority, id_type) where params is keyed by the
    QIP references the monitor sent, e.g. {'@PID.3.1': '10003'}. An empty params
    dict means an unfiltered query - a request for the entire patient list.
    """
    params = {}
    authority = id_type = ""

    def take(value):
        return (component(value, 1) or value,
                component(value, 4), component(value, 5))

    qpd = find_segment(segments, "QPD")
    if qpd:
        params.update({k: v for k, v in parse_qip(field(qpd, 3)).items() if v})
        if not params:
            for n in range(3, 10):
                value = field(qpd, n)
                if value and not component(value, 1).startswith("@"):
                    params["@PID.3.1"], authority, id_type = take(value)
                    break

    if "@PID.3.1" not in params:
        for name, num in (("QRD", 8), ("PID", 3)):
            seg = find_segment(segments, name)
            if seg and field(seg, num):
                params["@PID.3.1"], authority, id_type = take(field(seg, num))
                break

    return params, authority, id_type


def query_limit(segments, default=50):
    """RCP-2 is how many records the monitor is willing to receive."""
    rcp = find_segment(segments, "RCP")
    if rcp:
        value = component(field(rcp, 2), 1)
        if value.isdigit():
            return max(1, int(value))
    return default


def build_pid(patient, patient_id, authority="", id_type="", set_id=1):
    # Name type code L (legal) in XPN-7, matching the form the monitor uses in
    # its own PID-5 (John^Smith-Demo^^^^^L). Without it the VS8 ignores the name.
    name = "%s^%s^^^^^L" % (patient.get("last", ""), patient.get("first", ""))
    return "PID|%d||%s^^^%s^%s||%s||%s|%s" % (
        set_id,
        patient_id,
        authority or patient.get("assigning_authority", "Hospital"),
        id_type or patient.get("id_type", "PI"),
        name,
        patient.get("dob", ""),
        patient.get("sex", ""),
    )


def build_pv1(patient):
    return "PV1|1|I|%s^%s^%s||||||||||||||||%s" % (
        patient.get("ward", ""),
        patient.get("room", ""),
        patient.get("bed", ""),
        patient.get("visit", ""),
    )


def build_query_response(segments, candidates, app="SMARTWARD",
                         facility="SMARTWARD", authority="", id_type=""):
    """Answer in the same dialect the monitor asked in.

    The trigger event has to match the query, not just the message code. The VS8
    sends QBP^ZV1 under IHE ITI-22 (patient demographics *and visit* query), whose
    response is RSP^ZV2 - answering with the ITI-21 RSP^K22 form makes the monitor
    discard the PID and keep showing its demo placeholder.
    """
    msh = find_segment(segments, "MSH") or "MSH|^~\\&"
    sending_app, sending_fac = field(msh, 3), field(msh, 4)
    control_id = field(msh, 10)
    version = field(msh, 12) or "2.6"
    msg_type = field(msh, 9)
    code, trigger = component(msg_type, 1), component(msg_type, 2)
    profile = field(msh, 21)
    is_qbp = code == "QBP"

    found = bool(candidates)
    if is_qbp:
        header_type = RESPONSE_TYPE.get(trigger, "RSP^K22^RSP_K21")
    else:
        header_type = "ADR^A19"

    lines = ["MSH|^~\\&|%s|%s|%s|%s|%s||%s|%s|P|%s|||AL|NE||UNICODE UTF-8|||%s"
             % (app, facility, sending_app, sending_fac, now_hl7(),
                header_type, "RSP" + now_hl7(), version, profile),
             "MSA|AA|%s" % control_id]

    if is_qbp:
        qpd = find_segment(segments, "QPD") or "QPD|Q22^Find Candidates|Q1"
        # QAK-2 is the status, but QAK-4..6 are what make the result usable: a
        # client that reads the hit count sees an empty QAK-4 as "zero records"
        # and discards the PID that follows, so the query appears to succeed
        # while the demographics never land.
        #   QAK-3 message query name (echo QPD-1)
        #   QAK-4 hit count total
        #   QAK-5 this payload
        #   QAK-6 hits remaining
        hits = len(candidates)
        lines.append("QAK|%s|%s|%s|%d|%d|0"
                     % (field(qpd, 2) or "Q1", "OK" if found else "NF",
                        field(qpd, 1), hits, hits))
        lines.append(qpd)
    else:
        first_id = candidates[0][0] if candidates else ""
        lines.append("QRD|%s|R|I|%s|||1^RD|%s|DEM"
                     % (now_hl7(), control_id or "Q1", first_id))

    # One PID/PV1 group per candidate - this repetition is what populates the
    # monitor's ADT patient list rather than a single lookup result.
    for index, (pid_value, record) in enumerate(candidates, 1):
        lines.append(build_pid(record, pid_value, authority, id_type, index))
        lines.append(build_pv1(record))

    return "\r".join(lines) + "\r", found


# --------------------------------------------------------------------------
# MLLP server
# --------------------------------------------------------------------------

class Handler:
    def __init__(self, role, patients, logfile, app, facility):
        self.role = role
        self.patients = patients
        self.logfile = logfile
        self.app = app
        self.facility = facility

    def log(self, direction, peer, text):
        with _print_lock:
            self.logfile.write(json.dumps({
                "ts": datetime.now().isoformat(timespec="milliseconds"),
                "role": self.role,
                "dir": direction,
                "peer": peer,
                "hl7": text,
            }) + "\n")
            self.logfile.flush()

    def handle(self, raw, peer):
        text = raw.decode("utf-8", "replace")
        segments = split_segments(text)
        msg_type, control_id = describe_message(segments)

        out("", "[%s] %s  %s  <- %s  (%d bytes, id=%s)"
            % (stamp(), self.role, msg_type, peer, len(raw), control_id))
        for seg in segments:
            out("  | " + seg)
        self.log("in", peer, text)

        if self.role == "ADT-QUERY":
            params, authority, id_type = parse_query(segments)
            limit = query_limit(segments)
            candidates = self.patients.search(params, limit)
            criteria = ", ".join("%s=%s" % kv for kv in sorted(params.items()))
            out("  query: %s  (max %d)" % (criteria or "(no filter - whole list)", limit))
            out("  -> %d candidate(s)%s"
                % (len(candidates),
                   (": " + ", ".join(p for p, _ in candidates[:10])) if candidates else ""))
            if not candidates:
                out("  !! nothing matched - add the record to patients.json,",
                    "     or check the criteria above against the file.")
            reply, _ = build_query_response(segments, candidates,
                                            self.app, self.facility,
                                            authority, id_type)
        else:
            show_vitals(segments)
            reply = build_ack(segments, "AA", app=self.app, facility=self.facility)

        out("  --> replying:")
        for seg in split_segments(reply):
            out("  > " + seg)
        self.log("out", peer, reply)
        return reply.encode("utf-8")


def serve_client(conn, addr, handler, stop):
    peer = "%s:%d" % addr
    out("", "*** CONNECT %s -> %s" % (peer, handler.role))
    buf = bytearray()
    try:
        conn.settimeout(0.5)
        while not stop.is_set():
            try:
                data = conn.recv(65535)
            except socket.timeout:
                # Idle. Not every sender wraps messages in MLLP, so anything
                # already sitting in the buffer that looks like a complete HL7
                # message gets answered in the same unframed style.
                if buf and VT not in buf and b"MSH|" in buf:
                    message = bytes(buf)
                    buf.clear()
                    out("  (no MLLP framing - treating as bare HL7)")
                    conn.sendall(handler.handle(message, peer))
                continue
            if not data:
                break
            buf.extend(data)

            # Drain every complete MLLP frame currently in the buffer.
            while True:
                start = buf.find(VT)
                if start < 0:
                    break  # no frame start yet - keep bytes, may be unframed
                end = buf.find(bytes([FS, CR]), start)
                if end < 0:
                    del buf[:start]  # keep the partial frame, drop leading junk
                    break
                message = bytes(buf[start + 1:end])
                del buf[:end + 2]
                reply = handler.handle(message, peer)
                conn.sendall(bytes([VT]) + reply + bytes([FS, CR]))
    except OSError as exc:
        out("  %s error: %s" % (peer, exc))
    finally:
        # Never drop undecoded bytes silently - an unrecognised ADT dialect
        # would otherwise vanish without trace.
        if buf:
            out("  !! %d unconsumed byte(s) at close:" % len(buf),
                "     hex : %s" % bytes(buf[:256]).hex(),
                "     text: %r" % bytes(buf[:256]))
        conn.close()
        out("*** DISCONNECT %s" % peer)


def serve(port, handler, stop):
    srv = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    srv.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    try:
        srv.bind(("0.0.0.0", port))
    except OSError as exc:
        out("  ! cannot bind tcp/%d: %s" % (port, exc))
        return
    srv.listen(8)
    srv.settimeout(0.5)
    out("  listening on tcp/%-5d  %s" % (port, handler.role))
    while not stop.is_set():
        try:
            conn, addr = srv.accept()
        except socket.timeout:
            continue
        except OSError:
            break
        conn.setsockopt(socket.IPPROTO_TCP, socket.TCP_NODELAY, 1)
        threading.Thread(target=serve_client, args=(conn, addr, handler, stop),
                         daemon=True).start()
    srv.close()


class PatientStore:
    """patients.json, re-read automatically whenever the file changes.

    Records get added while someone is standing at the monitor testing IDs, so
    picking up edits without a restart avoids a confusing NOT FOUND for a
    patient that was just added.
    """

    def __init__(self, path):
        self.path = path
        self.mtime = None
        self.records = {}
        self.reload()

    def reload(self):
        try:
            mtime = os.path.getmtime(self.path)
        except OSError:
            return
        if mtime == self.mtime:
            return
        try:
            with open(self.path, "r", encoding="utf-8") as fh:
                data = json.load(fh)
        except (OSError, ValueError) as exc:
            out("  !! cannot read %s: %s" % (self.path, exc))
            return
        first = self.mtime is None
        self.mtime = mtime
        # Keys beginning with "_" are notes in the file, not patients.
        self.records = {str(k): v for k, v in data.items()
                        if not str(k).startswith("_")}
        if not first:
            out("  patients.json reloaded: %d record(s)" % len(self.records))

    def get(self, key):
        self.reload()
        return self.records.get(key)

    def search(self, params, limit=50):
        """Select every candidate matching a PDQ query.

        An unfiltered query means "the whole list" - that is what the monitor's
        ADT patient list screen asks for, and answering it with a single record
        is why that list comes back empty while local patients still show. IDs
        match on prefix as well as exactly, and names on substring, so a partial
        entry returns a set of candidates to choose from.
        """
        self.reload()

        def want(key):
            return (params.get(key) or "").strip()

        wanted_id = want("@PID.3.1") or want("@PID.3") or want("@PID.2")
        family = want("@PID.5.1").upper()
        given = want("@PID.5.2").upper()
        dob = want("@PID.7.1") or want("@PID.7")
        sex = want("@PID.8").upper()

        hits = []
        for pid in sorted(self.records):
            rec = self.records[pid]
            if wanted_id and not (pid == wanted_id or pid.startswith(wanted_id)):
                continue
            if family and family not in str(rec.get("last", "")).upper():
                continue
            if given and given not in str(rec.get("first", "")).upper():
                continue
            if dob and str(rec.get("dob", "")) != dob:
                continue
            if sex and str(rec.get("sex", "")).upper() != sex:
                continue
            hits.append((pid, rec))
        return hits[:limit]

    def __len__(self):
        return len(self.records)


def main():
    ap = argparse.ArgumentParser(description="Mindray VS8 HL7/MLLP gateway.")
    ap.add_argument("--hl7-port", type=int, default=2575,
                    help="port the monitor sends vitals to")
    ap.add_argument("--adt-port", type=int, default=3502,
                    help="port the monitor queries for patient demographics")
    ap.add_argument("--patients", default=None, help="patient store (JSON)")
    ap.add_argument("--app", default="SMARTWARD", help="our HL7 application name")
    ap.add_argument("--facility", default="SMARTWARD", help="our HL7 facility name")
    ap.add_argument("--duration", type=float, default=0, help="stop after N seconds")
    args = ap.parse_args()

    here = os.path.dirname(os.path.abspath(__file__))
    patients_path = args.patients or os.path.join(here, "patients.json")
    logdir = os.path.join(here, "logs")
    os.makedirs(logdir, exist_ok=True)
    log_path = os.path.join(logdir, "vs8_hl7_%s.jsonl"
                            % datetime.now().strftime("%Y%m%d_%H%M%S"))

    patients = PatientStore(patients_path)

    print("=" * 70)
    print("Mindray VS8 - HL7 / MLLP gateway")
    print("=" * 70)
    print("patients : %s (%d record%s)"
          % (patients_path, len(patients), "" if len(patients) == 1 else "s"))
    print("log      : %s" % log_path)
    print()

    logfile = open(log_path, "a", encoding="utf-8")
    stop = threading.Event()
    threads = []
    for port, role in ((args.hl7_port, "VITALS"), (args.adt_port, "ADT-QUERY")):
        handler = Handler(role, patients, logfile, args.app, args.facility)
        t = threading.Thread(target=serve, args=(port, handler, stop), daemon=True)
        t.start()
        threads.append(t)

    print("\nWaiting for the monitor. Ctrl+C to stop.")
    sys.stdout.flush()
    deadline = time.time() + args.duration if args.duration else None
    try:
        while True:
            if deadline and time.time() >= deadline:
                print("\nDuration reached.")
                break
            time.sleep(0.25)
    except KeyboardInterrupt:
        print("\nStopping ...")
    finally:
        stop.set()
        for t in threads:
            t.join(timeout=2)
        logfile.close()
        print("Log: %s" % log_path)
    return 0


if __name__ == "__main__":
    sys.exit(main())
