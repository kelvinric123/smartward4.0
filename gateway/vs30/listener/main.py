import socket
import threading
import signal
import logging
import time
import os
import sys
from datetime import datetime
try:
    from .config import HOST, PORT, BUFFER_SIZE, LOG_DIR
    from .mllp_handler import MLLPHandler
    from .hl7_parser import HL7Parser
    from .api_client import APIClient
except ImportError:
    from config import HOST, PORT, BUFFER_SIZE, LOG_DIR
    from mllp_handler import MLLPHandler
    from hl7_parser import HL7Parser
    from api_client import APIClient

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[
        logging.FileHandler(os.path.join(LOG_DIR, 'listener.log')),
        logging.StreamHandler()
    ]
)

class HL7ListenerServer:
    def __init__(self, host, port):
        self.host = host
        self.port = port
        self.server_socket = None
        self.running = False
        self.parser = HL7Parser()
        self.api_client = APIClient()
        self.client_threads = []

    def start(self):
        self.server_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        self.server_socket.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        self.server_socket.settimeout(1.0)  # 1s timeout so we can check self.running
        
        try:
            self.server_socket.bind((self.host, self.port))
            self.server_socket.listen(5)
            self.running = True
            logging.info(f"VS30 HL7 Listener started on {self.host}:{self.port}")
            logging.info("Press Ctrl+C to stop the server")
            
            while self.running:
                try:
                    client_socket, client_address = self.server_socket.accept()
                    logging.info(f"Connection from {client_address}")
                    
                    # Handle each client in a separate thread
                    client_thread = threading.Thread(
                        target=self.handle_client,
                        args=(client_socket, client_address)
                    )
                    client_thread.daemon = True
                    client_thread.start()
                    self.client_threads.append(client_thread)
                    
                except socket.timeout:
                    # Normal timeout - loop back to check self.running
                    continue
                except OSError as e:
                    if self.running:
                        logging.error(f"Socket error accept: {e}")
                        
        except Exception as e:
            if self.running:
                logging.error(f"Failed to start server: {e}")
        finally:
            self.stop()

    def stop(self):
        if not self.running:
            return
        self.running = False
        logging.info("Shutting down server...")

        # Close server socket to unblock accept()
        if self.server_socket:
            try:
                self.server_socket.close()
            except OSError:
                pass

        # Wait briefly for client threads to finish
        for t in self.client_threads:
            t.join(timeout=2.0)

        logging.info("Server stopped cleanly")

    def handle_client(self, client_socket, client_address):
        buffer = b""
        
        try:
            while self.running:
                data = client_socket.recv(BUFFER_SIZE)
                if not data:
                    break
                
                buffer += data
                
                # Check for complete MLLP messages
                if MLLPHandler.EB in buffer and MLLPHandler.CR in buffer:
                    # Find the first full message end
                    end_idx = buffer.find(MLLPHandler.EB + MLLPHandler.CR)
                    
                    if end_idx != -1:
                        # Extract the full frame including MLLP wrappers
                        full_frame = buffer[:end_idx + 2] 
                        # Keep the rest for next iteration
                        buffer = buffer[end_idx + 2:]
                        
                        # Remove SB
                        if full_frame.startswith(MLLPHandler.SB):
                            full_frame = full_frame[len(MLLPHandler.SB):]
                            
                        # Remove EB+CR
                        if full_frame.endswith(MLLPHandler.EB + MLLPHandler.CR):
                            full_frame = full_frame[:-len(MLLPHandler.EB + MLLPHandler.CR)]
                            
                        hl7_msg_str = full_frame.decode('utf-8', errors='ignore')
                        
                        logging.info(f"Received HL7 message from {client_address}")
                        logging.debug(f"Raw Content: {hl7_msg_str}")
                        
                        # Save raw message to log file
                        self._log_raw_message(hl7_msg_str, client_address)
                        
                        # Parse
                        parsed_msg = self.parser.parse_message(hl7_msg_str)
                        
                        if parsed_msg:
                            # Log extracted info
                            vitals_data = self.parser.extract_vitals(parsed_msg)
                            logging.info(f"Parsed Data: {vitals_data}")
                            
                            # Send to API if patient ID and vitals exist
                            patient_id = vitals_data.get('patient_id')
                            vitals = vitals_data.get('vitals')
                            
                            # Fallback patient ID for testing
                            if not patient_id:
                                patient_id = '123456'
                                logging.info(f"Using fallback patient_id: {patient_id}")
                            
                            if vitals:
                                self.api_client.send_vitals(patient_id, vitals)
                            else:
                                logging.warning("Skipping API send: No vitals data")
                            
                            # Create ACK
                            ack_msg = self.parser.create_ack(parsed_msg)
                            
                            # Send ACK (wrapped in MLLP)
                            wrapped_ack = MLLPHandler.wrap_message(ack_msg.encode('utf-8'))
                            client_socket.sendall(wrapped_ack)
                            logging.info(f"Sent ACK to {client_address}")
                        else:
                            logging.error("Failed to parse HL7 message")
                            
        except Exception as e:
            if self.running:
                logging.error(f"Error handling client {client_address}: {e}")
        finally:
            client_socket.close()
            logging.info(f"Connection closed for {client_address}")

    def _log_raw_message(self, raw_message, client_address):
        """Save raw HL7 message to a dedicated log file for debugging."""
        try:
            raw_log_path = os.path.join(LOG_DIR, 'raw_messages.log')
            with open(raw_log_path, 'a', encoding='utf-8') as f:
                f.write(f"\n{'='*80}\n")
                f.write(f"Timestamp: {datetime.now().isoformat()}\n")
                f.write(f"Source: {client_address}\n")
                f.write(f"{'='*80}\n")
                f.write(raw_message)
                f.write(f"\n{'='*80}\n")
        except Exception as e:
            logging.error(f"Failed to log raw message: {e}")

if __name__ == "__main__":
    server = HL7ListenerServer(HOST, PORT)

    # Register signal handlers so Ctrl+C triggers a clean shutdown
    def shutdown_handler(signum, frame):
        logging.info(f"Received signal {signum}, stopping server...")
        server.stop()
        sys.exit(0)

    signal.signal(signal.SIGINT, shutdown_handler)
    signal.signal(signal.SIGTERM, shutdown_handler)

    server.start()
