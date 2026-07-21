"""
Send sample VS4-style HL7 ORU^R01 messages to the v2 gateway to prove the whole
chain works locally: MLLP framing -> ORU parsing -> outbox enqueue -> ACK.

Usage:  python test_sender.py [host] [port]
        (defaults: 127.0.0.1 4000)
"""

import socket
import sys
from datetime import datetime

VT, FS, CR = 0x0B, 0x1C, 0x0D
host = sys.argv[1] if len(sys.argv) > 1 else "127.0.0.1"
port = int(sys.argv[2]) if len(sys.argv) > 2 else 4000
cr = "\r"
stamp = datetime.now().strftime("%Y%m%d%H%M%S")

oru = cr.join([
    rf"MSH|^~\&|SureSignsVS4|WARD|VS4_GATEWAY|SMARTWARD|{stamp}||ORU^R01|MSG00001|P|2.4",
    r"PID|||55555||DOE^JOHN",
    r"PV1|||WARD^BED1",
    rf"OBR|1||||||{stamp}",
    r"OBX|1|NM|8480-6^NBP Systolic^LN||120|mmHg|||||F",
    r"OBX|2|NM|8462-4^NBP Diastolic^LN||80|mmHg|||||F",
    r"OBX|3|NM|8867-4^Heart Rate^LN||72|bpm|||||F",
    r"OBX|4|NM|59408-5^SpO2^LN||98|%|||||F",
    r"OBX|5|NM|8310-5^Temperature^LN||98.6|degF|||||F",
    r"OBX|6|NM|9279-1^Respiratory Rate^LN||16|/min|||||F",
]) + cr

# Same reading again: must be logged as "duplicate ignored" by the gateway.
dup = oru

# No patient ID: must be skipped with a warning, still ACKed.
no_pid = cr.join([
    rf"MSH|^~\&|SureSignsVS4|WARD|VS4_GATEWAY|SMARTWARD|{stamp}||ORU^R01|MSG00003|P|2.4",
    r"PID|||||",
    rf"OBR|1||||||{stamp}",
    r"OBX|1|NM|8867-4^Heart Rate^LN||70|bpm|||||F",
]) + cr

# Non-ORU: must be ACKed and ignored.
adt = cr.join([
    rf"MSH|^~\&|SureSignsVS4|WARD|VS4_GATEWAY|SMARTWARD|{stamp}||ADT^A01|MSG00004|P|2.4",
    rf"EVN|A01|{stamp}",
    r"PID|||55555||DOE^JOHN",
]) + cr

for name, msg in [("ORU^R01", oru), ("ORU duplicate", dup), ("ORU no-PID", no_pid), ("ADT^A01", adt)]:
    s = socket.create_connection((host, port), timeout=5)
    s.sendall(bytes([VT]) + msg.encode() + bytes([FS, CR]))
    ack = s.recv(4096)
    clean = bytes(b for b in ack if b not in (VT, FS, CR)).decode("utf-8", "replace")
    print("sent %-14s -> ACK: %s" % (name, clean.split(cr)[-1] if cr in clean else clean[:60]))
    s.close()
