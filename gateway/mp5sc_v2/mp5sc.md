# MP5SC Gateway — Scenario, Pain Points & Robustness Brainstorm

> **Purpose of this document.** This is *not* a design or implementation spec.
> It describes the real-world scenario of the Philips MP5SC → Raspberry Pi →
> SmartWard pipeline, then catalogues where it hurts today. The goal is to agree
> on **what the problems actually are** before committing to any fix. The last
> section is a deliberately loose brainstorm of directions — options, not
> decisions.
>
> Companion doc (what v2 already changed): [`mp5sc_v2.md`](./mp5sc_v2.md).

---

## 1. The scenario

### 1.1 Physical setup

A **mobile vital-signs cart** is pushed from room to room:

```
   ┌─────────────────────────────┐        ┌──────────────────────────┐
   │ Philips MP5SC monitor       │        │ Raspberry Pi (gateway)   │
   │ 192.168.0.5                 │        │ 192.168.0.10             │
   │ (BP cuff, SpO2, Temp, ...)  │◄──────►│ - polls the monitor      │
   │                             │  UDP   │ - queues readings        │
   └─────────────────────────────┘ :24105 │ - forwards to server     │
                                           └────────────┬─────────────┘
      powered by a USB power bank ◄────────────────────┘│
                                                         │ Wi-Fi (DHCP or fixed)
                                                         ▼
                                           ┌──────────────────────────┐
                                           │ SmartWard server          │
                                           │ http://smartward:88       │
                                           │ POST /api/v1/vital-signs  │
                                           └──────────────────────────┘
```

Key physical facts that drive every pain point below:

- The **whole rig runs on a consumer USB power bank**, because the cart is
  constantly moved.
- The **monitor↔Pi link is a small private network** (`192.168.0.0/24`, monitor
  fixed at `.5`, Pi at `.10`).
- The **Pi↔server link is hospital Wi-Fi**, addressed by DHCP or a fixed IP.
- The Pi is an **unattended appliance** — nobody logs into it during a shift.

### 1.2 Data flow (v2, current code)

