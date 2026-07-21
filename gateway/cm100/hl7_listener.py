"""
HL7 MLLP listener for the Philips CM100 (Efficia CM).

A small, dependency-free (stdlib only) module you can drop into an existing
framework. The CM100 connects out to this listener as an HL7 client and streams
ORU^R01 messages framed with MLLP (Minimal Lower Layer Protocol). This module
deframes MLLP, hands each raw HL7 message to your callback, and replies with a
proper HL7 ACK so the monitor does not retransmit.

Typical use (merge into your framework):

    from hl7_listener import start_listener

    def my_handler(hl7: str) -> None:
        ...  # your framework's logic here (parse, store, forward, ...)





























3333333333333333333333333333333333333333333333333333333333333333333333333333



    start_listener(port=2575, on_message=my_handler)   # blocks

If you need to run it inside your own loop/thread instead of blocking, use
`make_server(...)` which returns a ThreadingTCPServer you control:

    server = make_server(port=2575, on_message=my_handler)
    threading.Thread(target=server.serve_forever, daemon=True).start()
    ...
    server.shutdown()
"""

import socketserver
from datetime import datetime
from typing import Callable, Optional

__all__ = ["start_listener", "make_server", "build_ack", "parse_msh", "mllp_wrap"]

# --- MLLP framing bytes -----------------------------------------------------
VT = 0x0B  # <SB> Start Block   (vertical tab)
FS = 0x1C  # <EB> End Block     (file separator)
CR = 0x0D  # <CR> Carriage return
_END = bytes([FS, CR])

# This listener's identity, used as the Sending App/Facility in the ACK header.
DEFAULT_APP = "CM100_LISTENER"
DEFAULT_FACILITY = "CM100"


# --- HL7 helpers ------------------------------------------------------------
def parse_msh(hl7: str) -> Optional[dict]:
    """Parse the MSH header into the pieces needed to build an ACK.

    Returns None if the message has no usable MSH segment. Field separator and
    encoding characters are read from the message itself rather than assumed.
    """
    first = hl7.split("\r", 1)[0].split("\n", 1)[0]
    if not first.startswith("MSH"):
        return None

    field_sep = first[3] if len(first) > 3 else "|"
    fields = first.split(field_sep)

    def get(i: int) -> str:
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


def build_ack(
    hl7: str,
    ack_code: str = "AA",
    text: str = "",
    app: str = DEFAULT_APP,
    facility: str = DEFAULT_FACILITY,
) -> str:
    """Build an HL7 ACK for a received message.

    ack_code: 'AA' (accept), 'AE' (error), 'AR' (reject). Sender/receiver from
    the original MSH are swapped so the ACK is addressed back to the CM100.
    """
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
    """Wrap an HL7 string in MLLP framing bytes."""
    return bytes([VT]) + payload.encode("utf-8") + _END


# --- TCP server -------------------------------------------------------------
class _HL7Handler(socketserver.BaseRequestHandler):
    """One instance per CM100 connection; deframes MLLP and dispatches."""

    def handle(self) -> None:
        buffer = bytearray()
        while True:
            chunk = self.request.recv(4096)
            if not chunk:
                break  # peer closed
            buffer.extend(chunk)

            # A single TCP read may hold zero, one, or several full frames.
            while True:
                start = buffer.find(VT)
                if start == -1:
                    buffer.clear()  # no start byte yet; drop noise
                    break
                end = buffer.find(_END, start + 1)
                if end == -1:
                    if start > 0:
                        del buffer[:start]  # trim leading noise, keep partial
                    break
                message = bytes(buffer[start + 1:end]).decode("utf-8", "replace")
                del buffer[:end + len(_END)]
                self._process(message)

    def _process(self, message: str) -> None:
        server = self.server
        ack_code = "AA"
        text = ""
        try:
            if server.on_message is not None:
                server.on_message(message)
        except Exception as exc:  # your handler failed -> tell the CM100
            ack_code = "AE"
            text = str(exc).replace("\r", " ").replace("\n", " ")
        ack = build_ack(message, ack_code, text, server.app, server.facility)
        self.request.sendall(mllp_wrap(ack))


class HL7Server(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
    daemon_threads = True


def make_server(
    on_message: Optional[Callable[[str], None]] = None,
    host: str = "0.0.0.0",
    port: int = 2575,
    app: str = DEFAULT_APP,
    facility: str = DEFAULT_FACILITY,
) -> HL7Server:
    """Create (but do not start) the threaded MLLP server."""
    server = HL7Server((host, port), _HL7Handler)
    server.on_message = on_message
    server.app = app
    server.facility = facility
    return server


def start_listener(
    on_message: Optional[Callable[[str], None]] = None,
    host: str = "0.0.0.0",
    port: int = 2575,
    app: str = DEFAULT_APP,
    facility: str = DEFAULT_FACILITY,
) -> None:
    """Create and run the listener (blocks until interrupted)."""
    server = make_server(on_message, host, port, app, facility)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.shutdown()
        server.server_close()


if __name__ == "__main__":
    # Running this file directly starts the FULL test pipeline (writes raw/ and
    # interpreted/ folders) via interpreter.py. The reusable transport API above
    # (start_listener / build_ack / parse_msh / mllp_wrap) is unaffected and can
    # still be imported on its own for merging into your framework.
    from interpreter import main
    main()
