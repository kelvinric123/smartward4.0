# rpa_cplus_smartward

Reads C+ (Cerebral HIS, https://cerebral.ihhmy.com) **Bed Management** for SmartWard.
Python + curl with its own cookie jar, no browser. It works the same way as the other C+ RPAs:
SmartOT's `C:\laragon\www\SmartOT\rpa_cplus_smartOT` (login rules) and the production
`C:\laragon\www\rpa_cplus` (`scraper/bed_management.py`, `bed_management.md`).
It is read-only and writes nothing to C+: every call after the login is a GET, except the
Inpatient Daily Census, a report C+ only serves by POST.

## Setup

```bash
py -3.12 -m venv venv
venv\Scripts\python.exe -m pip install -r requirements.txt
copy .env.example .env
```

In `.env`, fill in `HIS_PASSWORD` for `rpa.qmed` and check the facility (`HIS_FACILITY_ID`:
PHAK = 26, PHKL = 33).

> Use the venv interpreter. A bare `python` on this machine is Python 2.7.

## Start

```bash
venv\Scripts\python.exe login.py --check      # free: login form + facility offered; no login
venv\Scripts\python.exe login.py              # the ONE login attempt
venv\Scripts\python.exe bed_management.py     # opens /pm/Reservation/BedManagement, lists the wards
```

`bed_management.py` never logs in. When the session is gone, it stops with exit code 2 and
says so. Exit codes: `login.py` 0 ok, 1 failed, 2 blocked or too soon;
`bed_management.py` 0 ok, 1 error, 2 no session.

## Sync to SmartWard

```bash
venv\Scripts\python.exe sync_smartward.py --check     # SmartWard address, token and hospital only
venv\Scripts\python.exe sync_smartward.py --dry-run   # read C+, save scratch\last_payload.json, send nothing
venv\Scripts\python.exe sync_smartward.py --once      # one sync, then exit
venv\Scripts\python.exe sync_smartward.py             # sync now and every SYNC_INTERVAL_MINUTES (5)
```

```
C+ Bed Management ──curl──▶ sync_smartward.py ──JSON + token──▶ SmartWard POST /api/cplus/bed-sync
 "All Locations" (XXX)       scraper/bed_management.py          App\Services\Cplus\CplusBedImporter
 one GET per unit group                                          wards, beds, patients
```

Each sync reads Bed Management with **All Locations** (location `XXX`, one GET per unit group
in `SYNC_UNIT_GROUPS`, default `2` Ward (IP)). It pushes every ward, bed and patient in one call.

With `SYNC_ER` (on by default), it also reads **ER Management** (`/pm/Reservation/ErManagement`). That
is the same screen with page 2 and unit group 3, and its locations are the ED's zones: Red, Yellow,
Green, Waiting Area, Fever Tent and so on. They come across as wards marked `unit_group` 3, which
SmartWard tags with its Emergency ward type for **Command Center V2 (ED)**. The ER census (unit
group 3) gives each ED patient their arrival time. When a patient leaves an ED zone for a ward,
SmartWard records the end of the ED visit and names the ward they went to, so the ED board can
split departures into admitted and home.

`SMARTWARD_TOKEN` is the API token of a SmartWard **Integration User**. To get one, go to Users →
Add User, pick the role *Integration User* (no password needed), then Generate token on the page
that opens. The token is shown once; SmartWard keeps only its hash. Regenerate or Revoke there,
and deactivating the user also stops it. The C+ wards go to the hospital set in SmartWard's
`CPLUS_SYNC_HOSPITAL_ID`, or to the only active hospital when that is empty.

What SmartWard does with it (C+ owns occupancy in the wards it creates):

| C+ | SmartWard |
|---|---|
| Location, e.g. `699 WARD C4` | Ward `CP26-699`, linked by `wards.cplus_location_id`. Its name is set once; rename it freely |
| Bed, e.g. `12646 217-A`, room `217` | Bed `217-A` (bed id `CP26-12646`), linked by `beds.cplus_bed_id` |
| Grey or yellow bed with an MRN | Patient admitted to that bed, found or created by MRN. RN goes into `rn` and `visit_number` |
| Patient in another bed than last time | Transferred |
| Patient in a linked ward that C+ no longer lists | Discharged, unless C+ listed no patients at all |
| Green bed | Available |
| Red bed (out of service) | Maintenance |
| Physician | Consultant with the same name, if there is one. Unmatched names are counted in the log |
| Admission date | See below. Patients already in a bed, and their C+ admit log entry, are corrected when it changes |
| Isolation box on the admission card | Isolation Precautions, see below |

**Admission date** (`scraper/admissions.py`), taken in this order:
1. **Entry Date** from the Inpatient Daily Census (Bed Management → Reports), the actual admission
   time. It is read for `CENSUS_UNIT_GROUPS` (`2,6`) because a patient in a ward bed can be listed
   under Day Care. C+ only serves this report by POST, and it is the one read-only POST the
   session allows (`REPORTS` in `core/session.py`).
2. **Request Date** of the patient's reservation request (the Reservation Requests panel), for
   anyone the census does not list. The plan dates are read from today back
   `REQUEST_LOOKBACK_DAYS` (7), and only while someone is still missing.
3. Neither: SmartWard dates the admission when the sync first sees the patient.

The Command Center counts admissions by the admit entry's `admitted_at`, so those entries follow
the corrected date.

**Isolation** (`scraper/admission_card.py`, on unless `SYNC_ISOLATION=false`). C+ is SmartWard's
main source of isolation. It keeps it as the **Isolation** box on each patient's admission card
(the bed's menu → Patient Admission Card), which records that the patient is isolated but not the
kind. That is one C+ page per patient, so a card is read again only after
`ISOLATION_REFRESH_MINUTES` (30), and at most `ISOLATION_MAX_READS` (60) cards per sync, with
patients never read first. What was read is kept in `isolation_cache.json` (by MRN and RN, so a
new stay is read afresh; patient data, gitignored). ED patients have no admission card.
In SmartWard:
- Ticked: a patient without an isolation precaution gets **Isolation**, and gets it back if it is
  taken off there. A kind picked in Patient Details (e.g. Airborne) is kept.
- Unticked after being ticked: the C+ Isolation comes off. A kind picked in Patient Details stays.
- Staff can still set isolation in Patient Details for patients C+ has not ticked; the sync leaves it.
- The bed's EKad screen shows it (`isolation_type`).

A card whose Isolation box has neither the cross nor a check icon is left unread, and the first
one is saved as `scratch\admission_card_unread.html`, so a C+ change to the page shows up in
the log instead of flipping patients.

Admissions, transfers and discharges are written to the admission log with source `cplus`.
Prebooked patients and wards not linked to C+ are left alone. Nothing is deleted.

By default `sync_smartward.py` never logs in. It uses the session `login.py` left. When that
session is gone, `--once` exits with code 2 and the loop waits, checking again every interval.
With `--allow-login`, it logs in by itself under the same rules (one attempt, blocked after a
failure, 15 minutes apart). That flag is for the person who runs it unattended, e.g. in Docker.
Exit codes for `--once` / `--dry-run`: 0 done, 1 this sync failed, 2 no C+ session or logins
blocked, 3 settings missing.

Tests for the parser (no network):

```bash
venv\Scripts\python.exe -m unittest discover -s tests -v
```

`probe.py <path>` GETs any C+ page with the session and saves it under `scratch\`. It is for
exploring screens.

## Docker

Build and push `kelvinric/rpa_cplus_smartward:latest` from this folder with
`build and push rpa_cplus_smartward.bat`, or `.\build_and_push.ps1 -TagName 1.0.0` for another tag.
Docker Desktop must be running and logged in. `.dockerignore` keeps `.env`, `rpa.env`, cookie jars,
`his_state.json`, `isolation_cache.json`, `scratch\` and `logs\` out of the image, and the script
refuses to push if any of them got in.

On the server, put `docker-compose.yml` and a filled-in copy of `rpa.env.example` named `rpa.env`
in one folder:

```bash
docker compose pull && docker compose up -d
docker compose logs -f
```

- Settings come from `rpa.env`. The login state, cookie jar, logs and scratch live on the
  `rpa_data` volume (`/data`, via `RPA_DATA_DIR`), so a redeploy keeps the C+ session and any login block.
- The compose file runs the loop with `--allow-login`: the RPA logs in to C+ by itself, under the
  rules above. After a failed login, check the account, then run
  `docker exec rpa_cplus_smartward python login.py --clear-login-block`. To log in only by hand,
  drop `--allow-login` from `command:` and run `docker exec rpa_cplus_smartward python login.py`
  when the session is gone. The loop waits and picks the session up at its next round.
- The container must reach `https://cerebral.ihhmy.com` and `SMARTWARD_URL`.
- Run only one copy of the RPA against a SmartWard.

## Login rules

- **One attempt.** A failed login is never retried. It blocks every later login, even after a
  restart (`his_state.json`), until the account is checked and the block is cleared with
  `login.py --clear-login-block`. The `rpa.qmed` account can be locked.
- **Never change the password.** After login, C+ lands on `/Settings?rp=1` ("C+ Settings"),
  which is a change-password prompt. It is ignored. Nothing here posts to
  `/settings/ChangePassword` or `/settings/ChangeUsername`. In a browser, press **Cancel**.
- **Facility by id.** `rpa.qmed` offers PHAK (26) first and PHKL (33) second. The login picks
  `HIS_FACILITY_ID` and never takes the first option. The jar is bound to account@facility,
  so after a switch the old session is dropped, not reused. Ward ids differ per facility.
- The free checks (login form, facility list) run before the POST, so a doomed login is
  never sent. Logins are at least `HIS_MIN_LOGIN_INTERVAL_MINUTES` (15) apart.
- This RPA has its own jar (`cookies_smartward.txt`). Never share or delete another RPA's jar.
  Concurrent `rpa.qmed` sessions are fine.
- `.env`, `cookies*.txt`, `his_state.json`, `isolation_cache.json`, `scratch\` and `logs\` are
  gitignored. The last three hold patient data.
