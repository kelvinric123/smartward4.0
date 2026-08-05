#!/usr/bin/env python3
"""
Loopback self-test for vs8_hl7.py.

Sends a synthetic ORU^R01 (vitals) and an ADT query over MLLP so the gateway can
be verified without waiting on the monitor. Run it against a gateway that is
already listening.

Usage:
    py -3 test_client.py
    py -3 test_client.py --host 192.168.1.5 --pid 99999
"""

import argparse
import socket
import sys
from datetime import datetime

VT, FS, CR = b"\x0b", b"\x1c", b"\x0d"


def send(host, port, message, label):
    print("=" * 70)
    print("%s  ->  %s:%d" % (label, host, port))
    print("=" * 70)
    for line in message.split("\r"):
        if line:
            print("  > " + line)

    sock = socket.create_connection((host, port), timeout=10)
    try:
        sock.sendall(VT + message.encode("utf-8") + FS + CR)
        buf = b""
        while FS + CR not in buf:
            chunk = sock.recv(65535)
            if not chunk:
                break
            buf += chunk
    finally:
        sock.close()

    if not buf:
        print("\n  !! no reply")
        return False
    reply = buf.strip(VT).split(FS)[0].decode("utf-8", "replace")
    print("\n  reply:")
    for line in reply.replace("\n", "\r").split("\r"):
        if line:
            print("  < " + line)
    print()
    return True


def main():
    ap = argparse.ArgumentParser(description="Self-test the VS8 HL7 gateway.")
    ap.add_argument("--host", default="127.0.0.1")
    ap.add_argument("--hl7-port", type=int, default=2575)
    ap.add_argument("--adt-port", type=int, default=3502)
    ap.add_argument("--pid", default="99999")
    args = ap.parse_args()

    ts = datetime.now().strftime("%Y%m%d%H%M%S")

    oru = "\r".join([
        "MSH|^~\\&|VS8|MINDRAY|SMARTWARD|SMARTWARD|%s||ORU^R01|MSG%s|P|2.6" % (ts, ts),
        "PID|1||%s^^^MINDRAY^MR||TEST^PATIENT||19800101|M" % args.pid,
        "PV1|1|I|D5^12^01",
        "OBR|1||%s|MDC_DEV_MON_PHYSIO_MULTI_PARAM_MDS^^MDC" % ts,
        "OBX|1|NM|MDC_ECG_HEART_RATE^Heart rate^MDC||78|/min^/min^MDC|||||F|||%s" % ts,
        "OBX|2|NM|MDC_PULS_OXIM_SAT_O2^SpO2^MDC||98|%%^%%^MDC|||||F|||%s" % ts,
        "OBX|3|NM|MDC_PRESS_BLD_NONINV_SYS^NIBP systolic^MDC||121|mm[Hg]^mmHg^MDC|||||F|||%s" % ts,
        "OBX|4|NM|MDC_PRESS_BLD_NONINV_DIA^NIBP diastolic^MDC||79|mm[Hg]^mmHg^MDC|||||F|||%s" % ts,
        "OBX|5|NM|MDC_TEMP_BODY^Body temperature^MDC||36.8|Cel^Cel^MDC|||||F|||%s" % ts,
    ]) + "\r"

    qry = "\r".join([
        "MSH|^~\\&|VS8|MINDRAY|SMARTWARD|SMARTWARD|%s||QRY^A19|QRY%s|P|2.6" % (ts, ts),
        "QRD|%s|R|I|Q%s|||1^RD|%s|DEM" % (ts, ts, args.pid),
    ]) + "\r"

    qbp = "\r".join([
        "MSH|^~\\&|VS8|MINDRAY|SMARTWARD|SMARTWARD|%s||QBP^Q22^QBP_Q21|QBP%s|P|2.6"
        % (ts, ts),
        # IHE PDQ style: QPD-3 is a repeating QIP field, so the ID sits in
        # component 2 behind an @PID.3.1 reference rather than in the clear.
        "QPD|Q22^Find Candidates^HL7nnnn|Q1|@PID.3.1^%s" % args.pid,
        "RCP|I|10^RD",
    ]) + "\r"

    # Byte-for-byte what the VS8 actually sends, captured from the wire.
    zv1 = "\r".join([
        "MSH|^~\\&|MINDRAY_^00A037000000^EUI-64|MINDRAY|||%s.0000"
        "||QBP^ZV1^QBP_Q21|3|P|2.6|||AL|NE||UNICODE UTF-8|||ITI-22^IHE" % ts,
        "QPD|IHE PDQ Query|QueryTag_4|@PID.3.1^%s" % args.pid,
        "RCP|I|50^RD",
    ]) + "\r"

    def zv1_query(tag, qpd3):
        """The VS8's own ITI-22 form, with whatever criteria we want to try."""
        return "\r".join([
            "MSH|^~\\&|MINDRAY_^00A037000000^EUI-64|MINDRAY|||%s.0000"
            "||QBP^ZV1^QBP_Q21|%s|P|2.6|||AL|NE||UNICODE UTF-8|||ITI-22^IHE"
            % (ts, tag),
            "QPD|IHE PDQ Query|QueryTag_%s|%s" % (tag, qpd3),
            "RCP|I|50^RD",
        ]) + "\r"

    ok = True
    ok &= send(args.host, args.hl7_port, oru, "ORU^R01 vitals")
    ok &= send(args.host, args.adt_port, qry, "QRY^A19 ADT query")
    ok &= send(args.host, args.adt_port, qbp, "QBP^Q22 ADT query")
    ok &= send(args.host, args.adt_port, zv1, "QBP^ZV1 ADT query (real VS8 format)")
    # The patient-list case: no criteria at all should return the whole list.
    ok &= send(args.host, args.adt_port, zv1_query("9", ""),
               "QBP^ZV1 unfiltered (ADT patient list)")
    ok &= send(args.host, args.adt_port, zv1_query("10", "@PID.3.1^100"),
               "QBP^ZV1 prefix 100 (partial ID)")

    print("=" * 70)
    print("self-test %s" % ("PASSED" if ok else "FAILED"))
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
