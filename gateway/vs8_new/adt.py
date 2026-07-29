#!/usr/bin/env python3
r"""
ADT responder for the Mindray VS8.

One job: listen on tcp/3502 and answer the monitor's patient demographics
query. No vitals, no discovery, no other ports.

What the VS8 sends (IHE ITI-22, wrapped in MLLP):

    MSH|^~\&|MINDRAY_^00A037000000^EUI-64|MINDRAY|||20260727130121.0000
       ||QBP^ZV1^QBP_Q21|7|P|2.6|||AL|NE||UNICODE UTF-8|||ITI-22^IHE
    QPD|IHE PDQ Query|QueryTag_8|@PID.3.1^10003
    RCP|I|50^RD

What it expects back:

    MSH|^~\&|MINDRAY_EGATEWAY^...^EUI-64|MINDRAY|MINDRAY_^...|MINDRAY
       |20260727130126.0000+0800||RSP^ZV2^RSP_ZV2|<id>|P|2.6
       |||AL|NE||UNICODE UTF-8|||ITI-22^IHE
    MSA|AA|7
    QAK|QueryTag_8|OK|IHE PDQ Query|1|1|0
    QPD|IHE PDQ Query|QueryTag_8|@PID.3.1^10003
    PID|1||10003^^^Hospital^PI||MEI LING^LEE^^^^^L||19910707|F
    PV1|1|I|D6^05^01||||||||||||||||V00010003

Details that matter, learned from this monitor and from real eGateway traffic:

  * QBP^ZV1 must be answered with RSP^ZV2 (not the plain PDQ RSP^K22).
  * QPD-3 is a '~' separated list of <@reference>^<value> pairs, so the ID sits
    in component 2 of '@PID.3.1^10003', not in the field itself.
  * QAK-4 is the hit count. Leave it empty and the monitor reads zero records
    and discards the PID segments that follow.
  * PID-5 needs the name type code L. Mindray orders it <given>^<family>, the
    reverse of the HL7 XPN standard - their own traffic carries
    'John^Smith-Demo' for the patient John Smith-Demo.
  * MSH-7 in real eGateway traffic is '20220520121219.0000+0800' - fractional
    seconds plus timezone, not a bare timestamp.
  * An empty QPD-3 is a request for the whole list, so return every patient -
    that is what fills the monitor's ADT patient list.

If the monitor answers the query but leaves the name blank, work through the
toggles below - each one mimics a different part of the eGateway's format:

    py -3 adt.py --name-order family    # swap PID-5 back to family^given
    py -3 adt.py --no-set-id            # PID-1 empty, as the eGateway sends it
    py -3 adt.py --no-pv1               # demographics only, drop the visit
    py -3 adt.py --authority ""         # bare ID with no assigning authority
    py -3 adt.py --plain-timestamp      # bare MSH-7, no fraction or timezone

Edit PATIENTS below, then:

    py -3 adt.py
"""

import argparse
import socket
import sys
import threading
from datetime import datetime

VT, FS, CR = 0x0B, 0x1C, 0x0D

# Keyed by the patient ID typed on the monitor.
PATIENTS = {
    "10001": {"last": "SITI NURHALIZA", "first": "BINTI ABDULLAH",
              "dob": "19790215", "sex": "F",
              "ward": "D5", "room": "01", "bed": "01", "visit": "V00010001"},
    "10002": {"last": "RAJESH", "first": "KUMAR",
              "dob": "19680922", "sex": "M",
              "ward": "D5", "room": "02", "bed": "03", "visit": "V00010002"},
    "10003": {"last": "LEE", "first": "MEI LING",
              "dob": "19910707", "sex": "F",
              "ward": "D6", "room": "05", "bed": "01", "visit": "V00010003"},
    "12345": {"last": "TAN", "first": "AH KOW",
              "dob": "19551103", "sex": "M",
              "ward": "D6", "room": "03", "bed": "02", "visit": "V00012345"},
    "99999": {"last": "TEST", "first": "PATIENT",
              "dob": "19800101", "sex": "M",
              "ward": "D5", "room": "12", "bed": "01", "visit": "V00099999"},
}

