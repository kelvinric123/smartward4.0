"""
VS4 HL7 discovery handler.

Shows every incoming HL7 message -- ANY type (ORU, ADT, ORM, MDM, ACK, ...),
all segments, one line each -- and saves the raw message to raw/. This is the
"show all possible messages" starting point: run it, point the VS4 at this
machine, and watch what actually arrives. Once we know the VS4's message shape
we'll add an interpreter/ + interpreted/ step like we did for cm100.
"""

import os
import re
from datetime import datetime

from hl7_listener import start_listener, parse_msh

HOST = os.environ.get("VS4_HOST", "0.0.0.0")
PORT = int(os.environ.get("VS4_PORT", "4000"))
HEXDUMP = os.environ.get("VS4_HEXDUMP", "1") != "0"

BASE = os.path.dirname(os.path.abspath(__file__))
RAW_DIR = os.path.join(BASE, "raw")

_count = 0


def _safe(s):
    return re.sub(r"[^A-Za-z0-9_.-]", "", s or "")


def handle(hl7, framed=True):
    global _count
    _count += 1
    now = datetime.now()
    msh = parse_msh(hl7)
    mtype = msh["message_type"] if msh else "NON-HL7 / NO-MSH"
    ctrl = msh["control_id"] if msh else ""
    sender = msh["sending_app"] if msh else ""

    print("=" * 72)
    print("MSG #%d  %s  type=%s  ctrl=%s  from=%s  framed=%s"
          % (_count, now.strftime("%H:%M:%S"), mtype, ctrl, sender, framed))
    print("-" * 72)
    for i, seg in enumerate(s for s in hl7.replace("\n", "\r").split("\r") if s):
        name = seg[:3]
        print("  [%02d] %s | %s" % (i, name, seg))

    os.makedirs(RAW_DIR, exist_ok=True)
    stamp = now.strftime("%Y%m%d-%H%M%S-") + "%03d" % (now.microsecond // 1000)
    fname = "%s_%s_%s.hl7" % (stamp, _safe(mtype) or "RAW", _safe(ctrl))
    with open(os.path.join(RAW_DIR, fname), "w", encoding="utf-8", newline="") as fh:
        fh.write(hl7 if hl7.endswith("\r") else hl7 + "\r")
    print("  -> saved raw/%s" % fname)


def main():
    os.makedirs(RAW_DIR, exist_ok=True)
    print("VS4 HL7 discovery listener on %s:%d" % (HOST, PORT))
    print("  mode    : SHOW ALL incoming HL7 messages (any type, all segments)")
    print("  hexdump : %s" % ("on" if HEXDUMP else "off"))
    print("  raw dir : %s" % RAW_DIR)
    print("  (Ctrl+C to stop)")
    start_listener(on_message=handle, host=HOST, port=PORT, hexdump=HEXDUMP)


if __name__ == "__main__":
    main()
