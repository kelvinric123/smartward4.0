# VS4 V2

## Purpose

`vs4_v2` is the production gateway implementation for **Philips SureSigns VS4** vital-sign capture.
It follows the exact same architecture as [`gateway/mp5sc_v2`](</C:/laragon/www/smartward4/gateway/mp5sc_v2>) (store-and-forward outbox, classified retries, dead-letter, heartbeat telemetry, server-assigned identity), so both gateway types are operated, deployed, and monitored the same way.

The discovery-stage tool in [`gateway/vs4`](</C:/laragon/www/smartward4/gateway/vs4>) stays untouched, the same way `mp5sc_listener` stayed untouched when `mp5sc_v2` was introduced.

Folder:
[`gateway/vs4_v2`](</C:/laragon/www/smartward4/gateway/vs4_v2>)

Main entrypoint:
[`gateway/vs4_v2/listener/main.py`](</C:/laragon/www/smartward4/gateway/vs4_v2/listener/main.py>)

## The one deliberate difference from MP5SC

The nature of the listener is different:

| | MP5SC v2 | VS4 v2 |
|---|---|---|
| Protocol | Philips proprietary over **UDP** | **HL7 v2 ORU^R01 over MLLP/TCP** |
| Direction | Gateway **polls** the monitor continuously | Monitor **pushes** to the gateway |
| Monitoring style | Continuous (BP event triggers a capture) | Spot-check (each message is one complete reading) |
| Listener threads | One polling thread per monitor IP | One passive TCP server for any number of VS4 units |
| Patient-safety logic | Vitals cache staleness + identity settle window | Not needed — patient ID and vitals arrive together in one message |
| Acknowledgement | n/a | HL7 ACK per message (AA) |

Everything else — the outbox, the sender, the retry/dead-letter policy, the heartbeat, the API endpoints, the deploy scripts — matches `mp5sc_v2`.

## Flow

1. The VS4 takes a spot-check reading; the nurse confirms/enters the patient ID on the device.
2. The VS4 connects to the gateway (`LISTEN_PORT`, default **4000**) and sends an MLLP-framed `ORU^R01`.
3. The gateway parses PID (patient) + OBX (vitals), normalizes units (°F→°C, lb→kg, in→cm), and validates ranges.
4. The reading is written to the local SQLite outbox **first**, then the HL7 ACK is returned to the VS4.
5. The background sender POSTs queued payloads to SmartWard (`POST /api/v1/vital-signs`) with `X-Idempotency-Key`.
6. On API success the event is marked `sent`; on failure it is retried with exponential backoff or dead-lettered per the failure class.

Power loss or Wi-Fi loss never loses an ACKed reading: it is on disk before the ACK.

## Files

- [`listener/main.py`](</C:/laragon/www/smartward4/gateway/vs4_v2/listener/main.py>): manager, HL7 listener service, sender worker
- [`listener/src/hl7_mllp.py`](</C:/laragon/www/smartward4/gateway/vs4_v2/listener/src/hl7_mllp.py>): MLLP transport + ACK (hardened from the discovery tool)
- [`listener/src/hl7_oru.py`](</C:/laragon/www/smartward4/gateway/vs4_v2/listener/src/hl7_oru.py>): ORU^R01 → normalized reading (LOINC/device codes + text fallback)
- [`listener/src/storage.py`](</C:/laragon/www/smartward4/gateway/vs4_v2/listener/src/storage.py>): local SQLite outbox (identical to mp5sc_v2)
- [`listener/src/api_client.py`](</C:/laragon/www/smartward4/gateway/vs4_v2/listener/src/api_client.py>): SmartWard API client (identical to mp5sc_v2)
- [`listener/src/heartbeat.py`](</C:/laragon/www/smartward4/gateway/vs4_v2/listener/src/heartbeat.py>): health telemetry (same payload shape as mp5sc_v2)
- [`listener/src/config.py`](</C:/laragon/www/smartward4/gateway/vs4_v2/listener/src/config.py>): environment-driven settings
- [`listener/.env.example`](</C:/laragon/www/smartward4/gateway/vs4_v2/listener/.env.example>): sample environment file
- [`test_sender.py`](</C:/laragon/www/smartward4/gateway/vs4_v2/test_sender.py>): local end-to-end test (ORU, duplicate, no-PID, ADT)
- [`deploy_pi/`](</C:/laragon/www/smartward4/gateway/vs4_v2/deploy_pi>): guided Raspberry Pi setup + systemd units + netwatch

## Queue behavior

Identical to mp5sc_v2. Row lifecycle:

    pending -> sent  (2xx / idempotent duplicate) -> purged by retention
    pending -> retry (transient / bounded soft failures) -> pending ...
    pending -> dead  (permanent failures / exceeded caps) -> purged by retention

