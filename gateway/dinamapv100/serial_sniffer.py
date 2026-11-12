"""
DINAMAP V100 (GE CARESCAPE V100) serial discovery sniffer -- PASSIVE / READ-ONLY.

Opens the USB-serial adapter wired to the V100's rear DB15 host port, shows
every byte the monitor sends (hex + ASCII) grouped into bursts split on idle
gaps, and saves it all to raw/. It NEVER writes to the port, so it cannot start
a BP cycle or change anything on the monitor.

In its default "host communication command mode" the V100 answers a host's
commands rather than streaming, so silence here can be normal -- see README.md.

    venv\\Scripts\\python.exe serial_sniffer.py --list          # which COM port?
    venv\\Scripts\\python.exe serial_sniffer.py                 # COM3 @ 9600 8N1
    venv\\Scripts\\python.exe serial_sniffer.py --scan          # try every V100 bit rate
"""

import argparse
import os
import sys
import time
from datetime import datetime

try:
    import serial
    from serial.tools import list_ports
except ImportError:
    sys.exit("pyserial is missing: venv\\Scripts\\python.exe -m pip install -r requirements.txt")

# The V100's "br" (bit rate) options in advanced configuration mode; 9600 is the default.
V100_BAUDS = [9600, 19200, 4800, 2400, 1200, 600, 300]

BASE = os.path.dirname(os.path.abspath(__file__))
RAW_DIR = os.path.join(BASE, "raw")


def hexdump(data, width=16):
    lines = []
    for off in range(0, len(data), width):
        chunk = data[off:off + width]
        hexes = " ".join("%02X" % b for b in chunk)
        text = "".join(chr(b) if 32 <= b < 127 else "." for b in chunk)
        lines.append("  %04X  %-*s  %s" % (off, width * 3 - 1, hexes, text))
    return "\n".join(lines)


def printable_ratio(data):
    if not data:
        return 0.0
    return sum(1 for b in data if 32 <= b < 127 or b in (9, 10, 13)) / len(data)


def open_port(args, baud):
    try:
        return serial.Serial(
            port=args.port, baudrate=baud, bytesize=args.bytesize, parity=args.parity,
            stopbits=args.stopbits, timeout=0.05,
            # The DB15 host port only has TxD/RxD/Common, so no handshaking.
            xonxoff=False, rtscts=False, dsrdtr=False,
        )
    except serial.SerialException as exc:
        sys.exit("Cannot open %s: %s\n(Unplugged, wrong port, or held by PuTTY/another sniffer? "
                 "Run with --list.)" % (args.port, exc))


def read_bursts(ser, gap, deadline, on_burst, on_idle=None, idle_every=30.0):
    """Read until deadline (None = forever), calling on_burst(started_at, bytes)
    whenever the line has been quiet for `gap` seconds after some data."""
    buf = bytearray()
    started_at = None
    last_rx = quiet_since = time.monotonic()
    try:
        while deadline is None or time.monotonic() < deadline:
            data = ser.read(ser.in_waiting or 1)
            now = time.monotonic()
            if data:
                if not buf:
                    started_at = datetime.now()
                buf += data
                last_rx = now
            elif buf and now - last_rx >= gap:
                on_burst(started_at, bytes(buf))
                buf.clear()
                quiet_since = now
            elif not buf and on_idle and now - quiet_since >= idle_every:
                on_idle()
                quiet_since = now
    finally:
        if buf:
            on_burst(started_at, bytes(buf))


def list_serial_ports():
    ports = list(list_ports.comports())
    if not ports:
        print("No serial ports found. Is the USB-serial adapter plugged in?")
    for p in ports:
        print("  %-6s %s  [%s]" % (p.device, p.description, p.hwid))


