#!/usr/bin/env python3
"""
B.Braun HL7 Sample Sender 2
Sends sample HL7 messages to the B.Braun HL7 listener for testing different scenarios
Allows manual entry of Serial Number
"""

import socket
import os
import sys
import time
from datetime import datetime

# MLLP (Minimal Lower Layer Protocol) constants
MLLP_START_BLOCK = b'\x0b'  # VT (Vertical Tab)
MLLP_END_BLOCK = b'\x1c'    # FS (File Separator)
MLLP_CARRIAGE_RETURN = b'\x0d'  # CR

# Directory containing sample files
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
SAMPLES_DIR = os.path.join(SCRIPT_DIR, 'samples')

# Sample configurations
SAMPLES = {
    '1': {
        'file': 'sample1.txt',
        'name': 'PCD-01 During Infusion',
        'description': 'Observation result during active infusion with Dobutamine'
    },
    '2': {
        'file': 'sample2.txt',
        'name': 'PCD-04 Syringe Holder Alarm Sequence',
        'description': 'Full alarm sequence: active -> muted -> minimized -> cleared'
    },
    '3': {
        'file': 'sample3.txt',
        'name': 'Sample 3',
        'description': 'Additional sample message'
    },
    '4': {
        'file': 'sample4.txt',
        'name': 'Sample 4',
        'description': 'Additional sample message'
    },
    '5': {
        'file': 'sample5.txt',
        'name': 'Sample 5',
        'description': 'Additional sample message'
    },
    '6': {
        'file': 'sample6_real.txt',
        'name': 'Sample 6 (Real)',
        'description': 'Real sample message from B.Braun SpacePlus'
    }
}

# Preset destinations
DESTINATIONS = {
    '1': ('localhost', 5001, 'Localhost (127.0.0.1:5001)'),
    '2': ('10.21.20.114', 5001, 'Remote Server (10.21.20.114:5001)'),
    '3': (None, None, 'Custom IP and Port'),
}


def print_header():
    """Print the application header"""
    print("\n" + "=" * 60)
    print("    B.Braun HL7 Sample Sender 2")
    print("    Sends test HL7 messages to the listener")
    print("    Allows overriding Serial Number")
    print("=" * 60 + "\n")


def print_menu(title, options):
    """Print a menu with options"""
    print(f"\n{title}")
    print("-" * 40)
    for key, value in options.items():
        if isinstance(value, dict):
            print(f"  [{key}] {value['name']}")
            print(f"       {value['description']}")
        elif isinstance(value, tuple):
            print(f"  [{key}] {value[2]}")
    print()


def get_destination():
    """Get the destination server from user input"""
    print_menu("Select Destination:", DESTINATIONS)
    
    while True:
        choice = input("Enter choice (1-3): ").strip()
        
        if choice == '1':
            return 'localhost', 5001
        elif choice == '2':
            return '10.21.20.114', 5001
        elif choice == '3':
            # Custom IP and port
            while True:
                ip = input("Enter IP address: ").strip()
                if ip:
                    break
                print("Please enter a valid IP address.")
            
            while True:
                port_str = input("Enter port (default: 5001): ").strip()
                if not port_str:
                    port = 5001
                    break
                try:
                    port = int(port_str)
                    if 1 <= port <= 65535:
                        break
                    print("Port must be between 1 and 65535.")
                except ValueError:
                    print("Please enter a valid port number.")
            
            return ip, port
        else:
            print("Invalid choice. Please enter 1, 2, or 3.")


def get_serial_number():
    """Get the serial number from user input"""
    print("\nSelect Serial Number:")
    print("-" * 40)
    print("  Enter new Serial No to override the one in the sample file.")
    print("  Leave empty to keep the original Serial No.")
    print()
    
    serial_no = input("Enter Serial No: ").strip()
    return serial_no


def get_sample():
    """Get the sample file from user input"""
    print_menu("Select Sample Message:", SAMPLES)
    
    # Add option to send all samples
    print("  [A] Send ALL samples (one by one)")
    print("  [R] Paste RAW HL7 message")
    print()
    
    while True:
        choice = input("Enter choice (1-6, A, or R): ").strip().upper()
        
        if choice in SAMPLES:
            return [choice]
        elif choice == 'A':
            return list(SAMPLES.keys())
        elif choice == 'R':
            return ['RAW']
        else:
            print("Invalid choice. Please enter 1-6, A, or R.")


