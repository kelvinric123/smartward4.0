"""
Send sample HL7 messages to the VS4 listener to prove the listener side works
locally (useful while the real VS4 is not connecting yet). It isolates the
problem: if these arrive but the VS4 doesn't, the issue is network/device config.

Usage:  python send_test.py [host] [port]
        (defaults: 127.0.0.1 2575)
"""

import socket
import sys

VT, FS, CR = 0x0B, 0x1C, 0x0D
host = sys.argv[1] if len(sys.argv) > 1 else "127.0.0.1"
port = int(sys.argv[2]) if len(sys.argv) > 2 else 4000
cr = "\r"

# A couple of different message types so you can see the dumper handle "all".
oru = cr.join([
    r"MSH|^~\&|SureSignsVS4|WARD|VS4_LISTENER|VS4|20260707180000||ORU^R01|MSG00001|P|2.4",
    r"PID|||55555||DOE^JOHN",
    r"PV1|||WARD^BED1",
    r"OBR|1||||||20260707180000",
    r"OBX|1|NM|8867-4^Heart rate^LN||72|bpm|||||F",
    r"OBX|2|NM|59408-5^SpO2^LN||98|%|||||F",
    r"OBX|3|NM|8480-6^Systolic^LN||120|mmHg|||||F",
    r"OBX|4|NM|8462-4^Diastolic^LN||80|mmHg|||||F",
    r"OBX|5|NM|8310-5^Temperature^LN||36.8|Cel|||||F",
]) + cr

adt = cr.join([
    r"MSH|^~\&|SureSignsVS4|WARD|VS4_LISTENER|VS4|20260707180500||ADT^A01|MSG00002|P|2.4",
    r"EVN|A01|20260707180500",
    r"PID|||55555||DOE^JOHN",
    r"PV1|1|I|WARD^BED1",
]) + cr

for name, msg in [("ORU^R01", oru), ("ADT^A01", adt)]:
    s = socket.create_connection((host, port), timeout=5)
    s.sendall(bytes([VT]) + msg.encode() + bytes([FS, CR]))
    ack = s.recv(4096)
    clean = bytes(b for b in ack if b not in (VT, FS, CR)).decode("utf-8", "replace")
    print("sent %s -> ACK: %s" % (name, clean.split(cr)[-1] if cr in clean else clean[:60]))
    s.close()
