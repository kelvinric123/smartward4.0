#!/usr/bin/env python3
"""
B.Braun HL7 Infusion Pump Message Listener
Listens for HL7 messages from B.Braun infusion pumps via MLLP protocol
Parses messages and stores them in the database for Laravel to display
"""

import socket
import logging
import sys
import os
import json
import mysql.connector
from datetime import datetime
from typing import Optional, Tuple, List, Dict, Any
import threading
import signal

# Load environment variables from .env file
try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass  # python-dotenv not installed, will use system env vars

# MLLP (Minimal Lower Layer Protocol) constants
MLLP_START_BLOCK = b'\x0b'  # VT (Vertical Tab)
MLLP_END_BLOCK = b'\x1c'    # FS (File Separator)
MLLP_CARRIAGE_RETURN = b'\x0d'  # CR

# Configuration
HOST = os.getenv('BBRAUN_HOST', '0.0.0.0')
PORT = int(os.getenv('BBRAUN_PORT', '5001'))
BUFFER_SIZE = 65536

# Database Configuration
DB_HOST = os.getenv('DB_HOST', 'localhost')
DB_PORT = int(os.getenv('DB_PORT', '3306'))
DB_NAME = os.getenv('DB_NAME', 'smartward4')
DB_USER = os.getenv('DB_USER', 'root')
DB_PASSWORD = os.getenv('DB_PASSWORD', '')

# Development Mode Configuration
# When DEV_MODE=true, all infusion data will be assigned to DEV_ASSIGN_MRN
DEV_MODE = os.getenv('DEV_MODE', 'false').lower() in ('true', '1', 'yes')
DEV_ASSIGN_MRN = os.getenv('DEV_ASSIGN_MRN', '')

# B.Braun HL7 Message Types
BBRAUN_MESSAGE_TYPES = {
    'ORU': 'Observation Result (Pump Status)',
    'ORM': 'Order Message (New Infusion)',
    'ADT': 'Patient Information Update',
    'RAS': 'Pharmacy/Treatment Administration',
    'RDE': 'Pharmacy/Treatment Encoded Order',
    'RGV': 'Pharmacy/Treatment Give',
}

# Infusion Status Mapping
INFUSION_STATUS_MAP = {
    'RUN': 'running',
    'RUNNING': 'running',
    'PAUSE': 'paused',
    'PAUSED': 'paused',
    'STOP': 'stopped',
    'STOPPED': 'stopped',
    'COMPLETE': 'completed',
    'COMPLETED': 'completed',
    'ALARM': 'alarming',
    'ALARMING': 'alarming',
    'PENDING': 'pending',
    'IDLE': 'pending',
}


class HL7Logger:
    """Custom logger for HL7 messages with both file and console output"""
    
    def __init__(self, log_dir: str = 'logs'):
        self.log_dir = log_dir
        os.makedirs(log_dir, exist_ok=True)
        
        # Configure root logger
        self.logger = logging.getLogger('BBRAUN_HL7_Listener')
        self.logger.setLevel(logging.DEBUG)
        self.logger.handlers = []  # Clear existing handlers
        
        # Console handler
        console_handler = logging.StreamHandler(sys.stdout)
        console_handler.setLevel(logging.DEBUG)
        console_format = logging.Formatter(
            '%(asctime)s | %(levelname)-8s | %(message)s',
            datefmt='%Y-%m-%d %H:%M:%S'
        )
        console_handler.setFormatter(console_format)
        self.logger.addHandler(console_handler)
        
        # File handler for all logs
        all_log_file = os.path.join(log_dir, 'bbraun_hl7_all.log')
        file_handler = logging.FileHandler(all_log_file, encoding='utf-8')
        file_handler.setLevel(logging.DEBUG)
        file_format = logging.Formatter(
            '%(asctime)s | %(levelname)-8s | %(message)s',
            datefmt='%Y-%m-%d %H:%M:%S'
        )
        file_handler.setFormatter(file_format)
        self.logger.addHandler(file_handler)
        
        # Separate file handler for messages only
        msg_log_file = os.path.join(log_dir, 'bbraun_messages.log')
        self.msg_handler = logging.FileHandler(msg_log_file, encoding='utf-8')
        self.msg_handler.setLevel(logging.INFO)
        msg_format = logging.Formatter('%(asctime)s\n%(message)s\n' + '='*80 + '\n')
        self.msg_handler.setFormatter(msg_format)
        
        # Error log file
        error_log_file = os.path.join(log_dir, 'bbraun_errors.log')
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
            name='BBRAUN_Message',
            level=logging.INFO,
            pathname='',
            lineno=0,
            msg=msg,
            args=(),
            exc_info=None
        )
        self.msg_handler.emit(record)


