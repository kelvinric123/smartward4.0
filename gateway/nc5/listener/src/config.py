import os
import socket
from dataclasses import dataclass


@dataclass(frozen=True)
class Settings:
    api_base_url: str
    api_passphrase: str
    api_username: str
    api_password: str
    api_timeout: int
    # HL7/MLLP listener (the NC5 opens one long-lived connection TO us and uses
    # it for both patient queries and observation messages)
    listen_host: str
    listen_port: int
    ack_app: str
    ack_facility: str
    # "ACK" (what the NC5 was proven to accept) or "auto" (ACK^<trigger>)
    ack_message_type: str
    idle_flush_seconds: float
    # How long after the last message a monitor still counts as "connected"
    peer_online_seconds: int
    # Optional raw-message archive for troubleshooting unexpected HL7 shapes
    save_raw: bool
    raw_dir: str
    # Optional byte-exact wire capture (both directions), for commissioning a
    # monitor whose traffic we do not fully understand yet. "" disables it.
    capture_dir: str
    # Patient query (QRY^R02 / QRY^A19) answered from the SmartWard API
    query_enabled: bool
    patient_cache_ttl: int
    patient_cache_stale_ttl: int
    patient_name_order: str
    # Continuous-vitals aggregation: one reading per patient per window
    record_interval: int
    flush_on_bp: bool
    window_idle_timeout: int
    vital_staleness: int
    bp_repeat_guard: int
    # Sender / offline queue
    send_interval: int
    send_batch_size: int
    retry_base_seconds: int
    retry_max_seconds: int
    queue_db_path: str
    debug_mode: bool
    # Optional device registry from the SmartWard API (maps monitor IP -> name/id)
    use_api_devices: bool
    device_fetch_interval: int
    # Identity
    gateway_id: str
    gateway_name: str
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
        return "nc5-gateway"


def load_settings() -> Settings:
    return Settings(
        api_base_url=os.getenv("API_BASE_URL", "http://localhost:8000/api/v1").rstrip("/"),
        api_passphrase=os.getenv("API_PASSPHRASE", ""),
        api_username=os.getenv("API_USERNAME", ""),
        api_password=os.getenv("API_PASSWORD", ""),
        api_timeout=int(os.getenv("API_TIMEOUT", "10")),
        listen_host=os.getenv("LISTEN_HOST", "0.0.0.0"),
        listen_port=int(os.getenv("LISTEN_PORT", "2555")),
        ack_app=os.getenv("ACK_APP", "NC5_GATEWAY"),
        ack_facility=os.getenv("ACK_FACILITY", "SMARTWARD"),
        ack_message_type=os.getenv("ACK_MESSAGE_TYPE", "ACK").strip() or "ACK",
        idle_flush_seconds=float(os.getenv("IDLE_FLUSH_SECONDS", "5")),
        peer_online_seconds=int(os.getenv("PEER_ONLINE_SECONDS", "120")),
        save_raw=_get_bool("SAVE_RAW", "false"),
        raw_dir=os.getenv("RAW_DIR", "./data/raw"),
        capture_dir=os.getenv("CAPTURE_DIR", "").strip(),
        query_enabled=_get_bool("QUERY_ENABLED", "true"),
        patient_cache_ttl=int(os.getenv("PATIENT_CACHE_TTL", "60")),
        patient_cache_stale_ttl=int(os.getenv("PATIENT_CACHE_STALE_TTL", "86400")),
        patient_name_order=os.getenv("PATIENT_NAME_ORDER", "full").strip().lower(),
        record_interval=int(os.getenv("RECORD_INTERVAL", "300")),
        flush_on_bp=_get_bool("FLUSH_ON_BP", "true"),
        window_idle_timeout=int(os.getenv("WINDOW_IDLE_TIMEOUT", "60")),
        vital_staleness=int(os.getenv("VITAL_STALENESS", "300")),
        bp_repeat_guard=int(os.getenv("BP_REPEAT_GUARD", "60")),
        send_interval=int(os.getenv("SEND_INTERVAL", "3")),
        send_batch_size=int(os.getenv("SEND_BATCH_SIZE", "20")),
        retry_base_seconds=int(os.getenv("RETRY_BASE_SECONDS", "5")),
        retry_max_seconds=int(os.getenv("RETRY_MAX_SECONDS", "300")),
        queue_db_path=os.getenv("QUEUE_DB_PATH", "./data/nc5.sqlite"),
        debug_mode=_get_bool("DEBUG_MODE", "false"),
        use_api_devices=_get_bool("USE_API_DEVICES", "false"),
        device_fetch_interval=int(os.getenv("DEVICE_FETCH_INTERVAL", "60")),
        gateway_id=os.getenv("GATEWAY_ID", "").strip() or _default_gateway_id(),
        gateway_name=os.getenv("GATEWAY_NAME", "").strip(),
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
        service_name=os.getenv("SERVICE_NAME", "nc5"),
    )
