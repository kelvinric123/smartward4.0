#!/usr/bin/python
# -*- coding: utf-8 -*-
"""
CM100 v2 gateway — HL7/MLLP store-and-forward listener for the Philips
Efficia CM100.

Same architecture as gateway/mp5sc_v2 and gateway/vs4_v2 (SQLite outbox,
classified retries, dead-letter, heartbeat telemetry, server-assigned
identity). The CM100-specific part is the capture semantics: the CM100 is a
CONTINUOUS monitor streaming ORU^R01 every few seconds, so single messages are
grouped into bounded vital-sign PROFILES (see src/cm100_profile.py) and each
closed profile becomes one queued record — the v2 replacement for the
test-flow interpreter in gateway/cm100/interpreter.py.
"""

import json
import os
import re
import sys
import threading
import time
from datetime import datetime

from dotenv import load_dotenv

CURRENT_DIR = os.path.dirname(__file__)
SRC_DIR = os.path.join(CURRENT_DIR, "src")

if SRC_DIR not in sys.path:
    sys.path.insert(0, SRC_DIR)

from api_client import ApiClient
from cm100_profile import ProfileEngine, parse_message
from config import load_settings
from heartbeat import HeartbeatSender
from hl7_mllp import make_server, parse_msh
from storage import OutboxStorage


load_dotenv(os.path.join(CURRENT_DIR, ".env"))


class LoggerMixin:
    def log(self, message, level="INFO"):
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        name = getattr(self, "device_name", "Manager")
        print(f"[{timestamp}] [{level}] [{name}] {message}")


class OutboxSender(threading.Thread, LoggerMixin):
    def __init__(self, settings, storage, api_client):
        super().__init__(daemon=True)
        self.settings = settings
        self.storage = storage
        self.api_client = api_client
        self.device_name = "Sender"
        self.running = True
        self.last_gc_at = 0

    def compute_retry_delay(self, retry_count: int) -> int:
        delay = self.settings.retry_base_seconds * (2 ** max(retry_count - 1, 0))
        return min(delay, self.settings.retry_max_seconds)

    @staticmethod
    def _age_seconds(created_at: str) -> float:
        try:
            created = datetime.strptime(created_at, "%Y-%m-%d %H:%M:%S")
            return max((datetime.utcnow() - created).total_seconds(), 0)
        except (ValueError, TypeError):
            return 0

    def handle_failure(self, event, failure_class, http_status, detail):
        """Decide retry vs dead-letter based on failure class and configured caps."""
        row_id = event["id"]
        retry_count = int(event["retry_count"]) + 1
        age = self._age_seconds(event["created_at"])

        dead = False
        reason = None
        if failure_class == "hard":
            dead, reason = True, f"invalid payload (HTTP {http_status})"
        elif failure_class == "soft":  # 404 patient not found
            if age > self.settings.max_age_404_minutes * 60:
                dead, reason = True, f"patient not found for > {self.settings.max_age_404_minutes}m"
        elif failure_class == "auth":
            if retry_count > self.settings.max_attempts_auth:
                dead, reason = True, f"auth rejected after {retry_count} attempts"
        else:  # transient
            if age > self.settings.max_age_transient_hours * 3600:
                dead, reason = True, f"undeliverable for > {self.settings.max_age_transient_hours}h"

        if dead:
            self.storage.mark_dead(row_id, reason, http_status, failure_class, detail)
            level = "ERROR" if failure_class in ("hard", "auth") else "WARNING"
            self.log(f"Dead-letter {event['event_id']} [{failure_class}]: {reason}: {detail}", level)
        else:
            delay = self.compute_retry_delay(retry_count)
            self.storage.mark_retry(row_id, retry_count, delay, detail, http_status, failure_class)
            self.log(
                f"Send failed {event['event_id']} [{failure_class}], retry {retry_count} in {delay}s: {detail}",
                "WARNING",
            )

    def maybe_run_janitor(self):
        now = time.time()
        if now - self.last_gc_at < 60:
            return
        self.last_gc_at = now
        try:
            r = self.storage.run_maintenance(
                self.settings.retention_sent_hours,
                self.settings.retention_dead_days,
                self.settings.max_db_rows,
                self.settings.max_db_mb,
            )
        except Exception as exc:
            self.log(f"Janitor error: {exc}", "ERROR")
            return
        if any(r.values()):
            self.log(
                "Janitor purged sent={purged_sent} dead={purged_dead}; "
                "evicted sent={evicted_sent} dead={evicted_dead} pending={evicted_pending}".format(**r)
            )
        if r["evicted_pending"]:
            self.log(
                f"ALARM: evicted {r['evicted_pending']} UNSENT reading(s) to stay under size cap "
                f"(max_rows={self.settings.max_db_rows}, max_mb={self.settings.max_db_mb}) - data lost",
                "ERROR",
            )

    def run(self):
        self.log(f"Sender started with queue DB: {self.storage.db_path}")
        while self.running:
            try:
                self.maybe_run_janitor()
                events = self.storage.fetch_due_events(self.settings.send_batch_size)
                if not events:
                    time.sleep(self.settings.send_interval)
                    continue

                for event in events:
                    # Per-row isolation: a poison row is dead-lettered, not left
                    # to stall the whole batch.
                    try:
                        payload = json.loads(event["payload_json"])
                    except Exception as exc:
                        self.storage.mark_dead(event["id"], "corrupt payload", None, "hard", str(exc))
                        self.log(f"Dead-letter corrupt row {event['event_id']}: {exc}", "ERROR")
                        continue

                    ok, failure_class, http_status, detail = self.api_client.send_vital_signs(
                        payload, event["event_id"]
                    )
                    if ok:
                        self.storage.mark_sent(event["id"])
                        self.log(
                            f"Sent {event['event_id']} for patient {payload.get('patient_code', '')}"
                        )
                    else:
                        self.handle_failure(event, failure_class, http_status, detail)

                time.sleep(1)
            except Exception as exc:
                self.log(f"Sender loop error: {exc}", "ERROR")
                time.sleep(self.settings.send_interval)

    def stop(self):
        self.running = False


