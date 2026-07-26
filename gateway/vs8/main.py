#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
Mindray VS8 patient-lookup server (stdlib only, no pip install needed).

What this does
--------------
The VS8 has an "ADT query" function: the nurse types a patient ID (MRN) on the
monitor, the monitor asks a server who that is, and shows the name it gets back.
This script is that server. Patient details come from `patients.json`, which is
re-read on every query - edit the file and the monitor sees the change on its
next lookup, no restart.

    nurse types 12345  ->  VS8 sends QRY^A19  ->  we answer ADR^A19
                                               <- name shown on the monitor

Protocol (Mindray PDS / ADT Net Query Interface)
------------------------------------------------
Mindray monitors and eGateway use the HL7 v2.3.1 A19 query dialect over MLLP:

    IN   MSH|^~\\&|Mindray|Gateway|||20260724134901||QRY^A19|2|P|2.3.1
         QRD|20260724134901000|D|D|1|||1^RD|12345|^DEM|^MindrayGateway

    OUT  MSH|^~\\&|VS8_GATEWAY|SMARTWARD|Mindray|Gateway|<ts>||ADR^A19|<id>|P|2.3.1
         MSA|AA|2|The Patient is Found
         QRD|<echoed back verbatim>
         PID|1||12345||Doe^John||19800115|M
         PV1|1|I|^^ICU&01&0&0&0
         OBX|1|NM|52^Height||170|cm|||||F
         OBX|2|NM|51^Weight||70|kg|||||F

Details that matter to the device:

* The patient ID is in QRD-8 (Who Subject Filter) - that is what the nurse typed.
* The QRD segment is echoed back verbatim; Mindray matches the response to its
  pending query with it.
* A miss is still MSA|AA, with the reason in MSA-3 ("The patient is not found!").
  An AE reads as a transport failure to Mindray senders and can make them retry
  forever instead of telling the nurse the ID is unknown.
* PV1-3 packs department and bed as sub-components: `^^Dept&Bed&0&0&0`.
* Height/weight ride along as OBX 52/51 when known, which pre-fills those fields
  on the monitor.

Everything received and sent is logged to the console and to
`logs/vs8-YYYY-MM-DD.log`, and the raw bytes of each connection are kept under
`capture/` so an unexpected message can be examined exactly as it arrived.

Usage
-----
    python main.py            # listen on 0.0.0.0:2575
    python main.py 2576       # ... on another port

Then on the VS8: set the ADT/query server to this machine's IP and that port.
"""

import json
import os
import socket
import socketserver
import sys
import threading
from datetime import datetime

# --- configuration (environment overrides, all optional) --------------------
HERE = os.path.dirname(os.path.abspath(__file__))
HOST = os.getenv("LISTEN_HOST", "0.0.0.0")
PORT = int(sys.argv[1]) if len(sys.argv) > 1 else int(os.getenv("LISTEN_PORT", "2575"))
PATIENTS_FILE = os.getenv("PATIENTS_FILE", os.path.join(HERE, "patients.json"))
LOG_DIR = os.getenv("LOG_DIR", os.path.join(HERE, "logs"))
# Byte-exact archive of every connection. Set CAPTURE_DIR="" to switch it off.
CAPTURE_DIR = os.getenv("CAPTURE_DIR", os.path.join(HERE, "capture"))
# What we call ourselves in MSH-3 / MSH-4 of our replies.
SENDING_APP = os.getenv("SENDING_APP", "VS8_GATEWAY")
SENDING_FACILITY = os.getenv("SENDING_FACILITY", "SMARTWARD")
# Seconds of silence before un-framed (non-MLLP) data is treated as a message.
IDLE_FLUSH = float(os.getenv("IDLE_FLUSH_SECONDS", "5"))

# --- MLLP framing -----------------------------------------------------------
VT, FS, CR = 0x0B, 0x1C, 0x0D
END = bytes([FS, CR])

_print_lock = threading.Lock()
_counter_lock = threading.Lock()
_counter = 0


# --- logging ----------------------------------------------------------------
def log(text, level="INFO"):
    """One line to the console and to today's log file."""
    now = datetime.now()
    line = "[%s] [%s] %s" % (now.strftime("%Y-%m-%d %H:%M:%S"), level, text)
    with _print_lock:
        print(line, flush=True)
        try:
            os.makedirs(LOG_DIR, exist_ok=True)
            path = os.path.join(LOG_DIR, "vs8-%s.log" % now.strftime("%Y-%m-%d"))
            with open(path, "a", encoding="utf-8") as fh:
                fh.write(line + "\n")
        except OSError as exc:
            print("(could not write log file: %s)" % exc, flush=True)


