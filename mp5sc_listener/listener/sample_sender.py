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

def send_vital_signs(config, patient_id="100001", patient_name="Test Patient"):
    """Send vital signs data to the API server"""
    try:
        api_url = f"{config['api_base_url']}/vital-signs"
        
        # Generate random vital signs
        bp_sys = random.randint(110, 140)
        bp_dias = random.randint(70, 90)
        heart_rate = random.randint(60, 100)
        oxygen = round(random.uniform(95.0, 100.0), 1)
        temperature = round(random.uniform(36.5, 37.5), 1)
        resp_rate = random.randint(12, 20)
        
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

    send_vital_signs(config, p_id, p_name)
    print("\nDone.")
