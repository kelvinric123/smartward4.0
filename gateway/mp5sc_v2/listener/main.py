#!/usr/bin/python
# -*- coding: utf-8 -*-

import hashlib
import json
import os
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
from config import load_settings
from heartbeat import HeartbeatSender
from reliable_ipv_data_source import ReliableIpvDataSource
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


class VitalSignListener(threading.Thread, LoggerMixin):
    def __init__(self, settings, storage, api_client, monitor_ip, device_name="Monitor", device_id=None):
        super().__init__(daemon=True)
        self.settings = settings
        self.storage = storage
        self.api_client = api_client
        self.monitor_ip = monitor_ip
        self.device_name = device_name
        self.device_id = device_id
        self.running = True

        self.last_patient_name = ""
        self.last_patient_id = ""
        self.patient_id_changed_at = 0
        self.last_valid_heart_rate = 0
        self.last_valid_oxygen = 0
        self.last_valid_temp = 0
        self.last_valid_resp_rate = 0
        # Capture timestamps so stale continuous vitals are not attached to a BP.
        self.hr_at = 0
        self.oxygen_at = 0
        self.temp_at = 0
        self.resp_rate_at = 0
        # Min/max observed for SpO2 and PR during the current capture window, so
        # we can report a range (e.g. "95-96") instead of a single sample.
        self.spo2_min = None
        self.spo2_max = None
        self.hr_min = None
        self.hr_max = None
        self.last_status_report_at = 0

    def reset_ranges(self):
        """Start a fresh SpO2/PR range window (after a capture)."""
        self.spo2_min = self.spo2_max = None
        self.hr_min = self.hr_max = None

    def reset_vital_cache(self):
        """Drop cached continuous vitals so one patient's values never ride
        along on the next patient's BP snapshot."""
        self.last_valid_heart_rate = 0
        self.last_valid_oxygen = 0
        self.last_valid_temp = 0
        self.last_valid_resp_rate = 0
        self.hr_at = self.oxygen_at = self.temp_at = self.resp_rate_at = 0
        self.reset_ranges()

    def update_status(self, status, connected=False):
        try:
            self.api_client.update_device_status(self.device_id, status, connected)
        except Exception as exc:
            if self.settings.debug_mode:
                self.log(f"Status update failed: {exc}", "DEBUG")

    def _fresh(self, captured_at):
        """True if a cached continuous value is recent enough to attach to a BP."""
        return captured_at > 0 and (time.time() - captured_at) <= self.settings.vital_staleness_seconds

    def build_payload(self, patient_id, timestamp, bp_sys, bp_dias, patient_name=""):
        # Credentials are NOT stored here; they are attached by the API client at
        # send time so the queue on disk never contains passwords.
        payload = {
            "patient_code": patient_id,
            "measured_at": timestamp.strftime("%Y-%m-%d %H:%M:%S"),
            "gateway_event_id": self.compute_event_id(patient_id, timestamp, bp_sys, bp_dias),
            "gateway_id": self.settings.gateway_id,
        }
        if patient_name:
            payload["patient_name"] = patient_name

        if bp_sys > 0 and bp_sys < 300:
            payload["blood_pressure_systolic"] = int(bp_sys)
        if bp_dias > 0 and bp_dias < 200:
            payload["blood_pressure_diastolic"] = int(bp_dias)
        # Continuous vitals only ride along if captured within the staleness window.
        # SpO2 and PR also carry the min/max observed during the capture window.
        if self._fresh(self.hr_at) and 0 < self.last_valid_heart_rate < 300:
            payload["pulse_rate"] = int(self.last_valid_heart_rate)
            if self.hr_min is not None and self.hr_max is not None:
                payload["pulse_rate_min"] = int(round(self.hr_min))
                payload["pulse_rate_max"] = int(round(self.hr_max))
        if self._fresh(self.oxygen_at) and 0 < self.last_valid_oxygen <= 100:
            payload["spo2"] = round(self.last_valid_oxygen, 1)
            if self.spo2_min is not None and self.spo2_max is not None:
                payload["spo2_min"] = int(round(self.spo2_min))
                payload["spo2_max"] = int(round(self.spo2_max))
        if self._fresh(self.temp_at) and 20 < self.last_valid_temp < 50:
            payload["temperature"] = round(self.last_valid_temp, 1)
        if self._fresh(self.resp_rate_at) and 0 < self.last_valid_resp_rate < 100:
            payload["respiratory_rate"] = int(self.last_valid_resp_rate)
        return payload

    def compute_event_id(self, patient_id, timestamp, bp_sys, bp_dias):
        raw = "|".join(
            [
                str(self.device_id or self.monitor_ip),
                patient_id.strip(),
                timestamp.strftime("%Y-%m-%d %H:%M:%S"),
                str(int(bp_sys)),
                str(int(bp_dias)),
            ]
        )
        return hashlib.sha1(raw.encode("utf-8")).hexdigest()

    def refresh_patient_context(self, dev):
        patient_id = self.last_patient_id
        full_name = self.last_patient_name
        try:
            patient_data = dev.get_patient_data()
            candidate_id = str(patient_data[0][1]).strip() if patient_data[0][1] else ""
            patient_prename = str(patient_data[1][1]).strip() if patient_data[1][1] else ""
            patient_name = str(patient_data[2][1]).strip() if patient_data[2][1] else ""
            candidate_name = f"{patient_prename} {patient_name}".strip()
            if candidate_id:
                patient_id = candidate_id
            if candidate_name:
                full_name = candidate_name
        except Exception:
            pass

        # A confirmed patient change clears cached vitals and starts the settle
        # timer so a BP taken mid-switch cannot be attributed to the wrong person.
        if patient_id and patient_id != self.last_patient_id:
            if self.last_patient_id:
                self.log(
                    f"Patient changed {self.last_patient_id} -> {patient_id}; clearing cached vitals",
                    "WARNING",
                )
                self.reset_vital_cache()
            self.patient_id_changed_at = time.time()

        if full_name != self.last_patient_name or patient_id != self.last_patient_id:
            if patient_id:
                if full_name:
                    self.log(f"Patient context updated: {full_name} (ID: {patient_id})")
                else:
                    self.log(f"Patient ID updated: {patient_id}")
            self.last_patient_name = full_name
            self.last_patient_id = patient_id

        return patient_id, full_name

    def update_live_values(self, temp_l):
        current_oxygen = float(temp_l[5][1])
        current_heart_rate = float(temp_l[6][1])

        try:
            current_temp = float(temp_l[11][1]) if len(temp_l) > 11 else 0
            current_resp_rate = float(temp_l[12][1]) if len(temp_l) > 12 else 0
        except (IndexError, ValueError):
            current_temp = 0
            current_resp_rate = 0

        now = time.time()
        # PR: keep the latest as the point value and track the window min/max.
        if self.settings.pr_range_min <= current_heart_rate <= self.settings.pr_range_max \
                and current_heart_rate != 8388607:
            self.last_valid_heart_rate = current_heart_rate
            self.hr_at = now
            self.hr_min = current_heart_rate if self.hr_min is None else min(self.hr_min, current_heart_rate)
            self.hr_max = current_heart_rate if self.hr_max is None else max(self.hr_max, current_heart_rate)
        # SpO2: same treatment.
        if self.settings.spo2_range_min <= current_oxygen <= self.settings.spo2_range_max \
                and current_oxygen != 8388607:
            self.last_valid_oxygen = current_oxygen
            self.oxygen_at = now
            self.spo2_min = current_oxygen if self.spo2_min is None else min(self.spo2_min, current_oxygen)
            self.spo2_max = current_oxygen if self.spo2_max is None else max(self.spo2_max, current_oxygen)
        if 20 < current_temp < 50 and current_temp != 8388607:
            self.last_valid_temp = current_temp
            self.temp_at = now
        if 0 < current_resp_rate < 100 and current_resp_rate != 8388607:
            self.last_valid_resp_rate = current_resp_rate
            self.resp_rate_at = now

        if self.settings.debug_mode:
            self.log(
                "Raw values "
                f"HR={current_heart_rate}, O2={current_oxygen}, "
                f"Temp={current_temp}, RR={current_resp_rate}",
                "DEBUG",
            )

    def maybe_report_queue_state(self):
        now = time.time()
        if now - self.last_status_report_at < 60:
            return
        stats = self.storage.get_stats()
        level = "WARNING" if stats["dead"] else "INFO"
        self.log(
            f"Queue pending={stats['pending']} retry={stats['retry']} "
            f"dead={stats['dead']} oldest_age={stats['oldest_pending_age_s']}s "
            f"db={stats['db_size_mb']}MB",
            level,
        )
        self.last_status_report_at = now

    def run(self):
        self.log(f"Starting v2 listener for {self.monitor_ip}")
        self.update_status("Connecting...", False)

        dev = ReliableIpvDataSource(self.monitor_ip)
        dev.debug_info = self.settings.debug_mode

        try:
            dev.start_client()
            self.update_status("Connected", True)
            self.log("Monitor connection established")
        except Exception as exc:
            self.log(f"Failed to start device client: {exc}", "ERROR")
            self.update_status(f"Connection failed: {exc}", False)
            return

        last_vital_time = 0
        last_nbp_time = datetime.strptime("01.01.1990 00:00:00", "%d.%m.%Y %H:%M:%S")
        refresh_counter = 0

        try:
            while self.running:
                temp_l = dev.get_vital_signs()
                refresh_counter += 1
                if refresh_counter >= self.settings.refresh_interval:
                    dev.refresh_patient_data()
                    refresh_counter = 0

                patient_id, full_name = self.refresh_patient_context(dev)

                diff_time = ((temp_l[8][1] * 0.000125) / 60) - ((temp_l[9][1] * 0.000125) / 60)
                if diff_time != last_vital_time:
                    self.update_live_values(temp_l)
                    last_vital_time = diff_time

                v = temp_l[4][1]
                w = temp_l[10][1]
                if ((v - w).total_seconds() > 0) and (last_nbp_time != v):
                    bp_sys = float(temp_l[0][1])
                    bp_dias = float(temp_l[1][1])

                    # Pull the freshest identity right before attributing this
                    # reading, instead of trusting the periodic refresh window.
                    dev.refresh_patient_data()
                    patient_id, full_name = self.refresh_patient_context(dev)

                    if not patient_id:
                        self.log("Skipping BP capture: patient ID missing", "WARNING")
                        last_nbp_time = v
                        time.sleep(self.settings.poll_interval)
                        continue

                    # Identity just changed => this BP is ambiguous (could be the
                    # previous patient's). Drop it rather than risk a wrong-patient
                    # record; a settled reading will be captured on the next cuff.
                    if (time.time() - self.patient_id_changed_at) < self.settings.identity_settle_seconds:
                        self.log(
                            f"Dropping BP {int(bp_sys)}/{int(bp_dias)}: identity unsettled "
                            f"(<{self.settings.identity_settle_seconds}s since patient change)",
                            "WARNING",
                        )
                        last_nbp_time = v
                        time.sleep(self.settings.poll_interval)
                        continue

                    payload = self.build_payload(patient_id, v, bp_sys, bp_dias, full_name)
                    event_id = payload["gateway_event_id"]
                    inserted = self.storage.enqueue(event_id, payload, self.device_name, self.monitor_ip)
                    state = "queued" if inserted else "duplicate ignored"
                    self.log(
                        f"BP captured for {full_name or patient_id}: "
                        f"{int(bp_sys)}/{int(bp_dias)} "
                        f"PR={payload.get('pulse_rate_min', '?')}-{payload.get('pulse_rate_max', '?')} "
                        f"SpO2={payload.get('spo2_min', '?')}-{payload.get('spo2_max', '?')} [{state}]"
                    )
                    last_nbp_time = v
                    # New capture window for the next reading.
                    self.reset_ranges()

                self.maybe_report_queue_state()
                time.sleep(self.settings.poll_interval)
        except KeyboardInterrupt:
            self.log("Shutdown requested by user")
        except Exception as exc:
            self.log(f"Listener loop error: {exc}", "ERROR")
            self.update_status(f"Error: {exc}", False)
        finally:
            self.running = False
            dev.halt_client()
            self.update_status("Disconnected", False)
            self.log("Listener stopped")

    def stop(self):
        self.running = False