class DatabaseManager:
    """Database manager for storing B.Braun HL7 logs"""
    
    def __init__(self, logger: HL7Logger):
        self.logger = logger
        self.connection = None
        self.connect()
    
    def connect(self):
        """Connect to the database"""
        try:
            self.connection = mysql.connector.connect(
                host=DB_HOST,
                port=DB_PORT,
                database=DB_NAME,
                user=DB_USER,
                password=DB_PASSWORD,
                autocommit=True
            )
            self.logger.info(f"Connected to MySQL database: {DB_NAME}@{DB_HOST}")
        except Exception as e:
            self.logger.warning(f"Could not connect to MySQL: {str(e)}")
            self.logger.info("Messages will be logged to files only")
            self.connection = None
    
    def ensure_connection(self):
        """Ensure database connection is alive"""
        if self.connection is None:
            self.connect()
        elif not self.connection.is_connected():
            self.connect()
    
    def log_message(self, parsed_data: dict, raw_message: str, source_ip: str, 
                   status: str = 'received', error_message: str = None) -> Optional[int]:
        """Log HL7 message to database"""
        if self.connection is None:
            return None
        
        try:
            self.ensure_connection()
            cursor = self.connection.cursor()
            
            msh = parsed_data.get('msh', {})
            pid = parsed_data.get('pid', {})
            obx = parsed_data.get('obx', {})
            
            # Development mode: override patient MRN
            patient_mrn = pid.get('mrn', '')
            if DEV_MODE and DEV_ASSIGN_MRN:
                patient_mrn = DEV_ASSIGN_MRN
                self.logger.info(f"[DEV MODE] Assigning infusion to MRN: {patient_mrn}")
            
            # Extract infusion data from parsed message
            infusion_data = parsed_data.get('infusion_data', {})
            
            query = """
                INSERT INTO bbraun_hl7_logs (
                    message_control_id, message_type, event_type,
                    sending_application, sending_facility,
                    patient_mrn, patient_name,
                    device_id, medication_name,
                    flow_rate, total_volume, infused_volume, remaining_volume,
                    pump_status, alarm_type, alarm_message,
                    raw_message, parsed_data,
                    source_ip, status, error_message,
                    created_at, updated_at
                ) VALUES (
                    %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s
                )
            """
            
            now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            
            values = (
                msh.get('message_control_id', ''),
                msh.get('message_type', ''),
                msh.get('event_type', ''),
                msh.get('sending_application', ''),
                msh.get('sending_facility', ''),
                patient_mrn,
                pid.get('name', ''),
                infusion_data.get('device_id', ''),
                infusion_data.get('medication_name', ''),
                infusion_data.get('flow_rate'),
                infusion_data.get('total_volume'),
                infusion_data.get('infused_volume'),
                infusion_data.get('remaining_volume'),
                infusion_data.get('pump_status', ''),
                infusion_data.get('alarm_type', ''),
                infusion_data.get('alarm_message', ''),
                raw_message,
                json.dumps(parsed_data),
                source_ip,
                status,
                error_message,
                now,
                now
            )
            
            cursor.execute(query, values)
            log_id = cursor.lastrowid
            cursor.close()
            
            self.logger.info(f"Message logged to database with ID: {log_id}")
            return log_id
            
        except Exception as e:
            self.logger.error(f"Error logging message to database: {str(e)}")
            return None
    
    def close(self):
        """Close database connection"""
        if self.connection and self.connection.is_connected():
            self.connection.close()
            self.logger.info("Database connection closed")


