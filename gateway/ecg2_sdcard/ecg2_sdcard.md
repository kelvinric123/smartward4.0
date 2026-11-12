# ECG USB Gateway (TC35 → USB stick → SmartWard)

## Purpose

`gateway/ecg2_sdcard` gets ECGs from a **Philips TC35** that has no network connection at all. A **Raspberry Pi Zero 2W** plugs into the TC35's USB port and **pretends to be a USB flash drive**. When the operator saves/exports an ECG to "the USB stick", the Pi picks the file up and forwards it to SmartWard over its Wi-Fi.

It is the sibling of [`gateway/ecg`](</C:/laragon/www/smartward4/gateway/ecg/ecg_gateway.md>) (where the TC35 uploads over HTTP to a Pi on the ward LAN). Everything after the file arrives is the same: SQLite outbox, classified retries, dead-letter, heartbeat, server-assigned identity, and the same **`POST /api/v1/ecg`** call. **No server changes are needed**: the gateway registers with `gateway_type=ecg`, so it shows on `/vital-sign-integration` → *Qmed Gateways* with the ECG badge, and the recordings land in the existing ECG admin page and patient ECG viewer.

## Flow

1. At boot, `ecg-usb-gadget.service` presents `/var/lib/ecg-usb/usb_drive.img` (a 4 GB FAT32 disk image, label `SMARTWARD`) on the Pi's OTG port through the kernel's USB mass-storage gadget. The TC35 sees an ordinary removable USB stick.
2. The TC35 writes its export (TC35 XML: `<PatientID>` + report PDF base64 in `<StudyData>`) to the stick. Each block lands in the image file, so the image's mtime changes.
3. The watcher sees the activity and waits until the drive has been **quiet for 8 s** (`QUIET_SECONDS`).
4. Then it mounts the image **read-only** on a fresh loop device, lists the files, and reads any new `.xml` (plus a `.pdf` with the same name, if the export put the PDF beside the XML).
5. A recording is captured only when the XML parses as a **complete document** (and the side PDF ends in `%%EOF`). Half-written files are retried every 30 s, and captured as-is after 10 min (`INCOMPLETE_GIVEUP_SECONDS`).
6. **Format conversion.** On USB, the TC35 saves **Philips native XML** (`<restingecgdata>`, namespace `http://www3.medical.philips.com`; patient under `patient/generalpatientdata`; **no PDF**). Over the network, it posts a different **"Study" layout** (`<Study>` with `<PatientID>` under the root and the PDF in `<StudyData>`), and that is the only layout SmartWard's ECG pages read. So the Philips file is re-wrapped:
   - `PatientID` comes from the first non-empty field in `PATIENT_ID_FIELDS` (default `patientid,MRN,uniquepatientid`);
   - name, age, sex and acquisition date/time are copied over;
   - a PDF saved beside the XML goes into `StudyData`;
   - the untouched original is kept in `<RawData>` (base64).

   `CAPTURE_VERSION` (in `main.py`) is part of the event hash. When it changes, files captured by older code are captured again and re-sent.
7. The XML and PDF are copied to `FILES_DIR` and queued in the outbox, keyed by a **content hash**. The same recording saved twice, or re-read after a restart, is deduplicated here and again by the server's idempotency check.
8. The sender POSTs `/api/v1/ecg` (base64 XML + PDF, `X-Idempotency-Key`) exactly like `gateway/ecg`, retrying with backoff until the server acknowledges.

## Rules that keep the TC35's stick safe

