import os
import socket
from dataclasses import dataclass


@dataclass(frozen=True)
class Settings:
    api_base_url: str
    api_passphrase: str
    api_username: str
    api_password: str
    # HL7/MLLP listener (the CM100 connects out TO us and streams continuously)
    listen_host: str
    listen_port: int
    ack_app: str
    ack_facility: str
    idle_flush_seconds: float
    # How long after the last message a sender is still considered "connected".
    peer_online_seconds: int
    # Profile grouping: continuous readings fold into one record until a data
    # gap, a different BP, or the max window elapses (then it is queued).
    profile_gap_seconds: int
    profile_max_seconds: int
    # Optional raw-message archive for troubleshooting unexpected HL7 shapes
    save_raw: bool
    raw_dir: str
    # Sender / offline queue
    send_interval: int
    send_batch_size: int
    retry_base_seconds: int
    retry_max_seconds: int
    queue_db_path: str
    debug_mode: bool
    # Optional device registry from the SmartWard API (maps sender IP -> name/id)
    use_api_devices: bool
    device_fetch_interval: int
    # Identity
    gateway_id: str
    # Queue lifecycle / retention (FIFO)
    retention_sent_hours: int
    retention_dead_days: int
    max_db_rows: int
    max_db_mb: int
    # Failure policy caps
    max_age_transient_hours: int
    max_age_404_minutes: int
    max_attempts_auth: int
    # Validation bounds (values outside these are treated as artifact and dropped)
    spo2_range_min: int
    spo2_range_max: int
    pr_range_min: int
    pr_range_max: int
    # Heartbeat
    heartbeat_interval: int
    heartbeat_enabled: bool
    # Extended stats (DB + network details) sent less frequently
    extended_interval: int
    # systemd unit name to report status for
    service_name: str


def _get_bool(name: str, default: str) -> bool:
    return os.getenv(name, default).strip().lower() == "true"


def _default_gateway_id() -> str:
    """Stable-ish fallback identity when GATEWAY_ID is not provisioned."""
    try:
        return socket.gethostname()
    except Exception:
        return "cm100-gateway"


def load_settings() -> Settings:
    return Settings(
        api_base_url=os.getenv("API_BASE_URL", "http://localhost:8000/api/v1").rstrip("/"),
        api_passphrase=os.getenv("API_PASSPHRASE", ""),
        api_username=os.getenv("API_USERNAME", ""),
        api_password=os.getenv("API_PASSWORD", ""),
        listen_host=os.getenv("LISTEN_HOST", "0.0.0.0"),
        listen_port=int(os.getenv("LISTEN_PORT", "2575")),
        ack_app=os.getenv("ACK_APP", "CM100_GATEWAY"),
        ack_facility=os.getenv("ACK_FACILITY", "SMARTWARD"),
        idle_flush_seconds=float(os.getenv("IDLE_FLUSH_SECONDS", "5")),
        peer_online_seconds=int(os.getenv("PEER_ONLINE_SECONDS", "120")),
        profile_gap_seconds=int(os.getenv("PROFILE_GAP_SECONDS", "120")),
        profile_max_seconds=int(os.getenv("PROFILE_MAX_SECONDS", "300")),
        save_raw=_get_bool("SAVE_RAW", "false"),
        raw_dir=os.getenv("RAW_DIR", "./data/raw"),
        send_interval=int(os.getenv("SEND_INTERVAL", "3")),
        send_batch_size=int(os.getenv("SEND_BATCH_SIZE", "20")),
        retry_base_seconds=int(os.getenv("RETRY_BASE_SECONDS", "5")),
        retry_max_seconds=int(os.getenv("RETRY_MAX_SECONDS", "300")),
        queue_db_path=os.getenv("QUEUE_DB_PATH", "./data/cm100_v2.sqlite"),
        debug_mode=_get_bool("DEBUG_MODE", "false"),
        use_api_devices=_get_bool("USE_API_DEVICES", "false"),
        device_fetch_interval=int(os.getenv("DEVICE_FETCH_INTERVAL", "60")),
        gateway_id=os.getenv("GATEWAY_ID", "").strip() or _default_gateway_id(),
        retention_sent_hours=int(os.getenv("RETENTION_SENT_HOURS", "72")),
        retention_dead_days=int(os.getenv("RETENTION_DEAD_DAYS", "14")),
        max_db_rows=int(os.getenv("MAX_DB_ROWS", "100000")),
        max_db_mb=int(os.getenv("MAX_DB_MB", "200")),
        max_age_transient_hours=int(os.getenv("MAX_AGE_TRANSIENT_HOURS", "168")),
        max_age_404_minutes=int(os.getenv("MAX_AGE_404_MINUTES", "120")),
        max_attempts_auth=int(os.getenv("MAX_ATTEMPTS_AUTH", "3")),
        spo2_range_min=int(os.getenv("SPO2_RANGE_MIN", "50")),
        spo2_range_max=int(os.getenv("SPO2_RANGE_MAX", "100")),
        pr_range_min=int(os.getenv("PR_RANGE_MIN", "30")),
        pr_range_max=int(os.getenv("PR_RANGE_MAX", "220")),
        heartbeat_interval=int(os.getenv("HEARTBEAT_INTERVAL", "30")),
        heartbeat_enabled=_get_bool("HEARTBEAT_ENABLED", "true"),
        extended_interval=int(os.getenv("EXTENDED_INTERVAL", "1800")),
        service_name=os.getenv("SERVICE_NAME", "cm100"),
    )
