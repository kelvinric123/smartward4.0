#!/usr/bin/env python3
"""
HL7 ADT Sample Message Sender
Based on: HL7 ADT Integration Document_ADT ONLY_24112025_v1.0

This sender allows you to test all ADT message types from the integration document:
- ADT^A01 - Admit/Register Patient
- ADT^A02 - Transfer Patient
- ADT^A03 - Discharge/End Visit
- ADT^A08 - Update Patient Details
- ADT^A11 - Cancel Admit/Cancel Visit
- ADT^A13 - Cancel Discharge
- ADT^A16 - Pending Discharge
- ADT^A25 - Cancel Pending Discharge

HL7 Version: 2.4
Transport: MLLP (TCP/IP)
Source System: CEREBRALPLUS

All samples use the SAME patient identifiers for realistic case simulation:
- MRN: 3300746940
- IC/Passport: PP^N7356938
- Name: TEST SST PATIENT
- DOB: 19920409
- Sex: M
- Visit Number: PHKL25IP11000009
"""

import socket
import sys
import os

# MLLP (Minimal Lower Layer Protocol) constants
MLLP_START_BLOCK = b'\x0b'  # VT (Vertical Tab)
MLLP_END_BLOCK = b'\x1c'    # FS (File Separator)
MLLP_CARRIAGE_RETURN = b'\x0d'  # CR

# Configuration
DEFAULT_HOST = os.getenv('HL7_HOST', 'localhost')
DEFAULT_PORT = int(os.getenv('HL7_PORT', '3000'))


# =============================================================================
# CONSISTENT PATIENT IDENTIFIERS FOR ALL MESSAGES
# =============================================================================
# MRN:           3300746940
# IC/Passport:   PP^N7356938
# Name:          TEST SST PATIENT
# DOB:           19920409
# Sex:           M
# Race:          00
# Address:       Test^^Ayer Hitam^Johor^N/A^MYS
# Country:       MYS
# Phone:         0^06128764
# Religion:      0
# Marital:       99
# Nationality:   IND
# Visit Number:  PHKL25IP11000009
# Ward:          WWC7 (WARD C7 - EXECUTIVE WARD)
# Doctor:        DALEXLHR^ALEX LEOW HWONG RUEY
# =============================================================================