def log_message(direction, peer, hl7):
    """Log a whole HL7 message with one segment per line, so it stays readable."""
    segments = [s for s in hl7.replace("\n", "\r").split("\r") if s.strip()]
    body = "\n".join("    " + s for s in segments)
    log("%s %s\n%s" % (direction, peer, body))


def next_message_id(prefix):
    global _counter
    with _counter_lock:
        _counter = (_counter + 1) % 100000
        return "%s%s%d" % (prefix, datetime.now().strftime("%Y%m%d%H%M%S"), _counter)


# --- patient store ----------------------------------------------------------
class PatientStore:
    """patients.json, re-read whenever it changes on disk.

    Keeping the last good copy in memory means a half-saved or malformed file
    never takes the lookup down mid-shift - the monitor keeps getting answers
    from the previous version until the file parses again.
    """

    def __init__(self, path):
        self.path = path
        self._lock = threading.Lock()
        self._patients = {}
        self._mtime = None
        self.reload(initial=True)

    @staticmethod
    def _key(value):
        return str(value or "").strip().upper()

    def reload(self, initial=False):
        try:
            mtime = os.path.getmtime(self.path)
        except OSError as exc:
            if initial:
                log("patients.json not found at %s (%s) - every lookup will "
                    "answer 'not found' until it exists" % (self.path, exc), "WARNING")
            return
        if self._mtime is not None and mtime == self._mtime:
            return
        try:
            with open(self.path, "r", encoding="utf-8-sig") as fh:
                raw = json.load(fh)
        except (OSError, ValueError) as exc:
            log("Could not read %s: %s (keeping the %d patient(s) already loaded)"
                % (self.path, exc, len(self._patients)), "ERROR")
            self._mtime = mtime  # do not re-report the same broken file every query
            return

        patients = {}
        # Accept either {"12345": {...}} or [{"mrn": "12345", ...}].
        entries = raw.items() if isinstance(raw, dict) else [
            (item.get("mrn") or item.get("id"), item) for item in raw if isinstance(item, dict)
        ]
        for key, value in entries:
            if not isinstance(value, dict):
                continue
            record = dict(value)
            record.setdefault("mrn", key)
            # Any identifier the nurse might type resolves to the same patient.
            for alias in (key, value.get("mrn"), value.get("id"),
                          value.get("rn"), value.get("visit_number")):
                if self._key(alias):
                    patients[self._key(alias)] = record

        with self._lock:
            self._patients = patients
            self._mtime = mtime
        log("Loaded %d patient(s) from %s" % (len(set(id(p) for p in patients.values())), self.path))

    def find(self, patient_id):
        self.reload()
        with self._lock:
            return self._patients.get(self._key(patient_id))


# --- HL7 helpers ------------------------------------------------------------
def split_segments(hl7):
    return [s.split("|") for s in hl7.replace("\n", "\r").split("\r") if s.strip()]


def find_segment(segments, name):
    for seg in segments:
        if seg and seg[0].upper() == name:
            return seg
    return None


def field(seg, index):
    if not seg or index >= len(seg):
        return ""
    return seg[index].strip()


