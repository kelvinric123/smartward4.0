import os
import socket
from dataclasses import dataclass


@dataclass(frozen=True)
class Settings:
    api_base_url: str
    api_passphrase: str
    api_username: str
    api_password: str
    poll_interval: int
    refresh_interval: int
    send_interval: int
    send_batch_size: int
    retry_base_seconds: int
    retry_max_seconds: int
    queue_db_path: str
    debug_mode: bool
    use_api_devices: bool
    device_fetch_interval: int
    legacy_monitor_ip: str
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
    # Patient safety
    vital_staleness_seconds: int
    identity_settle_seconds: int
    # Range capture bounds (values outside these are treated as extreme/artifact
    # and excluded from both the point value and the min/max range).
    spo2_range_min: int
    spo2_range_max: int
    pr_range_min: int
    pr_range_max: int
    # Temperature capture. The monitor publishes temperature under more than one
    # label: the value taken/confirmed on the monitor (primary) and the value
    # coming from the temperature probe (secondary). IDs are field-tunable
    # because the probe's label differs per monitor configuration.
    temp_primary_ids: str
    temp_secondary_ids: str
    temp_id_band_min: int
    temp_id_band_max: int
    temp_min_c: float
    temp_max_c: float
    temp_fahrenheit_autoconvert: bool
    temp_autoscale: bool
    temp_agreement_tolerance: float
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
        return "mp5sc-gateway"


def load_settings() -> Settings:
    return Settings(
        api_base_url=os.getenv("API_BASE_URL", "http://localhost:8000/api/v1").rstrip("/"),
        api_passphrase=os.getenv("API_PASSPHRASE", ""),
        api_username=os.getenv("API_USERNAME", ""),
        api_password=os.getenv("API_PASSWORD", ""),
        poll_interval=int(os.getenv("POLL_INTERVAL", "2")),
        refresh_interval=int(os.getenv("REFRESH_INTERVAL", "10")),
        send_interval=int(os.getenv("SEND_INTERVAL", "3")),
        send_batch_size=int(os.getenv("SEND_BATCH_SIZE", "20")),
        retry_base_seconds=int(os.getenv("RETRY_BASE_SECONDS", "5")),
        retry_max_seconds=int(os.getenv("RETRY_MAX_SECONDS", "300")),
        queue_db_path=os.getenv("QUEUE_DB_PATH", "./data/mp5sc_v2.sqlite"),
        debug_mode=_get_bool("DEBUG_MODE", "false"),
        use_api_devices=_get_bool("USE_API_DEVICES", "true"),
        device_fetch_interval=int(os.getenv("DEVICE_FETCH_INTERVAL", "60")),
        legacy_monitor_ip=os.getenv("MONITOR_IP", "").strip(),
        gateway_id=os.getenv("GATEWAY_ID", "").strip() or _default_gateway_id(),
        retention_sent_hours=int(os.getenv("RETENTION_SENT_HOURS", "72")),
        retention_dead_days=int(os.getenv("RETENTION_DEAD_DAYS", "14")),
        max_db_rows=int(os.getenv("MAX_DB_ROWS", "100000")),
        max_db_mb=int(os.getenv("MAX_DB_MB", "200")),
        max_age_transient_hours=int(os.getenv("MAX_AGE_TRANSIENT_HOURS", "168")),
        max_age_404_minutes=int(os.getenv("MAX_AGE_404_MINUTES", "120")),
        max_attempts_auth=int(os.getenv("MAX_ATTEMPTS_AUTH", "3")),
        vital_staleness_seconds=int(os.getenv("VITAL_STALENESS_SECONDS", "60")),
        identity_settle_seconds=int(os.getenv("IDENTITY_SETTLE_SECONDS", "5")),
        spo2_range_min=int(os.getenv("SPO2_RANGE_MIN", "50")),
        spo2_range_max=int(os.getenv("SPO2_RANGE_MAX", "100")),
        pr_range_min=int(os.getenv("PR_RANGE_MIN", "30")),
        pr_range_max=int(os.getenv("PR_RANGE_MAX", "220")),
        temp_primary_ids=os.getenv("TEMP_PRIMARY_IDS", "19272,19296,19298,61639"),
        temp_secondary_ids=os.getenv("TEMP_SECONDARY_IDS", ""),
        temp_id_band_min=int(os.getenv("TEMP_ID_BAND_MIN", "19272")),
        temp_id_band_max=int(os.getenv("TEMP_ID_BAND_MAX", "19420")),
        temp_min_c=float(os.getenv("TEMP_MIN_C", "25")),
        temp_max_c=float(os.getenv("TEMP_MAX_C", "45")),
        temp_fahrenheit_autoconvert=_get_bool("TEMP_FAHRENHEIT_AUTOCONVERT", "true"),
        temp_autoscale=_get_bool("TEMP_AUTOSCALE", "true"),
        temp_agreement_tolerance=float(os.getenv("TEMP_AGREEMENT_TOLERANCE", "0.3")),
        heartbeat_interval=int(os.getenv("HEARTBEAT_INTERVAL", "30")),
        heartbeat_enabled=_get_bool("HEARTBEAT_ENABLED", "true"),
        extended_interval=int(os.getenv("EXTENDED_INTERVAL", "1800")),
        service_name=os.getenv("SERVICE_NAME", "mp5sc"),
    )
