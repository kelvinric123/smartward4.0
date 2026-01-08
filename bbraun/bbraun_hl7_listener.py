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
import time
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
    'PUMP-STATUS-INFUSING': 'running',
    'PAUSE': 'paused',
    'PAUSED': 'paused',
    'STOP': 'stopped',
    'STOPPED': 'stopped',
    'PUMP-STATUS-NOT-INFUSING': 'stopped',
    'COMPLETE': 'completed',
    'COMPLETED': 'completed',
    'ALARM': 'alarming',
    'ALARMING': 'alarming',
    'PENDING': 'pending',
    'IDLE': 'pending',
}

# MDC (Medical Device Communication) Code Mappings
# Reference: IEEE 11073-10101 Medical Device Communication nomenclature
MDC_CODES = {
    # Pump Status & Delivery
    '184519': 'pump_status',           # MDC_PUMP_INFUSING_STATUS
    '158014': 'flow_rate_current',     # MDC_FLOW_FLUID_PUMP_CURRENT (mL/h)
    '157784': 'flow_rate_programmed',  # MDC_FLOW_FLUID_PUMP
    '158005': 'delivery_status',       # MDC_DEV_PUMP_CURRENT_DELIVERY_STATUS
    '158006': 'not_delivering_reason', # MDC_DEV_PUMP_NOT_DELIVERING_REASON
    '158008': 'delivery_mode',         # MDC_DEV_PUMP_PROGRAM_DELIVERY_MODE
    
    # Volume Data
    '157884': 'total_volume',          # MDC_VOL_FLUID_TBI (Total Volume to Be Infused)
    '157872': 'remaining_volume',      # MDC_VOL_FLUID_TBI_REMAIN
    '157993': 'infused_volume',        # MDC_VOL_FLUID_DELIV_TOTAL
    '157992': 'segment_volume',        # MDC_VOL_FLUID_DELIV_SEGMENT
    
    # Time Data
    '157996': 'programmed_time',       # MDC_TIME_PD_PROG (seconds)
    '157916': 'remaining_time',        # MDC_TIME_PD_REMAIN (seconds)
    '157997': 'remaining_time',        # MDC_TIME_PD_REMAIN_CONTAINER (seconds) - syringe remaining time
    
    # Drug/Medication
    '184514': 'drug_name',             # MDC_DRUG_NAME_LABEL
    '157760': 'drug_concentration',    # MDC_CONC_DRUG (mg/mL)
    '184520': 'drug_library_name',     # MDC_PUMP_DRUG_LIBRARY_NAME
    '184516': 'care_area',             # MDC_PUMP_DRUG_LIBRARY_CARE_AREA
    
    # Dose Data
    '157999': 'dose_tbi',              # MDC_DOSE_DRUG_TBI (Total Dose to Be Infused)
    '158000': 'dose_remaining',        # MDC_DOSE_DRUG_TBI_REMAIN
    '158001': 'dose_delivered',        # MDC_DOSE_DRUG_DELIV_TOTAL
    
    # Device Information
    '67880': 'pump_model',             # MDC_ATTR_ID_MODEL
    '67972': 'device_uuid',            # MDC_ATTR_SYS_ID
    '531976': 'firmware_version',      # MDC_ID_PROD_SPEC_FW
    
    # Syringe Data
    '157880': 'syringe_size',          # MDC_VOL_SYRINGE
    '157984': 'syringe_actual_vol',    # MDC_VOL_SYRINGE_ACTUAL
    '184488': 'syringe_manufacturer',  # MDC_SYRINGE_MANUFACTURER
    
    # Power & Battery
    '67925': 'power_status',           # MDC_ATTR_POWER_STAT (onBattery/onMains)
    '67996': 'battery_percent',        # MDC_ATTR_VAL_BATT_CHARGE
    '67976': 'battery_time_remaining', # MDC_ATTR_TIME_BATT_REMAIN (minutes)
    '68020': 'battery_status',         # MDC_ATTR_BATT_STAT
    '68023': 'battery_capacity',       # MDC_ATTR_CAPAC_BATT_FULL
    
    # Network/WiFi
    '69408': 'wifi_state',             # MDC_NCC_WIRELESS_STATE
    '69410': 'device_ip',              # MDC_NCC_WIRELESS_DEVICE_IPV4_ADDR
    '69416': 'device_mac',             # MDC_NCC_WIRELESS_MAC
    '69417': 'wifi_ssid',              # MDC_NCC_WIRELESS_SSID
    '69425': 'wifi_strength',          # MDC_NCC_WIRELESS_STRENGTH_PERCENT
    
    # Alarm/Alert Data
    '196616': 'alarm_event',           # MDC_EVT_ALARM
    '68012': 'alarm_condition',        # MDC_ATTR_AL_COND
    '68480': 'alert_source',           # MDC_ATTR_ALERT_SOURCE
    '68481': 'event_phase',            # MDC_ATTR_EVENT_PHASE (start/update/end)
    '68482': 'alarm_state',            # MDC_ATTR_ALARM_STATE (active/inactive)
    '68483': 'alarm_inactivation',     # MDC_ATTR_ALARM_INACTIVATION_STATE
    '68484': 'alarm_priority',         # MDC_ATTR_ALARM_PRIORITY (PH/PM/PL/ST)
    '68485': 'alert_type',             # MDC_ATTR_ALERT_TYPE
    '68546': 'alert_text',             # MDC_ATTR_ALERT_TEXT
    
    # Events
    '68487': 'event_condition',        # MDC_ATTR_EVT_COND
    '68488': 'event_source',           # MDC_ATTR_EVT_SOURCE
    
    # Patient Data
    '68063': 'patient_weight',         # MDC_ATTR_PT_WEIGHT (kg)
}

