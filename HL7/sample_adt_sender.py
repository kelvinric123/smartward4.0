#!/usr/bin/env python3
"""
Sample HL7 ADT A01 Message Sender
Sends the exact HL7 message format expected from the HIS system to test the ADT listener
"""

import socket
import sys
import os
from datetime import datetime

# MLLP (Minimal Lower Layer Protocol) constants
MLLP_START_BLOCK = b'\x0b'  # VT (Vertical Tab)
MLLP_END_BLOCK = b'\x1c'    # FS (File Separator)
MLLP_CARRIAGE_RETURN = b'\x0d'  # CR

# Configuration
DEFAULT_HOST = os.getenv('HL7_HOST', 'localhost')
DEFAULT_PORT = int(os.getenv('HL7_PORT', '3000'))


# =============================================================================
# SAMPLE HL7 ADT A01 MESSAGE
# This is the exact format expected from the HIS system
# =============================================================================

SAMPLE_ADT_A01 = """MSH|^~\\&|HIS|PHKL|ADT|12333|20250227145411|xxx|ADT^A01^ADT_A01|efe4c5bd-1db8-463f-b6b5-7495a8edd562|P|2.8
EVN||20250227145411
PID||123456789^^^MYS^MR|88888|Doe^John^^^Mr.||19850615|M||^Chinese|123 Main St.^Kuala Lumpur^Wilayah Persekutuan^51000^MYS^P||||||BUD||||||||||||||||^PRS^^^^^EXT123^^^^+601822400114
PV1||R^^^||E|DOCTOR0|DOCTOR1|DOCTOR2||||||1|DOCTOR3|VISITNO|||||||||||||||||VEGETARIAN||^^B2||20250227145411
PV2|||||||||20250227205411|6||||||||||||||1|||||||||||||S
AL1|1|DA|PENICILLIN|MI
AL1|2|DA|PARACETAMOL|MO
ZAT|FR|FALL RISK
ZIT|DAC|Droplet, Airborne and Contact
ZFR|1"""


def send_hl7_message(host, port, message):
    """
    Send HL7 message via MLLP (Minimal Lower Layer Protocol) and return response
    """
    try:
        # Convert newlines to carriage returns (HL7 segment delimiter)
        message = message.replace('\n', '\r')
        
        # Create MLLP wrapped message
        mllp_message = MLLP_START_BLOCK + message.encode('utf-8') + MLLP_END_BLOCK + MLLP_CARRIAGE_RETURN
        
        # Connect and send
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as sock:
            sock.settimeout(10)
            print(f"  → Connecting to {host}:{port}...")
            sock.connect((host, port))
            print(f"  → Connected! Sending message ({len(mllp_message)} bytes)...")
            sock.sendall(mllp_message)
            print(f"  → Message sent. Waiting for ACK...")
            
            # Receive response
            response = b''
            while True:
                try:
                    data = sock.recv(4096)
                    if not data:
                        break
                    response += data
                    if MLLP_END_BLOCK in response:
                        break
                except socket.timeout:
                    break
            
            # Parse response - remove MLLP framing
            if response:
                start_idx = response.find(MLLP_START_BLOCK)
                end_idx = response.find(MLLP_END_BLOCK)
                if start_idx >= 0 and end_idx > start_idx:
                    return response[start_idx + 1:end_idx].decode('utf-8', errors='replace')
            
            return "No response received"
            
    except ConnectionRefusedError:
        return "ERROR: Connection refused. Is the ADT listener running?"
    except socket.timeout:
        return "ERROR: Connection timeout"
    except Exception as e:
        return f"ERROR: {str(e)}"


