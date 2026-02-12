# Mindray uMEC10 HL7 Listener

This is a Python-based HL7 listener designed to receive vital signs from Mindray uMEC10 patient monitors via the MLLP protocol.

## Features

- TCP Server listening on configurable port (default 2575)
- MLLP (Minimal Lower Layer Protocol) framing support
- HL7 v2.x message parsing
- Automatic ACK (Acknowledgement) generation
- Configurable via `.env` file

## Directory Structure

```
mindray_umec10/
├── listener/
│   ├── __init__.py
│   ├── config.py       # Configuration loader
│   ├── hl7_parser.py   # HL7 parsing logic
│   ├── main.py         # Entry point (TCP Server)
│   └── mllp_handler.py # MLLP framing handler
├── logs/               # Application logs
├── requirements.txt    # Python dependencies
└── README.md           # This file
```

## Setup

1.  **Create a Virtual Environment** (Recommended):
    ```bash
    cd gateway/mindray_umec10
    python -m venv venv
    ```

2.  **Activate Virtual Environment**:
    - Windows: `venv\Scripts\activate`
    - Linux/Mac: `source venv/bin/activate`

3.  **Install Dependencies**:
    ```bash
    pip install -r requirements.txt
    ```

4.  **Configuration**:
    Create a `.env` file in the `listener/` directory or set environment variables:
    ```ini
    HL7_LISTENER_HOST=0.0.0.0
    HL7_LISTENER_PORT=2575
    # LOG_LEVEL=DEBUG
    ```

## Usage

Run the listener:

```bash
python -m listener.main
```

Or from within the `mindray_umec10` directory:

```bash
python listener/main.py
```

## Testing

You can use `test_sender.py` (if available) or `ncat` to send a sample HL7 message.

**Sample HL7 Message:**
```
MSH|^~\&|MINDRAY|uMEC10|LISTENER|GATEWAY|20231025103000||ORU^R01|1|P|2.3
PID|||123456||Doe^John
OBX|1|NM|8867-4||80|bpm
```

**Using ncat:**
```bash
# Note: You need to wrap the message in MLLP (Start Block 0x0B, End Block 0x1C + 0x0D)
# It's easier to use a Python script for testing.
```
