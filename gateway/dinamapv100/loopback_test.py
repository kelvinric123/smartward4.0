"""
Cable loopback test: which DB15 pins does our USB cable transmit/receive on?

UNPLUG THE CABLE FROM THE MONITOR FIRST. Bridge two pins on the cable's DB15
end with a paperclip, run this, and it sends a test string and checks it comes
back. Echo on 11+13 = wired to the V100's RS-232 pins (correct); echo on 2+3 =
wired to its inverted-TTL pins (needs a different cable or inversion).

    venv\\Scripts\\python.exe loopback_test.py --pins 11-13
"""

import argparse
import os
import sys
import time

from serial_sniffer import hexdump, serial

PATTERN = b"SMARTWARD-LOOPBACK-0123456789\r\n"


def main():
    ap = argparse.ArgumentParser(description="Loopback test for the USB-serial cable (monitor unplugged).")
    ap.add_argument("--port", default=os.environ.get("DINAMAP_PORT", "COM3"))
    ap.add_argument("--baud", type=int, default=9600)
    ap.add_argument("--pins", default="?", help="which DB15 pins you bridged, for the log line (e.g. 11-13)")
    ap.add_argument("--yes", action="store_true", help="skip the 'is the monitor unplugged' question")
    args = ap.parse_args()

    if not args.yes:
        answer = input("Is the cable UNPLUGGED from the monitor and pins %s bridged? [y/N] " % args.pins)
        if answer.strip().lower() not in ("y", "yes"):
            sys.exit("Stopped. Unplug the cable from the monitor first.")

    try:
        ser = serial.Serial(args.port, args.baud, bytesize=8, parity="N", stopbits=1, timeout=0.05,
                            xonxoff=False, rtscts=False, dsrdtr=False)
    except serial.SerialException as exc:
        sys.exit("Cannot open %s: %s" % (args.port, exc))

    with ser:
        ser.reset_input_buffer()
        ser.write(PATTERN)
        ser.flush()
        echo = bytearray()
        deadline = time.monotonic() + 1.5
        while time.monotonic() < deadline and len(echo) < len(PATTERN):
            echo += ser.read(ser.in_waiting or 1)

    print("pins %s @ %d: sent %d bytes, got %d back" % (args.pins, args.baud, len(PATTERN), len(echo)))
    if echo:
        print(hexdump(bytes(echo)))
    if bytes(echo) == PATTERN:
        print("PASS: the cable transmits and receives on pins %s." % args.pins)
    elif echo:
        print("PARTIAL/GARBLED: those pins are connected, but the data came back changed.")
    else:
        print("FAIL: nothing came back on pins %s." % args.pins)


if __name__ == "__main__":
    main()
