# VS4 HL7 Listener (discovery stage)

A dependency-free Python HL7/MLLP listener for the **VS4** monitor. Right now it
runs in **discovery mode**: it shows *every* incoming HL7 message (any type, all
segments) and hex-dumps raw bytes, so we can see exactly what the VS4 sends
before building an interpreter. Same structure as the `cm100` project.

## Run

```powershell
py -3 -m venv venv                      # once
venv\Scripts\python.exe -m pip install -r requirements.txt   # once
venv\Scripts\python.exe hl7_listener.py
```

Default bind: `0.0.0.0:4000`. Change with `set VS4_PORT=...` (PowerShell:
`$env:VS4_PORT=2575`). Every message is printed and saved to `raw/`.

## "Not receiving anything" checklist

Work top to bottom:

1. **Prove the listener works locally** (isolates the network from the code):
   ```powershell
   venv\Scripts\python.exe hl7_listener.py          # terminal 1
   venv\Scripts\python.exe send_test.py             # terminal 2
   ```
   You should see an `ORU^R01` and an `ADT^A01` dumped in terminal 1. If yes,
   the listener is fine and the problem is network/device config below.

2. **Port match** — the VS4's destination port must equal `VS4_PORT` (default
   **4000**; some devices only allow 4000). Confirm what you set on the VS4.
   Note: `cm100` uses 2575, so 4000 keeps the two listeners from clashing.

3. **Destination IP** — the VS4 must point at THIS machine's LAN IP, not
   localhost. Find it with `ipconfig` (IPv4 address). Ping it from the VS4's
   subnet if possible.

4. **Firewall** — allow inbound TCP:
   ```powershell
   New-NetFirewallRule -DisplayName "VS4 HL7 4000" -Direction Inbound -Protocol TCP -LocalPort 4000 -Action Allow
   ```

5. **Same subnet / reachable** — VS4 and this PC must route to each other.

6. **Watch for connects** — even a wrong-format sender shows `CONNECT <ip>` and
   a hex dump here. If you see connects but no HL7, it's a framing/format issue
   (the dumper will still show the raw bytes). If you see *nothing*, it's not
   reaching the machine (steps 2–5).

## Files

| File | Purpose |
|---|---|
| `hl7_listener.py` | MLLP transport + verbose discovery logging (run this) |
| `discovery.py` | Handler that dumps every message and saves to `raw/` |
| `send_test.py` | Sends sample HL7 locally to verify the listener |
| `raw/` | Every received message, one `.hl7` file each |

Once we see real VS4 traffic, we'll add the interpreter (`interpreted/`) like cm100.