def component(value, index=0):
    parts = value.split("~", 1)[0].split("^")
    return parts[index].strip() if index < len(parts) else ""


def escape(value):
    """Escape HL7 delimiters in free text (a name may contain & or ^)."""
    if value is None:
        return ""
    return (str(value).replace("\\", "\\E\\").replace("|", "\\F\\")
            .replace("^", "\\S\\").replace("~", "\\R\\").replace("&", "\\T\\")
            .replace("\r", " ").replace("\n", " "))


def hl7_now():
    return datetime.now().strftime("%Y%m%d%H%M%S")


def patient_name(record):
    """PID-5 as Family^Given.

    `firstName`/`lastName` are used when present; a single `name` field is put
    in the family component whole, because guessing which word is the family
    name mangles names that do not follow Western ordering.
    """
    last = record.get("lastName") or record.get("family")
    first = record.get("firstName") or record.get("given")
    if last or first:
        return "%s^%s" % (escape(last or ""), escape(first or ""))
    return "%s^" % escape(record.get("name") or "")


def patient_dob(record):
    """PID-7 wants YYYYMMDD; accept 1980-01-15 or 19800115 in the file."""
    digits = "".join(ch for ch in str(record.get("dob") or record.get("date_of_birth") or "")[:10]
                     if ch.isdigit())
    return digits if len(digits) == 8 else ""


def patient_sex(record):
    initial = str(record.get("sex") or record.get("gender") or "").strip()[:1].upper()
    return initial if initial in ("M", "F", "O", "U") else ""


# --- response builders ------------------------------------------------------
def build_msh(inbound_msh, message_type, version_default="2.3.1"):
    """Our MSH, addressed back to whoever sent the query."""
    return "|".join([
        "MSH", r"^~\&", SENDING_APP, SENDING_FACILITY,
        field(inbound_msh, 2), field(inbound_msh, 3),  # their app/facility become ours
        hl7_now(), "",
        message_type, next_message_id(message_type.split("^")[0]),
        field(inbound_msh, 10) or "P",
        field(inbound_msh, 11) or version_default,
    ])


def build_ack(segments, code="AA", text=""):
    msh = find_segment(segments, "MSH")
    fields = ["MSA", code, field(msh, 9)]
    if text:
        fields.append(text)
    return build_msh(msh, "ACK") + "\r" + "|".join(fields) + "\r"


def build_adr_a19(segments, store):
    """ADR^A19 - the answer the VS8 is waiting for."""
    msh = find_segment(segments, "MSH")
    qrd_seg = find_segment(segments, "QRD")
    qrd = "|".join(qrd_seg) if qrd_seg else "QRD"
    control_id = field(msh, 9)

    # QRD-8 holds what the nurse typed; fall back to a PID in the query.
    patient_id = component(field(qrd_seg, 8))
    if not patient_id:
        pid = find_segment(segments, "PID")
        for idx in (3, 2, 18):
            patient_id = component(field(pid, idx))
            if patient_id:
                break

    header = build_msh(msh, "ADR^A19")
    record = store.find(patient_id) if patient_id else None

    if not record:
        log("Lookup '%s' -> NOT FOUND in patients.json" % patient_id, "WARNING")
        # Deliberately AA: the query was understood, the patient simply is not
        # on file. MSA-3 is what the monitor shows the nurse.
        return "\r".join([header,
                          "MSA|AA|%s|The patient is not found!" % control_id,
                          qrd]) + "\r"

    display = record.get("name") or "%s, %s" % (record.get("lastName", ""), record.get("firstName", ""))
    log("Lookup '%s' -> %s (%s bed %s)" % (patient_id, display.strip(", "),
                                           record.get("ward") or "no ward",
                                           record.get("bed") or "-"))

    lines = [
        header,
        "MSA|AA|%s|The Patient is Found" % control_id,
        qrd,
        "PID|1||%s||%s||%s|%s" % (escape(record.get("mrn") or patient_id),
                                  patient_name(record), patient_dob(record), patient_sex(record)),
        "PV1|1|I|^^%s&%s&0&0&0" % (escape(record.get("ward") or ""), escape(record.get("bed") or "")),
    ]
    # Optional, and worth sending: these pre-fill height/weight on the monitor.
    obx = 0
    if record.get("height"):
        obx += 1
        lines.append("OBX|%d|NM|52^Height||%s|cm|||||F" % (obx, record["height"]))
    if record.get("weight"):
        obx += 1
        lines.append("OBX|%d|NM|51^Weight||%s|kg|||||F" % (obx, record["weight"]))
    return "\r".join(lines) + "\r"


