import socket
import sys
import time

def check_mqtt(host='localhost', port=1883):
    print(f"Testing MQTT connection to {host}:{port}...")
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        s.settimeout(5)
        s.connect((host, port))
        
        # MQTT 3.1.1 CONNECT Packet
        # Fixed Header: 0x10 (CONNECT)
        # Remaining Length: 14 (variable header 10 + payload 4)
        # Variable Header:
        #   Protocol Name Length: 00 04
        #   Protocol Name: M Q T T
        #   Level: 04 (3.1.1)
        #   Connect Flags: 02 (Clean Session only)
        #   Keep Alive: 00 3C (60s)
        # Payload:
        #   Client ID Length: 00 02
        #   Client ID: T 1 (Test1)
        
        packet = bytearray([
            0x10, 0x0E, 
            0x00, 0x04, ord('M'), ord('Q'), ord('T'), ord('T'),
            0x04, 0x02, 0x00, 0x3c,
            0x00, 0x02, ord('T'), ord('1')
        ])
        
        print("Sending Mqtt CONNECT packet...")
        s.send(packet)
        
        response = s.recv(1024)
        s.close()
        
        if len(response) >= 2 and response[0] == 0x20: # CONNACK
            print("SUCCESS: Received MQTT CONNACK (0x20). Service is running and accepting connections!")
            print(f"Raw Response: {response.hex()}")
            print("-" * 30)
            return True
        else:
            print("FAILURE: Connected to port but did not receive valid CONNACK.")
            print(f"Response: {response.hex() if response else 'None'}")
            return False
            
    except Exception as e:
        print(f"ERROR: Could not connect to {host}:{port}")
        print(f"Details: {e}")
        return False

if __name__ == "__main__":
    try:
        print("\n--- Seekink MQTT Connectivity Tester ---")
        
        # Get Host
        default_host = 'localhost'
        input_host = input(f"Enter MQTT Host IP [default: {default_host}]: ").strip()
        target_host = input_host if input_host else default_host
        
        # Get Port
        default_port = 1883
        input_port = input(f"Enter MQTT Port [default: {default_port}]: ").strip()
        target_port = int(input_port) if input_port else default_port
        
        print(f"\nTarget: {target_host}:{target_port}")
        check_mqtt(target_host, target_port)
        
        # Keep window open if run from double-click
        input("\nPress Enter to exit...")
        
    except KeyboardInterrupt:
        print("\nCancelled.")
    except ValueError:
        print("\nError: Port must be a number.")