class Cm100ListenerService(threading.Thread, LoggerMixin):
    """Passive MLLP/TCP server + profile engine: accepts the CM100's continuous
    ORU^R01 stream, folds it into bounded profiles, and queues each closed
    profile as one vital-sign record."""

    def __init__(self, settings, storage, api_client):
        super().__init__(daemon=True)
        self.settings = settings
        self.storage = storage
        self.api_client = api_client
        self.device_name = "HL7"
        self.running = True
        self.server = None
        self._lock = threading.Lock()
        self.engine = ProfileEngine(settings)
        # peer ip -> {"last_seen": ts, "messages": n, "name": str}
        self.peers = {}
        self.device_map = {}
        self.messages_total = 0
        self.queued_total = 0
        self.skipped_no_patient = 0
        self.last_message_at = 0
        self.last_status_report_at = 0
        self.last_live_log_at = 0
        self._status_sent_at = {}

    # ---- device registry (optional, mirrors mp5sc's API-managed mode) ------

    def set_device_map(self, devices):
        mapping = {}
        for device in devices:
            ip = (device.get("ip_address") or "").strip()
            if ip:
                mapping[ip] = {"id": device.get("id"), "name": device.get("name") or "CM100"}
        with self._lock:
            self.device_map = mapping

    def _peer_label(self, peer_ip):
        info = self.device_map.get(peer_ip)
        return info["name"] if info else f"CM100@{peer_ip}"

    def _report_device_status(self, peer_ip, status, connected):
        """Throttled per-device status push, only for API-registered devices."""
        info = self.device_map.get(peer_ip)
        if not info or not info.get("id"):
            return
        now = time.time()
        if now - self._status_sent_at.get(peer_ip, 0) < 60:
            return
        self._status_sent_at[peer_ip] = now
        try:
            self.api_client.update_device_status(info["id"], status, connected)
        except Exception as exc:
            if self.settings.debug_mode:
                self.log(f"Status update failed for {peer_ip}: {exc}", "DEBUG")

    # ---- raw archive (troubleshooting aid) ---------------------------------

    def save_raw(self, hl7, note):
        if not self.settings.save_raw:
            return
        try:
            os.makedirs(self.settings.raw_dir, exist_ok=True)
            stamp = datetime.now().strftime("%Y%m%d-%H%M%S-%f")
            safe_note = re.sub(r"[^A-Za-z0-9_.-]", "", note)[:40] or "MSG"
            path = os.path.join(self.settings.raw_dir, f"{stamp}_{safe_note}.hl7")
            with open(path, "w", encoding="utf-8", newline="") as fh:
                fh.write(hl7 if hl7.endswith("\r") else hl7 + "\r")
        except Exception as exc:
            self.log(f"Could not save raw message: {exc}", "WARNING")

    # ---- profile -> outbox --------------------------------------------------

    def enqueue_profile(self, payload, peer_ip):
        device_name = self._peer_label(peer_ip) if peer_ip else "CM100"
        inserted = self.storage.enqueue(
            payload["gateway_event_id"], payload, device_name, peer_ip or ""
        )
        with self._lock:
            if inserted:
                self.queued_total += 1
        state = "queued" if inserted else "duplicate ignored"
        summary = " ".join(
            f"{name}={payload[name]}" for name in (
                "blood_pressure_systolic", "blood_pressure_diastolic", "pulse_rate",
                "pulse_rate_min", "pulse_rate_max", "spo2_min", "spo2_max",
                "temperature", "respiratory_rate") if name in payload
        )
        self.log(f"Profile closed for {payload['patient_code']}: {summary} [{state}]")

    # ---- MLLP callbacks -----------------------------------------------------

    def on_message(self, hl7, framed, peer_ip):
        """Handle one HL7 message from the stream. Returns the ACK code."""
        now_ts = time.time()
        now = datetime.now()
        with self._lock:
            self.messages_total += 1
            self.last_message_at = now_ts
            peer = self.peers.setdefault(peer_ip, {"last_seen": 0, "messages": 0})
            peer["last_seen"] = now_ts
            peer["messages"] += 1
            peer["name"] = self._peer_label(peer_ip)

        if parse_msh(hl7) is None:
            self.log(f"Non-HL7 data from {peer_ip} ({len(hl7)} bytes) - ignored", "WARNING")
            self.save_raw(hl7, "NON-HL7")
            return "AA"

        self.save_raw(hl7, "ORU")
        mrn, control_id, readings = parse_message(hl7)

        if not readings:
            return "AA"  # empty cycle (result status X) — nothing to record

        if not mrn:
            # A reading we cannot attribute must never be guessed onto a patient.
            with self._lock:
                self.skipped_no_patient += 1
            if now_ts - self.last_live_log_at >= 30:  # continuous stream: don't spam
                self.log(
                    f"Readings from {peer_ip} without an MRN - admit/assign the patient "
                    f"on the CM100 (suppressing repeats for 30s)",
                    "WARNING",
                )
                self.last_live_log_at = now_ts
            return "AA"

        with self._lock:
            closed, reason = self.engine.ingest(mrn, readings, now)
        if closed:
            self.enqueue_profile(closed, peer_ip)
        if reason and self.settings.debug_mode:
            self.log(f"New profile for {mrn}: {reason}", "DEBUG")
        if self.settings.debug_mode:
            live = " ".join(f"{p}={v}" for p, v in readings)
            self.log(f"msg#{control_id[-4:] or '?'} MRN={mrn} {live}", "DEBUG")

        self._report_device_status(peer_ip, "Receiving", True)
        return "AA"

    # ---- periodic work (driven by the manager loop) -------------------------

    def close_expired_profiles(self):
        """Close profiles whose gap/max window elapsed with no new messages."""
        with self._lock:
            closed = self.engine.close_expired(datetime.now())
        for payload in closed:
            self.enqueue_profile(payload, None)

    def flush_profiles(self):
        """Shutdown path: queue whatever is buffered so nothing is lost."""
        with self._lock:
            closed = self.engine.flush_all()
        for payload in closed:
            self.enqueue_profile(payload, None)
        if closed:
            self.log(f"Flushed {len(closed)} open profile(s) to the queue on shutdown")

    # ---- snapshots for heartbeat / logging ---------------------------------

    def peer_snapshot(self):
        now = time.time()
        with self._lock:
            devices = [
                {
                    "ip": ip,
                    "name": peer.get("name") or f"CM100@{ip}",
                    "alive": (now - peer["last_seen"]) <= self.settings.peer_online_seconds,
                    "last_seen_s": int(now - peer["last_seen"]),
                    "messages": peer["messages"],
                }
                for ip, peer in self.peers.items()
            ]
            totals = {
                "messages_total": self.messages_total,
                "queued_total": self.queued_total,
                "skipped_no_patient": self.skipped_no_patient,
                "open_profiles": len(self.engine.profiles),
            }
        return devices, totals

    def maybe_report_queue_state(self):
        now = time.time()
        if now - self.last_status_report_at < 60:
            return
        stats = self.storage.get_stats()
        level = "WARNING" if stats["dead"] else "INFO"
        with self._lock:
            open_profiles = len(self.engine.profiles)
        self.log(
            f"Queue pending={stats['pending']} retry={stats['retry']} "
            f"dead={stats['dead']} oldest_age={stats['oldest_pending_age_s']}s "
            f"db={stats['db_size_mb']}MB; "
            f"hl7 msgs={self.messages_total} profiles_queued={self.queued_total} "
            f"open={open_profiles} no_mrn={self.skipped_no_patient}",
            level,
        )
        self.last_status_report_at = now

    # ---- thread body --------------------------------------------------------

    def run(self):
        self.log(
            f"Starting HL7/MLLP listener on {self.settings.listen_host}:{self.settings.listen_port} "
            f"(profile gap={self.settings.profile_gap_seconds}s, max={self.settings.profile_max_seconds}s)"
        )
        try:
            self.server = make_server(
                on_message=self.on_message,
                host=self.settings.listen_host,
                port=self.settings.listen_port,
                app=self.settings.ack_app,
                facility=self.settings.ack_facility,
                idle_flush=self.settings.idle_flush_seconds,
            )
        except OSError as exc:
            self.log(f"Cannot bind {self.settings.listen_host}:{self.settings.listen_port}: {exc}", "ERROR")
            return
        self.log("Listener ready; waiting for CM100 connections")
        try:
            self.server.serve_forever(poll_interval=0.5)
        except Exception as exc:
            self.log(f"Listener loop error: {exc}", "ERROR")
        finally:
            self.log("Listener stopped")

    def stop(self):
        self.running = False
        if self.server:
            try:
                self.server.shutdown()
                self.server.server_close()
            except Exception:
                pass


