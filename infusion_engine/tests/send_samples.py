#!/usr/bin/env python3
"""Send every sample HL7 message to the infusion engine over MLLP.

Usage (from infusion_engine/):
    python -m tests.send_samples [host] [port]
"""

import os
import socket
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from tests.sample_utils import load_all_samples

HOST = sys.argv[1] if len(sys.argv) > 1 else '127.0.0.1'
PORT = int(sys.argv[2]) if len(sys.argv) > 2 else 6000

MLLP_START_BLOCK = b'\x0b'
MLLP_END_BLOCK = b'\x1c'
MLLP_CARRIAGE_RETURN = b'\x0d'


def send_message(sock, raw):
    frame = MLLP_START_BLOCK + raw.encode('utf-8') + MLLP_END_BLOCK + MLLP_CARRIAGE_RETURN
    sock.sendall(frame)
    response = sock.recv(4096)
    return response.decode('utf-8', errors='replace').strip('\x0b\x1c\r')


def main():
    samples = load_all_samples()
    print(f'Sending {len(samples)} sample messages to {HOST}:{PORT} ...')
    sent = ok = 0
    with socket.create_connection((HOST, PORT), timeout=10) as sock:
        sock.settimeout(10)
        for name, raw in samples:
            ack = send_message(sock, raw)
            sent += 1
            ack_code = ''
            for seg in ack.split('\r'):
                if seg.startswith('MSA|'):
                    ack_code = seg.split('|')[1]
            status = 'OK ' if ack_code == 'AA' else ack_code or '???'
            if ack_code == 'AA':
                ok += 1
            print(f'  [{status}] {name}')
    print(f'Done: {ok}/{sent} accepted (AA)')
    return 0 if ok == sent else 1


if __name__ == '__main__':
    sys.exit(main())
