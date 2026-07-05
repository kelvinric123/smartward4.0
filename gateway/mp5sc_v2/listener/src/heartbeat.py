import json
import os
import shutil
import socket
import subprocess
import threading
import time
from datetime import datetime

APP_VERSION = "v2.1.0"


def _run(cmd, timeout=3):
    try:
        out = subprocess.check_output(cmd, stderr=subprocess.DEVNULL, timeout=timeout)
        return out.decode("utf-8", "replace").strip()
    except Exception:
        return None


def read_cpu_serial():
    try:
        with open("/proc/cpuinfo", "r") as fh:
            for line in fh:
                if line.lower().startswith("serial"):
                    return line.split(":", 1)[1].strip()
    except Exception:
        pass
    return ""


def primary_ip():
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    try:
        s.connect(("8.8.8.8", 80))
        return s.getsockname()[0]
    except Exception:
        try:
            return socket.gethostbyname(socket.gethostname())
        except Exception:
            return ""
    finally:
        s.close()


def read_power():
    """Under-voltage / throttling from vcgencmd — our software-only battery proxy.

    throttled bit 0 = under-voltage now; bit 16 = under-voltage has occurred.
    Returns Nones off-Pi so the field is simply absent from the health view.
    """
    power = {
        "undervoltage_now": None,
        "undervoltage_seen": None,
        "throttled_hex": None,
        "cpu_temp_c": None,
    }
    thr = _run(["vcgencmd", "get_throttled"])
    if thr and "=" in thr:
        try:
            value = int(thr.split("=", 1)[1], 16)
            power["throttled_hex"] = hex(value)
            power["undervoltage_now"] = bool(value & 0x1)
            power["undervoltage_seen"] = bool(value & 0x10000)
        except ValueError:
            pass
    temp = _run(["vcgencmd", "measure_temp"])
    if temp and "=" in temp:
        try:
            power["cpu_temp_c"] = float(temp.split("=", 1)[1].replace("'C", "").replace("C", ""))
        except ValueError:
            pass
    return power


def clock_synced():
    val = _run(["timedatectl", "show", "-p", "NTPSynchronized", "--value"])
    if val is None:
        return None
    return val.strip().lower() == "yes"


def disk_free_pct(path):
    try:
        usage = shutil.disk_usage(os.path.dirname(os.path.abspath(path)) or ".")
        return round(usage.free * 100.0 / usage.total, 1)
    except Exception:
        return None


def read_netwatch(db_path):
    """State written by the network self-healing watchdog (netwatch.sh) - how
    many times it reconnected the Wi-Fi and whether the link is currently down."""
    path = os.path.join(os.path.dirname(os.path.abspath(db_path)), "netwatch.state")
    try:
        with open(path, "r") as fh:
            return json.load(fh)
    except Exception:
        return None


def read_service_status(name):
    """systemd unit state for the listener: active/sub state, restart count, and
    when it last (re)started. Confirms the service is healthy and surfaces
    crash-loops (rising NRestarts). Empty off-systemd."""
    st = {"unit": name, "active_state": None, "sub_state": None,
          "n_restarts": None, "active_since": None}
    out = _run(["systemctl", "show", name, "--no-pager",
                "--property=ActiveState,SubState,NRestarts,ActiveEnterTimestamp"])
    if not out:
        return st
    for line in out.splitlines():
        if "=" not in line:
            continue
        key, val = line.split("=", 1)
        val = val.strip()
        if key == "ActiveState":
            st["active_state"] = val or None
        elif key == "SubState":
            st["sub_state"] = val or None
        elif key == "NRestarts":
            try:
                st["n_restarts"] = int(val)
            except ValueError:
                pass
        elif key == "ActiveEnterTimestamp":
            st["active_since"] = val or None
    return st


def read_network():
    """Uplink details for the extended heartbeat: which interface holds the
    default route, and the active Wi-Fi SSID + signal. Nones off-Pi."""
    net = {"ssid": None, "wifi_signal": None, "uplink_if": None}

    route = _run(["ip", "route", "show", "default"])
    if route:
        parts = route.split()
        if "dev" in parts:
            net["uplink_if"] = parts[parts.index("dev") + 1]

    wifi = _run(["nmcli", "-t", "-f", "active,ssid,signal", "dev", "wifi"])
    if wifi:
        for line in wifi.splitlines():
            fields = line.split(":")
            if fields and fields[0] == "yes":
                net["ssid"] = fields[1] if len(fields) > 1 and fields[1] else None
                try:
                    net["wifi_signal"] = int(fields[2]) if len(fields) > 2 and fields[2] else None
                except ValueError:
                    pass
                break
    if not net["ssid"]:
        ssid = _run(["iwgetid", "-r"])
        if ssid:
            net["ssid"] = ssid
    return net


