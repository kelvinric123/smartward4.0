#!/usr/bin/env python3
"""
HL7 ADT Message Listener
Listens for ADT (Admit, Discharge, Transfer) messages via MLLP protocol
Parses messages and forwards to Laravel API for processing
"""

import socket
import logging
import sys
import os
import json
import requests
from datetime import datetime
from typing import Optional, Tuple, List, Dict, Any
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

# Laravel API Configuration
LARAVEL_API_URL = os.getenv('LARAVEL_API_URL', 'http://localhost:80/api/adt/message')
LARAVEL_API_KEY = os.getenv('LARAVEL_API_KEY', '')

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
        msg_type = parsed.get('msh', {}).get('message_type', 'UNKNOWN')
        event_code = parsed.get('msh', {}).get('event_type', 'UNK')
        
        # Get patient MRN for filename
        patient_mrn = parsed.get('pid', {}).get('mrn', 'NOID')
        # Clean patient MRN for filename (remove special chars)
        patient_mrn = ''.join(c for c in str(patient_mrn) if c.isalnum() or c in '-_')[:20]
        
        # Create filename
        filename = f"{timestamp}_{event_code}_{patient_mrn}_{msg_count:05d}.hl7"
        filepath = os.path.join(self.data_dir, filename)
        
        # Write message to file
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(f"# HL7 Message Received: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n")
            f.write(f"# Message Type: {msg_type}\n")
            f.write(f"# Event Type: {event_code}\n")
            f.write(f"# Patient MRN: {parsed.get('pid', {}).get('mrn', 'N/A')}\n")
            f.write(f"# Patient Name: {parsed.get('pid', {}).get('name', 'N/A')}\n")
            f.write(f"# Control ID: {parsed.get('msh', {}).get('message_control_id', 'N/A')}\n")
            f.write("#" + "="*60 + "\n\n")
            f.write(msg)
        
        self.info(f"Message saved to: {filepath}")
        return filepath


