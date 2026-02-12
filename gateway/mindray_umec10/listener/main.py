import socket
import threading
import logging
import time
import os
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

    def start(self):
        self.server_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        self.server_socket.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        
        try:
            self.server_socket.bind((self.host, self.port))
            self.server_socket.listen(5)
            self.running = True
            logging.info(f"HL7 Listener started on {self.host}:{self.port}")
            
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
                    
                except socket.error as e:
                    if self.running:
                        logging.error(f"Socket error accept: {e}")
                        
        except Exception as e:
            logging.error(f"Failed to start server: {e}")
        finally:
            self.stop()

    def stop(self):
        self.running = False
        if self.server_socket:
            self.server_socket.close()
            logging.info("Server stopped")

    def handle_client(self, client_socket, client_address):
        buffer = b""
        
        try:
            while True:
                data = client_socket.recv(BUFFER_SIZE)
                if not data:
                    break
                
                buffer += data
                
                # Check for complete MLLP messages
                if MLLPHandler.EB in buffer and MLLPHandler.CR in buffer:
                    # Process buffer
                    # Note: We might have multiple messages or partial messages
                    # Simple MLLPHandler implementation assumes clean boundaries for now
                    # or splits by end frame.
                    
                    # Find the first full message end
                    end_idx = buffer.find(MLLPHandler.EB + MLLPHandler.CR)
                    
                    if end_idx != -1:
                        # Extract the full frame including MLLP wrappers
                        full_frame = buffer[:end_idx + 2] 
                        # Keep the rest for next iteration
                        buffer = buffer[end_idx + 2:]
                        
                        # Unwrap safely - Remove framing but keep internal separators
                        # full_frame includes SB ... EB+CR
                        
                        # Remove SB
                        if full_frame.startswith(MLLPHandler.SB):
                            full_frame = full_frame[len(MLLPHandler.SB):]
                            
                        # Remove EB+CR
                        if full_frame.endswith(MLLPHandler.EB + MLLPHandler.CR):
                            full_frame = full_frame[:-len(MLLPHandler.EB + MLLPHandler.CR)]
                            
                        hl7_msg_str = full_frame.decode('utf-8', errors='ignore')
                        
                        logging.info(f"Received HL7 message from {client_address}")
                        logging.debug(f"Raw Content: {hl7_msg_str}")
                        
                        # Parse
                        parsed_msg = self.parser.parse_message(hl7_msg_str)
                        
                        if parsed_msg:
                            # Log extracted info
                            vitals_data = self.parser.extract_vitals(parsed_msg)
                            logging.info(f"Parsed Data: {vitals_data}")
                            
                            # Send to API if patient ID and vitals exist
                            patient_id = vitals_data.get('patient_id')
                            vitals = vitals_data.get('vitals')
                            
                            if patient_id and vitals:
                                self.api_client.send_vitals(patient_id, vitals)
                            else:
                                logging.warning("Skipping API send: Missing Patient ID or Vitals")
                            
                            # Create ACK
                            ack_msg = self.parser.create_ack(parsed_msg)
                            
                            # Send ACK (wrapped in MLLP)
                            wrapped_ack = MLLPHandler.wrap_message(ack_msg.encode('utf-8'))
                            client_socket.sendall(wrapped_ack)
                            logging.info(f"Sent ACK to {client_address}")
                        else:
                            logging.error("Failed to parse HL7 message")
                            
        except Exception as e:
            logging.error(f"Error handling client {client_address}: {e}")
        finally:
            client_socket.close()
            logging.info(f"Connection closed for {client_address}")

if __name__ == "__main__":
    server = HL7ListenerServer(HOST, PORT)
    try:
        server.start()
    except KeyboardInterrupt:
        logging.info("Stopping server...")
        server.stop()
