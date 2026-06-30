# MP5SC V2

## Purpose

`mp5sc_v2` is a new gateway implementation for Philips MP5SC vital-sign capture.
It was created as a separate version so the current `gateway/mp5sc_listener` deployment can stay untouched while we harden the moving Raspberry Pi workflow.

Folder:
[`gateway/mp5sc_v2`](</C:/laragon/www/smartward4/gateway/mp5sc_v2>)

Main entrypoint:
[`gateway/mp5sc_v2/listener/main.py`](</C:/laragon/www/smartward4/gateway/mp5sc_v2/listener/main.py>)

## What v2 fixes

The old listener is mostly "read from monitor and immediately POST to API".
That is fragile when the Raspberry Pi is battery-powered and moved between rooms.

`mp5sc_v2` improves this in these ways:

1. It writes each captured BP-triggered reading into a local SQLite outbox first.
2. A separate sender worker retries queued records until SmartWard accepts them.
3. Power loss or Wi-Fi loss no longer means immediate data loss for unsent readings.
4. Duplicate capture replay is reduced by a deterministic `gateway_event_id`.
5. The legacy MP5SC parser is wrapped with a safer watchdog lifecycle to avoid duplicate watchdog threads and the old broken watchdog condition.

## New architecture

Flow:

1. MP5SC monitor sends data to the Pi listener.
2. When a BP event is captured, v2 builds a payload.
3. The payload is stored locally in SQLite.
4. The background sender POSTs queued payloads to SmartWard.
5. On API success, the event is marked `sent`.
6. On failure, the event stays in the queue and is retried with backoff.

This turns the gateway into `store-and-forward` instead of `send-and-pray`.

## Files

Core files:

- [`gateway/mp5sc_v2/listener/main.py`](</C:/laragon/www/smartward4/gateway/mp5sc_v2/listener/main.py>): manager, listener threads, sender worker
- [`gateway/mp5sc_v2/listener/src/storage.py`](</C:/laragon/www/smartward4/gateway/mp5sc_v2/listener/src/storage.py>): local SQLite outbox
- [`gateway/mp5sc_v2/listener/src/api_client.py`](</C:/laragon/www/smartward4/gateway/mp5sc_v2/listener/src/api_client.py>): SmartWard API client
- [`gateway/mp5sc_v2/listener/src/reliable_ipv_data_source.py`](</C:/laragon/www/smartward4/gateway/mp5sc_v2/listener/src/reliable_ipv_data_source.py>): wrapper around the legacy Philips parser with watchdog fixes
- [`gateway/mp5sc_v2/listener/src/config.py`](</C:/laragon/www/smartward4/gateway/mp5sc_v2/listener/src/config.py>): environment-driven settings
- [`gateway/mp5sc_v2/listener/.env.example`](</C:/laragon/www/smartward4/gateway/mp5sc_v2/listener/.env.example>): sample environment file

## Queue behavior

Database default path:

`./data/mp5sc_v2.sqlite`

Each outbox row keeps:

- `event_id`
- `patient_id`
- `measured_at`
- `payload_json`
- `status`
- `retry_count`
- `next_retry_at`
- `last_error`

Retry behavior:

- first retry starts from `RETRY_BASE_SECONDS`
- retry delay doubles over time
- delay is capped by `RETRY_MAX_SECONDS`
- events are not auto-deleted on send failure

That last point matters because patient lookup or temporary API/network issues should not silently destroy readings.

## Environment variables

Important settings:

- `API_BASE_URL`
- `API_PASSPHRASE`
- `API_USERNAME`
- `API_PASSWORD`
- `MONITOR_IP`
- `USE_API_DEVICES`
- `DEVICE_FETCH_INTERVAL`
- `POLL_INTERVAL`
- `REFRESH_INTERVAL`
- `SEND_INTERVAL`
- `SEND_BATCH_SIZE`
- `RETRY_BASE_SECONDS`
- `RETRY_MAX_SECONDS`
- `QUEUE_DB_PATH`
- `DEBUG_MODE`

Copy `.env.example` to `.env` beside `main.py` when deploying.

## Deployment modes

### 1. Fixed monitor mode

Set:

- `USE_API_DEVICES=false`
- `MONITOR_IP=<monitor-ip>`

Use this for one Pi permanently paired to one monitor.

### 2. API-managed mode

Set:

- `USE_API_DEVICES=true`

In this mode the manager asks SmartWard for active monitor devices and starts listeners dynamically.

## Operational recommendations

The code changes help, but the deployment model also needs improvement.

### Power

Recommended:

- use a Pi UPS HAT or proper DC UPS board, not a normal consumer power bank
- support graceful shutdown when battery gets low
- expose battery state to operations if possible

Avoid relying on power banks that:

- auto-sleep on low current draw
- drop output briefly when moved
- behave differently while charging and discharging

### Network

Recommended:

- keep monitor-to-Pi communication wired if possible
- use a separate uplink for SmartWard
- prefer strong roaming Wi-Fi or a dedicated SSID for gateways
- consider LTE fallback for critical workflows

Best practical topology:

`MP5SC <wired> Pi gateway <Wi-Fi/LTE> SmartWard`

### Process supervision

Recommended on Raspberry Pi:

- run with `systemd`
- set `Restart=always`
- enable start on boot
- log stdout/stderr to journald

## Important server-side note

`mp5sc_v2` now sends `gateway_event_id` and `X-Idempotency-Key`.
SmartWard currently appears to accept extra payload fields, but true duplicate protection should also be enforced server-side.

Best next backend step:

- make SmartWard store and reject duplicate `gateway_event_id` values

Without backend dedupe, retries can still create duplicate records if the API writes successfully but the Pi misses the ACK.

## Suggested rollout

1. Deploy `mp5sc_v2` on one test Raspberry Pi.
2. Run it beside the current workflow without disabling the old version yet.
3. Simulate:
   network drop
   API timeout
   Pi reboot
   battery disconnect
4. Verify queued readings replay after recovery.
5. Only then promote v2 to production use.

## Current limitation

The parser wrapper in v2 still reuses the legacy Philips protocol implementation from:
[`gateway/mp5sc_listener/listener/src/ipv_data_source.py`](</C:/laragon/www/smartward4/gateway/mp5sc_listener/listener/src/ipv_data_source.py>)

That keeps protocol behavior compatible, but it also means future hardening may still benefit from fully vendoring and refactoring that parser into v2 itself.
