#!/usr/bin/env python3
"""
Mindray VS8 - multicast / unicast listener and protocol discovery tool.

Site configuration (set on the monitor's Network > Multicast screen):

    Monitor IP        192.168.1.10
    Multicast address 225.0.0.8
    Master server     192.168.1.5     <- this host

Nothing is assumed about the on-the-wire format. Mindray's monitor<->CMS link is
proprietary binary, not MLLP, so the job of this script is simply to catch
everything the VS8 emits and describe it precisely: raw hex, ASCII, per-port
statistics, and a JSONL record of every datagram for offline analysis.

Because the UDP port is not known up front, a set of candidate ports is opened
at once. Each socket is bound to INADDR_ANY, which on Windows receives unicast,
broadcast and multicast for that port, and joins 225.0.0.8 on the chosen
interface so the switch actually forwards the group to us.

Usage:
    py -3 vs8_listen.py                        # default candidate ports
    py -3 vs8_listen.py --ports 5000-5010,8888 # once the real port is known
    py -3 vs8_listen.py --scan                 # wide sweep, ~2500 ports
"""

import argparse
import errno
import json
import os
import select
import socket
import string
import sys
import threading
import time
from collections import Counter, defaultdict
from datetime import datetime

# Ports worth trying first. Mindray gear clusters in the 5000/9000/10000 bands;
# the rest are generic discovery/announce ports that often carry a device beacon.
DEFAULT_PORTS = (
    # 6678 is confirmed: the VS8 multicasts to 225.0.0.8:6678 from source port
    # 5500 roughly every 15 seconds. The rest stay in the list to catch any
    # additional channel the monitor opens once a session is established.
    "6678,5500,515,1900,2050,2100,2575,3000,4000-4002,5000-5020,5353,"
    "6000-6002,7000-7002,8000-8002,8080,8888-8890,9000-9020,9100,"
    "10000-10020,12000,15000,20000,24105"
)

# Wide sweep for when the monitor is talking on something unexpected.
SCAN_PORTS = (
    "1024-1030,2000-2100,3000-3100,4000-4100,5000-5300,6000-6100,"
    "7000-7100,8000-8200,8800-8900,9000-9300,10000-10300,11000-11100,"
    "12000-12100,15000-15100,20000-20100,24100-24200,27000-27100"
)

# select() on Windows is capped at FD_SETSIZE (512) sockets per call, so the
# socket list is split across threads in chunks below that.
SELECT_CHUNK = 400

PRINTABLE = set(bytes(string.printable[:-5], "ascii"))


def parse_ports(spec):
    """Expand a "80,5000-5010" style spec into a sorted list of ints."""
    ports = set()
    for part in spec.split(","):
        part = part.strip()
        if not part:
            continue
        if "-" in part:
            lo, hi = part.split("-", 1)
            ports.update(range(int(lo), int(hi) + 1))
        else:
            ports.add(int(part))
    return sorted(p for p in ports if 0 < p < 65536)


def hexdump(data, limit=512):
    """Classic offset / hex / ascii dump, truncated to `limit` bytes."""
    out = []
    view = data[:limit]
    for off in range(0, len(view), 16):
        chunk = view[off:off + 16]
        hex_part = " ".join("%02x" % b for b in chunk)
        asc = "".join(chr(b) if b in PRINTABLE and b >= 0x20 else "." for b in chunk)
        out.append("  %04x  %-47s  %s" % (off, hex_part, asc))
    if len(data) > limit:
        out.append("  ....  (%d more bytes)" % (len(data) - limit))
    return "\n".join(out)


def classify(data):
    """Best-effort guess at what a payload is, to speed up reverse engineering."""
    if not data:
        return "empty"
    if data[:1] == b"\x0b" and data.rstrip()[-2:] == b"\x1c\r":
        return "mllp"
    if b"MSH|" in data[:64]:
        return "hl7"
    stripped = data.lstrip()
    if stripped[:1] == b"<":
        return "xml"
    if stripped[:1] in (b"{", b"["):
        return "json"
    printable = sum(1 for b in data if b in PRINTABLE)
    if printable / len(data) > 0.85:
        return "text"
    return "binary"


