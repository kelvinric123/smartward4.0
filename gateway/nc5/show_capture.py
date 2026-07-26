"""
Decode a wire capture written by CAPTURE_DIR (listener/data/capture/*.bin).

Each connection produces `<stamp>_<peer>_in.bin` (what the monitor sent) and
`_out.bin` (what we answered). This prints the MLLP frames as readable HL7, one
segment per line, and hex-dumps anything that arrived *outside* a frame — which
is exactly what the "Non-HL7 data ... ignored" warning is about.

Usage:
    python show_capture.py                       # newest _in.bin
    python show_capture.py <file.bin> [...]      # specific files
    python show_capture.py --all                 # every capture file
    python show_capture.py <file.bin> --hex      # also hex-dump framed content
"""

import glob
import os
import sys

VT, FS, CR = 0x0B, 0x1C, 0x0D
END = bytes([FS, CR])
CAPTURE_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)),
                           "listener", "data", "capture")


def hexdump(data, limit=512):
    shown = data[:limit]
    for offset in range(0, len(shown), 16):
        row = shown[offset:offset + 16]
        hexed = " ".join(f"{b:02x}" for b in row).ljust(47)
        text = "".join(chr(b) if 32 <= b < 127 else "." for b in row)
        print(f"    {offset:08x}  {hexed}  |{text}|")
    if len(data) > limit:
        print(f"    ... {len(data) - limit} more bytes")


def show_frame(index, payload, want_hex):
    text = payload.decode("utf-8", "replace")
    segments = [s for s in text.replace("\n", "\r").split("\r") if s.strip()]
    header = segments[0].split("|") if segments else []
    kind = header[8] if len(header) > 8 else "?"
    print(f"\n[frame {index}] {kind}  ({len(payload)} bytes)")
    for segment in segments:
        print(f"    {segment}")
    if want_hex:
        hexdump(payload)


def show_file(path, want_hex):
    with open(path, "rb") as fh:
        data = fh.read()
    print("=" * 78)
    print(f"{path}  ({len(data)} bytes)")
    print("=" * 78)
    if not data:
        print("  (empty - the monitor connected but sent nothing)")
        return

    index = 0
    position = 0
    unframed_total = 0
    while position < len(data):
        start = data.find(VT, position)
        if start == -1:
            break
        if start > position:
            gap = data[position:start]
            unframed_total += len(gap)
            print(f"\n[unframed] {len(gap)} bytes before the next MLLP frame:")
            hexdump(gap)
        end = data.find(END, start + 1)
        if end == -1:
            tail = data[start:]
            print(f"\n[incomplete frame] {len(tail)} bytes, no <FS><CR> terminator:")
            hexdump(tail)
            position = len(data)
            break
        index += 1
        show_frame(index, data[start + 1:end], want_hex)
        position = end + len(END)

    if position < len(data):
        trailing = data[position:]
        unframed_total += len(trailing)
        print(f"\n[unframed] {len(trailing)} trailing bytes:")
        hexdump(trailing)

    print(f"\n  -> {index} MLLP frame(s), {unframed_total} un-framed byte(s)")


def main():
    args = [a for a in sys.argv[1:] if not a.startswith("--")]
    want_hex = "--hex" in sys.argv

    if "--all" in sys.argv:
        paths = sorted(glob.glob(os.path.join(CAPTURE_DIR, "*.bin")))
    elif args:
        paths = args
    else:
        candidates = sorted(glob.glob(os.path.join(CAPTURE_DIR, "*_in.bin")),
                            key=os.path.getmtime)
        paths = candidates[-1:]

    if not paths:
        print(f"No capture files in {CAPTURE_DIR}")
        print("Start the listener with CAPTURE_DIR=./data/capture first.")
        return 1
    for path in paths:
        show_file(path, want_hex)
    return 0


if __name__ == "__main__":
    sys.exit(main())
