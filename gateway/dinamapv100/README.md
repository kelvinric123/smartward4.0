# DINAMAP V100 serial gateway (discovery stage)

Read-only serial sniffer for the **GE DINAMAP / CARESCAPE V100** vital signs
monitor. It shows every byte that comes out of the monitor's host port (hex +
ASCII) and saves it to `raw/`. It never writes to the port, so it can't start a
BP cycle or change a setting.

The hardware facts below come from the *CARESCAPE V100 Service Manual*
(2048723-002D), section 3 "Installation".

## 1. Hardware: the host port

The rear panel has a **15-pin D (DB15) host communication port**. The pins that matter:

| V100 DB15 pin | Function             | Goes to PC DB9 (USB-serial) |
|---------------|----------------------|-----------------------------|
| 11            | RS232 Transmit (TxD) | pin 2 (RxD)                 |
| 13            | RS232 Receive (RxD)  | pin 3 (TxD)                 |
| 1 or 7        | Common (ground)      | pin 5 (GND)                 |
| 2 / 3         | Inverted TTL Tx / Rx | (not used with an RS-232 adapter) |
| 4             | +5 V out             | do not connect              |
| 8             | Remote alarm         | do not connect              |

There are no handshake lines, so use **no flow control**.

> **Safety:** the host-port signals are **NON-ISOLATED**. If the monitor is on a
> patient, connect it only through GE's isolation (USB Cable Kit
> **2040229-001**, or ILC-1926 isolator **001926** + Host Comm cables
> **683235/683242**). For bench work use no patient and a laptop on battery.

Our adapter shows up as `FT232R USB UART` (FTDI 0403:6001) on **COM3**. If it's
a bare TTL board (TX/RX/GND/VCC pins, no DB9), do **not** wire it to pins 11/13,
because those are ±RS-232 levels.

## 2. Monitor configuration (advanced configuration mode)

1. Turn the monitor **off**.
2. Hold **Menu** and **−** together, then press **On/Off**. The software version
   appears briefly, then `ACF` shows in the Systolic window.
3. Press **Menu** to step through the pages, and use **+ / −** to change a value:

   | Page  | Setting                | Values                                                                 |
   |-------|------------------------|------------------------------------------------------------------------|
   | `rEn` | Remote mode            | 0 disabled, **1 enabled (default)**                                    |
   | `Adr` | Host comm unit address | **32 (default)**, max 126                                              |
   | `br`  | Host comm bit rate     | 0=300, 1=600, 2=1200, 3=2400, 4=4800, **5=9600 (default)**, 6=19200    |
   | `nod` | Host comm mode         | **0 = command mode (default)**, 1 = 1846 compatibility (needs `br`=600) |

4. Power-cycle the monitor. Changes are saved automatically.

What we want: `rEn`=0, `Adr`=32, `br`=5 (9600), `nod`=0. Write down what each page
showed **before** you change anything. Note that an Exergen temperature scanner
on this same port needs 9600.

Why `rEn`=0: GE's DinaWin instructions (2031886-001) require the monitor's
"blood pressure remote operations" to be disabled, so a host can never start or
stop a BP cycle by mistake. Reading values still works with it off.

Configuration mode (just Menu + On/Off for 3 s) covers clinical defaults and the
date/time. The host-comm settings above are only in **advanced** configuration.

## 3. Run the sniffer

```powershell
py -3 -m venv venv                                           # once
venv\Scripts\python.exe -m pip install -r requirements.txt   # once
venv\Scripts\python.exe serial_sniffer.py --list             # find the COM port
venv\Scripts\python.exe serial_sniffer.py                    # COM3 @ 9600 8N1, Ctrl+C to stop
venv\Scripts\python.exe serial_sniffer.py --scan             # 15 s at each V100 bit rate
```

Options: `--port COM5`, `--baud 19200`, `--parity E --bytesize 7`, `--duration 60`
(or the env vars `DINAMAP_PORT` / `DINAMAP_BAUD`). Each burst is printed and
appended to `raw/<time>_<port>_<baud>.bin` (raw bytes) and `.log` (hexdump).
Files are only created once a byte arrives.

## 4. Nothing arrives?

**This is expected.** In the default *host communication command mode*
(`nod`=0) the V100 is a slave. It answers commands from a host (addressed to
`Adr`) and doesn't stream readings on its own. First run on 2026-10-13
(COM3, 9600 8N1 for 25 s, then a scan of all 7 rates): **0 bytes**.