OPTS = argparse.Namespace(
    name_order="given", set_id=True, pv1=True, authority="Hospital",
    id_type="PI", timestamp="full", app="MINDRAY_EGATEWAY", facility="MINDRAY",
)


def now():
    """MSH-7. Real eGateway traffic uses 20220520121219.0000+0800."""
    stamp = datetime.now().astimezone()
    if OPTS.timestamp == "plain":
        return stamp.strftime("%Y%m%d%H%M%S")
    return stamp.strftime("%Y%m%d%H%M%S") + ".0000" + stamp.strftime("%z")


def field(segment, n):
    """HL7 field. MSH is offset by one because MSH-1 is the separator itself."""
    parts = segment.split("|")
    index = n - 1 if parts[:1] == ["MSH"] else n
    return parts[index] if 0 <= index < len(parts) else ""


def component(value, n):
    parts = value.split("^")
    return parts[n - 1] if 0 < n <= len(parts) else ""


def find(segments, name):
    for segment in segments:
        if segment.startswith(name + "|"):
            return segment
    return ""


def criteria(qpd3):
    """Parse QPD-3: '@PID.3.1^10003~@PID.5.1^LEE' -> {'@PID.3.1': '10003', ...}"""
    found = {}
    for repeat in qpd3.split("~"):
        key, value = component(repeat, 1), component(repeat, 2)
        if key.startswith("@") and value:
            found[key] = value
    return found


def match(params, limit):
    """Candidates for the query. No criteria means the whole list."""
    wanted = params.get("@PID.3.1", "").strip()
    family = params.get("@PID.5.1", "").strip().upper()
    given = params.get("@PID.5.2", "").strip().upper()

    hits = []
    for pid in sorted(PATIENTS):
        record = PATIENTS[pid]
        if wanted and not pid.startswith(wanted):
            continue
        if family and family not in record["last"].upper():
            continue
        if given and given not in record["first"].upper():
            continue
        hits.append((pid, record))
    return hits[:limit]


def build_pid(index, pid, record):
    if OPTS.name_order == "given":
        name = "%s^%s" % (record["first"], record["last"])
    else:
        name = "%s^%s" % (record["last"], record["first"])

    identifier = pid
    if OPTS.authority or OPTS.id_type:
        identifier = "%s^^^%s^%s" % (pid, OPTS.authority, OPTS.id_type)

    return "PID|%s||%s||%s^^^^^L||%s|%s" % (
        index if OPTS.set_id else "", identifier, name,
        record["dob"], record["sex"])


def build_pv1(record):
    return "PV1|1|I|%s^%s^%s||||||||||||||||%s" % (
        record["ward"], record["room"], record["bed"], record["visit"])


def build_response(segments):
    msh = find(segments, "MSH")
    qpd = find(segments, "QPD") or "QPD|IHE PDQ Query|Q1|"
    rcp = find(segments, "RCP")

    limit = 50
    if rcp:
        quantity = component(field(rcp, 2), 1)
        if quantity.isdigit():
            limit = max(1, int(quantity))

    params = criteria(field(qpd, 3))
    hits = match(params, limit)

    # Mirror the trigger event: ZV1 (ITI-22) is answered with ZV2.
    trigger = component(field(msh, 9), 2)
    msg_type = "RSP^ZV2^RSP_ZV2" if trigger == "ZV1" else "RSP^K22^RSP_K21"

    lines = [
        "MSH|^~\\&|%s|%s|%s|%s|%s||%s|RSP%s|P|%s|||AL|NE||UNICODE UTF-8|||%s"
        % (OPTS.app, OPTS.facility, field(msh, 3), field(msh, 4), now(),
           msg_type, datetime.now().strftime("%Y%m%d%H%M%S"),
           field(msh, 12) or "2.6", field(msh, 21)),
        "MSA|AA|%s" % field(msh, 10),
        "QAK|%s|%s|%s|%d|%d|0"
        % (field(qpd, 2) or "Q1", "OK" if hits else "NF",
           field(qpd, 1), len(hits), len(hits)),
        qpd,
    ]
    for index, (pid, record) in enumerate(hits, 1):
        lines.append(build_pid(index, pid, record))
        if OPTS.pv1:
            lines.append(build_pv1(record))

    return "\r".join(lines) + "\r", params, hits