def print_message_breakdown(message):
    """Print a detailed breakdown of the HL7 message segments"""
    print("\n" + "="*80)
    print("MESSAGE SEGMENT BREAKDOWN")
    print("="*80)
    
    segments = message.replace('\n', '\r').split('\r')
    
    for segment in segments:
        if not segment.strip():
            continue
        
        fields = segment.split('|')
        segment_name = fields[0]
        
        print(f"\n┌─ {segment_name} SEGMENT " + "─"*(65 - len(segment_name)))
        
        if segment_name == 'MSH':
            print("│  Message Header Segment")
            print(f"│  • Field Separator: |")
            print(f"│  • Encoding Characters: {fields[1] if len(fields) > 1 else 'N/A'}")
            print(f"│  • Sending Application: {fields[2] if len(fields) > 2 else 'N/A'}")
            print(f"│  • Sending Facility: {fields[3] if len(fields) > 3 else 'N/A'}")
            print(f"│  • Receiving Application: {fields[4] if len(fields) > 4 else 'N/A'}")
            print(f"│  • Receiving Facility: {fields[5] if len(fields) > 5 else 'N/A'}")
            print(f"│  • Message DateTime: {fields[6] if len(fields) > 6 else 'N/A'}")
            print(f"│  • Security: {fields[7] if len(fields) > 7 else 'N/A'}")
            print(f"│  • Message Type: {fields[8] if len(fields) > 8 else 'N/A'}")
            print(f"│  • Message Control ID: {fields[9] if len(fields) > 9 else 'N/A'}")
            print(f"│  • Processing ID: {fields[10] if len(fields) > 10 else 'N/A'} (P=Production)")
            print(f"│  • Version: {fields[11] if len(fields) > 11 else 'N/A'}")
            
        elif segment_name == 'EVN':
            print("│  Event Type Segment")
            print(f"│  • Event Type Code: {fields[1] if len(fields) > 1 and fields[1] else '(empty)'}")
            print(f"│  • Recorded DateTime: {fields[2] if len(fields) > 2 else 'N/A'}")
            
        elif segment_name == 'PID':
            print("│  Patient Identification Segment")
            print(f"│  • Set ID: {fields[1] if len(fields) > 1 else 'N/A'}")
            print(f"│  • Patient ID (External): {fields[2] if len(fields) > 2 else 'N/A'}")
            patient_id = fields[3] if len(fields) > 3 else ''
            print(f"│  • Patient ID (Internal/MRN): {patient_id}")
            print(f"│  • Alternate Patient ID: {fields[4] if len(fields) > 4 else 'N/A'}")
            print(f"│  • Patient Name: {fields[5] if len(fields) > 5 else 'N/A'}")
            print(f"│  • Mother's Maiden Name: {fields[6] if len(fields) > 6 else 'N/A'}")
            print(f"│  • Date of Birth: {fields[7] if len(fields) > 7 else 'N/A'}")
            print(f"│  • Sex: {fields[8] if len(fields) > 8 else 'N/A'}")
            print(f"│  • Patient Alias: {fields[9] if len(fields) > 9 else 'N/A'}")
            print(f"│  • Race: {fields[10] if len(fields) > 10 else 'N/A'}")
            print(f"│  • Address: {fields[11] if len(fields) > 11 else 'N/A'}")
            print(f"│  • Religion: {fields[17] if len(fields) > 17 else 'N/A'}")
            if len(fields) > 30 and fields[30]:
                print(f"│  • Extended Info (Contact): {fields[30]}")
                
        elif segment_name == 'PV1':
            print("│  Patient Visit Segment")
            print(f"│  • Set ID: {fields[1] if len(fields) > 1 else 'N/A'}")
            print(f"│  • Patient Class: {fields[2] if len(fields) > 2 else 'N/A'}")
            print(f"│  • Assigned Location: {fields[3] if len(fields) > 3 else 'N/A'}")
            print(f"│  • Admission Type: {fields[4] if len(fields) > 4 else 'N/A'}")
            print(f"│  • Preadmit Number: {fields[5] if len(fields) > 5 else 'N/A'}")
            print(f"│  • Prior Patient Location: {fields[6] if len(fields) > 6 else 'N/A'}")
            print(f"│  • Attending Doctor: {fields[7] if len(fields) > 7 else 'N/A'}")
            print(f"│  • Referring Doctor: {fields[8] if len(fields) > 8 else 'N/A'}")
            print(f"│  • Consulting Doctor: {fields[9] if len(fields) > 9 else 'N/A'}")
            if len(fields) > 14:
                print(f"│  • Admitting Doctor: {fields[14] if fields[14] else 'N/A'}")
            if len(fields) > 15:
                print(f"│  • Visit Number: {fields[15] if fields[15] else 'N/A'}")
            if len(fields) > 38:
                print(f"│  • Diet Type: {fields[38] if fields[38] else 'N/A'}")
            if len(fields) > 40:
                print(f"│  • Bed Status: {fields[40] if fields[40] else 'N/A'}")
            if len(fields) > 44:
                print(f"│  • Admit DateTime: {fields[44] if fields[44] else 'N/A'}")
            
        elif segment_name == 'PV2':
            print("│  Patient Visit - Additional Information")
            if len(fields) > 9:
                print(f"│  • Expected Discharge DateTime: {fields[9] if fields[9] else 'N/A'}")
            if len(fields) > 10:
                print(f"│  • Estimated Length of Stay: {fields[10] if fields[10] else 'N/A'} days")
            if len(fields) > 22:
                print(f"│  • Visit Protection Indicator: {fields[22] if fields[22] else 'N/A'}")
            if len(fields) > 38:
                print(f"│  • Mode of Arrival: {fields[38] if fields[38] else 'N/A'}")
            
        elif segment_name == 'AL1':
            print("│  Allergy Information Segment")
            print(f"│  • Set ID: {fields[1] if len(fields) > 1 else 'N/A'}")
            allergy_type = fields[2] if len(fields) > 2 else ''
            type_desc = {'DA': 'Drug Allergy', 'FA': 'Food Allergy', 'MA': 'Miscellaneous Allergy'}
            print(f"│  • Allergy Type: {allergy_type} ({type_desc.get(allergy_type, 'Unknown')})")
            print(f"│  • Allergen: {fields[3] if len(fields) > 3 else 'N/A'}")
            severity = fields[4] if len(fields) > 4 else ''
            severity_desc = {'MI': 'Mild', 'MO': 'Moderate', 'SV': 'Severe', 'U': 'Unknown'}
            print(f"│  • Severity: {severity} ({severity_desc.get(severity, 'Unknown')})")
            
        elif segment_name == 'ZAT':
            print("│  Custom Z-Segment: Patient Attributes/Tags")
            print(f"│  • Attribute Code: {fields[1] if len(fields) > 1 else 'N/A'}")
            print(f"│  • Attribute Description: {fields[2] if len(fields) > 2 else 'N/A'}")
            if len(fields) > 1 and fields[1] == 'FR':
                print(f"│  ★ FALL RISK indicator detected!")
                
        elif segment_name == 'ZIT':
            print("│  Custom Z-Segment: Isolation Type")
            print(f"│  • Isolation Code: {fields[1] if len(fields) > 1 else 'N/A'}")
            print(f"│  • Isolation Description: {fields[2] if len(fields) > 2 else 'N/A'}")
                
        elif segment_name == 'ZFR':
            print("│  Custom Z-Segment: Fall Risk Flag")
            print(f"│  • Fall Risk Value: {fields[1] if len(fields) > 1 else 'N/A'}")
            if len(fields) > 1 and fields[1] == '1':
                print(f"│  ★ FALL RISK is ACTIVE!")
        
        print(f"└" + "─"*79)


