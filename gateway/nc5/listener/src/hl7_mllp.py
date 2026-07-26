"""
HL7/MLLP transport for the Comen NC5 gateway (stdlib only).

Same framing as gateway/vs4_v2 with one required difference: the NC5 does not
only push observations, it also *queries* us for patient demographics and waits
for a real response message (ORF^R04 / ADR^A19) on the same connection. So the
message handler may return a full reply instead of the generic acknowledgement.

The NC5 opens one long-lived TCP connection and keeps it open for hours, so the
receive loop must survive idle periods rather than treating them as EOF.
"""

import os
import socket
import socketserver
from datetime import datetime
from typing import Callable, Optional

__all__ = ["make_server", "build_ack", "parse_msh", "mllp_wrap", "HL7Server", "Reply"]

# --- MLLP framing bytes -----------------------------------------------------
VT = 0x0B  # <SB> Start Block
FS = 0x1C  # <EB> End Block
CR = 0x0D  # <CR>
_END = bytes([FS, CR])


class Reply:
    """What the handler wants sent back for one inbound message.

    `Reply.ack("AA")` -> build the generic MSH/MSA acknowledgement.
    `Reply.raw(msg)`  -> send this complete message instead (query responses).
    """

    __slots__ = ("ack_code", "message")

    def __init__(self, ack_code: str = "AA", message: Optional[str] = None):
        self.ack_code = ack_code
        self.message = message

    @classmethod
    def ack(cls, code: str = "AA") -> "Reply":
        return cls(ack_code=code)

    @classmethod
    def raw(cls, message: str) -> "Reply":
        return cls(message=message)


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
        "version_id": get(11) or "2.6",
    }


def build_ack(hl7: str, ack_code: str = "AA", text: str = "",
              app: str = "NC5_GATEWAY", facility: str = "SMARTWARD",
              message_type: str = "ACK", now_stamp: str = "", ack_id: str = "") -> str:
    """Build a general acknowledgement mirroring the sender's MSH.

    `message_type` is "ACK" (what the NC5 accepts, and what most 2.3.1-era
    devices expect) or "auto" for the 2.4+ style ACK^<original trigger>.
    """
    msh = parse_msh(hl7)
    sep = msh["field_sep"] if msh else "|"
    enc = msh["encoding_chars"] if msh else "^~\\&"
    version = msh["version_id"] if msh else "2.6"
    processing = msh["processing_id"] if msh else "P"
    control_id = msh["control_id"] if msh else ""
    recv_app = msh["sending_app"] if msh else ""
    recv_fac = msh["sending_facility"] if msh else ""
    comp = enc[0] if enc else "^"

    if not now_stamp:
        now = datetime.now()
        now_stamp = now.strftime("%Y%m%d%H%M%S")
        ack_id = ack_id or ("ACK" + now.strftime("%Y%m%d%H%M%S%f"))

    ack_type = "ACK"
    if message_type.lower() == "auto" and msh:
        trigger = msh["message_type"].split(comp)[1] if comp in msh["message_type"] else ""
        ack_type = "ACK" + comp + trigger if trigger else "ACK"
    elif message_type.upper() != "ACK":
        ack_type = message_type

    msh_seg = sep.join([
        "MSH", enc, app, facility, recv_app, recv_fac, now_stamp, "",
        ack_type, ack_id, processing, version,
    ])
    msa_fields = ["MSA", ack_code, control_id] + ([text] if text else [])
    return msh_seg + "\r" + sep.join(msa_fields) + "\r"


def mllp_wrap(payload: str) -> bytes:
    return bytes([VT]) + payload.encode("utf-8") + _END


# --- TCP server -------------------------------------------------------------
class _HL7Handler(socketserver.BaseRequestHandler):
    def _open_capture(self, peer_ip):
        """Byte-exact wire capture, one pair of files per connection.

        The text archive (SAVE_RAW) decodes to UTF-8 with replacement, which is
        fine for HL7 but destroys anything binary — and this monitor does send
        un-framed data we do not understand yet. These files are the ground
        truth for working out what it is.
        """
        self._capture_in = self._capture_out = None
        if not self.server.capture_dir:
            return
        try:
            os.makedirs(self.server.capture_dir, exist_ok=True)
            base = os.path.join(
                self.server.capture_dir,
                "%s_%s" % (datetime.now().strftime("%Y%m%d-%H%M%S"), peer_ip.replace(":", "-")),
            )
            self._capture_in = open(base + "_in.bin", "ab", buffering=0)
            self._capture_out = open(base + "_out.bin", "ab", buffering=0)
        except OSError:
            self._capture_in = self._capture_out = None  # never break the gateway

    def _capture(self, handle, data) -> None:
        if handle is None:
            return
        try:
            handle.write(data)
        except OSError:
            pass

    def handle(self) -> None:
        peer_ip = self.client_address[0]
        server = self.server
        self._open_capture(peer_ip)
        if server.on_connect:
            self._safe(server.on_connect, peer_ip)
        self.request.settimeout(server.idle_flush)
        buffer = bytearray()
        try:
            while True:
                try:
                    chunk = self.request.recv(4096)
                except socket.timeout:
                    # The NC5 holds the connection open between messages; only
                    # flush if a misconfigured sender left un-framed data behind.
                    if buffer and buffer.find(VT) == -1:
                        self._flush_raw(buffer, peer_ip)
                        buffer.clear()
                    continue
                if not chunk:
                    break
                self._capture(self._capture_in, chunk)
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
            for handle in (self._capture_in, self._capture_out):
                if handle is not None:
                    try:
                        handle.close()
                    except OSError:
                        pass
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
        reply = Reply.ack("AA")
        try:
            if server.on_message is not None:
                result = server.on_message(hl7, framed, peer_ip)
                if isinstance(result, Reply):
                    reply = result
                elif isinstance(result, str) and result:
                    reply = Reply.ack(result)
        except Exception:
            # Never let a handler error drop the response; the reading is either
            # already queued or will be re-sent by the monitor.
            reply = Reply.ack("AE")

        if reply.message:
            payload = reply.message
        elif parse_msh(hl7):
            payload = build_ack(hl7, ack_code=reply.ack_code, app=server.app,
                                facility=server.facility,
                                message_type=server.ack_message_type)
        else:
            return  # not HL7 at all: nothing sensible to answer with
        framed_reply = mllp_wrap(payload)
        self._capture(self._capture_out, framed_reply)
        try:
            self.request.sendall(framed_reply)
        except OSError:
            pass


class HL7Server(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
    daemon_threads = True


def make_server(on_message: Optional[Callable] = None, host: str = "0.0.0.0",
                port: int = 2555, app: str = "NC5_GATEWAY", facility: str = "SMARTWARD",
                idle_flush: float = 5.0, ack_message_type: str = "ACK",
                capture_dir: str = "",
                on_connect: Optional[Callable] = None,
                on_disconnect: Optional[Callable] = None) -> HL7Server:
    server = HL7Server((host, port), _HL7Handler)
    server.on_message = on_message
    server.on_connect = on_connect
    server.on_disconnect = on_disconnect
    server.app = app
    server.facility = facility
    server.idle_flush = idle_flush
    server.ack_message_type = ack_message_type
    server.capture_dir = capture_dir
    return server
