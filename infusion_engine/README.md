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

## Test

Parser unit tests (against real captured B.Braun messages in `tests/samples/`):

```bash
python -m tests.test_parser
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
