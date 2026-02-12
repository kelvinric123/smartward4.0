import socket
import time
import argparse
import datetime

# MLLP Constants
SB = b'\x0b'
EB = b'\x1c'
CR = b'\x0d'

def send_hl7_message(host, port, message):
    try:
        # Create TCP/IP socket
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.connect((host, port))
        
        # Wrap message in MLLP
        wrapped_msg = SB + message.encode('utf-8') + EB + CR
        
        print(f"Sending message to {host}:{port}...")
        sock.sendall(wrapped_msg)
        
        # Receive response
        response = sock.recv(1024)
        print("Received response:")
        
        # Unwrap response
        unwrapped_resp = response.replace(SB, b'').replace(EB, b'').replace(CR, b'').decode('utf-8')
        print(unwrapped_resp)
        
    except ConnectionRefusedError:
        print(f"Connection refused to {host}:{port}. Is the listener running?")
    except Exception as e:
        print(f"Error: {e}")
    finally:
        sock.close()

if __name__ == "__main__":
    # Interactive Inputs with Default Values
    host = input("Enter Network Address [127.0.0.1]: ").strip() or "127.0.0.1"
    
    port_input = input("Enter Port [2575]: ").strip()
    port = int(port_input) if port_input else 2575
    
    patient_id = input("Enter MRN to be sent [1123456]: ").strip() or "1123456"
    
    # Prompt for more vitals
    heart_rate = input("Enter Heart Rate [80]: ").strip() or "80"
    spo2 = input("Enter SpO2 [98]: ").strip() or "98"
    temp = input("Enter Temperature [36.5]: ").strip() or "36.5"
    bp_sys = input("Enter Systolic BP [120]: ").strip() or "120"
    bp_dia = input("Enter Diastolic BP [80]: ").strip() or "80"
    resp_rate = input("Enter Respiratory Rate [18]: ").strip() or "18"

    # Generate current timestamp
    timestamp = datetime.datetime.now().strftime("%Y%m%d%H%M%S")
    
    # Construct HL7 Message
    hl7_msg = (
        f"MSH|^~\\&|MINDRAY|uMEC10|LISTENER|GATEWAY|{timestamp}||ORU^R01|10001|P|2.3\r"
        f"PID|||{patient_id}||Doe^John\r"
        f"OBX|1|NM|8867-4||{heart_rate}|bpm\r"
        f"OBX|2|NM|2708-6||{spo2}|%\r"
        f"OBX|3|NM|8310-5||{temp}|degC\r"
        f"OBX|4|NM|8480-6||{bp_sys}|mmHg\r"
        f"OBX|5|NM|8462-4||{bp_dia}|mmHg\r"
        f"OBX|6|NM|9279-1||{resp_rate}|rpm\r"
    )
    
    send_hl7_message(host, port, hl7_msg)