def extract_hl7_messages(content):
    """Extract HL7 messages from sample file content.
    
    Handles both properly formatted (one segment per line) and 
    concatenated single-line HL7 messages by splitting on known segment identifiers.
    """
    import re
    
    messages = []
    lines = content.split('\n')
    
    # Known HL7 segment identifiers
    SEGMENT_IDS = ('MSH|', 'PID|', 'PV1|', 'OBR|', 'OBX|', 'RXE|', 'RXG|', 'RXR|', 'NTE|', 'PD1|', 'EVN|', 'ORC|')
    
    # Pre-process: split concatenated segments within each line
    # e.g. "MSH|...|YOBR|...|OBX|..." -> ["MSH|...|Y", "OBR|...|", "OBX|..."]
    expanded_lines = []
    for line in lines:
        line = line.strip()
        if not line:
            expanded_lines.append('')
            continue
        
        # Use regex to split before known segment identifiers
        # This handles cases where segments are concatenated without \r separators
        parts = re.split(r'(?=(?:MSH|PID|PV1|OBR|OBX|RXE|RXG|RXR|NTE|PD1|EVN|ORC)\|)', line)
        for part in parts:
            part = part.strip()
            if part:
                expanded_lines.append(part)
    
    current_message = []
    
    for line in expanded_lines:
        # HL7 message segments start with these standard segment identifiers
        if line.startswith(SEGMENT_IDS):
            if line.startswith('MSH|') and current_message:
                # Start of new message - save the previous one
                messages.append('\r'.join(current_message))
                current_message = []
            current_message.append(line)
        elif current_message and not line:
            # Empty line after a message
            if current_message:
                messages.append('\r'.join(current_message))
                current_message = []
    
    # Don't forget the last message
    if current_message:
        messages.append('\r'.join(current_message))
    
    return messages


def get_raw_hl7_input():
    """Get raw HL7 message input from the user"""
    print("\nPaste your raw HL7 message below.")
    print("To finish, enter an empty line (press Enter twice).")
    print("-" * 40)
    
    lines = []
    while True:
        try:
            line = input()
            if not line:
                break
            lines.append(line)
        except EOFError:
            break
            
    return '\n'.join(lines)


def update_message_serial_no(message, new_serial_no):
    """Update the Serial Number in the HL7 message"""
    if not new_serial_no:
        return message
        
    segments = message.split('\r')
    updated_segments = []
    
    for segment in segments:
        if segment.startswith('OBX|'):
            fields = segment.split('|')
            # Check if this OBX segment has field 18 (Equipment Identifier)
            # OBX field index 18 corresponds to index 18 (0-indexed split) because split includes 'OBX' as 0
            # Wait: OBX|1|ST|...
            # 0: OBX
            # 1: Set ID
            # 2: Value Type
            # ...
            # 18: Equipment Instance Identifier
            
            if len(fields) > 18:
                equipment_field = fields[18]
                # If field is not empty, replace the first component with new serial no
                if equipment_field:
                    parts = equipment_field.split('^')
                    parts[0] = new_serial_no
                    fields[18] = '^'.join(parts)
                    segment = '|'.join(fields)
                elif len(fields) >= 19: # If field is empty but segment is long enough
                    # Construct a basic equipment ID
                    fields[18] = f"{new_serial_no}^^0000000000000000^EUI-64"
                    segment = '|'.join(fields)
            
        updated_segments.append(segment)
    
    return '\r'.join(updated_segments)


def send_mllp_message(host, port, message):
    """Send a single HL7 message using MLLP protocol"""
    try:
        # Create socket
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(10)  # 10 second timeout
        
        # Connect to server
        print(f"    Connecting to {host}:{port}...")
        sock.connect((host, port))
        
        # Wrap message in MLLP envelope
        mllp_message = MLLP_START_BLOCK + message.encode('utf-8') + MLLP_END_BLOCK + MLLP_CARRIAGE_RETURN
        
        # Send message
        sock.sendall(mllp_message)
        print(f"    Sent {len(message)} bytes")
        
        # Wait for acknowledgment (optional, some listeners may not send ACK)
        try:
            sock.settimeout(2)
            ack = sock.recv(4096)
            if ack:
                print(f"    Received ACK: {len(ack)} bytes")
        except socket.timeout:
            print("    No ACK received (timeout)")
        except Exception as e:
            print(f"    ACK error: {e}")
        
        sock.close()
        return True
        
    except socket.error as e:
        print(f"    Socket error: {e}")
        return False
    except Exception as e:
        print(f"    Error: {e}")
        return False


