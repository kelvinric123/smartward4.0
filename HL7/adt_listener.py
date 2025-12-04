#!/usr/bin/env python3
"""
HL7 ADT Message Listener
Listens for ADT (Admit, Discharge, Transfer) messages via MLLP protocol
"""

import socket
import logging
import sys
import os
from datetime import datetime
from typing import Optional, Tuple
import threading
import signal

# MLLP (Minimal Lower Layer Protocol) constants
MLLP_START_BLOCK = b'\x0b'  # VT (Vertical Tab)
MLLP_END_BLOCK = b'\x1c'    # FS (File Separator)
MLLP_CARRIAGE_RETURN = b'\x0d'  # CR

# Configuration
HOST = os.getenv('HL7_HOST', '0.0.0.0')
PORT = int(os.getenv('HL7_PORT', '3000'))
BUFFER_SIZE = 65536

# ADT Event Types
ADT_EVENTS = {
    'A01': 'Admit/Visit Notification',
    'A02': 'Transfer a Patient',
    'A03': 'Discharge/End Visit',
    'A04': 'Register a Patient',
    'A05': 'Pre-Admit a Patient',
    'A06': 'Change Outpatient to Inpatient',
    'A07': 'Change Inpatient to Outpatient',
    'A08': 'Update Patient Information',
    'A09': 'Patient Departing - Tracking',
    'A10': 'Patient Arriving - Tracking',
    'A11': 'Cancel Admit/Visit Notification',
    'A12': 'Cancel Transfer',
    'A13': 'Cancel Discharge/End Visit',
    'A14': 'Pending Admit',
    'A15': 'Pending Transfer',
    'A16': 'Pending Discharge',
    'A17': 'Swap Patients',
    'A18': 'Merge Patient Information',
    'A19': 'Patient Query',
    'A20': 'Bed Status Update',
    'A21': 'Patient Goes on a Leave of Absence',
    'A22': 'Patient Returns from a Leave of Absence',
    'A23': 'Delete a Patient Record',
    'A24': 'Link Patient Information',
    'A25': 'Cancel Pending Discharge',
    'A26': 'Cancel Pending Transfer',
    'A27': 'Cancel Pending Admit',
    'A28': 'Add Person Information',
    'A29': 'Delete Person Information',
    'A30': 'Merge Person Information',
    'A31': 'Update Person Information',
    'A32': 'Cancel Patient Arriving - Tracking',
    'A33': 'Cancel Patient Departing - Tracking',
    'A34': 'Merge Patient Information - Patient ID Only',
    'A35': 'Merge Patient Information - Account Number Only',
    'A36': 'Merge Patient Information - Patient ID & Account Number',
    'A37': 'Unlink Patient Information',
    'A38': 'Cancel Pre-Admit',
    'A39': 'Merge Person - Patient ID',
    'A40': 'Merge Patient - Patient Identifier List',
    'A41': 'Merge Account - Patient Account Number',
    'A42': 'Merge Visit - Visit Number',
    'A43': 'Move Patient Information - Patient Identifier List',
    'A44': 'Move Account Information - Patient Account Number',
    'A45': 'Move Visit Information - Visit Number',
    'A46': 'Change Patient ID',
    'A47': 'Change Patient Identifier List',
    'A48': 'Change Alternate Patient ID',
    'A49': 'Change Patient Account Number',
    'A50': 'Change Visit Number',
    'A51': 'Change Alternate Visit ID',
    'A52': 'Cancel Leave of Absence for a Patient',
    'A53': 'Cancel Patient Returns from Leave of Absence',
    'A54': 'Change Attending Doctor',
    'A55': 'Cancel Change Attending Doctor',
    'A60': 'Update Allergy Information',
    'A61': 'Change Consulting Doctor',
    'A62': 'Cancel Change Consulting Doctor',
}


