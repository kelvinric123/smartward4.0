# Mindray VS8 patient lookup

Answers the VS8's patient query so the nurse types an MRN on the monitor and the
patient's **name** appears on screen. Patient details come from
`patients.json`; every message in and out is logged.

```
nurse types 12345  ->  VS8 sends QRY^A19  ->  main.py answers ADR^A19
                                           <- "Doe, John" on the monitor
```

Python 3, standard library only — nothing to install.

## Run it

```bash
cd gateway/vs8
python main.py
```

It listens on `0.0.0.0:2575` (pass a port to change it: `python main.py 2576`).
Then on the VS8, set the ADT / patient-query server to this machine's IP and
that port.

Check it without the monitor:

```bash
python test_sender.py 127.0.0.1 2575 12345
```

That sends the same query the VS8 sends and prints the reply — a `PID` segment
carrying the name is a successful lookup.

## Patients

`patients.json` is re-read on every query, so editing it updates what the
monitor sees immediately — no restart:

```json
{
  "12345": {
    "mrn": "12345",
    "firstName": "John",
    "lastName": "Doe",
    "dob": "19800115",
    "sex": "M",
    "ward": "ICU",
    "bed": "01",
    "height": 170,
    "weight": 70
  }
}
```

* The key is what the nurse types. `mrn`, `rn` and `visit_number` also resolve
  to the same record, so any of a patient's identifiers works.
* Use `firstName`/`lastName`, **or** a single `name` field — with `name` the
  whole string goes in the family component, which is what the monitor
  displays. Splitting a name on spaces mangles anything that is not Western
  ordering, so it is not done by guesswork.
* `dob` accepts `19800115` or `1980-01-15`. `sex` accepts `M`/`F`/`Male`/`Female`.
* `ward` and `bed` populate PV1; `height` (cm) and `weight` (kg) are optional
  and pre-fill those fields on the monitor.
* A malformed file is logged and the previously loaded patients stay in use, so
  a half-saved edit never takes lookups down mid-shift.

## How the VS8 asks (Mindray ADT Net Query)

Mindray monitors and eGateway use the HL7 v2.3.1 A19 query dialect over MLLP:

```
IN   MSH|^~\&|Mindray|Gateway|||20260724134901||QRY^A19|2|P|2.3.1
     QRD|20260724134901000|D|D|1|||1^RD|12345|^DEM|^MindrayGateway

OUT  MSH|^~\&|VS8_GATEWAY|SMARTWARD|Mindray|Gateway|<ts>||ADR^A19|<id>|P|2.3.1
     MSA|AA|2|The Patient is Found
     QRD|<echoed back verbatim>
     PID|1||12345||Doe^John||19800115|M
     PV1|1|I|^^ICU&01&0&0&0
     OBX|1|NM|52^Height||170|cm|||||F
     OBX|2|NM|51^Weight||70|kg|||||F
```

Four details the device cares about:

1. **QRD-8** holds what the nurse typed — that is the lookup key.
2. **The QRD segment is echoed verbatim.** Mindray matches the response to its
   pending query with it.
3. **A miss is still `MSA|AA`**, with the reason in MSA-3 (`The patient is not
   found!`). An `AE` reads as a transport failure and can leave the monitor
   retrying instead of telling the nurse the ID is unknown.
4. **PV1-3 packs ward and bed as sub-components**: `^^Ward&Bed&0&0&0`.

`QRY^R02` is also answered (with `ORF^R04`) in case a unit is configured for
that dialect. Anything else — `ORU^R01` vitals, `ADT^A08` — is acknowledged and
logged; vitals additionally get a decoded one-line summary.

## Logs

* `logs/vs8-YYYY-MM-DD.log` — every message in and out, one segment per line,
  plus each lookup and its result.
* `capture/<timestamp>_<ip>.bin` — the raw bytes of each connection, exactly as
  they arrived. Text logs decode to UTF-8 with replacement, which is fine for
  HL7 but destroys anything binary; this is the copy to inspect when a monitor
  sends something unexpected. Set `CAPTURE_DIR=""` to switch it off.

If the VS8 rejects a reply, the log has both sides of the exchange — that is
what to send over, and the shape can be adjusted from there.

## Settings

All optional, via environment variables:

| Variable | Default | Purpose |
|---|---|---|
| `LISTEN_HOST` / `LISTEN_PORT` | `0.0.0.0` / `2575` | Where to listen (port can also be argv[1]) |
| `PATIENTS_FILE` | `./patients.json` | Patient source |
| `LOG_DIR` | `./logs` | Message log |
| `CAPTURE_DIR` | `./capture` | Raw byte capture (`""` disables) |
| `SENDING_APP` / `SENDING_FACILITY` | `VS8_GATEWAY` / `SMARTWARD` | MSH-3 / MSH-4 in our replies |

## Beyond the JSON file

This serves patients from a file, which is what commissioning and bench testing
need. When it should answer from live SmartWard data instead, `gateway/nc5`
does exactly that — it resolves the same query against
`GET /api/v1/patients/{code}` (matching `mrn`, `rn`, `visit_number` or
`ic_passport`) and falls back to a cached answer when the API is unreachable.
The query-answering code there is a drop-in for this one.
