import requests
import json
import logging
from datetime import datetime

try:
    from .config import API_BASE_URL, API_USERNAME, API_PASSWORD, API_PASSPHRASE
except ImportError:
    from config import API_BASE_URL, API_USERNAME, API_PASSWORD, API_PASSPHRASE

class APIClient:
    def __init__(self):
        self.base_url = API_BASE_URL.rstrip('/')
        self.username = API_USERNAME
        self.password = API_PASSWORD
        self.passphrase = API_PASSPHRASE

    def send_vitals(self, patient_id, vitals_data, measure_time=None):
        """
        Sends vital signs to the API.
        vitals_data: Dict of OBX codes and values.
        """
        url = f"{self.base_url}/api/v1/vital-signs"
        
        if not measure_time:
            measure_time = datetime.now()
            
        timestamp_str = measure_time.strftime('%Y-%m-%d %H:%M:%S')
        
        # Map HL7 codes to API fields
        # Common LOINC codes for vitals:
        # 8867-4: Heart Rate (Pulse rate)
        # 2708-6: Oxygen Saturation (SpO2)
        # 8480-6: Systolic Blood Pressure
        # 8462-4: Diastolic Blood Pressure
        # 8310-5: Body Temperature
        # 9279-1: Respiratory Rate
        
        # Initialize payload
        payload = {
            "username": self.username,
            "password": self.password,
            "patient_code": patient_id,
            "measured_at": timestamp_str
            # "blood_pressure_systolic": 0,
            # "blood_pressure_diastolic": 0,
            # "pulse_rate": 0,
            # "spo2": 0,
            # "temperature": 0,
            # "respiratory_rate": 0
        }
        
        has_data = False
        
        for code, data in vitals_data.items():
            value = data['value']
            try:
                val_float = float(value)
            except ValueError:
                continue
                
            if code == '8867-4': # Heart Rate
                payload['pulse_rate'] = int(val_float)
                has_data = True
            elif code == '2708-6': # SpO2
                payload['spo2'] = val_float
                has_data = True
            elif code == '8480-6': # Systolic BP
                payload['blood_pressure_systolic'] = int(val_float)
                has_data = True
            elif code == '8462-4': # Diastolic BP
                payload['blood_pressure_diastolic'] = int(val_float)
                has_data = True
            elif code == '8310-5': # Temperature
                payload['temperature'] = val_float
                has_data = True
            elif code == '9279-1': # Resp Rate
                payload['respiratory_rate'] = int(val_float)
                has_data = True

        if not has_data:
            logging.warning("No mappable vital signs found in message")
            return False

        headers = {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "X-Passphrase": self.passphrase
        }
        
        try:
            logging.info(f"Sending vitals for {patient_id} to {url}")
            logging.debug(f"Payload: {json.dumps(payload)}")
            
            response = requests.post(url, json=payload, headers=headers, timeout=10)
            
            if response.status_code in [200, 201]:
                logging.info(f"API Success: {response.text}")
                return True
            else:
                logging.error(f"API Failed {response.status_code}: {response.text}")
                return False
                
        except Exception as e:
            logging.error(f"API Connection Error: {e}")
            return False
