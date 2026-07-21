#!/usr/bin/python
# -*- coding: utf-8 -*-
"""
ECG gateway — TC35 store-and-forward gateway.

Same architecture as the v2 vital-sign gateways (gateway/mp5sc_v2 / vs4_v2 /
cm100_v2): SQLite outbox, classified retries, dead-letter, heartbeat telemetry,
server-assigned identity. The device-facing side replicates the dockerized ECG
listener (ecg/http_server.py) so the Philips TC35 — which has no Wi-Fi module —
POSTs its recordings to this Pi over the ward LAN exactly as it would to the
docker service, and the gateway forwards each recording to SmartWard's
POST /api/v1/ecg, tracking per-recording delivery until the server acknowledges
receipt.

Unlike the vital-sign gateways, the clinical payload here is FILES (the ECG XML
plus its extracted report PDF). Files live on disk under FILES_DIR; the outbox
row carries only metadata, and the base64 upload body is built at send time.
"""

import base64
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
from ecg_http import detect_file_type, extract_patient_id, extract_pdf_from_xml, make_server
from heartbeat import HeartbeatSender
from storage import OutboxStorage


load_dotenv(os.path.join(CURRENT_DIR, ".env"))


class LoggerMixin:
    def log(self, message, level="INFO"):
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        name = getattr(self, "device_name", "Manager")
        print(f"[{timestamp}] [{level}] [{name}] {message}")