def build_orf_r04(segments, store):
    """ORF^R04 - the other query dialect (QRY^R02), kept so a monitor or
    eGateway configured that way still gets an answer."""
    msh = find_segment(segments, "MSH")
    qrd_seg = find_segment(segments, "QRD")
    qrd = "|".join(qrd_seg) if qrd_seg else "QRD"
    qrfs = ["|".join(s) for s in segments if s and s[0].upper() == "QRF"]
    control_id = field(msh, 9)
    patient_id = component(field(qrd_seg, 8))

    header = build_msh(msh, "ORF^R04", version_default="2.6")
    record = store.find(patient_id) if patient_id else None
    if not record:
        log("Lookup '%s' (R02) -> NOT FOUND in patients.json" % patient_id, "WARNING")
        return "\r".join([header, "MSA|AE|%s|Patient not found" % control_id, qrd]) + "\r"

    log("Lookup '%s' (R02) -> %s" % (patient_id, record.get("name") or record.get("lastName")))
    pid_fields = [""] * 19
    pid_fields[0] = "PID"
    pid_fields[3] = escape(record.get("mrn") or patient_id)
    pid_fields[5] = patient_name(record)
    pid_fields[7] = patient_dob(record)
    pid_fields[8] = patient_sex(record)
    pid_fields[18] = escape(record.get("mrn") or patient_id)
    return "\r".join([header, "MSA|AA|%s" % control_id, qrd] + qrfs + [
        "|".join(pid_fields),
        "PV1||I|%s^^%s" % (escape(record.get("ward") or ""), escape(record.get("bed") or "")),
    ]) + "\r"


def summarize_oru(segments):
    """Readable one-liner for an incoming vitals message, so the log shows what
    the VS8 measured without having to read raw HL7."""
    values = []
    for seg in segments:
        if not seg or seg[0].upper() != "OBX":
            continue
        identifier = field(seg, 3)
        label = component(identifier, 1) or component(identifier, 0)
        value = field(seg, 5)
        units = component(field(seg, 6), 1) or component(field(seg, 6), 0)
        if label and value:
            values.append("%s=%s%s" % (label, value, (" " + units) if units else ""))
    return " ".join(values)


# --- message routing --------------------------------------------------------
def handle_message(hl7, store):
    """Decide what to send back. Returns the reply message, or None for silence."""
    segments = split_segments(hl7)
    msh = find_segment(segments, "MSH")
    if not msh:
        log("Not an HL7 message (no MSH) - ignoring %d bytes" % len(hl7), "WARNING")
        return None

    message_type = field(msh, 8)
    log("Message type: %s" % (message_type or "(none)"))

    if message_type.upper().startswith("QRY^A19"):
        return build_adr_a19(segments, store)
    if message_type.upper().startswith("QRY") or message_type.upper().startswith("QBP"):
        # QBP^Q22 (PDQ) would land here too - it is answered as best we can and
        # logged in full, so the real shape can be added once a device sends one.
        log("Query dialect %s answered with the R02/ORF shape - check the log if "
            "the monitor rejects it" % message_type, "WARNING")
        return build_orf_r04(segments, store)
    if message_type.upper().startswith("ORU"):
        summary = summarize_oru(segments)
        pid = find_segment(segments, "PID")
        log("Vitals from patient %s: %s" % (component(field(pid, 3)) or "?", summary or "(none decoded)"))
        return build_ack(segments)

    return build_ack(segments)


