#!/usr/bin/env python3
"""
Mindray VS8 - TCP master-server listener.

Observed on the wire at this site:

    192.168.1.10:37164 -> 192.168.1.5:9997   SYN      (monitor calls us)
    192.168.1.5:9997   -> 192.168.1.10:37164 RST/ACK  (nothing was listening)

So the VS8 does not wait to be polled: once "Master Server" is pointed at this
host it repeatedly tries to open a TCP session to us on port 9997. The multicast
beacon on 225.0.0.8:6678 is only the announce. The session is where the vitals
(and, later, the ADT sync) will flow.

This script completes that handshake and records everything the monitor sends.
It is deliberately passive - it never writes to the socket unless you pass
--reply - so the first capture shows the monitor's opening messages untouched.

Usage:
    py -3 vs8_tcp.py                       # accept on 9997, log everything
    py -3 vs8_tcp.py --ports 9997,9998
    py -3 vs8_tcp.py --reply 82010100       # send a hex probe on connect
"""

import argparse
import binascii
import json
import os
import socket
import ssl
import string
import subprocess
import sys
import threading
import time
from datetime import datetime

OPENSSL_CANDIDATES = [
    r"C:\Program Files\Git\usr\bin\openssl.exe",
    r"C:\laragon\bin\git\usr\bin\openssl.exe",
    r"C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\openssl.exe",
    "openssl",
]

PRINTABLE = set(bytes(string.printable[:-5], "ascii"))


def hexdump(data, limit=1024):
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
    if not data:
        return "empty"
    if data[:1] == b"\x0b":
        return "mllp"
    if b"MSH|" in data[:64]:
        return "hl7"
    stripped = data.lstrip()
    if stripped[:1] == b"<":
        return "xml"
    if stripped[:1] in (b"{", b"["):
        return "json"
    printable = sum(1 for b in data if b in PRINTABLE)
    return "text" if printable / len(data) > 0.85 else "binary"


def strings(data, minlen=4):
    """Pull ASCII runs out of a binary blob - patient IDs and model names hide here."""
    found, cur = [], bytearray()
    for b in data:
        if 0x20 <= b < 0x7F:
            cur.append(b)
        else:
            if len(cur) >= minlen:
                found.append(cur.decode("ascii"))
            cur = bytearray()
    if len(cur) >= minlen:
        found.append(cur.decode("ascii"))
    return found


def find_openssl():
    for path in OPENSSL_CANDIDATES:
        if path == "openssl":
            try:
                subprocess.run([path, "version"], capture_output=True, check=True)
                return path
            except (OSError, subprocess.CalledProcessError):
                continue
        elif os.path.exists(path):
            return path
    return None


def ensure_cert(certdir, ip):
    """Self-signed server certificate for the master-server IP.

    The VS8 sends no SNI, so the certificate is issued for the bare IP with a
    matching subjectAltName. Whether the monitor actually validates it is the
    open question this answers: a self-signed cert either completes the
    handshake or comes back as a TLS alert naming the reason.
    """
    os.makedirs(certdir, exist_ok=True)
    cert = os.path.join(certdir, "server.crt")
    key = os.path.join(certdir, "server.key")
    if os.path.exists(cert) and os.path.exists(key):
        return cert, key

    openssl = find_openssl()
    if not openssl:
        raise SystemExit("openssl not found - supply --cert/--key manually")

    print("Generating self-signed certificate for %s ..." % ip)
    cmd = [openssl, "req", "-x509", "-newkey", "rsa:2048", "-nodes",
           "-keyout", key, "-out", cert, "-days", "3650",
           "-subj", "/C=MY/O=SmartWard/CN=%s" % ip,
           "-addext", "subjectAltName=IP:%s" % ip]
    res = subprocess.run(cmd, capture_output=True, encoding="utf-8", errors="replace")
    if res.returncode != 0:
        # Older openssl builds have no -addext; the SAN is a nice-to-have.
        res = subprocess.run(cmd[:-2], capture_output=True,
                             encoding="utf-8", errors="replace")
        if res.returncode != 0:
            raise SystemExit("openssl failed:\n%s" % res.stderr)
    print("  cert: %s\n  key : %s" % (cert, key))
    return cert, key


