#!/usr/bin/python
# -*- coding: utf-8 -*-

"""
Sample Sender for Vital Signs
Used to test the notification system by sending dummy data to the API.
"""

import os
import sys
import time
import requests
import random
from datetime import datetime
from dotenv import load_dotenv

# Load environment variables from .env file (same as listener)
load_dotenv(os.path.join(os.path.dirname(__file__), '.env'))

def get_config():
    """Get configuration from environment"""
    return {
        'api_base_url': os.getenv('API_BASE_URL', 'http://localhost:8000').rstrip('/'),
        'api_passphrase': os.getenv('API_PASSPHRASE', ''),
        'api_username': os.getenv('API_USERNAME', ''),
        'api_password': os.getenv('API_PASSWORD', ''),
    }

def send_vital_signs(config, patient_id="100001", patient_name="Test Patient", manual_input=False):
    """Send vital signs data to the API server"""
    try:
        api_url = f"{config['api_base_url']}/vital-signs"
        
        # Generate default random vital signs
        default_bp_sys = random.randint(110, 140)
        default_bp_dias = random.randint(70, 90)
        default_heart_rate = random.randint(60, 100)
        default_oxygen = round(random.uniform(95.0, 100.0), 1)
        default_temperature = round(random.uniform(36.5, 37.5), 1)
        default_resp_rate = random.randint(12, 20)
        
        if manual_input:
            print("\nEnter vital signs (press Enter to use default random value):")
            
            bp_sys_input = input(f"  Blood Pressure Systolic [{default_bp_sys}]: ").strip()
            bp_sys = int(bp_sys_input) if bp_sys_input else default_bp_sys
            
            bp_dias_input = input(f"  Blood Pressure Diastolic [{default_bp_dias}]: ").strip()
            bp_dias = int(bp_dias_input) if bp_dias_input else default_bp_dias
            
            heart_rate_input = input(f"  Heart Rate [{default_heart_rate}]: ").strip()
            heart_rate = int(heart_rate_input) if heart_rate_input else default_heart_rate
            
            oxygen_input = input(f"  SpO2 % [{default_oxygen}]: ").strip()
            oxygen = float(oxygen_input) if oxygen_input else default_oxygen
            
            temperature_input = input(f"  Temperature °C [{default_temperature}]: ").strip()
            temperature = float(temperature_input) if temperature_input else default_temperature
            
            resp_rate_input = input(f"  Respiratory Rate [{default_resp_rate}]: ").strip()
            resp_rate = int(resp_rate_input) if resp_rate_input else default_resp_rate
        else:
            bp_sys = default_bp_sys
            bp_dias = default_bp_dias
            heart_rate = default_heart_rate
            oxygen = default_oxygen
            temperature = default_temperature
            resp_rate = default_resp_rate
        
        timestamp = datetime.now()
        timestamp_str = timestamp.strftime('%Y-%m-%d %H:%M:%S')
        
        print(f"Preparing to send vitals for {patient_name} ({patient_id})...")
        print(f"Values: BP={bp_sys}/{bp_dias}, HR={heart_rate}, SpO2={oxygen}%, Temp={temperature}C")

        # Prepare API payload
        payload = {
            "username": config['api_username'],
            "password": config['api_password'],
            "patient_code": patient_id,
            "measured_at": timestamp_str,
            "blood_pressure_systolic": bp_sys,
            "blood_pressure_diastolic": bp_dias,
            "pulse_rate": heart_rate,
            "spo2": oxygen,
            "temperature": temperature,
            "respiratory_rate": resp_rate
        }
        
        # Set headers with X-Passphrase
        headers = {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "X-Passphrase": config['api_passphrase']
        }
        
        # Send POST request to API
        print(f"Sending to {api_url}...")
        response = requests.post(api_url, json=payload, headers=headers, timeout=5)
        
        # Check response
        if response.status_code in [200, 201]:
            print(f"✓ Success! Server responded: {response.text}")
            return True
        else:
            print(f"✗ Failed! HTTP {response.status_code}: {response.text}")
            return False
            
    except Exception as e:
        print(f"✗ Error: {e}")
        return False

if __name__ == "__main__":
    print("Vital Sign Sample Sender")
    print("========================")
    
    config = get_config()
    
    if not config['api_passphrase']:
        print("Error: API_PASSPHRASE not found in .env")
        sys.exit(1)
        
    # Allow user to inputs
    if len(sys.argv) > 1:
        p_id = sys.argv[1]
    else:
        p_id = input("Enter Patient ID (default: 1): ") or "1"
        
    if len(sys.argv) > 2:
        p_name = sys.argv[2]
    else:
        p_name = "Test Patient"

    # Ask if user wants to manually input vitals
    manual_choice = input("Manually input vital signs? (y/N): ").strip().lower()
    manual_input = manual_choice in ['y', 'yes']

    send_vital_signs(config, p_id, p_name, manual_input=manual_input)
    print("\nDone.")