To get data we have to poll it. The command set is in GE's **Host
Communications Reference Manual (GE 2010566)** and the **DINAWIN API**. Neither
is public, so ask GE / the hospital's biomedical engineering team for them.
Once we have the manual, the next step is a poller (`dinamap_poll.py`) that
sends read-only data requests. Commands that *start* an NIBP determination are
excluded, and we test only with no patient on the cuff.

A silent sniffer can't tell "waiting to be polled" apart from "miswired". Check
the wiring anyway: monitor TxD (pin 11) must land on the PC's RxD (DB9 pin 2), and
the grounds must be joined.

## 5. Patient identity (MRN)

The V100 has no way to enter a patient ID. It has buttons and numeric LED
displays only, so its readings carry no MRN. GE's own accessory list solves this
outside the monitor: a "Patient ID Kit" (IR adapter, cable 2024500-001) for a
handheld that already has the patient selected. SmartWard's
`POST /api/v1/vital-signs` needs a `patient_code` (MRN / RN / visit no. / IC),
so the gateway has to supply it. The options are:

- **Barcode scanner on the gateway**: the nurse scans the wristband, then takes
  the reading. The scan expires after one reading or a few minutes.
- **Pick the patient in the nurse app**: the server tells the gateway which
  patient the next reading belongs to.
- **Fixed bed mapping**: monitor → bed → the current occupant. This only works
  if the monitor never leaves that bed.

GE's DinaWin workflow follows the same model: identify the patient on the host,
take the reading, pull it, have the nurse confirm, then reset. Note that the
monitor keeps the last BP and temperature for **90–120 minutes**. A reading has
to be newer than the patient selection, or the previous patient's BP gets filed
under the new one.

## 6. References (there is no open-source V100 driver)

- **GE DinaWin Instructions for Use** (2031886-001 Rev B, 2006):
  <https://archive.org/details/manualzilla-id-6003939>. GE's Windows DLL for the
  PRO 100–400 and ProCare (the V100's predecessor) has four calls:
  `checkReadiness()`, `getState()` (returns XML with SYS/DIA/MAP/SpO2/temp/pulse
  plus time stamps), `getBufferLength()` and `resetMonitor()`. It needs 9600
  baud, scans COM1–COM10 by itself, and hides the low-level protocol.
- **frogcm/dinamap** (<https://github.com/frogcm/dinamap>): a 2009 C# university
  project (CRC Dinamap) built on DinaWin.dll for a ProCare. It polls
  `getState()` every 30 s and stores a reading when the XML changes. It shows
  how to use the DLL; the raw protocol isn't in it. It also commits GE's
  copyrighted `DinaWin.dll`, so for anything real get the DLL from GE.
- **Host Communications Reference Manual (GE 2010566)**: the raw protocol. It's
  only available from GE.

Paths to working readings:

1. **Get manual 2010566 from GE**: write the protocol in Python. This runs on a
   Raspberry Pi like the other gateways and is the preferred path.
2. **DinaWin.dll on a Windows gateway**: call it from 32-bit Python via
   `ctypes` and parse the XML. Quickest, but Windows-only, and we'd need a
   version that supports the V100.
3. **DinaWin.dll as a protocol reference**: put a logging relay between the DLL
   and COM3 (a com0com virtual port pair plus a Python relay), record the bytes
   it sends and receives, then reimplement them in Python.


## 7. DinaWin probe (Windows gateway, chosen path)

[`dinawin.py`](dinawin.py) wraps the four DinaWin calls and parses the
`getState()` XML. [`dinawin_probe.py`](dinawin_probe.py) is the first-contact
test. Both run on Python 3 and on Laragon's **32-bit Python 2.7**. A 2006 DLL
is almost certainly 32-bit, and 32-bit DLLs only load into 32-bit Python. The
probe reads the DLL's header and tells you if the bitness is wrong.

1. Monitor: `rEn`=0, `Adr`=32, `br`=5, `nod`=0 (done 2026-10-13).
2. Stop `serial_sniffer.py` (Ctrl+C). DinaWin opens COM3 itself.
3. Put `DinaWin.dll` next to the script, or pass `--dll`.
4. Run it, then take a BP on the monitor:

```powershell
C:\laragon\bin\python\python2.7\python.exe dinawin_probe.py --watch 5
```

DinaWin only returns values measured **after** the DLL loaded, so the first
`getState()` usually has no results. Each new XML is printed and appended to
`raw/dinawin_<date>.log`. Offline parser check: `--parse saved.xml`.
