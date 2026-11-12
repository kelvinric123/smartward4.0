#!/usr/bin/python
# -*- coding: utf-8 -*-
"""
ECG USB gateway - TC35 "save to USB stick" store-and-forward gateway.

Sibling of gateway/ecg (which receives the TC35's HTTP uploads). Here the
TC35 has no network at all: a Raspberry Pi Zero 2W plugs into the TC35's USB
port and pretends to be a USB flash drive (kernel mass-storage gadget backed
by a FAT32 image). Whatever the TC35 saves to that "stick" is picked up by
the UsbWatcher below and forwarded over the Pi's Wi-Fi to SmartWard's
POST /api/v1/ecg - the same endpoint, payload, outbox, retries, dead-letter
and heartbeat as gateway/ecg, so the server side needs no changes.

Files live on disk under FILES_DIR; the outbox row carries only metadata, and
the base64 upload body is built at send time.
"""

import base64
import hashlib
import json
import os
import posixpath
import re
import shutil
import sys
import threading
import time
from datetime import datetime

CURRENT_DIR = os.path.dirname(__file__)
SRC_DIR = os.path.join(CURRENT_DIR, "src")

if SRC_DIR not in sys.path:
    sys.path.insert(0, SRC_DIR)

from api_client import ApiClient
from config import load_settings
from ecg_files import (
    acquisition_keys, extract_patient_id, extract_pdf_from_xml, parse_tc35_pdf_name, pdf_complete,
    philips_info, philips_root, to_study_xml,
    xml_complete,
)
from heartbeat import HeartbeatSender
from storage import OutboxStorage, TIME_FMT, utcnow
from usb_drive import DirDrive, DriveError, Gadget, ImageDrive, path_key, walk


try:
    # Local development only: on the Pi the config comes from the systemd
    # EnvironmentFile, and the gateway needs no third-party packages at all.
    from dotenv import load_dotenv

    load_dotenv(os.path.join(CURRENT_DIR, ".env"))
except ImportError:
    pass

