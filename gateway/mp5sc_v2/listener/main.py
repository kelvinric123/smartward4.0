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

    def compute_retry_delay(self, retry_count: int) -> int:
        delay = self.settings.retry_base_seconds * (2 ** max(retry_count - 1, 0))
        return min(delay, self.settings.retry_max_seconds)

    def run(self):
        self.log(f"Sender started with queue DB: {self.storage.db_path}")
        while self.running:
            try:
                events = self.storage.fetch_due_events(self.settings.send_batch_size)
                if not events:
                    time.sleep(self.settings.send_interval)
                    continue

                for event in events:
                    payload = json.loads(event["payload_json"])
                    ok, result = self.api_client.send_vital_signs(payload, event["event_id"])
                    if ok:
                        self.storage.mark_sent(event["id"])
                        self.log(
                            f"Sent queued event {event['event_id']} for patient {payload.get('patient_code', '')}"
                        )
                    else:
                        retry_count = int(event["retry_count"]) + 1
                        delay = self.compute_retry_delay(retry_count)
                        self.storage.mark_retry(event["id"], retry_count, delay, result)
                        self.log(
                            f"Send failed for {event['event_id']}, retry in {delay}s: {result}",
                            "WARNING",
                        )

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
        self.last_valid_heart_rate = 0
        self.last_valid_oxygen = 0
        self.last_valid_temp = 0
        self.last_valid_resp_rate = 0
        self.last_status_report_at = 0

    def update_status(self, status, connected=False):
        try:
            self.api_client.update_device_status(self.device_id, status, connected)
        except Exception as exc:
            if self.settings.debug_mode:
                self.log(f"Status update failed: {exc}", "DEBUG")

    def build_payload(self, patient_id, timestamp, bp_sys, bp_dias):
        payload = {
            "username": self.settings.api_username,
            "password": self.settings.api_password,
            "patient_code": patient_id,
            "measured_at": timestamp.strftime("%Y-%m-%d %H:%M:%S"),
            "gateway_event_id": self.compute_event_id(patient_id, timestamp, bp_sys, bp_dias),
        }

        if bp_sys > 0 and bp_sys < 300:
            payload["blood_pressure_systolic"] = int(bp_sys)
        if bp_dias > 0 and bp_dias < 200:
            payload["blood_pressure_diastolic"] = int(bp_dias)
        if self.last_valid_heart_rate > 0 and self.last_valid_heart_rate < 300:
            payload["pulse_rate"] = int(self.last_valid_heart_rate)
        if self.last_valid_oxygen > 0 and self.last_valid_oxygen <= 100:
            payload["spo2"] = round(self.last_valid_oxygen, 1)
        if self.last_valid_temp > 20 and self.last_valid_temp < 50:
            payload["temperature"] = round(self.last_valid_temp, 1)
        if self.last_valid_resp_rate > 0 and self.last_valid_resp_rate < 100:
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

        if current_heart_rate < 300 and current_heart_rate != 8388607:
            self.last_valid_heart_rate = current_heart_rate
        if current_oxygen <= 100 and current_oxygen != 8388607:
            self.last_valid_oxygen = current_oxygen
        if 20 < current_temp < 50 and current_temp != 8388607:
            self.last_valid_temp = current_temp
        if 0 < current_resp_rate < 100 and current_resp_rate != 8388607:
            self.last_valid_resp_rate = current_resp_rate

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
        self.log(
            f"Queue pending={stats['pending_count']} oldest={stats['oldest_pending_at']}",
            "INFO",
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

                    if not patient_id:
                        self.log("Skipping capture because patient ID is missing", "WARNING")
                        last_nbp_time = v
                        time.sleep(self.settings.poll_interval)
                        continue

                    payload = self.build_payload(patient_id, v, bp_sys, bp_dias)
                    event_id = payload["gateway_event_id"]
                    inserted = self.storage.enqueue(event_id, payload, self.device_name, self.monitor_ip)
                    state = "queued" if inserted else "duplicate ignored"
                    self.log(
                        f"BP captured for {full_name or patient_id}: "
                        f"{int(bp_sys)}/{int(bp_dias)} HR={int(self.last_valid_heart_rate)} "
                        f"SpO2={self.last_valid_oxygen:.1f} [{state}]"
                    )
                    last_nbp_time = v

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
            self.sender.stop()
            self.sender.join(timeout=10)
            self.log("Manager stopped")


def main():
    settings = load_settings()
    manager = MultiDeviceManager(settings)
    manager.run()


if __name__ == "__main__":
    main()