# MDC Event Codes
MDC_EVENTS = {
    '197288': 'delivery_start',        # MDC_EVT_PUMP_DELIV_START
    '197292': 'delivery_complete',     # MDC_EVT_PUMP_DELIV_COMP
    '197334': 'near_completion',       # MDC_EVT_VOL_INFUS_NEAR_COMP
    '197218': 'syringe_barrel_fault',  # MDC_EVT_SYRINGE_BARREL_CAPTURE_FAULT
}

# Alarm Priority Mapping
ALARM_PRIORITY_MAP = {
    'PH': 'high',       # Physiological High
    'PM': 'medium',     # Physiological Medium
    'PL': 'low',        # Physiological Low
    'ST': 'technical',  # Technical/System
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
        self.connect(retries=5)
    
    def connect(self, retries: int = 1):
        """Connect to the database"""
        attempt = 0
        while attempt < retries:
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
                return
            except Exception as e:
                attempt += 1
                if attempt < retries:
                    self.logger.warning(f"Could not connect to MySQL (Attempt {attempt}/{retries}): {str(e)}")
                    time.sleep(2)
                else:
                    self.logger.warning(f"Could not connect to MySQL after {retries} attempts: {str(e)}")
                    self.logger.info("Messages will be logged to files only until database becomes available")
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
        self.ensure_connection()
        
        if self.connection is None:
            return None
        
        try:
            cursor = self.connection.cursor()
            
            msh = parsed_data.get('msh', {})
            pid = parsed_data.get('pid', {})
            pv1 = parsed_data.get('pv1', {})
            
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
                    patient_mrn, patient_name, ward, room, bed,
                    device_id, device_uuid, pump_model, medication_name,
                    flow_rate, total_volume, infused_volume, remaining_volume,
                    remaining_minutes, drug_concentration, dose_rate, dose_unit,
                    syringe_size, delivery_mode,
                    pump_status, alarm_type, alarm_message, alarm_priority, alarm_state,
                    power_status, battery_percent, battery_minutes_remaining,
                    wifi_strength, device_ip,
                    raw_message, parsed_data,
                    source_ip, status, error_message,
                    created_at, updated_at
                ) VALUES (
                    %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,
                    %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,
                    %s, %s, %s, %s, %s, %s, %s, %s, %s, %s,
                    %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s
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
                pv1.get('ward', ''),
                pv1.get('room', ''),
                pv1.get('bed', ''),
                infusion_data.get('device_id', ''),
                infusion_data.get('device_uuid', ''),
                infusion_data.get('pump_model', ''),
                infusion_data.get('medication_name', ''),
                infusion_data.get('flow_rate'),
                infusion_data.get('total_volume'),
                infusion_data.get('infused_volume'),
                infusion_data.get('remaining_volume'),
                infusion_data.get('remaining_minutes'),
                infusion_data.get('drug_concentration'),
                infusion_data.get('dose_total'),
                infusion_data.get('dose_unit', ''),
                infusion_data.get('syringe_size'),
                infusion_data.get('delivery_mode', ''),
                infusion_data.get('pump_status', ''),
                infusion_data.get('alarm_type', ''),
                infusion_data.get('alarm_message', ''),
                infusion_data.get('alarm_priority', ''),
                infusion_data.get('alarm_state', ''),
                infusion_data.get('power_status', ''),
                infusion_data.get('battery_percent'),
                infusion_data.get('battery_minutes_remaining'),
                infusion_data.get('wifi_strength'),
                infusion_data.get('device_ip', ''),
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
            
            # Also update the infusions table if patient exists
            self._update_infusion_record(parsed_data, patient_mrn)
            
            return log_id
            
        except Exception as e:
            self.logger.error(f"Error logging message to database: {str(e)}")
            return None
    
    def _update_infusion_record(self, parsed_data: dict, patient_mrn: str) -> Optional[int]:
        """Update or create infusion record in the infusions table"""
        if not self.connection:
            return None
        
        try:
            cursor = self.connection.cursor(dictionary=True)
            infusion_data = parsed_data.get('infusion_data', {})
            device_id = infusion_data.get('device_id', '')
            
            # Skip if no meaningful infusion data
            if not infusion_data.get('flow_rate') and not infusion_data.get('pump_status'):
                return None
            
            patient_id = None
            ward_id = None
            pump_id = None
            
            # First, check if the pump is linked to a patient (via device_id)
            if device_id:
                cursor.execute(
                    """SELECT ip.id as pump_id, ip.patient_id, ip.ward_id as pump_ward_id,
                              p.id as linked_patient_id, p.ward_id as patient_ward_id, p.name as patient_name
                       FROM infusion_pumps ip
                       LEFT JOIN patients p ON ip.patient_id = p.id
                       WHERE ip.device_id = %s
                       LIMIT 1""",
                    (device_id,)
                )
                pump_record = cursor.fetchone()
                
                if pump_record:
                    pump_id = pump_record['pump_id']
                    # Update pump last_seen and device info
                    cursor.execute(
                        """UPDATE infusion_pumps SET 
                            last_seen_at = NOW(), 
                            is_active = 1,
                            pump_model = COALESCE(%s, pump_model),
                            device_uuid = COALESCE(%s, device_uuid)
                           WHERE id = %s""",
                        (infusion_data.get('pump_model'), infusion_data.get('device_uuid'), pump_id)
                    )
                    
                    # If pump is linked to a patient, use that patient
                    if pump_record['linked_patient_id']:
                        patient_id = pump_record['linked_patient_id']
                        ward_id = pump_record['patient_ward_id']
                        self.logger.info(f"Using linked patient: {pump_record['patient_name']} (ID: {patient_id}) for pump {device_id}")
                    else:
                        ward_id = pump_record['pump_ward_id']
                else:
                    # Pump not found, will create later if we have a patient
                    pass
            
            # If no patient from pump link, try to find by MRN (if it's a valid MRN)
            if not patient_id and patient_mrn and patient_mrn.lower() not in ['unknown patient', 'unknown', '']:
                cursor.execute("SELECT id, ward_id FROM patients WHERE mrn = %s LIMIT 1", (patient_mrn,))
                patient = cursor.fetchone()
                if patient:
                    patient_id = patient['id']
                    ward_id = patient['ward_id']
                    self.logger.info(f"Found patient by MRN: {patient_mrn} (ID: {patient_id})")
            
            # If still no patient, we can't create an infusion record
            if not patient_id:
                if device_id and not pump_id:
                    # At least create/update the pump record so it can be linked later
                    cursor.execute(
                        """INSERT INTO infusion_pumps (device_id, device_name, device_type, pump_model, device_uuid, is_active, last_seen_at, created_at, updated_at)
                           VALUES (%s, %s, %s, %s, %s, 1, NOW(), NOW(), NOW())
                           ON DUPLICATE KEY UPDATE 
                               last_seen_at = NOW(), 
                               is_active = 1,
                               pump_model = COALESCE(VALUES(pump_model), pump_model),
                               device_uuid = COALESCE(VALUES(device_uuid), device_uuid)""",
                        (device_id, infusion_data.get('pump_model', device_id), 'B.Braun Syringe Pump', 
                         infusion_data.get('pump_model'), infusion_data.get('device_uuid'))
                    )
                    self.logger.info(f"Pump {device_id} registered/updated, waiting to be linked to a patient")
                else:
                    self.logger.debug(f"No linked patient for pump {device_id} and MRN '{patient_mrn}' not found")
                cursor.close()
                return None
            
            # Create pump if it doesn't exist
            if not pump_id and device_id:
                cursor.execute(
                    """INSERT INTO infusion_pumps (device_id, device_name, device_type, pump_model, device_uuid, ward_id, is_active, last_seen_at, created_at, updated_at)
                       VALUES (%s, %s, %s, %s, %s, %s, 1, NOW(), NOW(), NOW())""",
                    (device_id, infusion_data.get('pump_model', device_id), 'B.Braun Syringe Pump',
                     infusion_data.get('pump_model'), infusion_data.get('device_uuid'), ward_id)
                )
                pump_id = cursor.lastrowid
            
            # Find existing active infusion for this patient/pump/medication
            medication_name = infusion_data.get('medication_name', 'Unknown')
            cursor.execute(
                """SELECT id FROM infusions 
                   WHERE patient_id = %s 
                   AND infusion_pump_id = %s 
                   AND medication_name = %s 
                   AND status IN ('pending', 'running', 'paused', 'alarming')
                   ORDER BY created_at DESC LIMIT 1""",
                (patient_id, pump_id, medication_name)
            )
            existing = cursor.fetchone()
            
            # Map pump_status
            pump_status = infusion_data.get('pump_status', '')
            if not pump_status:
                pump_status = 'running' if infusion_data.get('flow_rate', 0) > 0 else 'pending'
            
            # Calculate warning status (< 15 minutes remaining)
            remaining_minutes = infusion_data.get('remaining_minutes')
            is_warning = remaining_minutes is not None and remaining_minutes <= 15 and pump_status == 'running'
            
            now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            
            if existing:
                # Update existing infusion
                cursor.execute(
                    """UPDATE infusions SET
                        flow_rate = COALESCE(%s, flow_rate),
                        total_volume = COALESCE(%s, total_volume),
                        infused_volume = COALESCE(%s, infused_volume),
                        remaining_volume = COALESCE(%s, remaining_volume),
                        remaining_minutes = COALESCE(%s, remaining_minutes),
                        dose_rate = COALESCE(%s, dose_rate),
                        dose_unit = COALESCE(%s, dose_unit),
                        status = %s,
                        delivery_mode = COALESCE(%s, delivery_mode),
                        syringe_size = COALESCE(%s, syringe_size),
                        syringe_actual_volume = COALESCE(%s, syringe_actual_volume),
                        syringe_manufacturer = COALESCE(%s, syringe_manufacturer),
                        alarm_type = COALESCE(%s, alarm_type),
                        alarm_message = COALESCE(%s, alarm_message),
                        is_warning = %s,
                        last_updated_at = %s,
                        updated_at = %s
                    WHERE id = %s""",
                    (
                        infusion_data.get('flow_rate'),
                        infusion_data.get('total_volume'),
                        infusion_data.get('infused_volume'),
                        infusion_data.get('remaining_volume'),
                        remaining_minutes,
                        infusion_data.get('dose_total'),
                        infusion_data.get('dose_unit', ''),
                        pump_status,
                        infusion_data.get('delivery_mode', ''),
                        infusion_data.get('syringe_size'),
                        infusion_data.get('syringe_actual_vol'),
                        infusion_data.get('syringe_manufacturer', ''),
                        infusion_data.get('alarm_type', ''),
                        infusion_data.get('alarm_message', ''),
                        is_warning,
                        now,
                        now,
                        existing['id']
                    )
                )
                
                # Mark as completed if status is completed
                if pump_status == 'completed':
                    cursor.execute(
                        "UPDATE infusions SET completed_at = %s WHERE id = %s AND completed_at IS NULL",
                        (now, existing['id'])
                    )
                
                self.logger.info(f"Updated infusion record ID: {existing['id']}")
                cursor.close()
                return existing['id']
            else:
                # Create new infusion
                cursor.execute(
                    """INSERT INTO infusions (
                        patient_id, infusion_pump_id, medication_name, medication_code,
                        total_volume, infused_volume, remaining_volume, flow_rate,
                        dose_rate, dose_unit, remaining_minutes,
                        status, delivery_mode, syringe_size, syringe_actual_volume, syringe_manufacturer,
                        alarm_type, alarm_message, is_warning,
                        started_at, last_updated_at, created_at, updated_at
                    ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)""",
                    (
                        patient_id,
                        pump_id,
                        medication_name,
                        infusion_data.get('medication_code', ''),
                        infusion_data.get('total_volume'),
                        infusion_data.get('infused_volume', 0),
                        infusion_data.get('remaining_volume'),
                        infusion_data.get('flow_rate'),
                        infusion_data.get('dose_total'),
                        infusion_data.get('dose_unit', ''),
                        remaining_minutes,
                        pump_status,
                        infusion_data.get('delivery_mode', ''),
                        infusion_data.get('syringe_size'),
                        infusion_data.get('syringe_actual_vol'),
                        infusion_data.get('syringe_manufacturer', ''),
                        infusion_data.get('alarm_type', ''),
                        infusion_data.get('alarm_message', ''),
                        is_warning,
                        now if pump_status == 'running' else None,
                        now,
                        now,
                        now
                    )
                )
                infusion_id = cursor.lastrowid
                self.logger.info(f"Created new infusion record ID: {infusion_id}")
                cursor.close()
                return infusion_id
                
        except Exception as e:
            self.logger.error(f"Error updating infusion record: {str(e)}")
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
            'orc': {},
            'obr': {},
            'obx': [],
            'rxe': {},
            'rxg': {},
            'rxr': {},
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
            result['rxg'] = self._parse_rxg(result['segments'].get('RXG', []))
            
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
    
    def _parse_rxg(self, rxg: list) -> dict:
        """Parse RXG (Pharmacy/Treatment Give) segment - from RGV^O15 messages"""
        if not rxg:
            return {}
        
        # RXG|1|||0002^Sample Test Drug|150||mL^mL^UCUM^263762^MDC_DIM_MILLI_L^MDC||||||||15|mg/h^...
        give_code = self._get_field(rxg, 4)
        give_parts = give_code.split('^') if give_code else []
        
        return {
            'give_sub_id': self._get_field(rxg, 1),
            'dispense_sub_id': self._get_field(rxg, 2),
            'quantity_timing': self._get_field(rxg, 3),
            'give_code': give_code,
            'medication_code': give_parts[0] if give_parts else '',
            'medication_name': give_parts[1] if len(give_parts) > 1 else '',
            'give_amount': self._get_field(rxg, 5),
            'give_units': self._get_field(rxg, 7),
            'give_dosage_form': self._get_field(rxg, 8),
            'give_rate_amount': self._get_field(rxg, 15),
            'give_rate_units': self._get_field(rxg, 16),
            'give_strength': self._get_field(rxg, 17),
            'give_strength_units': self._get_field(rxg, 18),
            'substance_lot_number': self._get_field(rxg, 19),
            'substance_expiration': self._get_field(rxg, 20),
            'vtbi': self._get_field(rxg, 23),
            'vtbi_units': self._get_field(rxg, 24),
        }
    
    def _extract_infusion_data(self, parsed: dict) -> dict:
        """Extract infusion-specific data from parsed segments using MDC codes"""
        infusion_data = {
            # Device Info
            'device_id': '',
            'device_uuid': '',
            'pump_model': '',
            'firmware_version': '',
            
            # Medication
            'medication_name': '',
            'medication_code': '',
            'drug_concentration': None,
            'drug_concentration_unit': '',
            'care_area': '',
            
            # Flow & Volume
            'flow_rate': None,
            'flow_rate_programmed': None,
            'total_volume': None,
            'infused_volume': None,
            'remaining_volume': None,
            
            # Time
            'programmed_minutes': None,
            'remaining_minutes': None,
            
            # Dose
            'dose_total': None,
            'dose_remaining': None,
            'dose_delivered': None,
            'dose_unit': '',
            
            # Status
            'pump_status': '',
            'delivery_status': '',
            'delivery_mode': '',
            'not_delivering_reason': '',
            
            # Syringe
            'syringe_size': None,
            'syringe_actual_vol': None,
            'syringe_manufacturer': '',
            
            # Power & Battery
            'power_status': '',
            'battery_percent': None,
            'battery_minutes_remaining': None,
            'battery_status': '',
            
            # Network
            'wifi_state': '',
            'wifi_strength': None,
            'device_ip': '',
            
            # Alarm
            'alarm_type': '',
            'alarm_message': '',
            'alarm_priority': '',
            'alarm_state': '',
            'event_phase': '',
            
            # Patient
            'patient_weight': None,
        }
        
        msh = parsed.get('msh', {})
        rxe = parsed.get('rxe', {})
        rxg = parsed.get('rxg', {})
        obr = parsed.get('obr', {})
        obx_list = parsed.get('obx', [])
        
        # Get device ID from sending application (e.g., PAT_DEVICE_BBRAUN^0012211839000001^EUI-64)
        sending_app = msh.get('sending_application', '')
        if sending_app:
            # Extract EUI-64 device identifier if present
            if '^' in sending_app:
                parts = sending_app.split('^')
                if len(parts) >= 2:
                    infusion_data['device_id'] = parts[1]  # Use the EUI-64 portion
                else:
                    infusion_data['device_id'] = sending_app
            else:
                infusion_data['device_id'] = sending_app
        
        # Get medication info from OBR (observation request - drug name in component 4)
        if obr:
            universal_service = obr.get('universal_service_id', '')
            if universal_service and '^' in universal_service:
                parts = universal_service.split('^')
                if parts[0] and not parts[0].isdigit():
                    infusion_data['medication_name'] = parts[0]
        
        # Get medication info from RXE/RXG
        if rxe:
            if not infusion_data['medication_name']:
                infusion_data['medication_name'] = rxe.get('medication_name', '')
            infusion_data['medication_code'] = rxe.get('medication_code', '')
            
            rate_str = rxe.get('give_rate_amount', '')
            if rate_str:
                try:
                    infusion_data['flow_rate'] = float(rate_str)
                except:
                    pass
        
        if rxg:
            if not infusion_data['medication_name']:
                infusion_data['medication_name'] = rxg.get('medication_name', '')
            if rxg.get('give_amount'):
                try:
                    infusion_data['total_volume'] = float(rxg.get('give_amount'))
                except:
                    pass
        
        # Extract data from OBX segments using MDC codes
        for obx in obx_list:
            obs_id = obx.get('observation_id', '')
            obs_code = obx.get('observation_code', '')
            obs_name = obx.get('observation_name', '').upper()
            obs_value = obx.get('observation_value', '')
            units = obx.get('units', '')
            
            # Skip empty values
            if not obs_value:
                continue
            
            # Look up MDC code mapping
            mdc_field = MDC_CODES.get(obs_code, '')
            
            if mdc_field:
                # Handle each MDC field type
                if mdc_field == 'pump_status':
                    # Clean up status value: strip leading ^, convert to uppercase, replace remaining ^ with -
                    raw_status = obs_value.upper().lstrip('^').replace('^', '-')
                    infusion_data['pump_status'] = INFUSION_STATUS_MAP.get(raw_status, obs_value.lower().lstrip('^'))
                
                elif mdc_field == 'flow_rate_current':
                    try:
                        infusion_data['flow_rate'] = float(obs_value)
                    except:
                        pass
                
                elif mdc_field == 'flow_rate_programmed':
                    try:
                        infusion_data['flow_rate_programmed'] = float(obs_value)
                    except:
                        pass
                
                elif mdc_field == 'total_volume':
                    try:
                        infusion_data['total_volume'] = float(obs_value)
                    except:
                        pass
                
                elif mdc_field == 'remaining_volume':
                    try:
                        infusion_data['remaining_volume'] = float(obs_value)
                    except:
                        pass
                
                elif mdc_field == 'infused_volume':
                    try:
                        infusion_data['infused_volume'] = float(obs_value)
                    except:
                        pass
                
                elif mdc_field == 'remaining_time':
                    try:
                        # Convert seconds to minutes
                        seconds = int(obs_value)
                        infusion_data['remaining_minutes'] = int(seconds / 60)
                    except:
                        pass
                
                elif mdc_field == 'programmed_time':
                    try:
                        seconds = int(obs_value)
                        infusion_data['programmed_minutes'] = int(seconds / 60)
                    except:
                        pass
                
                elif mdc_field == 'drug_name':
                    infusion_data['medication_name'] = obs_value
                
                elif mdc_field == 'drug_concentration':
                    try:
                        infusion_data['drug_concentration'] = float(obs_value)
                        # Extract unit from units field
                        if units and '^' in units:
                            unit_parts = units.split('^')
                            infusion_data['drug_concentration_unit'] = unit_parts[3] if len(unit_parts) > 3 else ''
                    except:
                        pass
                
                elif mdc_field == 'care_area':
                    infusion_data['care_area'] = obs_value
                
                elif mdc_field == 'dose_tbi':
                    try:
                        infusion_data['dose_total'] = float(obs_value)
                        if units and '^' in units:
                            unit_parts = units.split('^')
                            infusion_data['dose_unit'] = unit_parts[3] if len(unit_parts) > 3 else ''
                    except:
                        pass
                
                elif mdc_field == 'dose_remaining':
                    try:
                        infusion_data['dose_remaining'] = float(obs_value)
                    except:
                        pass
                
                elif mdc_field == 'dose_delivered':
                    try:
                        infusion_data['dose_delivered'] = float(obs_value)
                    except:
                        pass
                
                elif mdc_field == 'pump_model':
                    infusion_data['pump_model'] = obs_value
                
                elif mdc_field == 'device_uuid':
                    infusion_data['device_uuid'] = obs_value
                
                elif mdc_field == 'firmware_version':
                    infusion_data['firmware_version'] = obs_value
                
                elif mdc_field == 'syringe_size':
                    try:
                        infusion_data['syringe_size'] = float(obs_value)
                    except:
                        pass
                
                elif mdc_field == 'syringe_actual_vol':
                    try:
                        infusion_data['syringe_actual_vol'] = float(obs_value)
                    except:
                        pass
                
                elif mdc_field == 'syringe_manufacturer':
                    infusion_data['syringe_manufacturer'] = obs_value
                
                elif mdc_field == 'power_status':
                    # Parse onBattery(1) or onMains(0)
                    if 'battery' in obs_value.lower():
                        infusion_data['power_status'] = 'battery'
                    elif 'mains' in obs_value.lower():
                        infusion_data['power_status'] = 'mains'
                    else:
                        infusion_data['power_status'] = obs_value
                
                elif mdc_field == 'battery_percent':
                    try:
                        infusion_data['battery_percent'] = int(float(obs_value))
                    except:
                        pass
                
                elif mdc_field == 'battery_time_remaining':
                    try:
                        infusion_data['battery_minutes_remaining'] = int(float(obs_value))
                    except:
                        pass
                
                elif mdc_field == 'battery_status':
                    infusion_data['battery_status'] = obs_value
                
                elif mdc_field == 'wifi_state':
                    infusion_data['wifi_state'] = obs_value
                
                elif mdc_field == 'wifi_strength':
                    try:
                        infusion_data['wifi_strength'] = int(float(obs_value))
                    except:
                        pass
                
                elif mdc_field == 'device_ip':
                    infusion_data['device_ip'] = obs_value
                
                elif mdc_field == 'delivery_status':
                    status_val = obs_value.lower().replace('^', '-')
                    if 'delivering' in status_val and 'not' not in status_val:
                        infusion_data['delivery_status'] = 'delivering'
                    elif 'not-delivering' in status_val:
                        infusion_data['delivery_status'] = 'not_delivering'
                    else:
                        infusion_data['delivery_status'] = status_val
                
                elif mdc_field == 'delivery_mode':
                    mode_val = obs_value.lower().replace('^', '-')
                    if 'continuous' in mode_val:
                        infusion_data['delivery_mode'] = 'continuous'
                    elif 'bolus' in mode_val:
                        infusion_data['delivery_mode'] = 'bolus'
                    elif 'intermittent' in mode_val:
                        infusion_data['delivery_mode'] = 'intermittent'
                    else:
                        infusion_data['delivery_mode'] = mode_val
                
                elif mdc_field == 'not_delivering_reason':
                    reason_val = obs_value.lower().replace('^', '-')
                    infusion_data['not_delivering_reason'] = reason_val
                
                elif mdc_field == 'alarm_state':
                    infusion_data['alarm_state'] = obs_value.lower()
                
                elif mdc_field == 'alarm_priority':
                    infusion_data['alarm_priority'] = ALARM_PRIORITY_MAP.get(obs_value, obs_value.lower())
                
                elif mdc_field == 'alert_text':
                    infusion_data['alarm_message'] = obs_value
                
                elif mdc_field == 'alert_type':
                    infusion_data['alarm_type'] = obs_value
                
                elif mdc_field == 'event_phase':
                    infusion_data['event_phase'] = obs_value.lower()
                
                elif mdc_field == 'alarm_condition':
                    # Parse alarm condition code
                    if '^' in obs_value:
                        parts = obs_value.split('^')
                        event_code = parts[0]
                        event_name = MDC_EVENTS.get(event_code, parts[1] if len(parts) > 1 else '')
                        if event_name:
                            infusion_data['alarm_type'] = event_name
                
                elif mdc_field == 'event_condition':
                    # Parse event type (delivery start, complete, etc.)
                    if '^' in obs_value:
                        parts = obs_value.split('^')
                        event_code = parts[0]
                        event_name = MDC_EVENTS.get(event_code, '')
                        if event_name == 'delivery_start':
                            infusion_data['pump_status'] = 'running'
                        elif event_name == 'delivery_complete':
                            infusion_data['pump_status'] = 'completed'
                
                elif mdc_field == 'patient_weight':
                    try:
                        infusion_data['patient_weight'] = float(obs_value)
                    except:
                        pass
            
            # Fallback: Legacy parsing for non-MDC codes
            else:
                obs_name_upper = obs_name.upper() if obs_name else ''
                
                if 'FLOW' in obs_name_upper and not infusion_data['flow_rate']:
                    try:
                        infusion_data['flow_rate'] = float(obs_value)
                    except:
                        pass
                
                elif 'STATUS' in obs_name_upper and not infusion_data['pump_status']:
                    # Clean up status value: strip leading ^, convert to uppercase, replace remaining ^ with -
                    raw_status = obs_value.upper().lstrip('^').replace('^', '-')
                    infusion_data['pump_status'] = INFUSION_STATUS_MAP.get(raw_status, obs_value.lower().lstrip('^'))
        
        # Derive status from alarm state if needed
        if infusion_data['alarm_state'] == 'active' and not infusion_data['pump_status']:
            infusion_data['pump_status'] = 'alarming'
        
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
        self.logger.info(f"Device ID        : {infusion.get('device_id', 'N/A')}")
        self.logger.info(f"Pump Model       : {infusion.get('pump_model', 'N/A')}")
        self.logger.info(f"Device UUID      : {infusion.get('device_uuid', 'N/A')}")
        self.logger.info(f"Firmware         : {infusion.get('firmware_version', 'N/A')}")
        
        if pid.get('mrn') and pid.get('mrn') != 'Unknown Patient':
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
        if infusion.get('drug_concentration'):
            self.logger.info(f"Concentration    : {infusion.get('drug_concentration')} {infusion.get('drug_concentration_unit', 'mg/mL')}")
        if infusion.get('care_area'):
            self.logger.info(f"Care Area        : {infusion.get('care_area')}")
        self.logger.info(f"Flow Rate        : {infusion.get('flow_rate', 'N/A')} ml/hr")
        self.logger.info(f"Total Volume     : {infusion.get('total_volume', 'N/A')} ml")
        self.logger.info(f"Infused Volume   : {infusion.get('infused_volume', 'N/A')} ml")
        self.logger.info(f"Remaining Volume : {infusion.get('remaining_volume', 'N/A')} ml")
        self.logger.info(f"Remaining Time   : {infusion.get('remaining_minutes', 'N/A')} min")
        self.logger.info(f"Status           : {infusion.get('pump_status', 'N/A')}")
        self.logger.info(f"Delivery Mode    : {infusion.get('delivery_mode', 'N/A')}")
        self.logger.info(f"Delivery Status  : {infusion.get('delivery_status', 'N/A')}")
        
        if infusion.get('syringe_size'):
            self.logger.info(f"\n{'-'*40}")
            self.logger.info(f"SYRINGE DATA")
            self.logger.info(f"{'-'*40}")
            self.logger.info(f"Syringe Size     : {infusion.get('syringe_size')} ml")
            self.logger.info(f"Actual Volume    : {infusion.get('syringe_actual_vol', 'N/A')} ml")
            self.logger.info(f"Manufacturer     : {infusion.get('syringe_manufacturer', 'N/A')}")
        
        if infusion.get('power_status') or infusion.get('battery_percent') is not None:
            self.logger.info(f"\n{'-'*40}")
            self.logger.info(f"POWER STATUS")
            self.logger.info(f"{'-'*40}")
            self.logger.info(f"Power Source     : {infusion.get('power_status', 'N/A')}")
            self.logger.info(f"Battery          : {infusion.get('battery_percent', 'N/A')}%")
            self.logger.info(f"Battery Time     : {infusion.get('battery_minutes_remaining', 'N/A')} min")
            self.logger.info(f"WiFi Strength    : {infusion.get('wifi_strength', 'N/A')}%")
            self.logger.info(f"Device IP        : {infusion.get('device_ip', 'N/A')}")
        
        if infusion.get('alarm_type') or infusion.get('alarm_message') or infusion.get('alarm_state') == 'active':
            self.logger.info(f"\n{'-'*40}")
            self.logger.info(f"ALARM/ALERT")
            self.logger.info(f"{'-'*40}")
            self.logger.info(f"State            : {infusion.get('alarm_state', 'N/A')}")
            self.logger.info(f"Priority         : {infusion.get('alarm_priority', 'N/A')}")
            self.logger.info(f"Type             : {infusion.get('alarm_type', 'N/A')}")
            self.logger.info(f"Message          : {infusion.get('alarm_message', 'N/A')}")
            self.logger.info(f"Phase            : {infusion.get('event_phase', 'N/A')}")
        
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
