# Comen NC5 gateway

HL7/MLLP gateway for the Comen NC5 bedside monitor. It does two jobs on the
monitor's single long-lived TCP connection:

1. **Answers the monitor's patient query from the real SmartWard record.** The
   NC5 asks "who is this MRN?" before it will admit a patient to a bed. The
   gateway resolves it with `GET /api/v1/patients/{code}` — the same lookup the
   rest of SmartWard uses, matching on `mrn`, `rn`, `visit_number` or
   `ic_passport` — and replies with the patient's name, date of birth, sex, ward
   and bed.
2. **Forwards vitals to SmartWard with store-and-forward durability.** Observations
   are aggregated per patient and posted to `POST /api/v1/vital-signs` through a
   SQLite outbox with classified retries, dead-lettering and idempotency, exactly
   like `gateway/vs4_v2` and `gateway/mp5sc_v2`.

`server.js` in this folder is the original bench prototype that answered queries
from `patients.json`. It is kept for offline poking at the monitor; it is not the
gateway and it does not talk to SmartWard.

## Layout

```
nc5/
  listener/
    main.py                  manager: listener + outbox sender + heartbeat
    .env.example             every setting, documented
    src/
      hl7_mllp.py            MLLP framing; a handler may return a full reply
      hl7_common.py          segment/field/component/timestamp helpers
      hl7_query.py           QRY^R02 -> ORF^R04, QRY^A19 -> ADR^A19
      hl7_oru.py             MDC/LOINC observation interpreter
      patient_directory.py   API-backed MRN lookup + cache
      api_client.py          SmartWard HTTP client
      storage.py             SQLite outbox (pending/retry/sent/dead + retention)
      heartbeat.py           health telemetry
  docker_nc5/                standalone Docker stack for the SmartWard NAS
  test_parse.py              offline checks for the interpreter and responder
  test_sender.py             replays real NC5 traffic at a running gateway
  requirements.txt
```

## Deployment

On the SmartWard NAS it runs as its own Docker stack (`kelvinric/nc5`),
separate from the SmartWard compose so it can be pulled, updated or removed on
its own — see [docker_nc5/README.md](docker_nc5/README.md). Anywhere else, run
`listener/main.py` directly as described below.

## Running it

```bash
cd gateway/nc5
pip install -r requirements.txt
cp listener/.env.example listener/.env      # then fill in API_* and GATEWAY_ID
python listener/main.py
```

On the NC5, point the HL7 export **and** the patient query at this host on
`LISTEN_PORT` (2555 by default — the port the monitor ships with).

Bench check without a monitor:

```bash
python test_parse.py                                  # no server needed
python test_sender.py 127.0.0.1 2555 MRN000001        # against a running gateway
```

## The patient query

| Monitor sends | Gateway replies | Notes |
| --- | --- | --- |
| `QRY^R02` (id in QRD-8) | `ORF^R04` | The NC5's own dialect. QRD/QRF echoed, id in PID-3 and PID-18, `PV1-3 = ward^^bed`. |
| `QRY^A19` | `ADR^A19` | Mindray/eGateway dialect. `PV1-3 = ^^ward&bed&0&0&0`. |

Outcomes are deliberately distinct:

