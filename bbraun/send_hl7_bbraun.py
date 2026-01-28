import socket
import sys

# Default configuration
IP = "10.21.20.114"
PORT = 5001

# The raw message content provided
# We concatenate it as a single string first, then apply segment separators.
raw_msg_parts = [
    "MSH|^~\\&|PAT_DEVICE_BBRAUN^0012211839000001^EUI-64|BBRAUN|QMED|QMED|20260113205908+0000||ORU^R01^ORU_R01|63903934748935663668521|P|2.6|||AL|NE||ASCII|en^English^ISO639||IHE_PCD_001^IHE PCD^1.3.6.1.4.1.19376.1.6.4.1^ISOPID|||Unknown Patient^Unknown Patient||^^^^^^U||||||||||||||||||||||||||Y",
    "OBR|1|0^PAT_DEVICE_BBRAUN^0012211839000001^EUI-64|0^PAT_DEVICE_BBRAUN^0012211839000001^EUI-64|999999^Unknown medication|||20260113205907+0000",
    "OBX|1||70053^MDC_DEV_PUMP_INFUS_SYRINGE_MDS^MDC|1.0.0.0|||||||X|||||||P52814^^0012210000000000^EUI-64",
    "OBX|2|ST|0^MDC_ATTR_PILLAR_ASSEMBLY^MDC|1.0.0.2|0^0^0^pillar-orientation-vertical^pillar-direction-right^slot-rack-direction-up||||||F",
    "OBX|3||70054^MDC_DEV_PUMP_INFUS_SYRINGE_VMD^MDC|1.1.0.0|||||||X",
    "OBX|4|ST|184520^MDC_PUMP_DRUG_LIBRARY_NAME^MDC|1.1.0.1|PHKL_D6||||||R",
    "OBX|5|ST|67880^MDC_ATTR_ID_MODEL^MDC|1.1.0.8|B Braun SpacePlus Perfusor||||||F",
    "OBX|6|ST|67972^MDC_ATTR_SYS_ID^MDC|1.1.0.9|3107c7c4-59d7-54b7-b500-8d716e5ca2cb||||||F",
    "OBX|7|ST|0^MDC_ATTR_PUMP_PILLAR_DETAILS^MDC|1.1.0.24|0^0||||||F|||||||~~~PHKL^^0012210000000000^EUI-64",
    "OBX|8||70067^MDC_DEV_PUMP_DELIVERY_INFO^MDC|1.1.1.0|||||||X",
    "OBX|9|CWE|184519^MDC_PUMP_INFUSING_STATUS^MDC|1.1.1.1|^pump-status-not-infusing||||||R",
    "OBX|10|NM|158014^MDC_FLOW_FLUID_PUMP_CURRENT^MDC|1.1.1.2|0|265266^MDC_DIM_MILLI_L_PER_HR^MDC^mL/h^mL/h^UCUM|||||R",
    "OBX|11||70071^MDC_DEV_PUMP_INFUSATE_SOURCE_PRIMARY_CHAN^MDC|1.1.2.0|||||||X",
    "OBX|12|ST|158012^MDC_PUMP_SOURCE_CHANNEL_LABEL^MDC|1.1.2.1|Primary||||||R",
    "OBX|13|CWE|158005^MDC_PUMP_CURRENT_DELIVERY_STATUS^MDC|1.1.2.22|^pump-delivery-status-not-delivering||||||R",
    "OBX|14|CWE|158006^MDC_PUMP_NOT_DELIVERING_REASON^MDC|1.1.2.23|^pump-stopped-powered-off||||||R",
    "OBX|15||70091^MDC_DEV_PUMP_SYRINGE_INFO^MDC|1.1.6.0|||||||X"
]

# Join without separators first, as the user input looked continuous
# But actually, looking at the user input `...YOBR|...`, it seems there ARE no newlines in the source text.
# So I will join them directly, then insert \r.
full_text = "".join(raw_msg_parts)

# Now inject \r before OBR| and OBX|
# NOTE: MSH is at start, so no \r needed there.
# We assume the first segment is MSH.
# We also look for PID if it exists, though not explicit in the prompt.

formatted_msg = full_text.replace("OBR|", "\rOBR|").replace("OBX|", "\rOBX|")

# Special handling for PID if it was intended but tag missing
# If we see "...ISOPID|||", it might be intended to be ...ISOPID\rPID||| ? 
# But let's stick to the prompt text. The prompt had "YOBR", so "Y" ends the previous segment.

# Wrap in MLLP
VT = b'\x0b'
FS = b'\x1c'
CR = b'\x0d'

final_payload = VT + formatted_msg.encode('utf-8') + FS + CR

def send_hl7(ip, port, data):
    print(f"Connecting to {ip}:{port}...")
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        s.settimeout(5)
        s.connect((ip, port))
        print("Connected.")
        
        print(f"Sending {len(data)} bytes...")
        s.sendall(data)
        print("Data sent.")
        
        try:
            response = s.recv(4096)
            print("Received ACK/Response:")
            print(response)
            
            # Check for MLLP in response
            if response.startswith(VT) and response.endswith(FS+CR):
                print("Decoded Response:")
                print(response[1:-2].decode('utf-8').replace('\r', '\n'))
        except socket.timeout:
            print("No response received (timeout).")
            
        s.close()
    except Exception as e:
        print(f"Connection failed: {e}")

if __name__ == "__main__":
    send_hl7(IP, PORT, final_payload)