SAMPLE_MESSAGES = {
    # =========================================================================
    # ADT^A01 - Admit/Register Patient
    # Description: Creates a new patient visit and assigns the patient to the 
    #              designated location/ward.
    # STEP 1: Patient is admitted to Ward C7, Bed C706
    # =========================================================================
    'A01': {
        'name': 'ADT^A01 - Admit/Register Patient',
        'description': 'Creates a new patient visit and assigns the patient to the designated location/ward.',
        'message': """MSH|^~\\&|CEREBRALPLUS|PHKL|IWARD|IWARD|20251117080000||ADT^A01^ADT_A01|50690.0|T|2.4
EVN|A01|20251117080000||||
PID|1||3300746940^^^^MR|PP^N7356938|TEST SST PATIENT||19920409|M||00|Test^^Ayer Hitam^Johor^N/A^MYS|MYS|0^06128764|||0|99||||||||||||IND|
PV1|1|I|WWC7^C706^C706|||WWC7^WARD C7 (EXECUTIVE WARD)|DALEXLHR^ALEX LEOW HWONG RUEY|||||||||||||PHKL25IP11000009|15^4^1^C000020027~15^1^99^||||||||||||||||||||||||||||20251117080000|
NK1|1||||||||||||||||||||||||||||||||||||||"""
    },

    # =========================================================================
    # ADT^A02 - Transfer Patient
    # Description: Transfers a patient to another ward, room, or bed.
    # STEP 2: Patient is transferred from C706 to D610 in Ward D6
    # =========================================================================
    'A02': {
        'name': 'ADT^A02 - Transfer Patient',
        'description': 'Transfers a patient to another ward, room, or bed.',
        'message': """MSH|^~\\&|CEREBRALPLUS|PHKL|IWARD|IWARD|20251117100000||ADT^A02^ADT_A02|50691.0|T|2.4
EVN|A02|20251117100000|||||
PID|1||3300746940^^^^MR|PP^N7356938|TEST SST PATIENT||19920409|M||00|Test^^Ayer Hitam^Johor^N/A^MYS|MYS|0^06128764|||0|99||||||||||||IND|
PV1|1|I|WWD6^D610^D610|||WWD6^WARD D6 (MEDICAL \\& SURGICAL)|DKAMJIT^KAMALJIT KAUR D/O HARBAN SINGH|||||||||||||PHKL25IP11000009|15^4^1^C000020027~15^1^99^||||||||||||||||||||||||||||20251117080000|||||||"""
    },

    # =========================================================================
    # ADT^A08 - Update Patient Details
    # Description: Updates patient demographics, contact details, diet, or 
    #              isolation status.
    # STEP 3: Update patient with diet and isolation information
    # =========================================================================
    'A08': {
        'name': 'ADT^A08 - Update Patient Details',
        'description': 'Updates patient demographics, contact details, diet, or isolation status.',
        'message': """MSH|^~\\&|CEREBRALPLUS|PHKL|IWARD|IWARD|20251117120000||ADT^A08^ADT_A08|50692.0|T|2.4
EVN|A08|20251117120000|||||
PID|1||3300746940^^^^MR|PP^N7356938|TEST SST PATIENT||19920409|M||00|Test^^Ayer Hitam^Johor^N/A^MYS|MYS|0^06128764|||0|99||||||||||||IND|
PV1|1|I|WWD6^D610^D610|||WWD6^WARD D6 (MEDICAL \\& SURGICAL)|DKAMJIT^KAMALJIT KAUR D/O HARBAN SINGH|||||||||||||PHKL25IP11000009|15^4^1^C000020027~15^1^99^||||||||||||||||||||DMD, REGD^DIABETIC DIET, REGULAR DIET|||||||||
NK1|1|^AHMAD|40^Son|Test^^Ayer Hitam^Johor^N/A^MYS|60123456789||||||||||||||||||||||||||||||||||||111103149999
RMI|0|||CI^Contact Isolation||||||||||||||||||||||||||||||||||"""
    },

    # =========================================================================
    # ADT^A16 - Pending Discharge
    # Description: Indicates the patient is awaiting discharge.
    # STEP 4: Patient is flagged as pending discharge
    # =========================================================================
    'A16': {
        'name': 'ADT^A16 - Pending Discharge',
        'description': 'Indicates the patient is awaiting discharge.',
        'message': """MSH|^~\\&|CEREBRALPLUS|PHKL|IWARD|IWARD|20251117140000||ADT^A16^ADT_A16|50693.0|T|2.4
EVN|A16|20251117140000|||||
PID|1||3300746940^^^^MR|PP^N7356938|TEST SST PATIENT||19920409|M||00|Test^^Ayer Hitam^Johor^N/A^MYS|MYS|0^06128764|||0|99||||||||||||IND|
PV1|1|I|WWD6^D610^D610|||WWD6^WARD D6 (MEDICAL \\& SURGICAL)|DKAMJIT^KAMALJIT KAUR D/O HARBAN SINGH|||||||||||||PHKL25IP11000009|15^4^1^C000020027~15^1^99^||||||||||||||||||||||||||20251117080000||||||||"""
    },

    # =========================================================================
    # ADT^A25 - Cancel Pending Discharge
    # Description: Reverses a pending discharge previously flagged by an A16.
    # STEP 4B (Optional): Cancel the pending discharge
    # =========================================================================
    'A25': {
        'name': 'ADT^A25 - Cancel Pending Discharge',
        'description': 'Reverses a pending discharge previously flagged by an A16.',
        'message': """MSH|^~\\&|CEREBRALPLUS|PHKL|IWARD|IWARD|20251117143000||ADT^A25^ADT_A25|50694.0|T|2.4
EVN|A25|20251117143000||||
PID|1||3300746940^^^^MR|PP^N7356938|TEST SST PATIENT||19920409|M||00|Test^^Ayer Hitam^Johor^N/A^MYS|MYS|0^06128764|||0|99||||||||||||IND|
PV1|1|I|WWD6^D610^D610|||WWD6^WARD D6 (MEDICAL \\& SURGICAL)|DKAMJIT^KAMALJIT KAUR D/O HARBAN SINGH|||||||||||||PHKL25IP11000009|15^4^1^C000020027~15^1^99^||||||||||||||||||||||||||20251117080000|||||||||"""
    },

    # =========================================================================
    # ADT^A03 - Discharge/End Visit
    # Description: Indicates the patient has been discharged and the visit is 
    #              considered closed.
    # STEP 5: Patient is discharged
    # =========================================================================
    'A03': {
        'name': 'ADT^A03 - Discharge/End Visit',
        'description': 'Indicates the patient has been discharged and the visit is considered closed.',
        'message': """MSH|^~\\&|CEREBRALPLUS|PHKL|IWARD|IWARD|20251117160000||ADT^A03^ADT_A03|50695.0|T|2.4
EVN|A03|20251117160000|||||
PID|1||3300746940^^^^MR|PP^N7356938|TEST SST PATIENT||19920409|M||00|Test^^Ayer Hitam^Johor^N/A^MYS|MYS|0^06128764|||0|99||||||||||||IND|
PV1|1|I|WWD6^D610^D610|||WWD6^WARD D6 (MEDICAL \\& SURGICAL)|DKAMJIT^KAMALJIT KAUR D/O HARBAN SINGH|||||||||||||PHKL25IP11000009|15^4^1^C000020027~15^1^99^|||||||||||||||||||||||||||20251117080000|20251117160000||||||||"""
    },

    # =========================================================================
    # ADT^A13 - Cancel Discharge
    # Description: Reopens a visit by cancelling a prior discharge event.
    # STEP 5B (Optional): Cancel the discharge, reopen visit
    # =========================================================================
    'A13': {
        'name': 'ADT^A13 - Cancel Discharge',
        'description': 'Reopens a visit by cancelling a prior discharge event.',
        'message': """MSH|^~\\&|CEREBRALPLUS|PHKL|IWARD|IWARD|20251117170000||ADT^A13^ADT_A13|50696.0|T|2.4
EVN|A13|20251117170000|||||
PID|1||3300746940^^^^MR|PP^N7356938|TEST SST PATIENT||19920409|M||00|Test^^Ayer Hitam^Johor^N/A^MYS|MYS|0^06128764|||0|99||||||||||||IND|
PV1|1|I|WWD6^D610^D610|||WWD6^WARD D6 (MEDICAL \\& SURGICAL)|DKAMJIT^KAMALJIT KAUR D/O HARBAN SINGH|||||||||||||PHKL25IP11000009|15^4^1^C000020027~15^1^99^||||||||||||||||||||||||||20251117080000||||||||"""
    },

    # =========================================================================
    # ADT^A11 - Cancel Admit/Cancel Visit
    # Description: Reverses a previously sent A01 message.
    # SPECIAL: Cancel the entire admission (use for error correction)
    # =========================================================================
    'A11': {
        'name': 'ADT^A11 - Cancel Admit/Cancel Visit',
        'description': 'Reverses a previously sent A01 message.',
        'message': """MSH|^~\\&|CEREBRALPLUS|PHKL|IWARD|IWARD|20251117180000||ADT^A11^ADT_A11|50697.0|T|2.4
EVN|A11|20251117180000||||
PID|1||3300746940^^^^MR|PP^N7356938|TEST SST PATIENT||19920409|M||00|Test^^Ayer Hitam^Johor^N/A^MYS|MYS|0^06128764|||0|99||||||||||||IND|
PV1|1|I|WWC7^C706^C706|||WWC7^WARD C7 (EXECUTIVE WARD)|DALEXLHR^ALEX LEOW HWONG RUEY|||||||||||||PHKL25IP11000009|15^4^1^C000020027~15^1^99^||||||||||||||||||||||||||||||||||||"""
    },
}