def ask_for_ip_and_port():
    """
    Prompt user for IP address and port to send HL7 message to.
    Shows default values from .env as hints.
    Returns tuple of (host, port)
    """
    print("\n" + "="*80)
    print("CONNECTION SETTINGS")
    print("="*80)
    
    # Show defaults from .env
    print(f"\nDefault from .env: {DEFAULT_HOST}:{DEFAULT_PORT}")
    print("(Press Enter to use defaults, or enter custom values)\n")
    
    # Ask for IP address
    ip_input = input(f"Enter IP address or hostname [{DEFAULT_HOST}]: ").strip()
    host = ip_input if ip_input else DEFAULT_HOST
    
    # Ask for port
    while True:
        port_input = input(f"Enter port [{DEFAULT_PORT}]: ").strip()
        if not port_input:
            port = DEFAULT_PORT
            break
        try:
            port = int(port_input)
            if 1 <= port <= 65535:
                break
            else:
                print("  ⚠️  Port must be between 1 and 65535. Please try again.")
        except ValueError:
            print("  ⚠️  Invalid port number. Please enter a number between 1 and 65535.")
    
    return host, port


def main():
    """Main entry point"""
    print("""
╔══════════════════════════════════════════════════════════════════════════════╗
║                    Sample HL7 ADT A01 Message Sender                         ║
║                      SmartWard Healthcare Integration                        ║
╠══════════════════════════════════════════════════════════════════════════════╣
║  Message Type: ADT^A01 (Admit/Visit Notification)                            ║
║  HL7 Version: 2.8                                                            ║
╚══════════════════════════════════════════════════════════════════════════════╝
    """)
    
    # Get target from command line, interactive input, or environment
    if len(sys.argv) > 1:
        # Use command line arguments if provided
        host = sys.argv[1]
        port = int(sys.argv[2]) if len(sys.argv) > 2 else DEFAULT_PORT
    else:
        # Ask user for IP and port interactively
        host, port = ask_for_ip_and_port()
    
    print(f"\nTarget: {host}:{port}")
    
    # Print message breakdown
    print_message_breakdown(SAMPLE_ADT_A01)
    
    # Send message
    print("\n" + "="*80)
    print("SENDING MESSAGE")
    print("="*80)
    
    print(f"\n📤 Sending ADT^A01 message to {host}:{port}...")
    response = send_hl7_message(host, port, SAMPLE_ADT_A01)
    
    print("\n" + "="*80)
    print("RESPONSE FROM LISTENER")
    print("="*80)
    
    if 'ERROR' in response:
        print(f"\n❌ {response}")
    else:
        print(f"\n✅ ACK Received!")
        print(f"\n{response}")
        
        if 'AA' in response:
            print("\n✅ Status: Application Accept (AA) - Message processed successfully")
        elif 'AE' in response:
            print("\n⚠️  Status: Application Error (AE) - Message had errors")
        elif 'AR' in response:
            print("\n❌ Status: Application Reject (AR) - Message rejected")
    
    print("\n" + "="*80)


if __name__ == '__main__':
    main()