class HL7Parser:
    """Enhanced HL7 v2.x message parser for ADT messages"""
    
    def __init__(self, logger: HL7Logger):
        self.logger = logger
    
    def parse(self, raw_message: str) -> dict:
        """Parse HL7 message and extract all relevant fields"""
        result = {
            'raw': raw_message,
            'segments': {},
            'msh': {},
            'evn': {},
            'pid': {},
            'pv1': {},
            'pv2': {},
            'allergies': [],
            'custom': {},
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
                    # Handle multiple segments with same name (like AL1)
                    if segment_name in result['segments']:
                        if isinstance(result['segments'][segment_name], list):
                            result['segments'][segment_name].append(fields)
                        else:
                            result['segments'][segment_name] = [result['segments'][segment_name], fields]
                    else:
                        result['segments'][segment_name] = fields
            
            # Parse each segment type
            result['msh'] = self._parse_msh(result['segments'].get('MSH', []))
            result['evn'] = self._parse_evn(result['segments'].get('EVN', []))
            result['pid'] = self._parse_pid(result['segments'].get('PID', []))
            result['pv1'] = self._parse_pv1(result['segments'].get('PV1', []))
            result['pv2'] = self._parse_pv2(result['segments'].get('PV2', []))
            result['allergies'] = self._parse_al1(result['segments'].get('AL1', []))
            result['custom'] = self._parse_custom_segments(result['segments'])
            
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
    
    def _extract_phone_number(self, phone_field: str) -> str:
        """Extract phone number from HL7 phone field
        
        Handles formats like:
        - 0105656947 (just the number)
        - 0^0105656947 (type^number)
        - +60123456789
        - (603) 1234-5678
        """
        if not phone_field:
            return ''
        
        # If field contains ^, extract the most phone-like component
        if '^' in phone_field:
            parts = phone_field.split('^')
            for part in parts:
                # Find the part that looks most like a phone number (longer digit string)
                cleaned = part.replace('-', '').replace(' ', '').replace('(', '').replace(')', '')
                if cleaned.startswith('+'):
                    return part  # International format
                if len(cleaned) >= 8 and cleaned.isdigit():
                    return part  # Looks like a phone number
            # If no good match found, try to find any digit string with 8+ chars
            for part in parts:
                cleaned = ''.join(c for c in part if c.isdigit())
                if len(cleaned) >= 8:
                    return cleaned
        
        return phone_field
    
    def _parse_msh(self, msh: list) -> dict:
        """Parse MSH (Message Header) segment"""
        if not msh:
            return {}
        
        # MSH is special - field separator is MSH-1, but it's also the delimiter
        # So MSH fields are offset by 1 compared to how we split
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
    
    def _parse_evn(self, evn: list) -> dict:
        """Parse EVN (Event Type) segment"""
        if not evn:
            return {}
        
        return {
            'event_type_code': self._get_field(evn, 1),
            'recorded_datetime': self._get_field(evn, 2),
            'planned_event_datetime': self._get_field(evn, 3),
            'event_reason_code': self._get_field(evn, 4),
            'operator_id': self._get_field(evn, 5),
        }
    
    def _parse_pid(self, pid: list) -> dict:
        """Parse PID (Patient Identification) segment - Enhanced for non-standard HIS"""
        if not pid:
            return {}
        
        # PID-2: Patient ID (External) - may contain MRN
        patient_id_external = self._get_field(pid, 2)
        
        # PID-3: Patient Identifier List (Internal) - main identifier
        patient_id_internal = self._get_field(pid, 3)
        
        # Extract MRN from PID-2 or PID-3 (whichever has the MRN format)
        mrn = ''
        mrn_type = ''
        
        # Check PID-2 first (format: 123456789^^^MYS^MR)
        if '^^^' in patient_id_external:
            parts = patient_id_external.split('^^^')
            mrn = parts[0]
            if len(parts) > 1:
                type_parts = parts[1].split('^')
                mrn_type = type_parts[1] if len(type_parts) > 1 else type_parts[0]
        elif patient_id_external:
            mrn = patient_id_external
        
        # If MRN not found in PID-2, check PID-3
        if not mrn and patient_id_internal:
            if '^^^' in patient_id_internal:
                parts = patient_id_internal.split('^^^')
                mrn = parts[0]
            else:
                mrn = patient_id_internal
        
        # PID-4: Alternate Patient ID (could be IC/Passport or name in some HIS)
        pid_4_value = self._get_field(pid, 4)
        
        # PID-5: Patient Name (Last^First^Middle^Suffix^Prefix)
        patient_name_raw = self._get_field(pid, 5)
        
        # Detect non-standard HIS format where:
        # - Name is in PID-4 instead of PID-5
        # - This causes DOB to be at PID-6 instead of PID-7, Sex at PID-7 instead of PID-8, etc.
        field_offset = 0  # Offset for subsequent fields
        alternate_id = ''
        
        # Check for ICN (IC Number) format in PID-4: ICN^<IC_NUMBER>
        # This is used by CEREBRALPLUS HIS
        if pid_4_value and '^' in pid_4_value:
            pid_4_parts = pid_4_value.split('^')
            # Check if first part is an identifier type code (ICN, IC, NRIC, PASSPORT, etc.)
            id_type_codes = ['ICN', 'IC', 'NRIC', 'PP', 'PASSPORT', 'PPN', 'DL', 'NI', 'PRC', 'PI']
            if pid_4_parts[0].upper() in id_type_codes and len(pid_4_parts) > 1:
                # Extract the actual ID value (second component)
                alternate_id = pid_4_parts[1].strip() if pid_4_parts[1] else ''
                self.logger.debug(f"Extracted IC/Passport from PID-4 format {pid_4_parts[0]}: {alternate_id}")
        
        if not patient_name_raw and pid_4_value and '^' in pid_4_value:
            # PID-4 contains name-like data (has ^ separator and alphabetic chars)
            first_component = pid_4_value.split('^')[0]
            # Make sure it's not an identifier type code
            id_type_codes = ['ICN', 'IC', 'NRIC', 'PP', 'PASSPORT', 'PPN', 'DL', 'NI', 'PRC', 'PI']
            if first_component and any(c.isalpha() for c in first_component) and first_component.upper() not in id_type_codes:
                # This is a name, adjust parsing
                patient_name_raw = pid_4_value
                field_offset = -1  # Subsequent fields are shifted by 1
                # Use PID-3 as alternate ID (IC) if it's numeric
                if patient_id_internal and patient_id_internal.replace('-', '').isdigit():
                    alternate_id = patient_id_internal
        
        if not alternate_id and pid_4_value and not ('^' in pid_4_value and any(c.isalpha() for c in pid_4_value.split('^')[0])):
            # Standard case - PID-4 is alternate ID (numeric or doesn't look like name)
            alternate_id = pid_4_value
        
        # If we still don't have alternate_id, try PID-3 (if it looks like IC/number)
        if not alternate_id and patient_id_internal:
            if patient_id_internal.replace('-', '').isdigit() and '^^^' not in patient_id_internal:
                alternate_id = patient_id_internal
        
        # Parse patient name (Last^First^Middle^Suffix^Prefix)
        name_parts = patient_name_raw.split('^') if patient_name_raw else []
        last_name = name_parts[0] if len(name_parts) > 0 else ''
        first_name = name_parts[1] if len(name_parts) > 1 else ''
        middle_name = name_parts[2] if len(name_parts) > 2 else ''
        suffix = name_parts[3] if len(name_parts) > 3 else ''
        prefix = name_parts[4] if len(name_parts) > 4 else ''
        
        # Construct full name
        full_name = f"{first_name} {middle_name} {last_name}".strip()
        full_name = ' '.join(full_name.split())  # Remove extra spaces
        if prefix:
            full_name = f"{prefix} {full_name}"
        
        # PID-7: Date of Birth (YYYYMMDD) - may be at PID-6 in non-standard HIS
        dob_raw = self._get_field(pid, 7 + field_offset)
        dob = None
        age = None
        
        # Validate DOB looks like a date (8 digits starting with 19 or 20)
        if dob_raw and len(dob_raw) >= 8 and dob_raw[:2] in ('19', '20'):
            try:
                dob = f"{dob_raw[0:4]}-{dob_raw[4:6]}-{dob_raw[6:8]}"
                birth_date = datetime.strptime(dob_raw[0:8], '%Y%m%d')
                today = datetime.today()
                age = today.year - birth_date.year - ((today.month, today.day) < (birth_date.month, birth_date.day))
            except:
                pass
        
        # PID-8: Sex - apply field_offset
        sex_raw = self._get_field(pid, 8 + field_offset)
        gender_map = {'M': 'Male', 'F': 'Female', 'O': 'Other', 'U': 'Unknown'}
        gender = gender_map.get(sex_raw.upper(), sex_raw) if sex_raw else ''
        
        # PID-10: Race - apply field_offset
        race_field = self._get_field(pid, 10 + field_offset)
        race = self._parse_component(race_field, 1) or self._parse_component(race_field, 0)
        
        # PID-11: Address (Street^City^State^Postal^Country^Type) - apply field_offset
        address_raw = self._get_field(pid, 11 + field_offset)
        address_parts = address_raw.split('^') if address_raw else []
        address = {
            'street': address_parts[0] if len(address_parts) > 0 else '',
            'city': address_parts[1] if len(address_parts) > 1 else '',
            'state': address_parts[2] if len(address_parts) > 2 else '',
            'postal_code': address_parts[3] if len(address_parts) > 3 else '',
            'country': address_parts[4] if len(address_parts) > 4 else '',
            'type': address_parts[5] if len(address_parts) > 5 else '',
        }
        
        # PID-13: Phone Number (Home) - apply field_offset
        phone_home_raw = self._get_field(pid, 13 + field_offset)
        # Parse phone - may be in format: 0^0105656947 or just the number
        phone_home = self._extract_phone_number(phone_home_raw)
        
        # PID-14: Phone Number (Business) - apply field_offset
        phone_business_raw = self._get_field(pid, 14 + field_offset)
        phone_business = self._extract_phone_number(phone_business_raw)
        
        # PID-17: Religion - apply field_offset
        religion = self._get_field(pid, 17 + field_offset)
        
        # PID-30 or later: Extended patient info with phone
        # Format: ^PRS^^^^^EXT123^^^^+601822400114
        phone_extended = ''
        for i in range(30, min(len(pid), 35)):
            field = self._get_field(pid, i)
            if field and ('+' in field or field.replace('-', '').replace(' ', '').isdigit()):
                # Extract phone from component
                parts = field.split('^')
                for part in parts:
                    if '+' in part or (part.replace('-', '').replace(' ', '').isdigit() and len(part) > 8):
                        phone_extended = part
                        break
                if phone_extended:
                    break
        
        # Determine best phone number
        phone = phone_extended or phone_home or phone_business
        # Clean phone number
        if phone:
            phone = phone.replace(' ', '').replace('-', '')
        
        return {
            'set_id': self._get_field(pid, 1),
            'patient_id_external': patient_id_external,
            'patient_id_internal': patient_id_internal,
            'mrn': mrn,
            'mrn_type': mrn_type,
            'alternate_id': alternate_id,  # IC/Passport
            'name_raw': patient_name_raw,
            'name': full_name,
            'last_name': last_name,
            'first_name': first_name,
            'middle_name': middle_name,
            'prefix': prefix,
            'suffix': suffix,
            'dob': dob,
            'dob_raw': dob_raw,
            'age': age,
            'gender_raw': sex_raw,
            'gender': gender,
            'race': race,
            'address': address,
            'address_full': f"{address['street']}, {address['city']}, {address['state']} {address['postal_code']}, {address['country']}".strip(', '),
            'phone_home': phone_home,
            'phone_business': phone_business,
            'phone': phone,
            'religion': religion,
        }
    
    def _parse_pv1(self, pv1: list) -> dict:
        """Parse PV1 (Patient Visit) segment - Enhanced"""
        if not pv1:
            return {}
        
        # PV1-2: Patient Class
        patient_class_raw = self._get_field(pv1, 2)
        patient_class_map = {
            'I': 'Inpatient',
            'O': 'Outpatient',
            'E': 'Emergency',
            'P': 'Preadmit',
            'R': 'Recurring',
            'B': 'Obstetrics',
            'C': 'Commercial',
            'N': 'Not Applicable',
            'U': 'Unknown',
        }
        patient_class = patient_class_map.get(patient_class_raw.upper(), patient_class_raw) if patient_class_raw else ''
        
        # PV1-3: Assigned Patient Location (Ward^Room^Bed^Facility^LocationStatus^PersonLocationType^Building^Floor)
        location_raw = self._get_field(pv1, 3)
        location_parts = location_raw.split('^') if location_raw else []
        
        # PV1-7: Attending Doctor
        attending_raw = self._get_field(pv1, 7)
        attending_parts = attending_raw.split('^') if attending_raw else []
        
        # PV1-8: Referring Doctor
        referring_raw = self._get_field(pv1, 8)
        
        # PV1-9: Consulting Doctor
        consulting_raw = self._get_field(pv1, 9)
        
        # PV1-14: Admit Source
        admit_source = self._get_field(pv1, 14)
        
        # PV1-15: Admitting Doctor - Note: In the sample message, DOCTOR3 is at position 14
        admitting_raw = self._get_field(pv1, 17) or self._get_field(pv1, 14)
        
        # PV1-19: Visit Number - Note: In sample message, VISITNO is at position 15
        visit_number = self._get_field(pv1, 19) or self._get_field(pv1, 15)
        
        # PV1-38: Diet Type - Some HIS put it at PV1-36
        diet_type = self._get_field(pv1, 38) or self._get_field(pv1, 36)
        
        # PV1-40: Bed Status (may contain bed info like ^^B2)
        # Some HIS systems put bed at PV1-38 instead of PV1-40
        bed_from_status = ''
        bed_status_raw = ''
        
        # Try multiple positions for bed status (^^B2 format)
        for bed_field_idx in [40, 38, 39, 41]:
            field_val = self._get_field(pv1, bed_field_idx)
            if field_val and ('^' in field_val or field_val.startswith('B') or field_val[0:1].isalpha()):
                bed_status_raw = field_val
                # Extract bed from ^^B2 format (get last non-empty component)
                parts = field_val.split('^')
                for part in reversed(parts):
                    if part and (part[0].isalpha() or part[0].isdigit()):
                        bed_from_status = part
                        break
                if bed_from_status:
                    break
        
        # PV1-44: Admit DateTime - Some HIS put it at PV1-40 or PV1-42
        admit_datetime_raw = ''
        admit_datetime = None
        
        # Try multiple positions for admit datetime (YYYYMMDDHHMMSS format)
        for dt_field_idx in [44, 40, 42, 41]:
            field_val = self._get_field(pv1, dt_field_idx)
            if field_val and len(field_val) >= 8 and field_val[:4].isdigit() and field_val[4:6].isdigit():
                # Looks like a datetime
                admit_datetime_raw = field_val
                break
        
        if admit_datetime_raw and len(admit_datetime_raw) >= 8:
            try:
                admit_datetime = f"{admit_datetime_raw[0:4]}-{admit_datetime_raw[4:6]}-{admit_datetime_raw[6:8]}"
                if len(admit_datetime_raw) >= 14:
                    admit_datetime += f" {admit_datetime_raw[8:10]}:{admit_datetime_raw[10:12]}:{admit_datetime_raw[12:14]}"
            except:
                pass
        
        # PV1-45: Discharge DateTime
        discharge_datetime_raw = self._get_field(pv1, 45)
        discharge_datetime = None
        if discharge_datetime_raw and len(discharge_datetime_raw) >= 8:
            try:
                discharge_datetime = f"{discharge_datetime_raw[0:4]}-{discharge_datetime_raw[4:6]}-{discharge_datetime_raw[6:8]}"
                if len(discharge_datetime_raw) >= 14:
                    discharge_datetime += f" {discharge_datetime_raw[8:10]}:{discharge_datetime_raw[10:12]}:{discharge_datetime_raw[12:14]}"
            except:
                pass
        
        return {
            'set_id': self._get_field(pv1, 1),
            'patient_class_raw': patient_class_raw,
            'patient_class': patient_class,
            'location_raw': location_raw,
            'ward': location_parts[0] if len(location_parts) > 0 else '',
            'room': location_parts[1] if len(location_parts) > 1 else '',
            'bed': location_parts[2] if len(location_parts) > 2 else bed_from_status,
            'facility': location_parts[3] if len(location_parts) > 3 else '',
            'admission_type': self._get_field(pv1, 4),
            'preadmit_number': self._get_field(pv1, 5),
            'prior_location': self._get_field(pv1, 6),
            'attending_doctor_raw': attending_raw,
            'attending_doctor_id': attending_parts[0] if attending_parts else attending_raw,
            'attending_doctor_name': f"{attending_parts[1]} {attending_parts[2]}".strip() if len(attending_parts) > 2 else '',
            'referring_doctor': referring_raw,
            'consulting_doctor': consulting_raw,
            'hospital_service': self._get_field(pv1, 10),
            'admit_source': admit_source,
            'admitting_doctor': admitting_raw,
            'visit_number': visit_number,
            'financial_class': self._get_field(pv1, 20),
            'diet_type': diet_type,
            'bed_status': bed_status_raw,
            'admit_datetime_raw': admit_datetime_raw,
            'admit_datetime': admit_datetime,
            'discharge_datetime_raw': discharge_datetime_raw,
            'discharge_datetime': discharge_datetime,
        }
    
    def _parse_pv2(self, pv2: list) -> dict:
        """Parse PV2 (Patient Visit - Additional Info) segment"""
        if not pv2:
            return {}
        
        # PV2-8: Expected Admit DateTime
        expected_admit_raw = self._get_field(pv2, 8)
        
        # PV2-9: Expected Discharge DateTime
        expected_discharge_raw = self._get_field(pv2, 9)
        expected_discharge = None
        if expected_discharge_raw and len(expected_discharge_raw) >= 8:
            try:
                expected_discharge = f"{expected_discharge_raw[0:4]}-{expected_discharge_raw[4:6]}-{expected_discharge_raw[6:8]}"
                if len(expected_discharge_raw) >= 14:
                    expected_discharge += f" {expected_discharge_raw[8:10]}:{expected_discharge_raw[10:12]}:{expected_discharge_raw[12:14]}"
            except:
                pass
        
        # PV2-10: Estimated Length of Stay
        length_of_stay = self._get_field(pv2, 10)
        try:
            length_of_stay = int(length_of_stay) if length_of_stay else None
        except:
            length_of_stay = None
        
        # PV2-22: Visit Protection Indicator
        visit_protection = self._get_field(pv2, 22)
        
        # PV2-38: Mode of Arrival
        mode_of_arrival = self._get_field(pv2, 38)
        
        return {
            'prior_pending_location': self._get_field(pv2, 1),
            'accommodation_code': self._get_field(pv2, 2),
            'admit_reason': self._get_field(pv2, 3),
            'transfer_reason': self._get_field(pv2, 4),
            'expected_admit_datetime': expected_admit_raw,
            'expected_discharge_datetime_raw': expected_discharge_raw,
            'expected_discharge_datetime': expected_discharge,
            'estimated_length_of_stay': length_of_stay,
            'visit_protection_indicator': visit_protection,
            'clinic_organization': self._get_field(pv2, 23),
            'patient_status_code': self._get_field(pv2, 24),
            'mode_of_arrival': mode_of_arrival,
        }
    
    def _parse_al1(self, al1_data) -> List[Dict[str, Any]]:
        """Parse AL1 (Allergy) segments - handles multiple AL1 segments"""
        allergies = []
        
        if not al1_data:
            return allergies
        
        # Ensure we have a list of AL1 segments
        if isinstance(al1_data, list) and al1_data and isinstance(al1_data[0], str):
            # Single AL1 segment
            al1_list = [al1_data]
        elif isinstance(al1_data, list):
            al1_list = al1_data
        else:
            return allergies
        
        allergy_type_map = {
            'DA': 'Drug Allergy',
            'FA': 'Food Allergy',
            'MA': 'Miscellaneous Allergy',
            'MC': 'Miscellaneous Contraindication',
            'EA': 'Environmental Allergy',
            'AA': 'Animal Allergy',
            'PA': 'Plant Allergy',
            'LA': 'Pollen Allergy',
        }
        
        severity_map = {
            'MI': 'Mild',
            'MO': 'Moderate',
            'SV': 'Severe',
            'U': 'Unknown',
        }
        
        for al1 in al1_list:
            if not al1 or len(al1) < 4:
                continue
            
            allergy_type_code = self._get_field(al1, 2)
            severity_code = self._get_field(al1, 4)
            
            allergies.append({
                'set_id': self._get_field(al1, 1),
                'type_code': allergy_type_code,
                'type': allergy_type_map.get(allergy_type_code, allergy_type_code),
                'allergen_code': self._get_field(al1, 3),
                'allergen': self._get_field(al1, 3),
                'severity_code': severity_code,
                'severity': severity_map.get(severity_code, severity_code),
                'reaction': self._get_field(al1, 5) if len(al1) > 5 else '',
            })
        
        return allergies
    
    def _parse_custom_segments(self, segments: dict) -> dict:
        """Parse custom Z-segments"""
        custom = {
            'fall_risk': False,
            'fall_risk_description': '',
            'isolation_type': '',
            'isolation_description': '',
            'attributes': [],
        }
        
        # Parse ZAT (Patient Attributes)
        zat = segments.get('ZAT', [])
        if zat:
            if isinstance(zat[0], list):
                # Multiple ZAT segments
                for z in zat:
                    self._process_zat(z, custom)
            else:
                self._process_zat(zat, custom)
        
        # Parse ZIT (Isolation Type)
        zit = segments.get('ZIT', [])
        if zit:
            if isinstance(zit, list) and zit and isinstance(zit[0], str):
                custom['isolation_type'] = self._get_field(zit, 1)
                custom['isolation_description'] = self._get_field(zit, 2)
        
        # Parse ZFR (Fall Risk Flag)
        zfr = segments.get('ZFR', [])
        if zfr:
            if isinstance(zfr, list) and zfr and isinstance(zfr[0], str):
                zfr_value = self._get_field(zfr, 1)
                if zfr_value == '1' or zfr_value.upper() == 'Y':
                    custom['fall_risk'] = True
        
        return custom
    
    def _process_zat(self, zat: list, custom: dict):
        """Process a single ZAT segment"""
        if not zat or len(zat) < 2:
            return
        
        attr_code = self._get_field(zat, 1)
        attr_desc = self._get_field(zat, 2)
        
        custom['attributes'].append({
            'code': attr_code,
            'description': attr_desc,
        })
        
        # Check for specific attributes
        if attr_code == 'FR' or 'FALL' in attr_desc.upper():
            custom['fall_risk'] = True
            custom['fall_risk_description'] = attr_desc


class LaravelAPIClient:
    """Client for sending parsed ADT data to Laravel API"""
    
    def __init__(self, logger: HL7Logger, api_url: str = LARAVEL_API_URL, api_key: str = LARAVEL_API_KEY):
        self.logger = logger
        self.api_url = api_url
        self.api_key = api_key
    
    def send_adt_message(self, parsed_data: dict, source_ip: str) -> dict:
        """Send parsed ADT message to Laravel API"""
        try:
            # Prepare payload
            payload = {
                'msh': parsed_data.get('msh', {}),
                'evn': parsed_data.get('evn', {}),
                'pid': parsed_data.get('pid', {}),
                'pv1': parsed_data.get('pv1', {}),
                'pv2': parsed_data.get('pv2', {}),
                'allergies': parsed_data.get('allergies', []),
                'custom': parsed_data.get('custom', {}),
                'raw_message': parsed_data.get('raw', ''),
                'source_ip': source_ip,
            }
            
            headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            }
            
            if self.api_key:
                headers['X-API-Key'] = self.api_key
            
            self.logger.info(f"Sending ADT data to Laravel API: {self.api_url}")
            
            response = requests.post(
                self.api_url,
                json=payload,
                headers=headers,
                timeout=30
            )
            
            if response.status_code == 200:
                result = response.json()
                self.logger.info(f"Laravel API response: {result.get('message', 'Success')}")
                return result
            else:
                self.logger.error(f"Laravel API error: {response.status_code} - {response.text}")
                return {'success': False, 'error': f"HTTP {response.status_code}: {response.text}"}
                
        except requests.exceptions.ConnectionError:
            self.logger.warning(f"Cannot connect to Laravel API at {self.api_url} - Message logged only")
            return {'success': False, 'error': 'Connection refused'}
        except requests.exceptions.Timeout:
            self.logger.error("Laravel API request timeout")
            return {'success': False, 'error': 'Timeout'}
        except Exception as e:
            self.logger.error(f"Error sending to Laravel API: {str(e)}")
            return {'success': False, 'error': str(e)}