def send_hl7_message(host: str, port: int, message: str) -> str:
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


def print_message_details(event_type: str):
    """Print the full message details for a specific event type"""
    if event_type not in SAMPLE_MESSAGES:
        print(f"Unknown event type: {event_type}")
        return
    
    msg_info = SAMPLE_MESSAGES[event_type]
    message = msg_info['message']
    
    print("\n" + "="*80)
    print(f"MESSAGE DETAILS: {msg_info['name']}")
    print("="*80)
    print(f"\nDescription: {msg_info['description']}")
    print("\n" + "-"*80)
    print("RAW MESSAGE:")
    print("-"*80)
    
    # Print each segment on a new line with segment name highlighted
    segments = message.strip().split('\n')
    for segment in segments:
        if segment.strip():
            seg_name = segment.split('|')[0]
            print(f"\n[{seg_name}]")
            print(segment)
    
    print("\n" + "="*80)


def print_menu():
    """Print interactive menu"""
    print("""
╔════════════════════════════════════════════════════════════════════════════════╗
║              HL7 ADT Sample Message Sender (CEREBRALPLUS → IWARD)              ║
║              Based on: HL7 ADT Integration Document v1.0                       ║
╠════════════════════════════════════════════════════════════════════════════════╣
║  Patient: TEST SST PATIENT | MRN: 3300746940 | IC: N7356938                    ║
║  Visit: PHKL25IP11000009                                                       ║
╠════════════════════════════════════════════════════════════════════════════════╣
║  TYPICAL PATIENT JOURNEY:                                                      ║
║                                                                                ║
║  [1] ADT^A01 - Admit/Register Patient         ← START HERE                     ║
║      Admits patient to Ward C7, Bed C706                                       ║
║                                                                                ║
║  [2] ADT^A02 - Transfer Patient               ← STEP 2                         ║
║      Transfers patient from C706 to Ward D6, Bed D610                          ║
║                                                                                ║
║  [3] ADT^A08 - Update Patient Details         ← STEP 3                         ║
║      Updates diet (Diabetic) and isolation (Contact Isolation)                 ║
║                                                                                ║
║  [4] ADT^A16 - Pending Discharge              ← STEP 4                         ║
║      Flags patient as awaiting discharge                                       ║
║                                                                                ║
║  [5] ADT^A03 - Discharge/End Visit            ← STEP 5 (FINAL)                 ║
║      Discharges patient, closes visit                                          ║
║                                                                                ║
╠════════════════════════════════════════════════════════════════════════════════╣
║  REVERSAL/CANCEL OPERATIONS:                                                   ║
║                                                                                ║
║  [6] ADT^A25 - Cancel Pending Discharge       (Reverses A16)                   ║
║  [7] ADT^A13 - Cancel Discharge               (Reverses A03, reopens visit)    ║
║  [8] ADT^A11 - Cancel Admit/Cancel Visit      (Reverses A01, cancels visit)    ║
║                                                                                ║
╠════════════════════════════════════════════════════════════════════════════════╣
║  [9] Run FULL PATIENT JOURNEY (A01→A02→A08→A16→A03)                            ║
║  [V] View message details (without sending)                                    ║
║  [C] Change connection settings                                                ║
║  [0] Exit                                                                      ║
╚════════════════════════════════════════════════════════════════════════════════╝
    """)


