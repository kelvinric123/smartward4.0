"""
Replay Comen NC5 traffic against the gateway to prove the whole chain works:
MLLP framing -> patient query answered from SmartWard -> ORU parsing ->
aggregation -> outbox enqueue.

The messages below are modelled on what the NC5 at 192.168.0.155 actually put
on the wire (see logs/), including its IEEE 11073 (MDC) observation codes, and
are sent over one long-lived connection the way the monitor does.

Usage:  python test_sender.py [host] [port] [patient_code]
        (defaults: 127.0.0.1 2555 12345)

Tip: run the listener with RECORD_INTERVAL=20 WINDOW_IDLE_TIMEOUT=10 so the
continuous-vitals window closes while you are still watching.
"""

import socket
import sys
import time
from datetime import datetime

VT, FS, CR = 0x0B, 0x1C, 0x0D
host = sys.argv[1] if len(sys.argv) > 1 else "127.0.0.1"
port = int(sys.argv[2]) if len(sys.argv) > 2 else 2555
code = sys.argv[3] if len(sys.argv) > 3 else "12345"
cr = "\r"

now = datetime.now()
stamp = now.strftime("%Y%m%d%H%M%S")
ts = now.strftime("%Y%m%d%H%M%S.%f")[:18] + "+0800"
seq = [0]


def control_id():
    seq[0] += 1
    return f"{stamp}.{seq[0]:04d}"


def qry_r02(patient_code):
    """The NC5's own patient query: id in QRD-8, answered with ORF^R04."""
    return cr.join([
        rf"MSH|^~\&||KC575235977G|||{ts}||QRY^R02|{control_id()}|P|2.6",
        rf"QRD|{ts}|R|I|Q101||||{patient_code}|RES",
        rf"PID|||{patient_code}||UNKNOWN^PATIENT||19000101|M||||||||||{patient_code}",
        r"PV1||I|^^&24",
        r"QRF|MON||||3232235520&0^5^1^0^2005&2005&2005&2005&2005",
    ]) + cr


def qry_a19(patient_code):
    """The Mindray/eGateway ADT query dialect, answered with ADR^A19."""
    return cr.join([
        rf"MSH|^~\&|Mindray|Gateway|||{stamp}||QRY^A19|{control_id()}|P|2.3.1",
        rf"QRD|{stamp}|D|D|1|||1^RD|{patient_code}|^DEM|^MindrayGateway",
    ]) + cr


def adt_a08(patient_code):
    """Bed/patient confirmation the NC5 sends after a successful query."""
    return cr.join([
        rf"MSH|^~\&||KC575235977G|||{ts}||ADT^A08|{control_id()}|P|2.6",
        rf"EVN|A08|{ts}",
        rf"PID|||{patient_code}||DOE^JOHN||19800115|M||||||||||{patient_code}",
        r"PV1||I|^^&24|0|||99999",
        rf"OBR||||4^PATIENT^99COMEN|||{stamp}+0800",
        rf"OBX||NM|188740^MDC_LEN_BODY_ACTUAL^MDC|1.10.1.188740|0|263441^MDC_DIM_CENTI_M^MDC|||||F|||{stamp}+0800",
        rf"OBX||NM|188736^MDC_MASS_BODY_ACTUAL^MDC|1.10.1.188736|0|263875^MDC_DIM_KILO_G^MDC|||||F|||{stamp}+0800",
    ]) + cr