1. The Pi's listener opens a **proprietary Philips polling association** to the
   monitor over UDP `24105` and polls every few seconds
   ([`ipv_data_source.py:649`](../mp5sc_listener/listener/src/ipv_data_source.py#L649)).
2. Continuous values (HR, SpO2, Temp, RR) are cached as "last valid" as they
   arrive ([`main.py:172`](./listener/main.py#L172)).
3. **A new NBP (blood-pressure) reading is the trigger.** When one appears, the
   listener builds a payload = BP + the *last cached* HR/SpO2/Temp/RR + patient
   ID, and writes it to a local **SQLite outbox**
   ([`main.py:246`](./listener/main.py#L246)).
4. A background **sender** thread drains the outbox, POSTing to
   `/api/v1/vital-signs`, and marks rows `sent` on HTTP 200/201, or `retry`
   with exponential backoff otherwise ([`main.py:49`](./listener/main.py#L49)).
5. The server authenticates (passphrase header + username/password in body),
   looks the patient up by `mrn`/`rn`/`visit_number`/`ic_passport`, and inserts
   a `VitalSign` row
   ([`VitalSignApiV1Controller.php:46`](../../app/Http/Controllers/VitalSignApiV1Controller.php#L46)).

### 1.3 What v2 already improved (baseline)

So we don't re-litigate solved problems, v2 already:

- Replaced "read-and-POST-immediately" with a **store-and-forward SQLite outbox**
  ([`storage.py`](./listener/src/storage.py)).
- Added **client-side dedupe** via a deterministic `gateway_event_id`
  ([`main.py:133`](./listener/main.py#L133)).
- **Fixed the legacy watchdog bug** where the recovery check compared a *method
  object* instead of *calling* it — so the old watchdog never actually recovered
  ([`ipv_data_source.py:97`](../mp5sc_listener/listener/src/ipv_data_source.py#L97)
  vs. the fix in
  [`reliable_ipv_data_source.py:44`](./listener/src/reliable_ipv_data_source.py#L44)).

Those are real wins. The pain points below are what v2 **still** does not solve.

---

## 2. Pain points

Each item is tagged with the kind of damage it does:
**[Patient-safety]**, **[Data-loss]**, **[Reliability]**, **[Ops-blind]**,
**[Security]**, **[Deploy]**.

### A. Power & the physical cart

- **A1. Power-bank auto-sleep. [Reliability]**
  Consumer power banks cut output when current draw drops below a threshold. An
  idle Pi (especially while parked/transported and not polling hard) can dip
  under that threshold → the bank powers off → the Pi dies silently. Nobody
  notices until vitals stop appearing on the server.

- **A2. Abrupt power loss corrupts the SD card. [Data-loss]**
  Moving the cart jostles the USB connector; brownouts and hard cuts are normal.
  A Raspberry Pi + SD card + sudden power loss is the single most common field
  failure — it corrupts the *filesystem*, not just the last transaction. When
  that happens the outbox DB (and the OS) can be lost entirely, which defeats the
  whole point of store-and-forward. The DB is only as durable as the SD card
  under power cuts.

- **A3. No battery visibility, no graceful shutdown. [Ops-blind]**
  Nothing measures or reports battery state, and there is no low-battery
  graceful shutdown. The system cannot warn "this cart is about to die" and
  cannot flush/park safely before it does. v2's own doc flags this but the code
  does nothing about it.

### B. Network

- **B1. Two networks, one Pi, easy to misroute. [Reliability]**
  The Pi lives on a private `192.168.0.0/24` link to the monitor **and** on
  hospital Wi-Fi. If the hospital DHCP range overlaps `192.168.0.0/24`, or if
  both interfaces claim a default route, traffic to the monitor or to the server
  can silently go out the wrong interface. There is no explicit interface/route
  policy anywhere in the repo.

- **B2. Wi-Fi roaming gaps while moving. [Data-loss risk / masked by queue]**
  As the cart moves between rooms/APs, the Pi re-associates, its DHCP lease can
  change, and there are dead zones. Every send during that gap fails. The outbox
  masks this *if* the Pi survives, but see A1/A2 — the outbox and the Pi are on
  the same fragile power.

- **B3. Server addressed by hostname `smartward`. [Reliability]**
  Both `.env.example` and the Dockerfile hard-code `http://smartward:88`
  ([`.env.example:1`](./listener/.env.example#L1),
  [`Dockerfile:19`](./Dockerfile#L19)). If hospital Wi-Fi DNS does not resolve
  `smartward` (very likely — it's not an FQDN), **every** send fails with a name
  error, indefinitely, and it looks like a total outage.

- **B4. The monitor link itself is UDP with no retransmit. [Data-loss]**
  Polling is raw UDP with a 15 s socket timeout
  ([`ipv_data_source.py:667`](../mp5sc_listener/listener/src/ipv_data_source.py#L667)).
  If the monitor↔Pi link is wireless or flaky, lost packets = missed polls =
  missed values, with no recovery for that specific reading.

### C. The monitor link & the proprietary listener

- **C1. Reverse-engineered protocol, hard-coded offsets. [Reliability]**
  `ipv_data_source.py` is a reverse-engineered Philips IntelliVue export
  implementation ("Testbench 0.3", "from packet sniffing >> empiric appendix").
  It parses by fixed byte offsets and magic physiological IDs
  ([`ipv_data_source.py:301`](../mp5sc_listener/listener/src/ipv_data_source.py#L301)).
  Any monitor firmware/config/probe difference can shift offsets or IDs and the
  parser will silently produce wrong or empty values rather than error out.

- **C2. Capture only fires on a new blood-pressure reading. [Patient-safety / Data-gap]**
  Vitals are queued **only** when a new NBP timestamp appears
  ([`main.py:246`](./listener/main.py#L246)). Between BP cuffs, continuous HR /
  SpO2 / Temp / RR are cached but **never sent**. If a patient is monitored
  without NBP (e.g. SpO2-only), the server receives *nothing at all*. The system
  is structurally a "BP-and-a-snapshot" recorder, not a vitals recorder.

- **C3. Continuous values are a stale snapshot at BP time. [Patient-safety]**
  The HR/SpO2/Temp/RR attached to a BP event are the *last cached* values
  ([`main.py:258`](./listener/main.py#L258)), which may be seconds-to-minutes
  old and taken under different conditions than the cuff reading. They are
  recorded as if simultaneous.

- **C4. Duplicate-poller risk on recovery. [Reliability]**
  v2's watchdog restarts `do_events` if the poll thread looks dead
  ([`reliable_ipv_data_source.py:44`](./listener/src/reliable_ipv_data_source.py#L44)).
  It guards with `is_alive()`, but the underlying legacy `do_events` can be stuck
  inside `recvfrom`; a restart can leave two threads racing on the same monitor.
  Better than legacy, not provably safe.

### D. Patient identity & clinical correctness

- **D1. Wrong-patient window when the cart moves. [Patient-safety — highest]**
  `last_valid_*` vitals **and** `last_patient_id` persist on the listener
  object ([`main.py:96`](./listener/main.py#L96)), and patient context only
  refreshes every `REFRESH_INTERVAL` polls
  ([`main.py:234`](./listener/main.py#L234)). When the cart is wheeled to a new
  patient, there is a window where a BP event can fire while the patient ID (or
  the cached HR/SpO2) still belongs to the **previous** patient → a reading
  attributed to the wrong person. In a ward this is the most dangerous failure
  mode in the whole system.

- **D2. Patient ID is whatever was typed into the monitor. [Patient-safety]**
  The pipeline blindly trusts the monitor's patient field. A mistyped/blank ID
  means the reading either attaches to the wrong patient or 404s (see D3). There
  is no scan-to-confirm, no cross-check against the server's admitted-patient
  list before capture.

- **D3. "Patient not found" readings retry forever. [Data-loss / Queue-bloat]**
  If the ID doesn't match any patient, the server returns **404**
  ([`VitalSignApiV1Controller.php:190`](../../app/Http/Controllers/VitalSignApiV1Controller.php#L190)).
  The sender treats **any** non-2xx as retryable
  ([`api_client.py:45`](./listener/src/api_client.py#L45),
  [`main.py:66`](./listener/main.py#L66)) and never gives up — no max-retry, no
  dead-letter. That reading can never succeed (the patient isn't there), so it
  retries every ≤5 min *forever*, and the queue only grows. Validation errors
  (422) are stuck the same way.

### E. The store-and-forward queue (the part the user most wants fixed)

- **E1. No FIFO eviction / no retention cap — SQLite grows forever. [Data-loss (eventual)]**
  `mark_sent` only flips `status='sent'`
  ([`storage.py:96`](./listener/src/storage.py#L96)); **nothing is ever
  deleted** — not sent rows, not old rows, not by count, not by age. On a small
  SD card the DB grows until the disk fills, at which point writes fail and the
  Pi can't queue *new* readings — a silent, delayed data-loss bomb. This is
  exactly the "proper SQLite with FIFO erase" the user asked for; today it does
  not exist.

- **E2. No server-side dedupe → duplicates on lost ACK. [Data-integrity]**
  `gateway_event_id` and `X-Idempotency-Key` are sent
  ([`api_client.py:37`](./listener/src/api_client.py#L37)) but the **server
  ignores both** (confirmed: no reference to either anywhere in `app/`). If the
  server writes the row but the ACK is lost to a Wi-Fi drop (very common here),
  the Pi marks it `retry` and re-sends → a **duplicate vital-sign record**. The
  only dedupe today is the Pi's *local* SQLite UNIQUE constraint, which does
  nothing about a successful-write-but-lost-ACK.

- **E3. Backoff doesn't apply to network errors. [Reliability]**
  `api_client.send_vital_signs` wraps no try/except around the HTTP call
  ([`api_client.py:38`](./listener/src/api_client.py#L38)), so a
  `ConnectionError`/`Timeout` propagates out of the per-event loop into the
  sender's outer handler ([`main.py:76`](./listener/main.py#L76)). The current
  event is **never marked retry** (its `next_retry_at` doesn't advance), the rest
  of the batch is skipped, and the loop just sleeps `SEND_INTERVAL` (3 s) and
  retries the same event — i.e. when the server is unreachable it **hammers at a
  fixed 3 s interval with no backoff**, instead of the intended exponential
  backoff.

- **E4. Head-of-line blocking on a poison row. [Reliability]**
  The batch is fetched `ORDER BY id ASC` and each row is `json.loads`-ed
  ([`main.py:59`](./listener/main.py#L59)). A corrupted `payload_json` throws,
  aborting the whole batch before later (healthy) rows are attempted. One bad row
  can stall the queue.

- **E5. Credentials sit in plaintext inside the queue. [Security]**
  Every payload embeds `username`/`password`
  ([`main.py:110`](./listener/main.py#L110)) and the whole payload is stored as
  `payload_json` in SQLite. Combined with hard-coded creds in the image
  ([`Dockerfile:19`](./Dockerfile#L19), [`.env.example:1`](./listener/.env.example#L1)),
  a lost/stolen cart SD card leaks working API credentials and patient data.

### F. Time & clock

- **F1. The Pi has no RTC; offline timestamps drift. [Data-integrity]**
  `measured_at` uses the monitor's clock
  ([`main.py:258`](./listener/main.py#L258)) while the queue uses
  `datetime.utcnow()` ([`storage.py:9`](./listener/src/storage.py#L9)). A Pi has
  no battery-backed clock; after a power-death reboot with no network (no NTP),
  its clock resets, and any timestamp it generates while offline is wrong. If the
  monitor's clock is also unset, `measured_at` — *and* the `gateway_event_id`
  derived from it — are wrong, corrupting both the record and the dedupe key.

### G. Observability & operations

- **G1. No heartbeat to the server — ops are blind. [Ops-blind]**
  There is a `/ping` endpoint
  ([`VitalSignApiV1Controller.php:372`](../../app/Http/Controllers/VitalSignApiV1Controller.php#L372)),
  but v2 **never calls it**. Nothing reports "Pi X is alive, on battery Y%, queue
  depth Z, last successful send at T". When a cart dies mid-transport, the only
  signal is the *absence* of vitals — noticed late, by a human. The user's ask
  for "send a warning to the server" has no mechanism today.

- **G2. Device status is silently disabled in the common mode. [Ops-blind]**
  `update_device_status` returns immediately when `device_id` is `None`
  ([`api_client.py:27`](./listener/src/api_client.py#L27)). In fixed-`MONITOR_IP`
  mode the listener is started with `device_id=None`
  ([`main.py:359`](./listener/main.py#L359)), so the "one Pi, one monitor"
  deployment — the exact scenario described — reports **no status at all**.

- **G3. The `/ping` server side can't reliably identify the gateway. [Ops-blind]**
  The ping handler tries MAC, then last-known IP, then gives up with commented-out
  guesswork ([`VitalSignApiV1Controller.php:391`](../../app/Http/Controllers/VitalSignApiV1Controller.php#L391)).
  Even if the Pi did ping, the server may not know *which* cart it was.

- **G4. Logs are `print()` to stdout with no persistence. [Ops-blind]**
  All diagnostics are `print()` ([`main.py:29`](./listener/main.py#L29)). If the
  Pi isn't run under systemd/journald, they vanish on reboot. Queue depth is only
  logged locally ([`main.py:200`](./listener/main.py#L200)), never surfaced. Field
  failures are near-impossible to diagnose after the fact.

### H. Provisioning & deployment

- **H1. No Pi setup/provisioning script. [Deploy]**
  Deploying to a new Pi is entirely manual (copy files, write `.env`, install
  deps, *maybe* set up systemd — which the doc only "recommends"). There is no
  script to configure the dual network, install the service, and verify it works.
  (The `deploy/` folder in the repo is for the **server**, not the Pi.)

- **H2. Image cloning → identity collisions. [Deploy]**
  `sdcard/pishrink.sh` implies Pis are provisioned by cloning an SD image. Clones
  share hostname, machine-id, SSH host keys, and any baked-in gateway identity —
  so multiple carts look identical to the network and to the server, which makes
  G1–G3 (per-cart identification) fundamentally unsolvable without a
  per-device first-boot step.

- **H3. No boot-time network preflight / self-test. [Deploy / Ops-blind]**
  Nothing verifies at startup: can I reach the monitor at `.5`? can I resolve and
  reach `smartward:88`? is my clock sane? is the DB writable and the disk not
  full? A freshly deployed Pi can sit there doing nothing, looking "on", with no
  indication anything is wrong.

- **H4. Config lives in two places and drifts. [Deploy]**
  Settings exist in both `.env` and Dockerfile `ENV`
  ([`Dockerfile:18`](./Dockerfile#L18)); it's unclear whether the field runtime is
  Docker-on-Pi or bare Python. Two sources of truth for creds/URLs/IPs invite the
  "works on the bench, wrong in the ward" class of bug.

---

## 3. Concrete failure walkthroughs

To make the above tangible — three plausible "bad shifts":

1. **The silent death.** Cart is parked between rounds. Power bank sees low draw
   (A1) and cuts. Pi loses power hard, SD filesystem corrupts (A2). Because there's
   no heartbeat (G1), nobody knows for hours; when found, the queue DB may be gone
   too. *Data lost, invisibly.*

2. **The wrong chart.** Cart moves from bed 3 to bed 4. Nurse takes a BP before the
   monitor's patient field updates / before the periodic context refresh (D1). The
   reading is queued and sent under bed 3's patient. Server accepts it (it's a valid
   patient), 201 OK. *A real reading on the wrong patient, with no error anywhere.*

3. **The growing swamp.** A patient's ID is mistyped, or they're not admitted yet.
   Every BP for that session 404s (D3) and retries forever. Meanwhile sent rows are
   never purged (E1). Over weeks the SQLite file bloats; one day the SD card fills,
   new readings can't be queued, and the cart quietly stops recording *everything*.

---

## 4. Brainstorm — directions to explore (not decisions)

> Deliberately kept at "options" altitude. We should pick and detail these in a
> follow-up design pass, not here.

**Queue / store-and-forward (E, the headline ask)**
- A real lifecycle for outbox rows: `pending → sent → purge`, plus a terminal
  `dead-letter` state for permanent failures (404/422) so they stop retrying.
- Retention as the user framed it: FIFO purge of `sent` rows by age *and/or* a max
  row/byte cap, with a hard ceiling on DB size so the SD card can never fill.
- Distinguish **retryable** (network/5xx) from **permanent** (404/422) failures;
  cap retries; surface dead-letters to ops instead of hiding them.
- Proper backoff that also covers transport errors, and per-row (not per-batch)
  failure isolation so one poison row can't stall the queue.

**Idempotency (E2)**
- Make the server honor `gateway_event_id` / `X-Idempotency-Key`: store it, and
  return the original result on replay instead of inserting a duplicate. This is
  the only way to make Pi-side retries safe under lost ACKs.

**Health & warnings to the server (G, the "send a warning" ask)**
- A periodic heartbeat carrying: gateway identity, uptime, battery %, queue depth,
  oldest-pending age, last-successful-send, monitor-link status. Server raises an
  alert when a cart goes quiet or its queue backs up.
- Give every cart a stable identity (MAC or a provisioned gateway token) so G1–G3
  actually resolve to "which cart".

**Provisioning / deployment (H)**
- A one-shot setup script for a fresh Pi: set per-device identity, configure the
  static monitor interface + Wi-Fi uplink, install a `systemd` unit
  (`Restart=always`, start-on-boot, journald), then run a **preflight self-test**
  (reach monitor `.5`, resolve+reach server, clock sane, DB writable, disk free)
  and report the result back to the server.
- Decide one runtime (bare systemd Python vs Docker) and one config source of
  truth to kill drift (H4). For a battery-constrained Pi, bare + systemd is likely
  simpler than Docker.
- First-boot identity step to defeat image-clone collisions (H2).

**Power & hardware (A)**
- Evaluate a proper Pi UPS HAT / supercap board instead of a consumer power bank,
  so brownouts are absorbed and a low-battery *graceful shutdown* becomes possible.
- Read battery state (UPS HAT exposes it) and feed it into the heartbeat.
- Consider a read-mostly / overlay root filesystem so power cuts can't corrupt the
  OS, keeping only the outbox on a robust writable partition.

**Network & time (B, F)**
- Explicit interface/route policy: static monitor subnet on one NIC, Wi-Fi uplink
  on the other, no ambiguity. Prefer the server's IP or a resolvable FQDN over the
  bare `smartward` name.
- Add an RTC module *or* refuse to trust timestamps generated while unsynced, and
  reconcile clocks on reconnect so offline data isn't mis-timed.

**Clinical safety (C, D — arguably the most important)**
- Tighten the patient-context window so a BP can't be captured against a stale
  patient ID (e.g. force a fresh context read immediately before enqueue, and drop
  the reading if identity is uncertain).
- Consider whether "BP-triggered snapshot" is the right data model at all, or
  whether periodic continuous vitals should also be captured.
- Explore scan-to-confirm patient identity at the cart before a reading is trusted.

---

## 5. Open questions to settle before designing

1. Is the monitor↔Pi link **wired or wireless**? (Changes B4/C severity a lot.)
2. Docker-on-Pi or bare Python in the field? (Decides H1/H4 direction.)
3. How are Pis imaged today — clone or per-device install? (Decides H2 approach.)
4. What is the acceptable **duplicate vs. loss** trade-off clinically? (Drives how
   aggressive retries/idempotency should be.)
5. Is BP-only capture acceptable clinically, or must continuous vitals be stored?
6. Is there budget/appetite for a **UPS HAT + RTC**, or must this stay on power
   banks? (Bounds what A/F fixes are even possible.)
7. What should "warn the server" actually *do* — dashboard flag, nurse-station
   alert, both?
