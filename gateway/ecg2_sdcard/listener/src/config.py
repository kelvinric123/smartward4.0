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
    # Where the TC35's files come from: 'image' = the FAT32 image behind the
    # USB mass-storage gadget (production); 'dir' = a plain folder (testing).
    source_mode: str
    usb_image_path: str
    usb_mount_dir: str
    watch_dir: str
    gadget_name: str
    # Watcher timing. A scan runs once the drive has been quiet (no writes)
    # for QUIET_SECONDS, plus a safety-net scan every FULL_SCAN_SECONDS.
    poll_seconds: float
    quiet_seconds: int
    full_scan_seconds: int
    # A file that never becomes a complete XML is captured anyway after this.
    incomplete_giveup_seconds: int
    # A PDF waits this long for its XML before it is treated as PDF-only.
    pdf_pair_grace_seconds: int
    # Philips XML (TC35 USB export): generalpatientdata fields tried in order
    # for the PatientID SmartWard matches against the patient's MRN or RN.
    patient_id_fields: tuple
    # Drive cleanup: when the drive is fuller than CLEANUP_AT_PCT and has been
    # idle CLEANUP_QUIET_SECONDS, recordings the server already acknowledged
    # are deleted from it (the TC35 briefly sees the stick unplugged).
    cleanup_enabled: bool
    cleanup_at_pct: int
    cleanup_quiet_seconds: int
    # Where captured ECG files (XML + extracted PDF) are kept until sent+retention.
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
        return "ecg-usb-gateway"


def load_settings() -> Settings:
    return Settings(
        api_base_url=os.getenv("API_BASE_URL", "http://localhost:8000/api/v1").rstrip("/"),
        api_passphrase=os.getenv("API_PASSPHRASE", ""),
        api_username=os.getenv("API_USERNAME", ""),
        api_password=os.getenv("API_PASSWORD", ""),
        source_mode=os.getenv("SOURCE_MODE", "image").strip().lower(),
        usb_image_path=os.getenv("USB_IMAGE_PATH", "/var/lib/ecg-usb/usb_drive.img"),
        usb_mount_dir=os.getenv("USB_MOUNT_DIR", "/var/lib/ecg-usb/mnt"),
        watch_dir=os.getenv("WATCH_DIR", "./data/usb"),
        gadget_name=os.getenv("GADGET_NAME", "smartward_ecg"),
        poll_seconds=float(os.getenv("POLL_SECONDS", "2")),
        quiet_seconds=int(os.getenv("QUIET_SECONDS", "8")),
        full_scan_seconds=int(os.getenv("FULL_SCAN_SECONDS", "900")),
        incomplete_giveup_seconds=int(os.getenv("INCOMPLETE_GIVEUP_SECONDS", "600")),
        pdf_pair_grace_seconds=int(os.getenv("PDF_PAIR_GRACE_SECONDS", "120")),
        patient_id_fields=tuple(
            f.strip() for f in os.getenv("PATIENT_ID_FIELDS", "patientid,MRN,uniquepatientid").split(",")
            if f.strip()
        ),
        cleanup_enabled=_get_bool("CLEANUP_ENABLED", "true"),
        cleanup_at_pct=int(os.getenv("CLEANUP_AT_PCT", "80")),
        cleanup_quiet_seconds=int(os.getenv("CLEANUP_QUIET_SECONDS", "900")),
        files_dir=os.getenv("FILES_DIR", "./data/files"),
        send_interval=int(os.getenv("SEND_INTERVAL", "3")),
        send_batch_size=int(os.getenv("SEND_BATCH_SIZE", "5")),
        retry_base_seconds=int(os.getenv("RETRY_BASE_SECONDS", "5")),
        retry_max_seconds=int(os.getenv("RETRY_MAX_SECONDS", "300")),
        queue_db_path=os.getenv("QUEUE_DB_PATH", "./data/ecg_usb_gateway.sqlite"),
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
        service_name=os.getenv("SERVICE_NAME", "ecg-usb-gateway"),
    )