def send_sample(host, port, sample_key, serial_no=None):
    """Send a sample file to the HL7 listener"""
    sample = SAMPLES[sample_key]
    sample_file = os.path.join(SAMPLES_DIR, sample['file'])
    
    print(f"\n{'='*50}")
    print(f"Sending: {sample['name']}")
    print(f"File: {sample['file']}")
    if serial_no:
        print(f"Overriding Serial No: {serial_no}")
    print(f"{'='*50}")
    
    # Check if file exists
    if not os.path.exists(sample_file):
        print(f"  ERROR: Sample file not found: {sample_file}")
        return False
    
    # Read file content
    with open(sample_file, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Extract HL7 messages from the sample file
    messages = extract_hl7_messages(content)
    
    if not messages:
        print("  No HL7 messages found in sample file!")
        return False
    
    print(f"  Found {len(messages)} HL7 message(s)")
    
    # Send each message
    success_count = 0
    for i, message in enumerate(messages, 1):
        print(f"\n  Message {i}/{len(messages)}:")
        
        # Update Serial Number if provided
        if serial_no:
            message = update_message_serial_no(message, serial_no)
        
        # Show first line of message (MSH segment) for context
        first_line = message.split('\r')[0][:80]
        print(f"    {first_line}...")
        
        # Show updated serial no if applicable
        if serial_no:
             # Find OBX with serial no for confirmation
             lines = message.split('\r')
             for line in lines:
                 if line.startswith('OBX|') and serial_no in line:
                     print(f"    Updated OBX: {line[:80]}...")
                     break

        if send_mllp_message(host, port, message):
            success_count += 1
        
        # Small delay between messages
        if i < len(messages):
            time.sleep(0.5)
    
    print(f"\n  Result: {success_count}/{len(messages)} messages sent successfully")
    return success_count == len(messages)


def send_raw_input(host, port, serial_no=None):
    """Get raw input and send it"""
    raw_content = get_raw_hl7_input()
    
    if not raw_content:
        print("  No message entered. Aborting.")
        return False
        
    messages = extract_hl7_messages(raw_content)
    
    if not messages:
        print("  No valid HL7 segments found. Aborting.")
        return False
        
    print(f"\n{'='*50}")
    print(f"Sending RAW Message")
    if serial_no:
        print(f"Overriding Serial No: {serial_no}")
    print(f"{'='*50}")
    print(f"  Found {len(messages)} HL7 message(s)")
    
    success_count = 0
    for i, message in enumerate(messages, 1):
        print(f"\n  Message {i}/{len(messages)}:")
        
        if serial_no:
            message = update_message_serial_no(message, serial_no)
            
        first_line = message.split('\r')[0][:80]
        print(f"    {first_line}...")
        
        if send_mllp_message(host, port, message):
            success_count += 1
            
        if i < len(messages):
            time.sleep(0.5)
            
    print(f"\n  Result: {success_count}/{len(messages)} messages sent successfully")
    return success_count == len(messages)


def main():
    """Main function"""
    print_header()
    
    # Get destination
    host, port = get_destination()
    print(f"\nDestination: {host}:{port}")
    
    # Get Serial Number
    serial_no = get_serial_number()
    if serial_no:
        print(f"Using Serial No: {serial_no}")
    else:
        print("Using default Serial No from file")
    
    # Get sample(s) to send
    sample_keys = get_sample()
    
    # Confirm before sending
    print(f"\nReady to send {len(sample_keys)} sample(s) to {host}:{port}")
    if serial_no:
        print(f"With Serial No override: {serial_no}")
        
    confirm = input("Proceed? (y/n): ").strip().lower()
    
    if confirm != 'y':
        print("Cancelled.")
        return
    
    # Send samples
    print(f"\n{'#'*60}")
    print(f"  Starting transmission at {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"{'#'*60}")
    
    total_success = 0
    for sample_key in sample_keys:
        if sample_key == 'RAW':
            if send_raw_input(host, port, serial_no):
                total_success += 1
        elif send_sample(host, port, sample_key, serial_no):
            total_success += 1
        time.sleep(1)  # Delay between samples
    
    # Summary
    print(f"\n{'#'*60}")
    print(f"  Transmission Complete")
    print(f"  Samples sent: {total_success}/{len(sample_keys)}")
    print(f"  Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"{'#'*60}\n")


if __name__ == '__main__':
    try:
        main()
    except KeyboardInterrupt:
        print("\n\nCancelled by user.")
        sys.exit(0)
