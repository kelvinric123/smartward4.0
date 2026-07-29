#!/usr/bin/env python3
"""
Ground-truth packet capture for the Mindray VS8, via Npcap/dumpcap.

vs8_listen.py can only see ports it guessed. This script sees everything: it
holds an IGMP membership for the multicast group (so the switch forwards it to
this NIC) while dumpcap records every frame to/from the monitor, then prints a
per-flow summary so the real transport and port fall out of the data.

Run this first. Feed the port it reports back into vs8_listen.py --ports.

Usage:
    py -3 sniff.py                 # 60 second capture
    py -3 sniff.py --duration 300  # 5 minutes
"""

import argparse
import os
import re
import socket
import struct
import subprocess
import sys
from datetime import datetime

WIRESHARK_DIRS = [
    r"C:\Program Files\Wireshark",
    r"C:\Program Files (x86)\Wireshark",
]


def find_tool(name):
    for d in WIRESHARK_DIRS:
        path = os.path.join(d, name)
        if os.path.exists(path):
            return path
    return None


def run_text(cmd):
    """Run a command, decoding as UTF-8 regardless of the console code page.

    dumpcap emits UTF-8 while a zh-TW console is cp950, so the default locale
    decoding blows up on non-ASCII adapter names.
    """
    return subprocess.run(cmd, capture_output=True,
                          encoding="utf-8", errors="replace").stdout or ""


def find_interface(dumpcap, iface_ip):
    """Map a local IP to its \\Device\\NPF_{...} capture handle.

    Matching on the adapter GUID rather than its display name keeps this working
    on localised Windows, where the alias is not ASCII.
    """
    out = run_text([dumpcap, "-D"])
    devices = re.findall(r"^\s*\d+\.\s+(\S+)\s+\((.*)\)\s*$", out, re.M)

    info = run_text([
        "powershell", "-NoProfile", "-Command",
        "$i = (Get-NetIPAddress -IPAddress %s -AddressFamily IPv4).InterfaceIndex;"
        "$a = Get-NetAdapter -InterfaceIndex $i;"
        "Write-Output $a.InterfaceGuid; Write-Output $a.Name" % iface_ip,
    ])
    lines = [l.strip() for l in info.splitlines() if l.strip()]
    guid = lines[0] if lines else ""
    alias = lines[1] if len(lines) > 1 else ""

    if guid:
        for dev, desc in devices:
            if guid.lower() in dev.lower():
                return dev, desc.strip() or alias
    for dev, desc in devices:
        if alias and alias == desc.strip():
            return dev, desc.strip()
    return None, alias


def join_group(group, iface_ip):
    """Keep an IGMP membership alive for the duration of the capture."""
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    sock.bind(("", 0))
    mreq = socket.inet_aton(group) + socket.inet_aton(iface_ip)
    sock.setsockopt(socket.IPPROTO_IP, socket.IP_ADD_MEMBERSHIP, mreq)
    return sock


def summarize(tshark, pcap, monitor_ip):
    if not tshark:
        print("\ntshark not found - open the capture in Wireshark to inspect it.")
        return
    print("\n" + "=" * 70)
    print("FLOW SUMMARY")
    print("=" * 70)
    fields = ["-e", "ip.src", "-e", "ip.dst", "-e", "_ws.col.Protocol",
              "-e", "udp.srcport", "-e", "udp.dstport",
              "-e", "tcp.srcport", "-e", "tcp.dstport", "-e", "frame.len"]
    out = run_text([tshark, "-r", pcap, "-T", "fields", "-E", "separator=|"] + fields)

    flows = {}
    for line in out.splitlines():
        f = line.split("|")
        if len(f) < 8 or not f[0]:
            continue
        src, dst, proto = f[0], f[1], f[2]
        if f[4]:
            transport, sport, dport = "udp", f[3], f[4]
        elif f[6]:
            transport, sport, dport = "tcp", f[5], f[6]
        else:
            transport, sport, dport = proto.lower(), "", ""
        key = (src, dst, transport, dport)
        rec = flows.setdefault(key, [0, 0, sport])
        rec[0] += 1
        rec[1] += int(f[7] or 0)

    if not flows:
        print("No packets captured.")
        return

    print("%-16s %-16s %-5s %-7s %8s %10s" %
          ("SOURCE", "DEST", "PROTO", "DPORT", "PACKETS", "BYTES"))
    for (src, dst, transport, dport), (n, nbytes, _) in \
            sorted(flows.items(), key=lambda kv: -kv[1][0]):
        tag = "  <-- VS8" if src == monitor_ip else ""
        print("%-16s %-16s %-5s %-7s %8d %10d%s" %
              (src, dst, transport, dport, n, nbytes, tag))

    print("\nPorts the monitor sends to:")
    ports = sorted({dport for (src, _, t, dport) in flows
                    if src == monitor_ip and t == "udp" and dport})
    if ports:
        print("  udp: %s" % ",".join(ports))
        print("\n  Next:  py -3 vs8_listen.py --ports %s" % ",".join(ports))
    else:
        print("  (none seen - the VS8 sent no UDP during this window)")


def main():
    ap = argparse.ArgumentParser(description="Capture Mindray VS8 traffic with dumpcap.")
    ap.add_argument("--group", default="225.0.0.8")
    ap.add_argument("--iface", default="192.168.1.5")
    ap.add_argument("--monitor", default="192.168.1.10")
    ap.add_argument("--duration", type=int, default=60, help="capture seconds")
    ap.add_argument("--dev", default=None,
                    help=r"capture device override, e.g. \Device\NPF_{GUID}")
    args = ap.parse_args()

    dumpcap = find_tool("dumpcap.exe")
    tshark = find_tool("tshark.exe")
    if not dumpcap:
        print("dumpcap.exe not found. Install Wireshark (with Npcap).", file=sys.stderr)
        return 1

    if args.dev:
        dev, alias = args.dev, "(manual)"
    else:
        dev, alias = find_interface(dumpcap, args.iface)
    if not dev:
        print("Could not map %s to a capture interface (alias=%r)." % (args.iface, alias),
              file=sys.stderr)
        print("Run: \"%s\" -D" % dumpcap, file=sys.stderr)
        return 1

    here = os.path.dirname(os.path.abspath(__file__))
    capdir = os.path.join(here, "capture")
    os.makedirs(capdir, exist_ok=True)
    pcap = os.path.join(capdir, "vs8_%s.pcapng" % datetime.now().strftime("%Y%m%d_%H%M%S"))

    # Everything involving the monitor, plus any multicast, plus ARP so we can
    # still see the monitor if it is talking on a protocol we did not expect.
    bpf = ("host %s or host %s or (ip and dst net 224.0.0.0/4) or arp"
           % (args.monitor, args.group))

    print("=" * 70)
    print("VS8 capture")
    print("=" * 70)
    print("interface : %s (%s)" % (alias, dev))
    print("filter    : %s" % bpf)
    print("duration  : %ds" % args.duration)
    print("output    : %s\n" % pcap)

    sock = join_group(args.group, args.iface)
    print("Joined %s on %s. Capturing ...\n" % (args.group, args.iface))
    try:
        subprocess.run([dumpcap, "-i", dev, "-f", bpf, "-w", pcap,
                        "-a", "duration:%d" % args.duration])
    except KeyboardInterrupt:
        print("\nInterrupted.")
    finally:
        sock.close()

    if os.path.exists(pcap):
        print("\nCaptured %d bytes to %s" % (os.path.getsize(pcap), pcap))
        summarize(tshark, pcap, args.monitor)
    return 0


if __name__ == "__main__":
    sys.exit(main())