class GatewayManager(LoggerMixin):
    """Supervises the three workers (HL7 listener, outbox sender, heartbeat),
    drives profile expiry, restarts the listener if it dies, and optionally
    syncs the device registry from the SmartWard API."""

    def __init__(self, settings):
        self.settings = settings
        self.device_name = "Manager"
        self.storage = OutboxStorage(self.settings.queue_db_path)
        self.api_client = ApiClient(self.settings)
        self.sender = OutboxSender(self.settings, self.storage, self.api_client)
        self.listener = Cm100ListenerService(self.settings, self.storage, self.api_client)
        self.heartbeat = (
            HeartbeatSender(self.settings, self.storage, self.api_client, self)
            if self.settings.heartbeat_enabled
            else None
        )
        self.running = True

    def monitor_snapshot(self):
        """Heartbeat `monitor` block — same shape as mp5sc_v2/vs4_v2 so the
        server dashboard renders every gateway type."""
        devices, totals = self.listener.peer_snapshot()
        return {
            "count": len(devices),
            "connected": sum(1 for d in devices if d["alive"]),
            "devices": devices,
            "listener": {
                "port": self.settings.listen_port,
                "alive": self.listener.is_alive(),
                **totals,
            },
        }

    def start_listener(self):
        self.listener = Cm100ListenerService(self.settings, self.storage, self.api_client)
        self.listener.start()

    def run(self):
        self.log("Starting CM100 v2 manager")
        self.log(f"Queue DB: {self.storage.db_path}")
        self.sender.start()
        self.listener.start()
        if self.heartbeat:
            self.heartbeat.start()

        last_fetch = 0
        try:
            while self.running:
                if self.settings.use_api_devices and (
                    time.time() - last_fetch >= self.settings.device_fetch_interval
                ):
                    try:
                        devices = self.api_client.fetch_devices()
                        self.listener.set_device_map(devices)
                        if self.settings.debug_mode:
                            self.log(f"Synced {len(devices)} API device(s)", "DEBUG")
                    except Exception as exc:
                        self.log(f"Device sync failed: {exc}", "WARNING")
                    last_fetch = time.time()

                if not self.listener.is_alive():
                    self.log("HL7 listener stopped unexpectedly, restarting", "WARNING")
                    time.sleep(2)
                    self.start_listener()

                self.listener.close_expired_profiles()
                self.listener.maybe_report_queue_state()
                time.sleep(1)
        except KeyboardInterrupt:
            self.log("Shutdown requested by user")
        finally:
            self.running = False
            self.listener.stop()
            self.listener.join(timeout=10)
            # Queue buffered profiles before exiting - the outbox survives, RAM does not.
            self.listener.flush_profiles()
            if self.heartbeat:
                self.heartbeat.stop()
                self.heartbeat.join(timeout=5)
            self.sender.stop()
            self.sender.join(timeout=10)
            self.log("Manager stopped")


def main():
    settings = load_settings()
    manager = GatewayManager(settings)
    manager.run()


if __name__ == "__main__":
    main()