class HL7Logger:
    """Custom logger for HL7 messages with both file and console output"""
    
    def __init__(self, log_dir: str = 'logs', data_dir: str = 'received_messages'):
        self.log_dir = log_dir
        self.data_dir = data_dir
        os.makedirs(log_dir, exist_ok=True)
        os.makedirs(data_dir, exist_ok=True)
        
        # Configure root logger
        self.logger = logging.getLogger('HL7_ADT_Listener')
        self.logger.setLevel(logging.DEBUG)
        self.logger.handlers = []  # Clear existing handlers
        
        # Console handler with colors
        console_handler = logging.StreamHandler(sys.stdout)
        console_handler.setLevel(logging.DEBUG)
        console_format = logging.Formatter(
            '%(asctime)s | %(levelname)-8s | %(message)s',
            datefmt='%Y-%m-%d %H:%M:%S'
        )
        console_handler.setFormatter(console_format)
        self.logger.addHandler(console_handler)
        
        # File handler for all logs
        all_log_file = os.path.join(log_dir, 'hl7_adt_all.log')
        file_handler = logging.FileHandler(all_log_file, encoding='utf-8')
        file_handler.setLevel(logging.DEBUG)
        file_format = logging.Formatter(
            '%(asctime)s | %(levelname)-8s | %(message)s',
            datefmt='%Y-%m-%d %H:%M:%S'
        )
        file_handler.setFormatter(file_format)
        self.logger.addHandler(file_handler)
        
        # Separate file handler for messages only
        msg_log_file = os.path.join(log_dir, 'hl7_messages.log')
        self.msg_handler = logging.FileHandler(msg_log_file, encoding='utf-8')
        self.msg_handler.setLevel(logging.INFO)
        msg_format = logging.Formatter('%(asctime)s\n%(message)s\n' + '='*80 + '\n')
        self.msg_handler.setFormatter(msg_format)
        
        # Error log file
        error_log_file = os.path.join(log_dir, 'hl7_errors.log')
        error_handler = logging.FileHandler(error_log_file, encoding='utf-8')
        error_handler.setLevel(logging.ERROR)
        error_handler.setFormatter(file_format)
        self.logger.addHandler(error_handler)
    
    def debug(self, msg: str):
        self.logger.debug(msg)
    
    def info(self, msg: str):
        self.logger.info(msg)
    
    def warning(self, msg: str):
        self.logger.warning(msg)
    
    def error(self, msg: str):
        self.logger.error(msg)
    
    def log_raw_message(self, msg: str):
        """Log raw HL7 message to separate file"""
        record = logging.LogRecord(
            name='HL7_Message',
            level=logging.INFO,
            pathname='',
            lineno=0,
            msg=msg,
            args=(),
            exc_info=None
        )
        self.msg_handler.emit(record)
    
    def save_message_to_file(self, msg: str, parsed: dict, msg_count: int) -> str:
        """Save received message to individual file for reference"""
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        
        # Get event type for filename
        msg_type = parsed.get('parsed', {}).get('message_type', 'UNKNOWN')
        event_code = ''
        if '^' in msg_type:
            parts = msg_type.split('^')
            event_code = parts[1] if len(parts) > 1 else 'UNK'
        else:
            event_code = 'UNK'
        
        # Get patient ID for filename
        patient_id = parsed.get('parsed', {}).get('patient_id', 'NOID')
        # Clean patient ID for filename (remove special chars)
        patient_id = ''.join(c for c in patient_id if c.isalnum() or c in '-_')[:20]
        
        # Create filename
        filename = f"{timestamp}_{event_code}_{patient_id}_{msg_count:05d}.hl7"
        filepath = os.path.join(self.data_dir, filename)
        
        # Write message to file
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(f"# HL7 Message Received: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n")
            f.write(f"# Message Type: {msg_type}\n")
            f.write(f"# Patient ID: {parsed.get('parsed', {}).get('patient_id', 'N/A')}\n")
            f.write(f"# Patient Name: {parsed.get('parsed', {}).get('patient_name', 'N/A')}\n")
            f.write(f"# Control ID: {parsed.get('parsed', {}).get('message_control_id', 'N/A')}\n")
            f.write("#" + "="*60 + "\n\n")
            f.write(msg)
        
        self.info(f"Message saved to: {filepath}")
        return filepath


