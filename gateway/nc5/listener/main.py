#!/usr/bin/python
# -*- coding: utf-8 -*-
"""
Comen NC5 gateway — HL7/MLLP listener with store-and-forward to SmartWard.

Same architecture as gateway/vs4_v2 (SQLite outbox, classified retries,
dead-letter, heartbeat telemetry, server-assigned identity) with the two things
the NC5 needs on top:

  * It answers the monitor's patient query (QRY^R02 / QRY^A19) from the real
    SmartWard patient record — GET /api/v1/patients/{code} — instead of a local
    fixture file, so what the nurse sees on the bedside screen is what the ward
    system holds.
  * It is a continuous monitor, not a spot-check one. Observations stream in
    every few seconds, so they are aggregated per patient into a window
    (RECORD_INTERVAL) and posted as one reading carrying the latest value plus
    the PR/SpO2 range observed — the same shape mp5sc_v2 sends. A completed NIBP
    cycle flushes its window immediately, because a new cuff reading is a
    clinical event in its own right.

Everything posted uses the field names POST /api/v1/vital-signs validates:
patient_code, measured_at, blood_pressure_systolic/diastolic, pulse_rate(+min/
max), heart_rate, spo2(+min/max), temperature, respiratory_rate, weight, height.
"""

import hashlib
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
from config import load_settings
from heartbeat import HeartbeatSender
from hl7_mllp import Reply, make_server, parse_msh
from hl7_oru import is_observation, parse_message
from hl7_query import build_response, is_query, parse_query
from patient_directory import PatientDirectory
from storage import OutboxStorage


load_dotenv(os.path.join(CURRENT_DIR, ".env"))

# Fields the API also accepts a min/max for, so a window can report the range
# observed ("SpO2 94-97") instead of pretending one sample stood for five minutes.
_RANGE_FIELDS = ("pulse_rate", "spo2")


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


_BP_FIELDS = ("blood_pressure_systolic", "blood_pressure_diastolic")


class VitalWindow:
    """One patient's observations on one monitor, collapsed into one reading.

    The NC5 streams the same vitals over and over; storing every message would
    bury the chart. Instead each field keeps its latest value, PR and SpO2 also
    keep the min/max seen, and the window is flushed on an interval (or as soon
    as a new NIBP completes) and then reset in place.

    Blood pressure needs its own bookkeeping: the monitor repeats the last
    completed cuff reading in every subsequent message, so the window tracks
    which BP it has already filed and refuses to file it twice.
    """

    def __init__(self, patient_id, patient_name, bed, peer_ip, device_name,
                 bp_repeat_guard=60):
        self.patient_id = patient_id
        self.patient_name = patient_name
        self.bed = bed
        self.peer_ip = peer_ip
        self.device_name = device_name
        self.bp_repeat_guard = bp_repeat_guard
        self.bp_values = None      # last (systolic, diastolic) seen
        self.bp_observed = None    # the time the monitor stamped on it
        self.bp_charted = False
        self.bp_charted_at = 0
        self.reset()

    def reset(self):
        """Start a fresh window, keeping the identity and what BP was charted."""
        self.opened_at = time.time()
        self.updated_at = self.opened_at
        self.values = {}
        self.ranges = {}       # field -> [min, max]
        self.observed = {}     # field -> datetime the monitor stamped on it
        self.sources = {}      # field -> e.g. "ART" when BP came off an art line
        self.context = {}      # ACVPU / probe site / operator, for the notes
        self.samples = 0

    def update(self, reading) -> bool:
        """Fold one parsed message in. Returns True if a new NIBP completed."""
        self.updated_at = time.time()
        self.samples += 1
        if reading["bed"]:
            self.bed = reading["bed"]
        if reading["patient_name"]:
            self.patient_name = reading["patient_name"]

        for api_field, value in reading["vitals"].items():
            self.values[api_field] = value
            observed = reading["observed_at"].get(api_field) or reading["measured_at"]
            if observed:
                self.observed[api_field] = observed
            if api_field in _RANGE_FIELDS:
                bounds = self.ranges.get(api_field)
                if bounds is None:
                    self.ranges[api_field] = [value, value]
                else:
                    bounds[0] = min(bounds[0], value)
                    bounds[1] = max(bounds[1], value)
        self.sources.update(reading["sources"])
        self.context.update(reading["context"])
        return self._absorb_bp(reading)

    def _absorb_bp(self, reading) -> bool:
        """Decide whether this message carries a cuff reading we have not filed.

        Different numbers are always a new cycle. Identical numbers with a later
        observation time are only treated as a new cycle once `bp_repeat_guard`
        has passed, so a monitor that re-stamps its retransmissions cannot chart
        the same BP over and over.
        """
        values = tuple(reading["vitals"].get(name) for name in _BP_FIELDS)
        if all(value is None for value in values):
            return False
        observed = max(
            (reading["observed_at"][name] for name in _BP_FIELDS if name in reading["observed_at"]),
            default=None,
        )

        if values != self.bp_values:
            new_cycle = True
        else:
            new_cycle = (
                observed is not None
                and observed != self.bp_observed
                and (time.time() - self.bp_charted_at) >= self.bp_repeat_guard
            )
        self.bp_values = values
        self.bp_observed = observed
        if new_cycle:
            self.bp_charted = False
        return new_cycle

    def mark_bp_charted(self):
        self.bp_charted = True
        self.bp_charted_at = time.time()

    def newest_observation(self):
        return max(self.observed.values()) if self.observed else None

    def age(self) -> float:
        return time.time() - self.opened_at

    def idle(self) -> float:
        return time.time() - self.updated_at


