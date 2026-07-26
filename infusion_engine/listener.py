#!/usr/bin/env python3
"""
Infusion Engine - HL7 Listener
Receives HL7 messages from the NUC (infusion pump gateway) via MLLP on port 6000.
Step 1: receive, ACK, and log every message so we can inspect what the NUC sends.
"""

import os
import sys
import socket
import logging
import threading
from datetime import datetime
from typing import Tuple

try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass  # python-dotenv not installed, use system env vars

# MLLP (Minimal Lower Layer Protocol) framing
MLLP_START_BLOCK = b'\x0b'      # VT
MLLP_END_BLOCK = b'\x1c'        # FS
MLLP_CARRIAGE_RETURN = b'\x0d'  # CR

# Configuration
HOST = os.getenv('INFUSION_HOST', '0.0.0.0')
PORT = int(os.getenv('INFUSION_PORT', '6000'))
BUFFER_SIZE = 65536

LOG_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'logs')


def setup_logger() -> logging.Logger:
    os.makedirs(LOG_DIR, exist_ok=True)

    logger = logging.getLogger('InfusionEngine')
    logger.setLevel(logging.DEBUG)
    logger.handlers = []

    fmt = logging.Formatter(
        '%(asctime)s | %(levelname)-8s | %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )

    console = logging.StreamHandler(sys.stdout)
    console.setLevel(logging.DEBUG)
    console.setFormatter(fmt)
    logger.addHandler(console)

    all_log = logging.FileHandler(os.path.join(LOG_DIR, 'infusion_engine.log'), encoding='utf-8')
    all_log.setLevel(logging.DEBUG)
    all_log.setFormatter(fmt)
    logger.addHandler(all_log)

    return logger


def setup_message_logger() -> logging.Handler:
    """Separate file that stores each raw HL7 message in full."""
    handler = logging.FileHandler(os.path.join(LOG_DIR, 'infusion_messages.log'), encoding='utf-8')
    handler.setLevel(logging.INFO)
    handler.setFormatter(logging.Formatter('%(asctime)s\n%(message)s\n' + '=' * 80 + '\n'))
    return handler


def hexdump(data: bytes, max_bytes: int = 512) -> str:
    """Format bytes as a classic hex + ASCII dump for diagnostics."""
    lines = []
    shown = data[:max_bytes]
    for offset in range(0, len(shown), 16):
        chunk = shown[offset:offset + 16]
        hex_part = ' '.join(f'{b:02x}' for b in chunk)
        ascii_part = ''.join(chr(b) if 32 <= b < 127 else '.' for b in chunk)
        lines.append(f'  {offset:04x}  {hex_part:<48}  {ascii_part}')
    if len(data) > max_bytes:
        lines.append(f'  ... ({len(data) - max_bytes} more bytes)')
    return '\n'.join(lines)


class HL7Message:
    """Minimal HL7 v2.x parse - enough to identify the message and build an ACK."""

    def __init__(self, raw: str):
        self.raw = raw
        self.segments = {}
        self.obx_segments = []

        for line in raw.replace('\n', '\r').split('\r'):
            line = line.strip()
            if not line:
                continue
            fields = line.split('|')
            name = fields[0]
            if name == 'OBX':
                self.obx_segments.append(fields)
            else:
                self.segments[name] = fields

        msh = self.segments.get('MSH', [])
        self.sending_application = self._field(msh, 2)
        self.sending_facility = self._field(msh, 3)
        self.receiving_application = self._field(msh, 4)
        self.receiving_facility = self._field(msh, 5)
        self.message_datetime = self._field(msh, 6)
        self.message_type = self._field(msh, 8)
        self.message_control_id = self._field(msh, 9)
        self.version = self._field(msh, 11) or '2.5'

    @staticmethod
    def _field(fields: list, index: int, default: str = '') -> str:
        return fields[index] if len(fields) > index else default

    def summary(self) -> str:
        parts = [
            f"Type: {self.message_type or '?'}",
            f"Control ID: {self.message_control_id or '?'}",
            f"From: {self.sending_application or '?'}@{self.sending_facility or '?'}",
        ]
        pid = self.segments.get('PID')
        if pid:
            parts.append(f"Patient: {self._field(pid, 3)} {self._field(pid, 5)}")
        if self.obx_segments:
            parts.append(f"OBX segments: {len(self.obx_segments)}")
        return ' | '.join(parts)


class InfusionListener:
    """MLLP TCP server that receives HL7 messages from the NUC."""

    def __init__(self, host: str = HOST, port: int = PORT):
        self.host = host
        self.port = port
        self.logger = setup_logger()
        self.msg_handler = setup_message_logger()
        self.running = False
        self.server_socket = None
        self.message_count = 0

    def log_raw_message(self, raw: str):
        record = logging.LogRecord(
            name='InfusionMessage', level=logging.INFO, pathname='', lineno=0,
            msg=raw, args=(), exc_info=None
        )
        self.msg_handler.emit(record)

    def create_ack(self, msg: HL7Message, ack_code: str = 'AA') -> bytes:
        timestamp = datetime.now().strftime('%Y%m%d%H%M%S')
        ack = (
            f"MSH|^~\\&|SMARTWARD|SMARTWARD|{msg.sending_application}|{msg.sending_facility}|"
            f"{timestamp}||ACK|{timestamp}|P|{msg.version}\r"
            f"MSA|{ack_code}|{msg.message_control_id}|Message received successfully\r"
        )
        return MLLP_START_BLOCK + ack.encode('utf-8') + MLLP_END_BLOCK + MLLP_CARRIAGE_RETURN

    def process_message(self, raw_message: str, client_socket: socket.socket,
                        client_address: Tuple[str, int]):
        self.message_count += 1
        self.logger.info('=' * 60)
        self.logger.info(f"MESSAGE #{self.message_count} from {client_address[0]}:{client_address[1]}")

        self.log_raw_message(raw_message)

        try:
            msg = HL7Message(raw_message)
            self.logger.info(msg.summary())
            # Show the message segment by segment for easy inspection
            for line in raw_message.replace('\n', '\r').split('\r'):
                if line.strip():
                    self.logger.debug(f"  {line.strip()}")
            ack = self.create_ack(msg, 'AA')
        except Exception as e:
            self.logger.error(f"Failed to parse message: {e}")
            msg = HL7Message('')
            ack = self.create_ack(msg, 'AE')

        try:
            client_socket.sendall(ack)
            self.logger.debug("ACK sent")
        except Exception as e:
            self.logger.error(f"Failed to send ACK: {e}")

    def handle_client(self, client_socket: socket.socket, client_address: Tuple[str, int]):
        peer = f"{client_address[0]}:{client_address[1]}"
        self.logger.info(f"New connection from {peer}")
        buffer = b''
        total_bytes = 0
        try:
            while self.running:
                try:
                    data = client_socket.recv(BUFFER_SIZE)
                    if not data:
                        break
                    total_bytes += len(data)

                    # Diagnostic mode: show every raw chunk that arrives, whatever it is
                    self.logger.info(f"[{peer}] Received {len(data)} bytes:")
                    self.logger.info(hexdump(data))
                    self.log_raw_message(f"[{peer}] RAW {len(data)} bytes:\n{hexdump(data, max_bytes=65536)}")

                    buffer += data

                    # Extract every complete MLLP-framed message in the buffer
                    while MLLP_START_BLOCK in buffer and MLLP_END_BLOCK in buffer:
                        start_idx = buffer.index(MLLP_START_BLOCK)
                        end_idx = buffer.index(MLLP_END_BLOCK)
                        if end_idx < start_idx:
                            buffer = buffer[start_idx:]
                            continue
                        raw_message = buffer[start_idx + 1:end_idx].decode('utf-8', errors='replace')
                        buffer = buffer[end_idx + 2:]
                        self.process_message(raw_message, client_socket, client_address)

                    # Forgiving mode: bare HL7 without MLLP framing (starts with MSH|)
                    if MLLP_START_BLOCK not in buffer and buffer.lstrip().startswith(b'MSH|'):
                        text = buffer.decode('utf-8', errors='replace')
                        # Wait until the message looks complete (ends with a segment terminator)
                        if text.rstrip().endswith(('\r', '\n')) or b'\r' in buffer or b'\n' in buffer:
                            self.logger.warning(f"[{peer}] HL7 without MLLP framing - processing anyway")
                            self.process_message(text, client_socket, client_address)
                            buffer = b''

                except socket.timeout:
                    continue
                except ConnectionResetError:
                    self.logger.warning(f"[{peer}] Connection reset by peer")
                    break
                except Exception as e:
                    self.logger.error(f"Error receiving data: {e}")
                    break

            # Connection closing with unprocessed data left over - show what it was
            if buffer.strip():
                text_preview = buffer.decode('utf-8', errors='replace')
                self.logger.warning(f"[{peer}] Connection closing with {len(buffer)} unprocessed bytes:")
                self.logger.warning(hexdump(buffer))
                if text_preview.lstrip().startswith('MSH|'):
                    self.logger.warning(f"[{peer}] Looks like HL7 - processing on close")
                    self.process_message(text_preview, client_socket, client_address)
        finally:
            client_socket.close()
            self.logger.info(f"Connection closed: {peer} (total {total_bytes} bytes received)")

    def start(self):
        self.running = True
        self.server_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        self.server_socket.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        self.server_socket.bind((self.host, self.port))
        self.server_socket.listen(5)
        self.server_socket.settimeout(1.0)

        self.logger.info('=' * 60)
        self.logger.info('INFUSION ENGINE - HL7 LISTENER')
        self.logger.info(f"Listening on {self.host}:{self.port} (MLLP)")
        self.logger.info(f"Logs: {LOG_DIR}")
        self.logger.info('Press Ctrl+C to stop')
        self.logger.info('=' * 60)

        try:
            while self.running:
                try:
                    client_socket, client_address = self.server_socket.accept()
                    client_socket.settimeout(1.0)
                    thread = threading.Thread(
                        target=self.handle_client,
                        args=(client_socket, client_address),
                        daemon=True
                    )
                    thread.start()
                except socket.timeout:
                    continue
        except KeyboardInterrupt:
            self.logger.info("Shutting down (Ctrl+C)")
        finally:
            self.stop()

    def stop(self):
        self.running = False
        if self.server_socket:
            self.server_socket.close()
        self.logger.info(f"Listener stopped. Total messages received: {self.message_count}")


if __name__ == '__main__':
    InfusionListener().start()
