# MP5SC v2 — Raspberry Pi deployment

Bare Python + `systemd`. One guided, step-by-step script sets up a fresh cart.

## Quick start

Copy `gateway/mp5sc_v2` to the Pi (e.g. `/home/pi/mp5sc_v2`), then just run:

```bash
cd mp5sc_v2/deploy_pi
chmod +x setup.sh
sudo ./setup.sh
```

`setup.sh` is **interactive**: it asks for each value (server URL, credentials,
monitor IP, interfaces) with sensible defaults, runs **one step at a time**, and
**verifies each step** before continuing. If a step fails you get
**Retry / Continue / Abort**.

Non-interactive (e.g. imaging pipeline):

```bash
sudo ./setup.sh --server http://10.0.0.5:88/api/v1 \
  --api-user api@qmed.asia --api-pass '••••••' --passphrase qmedno1 \
  --monitor-if eth0 --monitor-ip 192.168.0.5 --static-ip 192.168.0.10/24 \
  --uplink-if wlan0 --yes
```

### The 12 steps

1. Check environment (root, Pi detection, base tools)
2. Gather configuration (interactive prompts)
3. Install dependencies → verify each command present
4. Create service user & directories → verify
5. Install application & virtualenv → verify deps import
6. **Verify server connectivity** (DNS + TCP + credentials) — gates naming
7. **Register & get server-assigned name** (see below)
8. Write the env file → verify `GATEWAY_ID`
9. **Configure monitor LAN, never-default** (see below)
10. **Verify routing** — server traffic must not go out the monitor LAN
11. Enable time sync (`fake-hwclock` + `systemd-timesyncd`)
12. Install & start the `systemd` service → verify active

## Server-assigned naming (SD-clone safe)

Identity is **issued by Laravel**, not baked into the image. During step 7 the Pi
sends its **hardware serial** (Pi CPU serial, `machine-id` fallback) to
`POST /api/v1/gateway/register`. The server:

- returns the **same** `gateway_id` for a board it has seen before (idempotent), or
- **assigns the next sequential name** (e.g. `GW-0007`) for a new board.

So cloning an SD card is safe: the clone runs on different hardware and is issued a
**new** name; re-imaging the same board keeps its name. The prefix is configurable
server-side via `VITAL_SIGN_GATEWAY_PREFIX` (default `GW-`).

If a cloned Pi ever runs with a copied `gateway_id` **without** re-running setup,
the server detects the hardware-serial mismatch on the next heartbeat, marks that
cart **critical (identity conflict)**, and tells the Pi to re-register.

## Networking — fixing the Wi-Fi/LAN IP-hopping

The Pi is dual-homed: a private LAN to the monitor (e.g. `192.168.0.0/24`) and an
enterprise Wi-Fi uplink (e.g. `10.x.x.x`). The uplink must own the default route.

- Step 9 pins the monitor NIC to `--static-ip` and sets **`ipv4.never-default yes`**
  + `ipv4.ignore-auto-dns yes` via NetworkManager (the programmatic equivalent of
  toggling *Never use this network for default route* in `sudo nmtui`). Falls back
  to `dhcpcd.conf` `nogateway` if NetworkManager isn't active.
- Step 10 **verifies** it: the default route must not be via the monitor interface,
  and `ip route get <server>` must not resolve out the monitor interface. If it
  does, setup fails the step and tells you how to fix it.
- Configure the **Wi-Fi uplink** itself with `sudo nmtui` (enterprise EAP: identity,
  CA cert, method). Leave it DHCP or static per site. Re-run `./setup.sh` (or just
  step 10 logic via `./preflight.sh`) afterwards to confirm routing.

## What it installs

| Path | Purpose |
|---|---|
| `/opt/mp5sc/` | application code + `venv` |
| `/var/lib/mp5sc/mp5sc.env` | **single source of truth** for config + creds (`chmod 600`) |
| `/var/lib/mp5sc/queue.sqlite` | the store-and-forward outbox |
| `/etc/systemd/system/mp5sc.service` | the service (`Restart=always`, journald) |

Useful commands:

```bash
journalctl -u mp5sc -f            # live logs
sudo systemctl restart mp5sc      # restart
./status.sh                       # human-readable snapshot at the cart
./preflight.sh --register         # re-run self-test + re-send heartbeat
```

## Power & SD-card resilience (software-only, no UPS/RTC)

We stay on power banks, so the OS must survive abrupt power loss:

1. **Read-only root filesystem.** After a good run, enable it:
   `sudo raspi-config` → *Performance* → *Overlay File System*. The OS then
   survives power cuts uncorrupted. Toggle off (or re-run `setup.sh`) to update.
2. **Writable data only** in `/var/lib/mp5sc` (or a dedicated `/data` partition via
   `--data-dir`). SQLite runs WAL + `synchronous=FULL`, so committed readings
   survive a cut.
3. **Clock:** `fake-hwclock` + `systemd-timesyncd`; clinical `measured_at` comes
   from the monitor, not the Pi.
4. **Power telemetry:** the heartbeat reports `vcgencmd get_throttled`, so a failing
   power bank surfaces on the server as *undervoltage* before it kills the Pi.

> Enable the overlay FS **after** confirming the service runs and preflight passes,
> while the rootfs is still writable.