class HL7ADTListener:
    """HL7 ADT Message Listener using MLLP protocol"""
    
    def __init__(self, host: str = HOST, port: int = PORT):
        self.host = host
        self.port = port
        self.logger = HL7Logger()
        self.parser = HL7Parser(self.logger)
        self.api_client = LaravelAPIClient(self.logger)
        self.running = False
        self.server_socket = None
        self.message_count = 0
    
    def create_ack(self, parsed_msg: dict, ack_code: str = 'AA') -> bytes:
        """Create HL7 ACK (Acknowledgment) message"""
        timestamp = datetime.now().strftime('%Y%m%d%H%M%S')
        
        msh = parsed_msg.get('msh', {})
        sending_app = msh.get('receiving_application', 'HL7_LISTENER') or 'HL7_LISTENER'
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
        
        # Send to Laravel API
        api_result = self.api_client.send_adt_message(parsed, client_address[0])
        
        # Calculate processing time
        processing_time = (datetime.now() - start_time).total_seconds() * 1000
        self.logger.info(f"Processing time: {processing_time:.2f}ms")
        
        # Send acknowledgment
        try:
            ack_code = 'AA' if api_result.get('success', True) else 'AE'
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
        pv2 = parsed.get('pv2', {})
        allergies = parsed.get('allergies', [])
        custom = parsed.get('custom', {})
        
        event_code = msh.get('event_type', '')
        event_description = ADT_EVENTS.get(event_code, 'Unknown Event')
        
        self.logger.info(f"\n{'─'*40}")
        self.logger.info(f"MESSAGE DETAILS")
        self.logger.info(f"{'─'*40}")
        self.logger.info(f"Message Type     : {msh.get('message_type', 'N/A')}")
        self.logger.info(f"Event            : {event_code} - {event_description}")
        self.logger.info(f"Control ID       : {msh.get('message_control_id', 'N/A')}")
        self.logger.info(f"HL7 Version      : {msh.get('version', 'N/A')}")
        self.logger.info(f"Message DateTime : {msh.get('message_datetime', 'N/A')}")
        
        self.logger.info(f"\n{'─'*40}")
        self.logger.info(f"ROUTING INFORMATION")
        self.logger.info(f"{'─'*40}")
        self.logger.info(f"Sending App      : {msh.get('sending_application', 'N/A')}")
        self.logger.info(f"Sending Facility : {msh.get('sending_facility', 'N/A')}")
        self.logger.info(f"Receiving App    : {msh.get('receiving_application', 'N/A')}")
        self.logger.info(f"Receiving Fac    : {msh.get('receiving_facility', 'N/A')}")
        
        if pid:
            self.logger.info(f"\n{'─'*40}")
            self.logger.info(f"PATIENT INFORMATION")
            self.logger.info(f"{'─'*40}")
            self.logger.info(f"MRN              : {pid.get('mrn', 'N/A')}")
            self.logger.info(f"IC/Passport      : {pid.get('alternate_id', 'N/A')}")
            self.logger.info(f"Name             : {pid.get('name', 'N/A')}")
            self.logger.info(f"DOB              : {pid.get('dob', 'N/A')}")
            self.logger.info(f"Age              : {pid.get('age', 'N/A')}")
            self.logger.info(f"Gender           : {pid.get('gender', 'N/A')}")
            self.logger.info(f"Phone            : {pid.get('phone', 'N/A')}")
            self.logger.info(f"Race             : {pid.get('race', 'N/A')}")
            self.logger.info(f"Religion         : {pid.get('religion', 'N/A')}")
            self.logger.info(f"Address          : {pid.get('address_full', 'N/A')}")
        
        if pv1:
            self.logger.info(f"\n{'─'*40}")
            self.logger.info(f"VISIT INFORMATION")
            self.logger.info(f"{'─'*40}")
            self.logger.info(f"Patient Class    : {pv1.get('patient_class', 'N/A')}")
            self.logger.info(f"Ward             : {pv1.get('ward', 'N/A')}")
            self.logger.info(f"Room             : {pv1.get('room', 'N/A')}")
            self.logger.info(f"Bed              : {pv1.get('bed', 'N/A')}")
            self.logger.info(f"Visit Number     : {pv1.get('visit_number', 'N/A')}")
            self.logger.info(f"Attending Doctor : {pv1.get('attending_doctor_id', 'N/A')}")
            self.logger.info(f"Diet Type        : {pv1.get('diet_type', 'N/A')}")
            self.logger.info(f"Admit DateTime   : {pv1.get('admit_datetime', 'N/A')}")
        
        if pv2:
            self.logger.info(f"\n{'─'*40}")
            self.logger.info(f"ADDITIONAL VISIT INFO")
            self.logger.info(f"{'─'*40}")
            self.logger.info(f"Expected Discharge: {pv2.get('expected_discharge_datetime', 'N/A')}")
            self.logger.info(f"Length of Stay    : {pv2.get('estimated_length_of_stay', 'N/A')} days")
        
        if allergies:
            self.logger.info(f"\n{'─'*40}")
            self.logger.info(f"ALLERGIES ({len(allergies)} found)")
            self.logger.info(f"{'─'*40}")
            for allergy in allergies:
                self.logger.info(f"  • {allergy.get('allergen', 'N/A')} ({allergy.get('type', 'N/A')}) - Severity: {allergy.get('severity', 'N/A')}")
        
        if custom.get('fall_risk') or custom.get('isolation_type'):
            self.logger.info(f"\n{'─'*40}")
            self.logger.info(f"CLINICAL INDICATORS")
            self.logger.info(f"{'─'*40}")
            if custom.get('fall_risk'):
                self.logger.info(f"⚠️  FALL RISK: Yes")
            if custom.get('isolation_type'):
                self.logger.info(f"🔒 Isolation: {custom.get('isolation_type')} - {custom.get('isolation_description', '')}")
        
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
            self.logger.info(f"Laravel API: {self.api_client.api_url}")
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
    ║           HL7 ADT Message Listener v2.0                   ║
    ║        SmartWard Healthcare Integration                   ║
    ╠═══════════════════════════════════════════════════════════╣
    ║  Supported Events: A01-A62 (ADT Messages)                 ║
    ║  Protocol: MLLP over TCP/IP                               ║
    ║  Default Port: 3000                                       ║
    ║  Features: Enhanced PID/PV1 parsing, AL1, Z-segments      ║
    ╚═══════════════════════════════════════════════════════════╝
    """)
    
    # Get configuration from environment or use defaults
    host = os.getenv('HL7_HOST', '0.0.0.0')
    port = int(os.getenv('HL7_PORT', '3000'))
    
    listener = HL7ADTListener(host=host, port=port)
    listener.start()


if __name__ == '__main__':
    main()
