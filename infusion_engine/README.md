# Infusion Engine

Standalone service that records infusion pump data flowing as HL7 and serves
it over a REST API. It runs separately from SmartWard — SmartWard will later
just point at the API.

```
Pump gateway (NUC) --HL7/MLLP:6000--> INFUSION ENGINE --REST:6001--> SmartWard (later)
                                       |- stores every raw HL7 message
                                       |- parses rate/volumes/drug/status/alarms
                                       |- PostgreSQL (own database, no shared DB;
                                       |  embedded SQLite fallback for local dev)
                                       '- management page at http://host:6001/
```

## Management page

Open **http://host:6001/** in a browser (served by the engine itself, no
Laravel needed):

- **Pumps** — live cards per pump: Device ID, status, rate, drug, ward,
  volumes, battery, alarms, and whether the pump is registered in SmartWard.
- **Message Log** — every HL7 message received; click a row to see the raw segments
- **Demo Data** — simulated pumps that send real PCD-01/PCD-04 HL7 into the
  engine's own MLLP port (same pipeline as real pumps). Like real B.Braun
  pumps, demo pumps carry **no patient identity** ("Unknown Patient") — bind
  the pump's Device ID to a patient in the SmartWard ward dashboard.
  "＋ Add pump" can pick a pump registered in SmartWard (via
  `INFUSION_SMARTWARD_URL`) so the Device IDs match, or create a custom pump.
  Per-pump actions: start/stop infusion, complete, activate a **chosen alarm**
  (10 presets or custom text), clear alarm.
  Disable with `INFUSION_DEMO_ENABLED=false` in production.

Pump -> patient binding is deliberately **not** the engine's job: the pump HL7
identifies only the device (Device ID). SmartWard's ward dashboard binds the
Device ID to a patient, and SmartWard's integration page auto-registers every
pump the engine has seen into its "Registered Pump Users" list.
- **API Docs** — built-in reference for every data endpoint: how the engine
  works, query params, response field explanations, live "Try" buttons that run
  the request against this engine, and a Laravel integration snippet for
  SmartWard.

Understands B.Braun SpacePlus / IHE PCD messages:

| Message | Profile | Content |
|---|---|---|
| ORU^R01 | PCD-01 | periodic infusion status (rate, volumes, drug, pump state) |
| ORU^R42 | PCD-10 | infusion events (delivery start/stop) |
| ORU^R40 | PCD-04 | alarms (active/muted/cleared, priority, alert text) |
| ORU^R01 | PCD-15 | device reports (battery, power, wifi) |

Every message is ACKed and stored raw even if parsing fails, so nothing is lost.

## Layout

- `engine/` — the service (Python; only pip dependency is `pg8000` for Postgres)
  - `main.py` entry point, `mllp_server.py` HL7 in, `hl7.py` parser,
    `db.py` storage (PostgreSQL or SQLite), `api.py` REST out, `config.py` env config
- `simulator.py` — B.Braun HL7 scenario simulator (see below)
- `docker_infusion/` — Dockerfile, docker-compose (engine + PostgreSQL),
  `build_and_push.bat` (image `kelvinric/infusion`)
- `tests/` — parser unit tests + MLLP sample sender (real B.Braun samples)
- `listener.py` — old step-1 diagnostic listener (superseded by the engine)

## Run locally

```bash
python -m engine.main
```

With no `INFUSION_DB_HOST` set it uses embedded SQLite — data goes to
`data/infusion.db`, logs to `logs/`. Nothing to install.

## Run with Docker (engine + PostgreSQL)

```bash
cd docker_infusion
docker compose up -d --build
```

See `docker_infusion/README.md` for details.

## Configuration (env vars)

| Variable | Default | Description |
|---|---|---|
| `INFUSION_MLLP_HOST` | `0.0.0.0` | MLLP bind address |
| `INFUSION_MLLP_PORT` | `6000` | MLLP listen port (HL7 in) |
| `INFUSION_API_HOST` | `0.0.0.0` | API bind address |
| `INFUSION_API_PORT` | `6001` | REST API port (data out) |
| `INFUSION_API_KEY` | *(empty)* | If set, API requires `X-API-Key` header |
| `INFUSION_DB_HOST` | *(empty)* | PostgreSQL host — empty = use SQLite fallback |
| `INFUSION_DB_PORT` | `5432` | PostgreSQL port |
| `INFUSION_DB_NAME` | `infusion` | PostgreSQL database |
| `INFUSION_DB_USER` | `infusion` | PostgreSQL user |
| `INFUSION_DB_PASSWORD` | `infusion_secret` | PostgreSQL password |
| `INFUSION_DB_PATH` | `data/infusion.db` | SQLite file (fallback mode only) |
| `INFUSION_LOG_DIR` | `logs/` | Log directory |
| `INFUSION_RETENTION_DAYS` | `0` | Purge stored data older than N days (0 = keep) |
| `INFUSION_DEMO_ENABLED` | `true` | Demo pump simulator endpoints + UI tab |
| `INFUSION_SMARTWARD_URL` | *(empty)* | SmartWard base URL — demo tab lists its registered pumps |
| `LOG_LEVEL` | `INFO` | DEBUG / INFO / WARNING |

## REST API (for SmartWard)

All GET, JSON responses. If `INFUSION_API_KEY` is set, send it as `X-API-Key`.