def handle(raw, peer):
    text = raw.decode("utf-8", "replace")
    segments = [s for s in text.replace("\n", "\r").split("\r") if s.strip()]

    print("\n[%s] query from %s" % (datetime.now().strftime("%H:%M:%S"), peer))
    for segment in segments:
        print("  < " + segment)

    reply, params, hits = build_response(segments)

    shown = ", ".join("%s=%s" % kv for kv in sorted(params.items()))
    print("  criteria: %s" % (shown or "(none - whole list)"))
    print("  matched : %d  %s"
          % (len(hits), ", ".join(pid for pid, _ in hits[:10])))
    for segment in reply.split("\r"):
        if segment:
            print("  > " + segment)
    sys.stdout.flush()
    return reply.encode("utf-8")


def serve_client(conn, addr):
    peer = "%s:%d" % addr
    buf = bytearray()
    try:
        conn.settimeout(30)
        while True:
            data = conn.recv(65535)
            if not data:
                break
            buf.extend(data)
            while True:
                start = buf.find(VT)
                if start < 0:
                    break
                end = buf.find(bytes([FS, CR]), start)
                if end < 0:
                    break
                message = bytes(buf[start + 1:end])
                del buf[:end + 2]
                conn.sendall(bytes([VT]) + handle(message, peer) + bytes([FS, CR]))
    except (OSError, socket.timeout):
        pass
    finally:
        conn.close()


def main():
    ap = argparse.ArgumentParser(description="ADT responder for the Mindray VS8.")
    ap.add_argument("--port", type=int, default=3502)
    ap.add_argument("--name-order", choices=("given", "family"), default="given",
                    help="PID-5 order; Mindray sends given^family (default)")
    ap.add_argument("--no-set-id", action="store_true",
                    help="leave PID-1 empty, as the eGateway does")
    ap.add_argument("--no-pv1", action="store_true", help="omit the PV1 segment")
    ap.add_argument("--authority", default="Hospital",
                    help="PID-3 assigning authority ('' for a bare ID)")
    ap.add_argument("--id-type", default="PI", help="PID-3 identifier type code")
    ap.add_argument("--plain-timestamp", action="store_true",
                    help="bare MSH-7 without fractional seconds or timezone")
    ap.add_argument("--app", default="MINDRAY_EGATEWAY", help="our MSH-3")
    ap.add_argument("--facility", default="MINDRAY", help="our MSH-4")
    args = ap.parse_args()

    OPTS.name_order = args.name_order
    OPTS.set_id = not args.no_set_id
    OPTS.pv1 = not args.no_pv1
    OPTS.authority = args.authority
    OPTS.id_type = args.id_type
    OPTS.timestamp = "plain" if args.plain_timestamp else "full"
    OPTS.app = args.app
    OPTS.facility = args.facility

    srv = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    srv.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    srv.bind(("0.0.0.0", args.port))
    srv.listen(8)

    print("ADT responder on tcp/%d, %d patient(s): %s"
          % (args.port, len(PATIENTS), ", ".join(sorted(PATIENTS))))
    print("PID-5 order=%s  set-id=%s  pv1=%s  authority=%r  timestamp=%s"
          % (OPTS.name_order, OPTS.set_id, OPTS.pv1, OPTS.authority,
             OPTS.timestamp))
    print("Ctrl+C to stop.")
    sys.stdout.flush()

    try:
        while True:
            conn, addr = srv.accept()
            threading.Thread(target=serve_client, args=(conn, addr),
                             daemon=True).start()
    except KeyboardInterrupt:
        print("\nStopped.")
    finally:
        srv.close()
    return 0


if __name__ == "__main__":
    sys.exit(main())