# Bump when the conversion of drive files changes: files captured by an older
# version are captured again and re-sent (see UsbWatcher._unchanged).
# v2: Philips XML (TC35 USB export) re-wrapped in the Study layout.
CAPTURE_VERSION = 2
# How soon to look again at a file that was not complete yet.
RETRY_SCAN_SECONDS = 30
# Minimum gap between two drive cleanups (each one replugs the stick).
CLEANUP_BACKOFF_SECONDS = 1800
# Rebind a gadget found unbound for this long (e.g. a failed replug).
REBIND_AFTER_SECONDS = 10


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
            created = datetime.strptime(created_at, TIME_FMT)
            return max((utcnow() - created).total_seconds(), 0)
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
        Raises if the XML is gone - the caller dead-letters that row."""
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


class UsbWatcher(threading.Thread, LoggerMixin):
    """Finds new TC35 recordings on the virtual USB drive and queues them.

    The drive is only read after the TC35's writes have gone quiet, and a
    recording is only captured once its XML (and a PDF saved beside it) is
    complete - so a half-written file is never forwarded. Captured files stay
    on the drive; they are deleted only by the cleanup, and only once the
    server has acknowledged them."""

    def __init__(self, settings, storage, drive):
        super().__init__(daemon=True)
        self.settings = settings
        self.storage = storage
        self.drive = drive
        self.device_name = "USB"
        self.running = True
        self.last_signature = None
        self.last_activity = time.monotonic()
        self.dirty = True  # scan once at startup
        self.retry_at = None
        self.last_scan_at = time.monotonic()
        self.last_scan_wall = None
        self.last_received_at = None
        self.last_cleanup_attempt = 0.0
        self.last_cleanup_at = None
        self.usage_pct = None
        self.files_on_drive = None
        self.last_error = None
        self.unbound_since = None
        self.last_status_report_at = 0

    # ---- capture ------------------------------------------------------------

    def compute_event_id(self, data: bytes) -> str:
        # Content-addressed, like gateway/ecg: the same recording saved twice
        # (or re-read after a restart) hashes identically and is deduplicated
        # locally and by the server's idempotency check. The capture version is
        # part of the hash so a file re-captured by a newer conversion is a new
        # upload, not a duplicate of the old one.
        return hashlib.sha1(
            f"{self.settings.gateway_id}|v{CAPTURE_VERSION}|".encode("utf-8") + data
        ).hexdigest()

    @staticmethod
    def _read(path: str) -> bytes:
        with open(path, "rb") as fh:
            return fh.read()

    @staticmethod
    def _write_durable(path: str, data: bytes) -> None:
        tmp = path + ".tmp"
        with open(tmp, "wb") as fh:
            fh.write(data)
            fh.flush()
            os.fsync(fh.fileno())
        os.replace(tmp, path)

    @staticmethod
    def _waited_seconds(first_seen_at: str) -> float:
        try:
            return (utcnow() - datetime.strptime(first_seen_at, TIME_FMT)).total_seconds()
        except (ValueError, TypeError):
            return 0

    def _retry_later(self):
        due = time.monotonic() + RETRY_SCAN_SECONDS
        self.retry_at = due if self.retry_at is None else min(self.retry_at, due)

    def _mark(self, entry, state, event_id=None) -> str:
        return self.storage.usb_mark(
            path_key(entry.relpath), entry.relpath, entry.size, entry.mtime_ns, state, event_id,
            CAPTURE_VERSION,
        )

    @staticmethod
    def _unchanged(entry, seen, states) -> bool:
        row = seen.get(path_key(entry.relpath))
        return (
            row is not None
            and row["size"] == entry.size
            and row["mtime_ns"] == entry.mtime_ns
            and row["state"] in states
            and (row["state"] != "captured" or row.get("capture_version", 1) == CAPTURE_VERSION)
        )

    def _warn_no_patient(self, entry):
        self.log(
            f"{entry.relpath}: no patient ID in the TC35 export - SmartWard cannot match a patient; "
            f"enter the MRN as Patient ID on the TC35", "WARNING"
        )

    def ingest(self, xml_data, pdf_data, entry, pdf_source, pdf_only=None) -> str:
        """Store one recording in FILES_DIR and queue it. Returns its event id.

        A Philips native XML (the TC35's USB export) is re-wrapped in the Study
        layout SmartWard's ECG pages read; the original travels in <RawData>.
        pdf_only = (patient_id, acquired) for a TC35 PDF exported without XML:
        the Study is built from its file name, with the PDF as the report."""
        now = datetime.now()
        self.storage.bump_counter("received_total")
        self.last_received_at = now.strftime("%Y-%m-%d %H:%M:%S")
        if pdf_only is not None:
            event_id = self.compute_event_id(pdf_data)
            patient_id, acquired = pdf_only
            info = {"PatientID": patient_id, "RefDateAcquired": acquired}
            xml_data = to_study_xml(None, info, pdf_data, event_id[:16], study_format="PDF")
            if not patient_id:
                self._warn_no_patient(entry)
        else:
            event_id = self.compute_event_id(xml_data)
            philips = philips_root(xml_data)
            if philips is not None:
                info = philips_info(philips, self.settings.patient_id_fields)
                patient_id = info["PatientID"]
                xml_data = to_study_xml(xml_data, info, pdf_data, event_id[:16])
                if not patient_id:
                    self._warn_no_patient(entry)
            else:
                patient_id = extract_patient_id(xml_data)

        if self.storage.has_event(event_id):
            self.storage.bump_counter("duplicate_total")
            self.log(f"{entry.relpath}: same recording already queued (patient={patient_id or '?'}) [duplicate ignored]")
            return event_id

        files_dir = os.path.abspath(self.settings.files_dir)
        os.makedirs(files_dir, exist_ok=True)
        base = f"ecg_usb_{now.strftime('%Y%m%d_%H%M%S')}_{event_id[:8]}"
        xml_path = os.path.join(files_dir, base + ".xml")
        self._write_durable(xml_path, xml_data)
        pdf_path = None
        if pdf_data:
            pdf_path = os.path.join(files_dir, base + "_extracted.pdf")
            self._write_durable(pdf_path, pdf_data)

        meta = {
            "event_id": event_id,
            "xml_path": xml_path,
            "pdf_path": pdf_path,
            "patient_id": patient_id,
            "captured_at": now.strftime("%Y-%m-%d %H:%M:%S"),
            "orig_filename": posixpath.basename(entry.relpath),
            "usb_path": entry.relpath,
            "size_bytes": len(xml_data),
            "source_ip": "usb",
            "method": "USB",
            "pdf_source": pdf_source,
        }
        inserted = self.storage.enqueue(event_id, meta, "TC35@usb", "usb")
        if not inserted:
            self.storage.bump_counter("duplicate_total")
        self.log(
            f"ECG found on USB drive: {entry.relpath} patient={patient_id or '?'} "
            f"{len(xml_data):,} bytes pdf={pdf_source or 'no'} "
            f"[{'queued' if inserted else 'duplicate ignored'}]"
        )
        return event_id

    def handle_xml(self, xml_e, pdf_e, seen, pdf_index=None, consumed=None):
        if self._unchanged(xml_e, seen, ("captured",)):
            return
        data = self._read(xml_e.abspath)
        xml_ok = xml_complete(data)
        pdf, pdf_source, pdf_ok = None, None, True
        if xml_ok:
            pdf = extract_pdf_from_xml(data)
            pdf_source = "embedded" if pdf else None
        if pdf is None and pdf_e is None and xml_ok and pdf_index:
            # The TC35 names its PDF EK_<Patient ID>_[<acquired>] - not like the
            # XML - so pair them by patient ID + acquisition time, exactly.
            root = philips_root(data)
            if root is not None:
                info = philips_info(root, self.settings.patient_id_fields)
                for pid, when in acquisition_keys(root, info):
                    match = pdf_index.get((pid, re.sub(r"\D", "", when)))
                    if match is not None and path_key(match.relpath) not in consumed:
                        pdf_e = match
                        break
        if pdf is None and pdf_e is not None:
            # The report PDF saved beside the XML instead of inside it.
            sibling = self._read(pdf_e.abspath)
            pdf_ok = pdf_complete(sibling)
            if pdf_ok:
                pdf, pdf_source = sibling, "file"
            if consumed is not None:
                consumed.add(path_key(pdf_e.relpath))

        if not (xml_ok and pdf_ok):
            waited = self._waited_seconds(self._mark(xml_e, "waiting"))
            if waited < self.settings.incomplete_giveup_seconds:
                self._retry_later()
                return
            self.log(
                f"{xml_e.relpath} still incomplete after {int(waited)}s "
                f"(xml_ok={xml_ok} pdf_ok={pdf_ok}) - capturing as-is", "WARNING"
            )

        event_id = self.ingest(data, pdf, xml_e, pdf_source)
        self._mark(xml_e, "captured", event_id)
        if pdf_e is not None:
            self._mark(pdf_e, "paired", event_id)

    def handle_pdf(self, pdf_e, seen):
        """A PDF with no XML of its own. Named the TC35 way (EK_<Patient ID>_
        [<acquired>]) it is a complete export: sent as an ECG with that patient
        ID and the PDF as the report. Any other PDF cannot be matched to a
        patient: left on the drive, reported, never deleted by the cleanup."""
        parsed = parse_tc35_pdf_name(posixpath.basename(pdf_e.relpath))
        # A TC35 PDF held back by an older version ("bare_pdf") is sent now.
        if self._unchanged(pdf_e, seen, ("captured", "paired") if parsed else ("bare_pdf",)):
            return
        waited = self._waited_seconds(self._mark(pdf_e, "waiting"))
        if waited < self.settings.pdf_pair_grace_seconds:
            self._retry_later()  # its XML may still be on the way
            return
        if not parsed:
            self._mark(pdf_e, "bare_pdf")
            self.storage.bump_counter("bare_pdf_total")
            self.log(
                f"{pdf_e.relpath}: PDF without its XML and not named like a TC35 export - "
                f"not forwarded (left on the drive)", "WARNING"
            )
            return
        data = self._read(pdf_e.abspath)
        if not pdf_complete(data) and waited < self.settings.incomplete_giveup_seconds:
            self._retry_later()
            return
        event_id = self.ingest(None, data, pdf_e, "pdf-only", pdf_only=parsed)
        self._mark(pdf_e, "captured", event_id)

    def process(self, entries):
        seen = self.storage.usb_files()
        present = {path_key(e.relpath) for e in entries}
        self.storage.usb_forget(k for k in seen if k not in present)

        groups = {}
        for entry in entries:
            key = path_key(entry.relpath)
            stem, ext = posixpath.splitext(key)
            if ext in (".xml", ".pdf"):
                groups.setdefault(stem, {})[ext[1:]] = entry

        # TC35 PDFs without a same-name XML, by (patient ID, acquired) - for pairing.
        pdf_index = {}
        for group in groups.values():
            if "pdf" in group and "xml" not in group:
                parsed = parse_tc35_pdf_name(posixpath.basename(group["pdf"].relpath))
                if parsed and parsed[0]:
                    pdf_index[(parsed[0], re.sub(r"\D", "", parsed[1]))] = group["pdf"]
        consumed = set()

        # XMLs first (they claim their PDFs), then the PDFs left over; each
        # oldest first, so recordings are queued in the order they were taken.
        ordered = sorted(groups.values(), key=lambda g: min(e.mtime_ns for e in g.values()))
        for want_xml in (True, False):
            for group in ordered:
                if ("xml" in group) != want_xml:
                    continue
                try:
                    if want_xml:
                        self.handle_xml(group["xml"], group.get("pdf"), seen, pdf_index, consumed)
                    elif path_key(group["pdf"].relpath) not in consumed:
                        self.handle_pdf(group["pdf"], seen)
                except OSError as exc:
                    # Unreadable right now (TC35 mid-write, FAT inconsistent): retry.
                    name = (group.get("xml") or group.get("pdf")).relpath
                    self.log(f"Cannot read {name} yet: {exc}", "WARNING")
                    self._retry_later()

    def scan(self):
        self.dirty = False
        self.retry_at = None
        self.last_scan_at = time.monotonic()
        try:
            with self.drive.open_volume() as root:
                entries = walk(root)
                try:
                    usage = shutil.disk_usage(root)
                    self.usage_pct = round(usage.used * 100.0 / usage.total, 1) if usage.total else None
                except OSError:
                    pass
                self.files_on_drive = len(entries)
                self.process(entries)
        except Exception as exc:  # mount failure/timeout, FAT read errors
            if str(exc) != self.last_error:
                self.log(f"Cannot read the USB drive: {exc}", "ERROR")
            self.last_error = str(exc)
            self._retry_later()
            return
        if self.last_error:
            self.log("USB drive readable again")
        self.last_error = None
        self.last_scan_wall = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    # ---- cleanup --------------------------------------------------------------

    def maybe_cleanup(self):
        s = self.settings
        if not s.cleanup_enabled or self.usage_pct is None or self.usage_pct < s.cleanup_at_pct:
            return
        now = time.monotonic()
        if now - self.last_activity < s.cleanup_quiet_seconds:
            return  # the TC35 may be in use - never pull the stick then
        if self.dirty or self.retry_at is not None:
            return  # unscanned or incomplete files: finish capturing first
        if self.last_cleanup_attempt and now - self.last_cleanup_attempt < CLEANUP_BACKOFF_SECONDS:
            return
        self.last_cleanup_attempt = now
        self.cleanup()

    def cleanup(self):
        removable = self.storage.usb_removable()
        if not removable:
            self.log(
                f"USB drive {self.usage_pct}% full but no delivered recording to remove yet "
                f"(waiting for the server to acknowledge)", "WARNING"
            )
            return
        self.log(
            f"USB drive {self.usage_pct}% full - unplugging from the TC35 briefly "
            f"to remove up to {len(removable)} delivered file(s)"
        )
        deleted = []
        try:
            with self.drive.unplugged():
                with self.drive.open_volume(writable=True) as root:
                    for entry in walk(root):
                        row = removable.get(path_key(entry.relpath))
                        if row and row["size"] == entry.size and row["mtime_ns"] == entry.mtime_ns:
                            try:
                                os.remove(entry.abspath)
                                deleted.append(path_key(entry.relpath))
                            except OSError as exc:
                                self.log(f"Could not delete {entry.relpath}: {exc}", "WARNING")
            self.last_cleanup_at = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            self.log(f"Cleanup removed {len(deleted)} delivered file(s); drive plugged back in")
        except Exception as exc:
            self.log(f"Cleanup failed: {exc}", "ERROR")
        finally:
            self.storage.usb_forget(deleted)
            # Our own writes are not TC35 activity; rescan to refresh usage.
            self.last_signature = self.drive.activity_signature()
            self.dirty = True

    # ---- loop -------------------------------------------------------------

    def ensure_plugged(self):
        """Self-heal: if the gadget exists but is unbound (e.g. a replug that
        failed), plug it back so the TC35 has its stick."""
        if not isinstance(self.drive, ImageDrive):
            return
        if self.drive.gadget.state() != "unbound":
            self.unbound_since = None
            return
        now = time.monotonic()
        if self.unbound_since is None:
            self.unbound_since = now
        elif now - self.unbound_since >= REBIND_AFTER_SECONDS:
            try:
                self.drive.gadget.bind()
                self.log("USB gadget was unbound - plugged it back in", "WARNING")
            except Exception as exc:
                self.log(f"Could not rebind USB gadget: {exc}", "ERROR")
            self.unbound_since = None

    def tick(self):
        now = time.monotonic()
        signature = self.drive.activity_signature()
        if signature != self.last_signature:
            self.last_signature = signature
            self.last_activity = now
            self.dirty = True
        quiet = now - self.last_activity >= self.settings.quiet_seconds
        since_scan = now - self.last_scan_at
        retry_due = self.retry_at is not None and now >= self.retry_at
        periodic_due = since_scan >= self.settings.full_scan_seconds
        if (self.dirty or retry_due or periodic_due) and (quiet or since_scan >= 2 * self.settings.full_scan_seconds):
            self.scan()
        self.maybe_cleanup()
        self.ensure_plugged()

    def snapshot(self):
        status = self.drive.status()
        status.update({
            "used_pct": self.usage_pct,
            "files_on_drive": self.files_on_drive,
            "last_scan_at": self.last_scan_wall,
            "last_cleanup_at": self.last_cleanup_at,
            "error": self.last_error,
        })
        return status

    def maybe_report_queue_state(self):
        now = time.time()
        if now - self.last_status_report_at < 60:
            return
        stats = self.storage.get_stats()
        counters = self.storage.get_counters()
        usb = self.drive.status()
        level = "WARNING" if stats["dead"] else "INFO"
        self.log(
            f"Queue pending={stats['pending']} retry={stats['retry']} "
            f"dead={stats['dead']} oldest_age={stats['oldest_pending_age_s']}s "
            f"db={stats['db_size_mb']}MB; usb={usb['gadget_state']} "
            f"drive={self.usage_pct if self.usage_pct is not None else '?'}% "
            f"files={self.files_on_drive if self.files_on_drive is not None else '?'}; "
            f"ecg received={counters.get('received_total', 0)} "
            f"dup={counters.get('duplicate_total', 0)} pdf_only={counters.get('bare_pdf_total', 0)}",
            level,
        )
        self.last_status_report_at = now

    def run(self):
        target = getattr(self.drive, "image_path", None) or getattr(self.drive, "path", "?")
        self.log(f"Watching USB drive ({self.drive.kind}: {target}); quiet={self.settings.quiet_seconds}s")
        while self.running:
            try:
                self.tick()
            except Exception as exc:
                self.log(f"Watcher loop error: {exc}", "ERROR")
                time.sleep(5)
            time.sleep(self.settings.poll_seconds)
        self.log("Watcher stopped")

    def stop(self):
        self.running = False


def build_drive(settings):
    if settings.source_mode == "dir":
        os.makedirs(settings.watch_dir, exist_ok=True)
        return DirDrive(os.path.abspath(settings.watch_dir))
    return ImageDrive(settings.usb_image_path, settings.usb_mount_dir, Gadget(settings.gadget_name))


class GatewayManager(LoggerMixin):
    """Supervises the workers and feeds the heartbeat its ECG telemetry."""

    def __init__(self, settings):
        self.settings = settings
        self.device_name = "Manager"
        self.storage = OutboxStorage(self.settings.queue_db_path)
        self.api_client = ApiClient(self.settings)
        self.drive = build_drive(self.settings)
        self.sender = OutboxSender(self.settings, self.storage, self.api_client)
        self.watcher = UsbWatcher(self.settings, self.storage, self.drive)
        self.heartbeat = (
            HeartbeatSender(self.settings, self.storage, self.api_client, self)
            if self.settings.heartbeat_enabled
            else None
        )
        self.running = True

    def monitor_snapshot(self):
        """Heartbeat `monitor` block - same shape as the other gateways so the
        dashboard renders it. The one "device" is the TC35 on the USB port;
        it counts as connected while it has the drive enumerated."""
        usb = self.watcher.snapshot()
        connected = bool(usb.get("host_connected"))
        counters = self.storage.get_counters()
        return {
            "count": 1,
            "connected": 1 if connected else 0,
            "devices": [{
                "ip": "usb",
                "name": "TC35 (USB)",
                "alive": connected,
                "state": usb.get("gadget_state"),
                "messages": counters.get("received_total", 0),
            }],
            "listener": {"port": None, "alive": self.watcher.is_alive()},
        }

    def ecg_snapshot(self):
        """ECG delivery counters for the heartbeat's `ecg` block (same keys as
        gateway/ecg) plus the USB drive state. Totals come from the persistent
        counters table, so they survive restarts."""
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
            "bare_pdf_total": counters.get("bare_pdf_total", 0),
            "sent_total": counters.get("sent_total", 0),
            "failed_total": counters.get("failed_total", 0),
            "pending": stats["pending"] + stats["retry"],
            "last_received_at": self.watcher.last_received_at,
            "last_sent_at": self.sender.last_sent_at,
            "store_files": store_files,
            "store_mb": round(store_mb / (1024 * 1024), 1),
            "usb": self.watcher.snapshot(),
        }

    def start_watcher(self):
        old = self.watcher
        self.watcher = UsbWatcher(self.settings, self.storage, self.drive)
        self.watcher.last_received_at = old.last_received_at
        self.watcher.start()

    def run(self):
        self.log("Starting ECG USB gateway manager")
        self.log(f"Queue DB: {self.storage.db_path}")
        self.log(f"Files:    {os.path.abspath(self.settings.files_dir)}")
        self.sender.start()
        self.watcher.start()
        if self.heartbeat:
            self.heartbeat.start()

        try:
            while self.running:
                if not self.watcher.is_alive():
                    self.log("USB watcher stopped unexpectedly, restarting", "WARNING")
                    time.sleep(2)
                    self.start_watcher()
                self.watcher.maybe_report_queue_state()
                time.sleep(1)
        except KeyboardInterrupt:
            self.log("Shutdown requested by user")
        finally:
            self.running = False
            self.watcher.stop()
            self.watcher.join(timeout=10)
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