| Endpoint | Returns |
|---|---|
| `/` | management page (HTML) |
| `/health` | liveness + row counts (always open, no key needed) |
| `/api/patients/{mrn}` | patient infusion info by MRN (pumps, readings, alarms) |
| `/api/pumps` | latest state of every pump (rate, drug, status, ward, alarm) |
| `/api/pumps/{device_id}` | one pump + recent readings + recent alarms |
| `/api/pumps/{device_id}/readings` | reading history for one pump |
| `/api/pumps/{device_id}/alarms` | alarm history for one pump |
| `/api/readings` | readings across all pumps |
| `/api/alarms` | alarms across all pumps (`?state=active`) |
| `/api/messages` | raw HL7 message log (`?raw=1` to include full HL7) |
| `/api/messages/{id}` | one message including the raw HL7 |

Demo control (POST, used by the management page): `/api/demo/start`,
`/api/demo/stop`, `/api/demo/status` (GET),
`/api/demo/pumps/{label}/{start|stop|complete|alarm|clear_alarm}`.

Common query params: `limit` (default 100, max 1000), `offset`,
`since` (ISO timestamp), `device_id`, `type` (R01/R40/R42 on `/api/messages`).

`device_id` is the pump's EUI-64 serial from MSH-3 (e.g. `0012211839000001`).

## Simulator (`simulator.py`)

One CLI that plays realistic B.Braun ward traffic into the engine's MLLP port —
PCD-01 status, PCD-10 delivery events, PCD-04 alarms and PCD-15 device reports —
so the whole receive → parse → store → API path can be exercised without pumps.
Segment layout, MDC codes and units come from the real captures in
`tests/samples/`, and `tests/test_simulator.py` asserts every scenario parses
back correctly and that no observation code is invented.

```bash
python simulator.py --list            # the catalogue, with what each one exercises
python simulator.py normal            # one scenario
python simulator.py occlusion battery # several, back to back
python simulator.py all               # everything except the soak test
python simulator.py normal --dry-run  # print the HL7, send nothing
```

| Scenario | What it puts through the engine |
|---|---|
| `idle` | pump powered on, nothing loaded — baseline registration |
| `normal` | LVP infusion start → periodic status → near-end → complete |
| `syringe` | Perfusor with concentration, dose delivered/remaining, weight, syringe level |
| `titration` | rate changed four times mid-infusion |
| `occlusion` | alarm active → muted → cleared → infusion resumes |
| `air-in-line` | the same alarm firing twice (repeat-alarm handling) |
| `syringe-holder-open` | exact replay of the 4-message sequence in `sample2.txt` |
| `near-end` | near-end condition, completion, then KVO |
| `battery` | PCD-15 on mains → on battery → Battery Low → Battery Empty → recovery |
| `power-off` | `pump-stopped-powered-off`, reporting gap, pump returns |
| `wifi-drop` | signal decays, station goes quiet, backfill arrives **out of order** |
| `standby` | paused into standby until the standby-timeout reminder |
| `multi-pump` | 4 pumps, 2 wards, one SpaceStation, concurrent |
| `alarm-storm` | 6 pumps alarming and clearing in quick succession |
| `alarm` | fire one chosen alarm — `--alarm air-in-line` |
| `malformed` | truncated / unparseable / out-of-spec HL7 — nothing may be lost |
| `soak` | throughput test — `--pumps 20 --messages 50` |
| `replay` | the genuine captures in `tests/samples/` |

The simulator runs on its own clock: messages carry *simulated* timestamps and
`--speed` only changes how long the run takes in wall-clock seconds, so an hour
of infusion can be replayed in a minute with the arithmetic still lining up.

| Option | Default | Description |
|---|---|---|
| `--host`, `--port` | `127.0.0.1:6000` | engine MLLP endpoint |
| `--speed` | `10` | simulated seconds per real second; `0` = no waiting |
| `--interval` | `60` | seconds between periodic PCD-01 reports |
| `--start-time` | now | backdate the clock, e.g. `2026-08-05T09:00:00` |
| `--dry-run`, `--out FILE` | off | print / save the HL7 instead of (as well as) sending |
| `--repeat N`, `--loop` | 1 | run the scenarios repeatedly |
| `--connection` | `persistent` | or `per-message` to reconnect for every message |
| `--alarm-identity` | `sys-id` | `realistic` omits MDC_ATTR_SYS_ID from PCD-04, exactly like the captures |
| `--pump-type`, `--label`, `--ward`, `--facility`, `--drug`, `--rate`, `--vtbi`, `--mrn` | — | override the scenario's first pump |

Two things worth knowing, both true of real pumps and reproduced here:

- **Battery and wifi only arrive in PCD-15**, never in PCD-01, so a pump's
  battery stays `null` until a device report comes in.
- **PCD-04 alarms carry no `MDC_ATTR_SYS_ID`** in the B.Braun captures, so the
  engine can only key them as `<station>:<label>` — a *different* record from the
  PCD-01 stream. The simulator adds the SYS_ID by default so alarms bind to the
  right pump; `--alarm-identity realistic` reproduces the split.

Exit code is 0 only when every message got the ACK the scenario expected.

## Test

Parser unit tests (against real captured B.Braun messages in `tests/samples/`):

```bash
python -m tests.test_parser
```

Simulator tests — runs every scenario and pushes the generated HL7 back through
the parser:

```bash
python -m tests.test_simulator
```

End-to-end: with the engine running, send all samples over MLLP and check the API:

```bash
python -m tests.send_samples 127.0.0.1 6000
curl http://127.0.0.1:6001/api/pumps
```

## Windows Firewall

If the pump gateway cannot connect, allow inbound TCP 6000:

```powershell
netsh advfirewall firewall add rule name="Infusion Engine HL7" dir=in action=allow protocol=TCP localport=6000
```