class HL7Parser:
    """Simple HL7 v2.x message parser"""
    
    def __init__(self, logger: HL7Logger):
        self.logger = logger
    
    def parse(self, raw_message: str) -> dict:
        """Parse HL7 message and extract key fields"""
        result = {
            'raw': raw_message,
            'segments': {},
            'parsed': {}
        }
        
        try:
            # Split message into segments
            segments = raw_message.strip().split('\r')
            if not segments:
                segments = raw_message.strip().split('\n')
            
            for segment in segments:
                if not segment.strip():
                    continue
                    
                # Get segment name and fields
                fields = segment.split('|')
                segment_name = fields[0] if fields else ''
                
                if segment_name:
                    result['segments'][segment_name] = fields
            
            # Parse MSH (Message Header) segment
            if 'MSH' in result['segments']:
                msh = result['segments']['MSH']
                result['parsed']['sending_application'] = msh[2] if len(msh) > 2 else ''
                result['parsed']['sending_facility'] = msh[3] if len(msh) > 3 else ''
                result['parsed']['receiving_application'] = msh[4] if len(msh) > 4 else ''
                result['parsed']['receiving_facility'] = msh[5] if len(msh) > 5 else ''
                result['parsed']['message_datetime'] = msh[6] if len(msh) > 6 else ''
                result['parsed']['message_type'] = msh[8] if len(msh) > 8 else ''
                result['parsed']['message_control_id'] = msh[9] if len(msh) > 9 else ''
                result['parsed']['processing_id'] = msh[10] if len(msh) > 10 else ''
                result['parsed']['version'] = msh[11] if len(msh) > 11 else ''
            
            # Parse EVN (Event Type) segment
            if 'EVN' in result['segments']:
                evn = result['segments']['EVN']
                result['parsed']['event_type_code'] = evn[1] if len(evn) > 1 else ''
                result['parsed']['recorded_datetime'] = evn[2] if len(evn) > 2 else ''
            
            # Parse PID (Patient Identification) segment
            if 'PID' in result['segments']:
                pid = result['segments']['PID']
                result['parsed']['patient_id'] = pid[3] if len(pid) > 3 else ''
                result['parsed']['patient_name'] = pid[5] if len(pid) > 5 else ''
                result['parsed']['dob'] = pid[7] if len(pid) > 7 else ''
                result['parsed']['gender'] = pid[8] if len(pid) > 8 else ''
                result['parsed']['address'] = pid[11] if len(pid) > 11 else ''
                result['parsed']['phone'] = pid[13] if len(pid) > 13 else ''
            
            # Parse PV1 (Patient Visit) segment
            if 'PV1' in result['segments']:
                pv1 = result['segments']['PV1']
                result['parsed']['patient_class'] = pv1[2] if len(pv1) > 2 else ''
                result['parsed']['assigned_location'] = pv1[3] if len(pv1) > 3 else ''
                result['parsed']['admission_type'] = pv1[4] if len(pv1) > 4 else ''
                result['parsed']['attending_doctor'] = pv1[7] if len(pv1) > 7 else ''
                result['parsed']['visit_number'] = pv1[19] if len(pv1) > 19 else ''
                result['parsed']['admit_datetime'] = pv1[44] if len(pv1) > 44 else ''
                result['parsed']['discharge_datetime'] = pv1[45] if len(pv1) > 45 else ''
            
            # Parse PV2 (Patient Visit - Additional Info) segment
            if 'PV2' in result['segments']:
                pv2 = result['segments']['PV2']
                result['parsed']['admit_reason'] = pv2[3] if len(pv2) > 3 else ''
            
        except Exception as e:
            self.logger.error(f"Error parsing HL7 message: {str(e)}")
            result['error'] = str(e)
        
        return result