- **Never written while plugged in.** While the gadget is bound, the TC35 owns the filesystem; the gateway only does read-only mounts. A fresh mount for every scan means no stale FAT cache.
- **Cleanup only of acknowledged ECGs.** When the drive is more than 80% full (`CLEANUP_AT_PCT`) and has been idle for 15 min (`CLEANUP_QUIET_SECONDS`), the gateway "unplugs" the stick (unbinds the gadget for ~2 s). It then deletes only the files whose recording the **server has acknowledged** and whose size/mtime still match what was captured, and plugs the stick back in. At most once every 30 min. With a 4 GB drive this is rare.
- **The TC35's PDF export** is named `EK_<Patient ID>_[YYYYMMDDHHMMSS]______.pdf`. If an XML of the same recording is on the drive, the PDF goes into that XML's `StudyData`. They're paired only when the patient ID and the acquisition time (to the second) **both** match exactly, so a PDF can never land on another patient. Otherwise the PDF is sent on its own, as a Study built from its file name (`StudyFormat` `PDF`). Either way SmartWard shows it as the report.
  - A PDF waits `PDF_PAIR_GRACE_SECONDS` for its XML first.
  - Any other PDF can't be matched to a patient. It's left on the drive, counted (`bare_pdf_total`), and listed by `status.sh`.
- Files the TC35 itself deletes are simply forgotten. Folders a laptop drops on sticks (`System Volume Information`, `.Trashes`, `._*`) are ignored.

## Heartbeat

Same 30 s heartbeat as `gateway/ecg`, so the dashboard works unchanged:

- `gateway_type: "ecg"`;
- `ecg`: `{received_total, sent_total, failed_total, duplicate_total, bare_pdf_total, pending, last_received_at, last_sent_at, store_files, store_mb, usb}`, where `usb` = `{gadget_state, host_connected, used_pct, files_on_drive, last_scan_at, last_cleanup_at, error}`;
- `monitor.devices` has one entry, `TC35 (USB)`, which is *alive* while the TC35 has the drive enumerated (USB state `configured`).

## Files

- [`listener/main.py`](</C:/laragon/www/smartward4/gateway/ecg2_sdcard/listener/main.py>): manager, `UsbWatcher` (quiet-window scan, capture, cleanup), sender + file janitor (from `gateway/ecg`)
- [`listener/src/usb_drive.py`](</C:/laragon/www/smartward4/gateway/ecg2_sdcard/listener/src/usb_drive.py>): drive image (partition offset, read-only/read-write loop mounts), configfs gadget bind/unbind, folder drive for tests
- [`listener/src/ecg_files.py`](</C:/laragon/www/smartward4/gateway/ecg2_sdcard/listener/src/ecg_files.py>): TC35 XML/PDF detection, PDF + PatientID extraction (ported from `gateway/ecg`), completeness checks
- [`listener/src/storage.py`](</C:/laragon/www/smartward4/gateway/ecg2_sdcard/listener/src/storage.py>): `gateway/ecg` outbox, plus the `usb_files` table (what is on the drive and what was captured)
- `listener/src/api_client.py`: same calls as `gateway/ecg`, but standard-library only (no pip, so no internet needed) and it never follows redirects
- `listener/src/heartbeat.py`: from `gateway/ecg`
- [`autosetup/`](</C:/laragon/www/smartward4/gateway/ecg2_sdcard/autosetup/autosetup.sh>) + [`setup.conf.example`](</C:/laragon/www/smartward4/gateway/ecg2_sdcard/setup.conf.example>): no-SSH first boot. The Pi sets itself up from the SD card and reports to `SETUP_RESULT.txt` on the card.
- [`make_sample_ecg.py`](</C:/laragon/www/smartward4/gateway/ecg2_sdcard/make_sample_ecg.py>): writes a TC35-style XML; use it to test from a laptop without the TC35
- [`tests/test_usb_gateway.py`](</C:/laragon/www/smartward4/gateway/ecg2_sdcard/tests/test_usb_gateway.py>): watcher/cleanup/sender/format tests
- [`deploy_pi/`](</C:/laragon/www/smartward4/gateway/ecg2_sdcard/deploy_pi/README.md>): guided installer, gadget + drive scripts, systemd units, status/preflight

## Local test (no Pi)

```powershell
cd gateway\ecg2_sdcard
python -m unittest discover -s tests -v

cd listener
copy .env.example .env                        # SOURCE_MODE=dir; point API_BASE_URL at your server
python main.py                                # terminal 1
python ..\make_sample_ecg.py data\usb 55555   # terminal 2 - the ECG appears in SmartWard
```

## Deployment

See [`deploy_pi/README.md`](</C:/laragon/www/smartward4/gateway/ecg2_sdcard/deploy_pi/README.md>).
