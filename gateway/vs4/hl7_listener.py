"""
HL7 MLLP listener for VS4 -- DISCOVERY MODE (stdlib only).

Goal: bring the listener up and SHOW EVERYTHING that arrives, so you can see
exactly what the VS4 sends (any HL7 message type, all segments) -- and, while
you're still receiving nothing, so you can see raw bytes / connections too.

What it does:
  * logs every TCP connection (peer ip:port),
  * hex+ASCII dumps every raw chunk received (even if NOT MLLP-framed),
  * extracts MLLP frames (<VT>...<FS><CR>) and hands each message to the handler,
  * also flushes any NON-MLLP bytes (misconfigured senders) so nothing is hidden,
  * replies with an HL7 ACK for any message that has a parseable MSH.

Reusable API (importable for merging later): start_listener, make_server,
build_ack, parse_msh, mllp_wrap.

Run:  python hl7_listener.py     (delegates to discovery.py's dumper)
"""

import socket
import socketserver
from datetime import datetime
from typing import Callable, Optional

__all__ = ["start_listener", "make_server", "build_ack", "parse_msh", "mllp_wrap"]

# --- MLLP framing bytes -----------------------------------------------------
VT = 0x0B  # <SB> Start Block
FS = 0x1C  # <EB> End Block
CR = 0x0D  # <CR>
_END = bytes([FS, CR])

DEFAULT_APP = "VS4_LISTENER"
DEFAULT_FACILITY = "VS4"


def _log(*args):
    print("[%s]" % datetime.now().strftime("%H:%M:%S"), *args)


# --- HL7 helpers ------------------------------------------------------------
def parse_msh(hl7: str) -> Optional[dict]:
    """Parse the MSH header. Returns None if there is no usable MSH."""
    first = hl7.split("\r", 1)[0].split("\n", 1)[0]
    if not first.startswith("MSH"):
        return None
    field_sep = first[3] if len(first) > 3 else "|"
    fields = first.split(field_sep)

    def get(i):
        return fields[i] if i < len(fields) else ""

    return {
        "field_sep": field_sep,
        "encoding_chars": get(1) or "^~\\&",
        "sending_app": get(2),
        "sending_facility": get(3),
        "receiving_app": get(4),
        "receiving_facility": get(5),
        "datetime": get(6),
        "message_type": get(8),
        "control_id": get(9),
        "processing_id": get(10) or "P",
        "version_id": get(11) or "2.4",
    }


def build_ack(hl7: str, ack_code: str = "AA", text: str = "",
              app: str = DEFAULT_APP, facility: str = DEFAULT_FACILITY) -> str:
    msh = parse_msh(hl7)
    sep = msh["field_sep"] if msh else "|"
    enc = msh["encoding_chars"] if msh else "^~\\&"
    version = msh["version_id"] if msh else "2.4"
    processing = msh["processing_id"] if msh else "P"
    control_id = msh["control_id"] if msh else ""
    recv_app = msh["sending_app"] if msh else ""
    recv_fac = msh["sending_facility"] if msh else ""

    now = datetime.now()
    stamp = now.strftime("%Y%m%d%H%M%S")
    ack_id = "ACK" + now.strftime("%Y%m%d%H%M%S%f")
    comp = enc[0] if enc else "^"

    msh_seg = sep.join([
        "MSH", enc, app, facility, recv_app, recv_fac, stamp, "",
        "ACK" + comp + "R01", ack_id, processing, version,
    ])
    msa_seg = sep.join(["MSA", ack_code, control_id, text])
    return msh_seg + "\r" + msa_seg + "\r"


def mllp_wrap(payload: str) -> bytes:
    return bytes([VT]) + payload.encode("utf-8") + _END


# --- TCP server -------------------------------------------------------------
class _HL7Handler(socketserver.BaseRequestHandler):
    def handle(self) -> None:
        peer = "%s:%s" % self.client_address
        _log("CONNECT  %s" % peer)
        self.request.settimeout(self.server.idle_flush)
        buffer = bytearray()
        try:
            while True:
                try:
                    chunk = self.request.recv(4096)
                except socket.timeout:
                    # Sender is holding the connection with un-framed data.
                    if buffer and buffer.find(VT) == -1:
                        self._flush_raw(buffer, peer)
                        buffer.clear()
                    continue
                if not chunk:
                    break
                buffer.extend(chunk)
                if self.server.hexdump:
                    self._hexdump(chunk, peer)

                # Pull out every complete MLLP frame.
                while True:
                    start = buffer.find(VT)
                    if start == -1:
                        break
                    end = buffer.find(_END, start + 1)
                    if end == -1:
                        break
                    frame = bytes(buffer[start + 1:end]).decode("utf-8", "replace")
                    del buffer[:end + len(_END)]
                    self._dispatch(frame, peer, framed=True)
        except OSError as exc:
            _log("ERROR    %s: %s" % (peer, exc))
        finally:
            if bytes(buffer).strip():
                self._flush_raw(buffer, peer)
            _log("DISCONN  %s" % peer)

    def _hexdump(self, data, peer, cap=512):
        view = data[:cap]
        suffix = " (first %d of %d shown)" % (cap, len(data)) if len(data) > cap else ""
        _log("RAW %d bytes from %s%s:" % (len(data), peer, suffix))
        for off in range(0, len(view), 16):
            row = view[off:off + 16]
            hexpart = " ".join("%02x" % b for b in row)
            asci = "".join(chr(b) if 32 <= b < 127 else "." for b in row)
            print("    %04x  %-47s  %s" % (off, hexpart, asci))

    def _flush_raw(self, buffer, peer):
        text = bytes(buffer).decode("utf-8", "replace")
        text = text.replace("\x0b", "").replace("\x1c", "")
        if text.strip():
            _log("NON-MLLP data from %s (%d bytes) -- dumping as-is" % (peer, len(buffer)))
            self._dispatch(text, peer, framed=False)

    def _dispatch(self, hl7, peer, framed):
        server = self.server
        try:
            if server.on_message is not None:
                server.on_message(hl7, framed)
        except Exception as exc:  # never let a handler error drop the ACK
            _log("handler error: %s" % exc)
        if parse_msh(hl7):
            self.request.sendall(mllp_wrap(build_ack(hl7, app=server.app, facility=server.facility)))
            _log("ACK      -> %s" % peer)


class HL7Server(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
    daemon_threads = True


def make_server(on_message: Optional[Callable] = None, host: str = "0.0.0.0",
                port: int = 2575, app: str = DEFAULT_APP, facility: str = DEFAULT_FACILITY,
                hexdump: bool = True, idle_flush: float = 5.0) -> HL7Server:
    server = HL7Server((host, port), _HL7Handler)
    server.on_message = on_message
    server.app = app
    server.facility = facility
    server.hexdump = hexdump
    server.idle_flush = idle_flush
    return server


def start_listener(on_message: Optional[Callable] = None, host: str = "0.0.0.0",
                   port: int = 2575, app: str = DEFAULT_APP, facility: str = DEFAULT_FACILITY,
                   hexdump: bool = True, idle_flush: float = 5.0) -> None:
    server = make_server(on_message, host, port, app, facility, hexdump, idle_flush)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.shutdown()
        server.server_close()


if __name__ == "__main__":
    # Running this file starts the discovery dumper (writes raw/ + prints all
    # messages). The reusable API above is unaffected and importable on its own.
    from discovery import main
    main()