def listen(args):
    fmt = "%d%s%s" % (args.bytesize, args.parity, args.stopbits)
    print("DINAMAP V100 serial sniffer (read-only)")
    print("  port    : %s @ %d %s" % (args.port, args.baud, fmt))
    print("  burst   : split after %.2fs of silence" % args.gap)
    print("  raw dir : %s" % RAW_DIR)
    print("  (Ctrl+C to stop)")

    stamp = datetime.now().strftime("%Y%m%d-%H%M%S")
    base = os.path.join(RAW_DIR, "%s_%s_%d" % (stamp, args.port, args.baud))
    files = {}
    stats = {"bursts": 0, "bytes": 0}
    t0 = time.monotonic()

    def on_burst(at, data):
        # Files are created on the first byte so silent runs leave nothing behind.
        if not files:
            os.makedirs(RAW_DIR, exist_ok=True)
            files["bin"] = open(base + ".bin", "ab")
            files["log"] = open(base + ".log", "a", encoding="utf-8")
        stats["bursts"] += 1
        stats["bytes"] += len(data)
        text = "#%d  %s  %d bytes  printable=%d%%\n%s" % (
            stats["bursts"], at.strftime("%H:%M:%S.%f")[:-3], len(data),
            printable_ratio(data) * 100, hexdump(data))
        print(text)
        files["log"].write(text + "\n")
        files["log"].flush()
        files["bin"].write(data)
        files["bin"].flush()

    def on_idle():
        print("  ... %s  still listening, %d bytes so far (%ds elapsed)"
              % (datetime.now().strftime("%H:%M:%S"), stats["bytes"], time.monotonic() - t0))

    ser = open_port(args, args.baud)
    deadline = time.monotonic() + args.duration if args.duration else None
    try:
        read_bursts(ser, args.gap, deadline, on_burst, on_idle, args.idle_notice)
    except KeyboardInterrupt:
        pass
    finally:
        ser.close()
        for fh in files.values():
            fh.close()

    print("-" * 72)
    print("Done: %d bytes in %d bursts over %ds." % (stats["bytes"], stats["bursts"], time.monotonic() - t0))
    if files:
        print("Saved %s.bin / .log" % base)
    else:
        print("Nothing received. In host communication command mode the V100 waits to be "
              "polled -- see README.md 'Nothing arrives'.")


def scan(args):
    print("Scanning %s at every V100 bit rate, %ds each (read-only)..." % (args.port, args.scan_seconds))
    results = []
    for baud in V100_BAUDS:
        got = bytearray()
        ser = open_port(args, baud)
        try:
            read_bursts(ser, args.gap, time.monotonic() + args.scan_seconds,
                        lambda at, data: got.extend(data))
        except KeyboardInterrupt:
            ser.close()
            break
        ser.close()
        ratio = printable_ratio(got)
        results.append((baud, len(got), ratio))
        print("  %5d bps : %5d bytes  printable=%d%%" % (baud, len(got), ratio * 100))
        if got:
            print(hexdump(bytes(got[:64])))

    print("-" * 72)
    if not any(n for _, n, _ in results):
        print("No bytes at any rate: the monitor is not transmitting on its own (or the wiring is off).")
        return
    best = max(results, key=lambda r: (r[1] > 0, r[2], r[1]))
    print("Most plausible rate: %d bps. At a wrong rate you mostly get 00/FF/80+ garbage; "
          "at the right one bursts repeat with the same structure." % best[0])


def main():
    ap = argparse.ArgumentParser(description="Passive serial sniffer for the DINAMAP V100 host port.")
    ap.add_argument("--port", default=os.environ.get("DINAMAP_PORT", "COM3"))
    ap.add_argument("--baud", type=int, default=int(os.environ.get("DINAMAP_BAUD", "9600")))
    ap.add_argument("--bytesize", type=int, choices=[7, 8], default=8)
    ap.add_argument("--parity", choices=["N", "E", "O"], default="N")
    ap.add_argument("--stopbits", type=int, choices=[1, 2], default=1)
    ap.add_argument("--gap", type=float, default=0.15, help="seconds of silence that end a burst")
    ap.add_argument("--duration", type=float, default=0, help="stop after N seconds (0 = until Ctrl+C)")
    ap.add_argument("--idle-notice", type=float, default=30, help="print a still-listening line every N quiet seconds")
    ap.add_argument("--scan", action="store_true", help="listen briefly at each V100 bit rate")
    ap.add_argument("--scan-seconds", type=float, default=15)
    ap.add_argument("--list", action="store_true", help="list serial ports and exit")
    args = ap.parse_args()

    if args.list:
        list_serial_ports()
    elif args.scan:
        scan(args)
    else:
        listen(args)


if __name__ == "__main__":
    main()
