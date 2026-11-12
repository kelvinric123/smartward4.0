"""
Tests for the ECG USB gateway (stdlib unittest - runs on a laptop or the Pi):

    python -m unittest discover -s tests -v        (from gateway/ecg2_sdcard)

The watcher runs in 'dir' mode (a plain folder standing in for the mounted
drive), the sender posts to a stub /api/v1/ecg, and the partition-table
parser is fed hand-built sectors. Mounting the real image and the USB gadget
are exercised on the Pi itself (deploy_pi/setup.sh + preflight.sh).
"""

import base64
import dataclasses
import json
import os
import shutil
import sys
import tempfile
import threading
import time
import unittest
import xml.etree.ElementTree as ET
from datetime import datetime
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "listener"))
sys.path.insert(0, os.path.join(ROOT, "listener", "src"))
sys.path.insert(0, ROOT)

import main  # noqa: E402
from api_client import ApiClient  # noqa: E402
from config import load_settings  # noqa: E402
from ecg_files import pdf_complete, xml_complete  # noqa: E402
from make_sample_ecg import SAMPLE_PDF, philips_xml, tc35_xml  # noqa: E402
from storage import OutboxStorage  # noqa: E402
from usb_drive import DirDrive, DriveError, find_fs_offset  # noqa: E402


def make_settings(tmp, **overrides):
    os.environ.update({
        "SOURCE_MODE": "dir",
        "WATCH_DIR": os.path.join(tmp, "usb"),
        "FILES_DIR": os.path.join(tmp, "files"),
        "QUEUE_DB_PATH": os.path.join(tmp, "queue.sqlite"),
        "GATEWAY_ID": "ECG-TEST-01",
        "HEARTBEAT_ENABLED": "false",
    })
    settings = load_settings()
    return dataclasses.replace(settings, **overrides)


def write(path, data):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, "wb") as fh:
        fh.write(data)


class WatcherTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.mkdtemp()
        self.usb = os.path.join(self.tmp, "usb")
        os.makedirs(self.usb)

    def tearDown(self):
        shutil.rmtree(self.tmp, ignore_errors=True)

    def watcher(self, **overrides):
        settings = make_settings(self.tmp, **overrides)
        storage = OutboxStorage(settings.queue_db_path)
        return main.UsbWatcher(settings, storage, DirDrive(self.usb)), storage

    def rows(self, storage):
        return [dict(r, meta=json.loads(r["payload_json"])) for r in storage.fetch_due_events(100)]

    def test_captures_tc35_xml_with_embedded_pdf(self):
        w, storage = self.watcher()
        xml = tc35_xml("55555")
        write(os.path.join(self.usb, "ECG", "TC35_0001.XML"), xml)
        w.scan()

        rows = self.rows(storage)
        self.assertEqual(len(rows), 1)
        meta = rows[0]["meta"]
        self.assertEqual(meta["patient_id"], "55555")
        self.assertEqual(meta["usb_path"], "ECG/TC35_0001.XML")
        self.assertEqual(meta["orig_filename"], "TC35_0001.XML")
        self.assertEqual(meta["pdf_source"], "embedded")
        with open(meta["xml_path"], "rb") as fh:
            self.assertEqual(fh.read(), xml)
        with open(meta["pdf_path"], "rb") as fh:
            self.assertEqual(fh.read(), SAMPLE_PDF)
        self.assertEqual(storage.usb_files()["ecg/tc35_0001.xml"]["state"], "captured")

        w.scan()  # unchanged file: nothing new
        self.assertEqual(len(self.rows(storage)), 1)
        self.assertEqual(storage.get_counters()["received_total"], 1)

    def test_same_recording_saved_twice_is_deduplicated(self):
        w, storage = self.watcher()
        xml = tc35_xml("12345")
        write(os.path.join(self.usb, "a.xml"), xml)
        write(os.path.join(self.usb, "copy_of_a.xml"), xml)
        w.scan()
        self.assertEqual(len(self.rows(storage)), 1)
        self.assertEqual(storage.get_counters()["duplicate_total"], 1)
        files = os.listdir(os.path.join(self.tmp, "files"))
        self.assertEqual(len(files), 2, files)  # one xml + one pdf, no duplicate copies

    def test_half_written_xml_waits_until_complete(self):
        w, storage = self.watcher()
        xml = tc35_xml("777")
        path = os.path.join(self.usb, "partial.xml")
        write(path, xml[: len(xml) // 2] + b"\x00" * 64)  # FAT cluster tail
        w.scan()
        self.assertEqual(self.rows(storage), [])
        self.assertEqual(storage.usb_files()["partial.xml"]["state"], "waiting")
        self.assertIsNotNone(w.retry_at)

        write(path, xml)
        w.scan()
        self.assertEqual(len(self.rows(storage)), 1)
        self.assertEqual(storage.usb_files()["partial.xml"]["state"], "captured")

    def test_never_completing_xml_is_captured_after_giveup(self):
        w, storage = self.watcher(incomplete_giveup_seconds=0)
        write(os.path.join(self.usb, "broken.xml"), tc35_xml("888")[:-40])
        w.scan()
        rows = self.rows(storage)
        self.assertEqual(len(rows), 1)
        self.assertIsNone(rows[0]["meta"]["pdf_path"])

    def test_pdf_saved_beside_xml_is_paired(self):
        w, storage = self.watcher()
        write(os.path.join(self.usb, "R1.xml"), tc35_xml("999", embed_pdf=False))
        write(os.path.join(self.usb, "R1.pdf"), SAMPLE_PDF)
        w.scan()
        rows = self.rows(storage)
        self.assertEqual(len(rows), 1)
        self.assertEqual(rows[0]["meta"]["pdf_source"], "file")
        self.assertEqual(storage.usb_files()["r1.pdf"]["state"], "paired")

    def test_pdf_without_xml_is_reported_not_queued(self):
        w, storage = self.watcher(pdf_pair_grace_seconds=3600)
        write(os.path.join(self.usb, "lonely.pdf"), SAMPLE_PDF)
        w.scan()
        self.assertEqual(storage.usb_files()["lonely.pdf"]["state"], "waiting")

        w2, storage2 = self.watcher(pdf_pair_grace_seconds=0)
        w2.scan()
        self.assertEqual(self.rows(storage2), [])
        self.assertEqual(storage2.usb_files()["lonely.pdf"]["state"], "bare_pdf")
        self.assertEqual(storage2.get_counters()["bare_pdf_total"], 1)

    def test_cleanup_removes_only_acknowledged_recordings(self):
        w, storage = self.watcher(pdf_pair_grace_seconds=0)
        write(os.path.join(self.usb, "sent.xml"), tc35_xml("1"))
        write(os.path.join(self.usb, "sent.pdf"), SAMPLE_PDF)
        write(os.path.join(self.usb, "unsent.xml"), tc35_xml("2"))
        write(os.path.join(self.usb, "lonely.pdf"), SAMPLE_PDF + b"\n")
        w.scan()
        sent_event = storage.usb_files()["sent.xml"]["event_id"]
        for row in storage.fetch_due_events(10):
            if row["event_id"] == sent_event:
                storage.mark_sent(row["id"])

        w.cleanup()
        self.assertEqual(sorted(os.listdir(self.usb)), ["lonely.pdf", "unsent.xml"])
        self.assertNotIn("sent.xml", storage.usb_files())

    def test_cleanup_skips_a_file_rewritten_since_capture(self):
        w, storage = self.watcher()
        path = os.path.join(self.usb, "r.xml")
        write(path, tc35_xml("1"))
        w.scan()
        for row in storage.fetch_due_events(10):
            storage.mark_sent(row["id"])
        write(path, tc35_xml("1-new-recording"))  # TC35 reused the name
        w.cleanup()
        self.assertTrue(os.path.exists(path))

    def test_maybe_cleanup_waits_for_idle_and_threshold(self):
        w, storage = self.watcher(cleanup_at_pct=80, cleanup_quiet_seconds=3600)
        w.dirty, w.retry_at = False, None
        w.usage_pct = 95
        calls = []
        w.cleanup = lambda: calls.append(1)
        w.maybe_cleanup()
        self.assertEqual(calls, [], "drive used recently -> must not unplug")
        w.last_activity -= 4000
        w.maybe_cleanup()
        self.assertEqual(calls, [1])
        w.maybe_cleanup()
        self.assertEqual(calls, [1], "backoff between cleanups")
        w.usage_pct, w.last_cleanup_attempt = 50, 0.0
        w.maybe_cleanup()
        self.assertEqual(calls, [1], "below threshold")

    def sent_study(self, storage):
        """The XML the gateway will POST, parsed the way SmartWard reads it:
        <PatientID> directly under the root (EcgArchive: $xml->PatientID)."""
        meta = self.rows(storage)[-1]["meta"]
        with open(meta["xml_path"], "rb") as fh:
            root = ET.fromstring(fh.read())
        return meta, root

    def test_philips_usb_export_is_sent_in_study_layout(self):
        w, storage = self.watcher()
        original = philips_xml("A1234567", mrn="M999")
        write(os.path.join(self.usb, "ECG", "0001.xml"), original)
        w.scan()

        meta, root = self.sent_study(storage)
        self.assertEqual(root.tag, "Study")
        self.assertEqual(root.findtext("PatientID"), "A1234567")
        self.assertEqual(meta["patient_id"], "A1234567")
        self.assertEqual(root.findtext("PatientLastName"), "TEST")
        self.assertEqual(root.findtext("RefPatientGender"), "Male")
        self.assertRegex(root.findtext("RefDateAcquired"), r"^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$")
        self.assertEqual(base64.b64decode(root.findtext("RawData")), original)
        self.assertEqual(root.findtext("StudyData"), "")  # the USB export carries no PDF

    def test_philips_pdf_beside_the_xml_goes_into_studydata(self):
        w, storage = self.watcher()
        write(os.path.join(self.usb, "R7.xml"), philips_xml("P7"))
        write(os.path.join(self.usb, "R7.pdf"), SAMPLE_PDF)
        w.scan()
        meta, root = self.sent_study(storage)
        self.assertEqual(base64.b64decode(root.findtext("StudyData")), SAMPLE_PDF)
        with open(meta["pdf_path"], "rb") as fh:
            self.assertEqual(fh.read(), SAMPLE_PDF)

    def test_patient_id_falls_back_to_mrn_field(self):
        w, storage = self.watcher()
        write(os.path.join(self.usb, "a.xml"), philips_xml("", mrn="M555"))
        w.scan()
        self.assertEqual(self.sent_study(storage)[1].findtext("PatientID"), "M555")

        w2, storage2 = self.watcher(patient_id_fields=("MRN", "patientid"))
        write(os.path.join(self.usb, "b.xml"), philips_xml("P1", mrn="M1"))
        w2.scan()
        self.assertEqual(self.sent_study(storage2)[1].findtext("PatientID"), "M1")

    def test_files_captured_by_an_older_version_are_sent_again(self):
        w, storage = self.watcher()
        path = os.path.join(self.usb, "old.xml")
        write(path, philips_xml("P2"))
        st = os.stat(path)
        # As the previous release left it: captured (and sent) unconverted.
        storage.usb_mark("old.xml", "old.xml", st.st_size, st.st_mtime_ns,
                         "captured", "old-event-id", capture_version=1)
        w.scan()
        rows = self.rows(storage)
        self.assertEqual(len(rows), 1)  # captured again, queued for sending
        self.assertEqual(rows[0]["meta"]["patient_id"], "P2")
        self.assertEqual(storage.usb_files()["old.xml"]["capture_version"], main.CAPTURE_VERSION)
        w.scan()
        self.assertEqual(len(self.rows(storage)), 1)  # and only once

    TC35_PDF = "EK_{pid}_[{ts}]______.pdf"

    def test_tc35_pdf_only_export_is_sent_with_patient_and_report(self):
        w, storage = self.watcher(pdf_pair_grace_seconds=0)
        write(os.path.join(self.usb, self.TC35_PDF.format(pid="MRN000014", ts="20261013173826")), SAMPLE_PDF)
        w.scan()
        meta, root = self.sent_study(storage)
        self.assertEqual(root.findtext("PatientID"), "MRN000014")
        self.assertEqual(root.findtext("RefDateAcquired"), "2026-10-13 17:38:26")
        self.assertEqual(root.findtext("StudyFormat"), "PDF")
        self.assertEqual(base64.b64decode(root.findtext("StudyData")), SAMPLE_PDF)
        self.assertEqual(meta["patient_id"], "MRN000014")
        with open(meta["pdf_path"], "rb") as fh:
            self.assertEqual(fh.read(), SAMPLE_PDF)

    def test_tc35_pdf_waits_for_its_xml_before_going_alone(self):
        w, storage = self.watcher(pdf_pair_grace_seconds=3600)
        write(os.path.join(self.usb, self.TC35_PDF.format(pid="P1", ts="20261013180000")), SAMPLE_PDF)
        w.scan()
        self.assertEqual(self.rows(storage), [])
        self.assertIsNotNone(w.retry_at)

    def test_xml_and_pdf_of_one_recording_are_paired_by_patient_and_time(self):
        w, storage = self.watcher(pdf_pair_grace_seconds=0)
        acquired = datetime(2026, 10, 13, 18, 10, 5)
        original = philips_xml("MRN000014", acquired=acquired)
        write(os.path.join(self.usb, "ECG_0042.xml"), original)
        pdf_name = self.TC35_PDF.format(pid="MRN000014", ts="20261013181005")
        write(os.path.join(self.usb, pdf_name), SAMPLE_PDF)
        w.scan()

        rows = self.rows(storage)
        self.assertEqual(len(rows), 1)  # one ECG, not an XML and a PDF
        meta, root = self.sent_study(storage)
        self.assertEqual(base64.b64decode(root.findtext("RawData")), original)
        self.assertEqual(base64.b64decode(root.findtext("StudyData")), SAMPLE_PDF)
        self.assertEqual(storage.usb_files()[pdf_name.lower()]["state"], "paired")

    def test_pdf_of_another_patient_or_time_is_never_paired(self):
        w, storage = self.watcher(pdf_pair_grace_seconds=0)
        acquired = datetime(2026, 10, 13, 18, 10, 5)
        write(os.path.join(self.usb, "a.xml"), philips_xml("P9", acquired=acquired))
        write(os.path.join(self.usb, self.TC35_PDF.format(pid="P8", ts="20261013181005")), SAMPLE_PDF)
        write(os.path.join(self.usb, self.TC35_PDF.format(pid="P9", ts="20261013181006")), SAMPLE_PDF + b"\n")
        w.scan()
        by_patient = {}
        for row in self.rows(storage):
            with open(row["meta"]["xml_path"], "rb") as fh:
                study = ET.fromstring(fh.read())
            by_patient.setdefault(study.findtext("PatientID"), []).append(study.findtext("StudyFormat"))
        self.assertEqual(sorted(by_patient["P9"]), ["PDF", "PhilipsXML"])  # 1 s apart: separate
        self.assertEqual(by_patient["P8"], ["PDF"])

    def test_tc35_pdf_held_back_by_an_older_version_is_sent(self):
        w, storage = self.watcher(pdf_pair_grace_seconds=0)
        name = self.TC35_PDF.format(pid="MRN000014", ts="20261013173826")
        path = os.path.join(self.usb, name)
        write(path, SAMPLE_PDF)
        st = os.stat(path)
        storage.usb_mark(name.lower(), name, st.st_size, st.st_mtime_ns, "bare_pdf", capture_version=2)
        w.scan()
        self.assertEqual(len(self.rows(storage)), 1)
        self.assertEqual(storage.usb_files()[name.lower()]["state"], "captured")

    def test_tick_scans_only_after_writes_go_quiet(self):
        w, storage = self.watcher(quiet_seconds=1)
        w.tick()  # first look at the drive counts as activity
        write(os.path.join(self.usb, "x.xml"), tc35_xml("42"))
        w.tick()
        self.assertEqual(self.rows(storage), [])
        time.sleep(1.1)
        w.tick()
        self.assertEqual(len(self.rows(storage)), 1)


class StubServer(ThreadingHTTPServer):
    daemon_threads = True


class SenderTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.mkdtemp()
        self.requests = []
        test = self

        class Handler(BaseHTTPRequestHandler):
            def do_POST(self):
                body = json.loads(self.rfile.read(int(self.headers["Content-Length"])))
                test.requests.append((self.path, dict(self.headers), body))
                out = b'{"success":true}'
                self.send_response(201)
                self.send_header("Content-Length", str(len(out)))
                self.end_headers()
                self.wfile.write(out)

            def log_message(self, *args):
                pass

        self.server = StubServer(("127.0.0.1", 0), Handler)
        threading.Thread(target=self.server.serve_forever, daemon=True).start()

    def tearDown(self):
        self.server.shutdown()
        self.server.server_close()
        shutil.rmtree(self.tmp, ignore_errors=True)

    def test_captured_ecg_is_posted_like_gateway_ecg(self):
        port = self.server.server_address[1]
        settings = make_settings(self.tmp, api_base_url=f"http://127.0.0.1:{port}/api/v1",
                                 api_passphrase="qmedno1", api_username="api@qmed.asia",
                                 api_password="secret", send_interval=0)
        storage = OutboxStorage(settings.queue_db_path)
        usb = os.path.join(self.tmp, "usb")
        xml = tc35_xml("55555")
        write(os.path.join(usb, "TC35.xml"), xml)
        main.UsbWatcher(settings, storage, DirDrive(usb)).scan()

        sender = main.OutboxSender(settings, storage, ApiClient(settings))
        sender.start()
        deadline = time.time() + 10
        while time.time() < deadline and storage.get_stats()["sent"] == 0:
            time.sleep(0.1)
        sender.stop()

        self.assertEqual(storage.get_stats()["sent"], 1)
        path, headers, body = self.requests[0]
        self.assertEqual(path, "/api/v1/ecg")
        self.assertEqual(headers["X-Passphrase"], "qmedno1")
        self.assertEqual(headers["X-Idempotency-Key"], body["gateway_event_id"])
        self.assertEqual(body["gateway_id"], "ECG-TEST-01")
        self.assertEqual(body["patient_code"], "55555")
        self.assertEqual(body["filename"], "TC35.xml")
        self.assertEqual(base64.b64decode(body["xml_b64"]), xml)
        self.assertEqual(base64.b64decode(body["pdf_b64"]), SAMPLE_PDF)
        self.assertEqual(body["username"], "api@qmed.asia")


class RedirectTest(unittest.TestCase):
    """PHKL's port 80 answers IP access with a 301 to the HTTPS domain; urllib
    would follow it as a GET and drop the ECG, so it must surface as an error."""

    def test_redirect_is_reported_not_followed(self):
        hits = []

        class Handler(BaseHTTPRequestHandler):
            def do_POST(self):
                hits.append(self.path)
                self.rfile.read(int(self.headers["Content-Length"]))
                self.send_response(301)
                self.send_header("Location", "https://phklsmartward.example/api/v1/ecg")
                self.send_header("Content-Length", "0")
                self.end_headers()

            do_GET = do_POST

            def log_message(self, *args):
                pass

        server = StubServer(("127.0.0.1", 0), Handler)
        threading.Thread(target=server.serve_forever, daemon=True).start()
        try:
            tmp = tempfile.mkdtemp()
            settings = make_settings(tmp, api_base_url=f"http://127.0.0.1:{server.server_address[1]}/api/v1")
            ok, failure_class, status, detail = ApiClient(settings).send_ecg({"x": 1}, "evt")
        finally:
            server.shutdown()
            server.server_close()
            shutil.rmtree(tmp, ignore_errors=True)
        self.assertFalse(ok)
        self.assertEqual((failure_class, status), ("transient", 301))
        self.assertIn("check API_BASE_URL", detail)
        self.assertEqual(hits, ["/api/v1/ecg"])  # not re-sent anywhere


class DriveFormatTest(unittest.TestCase):
    def test_mbr_partition_offset(self):
        mbr = bytearray(512)
        entry = bytearray(16)
        entry[4] = 0x0C
        entry[8:12] = (2048).to_bytes(4, "little")
        entry[12:16] = (100000).to_bytes(4, "little")
        mbr[446:462] = entry
        mbr[510:512] = b"\x55\xaa"
        self.assertEqual(find_fs_offset(bytes(mbr)), 2048 * 512)

    def test_superfloppy_fat32(self):
        boot = bytearray(512)
        boot[0:3] = b"\xeb\x58\x90"
        boot[82:90] = b"FAT32   "
        boot[510:512] = b"\x55\xaa"
        self.assertEqual(find_fs_offset(bytes(boot)), 0)

    def test_unformatted_and_gpt_are_rejected(self):
        with self.assertRaises(DriveError):
            find_fs_offset(bytes(512))
        gpt = bytearray(512)
        gpt[446 + 4] = 0xEE
        gpt[446 + 8] = 1
        gpt[446 + 12] = 1
        gpt[510:512] = b"\x55\xaa"
        with self.assertRaises(DriveError):
            find_fs_offset(bytes(gpt))

    def test_completeness_checks(self):
        xml = tc35_xml("1")
        self.assertTrue(xml_complete(xml))
        self.assertTrue(xml_complete(xml + b"\x00" * 100))
        self.assertFalse(xml_complete(xml[:-30]))
        self.assertFalse(xml_complete(b""))
        self.assertTrue(pdf_complete(SAMPLE_PDF))
        self.assertFalse(pdf_complete(SAMPLE_PDF[:-10]))


if __name__ == "__main__":
    unittest.main()