def ask_for_ip_and_port():
    """
    Prompt user for IP address and port to send HL7 message to.
    Returns tuple of (host, port)
    """
    print("\n" + "="*80)
    print("CONNECTION SETTINGS")
    print("="*80)
    
    print(f"\nCurrent default: {DEFAULT_HOST}:{DEFAULT_PORT}")
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


def send_and_display_response(host: str, port: int, event_type: str):
    """Send a message and display the response"""
    msg_info = SAMPLE_MESSAGES[event_type]
    message = msg_info['message']
    
    print(f"\n{'='*80}")
    print(f"SENDING: {msg_info['name']}")
    print(f"{'='*80}")
    print(f"\nDescription: {msg_info['description']}")
    print(f"\n{'─'*80}")
    
    response = send_hl7_message(host, port, message)
    
    print(f"\n{'─'*80}")
    print("RESPONSE:")
    print(f"{'─'*80}")
    
    if 'ERROR' in response:
        print(f"\n❌ {response}")
    else:
        print(f"\n{response}")
        
        if 'AA' in response:
            print("\n✅ Status: Application Accept (AA) - Message processed successfully")
        elif 'AE' in response:
            print("\n⚠️  Status: Application Error (AE) - Message had errors")
        elif 'AR' in response:
            print("\n❌ Status: Application Reject (AR) - Message rejected")
    
    print(f"\n{'='*80}")


