"""
Pretend to be a Mindray VS8 so the lookup server can be tested without the
monitor: sends the queries the VS8 sends and prints what comes back.

Usage:  python test_sender.py [host] [port] [patient_id]
        (defaults: 127.0.0.1 2575 12345)

A successful lookup prints a PID segment carrying the name the monitor will
display.
"""

import socket
import sys
from datetime import datetime

VT, FS, CR = 0x0B, 0x1C, 0x0D
host = sys.argv[1] if len(sys.argv) > 1 else "127.0.0.1"
port = int(sys.argv[2]) if len(sys.argv) > 2 else 2575
patient = sys.argv[3] if len(sys.argv) > 3 else "12345"
cr = "\r"
stamp = datetime.now().strftime("%Y%m%d%H%M%S")
seq = [0]


def control_id():
    seq[0] += 1
    return str(seq[0])


def qry_a19(patient_id):
    """The ADT query a Mindray monitor/eGateway sends: id in QRD-8."""
    return cr.join([
        rf"MSH|^~\&|Mindray|Gateway|||{stamp}||QRY^A19|{control_id()}|P|2.3.1",
        rf"QRD|{stamp}000|D|D|1|||1^RD|{patient_id}|^DEM|^MindrayGateway",
    ]) + cr


def oru_r01(patient_id):
    """A spot-check result, so the log shows what a vitals message looks like."""
    return cr.join([
        rf"MSH|^~\&|Mindray|VS8|VS8_GATEWAY|SMARTWARD|{stamp}||ORU^R01|{control_id()}|P|2.3.1",
        rf"PID|||{patient_id}||DOE^JOHN||19800115|M",
        r"PV1||I|^^ICU&01&0&0&0",
        rf"OBR|1||||||{stamp}",
        rf"OBX|1|NM|150017^MDC_PRESS_BLD_NONINV_SYS^MDC||118|mmHg|||||F|||{stamp}",
        rf"OBX|2|NM|150018^MDC_PRESS_BLD_NONINV_DIA^MDC||74|mmHg|||||F|||{stamp}",
        rf"OBX|3|NM|149530^MDC_PULS_OXIM_PULS_RATE^MDC||72|bpm|||||F|||{stamp}",
        rf"OBX|4|NM|150456^MDC_PULS_OXIM_SAT_O2^MDC||97|%|||||F|||{stamp}",
        rf"OBX|5|NM|150344^MDC_TEMP^MDC||36.7|Cel|||||F|||{stamp}",
    ]) + cr


messages = [
    ("QRY^A19 (known id)", qry_a19(patient)),
    ("QRY^A19 (unknown id)", qry_a19("NO-SUCH-PATIENT")),
    ("ORU^R01 (vitals)", oru_r01(patient)),
]

for name, message in messages:
    sock = socket.create_connection((host, port), timeout=10)
    try:
        sock.sendall(bytes([VT]) + message.encode() + bytes([FS, CR]))
        raw = sock.recv(8192)
    finally:
        sock.close()
    text = bytes(b for b in raw if b not in (VT, FS)).decode("utf-8", "replace")
    print(f"--- sent {name}")
    for line in text.split(cr):
        if line.strip():
            print(f"    {line}")
