# ECG Gateway

## Purpose

`gateway/ecg` is the store-and-forward gateway for the **Philips TC35 ECG machine**. The TC35 has no Wi-Fi module, so a Raspberry Pi sits next to it on the ward LAN: the TC35 uploads each recording to the Pi over HTTP (exactly as it would to the dockerized listener), and the Pi forwards it to SmartWard over the Wi-Fi uplink — keeping every ECG locally until the server has **acknowledged receipt**.

It follows the same architecture as the v2 vital-sign gateways ([`mp5sc_v2`](</C:/laragon/www/smartward4/gateway/mp5sc_v2>), [`vs4_v2`](</C:/laragon/www/smartward4/gateway/vs4_v2>), [`cm100_v2`](</C:/laragon/www/smartward4/gateway/cm100_v2>)): SQLite outbox, classified retries, dead-letter, heartbeat telemetry, server-assigned identity, same guided Pi installer. It registers with **`gateway_type=ecg`**, so it appears on `/vital-sign-integration` → *Qmed Gateways* under its ward with an **ECG badge** and ECG-specific stats.

## Flow

1. The TC35 POSTs a recording (XML with the report PDF embedded in `<StudyData>`, or a bare PDF) to the Pi — port **3050**, Basic auth — identical to the docker listener (`ecg/http_server.py`).
2. The gateway detects the file type (UTF-8/UTF-16 XML, PDF), extracts the embedded PDF, parses `<PatientID>` (the MRN/RN), and writes the files to `FILES_DIR`.
3. The recording is enqueued in the SQLite outbox **before** the TC35 receives its IDEP-style `OK` — an acknowledged ECG can no longer be lost.
4. The background sender POSTs each recording to **`POST /api/v1/ecg`** (base64 XML + PDF, `X-Idempotency-Key` = content hash) with the same passphrase + per-request credentials as the vital-sign gateways.
5. The server stores the files into the shared ECG store (`config('services.ecg.store_path')`, default `ecg/store`) — so the recording appears in the existing **ECG admin page** and **patient ECG viewer** exactly as if the docker listener had received it — and records the receipt in the `ecg_uploads` table.
6. On the server's ack the outbox row is marked `sent`; on failure it retries with backoff (`transient`), gives up after a few attempts (`auth`), or dead-letters immediately (`hard` 400/413/422). Every outcome is counted.

## Delivery tracking ("is it already sent and received?")

- **Gateway side**: the outbox row lifecycle (`pending → retry → sent/dead`) is the per-recording delivery status; `deploy_pi/status.sh` and the 60-second queue log show it at the cart, and persistent lifetime counters (`received / sent / failed / duplicate / rejected`) survive restarts.
- **Server side**: each received recording is a row in `ecg_uploads` (unique `gateway_event_id`, gateway, patient code, filenames, size, source IP) — the auditable receipt.
- **Duplicates**: the event ID is a **content hash**, so a TC35 retransmit (lost `OK`, operator retry) dedupes locally in the outbox *and* server-side via the idempotency check. A replayed send is acknowledged with `duplicate: true`, never stored twice.

## Heartbeat (ECG version)

Same 30-second heartbeat + extended stats as the other gateways (`monitor`, `queue`, `power`, `clock_synced`, `disk_free_pct`, `service`, `netwatch`, Wi-Fi/uplink info), so the dashboard health/traffic-light logic works unchanged, plus:

- `gateway_type: "ecg"` — types the row on the dashboard (heartbeat also types manually-added gateways);
- `ecg`: `{received_total, sent_total, failed_total, duplicate_total, rejected_total, pending, last_received_at, last_sent_at, store_files, store_mb}`;
- `monitor.devices` lists the ECG machines that have uploaded recently (IP, upload count).

The dashboard tile shows **ECG Sent** (with a red failed marker) instead of the Monitor column, and the detail view shows *ECG received / sent / failed* and *Last ECG sent*.

## Files

- [`listener/main.py`](</C:/laragon/www/smartward4/gateway/ecg/listener/main.py>): manager, TC35 HTTP listener service, sender + file janitor
- [`listener/src/ecg_http.py`](</C:/laragon/www/smartward4/gateway/ecg/listener/src/ecg_http.py>): TC35-compatible HTTP server (Basic auth, IDEP `OK`), file-type detection, PDF/PatientID extraction — ported from `ecg/http_server.py`
- [`listener/src/storage.py`](</C:/laragon/www/smartward4/gateway/ecg/listener/src/storage.py>): SQLite outbox + persistent counters + file-reference tracking
- [`listener/src/api_client.py`](</C:/laragon/www/smartward4/gateway/ecg/listener/src/api_client.py>): SmartWard client (`/ecg`, `/gateway/heartbeat`)
- [`listener/src/heartbeat.py`](</C:/laragon/www/smartward4/gateway/ecg/listener/src/heartbeat.py>): health telemetry with the `ecg` block
- [`test_sender.py`](</C:/laragon/www/smartward4/gateway/ecg/test_sender.py>): simulated TC35 upload (UTF-16 XML + embedded PDF, retransmit, bad auth)
- [`deploy_pi/`](</C:/laragon/www/smartward4/gateway/ecg/deploy_pi>): guided Pi installer (registers with `gateway_type=ecg`), systemd units, netwatch

## Server-side pieces (added with this gateway)

- `qmed_gateways.gateway_type` (`vital_sign` | `ecg`); ECG gateways get the `ECG-` name prefix (`ECG_GATEWAY_PREFIX`).
- `POST /api/v1/ecg` in `VitalSignApiV1Controller@receiveEcg` — idempotent receive, writes viewer-compatible filenames into the shared store.
- `ecg_uploads` table + `EcgUpload` model — the receipt log.
- `config('services.ecg.store_path')` (`ECG_STORE_PATH` env) — one store path used by both the viewers and the receive endpoint. **Docker note:** Laravel currently mounts `ecg_data` read-only (`:ro`); to accept gateway uploads in docker, mount it writable.
- Dashboard: type badges + ECG stats on the *Qmed Gateways* tab.

## Local test

```powershell
cd gateway\ecg
copy listener\.env.example listener\.env    # point API_BASE_URL at your server
python listener\main.py                     # terminal 1
python test_sender.py 127.0.0.1 3050        # terminal 2
```

The test uploads a TC35-style UTF-16 XML with an embedded PDF, retransmits it (deduped), and tries a wrong password (401). With the server reachable, the recording lands in `ecg/store` and the ECG admin page shows it.

## Deployment

- **Raspberry Pi:** `deploy_pi/setup.sh` — same guided flow as the other gateways; step 7 registers with `gateway_type=ecg`, step 14 verifies port 3050 is accepting. Point the TC35 at `http://<Pi static IP>:3050/`, method POST, Basic auth.
- **Docker:** `Dockerfile` (expose 3050) — a drop-in replacement for the plain listener container that adds the forwarding + heartbeat.

An ECG gateway can share a Pi with a vital-sign gateway (different ports, service names, and data dirs).
