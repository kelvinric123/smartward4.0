# ECG USB gateway — Raspberry Pi Zero 2W deployment

The Pi plugs into the TC35's USB port and acts as a USB stick. The TC35 saves
ECGs to it, and the Pi forwards them to SmartWard over Wi-Fi.

## Hardware

| Item | Notes |
|---|---|
| Raspberry Pi **Zero 2W** | The OTG port is what lets it act as a USB stick. A Pi 3/4 USB-A port cannot. |
| microSD ≥ 16 GB | Holds the OS, the 4 GB virtual stick, and the outbox |
| micro-USB → USB-A **data** cable | From the Pi's port marked **USB** (the inner one, *not* PWR IN) to the TC35's USB-A socket. Charge-only cables do not work. |
| 5 V 2.5 A supply (recommended) | Into **PWR IN**. Keeps the Pi up when the TC35 is off or sleeping. |

Power: the Zero 2W *can* run from the TC35's USB port alone (one cable). It then
reboots whenever the TC35 turns off, and Wi-Fi peaks can brown out a weak port.
Prefer the separate supply. Both micro-USB sockets share the Pi's 5 V rail, so
with a separate supply use a USB **power-blocker** adapter (or a cable with the
5 V wire cut) on the TC35 side. Otherwise the Pi's supply back-feeds the TC35's
USB port. The heartbeat reports under-voltage on the dashboard either way.

## 1. Flash the SD card

Raspberry Pi Imager → **Raspberry Pi OS Lite (64-bit)**. In the settings (⚙):

- hostname (any; setup replaces it with the server-assigned name), user + password
- **Wi-Fi SSID / password / country** (the hospital Wi-Fi that reaches SmartWard)
- **Enable SSH**

Then either let the Pi set itself up on first boot (no SSH), or boot it, SSH in, and run setup yourself (step 2).

## 2a. No SSH: prepare the card on the PC (recommended)

With the freshly flashed card still in the PC, Windows shows its boot partition (`bootfs`).
If Windows offers to format the card's *other* partition, click **Cancel**.

1. Copy this whole folder to the card as `bootfs\ecg2_sdcard`.
2. Copy `setup.conf.example` to `bootfs\ecg2_sdcard\setup.conf` and fill it in with Notepad: server URL, the API user/password from `/vital-sign-integration` → *API Users*, and the ward id.
3. Append `autosetup/cloud-init-autosetup.yaml` to `bootfs\user-data` (the file Imager wrote). If the card has booted before, also change `instance-id` in `bootfs\meta-data` (e.g. add `-ecgusb1`), or cloud-init won't run the addition.
4. Put the card in the Pi and power it from **PWR IN**.

The Pi waits for Imager's first-boot work, runs `setup.sh` unattended (retrying every 3 minutes until the Wi-Fi and server are reachable), reboots once, and then appears on `/vital-sign-integration` → *Qmed Gateways* as an **ECG** gateway in its ward, with the API user bound and its IP shown. The whole sequence takes about 5–10 minutes.

How it went is always on the card. Put it back in a PC and open:

- `ecg2_sdcard\SETUP_RESULT.txt`: `WAITING` / `RUNNING` / `FAILED` (with the end of the log) / `DONE`, plus IP and Wi-Fi;
- `ecg2_sdcard\SETUP_LOG.txt`: the full `setup.sh` output.

After a successful setup the Pi blanks `API_PASS` in `setup.conf`; the password then exists only in the root-only `/var/lib/ecg-usb/ecg-usb.env`.

## 2b. With SSH: copy this folder and run setup

From the PC (repo root):

```bash
scp -r gateway/ecg2_sdcard <user>@<pi-ip>:~/
```

On the Pi:

```bash
cd ~/ecg2_sdcard/deploy_pi
chmod +x *.sh
sudo ./setup.sh
```

`setup.sh` is **interactive**. It asks for each value with sensible defaults,
runs one step at a time, and verifies each step (Retry / Continue / Abort).
It ends by offering the one **reboot** needed to switch the USB port into
device mode.

Non-interactive:

```bash
sudo ./setup.sh --server http://10.0.0.5:88/api/v1 \
  --api-user api@qmed.asia --api-pass '••••••' --passphrase qmedno1 \
  --usb-size-mb 4096 --yes
```

### The 14 steps

1. Check environment (root, Pi model)
2. Gather configuration (server, credentials, Wi-Fi interface, stick size)
3. Install dependencies (auto-detects offline) → verify each present
4. Create the data directory (`/var/lib/ecg-usb`, root-only)
5. Install the application & virtualenv (`/opt/ecg-usb`) → verify deps import
6. **Verify server connectivity** (route, DNS, TCP, credentials)
7. **Register & get server-assigned name + ward + hostname** (`gateway_type=ecg`)
8. Write the env file → verify `GATEWAY_ID`
9. Apply the server hostname & enable SSH
10. **Enable USB gadget mode**: `dtoverlay=dwc2,dr_mode=peripheral` in `config.txt`, plus `dwc2`/`libcomposite` modules
11. **Create the virtual USB drive** (FAT32 image, label `SMARTWARD`). An existing image is never recreated, because it may hold ECGs.
12. Enable time sync
13. Install & start the services
14. **Verify the drive is presented on USB**

