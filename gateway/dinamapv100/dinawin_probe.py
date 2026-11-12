"""
First contact with the DINAMAP V100 through GE's DinaWin.dll.

    <32-bit python> dinawin_probe.py --dll C:\\path\\DinaWin.dll            one getState()
    <32-bit python> dinawin_probe.py --dll C:\\path\\DinaWin.dll --watch 5  poll; take a BP and watch it appear
    python dinawin_probe.py --parse saved.xml                               parse a saved XML, no DLL needed

Before running: stop serial_sniffer.py (COM3 must be free, DinaWin searches
COM1-COM10 itself) and set the monitor to br=5 (9600), nod=0, rEn=0.
Nothing here can start a BP cycle; DinaWin has no such call.
"""

from __future__ import print_function

import argparse
import io
import os
import sys
import time
from datetime import datetime

from dinawin import DinaWin, dll_machine, find_dll, parse_state, python_bits

BASE = os.path.dirname(os.path.abspath(__file__))
RAW_DIR = os.path.join(BASE, "raw")

ORDER = ["Systolic_blood_pressure", "Diastolic_blood_pressure", "Mean_arterial_pressure",
         "Pulse", "Oxygen_saturation", "Body_temperature"]


def summarize(xml):
    state = parse_state(xml)
    lines = ["  model: %s" % (state["model"] or "?")]
    if not state["results"]:
        lines.append("  (no results)")
    for name in sorted(state["results"], key=lambda n: ORDER.index(n) if n in ORDER else 99):
        r = state["results"][name]
        at = r["measured_at"].strftime("%H:%M:%S") if r["measured_at"] else "?"
        method = " (%s)" % r["method"] if r["method"] else ""
        lines.append("  %-26s %7g %-5s at %s%s" % (name, r["value"], r["units"], at, method))
    return "\n".join(lines)


def save_xml(xml):
    if not os.path.isdir(RAW_DIR):
        os.makedirs(RAW_DIR)
    path = os.path.join(RAW_DIR, "dinawin_%s.log" % datetime.now().strftime("%Y%m%d"))
    with io.open(path, "a", encoding="utf-8") as fh:
        fh.write(u"=== %s\n%s\n" % (datetime.now().strftime("%Y-%m-%d %H:%M:%S"), xml))
    return path


def main():
    ap = argparse.ArgumentParser(description="Read vitals from a DINAMAP V100 via GE DinaWin.dll.")
    ap.add_argument("--dll", help="path to DinaWin.dll (default: $DINAWIN_DLL, ./DinaWin.dll, GE install folder)")
    ap.add_argument("--watch", type=float, default=0, help="poll getState() every N seconds (0 = once)")
    ap.add_argument("--reset", action="store_true", help="call resetMonitor() first (hides older values)")
    ap.add_argument("--parse", metavar="FILE", help="parse a saved getState() XML and exit")
    args = ap.parse_args()

    if args.parse:
        with io.open(args.parse, encoding="ascii", errors="replace") as fh:
            print(summarize(fh.read()))
        return 0

    path = find_dll(args.dll)
    if not path:
        print("DinaWin.dll not found. Pass --dll C:\\path\\DinaWin.dll or put it next to this script.")
        return 2
    print("Python %s, %d-bit" % (sys.version.split()[0], python_bits()))
    try:
        print("DLL    %s  [%s]" % (path, dll_machine(path)))
        dina = DinaWin(path)
    except (ValueError, RuntimeError, OSError, AttributeError) as exc:
        print("Cannot load DinaWin.dll: %s" % exc)
        return 2
    print("getBufferLength() = %d" % dina.buffer_length())

    print("checkReadiness(): searching COM1-COM10 for the monitor ...")
    if not dina.check_readiness():
        print("  NOT READY. Check: monitor on and out of config mode; serial_sniffer.py stopped "
              "(COM3 free); br=5 (9600); cable; adapter on COM1-COM10 (ours is COM3).")
        return 1
    print("  ready")

    if args.reset:
        print("resetMonitor() = %s" % dina.reset_monitor())

    last = None
    try:
        while True:
            xml = dina.get_state()
            if xml != last:
                print("\n%s  getState(): %d chars" % (datetime.now().strftime("%H:%M:%S"), len(xml)))
                print(summarize(xml))
                print(xml)
                print("  -> saved to %s" % save_xml(xml))
                if last is None and not parse_state(xml)["results"]:
                    print("  (DinaWin only returns values measured after it loaded: take a BP on the monitor"
                          "%s)" % ("" if args.watch else " and run again with --watch 5"))
                last = xml
            if not args.watch:
                break
            time.sleep(args.watch)
    except KeyboardInterrupt:
        pass
    return 0


if __name__ == "__main__":
    sys.exit(main())