* **found** → `MSA|AA` plus PID/PV1 built from the SmartWard record.
* **not found** → `MSA|AE ... Patient not found` (`MSA|AA ... The patient is not
  found!` in the A19 dialect, which is what that dialect's senders expect).
* **lookup failed** (SmartWard unreachable) → `MSA|AE ... Patient lookup
  unavailable`, so an outage never reads as "this patient does not exist".

A patient the API confirmed within `PATIENT_CACHE_STALE_TTL` may be re-served
during an outage. A "not found" answer is never cached and never guessed —
admitting the wrong patient at the bedside is the failure mode this design
protects against.

SmartWard stores one `name` field; HL7 wants `Family^Given`. By default the whole
name goes in the family component (`PATIENT_NAME_ORDER=full`), which is what the
monitor displays. `split_last` / `split_first` are available for wards whose names
follow Western ordering.

## Vitals

Observations are matched on the OBX-3 identifier: the IEEE 11073 mnemonic family
first (`MDC_PRESS_BLD_NONINV_SYS`, `MDC_PULS_OXIM_SAT_O2`, `MDC_TEMP`, …), then
well-known numeric/LOINC codes, then the human-readable text. Matching the
mnemonic family means a firmware revision that renumbers codes still parses, and
that arterial-line pressures are recognised as pressures rather than dropped.

| SmartWard field | Sourced from |
| --- | --- |
| `blood_pressure_systolic` / `_diastolic` | `MDC_PRESS_BLD_NONINV_*` (cuff), else `MDC_PRESS_BLD_ART_*`, noted as "BP from ART" |
| `pulse_rate` (+ `_min`/`_max`) | `MDC_PULS_*_RATE` (SpO2 pulse) |
| `heart_rate` | `MDC_ECG_HEART_RATE` |
| `spo2` (+ `_min`/`_max`) | `MDC_PULS_OXIM_SAT_O2` |
| `temperature` | `MDC_TEMP*` (°F converted) |
| `respiratory_rate` | `MDC_RESP_RATE`, else airway/CO₂ rate |
| `weight` / `height` | `MDC_MASS_BODY_ACTUAL` / `MDC_LEN_BODY_ACTUAL` (lb/in/m converted) |

Ignored on purpose: mean/MAP pressures (no API field), `COMEN_*` demographics,
observations flagged `X` (cannot obtain), and the 11073 "not available" sentinels.

### Why readings are aggregated

The NC5 is a continuous monitor: charting every message would produce tens of
thousands of rows per bed per day. Instead each patient gets a window
(`RECORD_INTERVAL`, default 5 min) holding the latest value per field plus the
min/max seen for PR and SpO2, posted as one reading — the same shape mp5sc_v2
sends, so the ward chart looks identical across gateway types.

Two rules keep the timing honest:

* A **completed NIBP flushes immediately** — a new cuff reading is a clinical
  event, not something to hold for five minutes.
* The monitor keeps repeating that cuff reading in later messages, so it is
  charted once. A BP with different numbers is always a new cycle; identical
  numbers with a later observation time count as a new cycle only after
  `BP_REPEAT_GUARD` (so two cuff cycles that happen to read the same are both
  charted, but a chatty retransmitter is not). Separately, any value whose own
  OBX-14 timestamp is older than `VITAL_STALENESS` relative to the newest one in
  the window is dropped rather than re-dated to now.

A window also closes early when the monitor goes quiet (`WINDOW_IDLE_TIMEOUT`) or
when the bed changes patient, and everything still buffered is flushed to the
durable queue on shutdown.

### What is never guessed

An ORU with no patient ID is acknowledged, counted, and dropped with a warning.
`ADT^A08` is used only to follow which patient the monitor thinks is in which bed
(so a handover closes the previous patient's window); it never produces a vitals
record.

## Delivery, retries, health

Identical to `vs4_v2`: `gateway_event_id` is a content hash, so a monitor
retransmitting after a lost ACK deduplicates in the outbox and again at the
server's idempotency key. Failures are classified — `hard` (400/422) dead-letters
at once, `soft` (404, patient not admitted yet) retries for `MAX_AGE_404_MINUTES`,
`auth` retries `MAX_ATTEMPTS_AUTH` times, everything else backs off exponentially
up to `MAX_AGE_TRANSIENT_HOURS`. Retention and a hard size cap keep the SQLite
file bounded, and evicting an unsent row is logged as an alarm because that is
real data loss.

`POST /api/v1/gateway/heartbeat` carries queue depth, connected monitors, open
windows, lookup counters, power/undervoltage and systemd state, so a dead or
stuck gateway shows up on the dashboard without waiting for vitals to stop.