class OutboxSender(threading.Thread, LoggerMixin):
    """Delivers queued ECG recordings to SmartWard and manages retention of
    both the outbox rows and the files on disk."""

    def __init__(self, settings, storage, api_client):
        super().__init__(daemon=True)
        self.settings = settings
        self.storage = storage
        self.api_client = api_client
        self.device_name = "Sender"
        self.running = True
        self.last_gc_at = 0
        self.last_sent_at = None

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
        elif failure_class == "auth":
            if retry_count > self.settings.max_attempts_auth:
                dead, reason = True, f"auth rejected after {retry_count} attempts"
        else:  # transient
            if age > self.settings.max_age_transient_hours * 3600:
                dead, reason = True, f"undeliverable for > {self.settings.max_age_transient_hours}h"

        if dead:
            self.storage.mark_dead(row_id, reason, http_status, failure_class, detail)
            self.storage.bump_counter("failed_total")
            level = "ERROR" if failure_class in ("hard", "auth") else "WARNING"
            self.log(f"Dead-letter {event['event_id']} [{failure_class}]: {reason}: {detail}", level)
        else:
            delay = self.compute_retry_delay(retry_count)
            self.storage.mark_retry(row_id, retry_count, delay, detail, http_status, failure_class)
            self.log(
                f"Send failed {event['event_id']} [{failure_class}], retry {retry_count} in {delay}s: {detail}",
                "WARNING",
            )

    def build_body(self, meta):
        """Read the files and build the POST /api/v1/ecg body at send time.
        Raises if the XML is gone — the caller dead-letters that row."""
        with open(meta["xml_path"], "rb") as fh:
            xml = fh.read()
        body = {
            "gateway_event_id": meta["event_id"],
            "gateway_id": self.settings.gateway_id,
            "patient_code": meta.get("patient_id") or None,
            "captured_at": meta.get("captured_at"),
            "filename": meta.get("orig_filename"),
            "xml_b64": base64.b64encode(xml).decode("ascii"),
        }
        pdf_path = meta.get("pdf_path")
        if pdf_path and os.path.exists(pdf_path):
            with open(pdf_path, "rb") as fh:
                body["pdf_b64"] = base64.b64encode(fh.read()).decode("ascii")
        return body

    def sweep_orphan_files(self):
        """Delete files whose outbox rows were purged (sent + past retention),
        oldest first, and enforce the disk cap on what remains."""
        files_dir = os.path.abspath(self.settings.files_dir)
        if not os.path.isdir(files_dir):
            return
        referenced = self.storage.referenced_file_paths()
        entries = []
        for name in os.listdir(files_dir):
            path = os.path.join(files_dir, name)
            if os.path.isfile(path):
                entries.append((os.path.getmtime(path), path, os.path.getsize(path)))
        entries.sort()

        removed = 0
        # 1) Orphans: not referenced by any row and older than an hour (grace
        #    so a file being enqueued right now is never swept).
        for mtime, path, _size in entries:
            if path not in referenced and (time.time() - mtime) > 3600:
                try:
                    os.remove(path)
                    removed += 1
                except OSError:
                    pass
        # 2) Disk cap: oldest files first, but never a file whose row is unsent.
        total_mb = sum(s for _m, p, s in entries if os.path.exists(p)) / (1024 * 1024)
        if total_mb > self.settings.max_files_mb:
            unsent = self._unsent_paths()
            for _mtime, path, size in entries:
                if total_mb <= self.settings.max_files_mb:
                    break
                if not os.path.exists(path) or path in unsent:
                    continue
                try:
                    os.remove(path)
                    total_mb -= size / (1024 * 1024)
                    removed += 1
                except OSError:
                    pass
        if removed:
            self.log(f"Janitor removed {removed} old ECG file(s) from {files_dir}")

    def _unsent_paths(self):
        paths = set()
        for event in self.storage.fetch_due_events(10000):
            try:
                meta = json.loads(event["payload_json"])
            except Exception:
                continue
            for key in ("xml_path", "pdf_path"):
                if meta.get(key):
                    paths.add(os.path.abspath(meta[key]))
        return paths

    def maybe_run_janitor(self):
        now = time.time()
        if now - self.last_gc_at < 300:
            return
        self.last_gc_at = now
        try:
            r = self.storage.run_maintenance(
                self.settings.retention_sent_hours,
                self.settings.retention_dead_days,
                self.settings.max_db_rows,
                self.settings.max_db_mb,
            )
            self.sweep_orphan_files()
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
                f"ALARM: evicted {r['evicted_pending']} UNSENT ECG(s) to stay under size cap - data lost",
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
                    try:
                        meta = json.loads(event["payload_json"])
                        body = self.build_body(meta)
                    except Exception as exc:
                        self.storage.mark_dead(event["id"], "file missing/corrupt", None, "hard", str(exc))
                        self.storage.bump_counter("failed_total")
                        self.log(f"Dead-letter {event['event_id']}: {exc}", "ERROR")
                        continue

                    ok, failure_class, http_status, detail = self.api_client.send_ecg(
                        body, event["event_id"]
                    )
                    if ok:
                        self.storage.mark_sent(event["id"])
                        self.storage.bump_counter("sent_total")
                        self.last_sent_at = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                        self.log(
                            f"Sent ECG {event['event_id']} "
                            f"(patient {meta.get('patient_id') or 'unknown'}, "
                            f"{meta.get('size_bytes', 0):,} bytes)"
                        )
                    else:
                        self.handle_failure(event, failure_class, http_status, detail)

                time.sleep(1)
            except Exception as exc:
                self.log(f"Sender loop error: {exc}", "ERROR")
                time.sleep(self.settings.send_interval)

    def stop(self):
        self.running = False


