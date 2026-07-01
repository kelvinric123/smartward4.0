# MP5SC Gateway — Solution Design

> Companion to [`mp5sc.md`](./mp5sc.md) (scenario + pain points). This doc turns
> those pain points into a concrete, buildable design. It maps every fix to a
> file, a schema, or an endpoint contract. Where it shows code it's illustrative,
> not final.

## 0. Locked decisions (these shape everything below)

| Decision | Choice | Consequence |
|---|---|---|
| Pi runtime | **Bare Python + `systemd`** | No Docker in the field; setup script installs a unit with `Restart=always` + journald. |
| Per-cart hardware | **Software-only (power bank stays)** | No UPS/RTC. Power + clock resilience must be pure software (read-only rootfs, undervoltage telemetry, monitor-sourced time, monotonic scheduling). |
| Server changes | **Allowed (Pi + Laravel)** | We can add real server-side idempotency, a heartbeat receiver, and dead-letter visibility. |
| Scope | **All four workstreams** | Queue hardening, patient-safety, heartbeat/warnings, auto-setup + preflight. |

---

## 1. Target architecture

```
 MP5SC monitor ──UDP:24105──► Raspberry Pi (bare Python + systemd) ──HTTPS/HTTP──► SmartWard (Laravel)
  192.168.0.5     (poll)         │                                    (Wi-Fi)         smartward:88
                                 │  ┌─────────────── main.py (manager) ───────────────┐
                                 │  │ Listener thread(s)   Sender thread   Heartbeat thread │
                                 │  └──────────┬─────────────────┬────────────────┬────────┘
                                 │             ▼                 ▼                ▼
                                 │        capture BP        drain outbox     POST health
                                 │             │                 │
                                 │             ▼                 ▼
                                 │      ┌──────────────────────────────┐
                                 │      │  SQLite outbox (/data, WAL)   │
                                 │      │  pending→sent→purge / dead    │
                                 │      └──────────────────────────────┘
                                 │  Read-only rootfs · writable /data · fake-hwclock · systemd-timesyncd
```

**Component changes at a glance**

| Component | Change |
|---|---|
| [`listener/src/storage.py`](./listener/src/storage.py) | New status machine (`+dead`), failure classification columns, retention/FIFO purge, hard size cap, durability pragmas, credentials no longer stored. |
| [`listener/src/api_client.py`](./listener/src/api_client.py) | Catch + classify network errors, attach creds at send time, honor idempotent-replay response. |
| [`listener/main.py`](./listener/main.py) | Sender uses classification + monotonic backoff + per-row isolation; listener does fresh-context-before-capture + cache reset on patient change + staleness gate; new `Heartbeat` thread. |
| `listener/src/heartbeat.py` *(new)* | Builds + posts health telemetry. |
| Server: `VitalSignApiV1Controller` | Idempotency on `gateway_event_id`; new `heartbeat` action. |
| Server: migration | `gateway_event_id` unique on `vital_signs`; extend `qmed_gateways` for heartbeat fields. |
| `deploy_pi/` *(new)* | `setup.sh` (guided), `preflight.sh`, `status.sh`, `mp5sc.service`, network + overlay config. |

---

## 2. Workstream 1 — Queue hardening (the headline ask)

### 2.1 Status state machine

```
enqueue ─► pending ─┬─(2xx / duplicate)──────────► sent ──(retention)──► purged
                    ├─(transient: net/5xx/429)───► retry ──(due)──► pending…
                    ├─(soft-perm: 404)───────────► retry (bounded) ──► dead
                    └─(hard-perm: 422/400/corrupt)──────────────────► dead
retry ─► (same transitions as pending on next attempt)
dead  ─────────────────────────────────────────► purged (longer retention)
```

### 2.2 Failure classification (the core of "good send logic")

The current code treats **every** non-2xx as an infinite retry. Replace with:

| Send outcome | Class | Action |
|---|---|---|
| `200/201` | success | → `sent` |
| `200` + `duplicate:true` (idempotent replay) | success | → `sent` |
| Connection error / timeout / DNS fail / `5xx` / `429` | **transient** | → `retry`, exp backoff, until `MAX_AGE_TRANSIENT` (default 7d) → `dead` |
| `404` patient not found | **soft-permanent** | → `retry` bounded by `MAX_AGE_404` (default 2h) — covers "not admitted yet" — then → `dead` |
| `422` / `400` validation | **hard-permanent** | → `dead` immediately + alert |
| `401/403` auth | **config-error** | → `retry` up to `MAX_ATTEMPTS_AUTH` (default 3) then → `dead` + **loud** alert (creds broken) |
| local `json`/schema error on the row | **corrupt** | → `dead` immediately, **continue the batch** |