def open_socket(port, group, iface_ip, rcvbuf):
    """Bind one UDP port and join the multicast group on the given interface."""
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM, socket.IPPROTO_UDP)
    try:
        sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        try:
            sock.setsockopt(socket.SOL_SOCKET, socket.SO_RCVBUF, rcvbuf)
        except OSError:
            pass
        sock.bind(("", port))
        mreq = socket.inet_aton(group) + socket.inet_aton(iface_ip)
        sock.setsockopt(socket.IPPROTO_IP, socket.IP_ADD_MEMBERSHIP, mreq)
        sock.setblocking(False)
        return sock
    except OSError as exc:
        sock.close()
        if exc.errno not in (errno.EADDRINUSE, errno.EACCES, getattr(errno, "WSAEADDRINUSE", 10048)):
            print("  ! port %d: %s" % (port, exc), file=sys.stderr)
        return None


class Capture:
    """Shared, thread-safe sink for everything the sockets receive."""

    def __init__(self, jsonl_path, raw_dir, monitor_ip, dump_limit, max_dumps):
        self.lock = threading.Lock()
        self.jsonl = open(jsonl_path, "a", encoding="utf-8")
        self.raw_dir = raw_dir
        self.monitor_ip = monitor_ip
        self.dump_limit = dump_limit
        self.max_dumps = max_dumps
        self.total = 0
        self.bytes = 0
        self.per_port = Counter()
        self.per_source = Counter()
        self.per_kind = Counter()
        self.dumped = defaultdict(int)
        self.first_seen = {}

    def record(self, port, addr, data):
        now = time.time()
        kind = classify(data)
        src = "%s:%d" % addr

        with self.lock:
            self.total += 1
            self.bytes += len(data)
            self.per_port[port] += 1
            self.per_source[addr[0]] += 1
            self.per_kind[kind] += 1
            key = (addr[0], port)
            is_new_flow = key not in self.first_seen
            if is_new_flow:
                self.first_seen[key] = now
            show = self.dumped[key] < self.max_dumps
            if show:
                self.dumped[key] += 1
            seq = self.total

            self.jsonl.write(json.dumps({
                "ts": datetime.fromtimestamp(now).isoformat(timespec="milliseconds"),
                "seq": seq,
                "src_ip": addr[0],
                "src_port": addr[1],
                "dst_port": port,
                "len": len(data),
                "kind": kind,
                "hex": data.hex(),
            }) + "\n")
            self.jsonl.flush()

            if self.raw_dir:
                name = "%s_p%d_%06d.bin" % (addr[0].replace(".", "-"), port, seq)
                with open(os.path.join(self.raw_dir, name), "wb") as fh:
                    fh.write(data)

        if is_new_flow:
            tag = "  <-- THE MONITOR" if addr[0] == self.monitor_ip else ""
            print("\n*** NEW FLOW  %s -> udp/%d  (%s)%s" % (src, port, kind, tag))

        if show:
            stamp = datetime.fromtimestamp(now).strftime("%H:%M:%S.%f")[:-3]
            print("\n[%s] #%d  %s -> udp/%d  %d bytes  %s"
                  % (stamp, seq, src, port, len(data), kind))
            print(hexdump(data, self.dump_limit))
            if kind in ("hl7", "mllp", "text", "xml", "json"):
                try:
                    text = data.decode("utf-8", "replace").replace("\r", "\n")
                    print("  --- decoded ---")
                    for line in text.splitlines():
                        if line.strip():
                            print("  | " + line)
                except Exception:
                    pass

    def summary(self):
        print("\n" + "=" * 70)
        print("CAPTURE SUMMARY")
        print("=" * 70)
        print("datagrams : %d  (%d bytes)" % (self.total, self.bytes))
        if not self.total:
            print("\nNothing received. See the troubleshooting notes in README.md.")
            return
        print("\nby source IP:")
        for ip, n in self.per_source.most_common():
            tag = "   <-- the VS8" if ip == self.monitor_ip else ""
            print("  %-16s %6d%s" % (ip, n, tag))
        print("\nby destination port:")
        for port, n in self.per_port.most_common():
            print("  udp/%-6d %6d" % (port, n))
        print("\nby payload type:")
        for kind, n in self.per_kind.most_common():
            print("  %-8s %6d" % (kind, n))
        print("\nActive flows:")
        for (ip, port), _ in sorted(self.first_seen.items()):
            print("  %s -> udp/%d" % (ip, port))

    def close(self):
        self.jsonl.close()