def make_context(cert, key):
    """A deliberately permissive TLS server context.

    Medical devices often pin old ciphers, so this accepts everything back to
    TLS 1.0 at security level 0. Tighten it once the monitor's real
    requirements are known.
    """
    ctx = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
    ctx.minimum_version = ssl.TLSVersion.TLSv1
    ctx.verify_mode = ssl.CERT_NONE
    ctx.check_hostname = False
    try:
        ctx.set_ciphers("ALL:@SECLEVEL=0")
    except ssl.SSLError:
        ctx.set_ciphers("ALL")
    ctx.load_cert_chain(certfile=cert, keyfile=key)
    return ctx


class Session:
    """One accepted connection from the monitor."""

    counter = 0
    lock = threading.Lock()

    def __init__(self, conn, addr, port, log, capdir, reply, dump_limit):
        self.conn = conn
        self.addr = addr
        self.port = port
        self.log = log
        self.reply = reply
        self.dump_limit = dump_limit
        with Session.lock:
            Session.counter += 1
            self.sid = Session.counter
        stamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        self.raw_path = os.path.join(
            capdir, "tcp%d_%s_%s_%02d.bin" % (port, addr[0].replace(".", "-"), stamp, self.sid))
        self.raw = open(self.raw_path, "ab")
        self.total = 0
        self.chunks = 0

    def emit(self, data):
        now = time.time()
        kind = classify(data)
        self.total += len(data)
        self.chunks += 1

        self.raw.write(data)
        self.raw.flush()

        with Session.lock:
            self.log.write(json.dumps({
                "ts": datetime.fromtimestamp(now).isoformat(timespec="milliseconds"),
                "session": self.sid,
                "peer": "%s:%d" % self.addr,
                "dst_port": self.port,
                "seq": self.chunks,
                "len": len(data),
                "kind": kind,
                "hex": data.hex(),
            }) + "\n")
            self.log.flush()

            stamp = datetime.fromtimestamp(now).strftime("%H:%M:%S.%f")[:-3]
            print("\n[%s] s%d #%d  %s:%d -> tcp/%d  %d bytes  %s"
                  % (stamp, self.sid, self.chunks, self.addr[0], self.addr[1],
                     self.port, len(data), kind))
            print(hexdump(data, self.dump_limit))
            found = strings(data)
            if found:
                print("  strings: %s" % ", ".join(repr(s) for s in found[:20]))
            sys.stdout.flush()

    def run(self):
        print("\n*** CONNECT  %s:%d -> tcp/%d  (session %d)"
              % (self.addr[0], self.addr[1], self.port, self.sid))
        print("    raw stream: %s" % self.raw_path)
        try:
            if self.reply:
                self.conn.sendall(self.reply)
                print("    >>> sent %d byte probe: %s"
                      % (len(self.reply), self.reply.hex()))
            while True:
                data = self.conn.recv(65535)
                if not data:
                    break
                self.emit(data)
        except OSError as exc:
            print("\n    session %d error: %s" % (self.sid, exc))
        finally:
            self.conn.close()
            self.raw.close()
            print("\n*** DISCONNECT session %d  (%d chunks, %d bytes)"
                  % (self.sid, self.chunks, self.total))