class MultiDeviceManager(LoggerMixin):
    def __init__(self, settings):
        self.settings = settings
        self.device_name = "Manager"
        self.storage = OutboxStorage(self.settings.queue_db_path)
        self.api_client = ApiClient(self.settings)
        self.sender = OutboxSender(self.settings, self.storage, self.api_client)
        self.heartbeat = (
            HeartbeatSender(self.settings, self.storage, self.api_client, self)
            if self.settings.heartbeat_enabled
            else None
        )
        self.listeners = {}
        self.running = True

    def start_listener(self, ip, name="Monitor", device_id=None):
        if ip in self.listeners:
            return
        listener = VitalSignListener(
            self.settings,
            self.storage,
            self.api_client,
            monitor_ip=ip,
            device_name=name,
            device_id=device_id,
        )
        listener.start()
        self.listeners[ip] = listener
        self.log(f"Started listener for {name} ({ip})")

    def stop_listener(self, ip):
        listener = self.listeners.get(ip)
        if not listener:
            return
        listener.stop()
        listener.join(timeout=10)
        self.listeners.pop(ip, None)
        self.log(f"Stopped listener for {ip}")

    def sync_devices(self, devices):
        api_ips = {d["ip_address"] for d in devices if d.get("ip_address")}
        current_ips = set(self.listeners.keys())

        for ip in current_ips - api_ips:
            self.stop_listener(ip)

        for device in devices:
            ip = device.get("ip_address")
            if ip and ip not in current_ips:
                self.start_listener(ip, device.get("name", "Monitor"), device.get("id"))

    def run(self):
        self.log("Starting MP5SC v2 manager")
        self.log(f"Queue DB: {self.storage.db_path}")
        self.sender.start()
        if self.heartbeat:
            self.heartbeat.start()

        if self.settings.use_api_devices:
            self.log("Device source: SmartWard API")
        elif self.settings.legacy_monitor_ip:
            self.log(f"Device source: fixed MONITOR_IP={self.settings.legacy_monitor_ip}")
        else:
            self.log("No device source configured", "ERROR")
            return

        last_fetch = 0
        try:
            while self.running:
                if self.settings.use_api_devices and (time.time() - last_fetch >= self.settings.device_fetch_interval):
                    try:
                        devices = self.api_client.fetch_devices()
                        self.sync_devices(devices)
                        self.log(f"Synced {len(devices)} API device(s)")
                    except Exception as exc:
                        self.log(f"Device sync failed: {exc}", "WARNING")
                        if not self.listeners and self.settings.legacy_monitor_ip:
                            self.start_listener(self.settings.legacy_monitor_ip, "Legacy Monitor")
                    last_fetch = time.time()
                elif not self.settings.use_api_devices and self.settings.legacy_monitor_ip and not self.listeners:
                    self.start_listener(self.settings.legacy_monitor_ip, "Legacy Monitor")

                for ip, listener in list(self.listeners.items()):
                    if not listener.is_alive():
                        self.log(f"Listener for {ip} stopped unexpectedly, restarting", "WARNING")
                        self.listeners.pop(ip, None)
                        self.start_listener(ip, listener.device_name, listener.device_id)

                time.sleep(1)
        except KeyboardInterrupt:
            self.log("Shutdown requested by user")
        finally:
            self.running = False
            for ip in list(self.listeners.keys()):
                self.stop_listener(ip)
            if self.heartbeat:
                self.heartbeat.stop()
                self.heartbeat.join(timeout=5)
            self.sender.stop()
            self.sender.join(timeout=10)
            self.log("Manager stopped")


def main():
    settings = load_settings()
    manager = MultiDeviceManager(settings)
    manager.run()


if __name__ == "__main__":
    main()
