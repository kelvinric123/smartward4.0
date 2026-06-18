# VS30 HL7 Listener

A Python-based HL7 listener designed to receive vital signs from VS30 patient monitors via the MLLP protocol.

## Features

- TCP Server listening on configurable port (default 4602)
- MLLP (Minimal Lower Layer Protocol) framing support
- HL7 v2.x message parsing
- Automatic ACK (Acknowledgement) generation
- Vital signs extraction and API forwarding
- Configurable via `.env` file

## Directory Structure

```
vs30/
├── listener/
│   ├── __init__.py       # Package init
│   ├── config.py         # Configuration loader
│   ├── hl7_parser.py     # HL7 parsing logic
│   ├── main.py           # Entry point (TCP Server)
│   ├── mllp_handler.py   # MLLP framing handler
│   └── api_client.py     # SmartWard API client
├── logs/                 # Application logs (auto-created)
├── .env                  # Environment configuration
├── .gitignore            # Git ignore rules
├── requirements.txt      # Python dependencies
└── README.md             # This file
```

## Setup

1.  **Create a Virtual Environment**:
    ```bash
    cd gateway/vs30
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
    Edit the `.env` file in the `vs30/` directory:
    ```ini
    HL7_LISTENER_HOST=0.0.0.0
    HL7_LISTENER_PORT=4602
    API_BASE_URL=http://localhost:8000
    API_USERNAME=vs30@qmed.asia
    API_PASSWORD=88888888
    API_PASSPHRASE=qmedno1
    LOG_LEVEL=INFO
    ```

## Usage

Run the listener from the `vs30/` directory:

```bash
python -m listener.main
```

Or directly:

```bash
python listener/main.py
```

## Testing

You can use `test_sender.py` to send a sample HL7 message:

```bash
python test_sender.py
```

**Sample HL7 Message:**
```
MSH|^~\&|VS30|MONITOR|LISTENER|GATEWAY|20231025103000||ORU^R01|1|P|2.3
PID|||123456||Doe^John
OBX|1|NM|8867-4||80|bpm
OBX|2|NM|2708-6||98|%
OBX|3|NM|8480-6||120|mmHg
OBX|4|NM|8462-4||80|mmHg
```