This is what stops the queue from silently growing forever on a mistyped ID, and
stops a 3 s hammer loop when the server is down.

### 2.3 Retention & FIFO (the explicit "erase for FIFO" ask)

A dedicated **janitor** pass (runs inside the sender loop, e.g. once/minute):

1. **Time-based purge**
   - `sent` older than `RETENTION_SENT_HOURS` (default **72h**) → delete.
   - `dead` older than `RETENTION_DEAD_DAYS` (default **14d**) → delete (kept longer — it's lost data worth inspecting).
2. **Hard size cap** (`MAX_DB_ROWS` default 100k, `MAX_DB_MB` default 200):
   when exceeded, FIFO-evict in this priority order and **log + heartbeat each eviction**:
   1. oldest `sent`
   2. oldest `dead`
   3. **last resort only:** oldest `pending`/`retry` — this is real data loss, so it raises an alarm, never silent.
3. **Space reclaim:** DB created with `PRAGMA auto_vacuum=INCREMENTAL`; janitor runs
   `PRAGMA incremental_vacuum` (cheap, incremental) instead of full `VACUUM`
   (heavy full-file rewrite = risky under power loss).

> Design rule: **`pending`/`retry` is sacred.** Nothing auto-deletes unsent
> clinical data without an explicit, alarmed last-resort eviction.

### 2.4 Schema (additive; `storage._init_db` self-migrates via `pragma table_info`)

```sql
CREATE TABLE IF NOT EXISTS outbox_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  event_id      TEXT NOT NULL UNIQUE,     -- gateway_event_id (dedupe key)
  device_name   TEXT,
  monitor_ip    TEXT,
  patient_id    TEXT,
  measured_at   TEXT,                      -- monitor-sourced clinical time
  payload_json  TEXT NOT NULL,            -- clinical fields ONLY (no creds)
  status        TEXT NOT NULL DEFAULT 'pending',   -- pending|retry|sent|dead
  failure_class TEXT,                      -- transient|soft|hard|auth|corrupt
  http_status   INTEGER,
  retry_count   INTEGER NOT NULL DEFAULT 0,
  next_retry_at TEXT NOT NULL,
  first_attempt_at TEXT,
  last_error    TEXT,
  dead_reason   TEXT,
  created_at    TEXT NOT NULL,
  updated_at    TEXT NOT NULL,
  sent_at       TEXT
);
CREATE INDEX IF NOT EXISTS idx_outbox_due  ON outbox_events(status, next_retry_at, id);
CREATE INDEX IF NOT EXISTS idx_outbox_gc   ON outbox_events(status, updated_at);
```

### 2.5 Durability & correctness fixes

- **Pragmas** (low write volume, so favor safety):
  `journal_mode=WAL`, `synchronous=FULL`, `busy_timeout=30000`, `auto_vacuum=INCREMENTAL`.
- **Backoff on monotonic time**, not wall clock — retries stay correct even when
  the Pi's clock is wrong after a power-death reboot (see §6).
- **Per-row isolation:** wrap each row's send/parse in try/except so one poison
  row (corrupt JSON) is dead-lettered and the batch continues (fixes E4).
- **Network errors classified, not thrown:** `api_client` catches
  `RequestException` and returns `(False, class, status)` so the sender always
  advances `next_retry_at` (fixes E3 — no more fixed-interval hammering).
- **Credentials out of the DB (E5):** `build_payload` stores clinical fields only;
  `api_client.send_vital_signs` merges `username`/`password` at POST time from
  config. A lost SD card no longer leaks creds in every row.

---

## 3. Workstream 2 — Idempotency (kills duplicates on lost ACK)

Client already computes `gateway_event_id`; today the **server ignores it**.

### 3.1 Server (Laravel)

- **Migration:** add nullable `gateway_event_id` + **unique index** to `vital_signs`
  (or a small `vital_sign_idempotency` ledger if we don't want to widen the table).
- **Controller** ([`VitalSignApiV1Controller::receiveVitalSigns`](../../app/Http/Controllers/VitalSignApiV1Controller.php#L46)):
  1. Read the key from `X-Idempotency-Key` header (fallback body `gateway_event_id`).
  2. If a row with that key exists → return **`200`** with the original result +
     `"duplicate": true` (do **not** insert again).
  3. Else insert with the key. Rely on the unique index + catch the duplicate-key
     race to stay correct under concurrent retries.

### 3.2 Client

- Treat `200 + duplicate:true` exactly like a fresh success → `mark_sent`.
- Net effect: **write-succeeded-but-ACK-lost** (the classic flaky-Wi-Fi case) is
  now safe to retry — the replay is absorbed server-side.

---

## 4. Workstream 3 — Patient-safety (highest clinical severity)

Three targeted changes in [`main.py`](./listener/main.py), all cheap:

1. **Fresh context immediately before capture.** When a new BP event is detected,
   force `dev.refresh_patient_data()` + re-read `get_patient_data()` *right then*,
   before building the payload — don't rely on the periodic `REFRESH_INTERVAL`
   window (closes D1).
2. **Reset caches on patient change.** When `patient_id` changes, clear
   `last_valid_heart_rate/oxygen/temp/resp_rate` so the previous patient's
   continuous vitals can never ride along on the new patient's BP snapshot.
3. **Staleness gate on continuous vitals.** Timestamp each cached value; only
   include HR/SpO2/Temp/RR in a BP payload if captured within
   `VITAL_STALENESS_SECONDS` (default 60s) of the BP event. Old values are dropped
   rather than sent as if simultaneous (mitigates C3).
4. **Uncertain-identity drop.** If `patient_id` is empty *or* just changed within
   `IDENTITY_SETTLE_SECONDS`, skip this capture with a warning (and it will be
   re-captured cleanly next cuff) rather than risk a wrong-patient write.

> Optional server cross-check: if the payload carries `patient_name`, the server
> can compare it to the looked-up patient and reject/flag a mismatch. Cheap
> defense-in-depth; flagged as a follow-up.

---

## 4c. SpO2 / PR range capture (enhancement)

The monitor emits SpO2 and pulse rate continuously; collapsing them to one sample
loses information. During each capture window the Pi now tracks the **min and max**
of SpO2 and PR and reports them as a range, while keeping the single representative
value for backward compatibility.

- **Pi ([`main.py`](./listener/main.py)):** `update_live_values` accumulates
  min/max within configurable plausibility bounds (`SPO2_RANGE_MIN/MAX`,
  `PR_RANGE_MIN/MAX`) — **extreme/artifact values and the `8388607` sentinel are
  excluded**. The window resets after each BP capture and on patient change. The
  payload adds `spo2_min/max` and `pulse_rate_min/max`.
- **Server:** migration adds `spo2_min/max`, `pulse_rate_min/max` to `vital_signs`;
  the controller validates + stores them; [`VitalSign`](../../app/Models/VitalSign.php)
  exposes `spo2_display` / `pulse_rate_display` accessors that render **"95-96"**
  when a range exists (or **"95"** when min==max / only a single value).
- **Dashboard:** nurse dashboard tiles, patient-details, and the vital-signs
  tables use the display accessors, so they read e.g. `SpO2 95-96`, `PR 80-85`.
  Charts and EWS scoring keep using the single `spo2`/`pulse_rate` value.

---

## 5. Workstream 4 — Heartbeat / warnings

### 5.1 Pi side — new `Heartbeat` thread

Posts every `HEARTBEAT_INTERVAL` (default 30s), **and immediately** on notable
events (undervoltage seen, disk low, new dead-letter, auth failure):

```jsonc
POST /api/v1/gateway/heartbeat        // headers: X-Passphrase
{
  "username": "...", "password": "...",
  "gateway_id": "WARD3-CART-07",       // provisioned identity (see §7)
  "cpu_serial": "100000000abcdef",     // fallback fingerprint
  "app_version": "v2.1.0",
  "uptime_s": 48213,
  "ip": "10.20.0.51",
  "monitor": { "ip": "192.168.0.5", "connected": true, "last_bp_at": "…", "last_poll_at": "…" },
  "queue":   { "pending": 3, "retry": 1, "dead": 0, "oldest_pending_age_s": 12, "last_sent_at": "…" },
  "power":   { "undervoltage_now": false, "undervoltage_seen": true, "throttled_hex": "0x50000", "cpu_temp_c": 61.2 },
  "clock_synced": true,
  "disk_free_pct": 74
}
```

`power` comes from `vcgencmd get_throttled` — this is our **software-only battery
substitute**: bit 0 = under-voltage now, bit 16 = under-voltage has occurred. A
wobbling power bank shows up here before it kills the Pi.

### 5.2 Server side

- Extend `qmed_gateways` (already has `mac_address`, `last_ping_ip`, `updatePing`)
  with `gateway_id`, `last_heartbeat_at`, `last_heartbeat_json`, and derived health.
- **Upsert by `gateway_id`** (solves the "which cart?" identification gap G3).
- **Alerting rules** (dashboard flag + optional nurse-station/admin notification):
  - `last_heartbeat_at` older than `3 × HEARTBEAT_INTERVAL` → **cart offline/dead**.
  - `dead > 0` → readings being lost (mistyped ID / broken creds).
  - `undervoltage_seen` → **power unstable, cart may die**.
  - `oldest_pending_age_s` over threshold → server unreachable / backlog building.
  - `disk_free_pct` low, or `clock_synced=false` → degraded.

This is the "send a warning to the server" capability — proactive, not "we noticed
vitals stopped hours ago."

---

## 6. Cross-cutting: power & clock, software-only

Because we're staying on power banks with no RTC:

- **SD-card corruption resistance (A2):**
  - **Read-only root filesystem** via overlayfs (raspi-config "Overlay File
    System"); the OS can't be corrupted by a power cut.
  - **Dedicated writable `/data` partition** (ext4) holds only: the SQLite DB,
    `.env`, `fake-hwclock` data, minimal state. Small blast radius.
  - SQLite `synchronous=FULL` gives committed-transaction durability on that
    partition; volume is ~1 write per BP, so the cost is negligible.
  - Logs → journald **volatile** + shipped in heartbeat, so we're not hammering
    the SD with log writes.
- **Undervoltage telemetry (A1/A3):** report `vcgencmd get_throttled` in the
  heartbeat (§5.1) — the closest we get to battery visibility without a UPS.
- **Clock without RTC (F1):**
  - `measured_at` is taken from the **monitor's clock** (a set medical device),
    not the Pi — already true in v2; we make it explicit and validate it (reject
    the 1990 sentinel / year < 2020 → flag `time_source=pi` in payload).
  - **`fake-hwclock`** stops the clock resetting to 1970 across reboots.
  - **`systemd-timesyncd`** re-syncs when Wi-Fi returns; `clock_synced` reported.
  - **Retry scheduling uses `time.monotonic()`**, immune to wall-clock jumps.

---

## 7. Workstream 4b — Guided setup + network verification

New `deploy_pi/` folder. One interactive command on a fresh Pi: `sudo ./setup.sh`.

It runs **step by step with a verification checkpoint after each step**
(Retry / Continue / Abort on failure). The 12 steps:

1. Check environment (root, Pi detection, base tools).
2. Gather config interactively (server URL, creds, monitor IP, interfaces).
3. Install deps (`python3-venv`, `sqlite3`, `curl`, `fake-hwclock`,
   `systemd-timesyncd`, `network-manager`, `libraspberrypi-bin`) → verify each.
4. Create service user + `/var/lib/mp5sc` → verify.
5. Install code + venv (vendors the legacy parser) → verify deps import.
6. **Verify server** (DNS + TCP + `device/login` creds) — gates naming.
7. **Server-assigned name** (defeats SD-clone collisions H2): send the Pi's
   **hardware serial** (Pi CPU serial, `machine-id` fallback) to
   `POST /gateway/register`; Laravel returns the existing name for a known board
   or **assigns the next sequential** one (e.g. `GW-0007`). This makes Laravel the
   master of naming; identity is no longer baked into the image.
8. Write `/var/lib/mp5sc/mp5sc.env` with the assigned `GATEWAY_ID`
   (single source of truth, `chmod 600` — **no creds in the image**, H4) → verify.
9. **Monitor LAN, never-default** (B1 / IP-hopping fix): pin the monitor NIC
   static and set `ipv4.never-default yes` + `ipv4.ignore-auto-dns yes` via
   NetworkManager (the `nmtui` "never use for default route" toggle), dhcpcd
   `nogateway` fallback → verify `never-default=yes`.
10. **Verify routing:** the default route must not be via the monitor NIC, and
    `ip route get <server>` must not egress the monitor NIC. Fails the step if the
    enterprise Wi-Fi (`10.x`) isn't the path to the server.
11. Enable time sync + `fake-hwclock`.
12. Install `mp5sc.service` (`Restart=always`, journald) + start → verify active.

Companion scripts: **`preflight.sh`** (re-runnable PASS/FAIL self-test + heartbeat
registration) and **`status.sh`** (on-Pi snapshot: service, queue counts,
dead-letters, `vcgencmd` power, clock, disk).

**Runtime clone guard:** if a cloned Pi ever runs with a copied `gateway_id`
without re-running setup, the server sees the hardware-serial mismatch on the next
heartbeat, marks that cart **critical (identity conflict)**, and returns
`action: reregister`.

---

## 8. New / changed configuration

Additions to `.env` (on `/data`):

```ini
# Identity
GATEWAY_ID=WARD3-CART-07

# Queue lifecycle
RETENTION_SENT_HOURS=72
RETENTION_DEAD_DAYS=14
MAX_DB_ROWS=100000
MAX_DB_MB=200
MAX_AGE_TRANSIENT_HOURS=168      # 7d
MAX_AGE_404_MINUTES=120          # 2h
MAX_ATTEMPTS_AUTH=3

# Patient safety
VITAL_STALENESS_SECONDS=60
IDENTITY_SETTLE_SECONDS=5

# Heartbeat
HEARTBEAT_INTERVAL=30
```

---

## 9. Rollout plan (safe sequencing, parallel-run per the v2 doc)

| Phase | Change | Side | Risk | Why this order |
|---|---|---|---|---|
| **1** | Queue hardening (§2) + patient-safety cache reset/staleness (§4.1–4.3) | Pi only | Low | Headline ask + highest clinical severity; no server dependency; parallel-runnable on one test Pi. |
| **2** | Idempotency (§3) | Server + Pi | Low-med | Removes duplicate risk before we lean harder on retries. |
| **3** | Heartbeat + server alerting (§5) | Pi + Server | Low | Turns on ops visibility; needed before wide field rollout. |
| **4** | Auto-setup + preflight + overlay/network (§6–7) | Pi/OS | Med | Makes new-Pi deployment repeatable and safe; do once the software is stable. |

Validation for each phase reuses the v2 doc's simulation matrix: **network drop,
API timeout, Pi reboot, battery disconnect** → confirm no loss, no duplicates,
correct patient, and a server-side warning when the cart goes quiet.

---

## 9a. Implementation status (built)

All four phases are implemented in this repo. Two Laravel migrations are written
but **not yet applied** — the local MySQL was down; run `php artisan migrate`
when it is up.

| Area | Files | State |
|---|---|---|
| Queue lifecycle + retention/FIFO + durability | [`storage.py`](./listener/src/storage.py) | done, unit-tested |
| Failure classification + backoff + isolation | [`api_client.py`](./listener/src/api_client.py), [`main.py`](./listener/main.py) | done, unit-tested |
| Creds out of the queue | [`main.py`](./listener/main.py), [`api_client.py`](./listener/src/api_client.py) | done |
| Patient-safety (fresh context, cache reset, staleness, settle-drop) | [`main.py`](./listener/main.py) | done |
| SpO2/PR range capture + display | [`main.py`](./listener/main.py), migration `..._add_vital_ranges_to_vital_signs_table`, [`VitalSign`](../../app/Models/VitalSign.php), controller, dashboard views | code done, unit-tested; **migration pending** |
| Server idempotency | migration `..._add_gateway_event_id_to_vital_signs_table`, [`VitalSignApiV1Controller`](../../app/Http/Controllers/VitalSignApiV1Controller.php), [`VitalSign`](../../app/Models/VitalSign.php) | code done; **migration pending** |
| Heartbeat (Pi) | [`heartbeat.py`](./listener/src/heartbeat.py), [`main.py`](./listener/main.py) | done, smoke-tested |
| Heartbeat (server) + alerts | migration `..._add_heartbeat_to_qmed_gateways_table`, [`QmedGateway`](../../app/Models/QmedGateway.php), controller `heartbeat`, route | code done; **migration pending** |
| Server-assigned naming + clone guard | controller `registerGateway`, unique `cpu_serial`, `services.gateway_prefix`, heartbeat conflict detection | code done; **migration pending** |
| Guided setup (`setup.sh`) + preflight + never-default check | [`deploy_pi/`](./deploy_pi) | done, `bash -n` clean |

Not yet built (deliberately deferred): the operations **dashboard/alert UI** that
surfaces `QmedGateway::effective_health` (data + rules exist server-side), and the
optional server-side patient_name cross-check.

## 10. Open items to confirm during build

1. `vital_signs` table width — add `gateway_event_id` column vs. a separate
   idempotency ledger table? (Prefer column + unique index.)
2. Monitor↔Pi link: is it wired? If wireless, C1/B4 stay risky regardless of
   software — may still want a dongle for a wired monitor link.
3. Heartbeat transport when Wi-Fi is down — buffer last-N heartbeats and send on
   reconnect, or fire-and-forget? (Lean: fire-and-forget; the queue is the
   durable path, heartbeat is best-effort telemetry.)
4. Alert delivery target: dashboard flag only, or also push to a nurse
   station/admin channel?
