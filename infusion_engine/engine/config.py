"""Environment-driven configuration for the infusion engine."""

import os

try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass  # python-dotenv optional; plain env vars work fine

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# MLLP listener (HL7 in)
MLLP_HOST = os.getenv('INFUSION_MLLP_HOST', os.getenv('INFUSION_HOST', '0.0.0.0'))
MLLP_PORT = int(os.getenv('INFUSION_MLLP_PORT', os.getenv('INFUSION_PORT', '6000')))

# REST API (data out)
API_HOST = os.getenv('INFUSION_API_HOST', '0.0.0.0')
API_PORT = int(os.getenv('INFUSION_API_PORT', '6001'))

# Optional API key. Empty string = no auth required.
API_KEY = os.getenv('INFUSION_API_KEY', '')

# Storage - PostgreSQL when INFUSION_DB_HOST is set, embedded SQLite otherwise
DB_HOST = os.getenv('INFUSION_DB_HOST', '')
DB_PORT = int(os.getenv('INFUSION_DB_PORT', '5432'))
DB_NAME = os.getenv('INFUSION_DB_NAME', 'infusion')
DB_USER = os.getenv('INFUSION_DB_USER', 'infusion')
DB_PASSWORD = os.getenv('INFUSION_DB_PASSWORD', 'infusion_secret')

# SQLite fallback path (used only when INFUSION_DB_HOST is empty)
DB_PATH = os.getenv('INFUSION_DB_PATH', os.path.join(BASE_DIR, 'data', 'infusion.db'))
LOG_DIR = os.getenv('INFUSION_LOG_DIR', os.path.join(BASE_DIR, 'logs'))

# Purge raw messages/readings older than N days. 0 = keep forever.
RETENTION_DAYS = int(os.getenv('INFUSION_RETENTION_DAYS', '0'))

# Demo pump simulator endpoints (disable in production if not wanted)
DEMO_ENABLED = os.getenv('INFUSION_DEMO_ENABLED', 'true').lower() in ('1', 'true', 'yes')

# Passphrase required by POST /api/admin/clear (wipe stored data from the UI)
CLEAR_PASSPHRASE = os.getenv('INFUSION_CLEAR_PASSPHRASE', 'askdrtai')

# SmartWard base URL (e.g. http://192.168.0.88:18080). When set, the demo tab
# can list SmartWard's registered pumps so demo pumps use the same Device IDs.
SMARTWARD_URL = os.getenv('INFUSION_SMARTWARD_URL', '').rstrip('/')

LOG_LEVEL = os.getenv('LOG_LEVEL', 'INFO').upper()
