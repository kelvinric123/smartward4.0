import hl7
import datetime
import logging


class HL7Parser:
    """
    Parses HL7 messages from VS30 vital signs monitors.
    Extracts vital signs data from OBX segments and patient info from PID.
    Generates ACK messages in response.
    """
    
    def __init__(self):
        pass

    def parse_message(self, raw_message):
        """
        Parses a raw HL7 string (bytes or str).
        """
        if isinstance(raw_message, bytes):
            raw_message = raw_message.decode('utf-8', errors='ignore')
            
        try:
            # Force \r as segment separator if not already handled
            if '\r' not in raw_message and '\n' in raw_message:
                raw_message = raw_message.replace('\n', '\r')
                
            h = hl7.parse(raw_message)
            return h
        except Exception as e:
            logging.error(f"Error parsing HL7 message: {e}")
            return None

    def create_ack(self, original_message, ack_code='AA', text_message='Message Received'):
        """
        Creates an HL7 ACK message in response to the original message.
        """
        try:
            if isinstance(original_message, str):
                 h_orig = hl7.parse(original_message)
            else:
                 h_orig = original_message
            
            # Find MSH segment
            msh = None
            for segment in h_orig:
                if str(segment[0]) == 'MSH':
                    msh = segment
                    break
            
            if not msh:
                raise ValueError("No MSH segment found")
            
            # Construct MSH for ACK
            timestamp = datetime.datetime.now().strftime('%Y%m%d%H%M%S')
            
            sending_app = str(msh[2]) if len(msh) > 2 else "VS30"
            sending_facility = str(msh[3]) if len(msh) > 3 else "GATEWAY"
            receiving_app = str(msh[4]) if len(msh) > 4 else "LISTENER"
            receiving_facility = str(msh[5]) if len(msh) > 5 else "HOST"
            message_control_id = str(msh[9]) if len(msh) > 9 else "12345"
            processing_id = str(msh[10]) if len(msh) > 10 else "P"
            version_id = str(msh[11]) if len(msh) > 11 else "2.3"
            
            ack_msh = f"MSH|^~\\&|{receiving_app}|{receiving_facility}|{sending_app}|{sending_facility}|{timestamp}||ACK|{message_control_id}|{processing_id}|{version_id}\r"
            ack_msa = f"MSA|{ack_code}|{message_control_id}|{text_message}\r"
            
            return ack_msh + ack_msa
            
        except Exception as e:
            logging.error(f"Error creating ACK: {e}")
            return f"MSH|^~\\&|LISTENER|GATEWAY|VS30|DEVICE|{datetime.datetime.now().strftime('%Y%m%d%H%M%S')}||ACK|1|P|2.3\rMSA|AE|1|Error creating ACK\r"


    def extract_vitals(self, message):
        """
        Extracts vital signs (OBX segments) and Patient Info (PID).
        """
        data = {
            'patient_id': None,
            'patient_name': None,
            'vitals': {}
        }
        
        try:
            for segment in message:
                segment_id = str(segment[0])
                
                if segment_id == 'PID':
                    # PID-3: Patient Identifier List
                    if len(segment) > 3:
                        data['patient_id'] = str(segment[3])
                    
                    # PID-5: Patient Name
                    if len(segment) > 5:
                        data['patient_name'] = str(segment[5])
                        
                elif segment_id == 'OBX':
                    try:
                        # OBX-3: Observation Identifier (e.g., 8867-4 for HR)
                        obs_id_field = segment[3]
                        if not obs_id_field:
                            continue
                            
                        # If complex field, take first component
                        obs_id = str(obs_id_field).split('^')[0]
                        
                        # OBX-5: Value
                        obs_value = str(segment[5])
                        
                        # OBX-6: Units
                        obs_units = str(segment[6]) if len(segment) > 6 else ""
                        
                        data['vitals'][obs_id] = {
                            'value': obs_value,
                            'units': obs_units
                        }
                    except IndexError:
                        continue
                        
        except Exception as e:
            logging.error(f"Error extracting vitals: {e}")
            
        return data