# --- MLLP server ------------------------------------------------------------
class Handler(socketserver.BaseRequestHandler):
    def setup(self):
        self.capture = None
        if not CAPTURE_DIR:
            return
        try:
            os.makedirs(CAPTURE_DIR, exist_ok=True)
            name = "%s_%s.bin" % (datetime.now().strftime("%Y%m%d-%H%M%S"),
                                  self.client_address[0].replace(":", "-"))
            self.capture = open(os.path.join(CAPTURE_DIR, name), "ab", buffering=0)
        except OSError as exc:
            log("Wire capture disabled for this connection: %s" % exc, "WARNING")

    def handle(self):
        peer = "%s:%d" % self.client_address[:2]
        log("CONNECTED %s" % peer)
        self.request.settimeout(IDLE_FLUSH)
        buffer = bytearray()
        try:
            while True:
                try:
                    chunk = self.request.recv(4096)
                except socket.timeout:
                    # A sender that forgot the MLLP wrapper would otherwise sit
                    # here unheard; treat a quiet buffer as a complete message.
                    if buffer and buffer.find(VT) == -1:
                        self.dispatch(bytes(buffer), peer, framed=False)
                        buffer.clear()
                    continue
                if not chunk:
                    break
                if self.capture:
                    self.capture.write(chunk)
                buffer.extend(chunk)

                while True:
                    start = buffer.find(VT)
                    if start == -1:
                        break
                    end = buffer.find(END, start + 1)
                    if end == -1:
                        break
                    frame = bytes(buffer[start + 1:end])
                    del buffer[:end + len(END)]
                    self.dispatch(frame, peer, framed=True)
        except OSError as exc:
            log("Socket error %s: %s" % (peer, exc), "WARNING")
        finally:
            if bytes(buffer).strip():
                self.dispatch(bytes(buffer), peer, framed=False)
            log("DISCONNECTED %s" % peer)

    def dispatch(self, data, peer, framed):
        hl7 = data.decode("utf-8", "replace")
        if not framed:
            log("Un-framed data from %s (no MLLP wrapper) - handling it anyway" % peer, "WARNING")
        log_message("RECEIVED from", peer, hl7)
        try:
            reply = handle_message(hl7, self.server.store)
        except Exception as exc:  # a bad message must not drop the connection
            log("Error handling message: %r" % exc, "ERROR")
            reply = None
        if not reply:
            return
        log_message("SENT to", peer, reply)
        framed_reply = bytes([VT]) + reply.encode("utf-8") + END
        if self.capture:
            self.capture.write(framed_reply)
        try:
            self.request.sendall(framed_reply)
        except OSError as exc:
            log("Could not send reply to %s: %s" % (peer, exc), "WARNING")

    def finish(self):
        if self.capture:
            try:
                self.capture.close()
            except OSError:
                pass


class Server(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
    daemon_threads = True


def main():
    store = PatientStore(PATIENTS_FILE)
    try:
        server = Server((HOST, PORT), Handler)
    except OSError as exc:
        log("Cannot listen on %s:%d - %s" % (HOST, PORT, exc), "ERROR")
        return 1
    server.store = store

    log("Mindray VS8 patient lookup listening on %s:%d" % (HOST, PORT))
    log("Patients: %s (re-read automatically when you edit it)" % PATIENTS_FILE)
    log("Logs: %s" % LOG_DIR)
    if CAPTURE_DIR:
        log("Wire capture: %s" % CAPTURE_DIR)
    log("On the VS8, point the ADT/query server at this machine and port %d" % PORT)
    try:
        server.serve_forever(poll_interval=0.5)
    except KeyboardInterrupt:
        log("Stopped by user")
    finally:
        server.server_close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
