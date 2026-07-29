# Mindray VS8 — network integration

Reverse-engineering notes and gateway for the VS8 vital signs monitor.
Everything marked **confirmed** was observed on the wire at this site.

**Headline:** the VS8 speaks standard **IHE PCD-01 HL7 over MLLP**. The
proprietary TLS channel on 9997 is *not* required to get vitals — HL7 on 2575
carries them, and the ADT query on 3502 pushes patient demographics back.

## Site configuration

| Setting | Value |
|---|---|
| Monitor IP | `192.168.1.10` (MAC `00:0f:14:50:16:49`, OUI = Mindray) |
| Device ID | `MINDRAY_VS_SERIES` / EUI-64 `00A037009B023649` |
| This host | `192.168.1.5`, adapter `\Device\NPF_{08C49AA7-EB1B-4E7C-8783-E818B60FF74F}` |
| Multicast | `225.0.0.8` |
| HL7 send | → `192.168.1.5:2575` (vitals) |
| ADT query | → `192.168.1.5:3502` (demographics) |
| MLDAP | → `192.168.1.5:6665` |

The HL7 and ADT ports only appear in the monitor's menus **after** its handshake
completes — that is why they were invisible in the first sweeps.

## Channels observed

| Direction | Transport | Purpose | Status |
|---|---|---|---|
| VS8 → `:2575` | TCP MLLP | `ORU^R01` vitals push | **working** |
| VS8 → `:3502` | TCP MLLP | ADT demographics query | **serving**, awaiting a real query |
| VS8 → `225.0.0.8:6678` | UDP multicast | Announce beacon, every 15.0 s | confirmed, low value |
| VS8 → `:9997` | TCP + TLS | Proprietary CMS session | blocked, **not needed** |
| VS8 → `:6665` | TCP | MLDAP (user auth directory) | probe only |

### tcp/2575 — vitals (working)

The monitor pushes `ORU^R01` and expects an MLLP `ACK`. A real message:

```
MSH|^~\&|MINDRAY_VS_SERIES^00A037009B023649^EUI-64|MINDRAY|||20260727124424.0000+0000
   ||ORU^R01^ORU_R01|1|P|2.6|||AL|NE||UNICODE UTF-8
   |||IHE_PCD_001^IHE PCD^1.3.6.1.4.1.19376.1.6.1.1.1^ISO
PID|||99999^^^Hospital^PI||^^^^^^L|||||unknownrace
PV1||I
OBR|1|1^MINDRAY_VS_SERIES^00A037009B023649^EUI-64|...|182777000^monitoring of patient^SCT
OBX|1|NM|150456^MDC_PULS_OXIM_SAT_O2^MDC|1.3.1.150456|77|262688^MDC_DIM_PERCENT^MDC||DEMO|||R
OBX|2|NM|149530^MDC_PULS_OXIM_PULS_RATE^MDC|1.3.1.149530|50|264864^MDC_DIM_BEAT_PER_MIN^MDC||DEMO|||R
OBX|3|NM|150488^MDC_BLD_PERF_INDEX^MDC|1.3.1.150488|3.06|262688^MDC_DIM_PERCENT^MDC||DEMO|||R
OBX|4|NM|151658^MDC_PULS_OXIM_PLETH_RESP_RATE^MDC|1.3.1.151658|12|264928^MDC_DIM_RESP_PER_MIN^MDC||DEMO|||R
```

Notes that matter when parsing:

- Profile is **IHE PCD-01** (`1.3.6.1.4.1.19376.1.6.1.1.1`), HL7 v2.6, UTF-8.
- OBX-3 and OBX-6 are ISO/IEEE 11073 triplets ordered **code first**:
  `150456^MDC_PULS_OXIM_SAT_O2^MDC`. Component 1 is a bare number, component 2 is
  the readable MDC name — key on component 2.