def serve(port, log, capdir, reply, dump_limit, stop, ctx=None):
    srv = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    srv.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    try:
        srv.bind(("0.0.0.0", port))
    except OSError as exc:
        print("  ! cannot bind tcp/%d: %s" % (port, exc), file=sys.stderr)
        return
    srv.listen(8)
    srv.settimeout(0.5)
    print("  listening on tcp/%d" % port)
    while not stop.is_set():
        try:
            conn, addr = srv.accept()
        except socket.timeout:
            continue
        except OSError:
            break
        conn.setsockopt(socket.IPPROTO_TCP, socket.TCP_NODELAY, 1)
        if ctx is not None:
            try:
                conn.settimeout(15)
                conn = ctx.wrap_socket(conn, server_side=True)
                conn.settimeout(None)
                print("\n*** TLS ESTABLISHED  %s:%d  %s  %s"
                      % (addr[0], addr[1], conn.version(), conn.cipher()[0]))
                peer = conn.getpeercert(binary_form=True)
                print("    client certificate: %s"
                      % ("%d bytes" % len(peer) if peer else "none offered"))
                sys.stdout.flush()
            except ssl.SSLError as exc:
                print("\n*** TLS HANDSHAKE FAILED  %s:%d\n    %s"
                      % (addr[0], addr[1], exc))
                sys.stdout.flush()
                conn.close()
                continue
            except OSError as exc:
                print("\n*** TLS aborted by peer %s:%d - %s" % (addr[0], addr[1], exc))
                sys.stdout.flush()
                conn.close()
                continue
        threading.Thread(target=Session(conn, addr, port, log, capdir,
                                        reply, dump_limit).run, daemon=True).start()
    srv.close()


def main():
    ap = argparse.ArgumentParser(description="Accept and log Mindray VS8 TCP sessions.")
    ap.add_argument("--ports", default="9997",
                    help="TCP ports to accept on (the VS8 was seen calling 9997)")
    ap.add_argument("--reply", default=None,
                    help="hex bytes to send immediately on connect (default: stay silent)")
    ap.add_argument("--dump-limit", type=int, default=1024)
    ap.add_argument("--logdir", default=None)
    ap.add_argument("--duration", type=float, default=0,
                    help="stop after N seconds (0 = run until Ctrl+C)")
    ap.add_argument("--tls", action="store_true",
                    help="terminate TLS (the VS8 opens 9997 with a TLS ClientHello)")
    ap.add_argument("--cert", default=None, help="server certificate (PEM)")
    ap.add_argument("--key", default=None, help="server private key (PEM)")
    ap.add_argument("--ip", default="192.168.1.5",
                    help="master server IP, used as the certificate CN/SAN")
    args = ap.parse_args()

    ports = []
    for part in args.ports.split(","):
        part = part.strip()
        if "-" in part:
            lo, hi = part.split("-", 1)
            ports.extend(range(int(lo), int(hi) + 1))
        elif part:
            ports.append(int(part))

    reply = binascii.unhexlify(args.reply.replace(" ", "")) if args.reply else None

    here = os.path.dirname(os.path.abspath(__file__))
    logdir = args.logdir or os.path.join(here, "logs")
    capdir = os.path.join(here, "capture")
    os.makedirs(logdir, exist_ok=True)
    os.makedirs(capdir, exist_ok=True)
    stamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    log_path = os.path.join(logdir, "vs8_tcp_%s.jsonl" % stamp)

    print("=" * 70)
    print("Mindray VS8 - TCP master server")
    print("=" * 70)
    print("log : %s" % log_path)
    if reply:
        print("probe: %s" % reply.hex())

    ctx = None
    if args.tls:
        if args.cert and args.key:
            cert, key = args.cert, args.key
        else:
            cert, key = ensure_cert(os.path.join(here, "certs"), args.ip)
        ctx = make_context(cert, key)
        print("TLS : enabled (self-signed, CN=%s)" % args.ip)
    print()

    log = open(log_path, "a", encoding="utf-8")
    stop = threading.Event()
    threads = []
    for port in ports:
        t = threading.Thread(target=serve, args=(port, log, capdir, reply,
                                                 args.dump_limit, stop, ctx), daemon=True)
        t.start()
        threads.append(t)

    print("\nWaiting for the monitor to connect. Ctrl+C to stop.")
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
        log.close()
        print("Log: %s" % log_path)
    return 0


if __name__ == "__main__":
    sys.exit(main())
