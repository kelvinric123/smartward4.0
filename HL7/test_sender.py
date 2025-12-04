#!/usr/bin/env python3
"""
HL7 ADT Test Message Sender
Sends sample ADT messages to test the listener
"""

import socket
import sys
import os

# MLLP constants
MLLP_START_BLOCK = b'\x0b'
MLLP_END_BLOCK = b'\x1c'
MLLP_CARRIAGE_RETURN = b'\x0d'

# Sample ADT messages
SAMPLE_MESSAGES = {
    'A01': """MSH|^~\\&|HIS|HOSPITAL|SMARTWARD|ICU|20241204120000||ADT^A01|MSG00001|P|2.5
EVN|A01|20241204120000
PID|1||PAT001^^^HOSPITAL^MR||DOE^JOHN^WILLIAM||19850315|M|||123 MAIN ST^^KUALA LUMPUR^WP^50000||0123456789
PV1|1|I|ICU^101^A^HOSPITAL||||DR001^SMITH^JAMES^^^DR|||MED||||ADM|||||V001|||||||||||||||||||HOSPITAL|||||20241204120000""",

    'A02': """MSH|^~\\&|HIS|HOSPITAL|SMARTWARD|ICU|20241204130000||ADT^A02|MSG00002|P|2.5
EVN|A02|20241204130000
PID|1||PAT001^^^HOSPITAL^MR||DOE^JOHN^WILLIAM||19850315|M|||123 MAIN ST^^KUALA LUMPUR^WP^50000||0123456789
PV1|1|I|WARD3^201^B^HOSPITAL||||DR001^SMITH^JAMES^^^DR|||MED||||TRF|||||V001|||||||||||||||||||HOSPITAL|||||20241204120000""",

    'A03': """MSH|^~\\&|HIS|HOSPITAL|SMARTWARD|ICU|20241204140000||ADT^A03|MSG00003|P|2.5
EVN|A03|20241204140000
PID|1||PAT001^^^HOSPITAL^MR||DOE^JOHN^WILLIAM||19850315|M|||123 MAIN ST^^KUALA LUMPUR^WP^50000||0123456789
PV1|1|I|WARD3^201^B^HOSPITAL||||DR001^SMITH^JAMES^^^DR|||MED||||DIS|||||V001|||||||||||||||||||HOSPITAL|||||20241204120000|20241204140000""",

    'A04': """MSH|^~\\&|HIS|HOSPITAL|SMARTWARD|OPD|20241204150000||ADT^A04|MSG00004|P|2.5
EVN|A04|20241204150000
PID|1||PAT002^^^HOSPITAL^MR||AHMAD^SITI^BINTI||19900520|F|||456 JALAN PUTRA^^PETALING JAYA^SGR^47000||0198765432
PV1|1|O|OPD^CLINIC1^^HOSPITAL||||DR002^LEE^MEI^^^DR|||GEN||||REG|||||V002|||||||||||||||||||HOSPITAL|||||20241204150000""",

    'A08': """MSH|^~\\&|HIS|HOSPITAL|SMARTWARD|ICU|20241204160000||ADT^A08|MSG00005|P|2.5
EVN|A08|20241204160000
PID|1||PAT001^^^HOSPITAL^MR||DOE^JOHN^WILLIAM||19850315|M|||789 NEW ADDRESS^^SHAH ALAM^SGR^40000||0123456789
PV1|1|I|WARD3^201^B^HOSPITAL||||DR001^SMITH^JAMES^^^DR|||MED||||UPD|||||V001|||||||||||||||||||HOSPITAL|||||20241204120000""",
}


def send_hl7_message(host: str, port: int, message: str) -> str:
    """Send HL7 message via MLLP and return response"""
    try:
        # Create MLLP wrapped message
        mllp_message = MLLP_START_BLOCK + message.encode('utf-8') + MLLP_END_BLOCK + MLLP_CARRIAGE_RETURN
        
        # Connect and send
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as sock:
            sock.settimeout(10)
            sock.connect((host, port))
            sock.sendall(mllp_message)
            
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
            
            # Parse response
            if response:
                # Remove MLLP framing
                start_idx = response.find(MLLP_START_BLOCK)
                end_idx = response.find(MLLP_END_BLOCK)
                if start_idx >= 0 and end_idx > start_idx:
                    return response[start_idx + 1:end_idx].decode('utf-8', errors='replace')
            
            return "No response received"
            
    except ConnectionRefusedError:
        return "ERROR: Connection refused. Is the listener running?"
    except socket.timeout:
        return "ERROR: Connection timeout"
    except Exception as e:
        return f"ERROR: {str(e)}"


def print_menu():
    """Print interactive menu"""
    print("""
╔════════════════════════════════════════════════════════════╗
║           HL7 ADT Test Message Sender                      ║
╠════════════════════════════════════════════════════════════╣
║  Select a message type to send:                            ║
║                                                            ║
║  [1] A01 - Admit/Visit Notification                        ║
║  [2] A02 - Transfer a Patient                              ║
║  [3] A03 - Discharge/End Visit                             ║
║  [4] A04 - Register a Patient                              ║
║  [5] A08 - Update Patient Information                      ║
║  [6] Send ALL test messages                                ║
║  [7] Send custom message                                   ║
║  [0] Exit                                                  ║
╚════════════════════════════════════════════════════════════╝
    """)


def main():
    """Main entry point"""
    host = os.getenv('HL7_HOST', 'localhost')
    port = int(os.getenv('HL7_PORT', '3000'))
    
    print(f"\nConnecting to HL7 Listener at {host}:{port}")
    
    menu_map = {
        '1': 'A01',
        '2': 'A02',
        '3': 'A03',
        '4': 'A04',
        '5': 'A08',
    }
    
    while True:
        print_menu()
        choice = input("Enter your choice: ").strip()
        
        if choice == '0':
            print("\nGoodbye!")
            break
            
        elif choice in menu_map:
            event = menu_map[choice]
            message = SAMPLE_MESSAGES[event]
            print(f"\n{'='*60}")
            print(f"Sending ADT^{event} message...")
            print(f"{'='*60}")
            print(f"\nMessage:\n{message[:200]}...")
            print(f"\n{'─'*60}")
            response = send_hl7_message(host, port, message)
            print(f"Response:\n{response}")
            print(f"{'='*60}")
            
        elif choice == '6':
            print(f"\n{'='*60}")
            print("Sending ALL test messages...")
            print(f"{'='*60}")
            for event, message in SAMPLE_MESSAGES.items():
                print(f"\n[{event}] Sending...")
                response = send_hl7_message(host, port, message)
                if 'AA' in response:
                    print(f"[{event}] ✓ ACK received")
                else:
                    print(f"[{event}] ✗ {response}")
            print(f"\n{'='*60}")
            print("All messages sent!")
            print(f"{'='*60}")
            
        elif choice == '7':
            print("\nEnter your HL7 message (paste and press Enter twice to send):")
            lines = []
            while True:
                line = input()
                if not line:
                    break
                lines.append(line)
            
            if lines:
                message = '\r'.join(lines)
                print(f"\n{'─'*60}")
                response = send_hl7_message(host, port, message)
                print(f"Response:\n{response}")
                print(f"{'='*60}")
            else:
                print("No message entered.")
                
        else:
            print("\nInvalid choice. Please try again.")
        
        input("\nPress Enter to continue...")


if __name__ == '__main__':
    main()