- OBX-4 is a containment sub-ID (`1.3.1.150456`) identifying the channel.
- **`OBX-8 = DEMO` means the monitor is in demo mode and the values are
  simulated.** Do not treat these as patient data. Expect `R` (result status,
  OBX-11) on live data too.
- PID-3 is `99999^^^Hospital^PI`; the name in PID-5 is empty (`^^^^^^L`) until
  ADT supplies it.

### tcp/3502 — ADT query (the sync path)

This is the direction that matters for pushing ADT *onto* the monitor: the VS8 is
the client, asking us for demographics by patient ID. Answering it populates the
patient on the machine so staff do not key it in.

`vs8_hl7.py` answers in whichever dialect the monitor uses:

| Query | Response |
|---|---|
| `QRY^A19` | `ADR^A19` + `MSA\|AA` + `QRD` + `PID` + `PV1` |
| `QBP^Q22` | `RSP^K22` + `MSA\|AA` + `QAK\|…\|OK` (or `NF`) + `QPD` + `PID` + `PV1` |

Records come from `patients.json`, keyed by patient ID. `assigning_authority`
and `id_type` mirror the monitor's own PID-3 (`Hospital` / `PI`).

### tcp/9997 — proprietary TLS (blocked, and not needed)

The VS8 opens this every ~23 s with a TLS ClientHello (TLS 1.3/1.2, x25519, no
SNI). Presenting a self-signed certificate is refused:

```
[SSL: TLSV1_ALERT_UNKNOWN_CA] tlsv1 alert unknown ca
```

The monitor validates the server certificate against a trust store it already
ships with, so no self-signed or ad-hoc CA will pass. This would need a
Mindray-issued certificate (normally part of a licensed BeneVision CMS /
eGateway). **Since vitals arrive over HL7 on 2575, this path can be ignored.**

### udp/225.0.0.8:6678 — announce beacon

Every 15.0 s from source port 5500, byte-identical between beacons, so it carries
no live measurements. Header `82 01 01 00 00 04 02 …` then a high-entropy body
(encrypted or compressed). Discovery metadata only.

## Scripts

Python 3 (`py -3`), no third-party packages.

### `vs8_hl7.py` — the gateway (main program)

```bash
py -3 gateway/vs8_new/vs8_hl7.py
```

Listens on 2575 (vitals, replies `ACK`) and 3502 (ADT query, replies from
`patients.json`). Prints decoded observations and logs every message in and out
to `logs/vs8_hl7_*.jsonl`.

### `test_client.py` — self-test

```bash
py -3 gateway/vs8_new/test_client.py
```

Sends a synthetic `ORU^R01`, `QRY^A19` and `QBP^Q22` over MLLP so the gateway can
be verified without the monitor.

### `sniff.py` — ground truth

```bash
py -3 gateway/vs8_new/sniff.py --duration 60
```

Holds an IGMP membership for the multicast group, runs `dumpcap` (Npcap, works
unelevated here), prints a per-flow summary. Run this whenever behaviour changes
— it sees ports you did not guess, which is how 2575 was found.

### `vs8_listen.py` / `vs8_tcp.py` — discovery tools

UDP multicast listener and raw TCP recorder, kept for protocol work. `vs8_tcp.py
--tls` reproduces the 9997 certificate rejection.

## Next steps

1. Trigger a patient query on the monitor to capture a real `3502` request and
   confirm the response dialect. **Not yet observed.**
2. Take the monitor out of demo mode to get live values (`OBX-8` will stop
   saying `DEMO`).
3. Feed `patients.json` from SmartWard instead of a static file.
4. Forward parsed observations into SmartWard's vitals store.

## Troubleshooting

- **Nothing arrives.** Check the monitor's server address is `192.168.1.5`; this
  host has no `192.168.0.x` address.
- **`UnicodeDecodeError` from dumpcap.** Handled — subprocess output is decoded
  as UTF-8 because the console here is cp950.
- **Adapter lookup** matches by GUID, not display name (the alias is non-ASCII).
  Override with `sniff.py --dev`.