class Nc5ListenerService(threading.Thread, LoggerMixin):
    """Passive MLLP/TCP server for the NC5.

    Handles three inbound message families on the monitor's single long-lived
    connection: QRY (answered from SmartWard), ORU (aggregated and queued), and
    everything else (ADT and friends — acknowledged, and used only to follow
    which patient the monitor believes is in which bed).
    """

    def __init__(self, settings, storage, api_client, directory):
        super().__init__(daemon=True)
        self.settings = settings
        self.storage = storage
        self.api_client = api_client
        self.directory = directory
        self.device_name = "HL7"
        self.running = True
        self.server = None
        self._lock = threading.Lock()
        # peer ip -> {"last_seen": ts, "messages": n, "name": str}
        self.peers = {}
        # (peer ip, patient id) -> VitalWindow
        self.windows = {}
        # (peer ip, bed) -> patient id, so a bed handover flushes the old window
        self.bed_patients = {}
        # Optional registry from the SmartWard API: ip -> {"id":..., "name":...}
        self.device_map = {}
        self.messages_total = 0
        self.queued_total = 0
        self.queries_total = 0
        self.skipped_no_patient = 0
        self.skipped_no_vitals = 0
        self.last_message_at = 0
        self.last_status_report_at = 0
        self._status_sent_at = {}

    # ---- device registry (optional, mirrors mp5sc's API-managed mode) ------

    def set_device_map(self, devices):
        mapping = {}
        for device in devices:
            ip = (device.get("ip_address") or "").strip()
            if ip:
                mapping[ip] = {"id": device.get("id"), "name": device.get("name") or "NC5"}
        with self._lock:
            self.device_map = mapping

    def _peer_label(self, peer_ip):
        info = self.device_map.get(peer_ip)
        return info["name"] if info else f"NC5@{peer_ip}"

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

    # ---- payload construction ----------------------------------------------

    def compute_event_id(self, patient_id, measured_at_text, payload):
        # Content-addressed: a monitor retransmitting the same reading (lost ACK,
        # reboot, new control id) hashes to the same event and is deduplicated by
        # the outbox UNIQUE constraint and the server idempotency key.
        parts = [self.settings.gateway_id, patient_id.strip(), measured_at_text]
        for key in sorted(payload):
            if key in ("patient_code", "patient_name", "measured_at", "notes"):
                continue
            parts.append(f"{key}={payload[key]}")
        return hashlib.sha1("|".join(parts).encode("utf-8")).hexdigest()

    def _bounds(self):
        s = self.settings
        # (low, high, low_is_inclusive, cast)
        return {
            "blood_pressure_systolic": (0, 300, False, int),
            "blood_pressure_diastolic": (0, 200, False, int),
            "pulse_rate": (s.pr_range_min, s.pr_range_max, True, int),
            "pulse_rate_min": (s.pr_range_min, s.pr_range_max, True, int),
            "pulse_rate_max": (s.pr_range_min, s.pr_range_max, True, int),
            "heart_rate": (s.pr_range_min, s.pr_range_max, True, int),
            "spo2": (s.spo2_range_min, s.spo2_range_max, True, lambda v: round(v, 1)),
            "spo2_min": (s.spo2_range_min, s.spo2_range_max, True, int),
            "spo2_max": (s.spo2_range_min, s.spo2_range_max, True, int),
            "temperature": (20, 50, False, lambda v: round(v, 1)),
            "respiratory_rate": (0, 100, False, int),
            "weight": (0, 500, False, lambda v: round(v, 1)),
            "height": (0, 300, False, lambda v: round(v, 1)),
        }

    def build_notes(self, window):
        parts = [window.device_name]
        if window.bed:
            parts[0] += f" bed {window.bed}"
        bp_source = window.sources.get("blood_pressure_systolic") \
            or window.sources.get("blood_pressure_diastolic")
        if bp_source and bp_source != "NIBP":
            parts.append(f"BP from {bp_source}")
        # Context the monitor recorded with the reading (ACVPU, probe site, who
        # took it). The API has no columns for these, so the note is where they
        # survive rather than being dropped on the floor.
        for label, value in window.context.items():
            parts.append(f"{label}: {value}")
        if window.samples > 1:
            parts.append(f"{window.samples} samples over {int(window.age())}s")
        return "; ".join(parts)[:1000]

    def build_payload(self, window):
        """Turn a window into exactly the body /api/v1/vital-signs validates."""
        reference = window.newest_observation() or datetime.now()
        measured_at_text = reference.strftime("%Y-%m-%d %H:%M:%S")

        candidates = {}
        for api_field, value in window.values.items():
            if window.bp_charted and api_field in _BP_FIELDS:
                continue  # already filed; the monitor is just repeating it
            observed = window.observed.get(api_field)
            if observed is not None and \
                    (reference - observed).total_seconds() > self.settings.vital_staleness:
                # Carried over from an earlier window (typically the last cuff
                # reading): charting it now would date it wrongly.
                continue
            candidates[api_field] = value
        for api_field, (low, high) in window.ranges.items():
            if api_field in candidates:
                candidates[f"{api_field}_min"] = low
                candidates[f"{api_field}_max"] = high

        payload = {}
        for api_field, (low, high, inclusive, cast) in self._bounds().items():
            value = candidates.get(api_field)
            if value is None:
                continue
            if value == 0:
                # The NC5 sends 0 for anything not entered or not measured
                # (height/weight, a probe that is off). That is "no value", not
                # an artifact worth warning about on every single message.
                if self.settings.debug_mode:
                    self.log(f"Ignoring unset {api_field}", "DEBUG")
                continue
            in_range = (low <= value <= high) if inclusive else (low < value <= high)
            if not in_range:
                self.log(f"Dropping out-of-range {api_field}={value}", "WARNING")
                continue
            payload[api_field] = cast(value)

        # A range is only meaningful alongside the value it belongs to.
        for api_field in _RANGE_FIELDS:
            if api_field not in payload:
                payload.pop(f"{api_field}_min", None)
                payload.pop(f"{api_field}_max", None)

        if not payload:
            return None

        # Credentials are NOT stored here; they are attached by the API client at
        # send time so the queue on disk never contains passwords.
        payload["patient_code"] = window.patient_id
        payload["measured_at"] = measured_at_text
        payload["notes"] = self.build_notes(window)
        payload["gateway_id"] = self.settings.gateway_id
        payload["gateway_event_id"] = self.compute_event_id(
            window.patient_id, measured_at_text, payload
        )
        if window.patient_name:
            payload["patient_name"] = window.patient_name
        return payload

    # ---- aggregation windows ------------------------------------------------

    def _window_key(self, peer_ip, patient_id):
        return (peer_ip, patient_id)

    def _capture_window(self, window, reason):
        """Snapshot a window into a payload and reset it. Call under the lock."""
        payload = self.build_payload(window)
        if payload and any(name in payload for name in _BP_FIELDS):
            window.mark_bp_charted()
        capture = {
            "payload": payload,
            "reason": reason,
            "samples": window.samples,
            "device_name": window.device_name,
            "peer_ip": window.peer_ip,
            "patient_id": window.patient_id,
            "patient_name": window.patient_name,
        }
        window.reset()
        return capture

    def _queue_capture(self, capture):
        """Put a captured reading in the outbox. Call without the lock: SQLite
        writes must not block the threads serving the monitor's connection."""
        payload = capture["payload"]
        if not payload:
            with self._lock:
                self.skipped_no_vitals += 1
            self.log(
                f"Nothing usable to send for {capture['patient_id']} on "
                f"{capture['device_name']} ({capture['samples']} message(s), {capture['reason']})",
                "WARNING",
            )
            return

        inserted = self.storage.enqueue(
            payload["gateway_event_id"], payload, capture["device_name"], capture["peer_ip"]
        )
        with self._lock:
            if inserted:
                self.queued_total += 1
        state = "queued" if inserted else "duplicate ignored"
        summary = " ".join(
            f"{name}={payload[name]}" for name in (
                "blood_pressure_systolic", "blood_pressure_diastolic", "pulse_rate",
                "heart_rate", "spo2", "temperature", "respiratory_rate") if name in payload
        )
        self.log(
            f"Reading from {capture['device_name']} for "
            f"{capture['patient_name'] or capture['patient_id']}: {summary} "
            f"[{state}, {capture['reason']}]"
        )
        self._report_device_status(capture["peer_ip"], "Receiving", True)

    def flush_due_windows(self, force=False):
        """Called from the manager loop: close windows that are full or idle."""
        captures = []
        expiry = self.settings.record_interval + self.settings.window_idle_timeout
        with self._lock:
            for key, window in list(self.windows.items()):
                if window.samples == 0:
                    # Already flushed and quiet since: forget the patient once
                    # the monitor has clearly moved on.
                    if window.idle() >= expiry:
                        del self.windows[key]
                    continue
                if force:
                    reason = "shutdown"
                elif window.age() >= self.settings.record_interval:
                    reason = f"{self.settings.record_interval}s window"
                elif window.idle() >= self.settings.window_idle_timeout:
                    reason = f"idle {int(window.idle())}s"
                else:
                    continue
                captures.append(self._capture_window(window, reason))
        for capture in captures:
            self._queue_capture(capture)

    def _flush_patient(self, peer_ip, patient_id, reason):
        with self._lock:
            window = self.windows.pop(self._window_key(peer_ip, patient_id), None)
            capture = self._capture_window(window, reason) if window and window.samples else None
        if capture:
            self._queue_capture(capture)

    def _note_bed_patient(self, peer_ip, bed, patient_id):
        """Track bed occupancy so a handover closes the previous patient's window
        immediately instead of letting it linger until the interval expires."""
        if not bed or not patient_id:
            return
        key = (peer_ip, bed)
        with self._lock:
            previous = self.bed_patients.get(key)
            self.bed_patients[key] = patient_id
        if previous and previous != patient_id:
            self.log(f"Bed {bed} on {self._peer_label(peer_ip)}: {previous} -> {patient_id}")
            self._flush_patient(peer_ip, previous, "patient changed")

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

    # ---- MLLP callbacks -----------------------------------------------------

    @staticmethod
    def _is_probe(peer_ip):
        # The container healthcheck opens and closes the port every 30s; logging
        # that as a monitor connecting would bury the real ones.
        return peer_ip in ("127.0.0.1", "::1", "localhost")

    def on_connect(self, peer_ip):
        if self._is_probe(peer_ip) and not self.settings.debug_mode:
            return
        self.log(f"Monitor connected: {self._peer_label(peer_ip)} ({peer_ip})")

    def on_disconnect(self, peer_ip):
        if self._is_probe(peer_ip) and not self.settings.debug_mode:
            return
        self.log(f"Monitor disconnected: {self._peer_label(peer_ip)} ({peer_ip})")

    def on_message(self, hl7, framed, peer_ip):
        """Handle one HL7 message and decide what goes back over the socket."""
        now = time.time()
        with self._lock:
            self.messages_total += 1
            self.last_message_at = now
            peer = self.peers.setdefault(peer_ip, {"last_seen": 0, "messages": 0})
            peer["last_seen"] = now
            peer["messages"] += 1
            peer["name"] = self._peer_label(peer_ip)

        msh = parse_msh(hl7)
        if msh is None:
            self.log(f"Non-HL7 data from {peer_ip} ({len(hl7)} bytes) - ignored", "WARNING")
            self.save_raw(hl7, "NON-HL7")
            return Reply.ack("AA")

        message_type = msh.get("message_type") or ""
        if not framed:
            self.log(f"Un-framed (non-MLLP) {message_type} from {peer_ip} - sender misconfigured?", "WARNING")
        self.save_raw(hl7, message_type.replace("^", "_") or "UNKNOWN")

        if is_query(message_type):
            if not self.settings.query_enabled:
                self.log(f"Patient query from {peer_ip} ignored (QUERY_ENABLED=false)", "WARNING")
                return Reply.ack("AA")
            return self.handle_query(hl7, peer_ip)

        reading = parse_message(hl7)
        if reading is None:
            return Reply.ack("AA")

        if not is_observation(message_type):
            # ADT^A08 and friends: the monitor telling us who it thinks is in the
            # bed. Useful context, but never a vitals record.
            self._note_bed_patient(peer_ip, reading["bed"], reading["patient_id"])
            if self.settings.debug_mode:
                self.log(
                    f"{message_type} from {peer_ip}: patient={reading['patient_id'] or '-'} "
                    f"bed={reading['bed'] or '-'}",
                    "DEBUG",
                )
            return Reply.ack("AA")

        return self.handle_observation(reading, peer_ip, message_type)

    def handle_query(self, hl7, peer_ip):
        query = parse_query(hl7)
        if query is None:
            return Reply.ack("AE")

        with self._lock:
            self.queries_total += 1

        code = query["patient_code"]
        if not code:
            self.log(f"Patient query from {peer_ip} carried no patient id", "WARNING")
            return Reply.raw(build_response(query, "not_found", None, self.settings))

        status, patient, detail, stale = self.directory.lookup(code)
        if status == "found":
            self.log(
                f"Query from {self._peer_label(peer_ip)} for {code} -> "
                f"{patient.get('name')} (MRN {patient.get('mrn')}, "
                f"{patient.get('ward_name') or 'no ward'} "
                f"bed {patient.get('bed_number') or '-'})"
                + (" [cached]" if stale else "")
            )
        elif status == "not_found":
            self.log(f"Query from {self._peer_label(peer_ip)} for {code}: not in SmartWard")
        else:
            self.log(
                f"Query from {self._peer_label(peer_ip)} for {code} failed: {detail}",
                "ERROR",
            )

        return Reply.raw(build_response(query, status, patient, self.settings))

    def handle_observation(self, reading, peer_ip, message_type):
        patient_id = reading["patient_id"]
        if not patient_id:
            # A reading we cannot attribute must never be guessed onto a patient.
            with self._lock:
                self.skipped_no_patient += 1
            self.log(
                f"Skipping {message_type} from {peer_ip}: no patient ID "
                f"(admit the patient on the NC5 before it can be charted)",
                "WARNING",
            )
            self._report_device_status(peer_ip, "Reading without patient ID", True)
            return Reply.ack("AA")

        if not reading["vitals"]:
            if self.settings.debug_mode:
                self.log(f"{message_type} from {peer_ip} had no recognised vitals", "DEBUG")
            return Reply.ack("AA")

        self._note_bed_patient(peer_ip, reading["bed"], patient_id)

        key = self._window_key(peer_ip, patient_id)
        with self._lock:
            window = self.windows.get(key)
            if window is None:
                window = VitalWindow(
                    patient_id, reading["patient_name"], reading["bed"],
                    peer_ip, self._peer_label(peer_ip),
                    bp_repeat_guard=self.settings.bp_repeat_guard,
                )
                self.windows[key] = window
            new_bp = window.update(reading)
            # A completed cuff reading is a clinical event: chart it now rather
            # than holding it until the interval expires.
            capture = self._capture_window(window, "NIBP complete") \
                if (new_bp and self.settings.flush_on_bp) else None
        if capture:
            self._queue_capture(capture)
        return Reply.ack("AA")

    # ---- snapshots for heartbeat / logging ---------------------------------

    def peer_snapshot(self):
        now = time.time()
        with self._lock:
            devices = [
                {
                    "ip": ip,
                    "name": peer.get("name") or f"NC5@{ip}",
                    "alive": (now - peer["last_seen"]) <= self.settings.peer_online_seconds,
                    "last_seen_s": int(now - peer["last_seen"]),
                    "messages": peer["messages"],
                }
                for ip, peer in self.peers.items()
            ]
            totals = {
                "messages_total": self.messages_total,
                "queued_total": self.queued_total,
                "queries_total": self.queries_total,
                "open_windows": len(self.windows),
                "skipped_no_patient": self.skipped_no_patient,
                "skipped_no_vitals": self.skipped_no_vitals,
            }
        return devices, totals

    def maybe_report_queue_state(self):
        now = time.time()
        if now - self.last_status_report_at < 60:
            return
        stats = self.storage.get_stats()
        lookups = self.directory.snapshot()
        level = "WARNING" if stats["dead"] else "INFO"
        self.log(
            f"Queue pending={stats['pending']} retry={stats['retry']} "
            f"dead={stats['dead']} oldest_age={stats['oldest_pending_age_s']}s "
            f"db={stats['db_size_mb']}MB; "
            f"hl7 msgs={self.messages_total} queued={self.queued_total} "
            f"windows={len(self.windows)} no_patient={self.skipped_no_patient}; "
            f"queries={lookups['lookups']} found={lookups['found']} "
            f"missing={lookups['not_found']} errors={lookups['errors']}",
            level,
        )
        self.last_status_report_at = now

    # ---- thread body --------------------------------------------------------

    def run(self):
        self.log(
            f"Starting HL7/MLLP listener on {self.settings.listen_host}:{self.settings.listen_port}"
        )
        try:
            self.server = make_server(
                on_message=self.on_message,
                host=self.settings.listen_host,
                port=self.settings.listen_port,
                app=self.settings.ack_app,
                facility=self.settings.ack_facility,
                idle_flush=self.settings.idle_flush_seconds,
                ack_message_type=self.settings.ack_message_type,
                capture_dir=self.settings.capture_dir,
                on_connect=self.on_connect,
                on_disconnect=self.on_disconnect,
            )
        except OSError as exc:
            self.log(f"Cannot bind {self.settings.listen_host}:{self.settings.listen_port}: {exc}", "ERROR")
            return
        self.log(
            "Listener ready; point the NC5's HL7 export and patient query at this "
            f"host on port {self.settings.listen_port}"
        )
        if self.settings.capture_dir:
            self.log(f"Wire capture ON: every byte both ways -> {os.path.abspath(self.settings.capture_dir)}")
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
    """Supervises the workers (HL7 listener, outbox sender, heartbeat), closes
    aggregation windows on time, restarts the listener if it dies, and
    optionally syncs the device registry from the SmartWard API."""

    def __init__(self, settings):
        self.settings = settings
        self.device_name = "Manager"
        self.storage = OutboxStorage(self.settings.queue_db_path)
        self.api_client = ApiClient(self.settings)
        self.directory = PatientDirectory(self.settings, self.api_client, self.log)
        self.sender = OutboxSender(self.settings, self.storage, self.api_client)
        self.listener = self._new_listener()
        self.heartbeat = (
            HeartbeatSender(self.settings, self.storage, self.api_client, self)
            if self.settings.heartbeat_enabled
            else None
        )
        self.running = True

    def _new_listener(self):
        return Nc5ListenerService(self.settings, self.storage, self.api_client, self.directory)

    def monitor_snapshot(self):
        """Heartbeat `monitor` block — same shape as vs4_v2/mp5sc_v2 so the
        server dashboard renders every gateway type the same way."""
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
            "lookups": self.directory.snapshot(),
        }

    def start_listener(self):
        previous = self.listener
        self.listener = self._new_listener()
        # Carry the device registry over so a restart does not lose device names.
        self.listener.device_map = previous.device_map
        self.listener.start()

    def run(self):
        self.log("Starting Comen NC5 manager")
        self.log(f"Queue DB: {self.storage.db_path}")
        self.log(
            f"Aggregating vitals per patient every {self.settings.record_interval}s "
            f"(NIBP flushes immediately: {self.settings.flush_on_bp})"
        )
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

                self.listener.flush_due_windows()
                self.listener.maybe_report_queue_state()
                time.sleep(1)
        except KeyboardInterrupt:
            self.log("Shutdown requested by user")
        finally:
            self.running = False
            self.listener.stop()
            self.listener.join(timeout=10)
            # Never discard buffered vitals on the way out: the queue is durable,
            # the in-memory windows are not.
            self.listener.flush_due_windows(force=True)
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