def reader(socks, cap, stop):
    """Pump one chunk of sockets until told to stop."""
    fd_to_port = {s.fileno(): s.getsockname()[1] for s in socks}
    while not stop.is_set():
        try:
            ready, _, _ = select.select(socks, [], [], 0.5)
        except (OSError, ValueError):
            break
        for sock in ready:
            try:
                data, addr = sock.recvfrom(65535)
            except OSError:
                continue
            cap.record(fd_to_port.get(sock.fileno(), 0), addr, data)


def main():
    ap = argparse.ArgumentParser(
        description="Listen for Mindray VS8 multicast/unicast traffic.")
    ap.add_argument("--group", default="225.0.0.8", help="multicast group (monitor setting)")
    ap.add_argument("--iface", default="192.168.1.5", help="local NIC IP = master server address")
    ap.add_argument("--monitor", default="192.168.1.10", help="VS8 IP, highlighted in output")
    ap.add_argument("--ports", default=None, help="ports/ranges, e.g. 5000-5010,8888")
    ap.add_argument("--scan", action="store_true", help="use the wide port sweep")
    ap.add_argument("--all-ports", action="store_true", help="brute force udp/1-65535 (heavy)")
    ap.add_argument("--logdir", default=None, help="log directory (default ./logs)")
    ap.add_argument("--raw", action="store_true", help="also write one .bin per datagram")
    ap.add_argument("--dump-limit", type=int, default=512, help="max bytes hexdumped per packet")
    ap.add_argument("--max-dumps", type=int, default=8,
                    help="hexdumps printed per flow before going quiet (0 = unlimited)")
    ap.add_argument("--duration", type=float, default=0, help="stop after N seconds (0 = forever)")
    args = ap.parse_args()

    if args.all_ports:
        spec = "1-65535"
    elif args.ports:
        spec = args.ports
    elif args.scan:
        spec = SCAN_PORTS
    else:
        spec = DEFAULT_PORTS
    ports = parse_ports(spec)

    here = os.path.dirname(os.path.abspath(__file__))
    logdir = args.logdir or os.path.join(here, "logs")
    os.makedirs(logdir, exist_ok=True)
    stamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    jsonl_path = os.path.join(logdir, "vs8_%s.jsonl" % stamp)
    raw_dir = None
    if args.raw:
        raw_dir = os.path.join(here, "capture", stamp)
        os.makedirs(raw_dir, exist_ok=True)

    print("=" * 70)
    print("Mindray VS8 listener")
    print("=" * 70)
    print("multicast group : %s" % args.group)
    print("local interface : %s  (master server address configured on the VS8)" % args.iface)
    print("monitor         : %s" % args.monitor)
    print("candidate ports : %d" % len(ports))
    print("log             : %s" % jsonl_path)
    if raw_dir:
        print("raw payloads    : %s" % raw_dir)
    print()

    print("Binding sockets and joining %s ..." % args.group)
    socks = []
    for port in ports:
        sock = open_socket(port, args.group, args.iface, 1 << 20)
        if sock:
            socks.append(sock)
    if not socks:
        print("No sockets could be bound - is another listener already running?",
              file=sys.stderr)
        return 1
    print("Listening on %d/%d ports. Press Ctrl+C to stop.\n" % (len(socks), len(ports)))

    cap = Capture(jsonl_path, raw_dir, args.monitor,
                  args.dump_limit, args.max_dumps or 10 ** 9)
    stop = threading.Event()
    threads = []
    for i in range(0, len(socks), SELECT_CHUNK):
        t = threading.Thread(target=reader, args=(socks[i:i + SELECT_CHUNK], cap, stop),
                             daemon=True)
        t.start()
        threads.append(t)

    deadline = time.time() + args.duration if args.duration else None
    try:
        while True:
            if deadline and time.time() >= deadline:
                print("\nDuration reached.")
                break
            time.sleep(0.25)
    except KeyboardInterrupt:
        print("\nStopping ...")
    finally:
        stop.set()
        for t in threads:
            t.join(timeout=2)
        for sock in socks:
            sock.close()
        cap.summary()
        cap.close()
        print("\nFull record: %s" % jsonl_path)
    return 0


if __name__ == "__main__":
    sys.exit(main())