class BbraunHL7Parser:
    """HL7 v2.x message parser for B.Braun infusion pump messages"""
    
    def __init__(self, logger: HL7Logger):
        self.logger = logger
    
    def parse(self, raw_message: str) -> dict:
        """Parse HL7 message and extract all relevant fields"""
        result = {
            'raw': raw_message,
            'segments': {},
            'msh': {},
            'pid': {},
            'pv1': {},
            'obr': {},
            'obx': [],
            'rxe': {},
            'infusion_data': {},
        }
        
        try:
            # Split message into segments
            segments = raw_message.strip().split('\r')
            if len(segments) == 1:
                segments = raw_message.strip().split('\n')
            
            # Store raw segments
            for segment in segments:
                if not segment.strip():
                    continue
                fields = segment.split('|')
                segment_name = fields[0] if fields else ''
                if segment_name:
                    if segment_name == 'OBX':
                        # Handle multiple OBX segments
                        if 'OBX' not in result['segments']:
                            result['segments']['OBX'] = []
                        result['segments']['OBX'].append(fields)
                    else:
                        result['segments'][segment_name] = fields
            
            # Parse each segment type
            result['msh'] = self._parse_msh(result['segments'].get('MSH', []))
            result['pid'] = self._parse_pid(result['segments'].get('PID', []))
            result['pv1'] = self._parse_pv1(result['segments'].get('PV1', []))
            result['obr'] = self._parse_obr(result['segments'].get('OBR', []))
            result['obx'] = self._parse_obx_list(result['segments'].get('OBX', []))
            result['rxe'] = self._parse_rxe(result['segments'].get('RXE', []))
            
            # Extract infusion-specific data
            result['infusion_data'] = self._extract_infusion_data(result)
            
        except Exception as e:
            self.logger.error(f"Error parsing HL7 message: {str(e)}")
            result['error'] = str(e)
        
        return result
    
    def _get_field(self, fields: list, index: int, default: str = '') -> str:
        """Safely get a field from the fields list"""
        try:
            return fields[index] if len(fields) > index else default
        except:
            return default
    
    def _parse_component(self, field: str, index: int, default: str = '') -> str:
        """Parse a component from a field (separated by ^)"""
        try:
            parts = field.split('^')
            return parts[index] if len(parts) > index else default
        except:
            return default
    
    def _parse_msh(self, msh: list) -> dict:
        """Parse MSH (Message Header) segment"""
        if not msh:
            return {}
        
        message_type_full = self._get_field(msh, 8)
        event_type = ''
        if '^' in message_type_full:
            parts = message_type_full.split('^')
            event_type = parts[1] if len(parts) > 1 else ''
        
        return {
            'field_separator': '|',
            'encoding_characters': self._get_field(msh, 1),
            'sending_application': self._get_field(msh, 2),
            'sending_facility': self._get_field(msh, 3),
            'receiving_application': self._get_field(msh, 4),
            'receiving_facility': self._get_field(msh, 5),
            'message_datetime': self._get_field(msh, 6),
            'security': self._get_field(msh, 7),
            'message_type': message_type_full,
            'event_type': event_type,
            'message_control_id': self._get_field(msh, 9),
            'processing_id': self._get_field(msh, 10),
            'version': self._get_field(msh, 11),
        }
    
    def _parse_pid(self, pid: list) -> dict:
        """Parse PID (Patient Identification) segment"""
        if not pid:
            return {}
        
        patient_id = self._get_field(pid, 3)
        mrn = patient_id.split('^')[0] if '^' in patient_id else patient_id
        
        patient_name_raw = self._get_field(pid, 5)
        name_parts = patient_name_raw.split('^') if patient_name_raw else []
        last_name = name_parts[0] if len(name_parts) > 0 else ''
        first_name = name_parts[1] if len(name_parts) > 1 else ''
        full_name = f"{first_name} {last_name}".strip()
        
        return {
            'mrn': mrn,
            'name_raw': patient_name_raw,
            'name': full_name,
            'last_name': last_name,
            'first_name': first_name,
            'dob': self._get_field(pid, 7),
            'gender': self._get_field(pid, 8),
        }
    
    def _parse_pv1(self, pv1: list) -> dict:
        """Parse PV1 (Patient Visit) segment"""
        if not pv1:
            return {}
        
        location_raw = self._get_field(pv1, 3)
        location_parts = location_raw.split('^') if location_raw else []
        
        return {
            'patient_class': self._get_field(pv1, 2),
            'location_raw': location_raw,
            'ward': location_parts[0] if len(location_parts) > 0 else '',
            'room': location_parts[1] if len(location_parts) > 1 else '',
            'bed': location_parts[2] if len(location_parts) > 2 else '',
        }
    
    def _parse_obr(self, obr: list) -> dict:
        """Parse OBR (Observation Request) segment"""
        if not obr:
            return {}
        
        return {
            'set_id': self._get_field(obr, 1),
            'placer_order_number': self._get_field(obr, 2),
            'filler_order_number': self._get_field(obr, 3),
            'universal_service_id': self._get_field(obr, 4),
            'observation_datetime': self._get_field(obr, 7),
        }
    
    def _parse_obx_list(self, obx_list: list) -> List[dict]:
        """Parse multiple OBX (Observation Result) segments"""
        results = []
        if not obx_list:
            return results
        
        for obx in obx_list:
            parsed = self._parse_obx(obx)
            if parsed:
                results.append(parsed)
        
        return results
    
    def _parse_obx(self, obx: list) -> dict:
        """Parse single OBX (Observation Result) segment"""
        if not obx:
            return {}
        
        observation_id = self._get_field(obx, 3)
        obs_parts = observation_id.split('^') if observation_id else []
        
        return {
            'set_id': self._get_field(obx, 1),
            'value_type': self._get_field(obx, 2),
            'observation_id': observation_id,
            'observation_code': obs_parts[0] if obs_parts else '',
            'observation_name': obs_parts[1] if len(obs_parts) > 1 else '',
            'observation_sub_id': self._get_field(obx, 4),
            'observation_value': self._get_field(obx, 5),
            'units': self._get_field(obx, 6),
            'reference_range': self._get_field(obx, 7),
            'abnormal_flags': self._get_field(obx, 8),
            'observation_status': self._get_field(obx, 11),
            'observation_datetime': self._get_field(obx, 14),
        }
    
    def _parse_rxe(self, rxe: list) -> dict:
        """Parse RXE (Pharmacy/Treatment Encoded Order) segment"""
        if not rxe:
            return {}
        
        give_code = self._get_field(rxe, 2)
        give_parts = give_code.split('^') if give_code else []
        
        return {
            'quantity_timing': self._get_field(rxe, 1),
            'give_code': give_code,
            'medication_code': give_parts[0] if give_parts else '',
            'medication_name': give_parts[1] if len(give_parts) > 1 else '',
            'give_amount_min': self._get_field(rxe, 3),
            'give_amount_max': self._get_field(rxe, 4),
            'give_units': self._get_field(rxe, 5),
            'give_rate_amount': self._get_field(rxe, 22),
            'give_rate_units': self._get_field(rxe, 23),
        }
    
    def _extract_infusion_data(self, parsed: dict) -> dict:
        """Extract infusion-specific data from parsed segments"""
        infusion_data = {
            'device_id': '',
            'medication_name': '',
            'medication_code': '',
            'flow_rate': None,
            'total_volume': None,
            'infused_volume': None,
            'remaining_volume': None,
            'remaining_minutes': None,
            'pump_status': '',
            'alarm_type': '',
            'alarm_message': '',
        }
        
        msh = parsed.get('msh', {})
        rxe = parsed.get('rxe', {})
        obx_list = parsed.get('obx', [])
        
        # Get device ID from sending application
        infusion_data['device_id'] = msh.get('sending_application', '')
        
        # Get medication info from RXE
        if rxe:
            infusion_data['medication_name'] = rxe.get('medication_name', '')
            infusion_data['medication_code'] = rxe.get('medication_code', '')
            
            # Try to get flow rate from RXE
            rate_str = rxe.get('give_rate_amount', '')
            if rate_str:
                try:
                    infusion_data['flow_rate'] = float(rate_str)
                except:
                    pass
        
        # Extract data from OBX segments
        for obx in obx_list:
            obs_code = obx.get('observation_code', '').upper()
            obs_name = obx.get('observation_name', '').upper()
            obs_value = obx.get('observation_value', '')
            
            # B.Braun specific observation codes
            if obs_code in ['FLOWRATE', 'RATE', 'FLOW'] or 'FLOW' in obs_name:
                try:
                    infusion_data['flow_rate'] = float(obs_value)
                except:
                    pass
            
            elif obs_code in ['TOTALVOL', 'VTBI', 'TOTVOL'] or 'TOTAL' in obs_name and 'VOL' in obs_name:
                try:
                    infusion_data['total_volume'] = float(obs_value)
                except:
                    pass
            
            elif obs_code in ['INFVOL', 'INFUSED', 'GIVENVOL'] or 'INFUSED' in obs_name:
                try:
                    infusion_data['infused_volume'] = float(obs_value)
                except:
                    pass
            
            elif obs_code in ['REMVOL', 'REMAINING', 'RESTVOL'] or 'REMAIN' in obs_name and 'VOL' in obs_name:
                try:
                    infusion_data['remaining_volume'] = float(obs_value)
                except:
                    pass
            
            elif obs_code in ['REMTIME', 'TIMEREM', 'TTEND'] or 'TIME' in obs_name and 'REMAIN' in obs_name:
                try:
                    infusion_data['remaining_minutes'] = int(obs_value)
                except:
                    pass
            
            elif obs_code in ['STATUS', 'PUMPSTATUS', 'STATE'] or 'STATUS' in obs_name:
                raw_status = obs_value.upper()
                infusion_data['pump_status'] = INFUSION_STATUS_MAP.get(raw_status, obs_value.lower())
            
            elif obs_code in ['ALARM', 'ALARMTYPE', 'ALERT'] or 'ALARM' in obs_name:
                infusion_data['alarm_type'] = obs_value
            
            elif obs_code in ['ALARMMSG', 'ALERTMSG'] or 'MESSAGE' in obs_name:
                infusion_data['alarm_message'] = obs_value
            
            elif obs_code in ['DRUGNAME', 'MEDICATION', 'MED'] or 'DRUG' in obs_name or 'MED' in obs_name:
                if not infusion_data['medication_name']:
                    infusion_data['medication_name'] = obs_value
            
            elif obs_code in ['DEVICEID', 'PUMPID', 'DEVICE'] or 'DEVICE' in obs_name:
                infusion_data['device_id'] = obs_value
        
        return infusion_data


