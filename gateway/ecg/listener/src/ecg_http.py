"""
TC35-compatible HTTP upload listener for the ECG gateway (stdlib only).

Ported from ecg/http_server.py (the dockerized listener) so the ECG machine
cannot tell the difference: HTTP POST/PUT with Basic auth, and the exact
IDEP-style "OK" success response the Philips TC35 expects. The difference is
what happens after receipt — instead of only writing to a folder, every upload
is handed to a callback that stores it in the outbox for delivery to SmartWard.

Also ports the content helpers proven against real TC35 traffic:
  * file-type detection (PDF signature, XML in UTF-8/UTF-16 LE/BE),
  * base64 PDF extraction from the XML <StudyData> element,
  * <PatientID> parsing (the MRN/RN SmartWard matches on).
"""

import base64
import re
import xml.etree.ElementTree as ET
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from typing import Callable, Optional


# --- content helpers (from the field-proven docker listener) -----------------

def detect_file_type(data: bytes) -> str:
    if len(data) < 10:
        return ".bin"
    if data[:4] == b"%PDF":
        return ".pdf"
    if data[:5] == b"<?xml":
        return ".xml"
    if data[:2] == b"\xff\xfe":
        try:
            text = data[:200].decode("utf-16-le", errors="ignore")
            if "<?xml" in text or "<restingecgdata" in text:
                return ".xml"
        except Exception:
            pass
    if data[:2] == b"\xfe\xff":
        try:
            text = data[:200].decode("utf-16-be", errors="ignore")
            if "<?xml" in text or "<restingecgdata" in text:
                return ".xml"
        except Exception:
            pass
    if b"<html" in data[:1000].lower() or b"<!doctype" in data[:1000].lower():
        return ".html"
    return ".bin"


def decode_xml(data: bytes) -> Optional[str]:
    try:
        if data[:2] == b"\xff\xfe":
            return data.decode("utf-16-le")
        if data[:2] == b"\xfe\xff":
            return data.decode("utf-16-be")
        return data.decode("utf-8")
    except Exception:
        return None


def extract_pdf_from_xml(xml_data: bytes) -> Optional[bytes]:
    """Return the embedded report PDF (base64 in <StudyData>), if present."""
    xml_content = decode_xml(xml_data)
    if xml_content is None:
        return None
    try:
        root = ET.fromstring(xml_content)
        study = root.find(".//StudyData")
        if study is not None and study.text and study.text.strip():
            payload = study.text.strip()
            if payload.startswith("JVBERi"):  # %PDF in base64
                pdf = base64.b64decode(payload)
                if pdf.startswith(b"%PDF"):
                    return pdf
    except Exception:
        pass
    return None


def extract_patient_id(xml_data: bytes) -> str:
    """<PatientID> from the ECG XML — the MRN/RN SmartWard matches on."""
    xml_content = decode_xml(xml_data)
    if not xml_content:
        return ""
    try:
        root = ET.fromstring(xml_content)
        node = root.find(".//PatientID")
        if node is not None and node.text:
            return node.text.strip()
    except Exception:
        # Fall back to a regex when the XML is malformed but the tag is there.
        match = re.search(r"<PatientID>\s*([^<]+?)\s*</PatientID>", xml_content)
        if match:
            return match.group(1).strip()
    return ""


# --- HTTP server -------------------------------------------------------------

class _EcgHandler(BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.1"

    def handle(self):
        try:
            super().handle()
        except (BrokenPipeError, ConnectionResetError):
            pass  # client disconnected — normal for the TC35

    # -- auth --
    def _check_auth(self) -> bool:
        header = self.headers.get("Authorization", "")
        try:
            auth_type, credentials = header.split(" ", 1)
            if auth_type.lower() != "basic":
                return False
            username, password = base64.b64decode(credentials).decode("utf-8").split(":", 1)
            return username == self.server.username and password == self.server.password
        except Exception:
            return False

    def _send_auth_required(self):
        self._raw_response(
            b"HTTP/1.1 401 Unauthorized\r\n"
            b'WWW-Authenticate: Basic realm="ECG Upload Server"\r\n'
            b"Content-Type: text/plain\r\n"
            b"Content-Length: 12\r\n"
            b"Connection: close\r\n\r\nUnauthorized"
        )

    # -- responses the TC35 accepts (IDEP style) --
    def _raw_response(self, raw: bytes):
        try:
            self.wfile.write(raw)
            self.wfile.flush()
        except (BrokenPipeError, ConnectionResetError):
            pass
        self.close_connection = True

    def _send_ok(self):
        self._raw_response(
            b"HTTP/1.1 200 OK\r\n"
            b"Content-Type: text/plain\r\n"
            b"Content-Length: 2\r\n"
            b"Connection: close\r\n\r\nOK"
        )

    def _send_error_response(self):
        self._raw_response(
            b"HTTP/1.1 500 Internal Server Error\r\n"
            b"Content-Type: text/plain\r\n"
            b"Content-Length: 5\r\n"
            b"Connection: close\r\n\r\nERROR"
        )

    # -- upload handling --
    def _receive(self, method: str):
        if not self._check_auth():
            self._send_auth_required()
            return
        try:
            length = int(self.headers.get("Content-Length", 0))
            if length <= 0:
                self._send_error_response()
                return
            if length > self.server.max_upload_bytes:
                self._send_error_response()
                return
            data = self.rfile.read(length)

            filename = None
            disp = self.headers.get("Content-Disposition", "")
            if "filename=" in disp:
                filename = disp.split("filename=")[1].strip('"')
            if not filename:
                tail = self.path.strip("/").split("/")[-1]
                filename = tail or None

            ok = True
            if self.server.on_upload is not None:
                ok = self.server.on_upload(data, filename, self.client_address[0], method)
            if ok:
                self._send_ok()
            else:
                self._send_error_response()
        except Exception:
            self._send_error_response()

    def do_POST(self):
        self._receive("POST")

    def do_PUT(self):
        self._receive("PUT")

    def do_GET(self):
        """Minimal status page (also serves the docker healthcheck)."""
        body = b"ECG gateway listener: OK\n"
        self._raw_response(
            b"HTTP/1.1 200 OK\r\n"
            b"Content-Type: text/plain\r\n"
            b"Content-Length: " + str(len(body)).encode() + b"\r\n"
            b"Connection: close\r\n\r\n" + body
        )

    def log_message(self, format, *args):
        pass  # the gateway does its own logging


class EcgHttpServer(ThreadingHTTPServer):
    allow_reuse_address = True
    daemon_threads = True


def make_server(on_upload: Optional[Callable] = None, host: str = "0.0.0.0",
                port: int = 3050, username: str = "admin", password: str = "admin123",
                max_upload_mb: int = 25) -> EcgHttpServer:
    server = EcgHttpServer((host, port), _EcgHandler)
    server.on_upload = on_upload
    server.username = username
    server.password = password
    server.max_upload_bytes = max_upload_mb * 1024 * 1024
    return server
