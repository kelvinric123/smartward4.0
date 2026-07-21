import os
import socket
from dataclasses import dataclass


GATEWAY_TYPE = "ecg"


@dataclass(frozen=True)
class Settings:
    api_base_url: str
    api_passphrase: str
    api_username: str
    api_password: str
    # ECG HTTP listener — the TC35 POSTs XML/PDF to us with Basic auth,
    # exactly like the dockerized ecg/http_server.py it replaces on the ward.
    listen_host: str
    listen_port: int
    basic_username: str
    basic_password: str
    max_upload_mb: int
    # How long after the last upload a machine is still shown "connected".
    peer_online_seconds: int
    # Where received ECG files (XML + extracted PDF) are kept until sent+retention.
    files_dir: str
    # Sender / offline queue
    send_interval: int
    send_batch_size: int
    retry_base_seconds: int
    retry_max_seconds: int
    queue_db_path: str
    debug_mode: bool
    # Identity
    gateway_id: str
    # Queue lifecycle / retention (FIFO)
    retention_sent_hours: int
    retention_dead_days: int
    max_db_rows: int
    max_db_mb: int
    # File store cap: oldest SENT files are removed first when over this size.
    max_files_mb: int
    # Failure policy caps
    max_age_transient_hours: int
    max_attempts_auth: int
    # Heartbeat
    heartbeat_interval: int
    heartbeat_enabled: bool
    extended_interval: int
    service_name: str


def _get_bool(name: str, default: str) -> bool:
    return os.getenv(name, default).strip().lower() == "true"


def _default_gateway_id() -> str:
    """Stable-ish fallback identity when GATEWAY_ID is not provisioned."""
    try:
        return socket.gethostname()
    except Exception:
        return "ecg-gateway"


def load_settings() -> Settings:
    return Settings(
        api_base_url=os.getenv("API_BASE_URL", "http://localhost:8000/api/v1").rstrip("/"),
        api_passphrase=os.getenv("API_PASSPHRASE", ""),
        api_username=os.getenv("API_USERNAME", ""),
        api_password=os.getenv("API_PASSWORD", ""),
        listen_host=os.getenv("LISTEN_HOST", "0.0.0.0"),
        listen_port=int(os.getenv("LISTEN_PORT", "3050")),
        basic_username=os.getenv("ECG_USERNAME", "admin"),
        basic_password=os.getenv("ECG_PASSWORD", "admin123"),
        max_upload_mb=int(os.getenv("MAX_UPLOAD_MB", "25")),
        peer_online_seconds=int(os.getenv("PEER_ONLINE_SECONDS", "3600")),
        files_dir=os.getenv("FILES_DIR", "./data/files"),
        send_interval=int(os.getenv("SEND_INTERVAL", "3")),
        send_batch_size=int(os.getenv("SEND_BATCH_SIZE", "5")),
        retry_base_seconds=int(os.getenv("RETRY_BASE_SECONDS", "5")),
        retry_max_seconds=int(os.getenv("RETRY_MAX_SECONDS", "300")),
        queue_db_path=os.getenv("QUEUE_DB_PATH", "./data/ecg_gateway.sqlite"),
        debug_mode=_get_bool("DEBUG_MODE", "false"),
        gateway_id=os.getenv("GATEWAY_ID", "").strip() or _default_gateway_id(),
        retention_sent_hours=int(os.getenv("RETENTION_SENT_HOURS", "168")),
        retention_dead_days=int(os.getenv("RETENTION_DEAD_DAYS", "30")),
        max_db_rows=int(os.getenv("MAX_DB_ROWS", "50000")),
        max_db_mb=int(os.getenv("MAX_DB_MB", "50")),
        max_files_mb=int(os.getenv("MAX_FILES_MB", "2000")),
        max_age_transient_hours=int(os.getenv("MAX_AGE_TRANSIENT_HOURS", "336")),
        max_attempts_auth=int(os.getenv("MAX_ATTEMPTS_AUTH", "3")),
        heartbeat_interval=int(os.getenv("HEARTBEAT_INTERVAL", "30")),
        heartbeat_enabled=_get_bool("HEARTBEAT_ENABLED", "true"),
        extended_interval=int(os.getenv("EXTENDED_INTERVAL", "1800")),
        service_name=os.getenv("SERVICE_NAME", "ecg-gateway"),
    )