class HL7ADTListener:
    """HL7 ADT Message Listener using MLLP protocol"""
    
    def __init__(self, host: str = HOST, port: int = PORT):
        self.host = host
        self.port = port
        self.logger = HL7Logger()
        self.parser = HL7Parser(self.logger)
        self.running = False
        self.server_socket = None
        self.message_count = 0
    
    def create_ack(self, parsed_msg: dict, ack_code: str = 'AA') -> bytes:
        """Create HL7 ACK (Acknowledgment) message"""
        timestamp = datetime.now().strftime('%Y%m%d%H%M%S')
        
        sending_app = parsed_msg.get('parsed', {}).get('receiving_application', 'HL7_LISTENER')
        sending_fac = parsed_msg.get('parsed', {}).get('receiving_facility', 'SMARTWARD')
        receiving_app = parsed_msg.get('parsed', {}).get('sending_application', '')
        receiving_fac = parsed_msg.get('parsed', {}).get('sending_facility', '')
        msg_control_id = parsed_msg.get('parsed', {}).get('message_control_id', '')
        version = parsed_msg.get('parsed', {}).get('version', '2.5')
        
        ack_message = (
            f"MSH|^~\\&|{sending_app}|{sending_fac}|{receiving_app}|{receiving_fac}|"
            f"{timestamp}||ACK|{timestamp}|P|{version}\r"
            f"MSA|{ack_code}|{msg_control_id}|Message received successfully\r"
        )
        
        return MLLP_START_BLOCK + ack_message.encode('utf-8') + MLLP_END_BLOCK + MLLP_CARRIAGE_RETURN
    
    def handle_client(self, client_socket: socket.socket, client_address: Tuple[str, int]):
        """Handle incoming client connection"""
        self.logger.info(f"New connection from {client_address[0]}:{client_address[1]}")
        
        try:
            buffer = b''
            
            while self.running:
                try:
                    data = client_socket.recv(BUFFER_SIZE)
                    if not data:
                        break
                    
                    buffer += data
                    
                    # Check for complete MLLP message
                    while MLLP_START_BLOCK in buffer and MLLP_END_BLOCK in buffer:
                        start_idx = buffer.index(MLLP_START_BLOCK)
                        end_idx = buffer.index(MLLP_END_BLOCK)
                        
                        if end_idx > start_idx:
                            # Extract message (excluding MLLP framing)
                            raw_message = buffer[start_idx + 1:end_idx].decode('utf-8', errors='replace')
                            buffer = buffer[end_idx + 2:]  # Skip end block and CR
                            
                            # Process message
                            self.process_message(raw_message, client_socket, client_address)
                        else:
                            break
                            
                except socket.timeout:
                    continue
                except Exception as e:
                    self.logger.error(f"Error receiving data: {str(e)}")
                    break
                    
        except Exception as e:
            self.logger.error(f"Error handling client {client_address}: {str(e)}")
        finally:
            client_socket.close()
            self.logger.info(f"Connection closed: {client_address[0]}:{client_address[1]}")
    
    def process_message(self, raw_message: str, client_socket: socket.socket, client_address: Tuple[str, int]):
        """Process received HL7 message"""
        self.message_count += 1
        
        self.logger.info(f"{'='*60}")
        self.logger.info(f"MESSAGE #{self.message_count} RECEIVED")
        self.logger.info(f"From: {client_address[0]}:{client_address[1]}")
        self.logger.info(f"Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        self.logger.info(f"{'='*60}")
        
        # Log raw message
        self.logger.log_raw_message(raw_message)
        self.logger.debug(f"Raw Message:\n{raw_message}")
        
        # Parse message
        parsed = self.parser.parse(raw_message)
        
        # Save message to file for reference
        self.logger.save_message_to_file(raw_message, parsed, self.message_count)
        
        # Log parsed information
        self.log_parsed_message(parsed)
        
        # Send acknowledgment
        try:
            ack = self.create_ack(parsed)
            client_socket.send(ack)
            self.logger.info("ACK sent successfully")
        except Exception as e:
            self.logger.error(f"Failed to send ACK: {str(e)}")
    
    def log_parsed_message(self, parsed: dict):
        """Log parsed message details"""
        p = parsed.get('parsed', {})
        
        # Message type info
        msg_type = p.get('message_type', 'Unknown')
        event_code = ''
        if '^' in msg_type:
            parts = msg_type.split('^')
            event_code = parts[1] if len(parts) > 1 else ''
        
        event_description = ADT_EVENTS.get(event_code, 'Unknown Event')
        
        self.logger.info(f"\n{'─'*40}")
        self.logger.info(f"MESSAGE DETAILS")
        self.logger.info(f"{'─'*40}")
        self.logger.info(f"Message Type     : {msg_type}")
        self.logger.info(f"Event            : {event_code} - {event_description}")
        self.logger.info(f"Control ID       : {p.get('message_control_id', 'N/A')}")
        self.logger.info(f"HL7 Version      : {p.get('version', 'N/A')}")
        self.logger.info(f"Message DateTime : {p.get('message_datetime', 'N/A')}")
        
        self.logger.info(f"\n{'─'*40}")
        self.logger.info(f"ROUTING INFORMATION")
        self.logger.info(f"{'─'*40}")
        self.logger.info(f"Sending App      : {p.get('sending_application', 'N/A')}")
        self.logger.info(f"Sending Facility : {p.get('sending_facility', 'N/A')}")
        self.logger.info(f"Receiving App    : {p.get('receiving_application', 'N/A')}")
        self.logger.info(f"Receiving Fac    : {p.get('receiving_facility', 'N/A')}")
        
        if p.get('patient_id') or p.get('patient_name'):
            self.logger.info(f"\n{'─'*40}")
            self.logger.info(f"PATIENT INFORMATION")
            self.logger.info(f"{'─'*40}")
            self.logger.info(f"Patient ID       : {p.get('patient_id', 'N/A')}")
            self.logger.info(f"Patient Name     : {p.get('patient_name', 'N/A')}")
            self.logger.info(f"DOB              : {p.get('dob', 'N/A')}")
            self.logger.info(f"Gender           : {p.get('gender', 'N/A')}")
        
        if p.get('assigned_location') or p.get('patient_class'):
            self.logger.info(f"\n{'─'*40}")
            self.logger.info(f"VISIT INFORMATION")
            self.logger.info(f"{'─'*40}")
            self.logger.info(f"Patient Class    : {p.get('patient_class', 'N/A')}")
            self.logger.info(f"Location         : {p.get('assigned_location', 'N/A')}")
            self.logger.info(f"Admission Type   : {p.get('admission_type', 'N/A')}")
            self.logger.info(f"Visit Number     : {p.get('visit_number', 'N/A')}")
            self.logger.info(f"Attending Doctor : {p.get('attending_doctor', 'N/A')}")
            self.logger.info(f"Admit DateTime   : {p.get('admit_datetime', 'N/A')}")
            self.logger.info(f"Discharge DT     : {p.get('discharge_datetime', 'N/A')}")
        
        # Log all segments found
        segments = list(parsed.get('segments', {}).keys())
        self.logger.info(f"\n{'─'*40}")
        self.logger.info(f"SEGMENTS FOUND: {', '.join(segments)}")
        self.logger.info(f"{'─'*40}\n")
    
    def start(self):
        """Start the HL7 listener"""
        self.running = True
        
        # Setup signal handlers for graceful shutdown
        signal.signal(signal.SIGINT, self.shutdown)
        signal.signal(signal.SIGTERM, self.shutdown)
        
        try:
            self.server_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            self.server_socket.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
            self.server_socket.bind((self.host, self.port))
            self.server_socket.listen(5)
            self.server_socket.settimeout(1.0)  # Allow checking self.running periodically
            
            self.logger.info(f"{'='*60}")
            self.logger.info(f"HL7 ADT LISTENER STARTED")
            self.logger.info(f"{'='*60}")
            self.logger.info(f"Host: {self.host}")
            self.logger.info(f"Port: {self.port}")
            self.logger.info(f"Protocol: MLLP (Minimal Lower Layer Protocol)")
            self.logger.info(f"Listening for ADT messages...")
            self.logger.info(f"Press Ctrl+C to stop")
            self.logger.info(f"{'='*60}\n")
            
            while self.running:
                try:
                    client_socket, client_address = self.server_socket.accept()
                    client_socket.settimeout(30.0)
                    
                    # Handle client in separate thread
                    client_thread = threading.Thread(
                        target=self.handle_client,
                        args=(client_socket, client_address),
                        daemon=True
                    )
                    client_thread.start()
                    
                except socket.timeout:
                    continue
                except OSError:
                    break
                    
        except Exception as e:
            self.logger.error(f"Server error: {str(e)}")
        finally:
            self.cleanup()
    
    def shutdown(self, signum=None, frame=None):
        """Handle shutdown signal"""
        self.logger.info("\nShutdown signal received...")
        self.running = False
    
    def cleanup(self):
        """Cleanup resources"""
        if self.server_socket:
            try:
                self.server_socket.close()
            except:
                pass
        
        self.logger.info(f"\n{'='*60}")
        self.logger.info(f"HL7 ADT LISTENER STOPPED")
        self.logger.info(f"Total messages processed: {self.message_count}")
        self.logger.info(f"{'='*60}")


def main():
    """Main entry point"""
    print("""
    ╔═══════════════════════════════════════════════════════════╗
    ║           HL7 ADT Message Listener v1.0                   ║
    ║        SmartWard Healthcare Integration                   ║
    ╠═══════════════════════════════════════════════════════════╣
    ║  Supported Events: A01-A62 (ADT Messages)                 ║
    ║  Protocol: MLLP over TCP/IP                               ║
    ║  Default Port: 3000                                       ║
    ╚═══════════════════════════════════════════════════════════╝
    """)
    
    # Get configuration from environment or use defaults
    host = os.getenv('HL7_HOST', '0.0.0.0')
    port = int(os.getenv('HL7_PORT', '3000'))
    
    listener = HL7ADTListener(host=host, port=port)
    listener.start()


if __name__ == '__main__':
    main()

