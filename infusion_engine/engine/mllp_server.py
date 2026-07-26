"""MLLP TCP server: receives HL7 from the pump gateway, stores + parses, ACKs."""

import logging
import socket
import threading

from . import hl7

MLLP_START_BLOCK = b'\x0b'      # VT
MLLP_END_BLOCK = b'\x1c'        # FS
MLLP_CARRIAGE_RETURN = b'\x0d'  # CR
BUFFER_SIZE = 65536

logger = logging.getLogger('infusion.mllp')


class MLLPServer:
    def __init__(self, host, port, database):
        self.host = host
        self.port = port
        self.db = database
        self.running = False
        self.server_socket = None
        self.message_count = 0

    def process_message(self, raw_message, client_socket, client_address):
        self.message_count += 1
        source_ip = client_address[0]

        # Raw HL7 is always stored first, even if parsing fails
        message_id = self.db.store_raw_message(raw_message, source_ip)

        try:
            msg = hl7.parse_message(raw_message)
            self.db.save_parsed(message_id, msg)
            logger.info(
                'MSG #%d id=%d %s from %s pump=%s status=%s rate=%s',
                self.message_count, message_id, msg.message_type, source_ip,
                msg.pump_label or msg.device_id,
                msg.values.get('pump_status') or (msg.alarm or {}).get('alert_text'),
                msg.values.get('flow_rate'),
            )
            ack = hl7.build_ack(msg, 'AA')
        except Exception as e:
            logger.error('MSG #%d id=%d parse failed: %s', self.message_count, message_id, e)
            self.db.mark_parse_error(message_id, e)
            ack = hl7.build_ack(raw_message, 'AE', f'Parse error: {e}')

        try:
            frame = MLLP_START_BLOCK + ack.encode('utf-8') + MLLP_END_BLOCK + MLLP_CARRIAGE_RETURN
            client_socket.sendall(frame)
        except Exception as e:
            logger.error('Failed to send ACK: %s', e)

    def handle_client(self, client_socket, client_address):
        peer = f'{client_address[0]}:{client_address[1]}'
        logger.info('Connection from %s', peer)
        buffer = b''
        try:
            while self.running:
                try:
                    data = client_socket.recv(BUFFER_SIZE)
                except socket.timeout:
                    continue
                except (ConnectionResetError, OSError):
                    break
                if not data:
                    break
                buffer += data

                # Extract every complete MLLP-framed message
                while MLLP_START_BLOCK in buffer and MLLP_END_BLOCK in buffer:
                    start_idx = buffer.index(MLLP_START_BLOCK)
                    end_idx = buffer.index(MLLP_END_BLOCK)
                    if end_idx < start_idx:
                        buffer = buffer[start_idx:]
                        continue
                    raw = buffer[start_idx + 1:end_idx].decode('utf-8', errors='replace')
                    buffer = buffer[end_idx + 2:]
                    if raw.strip():
                        self.process_message(raw, client_socket, client_address)

                # Forgiving mode: bare HL7 without MLLP framing
                if MLLP_START_BLOCK not in buffer and buffer.lstrip().startswith(b'MSH|'):
                    if b'\r' in buffer or b'\n' in buffer:
                        logger.warning('[%s] HL7 without MLLP framing - processing anyway', peer)
                        self.process_message(
                            buffer.decode('utf-8', errors='replace'),
                            client_socket, client_address)
                        buffer = b''

            # Leftover un-framed data on close that looks like HL7
            if buffer.strip() and buffer.lstrip().startswith(b'MSH|'):
                logger.warning('[%s] Processing %d unframed bytes on close', peer, len(buffer))
                self.process_message(
                    buffer.decode('utf-8', errors='replace'), client_socket, client_address)
        finally:
            client_socket.close()
            logger.info('Connection closed: %s', peer)

    def serve_forever(self):
        self.running = True
        self.server_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        if hasattr(socket, 'SO_EXCLUSIVEADDRUSE'):
            # Windows: SO_REUSEADDR lets two processes silently share the port;
            # exclusive mode makes a second bind fail loudly instead.
            self.server_socket.setsockopt(socket.SOL_SOCKET, socket.SO_EXCLUSIVEADDRUSE, 1)
        else:
            self.server_socket.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        self.server_socket.bind((self.host, self.port))
        self.server_socket.listen(5)
        self.server_socket.settimeout(1.0)
        logger.info('MLLP listener on %s:%d', self.host, self.port)

        while self.running:
            try:
                client_socket, client_address = self.server_socket.accept()
            except socket.timeout:
                continue
            except OSError:
                break
            client_socket.settimeout(1.0)
            threading.Thread(
                target=self.handle_client,
                args=(client_socket, client_address),
                daemon=True,
            ).start()

    def stop(self):
        self.running = False
        if self.server_socket:
            self.server_socket.close()
        logger.info('MLLP listener stopped (%d messages received)', self.message_count)