After the reboot, check:

```bash
sudo /opt/ecg-usb/preflight.sh
```

## 3. Test with a laptop first (no TC35 needed)

1. Plug the Pi's **USB** port into a laptop. A drive called **SMARTWARD** appears.
2. Put a sample ECG on it, e.g. on Windows with the drive as `E:`:
   ```powershell
   python gateway\ecg2_sdcard\make_sample_ecg.py E:\ 55555
   ```
   (or copy a real TC35 XML file onto the drive)
3. **Eject** the drive in the tray icon. Windows may hold writes back until then.
4. On the Pi: `journalctl -u ecg-usb-gateway -f` shows
   `ECG found on USB drive: TC35_55555_….xml patient=55555 … [queued]` and then `Sent ECG …`.
   The ECG appears in SmartWard for the patient with MRN/RN 55555.

## 4. Connect the TC35

Plug the Pi's **USB** port into the TC35. Save/export an ECG to USB on the TC35
the way the ward normally would. Then:

```bash
sudo /opt/ecg-usb/usb_drive.sh ls     # what the TC35 wrote (read-only, safe while plugged in)
journalctl -u ecg-usb-gateway -f      # live capture + send log
```

If the TC35 does not see the stick, set `USB_STALL=0` in
`/var/lib/ecg-usb/ecg-usb.env`, then run `sudo systemctl restart ecg-usb-gadget`.

## What it installs

| Path | Purpose |
|---|---|
| `/opt/ecg-usb/` | application code (runs on the system `python3`) and the helper scripts below |
| `/var/lib/ecg-usb/ecg-usb.env` | **single source of truth** for config + creds (`chmod 600`) |
| `/var/lib/ecg-usb/usb_drive.img` | the USB stick the TC35 writes to |
| `/var/lib/ecg-usb/queue.sqlite` | outbox + `usb_files` (what was captured from the drive) |
| `/var/lib/ecg-usb/files/` | captured XML + PDF, kept until sent + 7 days |
| `ecg-usb-gadget.service` | presents the image on USB at boot ("plugs the stick in") |
| `ecg-usb-gateway.service` | watcher + sender + heartbeat (root: it loop-mounts the image) |
| `ecg-usb-netwatch.service` | Wi-Fi self-healing watchdog |

Useful commands:

```bash
journalctl -u ecg-usb-gateway -f                 # live logs
sudo /opt/ecg-usb/status.sh                      # snapshot: USB state, queue, last ECGs, power, Wi-Fi
sudo /opt/ecg-usb/usb_gadget.sh status           # is the drive presented / is the TC35 connected
sudo /opt/ecg-usb/usb_drive.sh ls                # files on the stick
sudo /opt/ecg-usb/preflight.sh --register        # self-test + send a heartbeat
sudo systemctl restart ecg-usb-gateway
```

## Golden image / cloning

Identity comes from the server, keyed to the board serial, as on the other
gateways. A cloned card on a new board gets a new name when `setup.sh` runs.
Clone **before** running `setup.sh` (an OS card with the apt packages installed
and this folder copied). Otherwise the clone also inherits the master's drive
image, queue, and captured files.

No internet is needed. The gateway uses only Python's standard library, and
everything setup requires (`python3 curl mkfs.vfat sfdisk losetup blkid`) is
part of Raspberry Pi OS Lite. When apt is reachable, setup also adds the
optional extras (`sqlite3`, `fake-hwclock`, `vcgencmd`).

## Power & SD-card resilience

- SQLite runs WAL + `synchronous=FULL`, and captured files are fsynced before they are queued, so a captured ECG survives a power cut.
- The TC35's own copy stays on the stick until the server has acknowledged it.
- **Do not enable the read-only overlay filesystem** (raspi-config → Overlay FS) on this gateway. The 4 GB drive image and the queue must persist on the SD card, and the Zero 2W has only 512 MB of RAM for an overlay.

## Updating an installed Pi

`deploy_pi/update.sh` swaps in the new code, restarts only `ecg-usb-gateway`,
and rolls back if it doesn't start. The drive stays plugged in to the TC35, and
the config, queue, and captured files are untouched. Two ways to run it:

**With the SD card (no SSH).** On a Pi set up through `autosetup`, the timer
keeps checking the card. Power the Pi off and put its card in a PC. Replace
everything in `bootfs\ecg2_sdcard` except `setup.conf` and `SETUP_*.txt`.
Put the card back. Within a few minutes of booting, `SETUP_RESULT.txt` on
the card says `UPDATED` (or `UPDATE FAILED`, which leaves the old code
running). Each card version is applied once.

*Pis set up before the timer stayed on* (the first ECG-0027 setup) need it
re-enabled once. Also change `instance-id` in `bootfs\meta-data` (e.g.
`-ecgusb1` → `-ecgusb2`), so cloud-init runs the `user-data` addition again.

**Over SSH**, from a PC that reaches the Pi:

```bash
scp -r gateway/ecg2_sdcard <user>@<pi-ip>:~/
ssh -t <user>@<pi-ip> "sudo bash ~/ecg2_sdcard/deploy_pi/update.sh && rm -rf ~/ecg2_sdcard"
```