def oru(patient_code, pulse, spo2, with_nibp=False, temp="36.8"):
    lines = [
        rf"MSH|^~\&||KC575235977G|||{ts}||ORU^R01|{control_id()}|P|2.6",
        rf"PID|||{patient_code}||DOE^JOHN||19800115|M||||||||||{patient_code}",
        r"PV1||I|^^&24",
        rf"OBR||||1^MDC_OBS^MDC|||{stamp}+0800",
    ]
    if with_nibp:
        lines += [
            rf"OBX||NM|150017^MDC_PRESS_BLD_NONINV_SYS^MDC|1.1.1|128|266016^MDC_DIM_MMHG^MDC|||||F|||{stamp}+0800",
            rf"OBX||NM|150018^MDC_PRESS_BLD_NONINV_DIA^MDC|1.1.2|76|266016^MDC_DIM_MMHG^MDC|||||F|||{stamp}+0800",
            # MAP has no SmartWard field and must be ignored, not read as systolic.
            rf"OBX||NM|150019^MDC_PRESS_BLD_NONINV_MEAN^MDC|1.1.3|93|266016^MDC_DIM_MMHG^MDC|||||F|||{stamp}+0800",
        ]
    lines += [
        rf"OBX||NM|147842^MDC_ECG_HEART_RATE^MDC|1.2.1|{pulse + 1}|264864^MDC_DIM_BEAT_PER_MIN^MDC|||||F|||{stamp}+0800",
        rf"OBX||NM|149530^MDC_PULS_OXIM_PULS_RATE^MDC|1.3.1|{pulse}|264864^MDC_DIM_BEAT_PER_MIN^MDC|||||F|||{stamp}+0800",
        rf"OBX||NM|150456^MDC_PULS_OXIM_SAT_O2^MDC|1.3.2|{spo2}|262688^MDC_DIM_PERCENT^MDC|||||F|||{stamp}+0800",
        rf"OBX||NM|150344^MDC_TEMP^MDC|1.4.1|{temp}|268192^MDC_DIM_DEGC^MDC|||||F|||{stamp}+0800",
        rf"OBX||NM|151562^MDC_RESP_RATE^MDC|1.5.1|18|264928^MDC_DIM_RESP_PER_MIN^MDC|||||F|||{stamp}+0800",
        # Probe off: 8388607 is the 11073 "value not available" sentinel.
        rf"OBX||NM|150456^MDC_PULS_OXIM_SAT_O2^MDC|1.6.1|8388607|262688^MDC_DIM_PERCENT^MDC|||||X|||{stamp}+0800",
    ]
    return cr.join(lines) + cr


no_pid = cr.join([
    rf"MSH|^~\&||KC575235977G|||{ts}||ORU^R01|{control_id()}|P|2.6",
    r"PID|||||",
    rf"OBR||||1^MDC_OBS^MDC|||{stamp}+0800",
    rf"OBX||NM|149530^MDC_PULS_OXIM_PULS_RATE^MDC|1.3.1|70|264864^MDC_DIM_BEAT_PER_MIN^MDC|||||F|||{stamp}+0800",
]) + cr

messages = [
    ("QRY^R02 (found?)", qry_r02(code)),
    ("QRY^R02 (unknown)", qry_r02("NO-SUCH-PATIENT")),
    ("QRY^A19", qry_a19(code)),
    ("ADT^A08", adt_a08(code)),
    ("ORU + NIBP", oru(code, 88, 97, with_nibp=True)),
    ("ORU continuous", oru(code, 92, 95)),
    ("ORU continuous", oru(code, 85, 99)),
    ("ORU repeat NIBP", oru(code, 86, 96, with_nibp=True)),
    ("ORU no patient", no_pid),
]

# One connection for everything, exactly as the monitor does it.
sock = socket.create_connection((host, port), timeout=10)
try:
    for name, message in messages:
        sock.sendall(bytes([VT]) + message.encode() + bytes([FS, CR]))
        raw = sock.recv(8192)
        text = bytes(b for b in raw if b not in (VT, FS)).decode("utf-8", "replace")
        print(f"--- sent {name}")
        for line in text.split(cr):
            if line.strip():
                print(f"    {line}")
        time.sleep(0.4)
finally:
    sock.close()

print("\nWatch the gateway log for 'Reading from ... [queued]' lines; the NIBP")
print("message should chart immediately and the continuous ones at the interval.")
