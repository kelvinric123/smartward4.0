"""
Send ONE DinaWin read command to the DINAMAP V100 and show the raw reply.

Unlike serial_sniffer.py this WRITES to the monitor. The commands are copied
byte-for-byte from GE's DinaWin.dll 1.2.0.3 (CR-framed ASCII at 9600 8N1).
DinaWin only reads values, and with rEn=0 the monitor refuses remote BP control
anyway. By default only NA is sent.

    venv\\Scripts\\python.exe hostcomm_test.py              send NA once, listen 3 s
    venv\\Scripts\\python.exe hostcomm_test.py --cmd OA     another DinaWin command

Monitor: br=5 (9600), nod=0, normal screen (not config mode). COM3 must be free.
"""

import argparse
import os
import sys
import time
from datetime import datetime

from serial_sniffer import RAW_DIR, hexdump, serial

# Verbatim from DinaWin.dll (offset 0xE2F8): CR, unit address 32 (' '),
# two-letter command, '!', checksum, CR.
COMMANDS = {
    "NA": b"\r NA!2\r",
    "OA": b"\r OA!3\r",
    "RA": b"\r RA!6\r",
    "TC": b"\r TC!:\r",
    "TD": b"\r TD!;\r",
}


def main():
    ap = argparse.ArgumentParser(description="Send one DinaWin read command and dump the reply.")
    ap.add_argument("--port", default=os.environ.get("DINAMAP_PORT", "COM3"))
    ap.add_argument("--baud", type=int, default=9600)
    ap.add_argument("--cmd", choices=sorted(COMMANDS), default="NA")
    ap.add_argument("--wait", type=float, default=3.0, help="seconds to listen for the reply")
    args = ap.parse_args()

    try:
        ser = serial.Serial(args.port, args.baud, bytesize=8, parity="N", stopbits=1, timeout=0.05,
                            xonxoff=False, rtscts=False, dsrdtr=False)
    except serial.SerialException as exc:
        sys.exit("Cannot open %s: %s (sniffer or probe still running?)" % (args.port, exc))

    packet = COMMANDS[args.cmd]
    with ser:
        ser.reset_input_buffer()
        sent_at = datetime.now()
        ser.write(packet)
        ser.flush()
        reply = bytearray()
        deadline = time.monotonic() + args.wait
        while time.monotonic() < deadline:
            reply += ser.read(ser.in_waiting or 1)

    print("%s  sent %s on %s @ %d:" % (sent_at.strftime("%H:%M:%S"), args.cmd, args.port, args.baud))
    print(hexdump(packet))
    if reply:
        print("reply, %d bytes:" % len(reply))
        print(hexdump(bytes(reply)))
    else:
        print("NO REPLY in %.1fs." % args.wait)

    os.makedirs(RAW_DIR, exist_ok=True)
    log = os.path.join(RAW_DIR, "hostcomm_%s.log" % sent_at.strftime("%Y%m%d"))
    with open(log, "a", encoding="utf-8") as fh:
        fh.write("=== %s sent %s @ %d: %s\nreply (%d bytes): %s\n" % (
            sent_at.strftime("%Y-%m-%d %H:%M:%S"), args.cmd, args.baud, packet.hex(" "),
            len(reply), bytes(reply).hex(" ")))
    print("logged to %s" % log)


if __name__ == "__main__":
    main()