class EcgListenerService(threading.Thread, LoggerMixin):
    """TC35-compatible HTTP listener: every accepted upload is written to disk
    and queued before the machine gets its 'OK' — so an acknowledged ECG can
    no longer be lost to a power or Wi-Fi failure."""

    def __init__(self, settings, storage):
        super().__init__(daemon=True)
        self.settings = settings
        self.storage = storage
        self.device_name = "ECG-HTTP"
        self.running = True
        self.server = None
        self._lock = threading.Lock()
        self.peers = {}  # ip -> {"last_seen": ts, "uploads": n}
        self.received_total = 0
        self.duplicate_total = 0
        self.rejected_total = 0
        self.last_received_at = None
        self.last_status_report_at = 0

    def compute_event_id(self, data: bytes) -> str:
        # Content-addressed: the TC35 re-sending the same recording (lost OK,
        # operator retry) hashes identically and is deduplicated locally and
        # by the server's idempotency check.
        return hashlib.sha1(
            (self.settings.gateway_id + "|").encode("utf-8") + data
        ).hexdigest()

    def on_upload(self, data, filename, peer_ip, method) -> bool:
        """Handle one uploaded file. Returning True sends the TC35 its 'OK'."""
        now = datetime.now()
        with self._lock:
            self.received_total += 1
            self.last_received_at = now.strftime("%Y-%m-%d %H:%M:%S")
            peer = self.peers.setdefault(peer_ip, {"last_seen": 0, "uploads": 0})
            peer["last_seen"] = time.time()
            peer["uploads"] += 1
        self.storage.bump_counter("received_total")

        try:
            ext = detect_file_type(data)
            if ext not in (".xml", ".pdf"):
                with self._lock:
                    self.rejected_total += 1
                self.storage.bump_counter("rejected_total")
                self.log(f"Rejected non-ECG upload from {peer_ip} ({ext}, {len(data)} bytes)", "WARNING")
                return False

            event_id = self.compute_event_id(data)
            stamp = now.strftime("%Y%m%d_%H%M%S")
            base = f"ecg_upload_{stamp}_{event_id[:8]}"

            files_dir = os.path.abspath(self.settings.files_dir)
            os.makedirs(files_dir, exist_ok=True)

            xml_path = pdf_path = None
            patient_id = ""
            if ext == ".xml":
                xml_path = os.path.join(files_dir, base + ".xml")
                with open(xml_path, "wb") as fh:
                    fh.write(data)
                patient_id = extract_patient_id(data)
                pdf = extract_pdf_from_xml(data)
                if pdf:
                    pdf_path = os.path.join(files_dir, base + "_extracted.pdf")
                    with open(pdf_path, "wb") as fh:
                        fh.write(pdf)
            else:
                # A bare PDF upload: forward it as the "xml" slot is XML-only,
                # so wrap: keep the PDF and synthesize no XML. The server
                # requires XML, so bare PDFs stay local-only and are flagged.
                pdf_path = os.path.join(files_dir, base + ".pdf")
                with open(pdf_path, "wb") as fh:
                    fh.write(data)
                self.log(
                    f"Bare PDF from {peer_ip} saved locally ({len(data):,} bytes) - "
                    f"no XML metadata, cannot forward; configure the TC35 to export XML",
                    "WARNING",
                )
                return True  # ACK the machine; the file is safe on disk

            meta = {
                "event_id": event_id,
                "xml_path": xml_path,
                "pdf_path": pdf_path,
                "patient_id": patient_id,
                "captured_at": now.strftime("%Y-%m-%d %H:%M:%S"),
                "orig_filename": filename or "",
                "size_bytes": len(data),
                "source_ip": peer_ip,
                "method": method,
            }
            inserted = self.storage.enqueue(event_id, meta, f"TC35@{peer_ip}", peer_ip)
            if not inserted:
                with self._lock:
                    self.duplicate_total += 1
                self.storage.bump_counter("duplicate_total")
                # Same content re-sent: remove the duplicate files just written.
                for path in (xml_path, pdf_path):
                    if path and self._path_differs_from_queued(event_id, path):
                        try:
                            os.remove(path)
                        except OSError:
                            pass
            state = "queued" if inserted else "duplicate ignored"
            self.log(
                f"ECG received from {peer_ip} via {method}: patient={patient_id or '?'} "
                f"{len(data):,} bytes pdf={'yes' if pdf_path else 'no'} [{state}]"
            )
            return True
        except Exception as exc:
            self.log(f"Failed to store upload from {peer_ip}: {exc}", "ERROR")
            return False  # TC35 shows the error and the operator can retry

    def _path_differs_from_queued(self, event_id, path) -> bool:
        """True if `path` is not the file the queued row references (i.e. this
        was a duplicate re-write with a new timestamped name)."""
        referenced = self.storage.referenced_file_paths()
        return os.path.abspath(path) not in referenced

    def peer_snapshot(self):
        now = time.time()
        with self._lock:
            devices = [
                {
                    "ip": ip,
                    "name": f"TC35@{ip}",
                    "alive": (now - peer["last_seen"]) <= self.settings.peer_online_seconds,
                    "last_seen_s": int(now - peer["last_seen"]),
                    "messages": peer["uploads"],
                }
                for ip, peer in self.peers.items()
            ]
        return devices

    def maybe_report_queue_state(self):
        now = time.time()
        if now - self.last_status_report_at < 60:
            return
        stats = self.storage.get_stats()
        level = "WARNING" if stats["dead"] else "INFO"
        self.log(
            f"Queue pending={stats['pending']} retry={stats['retry']} "
            f"dead={stats['dead']} oldest_age={stats['oldest_pending_age_s']}s "
            f"db={stats['db_size_mb']}MB; "
            f"ecg received={self.received_total} dup={self.duplicate_total} "
            f"rejected={self.rejected_total}",
            level,
        )
        self.last_status_report_at = now

    def run(self):
        self.log(
            f"Starting ECG HTTP listener on {self.settings.listen_host}:{self.settings.listen_port} "
            f"(Basic auth user '{self.settings.basic_username}')"
        )
        try:
            self.server = make_server(
                on_upload=self.on_upload,
                host=self.settings.listen_host,
                port=self.settings.listen_port,
                username=self.settings.basic_username,
                password=self.settings.basic_password,
                max_upload_mb=self.settings.max_upload_mb,
            )
        except OSError as exc:
            self.log(f"Cannot bind {self.settings.listen_host}:{self.settings.listen_port}: {exc}", "ERROR")
            return
        self.log("Listener ready; waiting for ECG uploads")
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
    """Supervises the workers and feeds the heartbeat its ECG telemetry."""

    def __init__(self, settings):
        self.settings = settings
        self.device_name = "Manager"
        self.storage = OutboxStorage(self.settings.queue_db_path)
        self.api_client = ApiClient(self.settings)
        self.sender = OutboxSender(self.settings, self.storage, self.api_client)
        self.listener = EcgListenerService(self.settings, self.storage)
        self.heartbeat = (
            HeartbeatSender(self.settings, self.storage, self.api_client, self)
            if self.settings.heartbeat_enabled
            else None
        )
        self.running = True

    def monitor_snapshot(self):
        """Heartbeat `monitor` block — same shape as the vital-sign gateways so
        the dashboard renders every gateway type. Devices here are the ECG
        machines that have uploaded recently."""
        devices = self.listener.peer_snapshot()
        return {
            "count": len(devices),
            "connected": sum(1 for d in devices if d["alive"]),
            "devices": devices,
            "listener": {
                "port": self.settings.listen_port,
                "alive": self.listener.is_alive(),
            },
        }

    def ecg_snapshot(self):
        """ECG delivery counters for the heartbeat's `ecg` block. Totals come
        from the persistent counters table, so they survive restarts."""
        counters = self.storage.get_counters()
        stats = self.storage.get_stats()
        files_dir = os.path.abspath(self.settings.files_dir)
        store_files = store_mb = 0
        try:
            for name in os.listdir(files_dir):
                path = os.path.join(files_dir, name)
                if os.path.isfile(path):
                    store_files += 1
                    store_mb += os.path.getsize(path)
        except OSError:
            pass
        return {
            "received_total": counters.get("received_total", 0),
            "duplicate_total": counters.get("duplicate_total", 0),
            "rejected_total": counters.get("rejected_total", 0),
            "sent_total": counters.get("sent_total", 0),
            "failed_total": counters.get("failed_total", 0),
            "pending": stats["pending"] + stats["retry"],
            "last_received_at": self.listener.last_received_at,
            "last_sent_at": self.sender.last_sent_at,
            "store_files": store_files,
            "store_mb": round(store_mb / (1024 * 1024), 1),
        }

    def start_listener(self):
        self.listener = EcgListenerService(self.settings, self.storage)
        self.listener.start()

    def run(self):
        self.log("Starting ECG gateway manager")
        self.log(f"Queue DB: {self.storage.db_path}")
        self.log(f"Files:    {os.path.abspath(self.settings.files_dir)}")
        self.sender.start()
        self.listener.start()
        if self.heartbeat:
            self.heartbeat.start()

        try:
            while self.running:
                if not self.listener.is_alive():
                    self.log("ECG listener stopped unexpectedly, restarting", "WARNING")
                    time.sleep(2)
                    self.start_listener()
                self.listener.maybe_report_queue_state()
                time.sleep(1)
        except KeyboardInterrupt:
            self.log("Shutdown requested by user")
        finally:
            self.running = False
            self.listener.stop()
            self.listener.join(timeout=10)
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
