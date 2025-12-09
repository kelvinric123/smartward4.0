#!/usr/bin/env python3
"""
Sample HL7 ADT A01 Message Sender #2
Sends the exact HL7 message format from CEREBRALPLUS HIS system to test the ADT listener
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
# SAMPLE HL7 ADT A01 MESSAGE FROM CEREBRALPLUS HIS
# This is the exact format received from the HIS system
# =============================================================================

SAMPLE_ADT_A01 = """MSH|^~\\&|CEREBRALPLUS|PHKL|IWARD|IWARD|20251209125140|eY1ckLPBisToLugFLXxFJqZMj2grMH6TkZwhoukZ|ADT^A01^ADT_A01|54169.0|T|2.4
EVN|A01|20251209125109||||
PID|1||3500493762^^^^MR|ICN^970629465102|NUR AINUN SYAKIRAH BINTI ISMAIL  ||19970629|F||100|  ^^Others^Others^N/A^ZZZ|ZZZ|0^0105656947||||99|||||||||||MAL|
PV1|1|I|WWD6^D610^D610|||WWD6^WARD D6 (MEDICAL & SURGICAL)|DKAMJIT^KAMALJIT KAUR D/O HARBAN SINGH||||||||||||PHKL25IP12000006|15^4^1^C000020027~15^1^99^||||||||||||||||||||||||20251209125108|
NK1|1||||||||||||||||||||||||||||||||||"""


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
            print(f"│  • Processing ID: {fields[10] if len(fields) > 10 else 'N/A'} (T=Testing, P=Production)")
            print(f"│  • Version: {fields[11] if len(fields) > 11 else 'N/A'}")
            
        elif segment_name == 'EVN':
            print("│  Event Type Segment")
            print(f"│  • Event Type Code: {fields[1] if len(fields) > 1 and fields[1] else '(empty)'}")
            print(f"│  • Recorded DateTime: {fields[2] if len(fields) > 2 else 'N/A'}")
            
        elif segment_name == 'PID':
            print("│  Patient Identification Segment")
            print(f"│  • Set ID: {fields[1] if len(fields) > 1 else 'N/A'}")
            print(f"│  • Patient ID (External): {fields[2] if len(fields) > 2 else 'N/A'}")
            
            # PID-3: MRN in format 3500493762^^^^MR
            patient_id = fields[3] if len(fields) > 3 else ''
            mrn = ''
            if patient_id:
                if '^^^^' in patient_id:
                    mrn = patient_id.split('^^^^')[0]
                else:
                    mrn = patient_id
            print(f"│  • Patient ID (Internal/MRN): {patient_id}")
            if mrn:
                print(f"│    ★ Extracted MRN: {mrn}")
            
            # PID-4 contains ICN (IC Number) in format ICN^970629465102
            icn_field = fields[4] if len(fields) > 4 else ''
            print(f"│  • Alternate Patient ID: {icn_field}")
            ic_number = ''
            if icn_field and '^' in icn_field:
                parts = icn_field.split('^')
                id_types = ['ICN', 'IC', 'NRIC', 'PP', 'PASSPORT', 'PPN']
                if len(parts) >= 2 and parts[0].upper() in id_types:
                    ic_number = parts[1]
                    print(f"│    ★ ID Type: {parts[0]} (IC Number)")
                    print(f"│    ★ IC/Passport Number: {ic_number}")
            
            print(f"│  • Patient Name: {fields[5] if len(fields) > 5 else 'N/A'}")
            print(f"│  • Mother's Maiden Name: {fields[6] if len(fields) > 6 else 'N/A'}")
            print(f"│  • Date of Birth: {fields[7] if len(fields) > 7 else 'N/A'}")
            print(f"│  • Sex: {fields[8] if len(fields) > 8 else 'N/A'}")
            print(f"│  • Patient Alias: {fields[9] if len(fields) > 9 else 'N/A'}")
            print(f"│  • Race: {fields[10] if len(fields) > 10 else 'N/A'}")
            print(f"│  • Address: {fields[11] if len(fields) > 11 else 'N/A'}")
            print(f"│  • Country Code: {fields[12] if len(fields) > 12 else 'N/A'}")
            
            # Phone in format 0^0105656947
            phone_field = fields[13] if len(fields) > 13 else ''
            print(f"│  • Phone (Home): {phone_field}")
            if phone_field and '^' in phone_field:
                phone_parts = phone_field.split('^')
                for part in phone_parts:
                    cleaned = part.replace('-', '').replace(' ', '')
                    if len(cleaned) >= 8 and cleaned.isdigit():
                        print(f"│    ★ Extracted Phone: {part}")
                        break
            
            print(f"│  • Religion: {fields[17] if len(fields) > 17 else 'N/A'}")
            print(f"│  • Nationality: {fields[28] if len(fields) > 28 else 'N/A'}")
                
        elif segment_name == 'PV1':
            print("│  Patient Visit Segment")
            print(f"│  • Set ID: {fields[1] if len(fields) > 1 else 'N/A'}")
            patient_class = fields[2] if len(fields) > 2 else ''
            class_desc = {'I': 'Inpatient', 'O': 'Outpatient', 'E': 'Emergency', 'R': 'Recurring'}
            print(f"│  • Patient Class: {patient_class} ({class_desc.get(patient_class, 'Unknown')})")
            print(f"│  • Assigned Location: {fields[3] if len(fields) > 3 else 'N/A'}")
            print(f"│  • Admission Type: {fields[4] if len(fields) > 4 else 'N/A'}")
            print(f"│  • Preadmit Number: {fields[5] if len(fields) > 5 else 'N/A'}")
            print(f"│  • Prior Patient Location: {fields[6] if len(fields) > 6 else 'N/A'}")
            print(f"│  • Attending Doctor: {fields[7] if len(fields) > 7 else 'N/A'}")
            if len(fields) > 19:
                print(f"│  • Visit Number: {fields[19] if fields[19] else 'N/A'}")
            if len(fields) > 20:
                print(f"│  • Financial Class: {fields[20] if fields[20] else 'N/A'}")
            if len(fields) > 44:
                print(f"│  • Admit DateTime: {fields[44] if fields[44] else 'N/A'}")
            
        elif segment_name == 'NK1':
            print("│  Next of Kin Segment")
            print(f"│  • Set ID: {fields[1] if len(fields) > 1 else 'N/A'}")
            print(f"│  • Name: {fields[2] if len(fields) > 2 else 'N/A'}")
            print(f"│  • Relationship: {fields[3] if len(fields) > 3 else 'N/A'}")
        
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
║               Sample HL7 ADT A01 Message Sender #2                           ║
║                   CEREBRALPLUS HIS Integration                               ║
╠══════════════════════════════════════════════════════════════════════════════╣
║  Message Type: ADT^A01 (Admit/Visit Notification)                            ║
║  HL7 Version: 2.4                                                            ║
║  Source: CEREBRALPLUS HIS → IWARD                                            ║
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

