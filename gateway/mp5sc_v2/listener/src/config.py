import os
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


def _get_bool(name: str, default: str) -> bool:
    return os.getenv(name, default).strip().lower() == "true"


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
    )