def run_patient_journey(host: str, port: int):
    """Run a complete patient journey: Admit → Transfer → Update → Pending Discharge → Discharge"""
    journey_steps = [
        ('A01', 'STEP 1: Admitting patient to Ward C7, Bed C706'),
        ('A02', 'STEP 2: Transferring patient to Ward D6, Bed D610'),
        ('A08', 'STEP 3: Updating patient details (diet, isolation)'),
        ('A16', 'STEP 4: Marking patient as pending discharge'),
        ('A03', 'STEP 5: Discharging patient'),
    ]
    
    print(f"\n{'='*80}")
    print("RUNNING FULL PATIENT JOURNEY")
    print(f"{'='*80}")
    print("\nPatient: TEST SST PATIENT")
    print("MRN: 3300746940 | IC: N7356938")
    print("Visit: PHKL25IP11000009")
    print(f"\n{'─'*80}")
    
    results = []
    for event_type, step_desc in journey_steps:
        msg_info = SAMPLE_MESSAGES[event_type]
        print(f"\n{step_desc}")
        print(f"Sending {msg_info['name']}...")
        
        response = send_hl7_message(host, port, msg_info['message'])
        
        if 'ERROR' in response:
            results.append((event_type, '❌', response))
            print(f"  ❌ {response}")
            print("\n⚠️  Journey stopped due to error. Please check the listener.")
            break
        elif 'AA' in response:
            results.append((event_type, '✅', 'ACK received'))
            print(f"  ✅ ACK received - Success!")
        elif 'AE' in response:
            results.append((event_type, '⚠️', 'Application Error'))
            print(f"  ⚠️ Application Error")
        elif 'AR' in response:
            results.append((event_type, '❌', 'Message Rejected'))
            print(f"  ❌ Message Rejected")
        else:
            results.append((event_type, '?', response))
            print(f"  ? {response}")
    
    print(f"\n{'='*80}")
    print("JOURNEY SUMMARY")
    print(f"{'='*80}")
    for event_type, status, result in results:
        print(f"  {status} {event_type}: {result}")
    
    success_count = sum(1 for _, status, _ in results if status == '✅')
    print(f"\n  Completed: {success_count}/{len(journey_steps)} steps")
    print(f"{'='*80}")


def main():
    """Main entry point"""
    print("""
╔══════════════════════════════════════════════════════════════════════════════════╗
║                     HL7 ADT Sample Message Sender                                ║
║                                                                                  ║
║  Source: HL7 ADT Integration Document_ADT ONLY_24112025_v1.0                     ║
║  HL7 Version: 2.4                                                                ║
║  Transport: MLLP (TCP/IP)                                                        ║
║  Source System: CEREBRALPLUS                                                     ║
║  Destination: IWARD                                                              ║
╠══════════════════════════════════════════════════════════════════════════════════╣
║  TEST PATIENT INFO (same across all messages):                                   ║
║  • Name: TEST SST PATIENT                                                        ║
║  • MRN: 3300746940                                                               ║
║  • IC/Passport: PP^N7356938                                                      ║
║  • DOB: 1992-04-09 | Sex: Male                                                   ║
║  • Visit Number: PHKL25IP11000009                                                ║
╚══════════════════════════════════════════════════════════════════════════════════╝
    """)
    
    # Menu mapping
    menu_map = {
        '1': 'A01',
        '2': 'A02',
        '3': 'A08',
        '4': 'A16',
        '5': 'A03',
        '6': 'A25',
        '7': 'A13',
        '8': 'A11',
    }
    
    # Get initial connection settings
    if len(sys.argv) > 1:
        host = sys.argv[1]
        port = int(sys.argv[2]) if len(sys.argv) > 2 else DEFAULT_PORT
    else:
        host, port = ask_for_ip_and_port()
    
    print(f"\n📡 Target: {host}:{port}")
    
    while True:
        print_menu()
        choice = input("Enter your choice: ").strip().upper()
        
        if choice == '0':
            print("\nGoodbye!")
            break
            
        elif choice in menu_map:
            event_type = menu_map[choice]
            send_and_display_response(host, port, event_type)
            
        elif choice == '9':
            # Run full patient journey
            run_patient_journey(host, port)
            
        elif choice == 'V':
            # View message details
            print("\nWhich message would you like to view?")
            print("  [1] A01 - Admit    [2] A02 - Transfer    [3] A08 - Update")
            print("  [4] A16 - Pending  [5] A03 - Discharge")
            print("  [6] A25 - Cancel Pending  [7] A13 - Cancel Discharge  [8] A11 - Cancel Admit")
            view_choice = input("\nEnter choice: ").strip()
            
            if view_choice in menu_map:
                print_message_details(menu_map[view_choice])
            else:
                print("Invalid choice.")
                
        elif choice == 'C':
            # Change connection settings
            host, port = ask_for_ip_and_port()
            print(f"\n📡 New target: {host}:{port}")
            
        else:
            print("\nInvalid choice. Please try again.")
        
        input("\nPress Enter to continue...")


if __name__ == '__main__':
    main()
