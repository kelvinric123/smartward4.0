#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
ECG Debug Listener - Maximally Forgiving (HTTP + FTP)
=====================================================
Accepts ALL traffic on HTTP (port 80) and FTP (port 21).
No authentication required. Logs EVERYTHING.
Use this to figure out what the Philips TC20 is actually sending.

Saves all received payloads to ecg/debug_captures/
"""

import os
import sys
import logging
import threading
from datetime import datetime
from http.server import HTTPServer, BaseHTTPRequestHandler

# =============================================================================
# Config
# =============================================================================
HOST = '0.0.0.0'
HTTP_PORT = int(os.environ.get('ECG_HTTP_PORT', 8080))
FTP_PORT = int(os.environ.get('ECG_FTP_PORT', 21))
SAVE_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "debug_captures")

os.makedirs(SAVE_DIR, exist_ok=True)

# =============================================================================
# Logging - verbose
# =============================================================================
logging.basicConfig(
    level=logging.DEBUG,
    format='%(asctime)s | %(levelname)-8s | %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S',
    stream=sys.stdout
)
logger = logging.getLogger('ECG-Debug')

# Counter for unique filenames
_counter = 0
_counter_lock = threading.Lock()

def next_counter():
    global _counter
    with _counter_lock:
        _counter += 1
        return _counter


# =============================================================================
# HTTP DEBUG HANDLER
# =============================================================================
class DebugHTTPHandler(BaseHTTPRequestHandler):
    """Accept everything. Log everything. Save everything."""

    def log_message(self, format, *args):
        logger.info("[HTTP] " + format % args)

    def handle(self):
        try:
            super().handle()
        except (BrokenPipeError, ConnectionResetError, ConnectionAbortedError):
            logger.warning("[HTTP] Connection dropped by %s" % self.client_address[0])
        except Exception as e:
            logger.error("[HTTP] Unexpected error in handle(): %s" % e)

    # ---- Catch ALL HTTP methods ----
    def do_GET(self):      self._handle_any("GET")
    def do_POST(self):     self._handle_any("POST")
    def do_PUT(self):      self._handle_any("PUT")
    def do_DELETE(self):   self._handle_any("DELETE")
    def do_HEAD(self):     self._handle_any("HEAD")
    def do_OPTIONS(self):  self._handle_any("OPTIONS")
    def do_PATCH(self):    self._handle_any("PATCH")
    def do_PROPFIND(self): self._handle_any("PROPFIND")
    def do_PROPPATCH(self):self._handle_any("PROPPATCH")
    def do_MKCOL(self):    self._handle_any("MKCOL")
    def do_COPY(self):     self._handle_any("COPY")
    def do_MOVE(self):     self._handle_any("MOVE")
    def do_LOCK(self):     self._handle_any("LOCK")
    def do_UNLOCK(self):   self._handle_any("UNLOCK")
    def do_REPORT(self):   self._handle_any("REPORT")

    def _handle_any(self, method):
        """Universal handler for any HTTP method"""
        n = next_counter()
        ts = datetime.now().strftime("%Y%m%d_%H%M%S")
        client_ip = self.client_address[0]
        client_port = self.client_address[1]

        logger.info("=" * 70)
        logger.info("[HTTP] REQUEST #%d" % n)
        logger.info("[HTTP] Time       : %s" % datetime.now().isoformat())
        logger.info("[HTTP] Source     : %s:%s" % (client_ip, client_port))
        logger.info("[HTTP] Method     : %s" % method)
        logger.info("[HTTP] Path       : %s" % self.path)
        logger.info("[HTTP] HTTP Ver   : %s" % self.request_version)
        logger.info("-" * 70)

        # Log ALL headers
        logger.info("[HTTP] HEADERS:")
        for header, value in self.headers.items():
            logger.info("[HTTP]   %s: %s" % (header, value))
        logger.info("-" * 70)

        # Read body (if any)
        body = b""
        content_length = self.headers.get('Content-Length')
        transfer_encoding = self.headers.get('Transfer-Encoding', '')

        if content_length:
            try:
                length = int(content_length)
                body = self.rfile.read(length)
                logger.info("[HTTP] BODY: %d bytes (Content-Length)" % len(body))
            except Exception as e:
                logger.error("[HTTP] Error reading body: %s" % e)
        elif 'chunked' in transfer_encoding.lower():
            try:
                chunks = []
                while True:
                    line = self.rfile.readline().strip()
                    chunk_size = int(line, 16)
                    if chunk_size == 0:
                        self.rfile.readline()
                        break
                    chunk = self.rfile.read(chunk_size)
                    chunks.append(chunk)
                    self.rfile.readline()
                body = b"".join(chunks)
                logger.info("[HTTP] BODY: %d bytes (chunked)" % len(body))
            except Exception as e:
                logger.error("[HTTP] Error reading chunked body: %s" % e)
        else:
            logger.info("[HTTP] BODY: (none / no Content-Length header)")

        # Show body preview and save
        if body:
            file_type = self._detect_type(body)
            logger.info("[HTTP] Detected   : %s" % file_type)

            try:
                preview = body[:500].decode('utf-8', errors='replace')
                for line in preview.split('\n')[:15]:
                    logger.info("[HTTP]   | %s" % line.rstrip())
                if len(body) > 500:
                    logger.info("[HTTP]   | ... (%d more bytes)" % (len(body) - 500))
            except:
                logger.info("[HTTP]   | (binary data, %d bytes)" % len(body))

            ext = {"PDF": ".pdf", "XML": ".xml", "JSON": ".json", "HTML": ".html"}.get(file_type, ".bin")
            save_name = "%s_%04d_HTTP_%s%s" % (ts, n, method, ext)
            save_path = os.path.join(SAVE_DIR, save_name)

            with open(save_path, 'wb') as f:
                f.write(body)
            logger.info("[HTTP] SAVED TO   : %s" % save_path)

            # Save metadata
            meta_path = os.path.join(SAVE_DIR, "%s_%04d_HTTP_%s.meta.txt" % (ts, n, method))
            with open(meta_path, 'w', encoding='utf-8') as f:
                f.write("Request #%d\n" % n)
                f.write("Time: %s\n" % datetime.now().isoformat())
                f.write("Source: %s:%s\n" % (client_ip, client_port))
                f.write("Method: %s\n" % method)
                f.write("Path: %s\n" % self.path)
                f.write("Body Size: %d bytes\n" % len(body))
                f.write("Detected Type: %s\n" % file_type)
                f.write("\n--- Headers ---\n")
                for header, value in self.headers.items():
                    f.write("%s: %s\n" % (header, value))
            logger.info("[HTTP] METADATA   : %s" % meta_path)

        logger.info("=" * 70)

        # Always respond 200 OK
        try:
            self.send_response(200)
            self.send_header('Content-Type', 'text/plain')
            self.send_header('Content-Length', '2')
            self.send_header('Connection', 'close')
            self.send_header('Server', 'Apache/2.4.0')
            self.send_header('DAV', '1,2')
            self.send_header('Allow', 'GET, HEAD, POST, PUT, DELETE, OPTIONS, PROPFIND, PROPPATCH, MKCOL, COPY, MOVE, LOCK, UNLOCK')
            self.end_headers()
            self.wfile.write(b"OK")
            self.wfile.flush()
        except (BrokenPipeError, ConnectionResetError):
            logger.warning("[HTTP] Client disconnected before response sent")

    def _detect_type(self, data):
        if data[:4] == b'%PDF':
            return "PDF"
        if data[:5] == b'<?xml' or data[:2] in (b'\xff\xfe', b'\xfe\xff'):
            return "XML"
        if data[:1] in (b'{', b'['):
            return "JSON"
        if b'<html' in data[:500].lower() or b'<!doctype' in data[:500].lower():
            return "HTML"
        return "unknown"


# =============================================================================
# FTP DEBUG SERVER
# =============================================================================
def start_ftp_server():
    """Start a permissive FTP server that accepts anonymous uploads"""
    try:
        from pyftpdlib.handlers import FTPHandler
        from pyftpdlib.servers import FTPServer
        from pyftpdlib.authorizers import DummyAuthorizer
    except ImportError:
        logger.error("[FTP] pyftpdlib not installed! Run: pip install pyftpdlib")
        return

    # Create FTP upload directory
    ftp_save_dir = os.path.join(SAVE_DIR, "ftp_uploads")
    os.makedirs(ftp_save_dir, exist_ok=True)

    # Set up authorizer - allow anonymous + common usernames
    authorizer = DummyAuthorizer()

    # Anonymous access (full permissions)
    authorizer.add_anonymous(ftp_save_dir, perm='elradfmwMT')

    # Common default credentials that ECG machines might use
    for username, password in [
        ('admin', 'admin'),
        ('admin', 'admin123'),
        ('admin', 'password'),
        ('admin', ''),
        ('ecg', 'ecg'),
        ('ecg', 'password'),
        ('ecg', ''),
        ('ftp', 'ftp'),
        ('ftp', ''),
        ('user', 'user'),
        ('user', 'password'),
        ('user', ''),
        ('philips', 'philips'),
        ('philips', ''),
        ('tc20', 'tc20'),
        ('tc20', ''),
        ('test', 'test'),
    ]:
        try:
            authorizer.add_user(username, password, ftp_save_dir, perm='elradfmwMT')
        except Exception:
            pass  # skip if duplicate

    class DebugFTPHandler(FTPHandler):
        """Custom FTP handler that logs everything"""

        # Override authentication to accept ANYTHING
        def ftp_PASS(self, line):
            """Accept any password"""
            logger.info("=" * 70)
            logger.info("[FTP] LOGIN ATTEMPT")
            logger.info("[FTP]   Source   : %s:%s" % (self.remote_ip, self.remote_port))
            logger.info("[FTP]   Username : %s" % self._current_user)
            logger.info("[FTP]   Password : %s" % line)
            logger.info("=" * 70)

            # Try normal auth first
            try:
                super().ftp_PASS(line)
                return
            except Exception:
                pass

            # If normal auth fails, add the user on-the-fly and retry
            try:
                username = self._current_user
                if not self.authorizer.has_user(username):
                    self.authorizer.add_user(username, line, ftp_save_dir, perm='elradfmwMT')
                    logger.info("[FTP]   -> Added user '%s' on-the-fly" % username)
                else:
                    # Remove and re-add with new password
                    self.authorizer.remove_user(username)
                    self.authorizer.add_user(username, line, ftp_save_dir, perm='elradfmwMT')
                    logger.info("[FTP]   -> Updated password for '%s'" % username)
                super().ftp_PASS(line)
            except Exception as e:
                logger.error("[FTP]   -> Auth still failed: %s" % e)
                # Last resort: just authenticate anyway
                try:
                    self.authenticated = True
                    self.username = self._current_user
                    self.password = line
                    self.respond("230 Login successful.")
                    logger.info("[FTP]   -> Force-authenticated user '%s'" % self._current_user)
                except Exception as e2:
                    logger.error("[FTP]   -> Force auth failed: %s" % e2)

        def on_connect(self):
            logger.info("=" * 70)
            logger.info("[FTP] NEW CONNECTION from %s:%s" % (self.remote_ip, self.remote_port))
            logger.info("=" * 70)

        def on_disconnect(self):
            logger.info("[FTP] DISCONNECTED: %s:%s" % (self.remote_ip, self.remote_port))

        def on_file_received(self, file):
            """Called when a file upload is complete"""
            n = next_counter()
            file_size = os.path.getsize(file)

            logger.info("=" * 70)
            logger.info("[FTP] FILE RECEIVED! (#%d)" % n)
            logger.info("[FTP]   Source    : %s:%s" % (self.remote_ip, self.remote_port))
            logger.info("[FTP]   Username  : %s" % (self.username or 'anonymous'))
            logger.info("[FTP]   Filename  : %s" % os.path.basename(file))
            logger.info("[FTP]   Size      : %s bytes" % "{:,}".format(file_size))
            logger.info("[FTP]   Saved at  : %s" % file)

            # Try to show preview
            try:
                with open(file, 'rb') as f:
                    data = f.read(500)
                if data[:4] == b'%PDF':
                    logger.info("[FTP]   Type      : PDF")
                elif data[:5] == b'<?xml' or data[:2] in (b'\xff\xfe', b'\xfe\xff'):
                    logger.info("[FTP]   Type      : XML")
                    preview = data[:500].decode('utf-8', errors='replace')
                    for line in preview.split('\n')[:10]:
                        logger.info("[FTP]     | %s" % line.rstrip())
                else:
                    logger.info("[FTP]   Type      : unknown")
                    logger.info("[FTP]   First 50 hex: %s" % data[:50].hex())
            except Exception as e:
                logger.error("[FTP]   Preview error: %s" % e)

            logger.info("=" * 70)

        def on_file_sent(self, file):
            logger.info("[FTP] File sent: %s" % file)

        def on_incomplete_file_received(self, file):
            logger.warning("[FTP] INCOMPLETE file received: %s" % file)
            logger.warning("[FTP]   (transfer was interrupted)")

        def on_login(self, username):
            logger.info("[FTP] USER LOGGED IN: %s from %s" % (username, self.remote_ip))

        def on_login_failed(self, username, password):
            logger.warning("[FTP] LOGIN FAILED: user=%s pass=%s from %s" % (username, password, self.remote_ip))

    handler = DebugFTPHandler
    handler.authorizer = authorizer
    handler.passive_ports = range(60000, 60100)
    handler.banner = "220 ECG FTP Server Ready"
    handler.permit_foreign_addresses = True
    handler.permit_privileged_ports = True

    # Log all FTP commands
    handler.log_prefix = '[FTP %(remote_ip)s:%(remote_port)s]'

    try:
        ftp_server = FTPServer((HOST, FTP_PORT), handler)
        logger.info("[FTP] [OK] Listening on %s:%d" % (HOST, FTP_PORT))
        logger.info("[FTP] Upload dir: %s" % ftp_save_dir)
        logger.info("[FTP] Auth: anonymous + common defaults + accepts anything")
        ftp_server.serve_forever()
    except PermissionError:
        logger.error("[FTP] [FAIL] Permission denied on port %d" % FTP_PORT)
        logger.error("[FTP]   -> Run as Administrator to use port 21")
    except OSError as e:
        if '10048' in str(e) or 'address already in use' in str(e).lower():
            logger.error("[FTP] [FAIL] Port %d is already in use!" % FTP_PORT)
            logger.error("[FTP]   -> Check: netstat -ano | findstr :%d" % FTP_PORT)
        else:
            logger.error("[FTP] [FAIL] Socket error: %s" % e)


# =============================================================================
# MAIN - Run both servers
# =============================================================================
def run_all():
    logger.info("=" * 70)
    logger.info("  ECG DEBUG LISTENER - HTTP + FTP")
    logger.info("  Maximally Forgiving - Accepts Everything")
    logger.info("=" * 70)
    logger.info("  HTTP Port  : %d" % HTTP_PORT)
    logger.info("  FTP Port   : %d" % FTP_PORT)
    logger.info("  Auth       : DISABLED (accepts everything)")
    logger.info("  Save Dir   : %s" % SAVE_DIR)
    logger.info("=" * 70)
    logger.info("  Waiting for Philips TC20 traffic...")
    logger.info("  Both HTTP and FTP connections will be logged.")
    logger.info("=" * 70)
    logger.info("")

    # Start FTP server in a background thread
    ftp_thread = threading.Thread(target=start_ftp_server, daemon=True)
    ftp_thread.start()

    # Start HTTP server in main thread
    try:
        http_server = HTTPServer((HOST, HTTP_PORT), DebugHTTPHandler)
        http_server.timeout = None
        logger.info("[HTTP] [OK] Listening on %s:%d" % (HOST, HTTP_PORT))
        logger.info("")
        logger.info("  >>> Both servers running. Send ECG from TC20 now. <<<")
        logger.info("")
        http_server.serve_forever()
    except PermissionError:
        logger.error("[HTTP] [FAIL] Permission denied on port %d" % HTTP_PORT)
        logger.error("[HTTP]   -> Run as Administrator to use port 80")
        sys.exit(1)
    except OSError as e:
        if '10048' in str(e) or 'address already in use' in str(e).lower():
            logger.error("[HTTP] [FAIL] Port %d is already in use!" % HTTP_PORT)
            logger.error("[HTTP]   -> Stop other process or run: netstat -ano | findstr :%d" % HTTP_PORT)
        else:
            logger.error("[HTTP] [FAIL] Socket error: %s" % e)
        sys.exit(1)
    except KeyboardInterrupt:
        logger.info("\n  Server stopped by user (Ctrl+C)")
        http_server.server_close()


if __name__ == '__main__':
    run_all()
