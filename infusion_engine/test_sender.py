#!/usr/bin/env python3
"""
Send a sample HL7 message to the infusion engine listener (MLLP).
Usage: python test_sender.py [host] [port]
"""

import socket
import sys
from datetime import datetime

HOST = sys.argv[1] if len(sys.argv) > 1 else '127.0.0.1'
PORT = int(sys.argv[2]) if len(sys.argv) > 2 else 6000

MLLP_START_BLOCK = b'\x0b'
MLLP_END_BLOCK = b'\x1c'
MLLP_CARRIAGE_RETURN = b'\x0d'

timestamp = datetime.now().strftime('%Y%m%d%H%M%S')

message = (
    f"MSH|^~\\&|NUC_GATEWAY|WARD1|SMARTWARD|SMARTWARD|{timestamp}||ORU^R01|TEST{timestamp}|P|2.5\r"
    "PID|1||MRN12345||DOE^JOHN||19800101|M\r"
    "PV1|1|I|WARD1^101^A\r"
    "OBR|1|||INFUSION\r"
    "OBX|1|NM|158014^MDC_FLOW_FLUID_PUMP_CURRENT|1|25.0|mL/h||||R\r"
    "OBX|2|ST|184519^MDC_PUMP_INFUSING_STATUS|1|RUNNING|||||R\r"
)

frame = MLLP_START_BLOCK + message.encode('utf-8') + MLLP_END_BLOCK + MLLP_CARRIAGE_RETURN

print(f"Sending test message to {HOST}:{PORT} ...")
with socket.create_connection((HOST, PORT), timeout=5) as sock:
    sock.sendall(frame)
    sock.settimeout(5)
    response = sock.recv(4096)

print("ACK received:")
print(response.decode('utf-8', errors='replace').strip('\x0b\x1c\r'))
