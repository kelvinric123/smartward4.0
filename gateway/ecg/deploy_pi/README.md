# ECG gateway — Raspberry Pi deployment

Bare Python + `systemd`. One guided, step-by-step script sets up a fresh gateway.
Same installer flow as `mp5sc_v2/deploy_pi`; only the listener direction differs
(the TC35 ECG uploads ECG files to the Pi, so setup also verifies the listen port).

## Quick start

Copy `gateway/ecg` to the Pi (e.g. `/home/pi/ecg`), then just run:

```bash
cd ecg/deploy_pi
chmod +x setup.sh
sudo ./setup.sh
```

`setup.sh` is **interactive**: it asks for each value (server URL, credentials,
listen port, interfaces) with sensible defaults, runs **one step at a time**, and
**verifies each step** before continuing. If a step fails you get
**Retry / Continue / Abort**.

Non-interactive (e.g. imaging pipeline):

```bash
sudo ./setup.sh --server http://10.0.0.5:88/api/v1 \
  --api-user api@qmed.asia --api-pass '••••••' --passphrase qmedno1 \
  --monitor-if eth0 --static-ip 192.168.0.10/24 --listen-port 3050 \
  --uplink-if wlan0 --yes
```

### The 14 steps

1. Check environment (root, Pi detection, base tools)
2. Gather configuration (interactive prompts)
3. Install dependencies (auto-detects offline) → verify each present
4. Create service user & directories → verify
5. Install application & virtualenv → verify deps import
6. **Verify server connectivity** (DNS + TCP + credentials) — gates naming
7. **Register & get server-assigned name + ward + hostname**
8. Write the env file → verify `GATEWAY_ID`
9. **Apply server hostname & enable SSH**
10. **Configure TC35 ECG LAN, never-default** — the static IP is what you program
    into the TC35 ECG's upload URL, so it must never hop
11. **Verify routing** — server traffic must not go out the monitor LAN
12. Enable time sync (`fake-hwclock` + `systemd-timesyncd`)
13. Install & start the `systemd` service → verify active
14. **Verify the ECG listener port** is accepting TCP → then configure the TC35 ECG

After step 14, configure the TC35: URL `http://<static-ip>:3050/`, method POST, Basic auth
(credentials chosen in step 2), then send a test ECG, and watch it arrive:

```bash
journalctl -u ecg-gateway -f
```

## Offline installs

Identical to mp5sc_v2: step 3 auto-detects a lack of apt connectivity (or pass
`--offline`) and **verifies** the required dependencies instead of installing
them — so they must already be in the Pi image:

```bash
sudo apt install -y python3 python3-venv python3-pip sqlite3 curl \
     network-manager fake-hwclock systemd-timesyncd \
     python3-requests python3-dotenv libraspberrypi-bin
```

Or drop matching-arch wheels into `deploy_pi/wheels/` and setup installs them
with `pip --no-index`.

## Server-assigned naming (SD-clone safe)

Identity is **issued by Laravel**, not baked into the image — same mechanism as
mp5sc_v2. Step 7 registers with `gateway_type=ecg` (server assigns an `ECG-` name) and sends the hardware serial to `POST /api/v1/gateway/register`;
the server returns the same `gateway_id` for a known board or assigns the next
sequential name for a new one. A cloned SD on new hardware gets a new name; the
heartbeat detects serial mismatches and flags identity conflicts.

## Networking

The Pi is dual-homed: a private LAN to the TC35 ECG (e.g. `192.168.0.0/24`) and the
Wi-Fi uplink to SmartWard. The uplink must own the default route.

- Step 10 pins the TC35 ECG-side NIC to `--static-ip` with `ipv4.never-default yes`
  (dhcpcd `nogateway` fallback). **This static address is the HL7 destination
  configured inside the TC35 ECG** — if it hops, readings stop arriving.
- Step 11 verifies no server traffic rides the monitor LAN.
- Configure the Wi-Fi uplink itself with `sudo nmtui`.

## What it installs

| Path | Purpose |
|---|---|
| `/opt/ecg-gateway/` | application code + `venv` |
| `/var/lib/ecg-gateway/ecg-gateway.env` | **single source of truth** for config + creds (`chmod 600`) |
| `/var/lib/ecg-gateway/queue.sqlite` | the store-and-forward outbox |
| `/etc/systemd/system/ecg-gateway.service` | the service (`Restart=always`, journald) |
| `/etc/systemd/system/ecg-gateway-netwatch.service` | network self-healing watchdog |

Useful commands:

```bash
journalctl -u ecg-gateway -f              # live logs
sudo systemctl restart ecg-gateway        # restart
./status.sh                       # human-readable snapshot at the gateway
./preflight.sh --register         # re-run self-test + re-send heartbeat
```

## Power & SD-card resilience

Same policy as mp5sc_v2: enable the overlay read-only rootfs once stable
(`sudo raspi-config` → Performance → Overlay File System); writable data only in
`/var/lib/ecg-gateway`; SQLite runs WAL + `synchronous=FULL` so ACKed readings survive a
power cut; `fake-hwclock` + `systemd-timesyncd` for the clock; the heartbeat
reports `vcgencmd get_throttled` so a failing power source surfaces server-side
as undervoltage before it kills the Pi.
