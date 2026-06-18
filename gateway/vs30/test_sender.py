"""
Test sender for VS30 HL7 Listener.
Sends a sample HL7 ORU^R01 message wrapped in MLLP framing.

Usage:
    python test_sender.py
    python test_sender.py --host 127.0.0.1 --port 4602
"""

import socket
import argparse
import sys


# MLLP framing characters
SB = b'\x0b'  # Start Block
EB = b'\x1c'  # End Block
CR = b'\x0d'  # Carriage Return


def create_sample_hl7_message():
    """Creates a sample HL7 ORU^R01 message with vital signs."""
    segments = [
        "MSH|^~\\&|VS30|MONITOR|LISTENER|GATEWAY|20231025103000||ORU^R01|MSG001|P|2.3",
        "PID|||PAT001||Doe^John^^^Mr",
        "PV1||I|ICU^001^01",
        "OBR|1|||VITALS|||20231025103000",
        "OBX|1|NM|8867-4^Heart Rate||78|bpm|60-100||||F",
        "OBX|2|NM|2708-6^SpO2||97|%|95-100||||F",
        "OBX|3|NM|8480-6^Systolic BP||122|mmHg|90-140||||F",
        "OBX|4|NM|8462-4^Diastolic BP||78|mmHg|60-90||||F",
        "OBX|5|NM|8310-5^Temperature||36.8|Cel|36.1-37.2||||F",
        "OBX|6|NM|9279-1^Respiratory Rate||16|/min|12-20||||F",
    ]
    
    # HL7 uses \r as segment separator
    return "\r".join(segments) + "\r"


def wrap_mllp(message_str):
    """Wraps a message string in MLLP framing."""
    return SB + message_str.encode('utf-8') + EB + CR


def send_message(host, port, message):
    """Sends an MLLP-wrapped HL7 message and waits for ACK."""
    wrapped = wrap_mllp(message)
    
    print(f"Connecting to {host}:{port}...")
    
    try:
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as sock:
            sock.settimeout(10)
            sock.connect((host, port))
            print(f"Connected! Sending HL7 message ({len(wrapped)} bytes)...")
            print(f"\n--- Sent Message ---")
            print(message)
            print(f"--- End Message ---\n")
            
            sock.sendall(wrapped)
            
            # Wait for ACK
            print("Waiting for ACK...")
            response = sock.recv(4096)
            
            if response:
                # Strip MLLP framing from response
                ack = response
                if ack.startswith(SB):
                    ack = ack[1:]
                if ack.endswith(EB + CR):
                    ack = ack[:-2]
                
                ack_str = ack.decode('utf-8', errors='ignore')
                print(f"\n--- Received ACK ---")
                print(ack_str)
                print(f"--- End ACK ---\n")
                print("SUCCESS: Message sent and ACK received!")
            else:
                print("WARNING: No ACK received (empty response)")
                
    except socket.timeout:
        print("ERROR: Connection timed out waiting for ACK")
        sys.exit(1)
    except ConnectionRefusedError:
        print(f"ERROR: Connection refused. Is the listener running on {host}:{port}?")
        sys.exit(1)
    except Exception as e:
        print(f"ERROR: {e}")
        sys.exit(1)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Test sender for VS30 HL7 Listener")
    parser.add_argument('--host', default='127.0.0.1', help='Listener host (default: 127.0.0.1)')
    parser.add_argument('--port', type=int, default=4602, help='Listener port (default: 4602)')
    args = parser.parse_args()
    
    msg = create_sample_hl7_message()
    send_message(args.host, args.port, msg)