class BbraunHL7Listener:
    """B.Braun HL7 Infusion Pump Message Listener using MLLP protocol"""
    
    def __init__(self, host: str = HOST, port: int = PORT):
        self.host = host
        self.port = port
        self.logger = HL7Logger()
        self.parser = BbraunHL7Parser(self.logger)
        self.db = DatabaseManager(self.logger)
        self.running = False
        self.server_socket = None
        self.message_count = 0
    
    def create_ack(self, parsed_msg: dict, ack_code: str = 'AA') -> bytes:
        """Create HL7 ACK (Acknowledgment) message"""
        timestamp = datetime.now().strftime('%Y%m%d%H%M%S')
        
        msh = parsed_msg.get('msh', {})
        sending_app = msh.get('receiving_application', 'SMARTWARD') or 'SMARTWARD'
        sending_fac = msh.get('receiving_facility', 'SMARTWARD') or 'SMARTWARD'
        receiving_app = msh.get('sending_application', '')
        receiving_fac = msh.get('sending_facility', '')
        msg_control_id = msh.get('message_control_id', '')
        version = msh.get('version', '2.5')
        
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
        start_time = datetime.now()
        
        self.logger.info(f"{'='*60}")
        self.logger.info(f"B.BRAUN MESSAGE #{self.message_count} RECEIVED")
        self.logger.info(f"From: {client_address[0]}:{client_address[1]}")
        self.logger.info(f"Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        self.logger.info(f"{'='*60}")
        
        # Log raw message
        self.logger.log_raw_message(raw_message)
        self.logger.debug(f"Raw Message:\n{raw_message}")
        
        # Parse message
        parsed = self.parser.parse(raw_message)
        
        # Log parsed information
        self.log_parsed_message(parsed)
        
        # Store in database
        error_msg = parsed.get('error')
        status = 'error' if error_msg else 'received'
        self.db.log_message(parsed, raw_message, client_address[0], status, error_msg)
        
        # Calculate processing time
        processing_time = (datetime.now() - start_time).total_seconds() * 1000
        self.logger.info(f"Processing time: {processing_time:.2f}ms")
        
        # Send acknowledgment
        try:
            ack_code = 'AA' if not error_msg else 'AE'
            ack = self.create_ack(parsed, ack_code)
            client_socket.send(ack)
            self.logger.info(f"ACK ({ack_code}) sent successfully")
        except Exception as e:
            self.logger.error(f"Failed to send ACK: {str(e)}")
    
    def log_parsed_message(self, parsed: dict):
        """Log parsed message details"""
        msh = parsed.get('msh', {})
        pid = parsed.get('pid', {})
        pv1 = parsed.get('pv1', {})
        infusion = parsed.get('infusion_data', {})
        
        self.logger.info(f"\n{'-'*40}")
        self.logger.info(f"MESSAGE DETAILS")
        self.logger.info(f"{'-'*40}")
        self.logger.info(f"Message Type     : {msh.get('message_type', 'N/A')}")
        self.logger.info(f"Event            : {msh.get('event_type', 'N/A')}")
        self.logger.info(f"Control ID       : {msh.get('message_control_id', 'N/A')}")
        self.logger.info(f"HL7 Version      : {msh.get('version', 'N/A')}")
        
        self.logger.info(f"\n{'-'*40}")
        self.logger.info(f"DEVICE INFORMATION")
        self.logger.info(f"{'-'*40}")
        self.logger.info(f"Sending App      : {msh.get('sending_application', 'N/A')}")
        self.logger.info(f"Sending Facility : {msh.get('sending_facility', 'N/A')}")
        self.logger.info(f"Device ID        : {infusion.get('device_id', 'N/A')}")
        
        if pid.get('mrn'):
            self.logger.info(f"\n{'-'*40}")
            self.logger.info(f"PATIENT INFORMATION")
            self.logger.info(f"{'-'*40}")
            self.logger.info(f"MRN              : {pid.get('mrn', 'N/A')}")
            self.logger.info(f"Name             : {pid.get('name', 'N/A')}")
        
        if pv1.get('ward'):
            self.logger.info(f"\n{'-'*40}")
            self.logger.info(f"LOCATION")
            self.logger.info(f"{'-'*40}")
            self.logger.info(f"Ward             : {pv1.get('ward', 'N/A')}")
            self.logger.info(f"Room             : {pv1.get('room', 'N/A')}")
            self.logger.info(f"Bed              : {pv1.get('bed', 'N/A')}")
        
        self.logger.info(f"\n{'-'*40}")
        self.logger.info(f"INFUSION DATA")
        self.logger.info(f"{'-'*40}")
        self.logger.info(f"Medication       : {infusion.get('medication_name', 'N/A')}")
        self.logger.info(f"Flow Rate        : {infusion.get('flow_rate', 'N/A')} ml/hr")
        self.logger.info(f"Total Volume     : {infusion.get('total_volume', 'N/A')} ml")
        self.logger.info(f"Infused Volume   : {infusion.get('infused_volume', 'N/A')} ml")
        self.logger.info(f"Remaining Volume : {infusion.get('remaining_volume', 'N/A')} ml")
        self.logger.info(f"Remaining Time   : {infusion.get('remaining_minutes', 'N/A')} min")
        self.logger.info(f"Status           : {infusion.get('pump_status', 'N/A')}")
        
        if infusion.get('alarm_type'):
            self.logger.info(f"\n{'-'*40}")
            self.logger.info(f"ALARM")
            self.logger.info(f"{'-'*40}")
            self.logger.info(f"Type             : {infusion.get('alarm_type', 'N/A')}")
            self.logger.info(f"Message          : {infusion.get('alarm_message', 'N/A')}")
        
        self.logger.info(f"\n{'-'*40}\n")
    
    def start(self):
        """Start the HL7 listener"""
        self.running = True
        
        # Setup signal handlers for graceful shutdown
        signal.signal(signal.SIGINT, self.shutdown)
        # SIGTERM may not be available on Windows
        try:
            signal.signal(signal.SIGTERM, self.shutdown)
        except (AttributeError, OSError):
            pass
        
        try:
            self.server_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            self.server_socket.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
            self.server_socket.bind((self.host, self.port))
            self.server_socket.listen(5)
            self.server_socket.settimeout(1.0)
            
            self.logger.info(f"{'='*60}")
            self.logger.info(f"B.BRAUN HL7 INFUSION PUMP LISTENER STARTED")
            self.logger.info(f"{'='*60}")
            self.logger.info(f"Host: {self.host}")
            self.logger.info(f"Port: {self.port}")
            self.logger.info(f"Protocol: MLLP (Minimal Lower Layer Protocol)")
            self.logger.info(f"Database: {DB_NAME}@{DB_HOST}:{DB_PORT}")
            self.logger.info(f"Listening for B.Braun infusion pump messages...")
            self.logger.info(f"Press Ctrl+C to stop")
            self.logger.info(f"{'='*60}")
            if DEV_MODE and DEV_ASSIGN_MRN:
                self.logger.info(f"*** DEVELOPMENT MODE ***")
                self.logger.info(f"All infusion data will be assigned to MRN: {DEV_ASSIGN_MRN}")
                self.logger.info(f"{'='*60}")
            self.logger.info("")
            
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
                except OSError as e:
                    if self.running:
                        self.logger.error(f"Socket error: {str(e)}")
                    break
                except KeyboardInterrupt:
                    self.logger.info("\nCtrl+C detected, shutting down...")
                    break
                    
        except KeyboardInterrupt:
            self.logger.info("\nCtrl+C detected, shutting down...")
        except Exception as e:
            self.logger.error(f"Server error: {str(e)}")
        finally:
            self.running = False
            self.cleanup()
    
    def shutdown(self, signum=None, frame=None):
        """Handle shutdown signal"""
        if self.running:
            self.logger.info("\nShutdown signal received...")
            self.running = False
            # Close socket to unblock accept()
            if self.server_socket:
                try:
                    self.server_socket.close()
                except:
                    pass
    
    def cleanup(self):
        """Cleanup resources"""
        # Close socket if still open
        if self.server_socket:
            try:
                self.server_socket.close()
            except:
                pass
            self.server_socket = None
        
        # Close database connection
        if self.db:
            self.db.close()
        
        self.logger.info(f"\n{'='*60}")
        self.logger.info(f"B.BRAUN HL7 LISTENER STOPPED")
        self.logger.info(f"Total messages processed: {self.message_count}")
        self.logger.info(f"{'='*60}")


def main():
    """Main entry point"""
    print("""
    ╔═══════════════════════════════════════════════════════════╗
    ║       B.Braun HL7 Infusion Pump Listener v1.0             ║
    ║           SmartWard Healthcare Integration                ║
    ╠═══════════════════════════════════════════════════════════╣
    ║  Protocol: MLLP over TCP/IP (HL7 v2.x)                    ║
    ║  Default Port: 5001                                       ║
    ║  Supported: ORU, ORM, RAS, RDE, RGV messages              ║
    ║  Features: Real-time pump status, alarms, volume tracking ║
    ╚═══════════════════════════════════════════════════════════╝
    """)
    
    # Get configuration from environment or use defaults
    host = os.getenv('BBRAUN_HOST', '0.0.0.0')
    port = int(os.getenv('BBRAUN_PORT', '5001'))
    
    listener = BbraunHL7Listener(host=host, port=port)
    
    try:
        listener.start()
    except KeyboardInterrupt:
        print("\n")
        listener.shutdown()


if __name__ == '__main__':
    try:
        main()
    except KeyboardInterrupt:
        print("\nExiting...")
        sys.exit(0)
