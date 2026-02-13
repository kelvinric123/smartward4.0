import os
from dotenv import load_dotenv

# Load environment variables from .env file
load_dotenv()

# Server Configuration
HOST = os.getenv('HL7_LISTENER_HOST', '0.0.0.0')
PORT = int(os.getenv('HL7_LISTENER_PORT', 4601))
BUFFER_SIZE = int(os.getenv('BUFFER_SIZE', 4096))

# API Configuration
API_BASE_URL = os.getenv('API_BASE_URL', 'http://localhost:8000').rstrip('/')
API_USERNAME = os.getenv('API_USERNAME', 'mindray@qmed.asia')
API_PASSWORD = os.getenv('API_PASSWORD', '88888888')
API_PASSPHRASE = os.getenv('API_PASSPHRASE', 'qmedno1')

# Logging Configuration
LOG_LEVEL = os.getenv('LOG_LEVEL', 'INFO').upper()
LOG_DIR = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'logs')

# Ensure log directory exists
os.makedirs(LOG_DIR, exist_ok=True)
