"""
Simulate a CM100 stream against the v2 gateway to prove the whole chain:
continuous SpO2/Pulse messages fold into one profile, a different BP closes it
(one queued record), empty cycles and missing MRNs are handled.

Real CM100 quirks reproduced: label in OBX-3 component 2, empty OBX-5 for
"no reading this cycle", useless device clock (2013-01-01).

Usage:  python test_sender.py [host] [port]
        (defaults: 127.0.0.1 2575)
"""

import socket
import sys
import time

VT, FS, CR = 0x0B, 0x1C, 0x0D
host = sys.argv[1] if len(sys.argv) > 1 else "127.0.0.1"
port = int(sys.argv[2]) if len(sys.argv) > 2 else 2575
cr = "\r"


def oru(ctrl, mrn, obx_rows):
    segs = [
        rf"MSH|^~\&|CM100|PHILIPS|CM100_GATEWAY|SMARTWARD|20130101000000||ORU^R01|{ctrl}|P|2.4",
        rf"PID|||{mrn}||DOE^JANE" if mrn else r"PID|||||",
        r"OBR|1||||||20130101000000",
    ]
    for i, (label, value, unit) in enumerate(obx_rows, 1):
        segs.append(rf"OBX|{i}|NM|0002^{label}^MDIL||{value}|{unit}|||||F")
    return cr.join(segs) + cr


def send(name, msg, sock):
    sock.sendall(bytes([VT]) + msg.encode() + bytes([FS, CR]))
    ack = sock.recv(4096)
    clean = bytes(b for b in ack if b not in (VT, FS, CR)).decode("utf-8", "replace")
    print("sent %-28s -> ACK: %s" % (name, clean.split(cr)[-1][:40] if cr in clean else clean[:40]))


# One persistent connection, like the real CM100.
s = socket.create_connection((host, port), timeout=5)

# Continuous cycles: SpO2 + Pulse (values drift), first BP arrives mid-stream.
send("cycle 1 (spo2+pulse)", oru("C001", "77777", [("SpO2", "97", "%"), ("Pulse", "78", "bpm")]), s)
send("cycle 2 (spo2+pulse)", oru("C002", "77777", [("SpO2", "96", "%"), ("Pulse", "82", "bpm")]), s)
send("cycle 3 (+NBP 121/79)", oru("C003", "77777", [
    ("SpO2", "98", "%"), ("Pulse", "80", "bpm"),
    ("NBPs", "121", "mmHg"), ("NBPd", "79", "mmHg"), ("NBPm", "93", "mmHg"),
    ("Temp", "36.9", "cel"), ("RR", "17", "rpm"),
]), s)
send("cycle 4 (empty OBX-5)", oru("C004", "77777", [("SpO2", "", "%"), ("Pulse", "", "bpm")]), s)
time.sleep(1)
# A DIFFERENT BP: closes the first profile (should log 'Profile closed ... queued').
send("cycle 5 (NEW BP 135/85)", oru("C005", "77777", [
    ("SpO2", "95", "%"), ("Pulse", "84", "bpm"),
    ("NBPs", "135", "mmHg"), ("NBPd", "85", "mmHg"),
]), s)
# No MRN: must be counted + suppressed-warned, never queued.
send("cycle 6 (no MRN)", oru("C006", "", [("SpO2", "97", "%"), ("Pulse", "70", "bpm")]), s)
s.close()

print()
print("Now stop the gateway (Ctrl+C) or wait PROFILE_GAP_SECONDS - the second")
print("profile (BP 135/85) closes on the gap/flush and is queued too.")
