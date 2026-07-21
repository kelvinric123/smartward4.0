"""
HL7/MLLP transport for the VS4 v2 gateway (stdlib only).

Hardened version of the discovery listener in gateway/vs4: same framing and ACK
behaviour, but quiet by default and with the peer address passed to the message
handler so the gateway can attribute readings to a sender.

The VS4 is a spot-check monitor: it opens a TCP connection, sends one or more
MLLP-framed HL7 messages (<VT>...<FS><CR>), expects an ACK per message, and
disconnects. Un-framed data from misconfigured senders is still surfaced to the
handler instead of being silently dropped.
"""

import socket
import socketserver
from typing import Callable, Optional

__all__ = ["make_server", "build_ack", "parse_msh", "mllp_wrap", "HL7Server"]

# --- MLLP framing bytes -----------------------------------------------------
VT = 0x0B  # <SB> Start Block
FS = 0x1C  # <EB> End Block
CR = 0x0D  # <CR>
_END = bytes([FS, CR])


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
              app: str = "VS4_GATEWAY", facility: str = "SMARTWARD",
              now_stamp: str = "", ack_id: str = "") -> str:
    msh = parse_msh(hl7)
    sep = msh["field_sep"] if msh else "|"
    enc = msh["encoding_chars"] if msh else "^~\\&"
    version = msh["version_id"] if msh else "2.4"
    processing = msh["processing_id"] if msh else "P"
    control_id = msh["control_id"] if msh else ""
    recv_app = msh["sending_app"] if msh else ""
    recv_fac = msh["sending_facility"] if msh else ""

    if not now_stamp:
        from datetime import datetime
        now = datetime.now()
        now_stamp = now.strftime("%Y%m%d%H%M%S")
        ack_id = ack_id or ("ACK" + now.strftime("%Y%m%d%H%M%S%f"))
    comp = enc[0] if enc else "^"

    msh_seg = sep.join([
        "MSH", enc, app, facility, recv_app, recv_fac, now_stamp, "",
        "ACK" + comp + "R01", ack_id, processing, version,
    ])
    msa_seg = sep.join(["MSA", ack_code, control_id, text])
    return msh_seg + "\r" + msa_seg + "\r"


def mllp_wrap(payload: str) -> bytes:
    return bytes([VT]) + payload.encode("utf-8") + _END


# --- TCP server -------------------------------------------------------------
class _HL7Handler(socketserver.BaseRequestHandler):
    def handle(self) -> None:
        peer_ip = self.client_address[0]
        server = self.server
        if server.on_connect:
            self._safe(server.on_connect, peer_ip)
        self.request.settimeout(server.idle_flush)
        buffer = bytearray()
        try:
            while True:
                try:
                    chunk = self.request.recv(4096)
                except socket.timeout:
                    # Sender is holding the connection with un-framed data.
                    if buffer and buffer.find(VT) == -1:
                        self._flush_raw(buffer, peer_ip)
                        buffer.clear()
                    continue
                if not chunk:
                    break
                buffer.extend(chunk)

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
                    self._dispatch(frame, peer_ip, framed=True)
        except OSError:
            pass
        finally:
            if bytes(buffer).strip():
                self._flush_raw(buffer, peer_ip)
            if server.on_disconnect:
                self._safe(server.on_disconnect, peer_ip)

    @staticmethod
    def _safe(func, *args):
        try:
            func(*args)
        except Exception:
            pass

    def _flush_raw(self, buffer, peer_ip):
        text = bytes(buffer).decode("utf-8", "replace")
        text = text.replace("\x0b", "").replace("\x1c", "")
        if text.strip():
            self._dispatch(text, peer_ip, framed=False)

    def _dispatch(self, hl7, peer_ip, framed):
        server = self.server
        ack_code = "AA"
        try:
            if server.on_message is not None:
                # Handler may veto/override the ACK code by returning one.
                result = server.on_message(hl7, framed, peer_ip)
                if isinstance(result, str) and result:
                    ack_code = result
        except Exception:
            # Never let a handler error drop the ACK; the reading is either
            # already queued or will be re-sent by the device.
            ack_code = "AE"
        if parse_msh(hl7):
            try:
                self.request.sendall(
                    mllp_wrap(build_ack(hl7, ack_code=ack_code,
                                        app=server.app, facility=server.facility))
                )
            except OSError:
                pass


class HL7Server(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
    daemon_threads = True


def make_server(on_message: Optional[Callable] = None, host: str = "0.0.0.0",
                port: int = 4000, app: str = "VS4_GATEWAY", facility: str = "SMARTWARD",
                idle_flush: float = 5.0,
                on_connect: Optional[Callable] = None,
                on_disconnect: Optional[Callable] = None) -> HL7Server:
    server = HL7Server((host, port), _HL7Handler)
    server.on_message = on_message
    server.on_connect = on_connect
    server.on_disconnect = on_disconnect
    server.app = app
    server.facility = facility
    server.idle_flush = idle_flush
    return server