class HeartbeatSender(threading.Thread):
    """Posts periodic health telemetry so the server can flag a dead, stuck, or
    power-unstable cart proactively instead of waiting for vitals to stop."""

    def __init__(self, settings, storage, api_client, manager):
        super().__init__(daemon=True)
        self.settings = settings
        self.storage = storage
        self.api_client = api_client
        self.manager = manager
        self.device_name = "Heartbeat"
        self.running = True
        self._started_monotonic = time.monotonic()
        self._cpu_serial = read_cpu_serial()
        self._wake = threading.Event()
        self._last_extended = 0.0

    def log(self, message, level="INFO"):
        ts = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        print(f"[{ts}] [{level}] [{self.device_name}] {message}")

    def build_payload(self):
        stats = self.storage.get_stats()
        listeners = list(getattr(self.manager, "listeners", {}).values())
        monitors = [
            {
                "ip": getattr(l, "monitor_ip", None),
                "name": getattr(l, "device_name", None),
                "alive": l.is_alive() if hasattr(l, "is_alive") else None,
            }
            for l in listeners
        ]
        return {
            "gateway_id": self.settings.gateway_id,
            "cpu_serial": self._cpu_serial,
            "app_version": APP_VERSION,
            "uptime_s": int(time.monotonic() - self._started_monotonic),
            "ip": primary_ip(),
            "monitor": {
                "count": len(monitors),
                "connected": sum(1 for m in monitors if m["alive"]),
                "devices": monitors,
            },
            "queue": {
                "pending": stats["pending"],
                "retry": stats["retry"],
                "dead": stats["dead"],
                "oldest_pending_age_s": stats["oldest_pending_age_s"],
                "last_sent_at": stats["last_sent_at"],
                "db_size_mb": stats["db_size_mb"],
            },
            "power": read_power(),
            "clock_synced": clock_synced(),
            "disk_free_pct": disk_free_pct(self.storage.db_path),
            "service": read_service_status(self.settings.service_name),
            "netwatch": read_netwatch(self.storage.db_path),
        }

    def build_stats(self):
        """Heavier, less-frequent detail: DB record counts/range and network."""
        db = self.storage.get_extended_stats()
        return {
            "db": {
                "total_records": db["total"],
                "pending": db["pending"],
                "retry": db["retry"],
                "sent": db["sent"],
                "dead": db["dead"],
                "oldest_record_at": db["oldest_record_at"],
                "newest_record_at": db["newest_record_at"],
                "last_sent_at": db["last_sent_at"],
                "db_size_mb": db["db_size_mb"],
            },
            "network": read_network(),
        }

    def send_once(self, include_stats=False):
        try:
            payload = self.build_payload()
            if include_stats:
                payload["stats"] = self.build_stats()
        except Exception as exc:
            self.log(f"Failed to build heartbeat: {exc}", "ERROR")
            return False
        ok, detail = self.api_client.send_heartbeat(payload)
        if ok:
            if self.settings.debug_mode:
                self.log(f"Heartbeat sent{' (with stats)' if include_stats else ''} ({detail})", "DEBUG")
        else:
            # Best-effort: the durable path is the queue, not the heartbeat.
            self.log(f"Heartbeat not delivered: {detail}", "WARNING")
        return ok

    def trigger(self):
        """Ask for an out-of-band heartbeat (e.g. on a notable event)."""
        self._wake.set()

    def run(self):
        self.log(
            f"Heartbeat started (gateway_id={self.settings.gateway_id}, "
            f"interval={self.settings.heartbeat_interval}s, extended={self.settings.extended_interval}s)"
        )
        while self.running:
            # Attach the heavier stats block on the first beat and then only every
            # extended_interval; keep retrying it until one gets through.
            due = self._last_extended == 0.0 or \
                (time.monotonic() - self._last_extended) >= self.settings.extended_interval
            ok = self.send_once(include_stats=due)
            if due and ok:
                self._last_extended = time.monotonic()
            # Sleep in short slices so stop()/trigger() are responsive.
            self._wake.clear()
            waited = 0
            while self.running and waited < self.settings.heartbeat_interval:
                if self._wake.wait(timeout=1):
                    break
                waited += 1

    def stop(self):
        self.running = False
        self._wake.set()