Failure classes from the API client:

- `transient` — network error, 5xx, 429 → retry with backoff, dead-letter after `MAX_AGE_TRANSIENT_HOURS`
- `soft` — 404 patient not found (may not be admitted yet) → retry, dead-letter after `MAX_AGE_404_MINUTES`
- `hard` — 400/422 invalid payload → dead-letter immediately
- `auth` — 401/403 → dead-letter after `MAX_ATTEMPTS_AUTH`

Retention (FIFO) and a hard size cap (`MAX_DB_ROWS` / `MAX_DB_MB`) keep the SQLite file bounded on the SD card; unsent rows are only evicted as a last resort and raise an ALARM log.

## Duplicate protection

`gateway_event_id` is **content-addressed**: SHA-1 of `gateway_id | patient | measured_at | all vital values`. A VS4 retransmitting the same reading (lost ACK, reboot, new HL7 control ID) hashes to the same event and is deduplicated twice:

1. locally by the outbox `UNIQUE(event_id)` constraint (logged as `duplicate ignored`), and
2. server-side by the `X-Idempotency-Key` / `gateway_event_id` check in `receiveVitalSigns`.

## HL7 interpretation

- Message type: only `ORU^R01` produces readings; ADT/ORM/etc. are ACKed and ignored.
- Patient: PID-3 (fallback PID-2/PID-4); name from PID-5. **A reading without a patient ID is never guessed onto a patient** — it is ACKed, logged as a WARNING, and counted in the heartbeat (`skipped_no_patient`).
- Time: OBR-7, else OBX-14, else MSH-7, else gateway time.
- Vitals: OBX-3 matched on known codes (LOINC + common device codes) first, then on the label text (SYS/DIA/HR/SpO2/TEMP/RR/WEIGHT/HEIGHT). MAP/mean pressures are ignored (no API field).
- Units normalized to what the API expects: °C, kg, cm.
- Range validation mirrors the server (and `SPO2_RANGE_*` / `PR_RANGE_*` are configurable).

Unknown message shapes can be captured for analysis with `SAVE_RAW=true` (writes each raw message to `RAW_DIR`) — the v2 replacement for the discovery dumper.

## Communication with the server

Same endpoints and auth as mp5sc_v2 (`X-Passphrase` header + username/password in the body, credentials attached only at send time — never stored in the queue):

- `POST /api/v1/vital-signs` — queued readings, with `X-Idempotency-Key`
- `POST /api/v1/gateway/register` — setup-time, server assigns `gateway_id` keyed to the hardware serial
- `POST /api/v1/gateway/heartbeat` — every 30 s; extended stats every 30 min
- `GET  /api/v1/wards` — setup-time ward selection
- `POST /api/v1/device/login` — connectivity/credential check
- `GET  /api/v1/monitor-devices` + `POST /monitor-devices/{id}/status` — optional (`USE_API_DEVICES=true`): maps sender IPs to configured device names and pushes per-device status

The heartbeat payload has the **same shape** as mp5sc_v2 (`monitor`, `queue`, `power`, `clock_synced`, `disk_free_pct`, `service`, `netwatch`, periodic `stats`), so the existing gateway dashboard renders VS4 gateways without changes. The `monitor.devices` list holds the VS4 units that have sent readings recently (`PEER_ONLINE_SECONDS`), and `monitor.listener` adds port/message counters.

## Local test

```powershell
cd gateway\vs4_v2
copy listener\.env.example listener\.env    # edit as needed
python listener\main.py                     # terminal 1
python test_sender.py 127.0.0.1 4000        # terminal 2
```

The test sender proves the full chain: ORU parsed + queued, retransmitted duplicate rejected, missing-patient-ID reading skipped, ADT ignored — each with a proper ACK.

## Deployment

- **Raspberry Pi (production):** `deploy_pi/setup.sh` — the same guided 14-step installer as mp5sc_v2 (server-assigned name, static monitor-LAN IP with `never-default`, systemd, netwatch, offline-capable). See [`deploy_pi/README.md`](</C:/laragon/www/smartward4/gateway/vs4_v2/deploy_pi/README.md>).
- **Docker:** `Dockerfile` (expose `LISTEN_PORT` 4000/tcp).

On the VS4 side, configure the HL7/network export destination to the gateway's **static LAN IP** and `LISTEN_PORT`. The static IP matters more here than for the MP5SC: it is the address programmed into the monitor, so it must never hop.

## Rollout

1. Deploy on one test Pi beside the discovery listener (different port if both run).
2. Take test readings on a VS4; verify they arrive, dedupe, and appear in SmartWard.
3. Simulate: network drop, API timeout, Pi reboot, power cut — verify queued readings replay after recovery.
4. Only then promote to production use.
