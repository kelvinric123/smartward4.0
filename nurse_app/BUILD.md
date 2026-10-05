# Nurse Dashboard — Expo App

A React Native (Expo SDK 56) port of `/nurses/{id}/dashboard`, talking to the
QMed Smart Ward server (`NurseAppApiController`, `NurseAppPatientController`,
`NurseAppRosterController`). "Demo Login" runs on sample data
(`src/data/mock*.js`) with no server.

## What a nurse can do (v1.3)

Dashboard: assigned beds with what is waiting on each (open orders, overdue
doses, I/O flags, unanswered calls, lab results to review, assessments
overdue) and each bed's workload score. A **My roster** card shows today's and
tomorrow's shift and anything waiting (a swap to answer, a week to
acknowledge), and **My load** shows this shift's workload against the team
average. "Open patient chart" on a bed opens the patient's chart, the mobile
twin of the ward dashboard's Patient Details:

| Tab | Shows | Actions |
|-----|-------|---------|
| Overview | What needs attention (most pressing first, including SpO₂ off target, critical or overdue lab results and assessments due), latest vitals + EWS, allergies with severity, diet, isolation, fall risk; VIP, payor / GL status, COE indicators, expected discharge and how far off it is | Each item opens its tab |
| Orders | Consultant orders open / closed, the shift on now and next | Done, cancel (reason), hand over to next shift, write down a new order |
| I/O | The I/O chart for a chart day, totals, intake limit, urine target, alerts, last days | Record intake / output, strike out an entry, set the fluid plan, fluid overload check |
| Meds | Medication orders, overdue / due soon / PRN, recent doses | Record a dose: given, held or refused (with reason) |
| Oxygen | The oxygen now (device, flow / FiO₂, since when), the SpO₂ target and the latest SpO₂ against it, every change this admission | Change the oxygen or to room air (quick picks per device, SpO₂ target, timed up to 4 h back), strike out a change made in error |
| Labs | HIS lab orders and results: priority, ordering doctor, flagged values (critical / abnormal), comments, and when to review each result. Shown only while the ward has Lab Investigations switched on | Mark a result reviewed |
| Infusion | Current infusions (live from the infusion engine when in use), alarms, progress | View only |
| Transfusion | Units by stage, Pre-start › Running › Finished, with counts: the 4 bedside checks, live timing (predicted end, 4 hour limit), completed and stopped units (latest finished first). Each unit's flagged problems sit on its own card | Register a unit (scan or type the unit number and crossmatch reference), the 4 checks in order (undo the last), start, complete or stop with a reason. The tab follows the unit to its new stage |
| Assess | The ward's assessment scales (Morse, Braden, pain, C-SSRS, ...): latest score and band, earlier scores, when the next one is due | Score scales item by item (live total and band) or by a total; the C-SSRS and monitor readings are recorded on the ward dashboard |
| Alerts | Unanswered patient calls, EWS and infusion alerts | Mark answered |
| Nursing plan | **This shift**: what is due before the shift ends, from the chart (doses, orders, assessments, HGT, I/O, blood units, infusions, trips out, care plan evaluations, vitals). **Care plan**: nursing diagnoses with goal and interventions, suggestions from the record (fall risk, fluid plan, isolation, oxygen, insulin, blood unit, ...) | A task opens the tab that deals with it. Evaluate each diagnosis once a shift (met / partly met / not met, with a note if not met), edit, resolve or discontinue (with a reason), add from the library or in your own words. Same plan as the ward dashboard's Nursing Plan tab |

**My roster** (from the dashboard's My roster card): this week and next from
the AI Nurse Schedule, day by day: shift and times, beds, special duties (Team
Leader, ...), leave and public holidays.

- Acknowledge a week once seen; a later change asks for it again. The nurse
  manager sees who has seen their week on the AI Nurse Schedule's roster.
- Request leave (type and days on a calendar) for the nurse manager to approve.
- Swap a shift that has not started: pick a colleague (their shift that day is
  shown); they accept or decline in their app, then the nurse manager approves.
  A colleague who is off that day can take the shift (give-away).
- Approving happens on the ward dashboard: AI Nurse Schedule › Leave &
  holidays › Requests from the nurse app. An approved swap moves both nurses'
  roster entries and their beds that day.

**Team** (from the My load card): who is on the ward this shift and next, each
nurse's beds and patients, workload (heavy / even / light against the shift
average) and special duties, team leader first.

Actions are recorded as the nurse's linked SmartWard user (Nurses → Edit →
LDAP Binding). A nurse with no linked user gets an app-only, deactivated
account named after them on their first action, so their name shows on the
ward dashboard like any other entry.

## Preview in a browser (no phone needed)

```powershell
cd C:\laragon\www\smartward4\nurse_app
npm install
npm install --no-save react-dom@19.2.3 react-native-web@~0.21.0 @expo/metro-runtime@~56.0.15
npx expo start --web
```

Use "Demo Login", or set the API path in Settings to your server (the web
build needs the server to allow cross-origin requests to `/api/nurse/*`).

Note: `npx expo install <package>` (or any `npm install <package>`) removes
the three `--no-save` preview packages again. Re-run the second line above
afterwards.

## Scanning blood unit and crossmatch barcodes (v1.2)

"Register a unit" has a **Scan** button beside UNIT NUMBER and CROSSMATCH
REFERENCE (expo-camera). What is read:

- **Unit number**: the ISBT 128 donation number barcode at the top left of
  the bag label (`=` + 13 characters + 2 flag characters) is saved as the
  13-character unit number. The bag's other barcodes (blood group, product
  code, expiry, ...) are named and refused, so they cannot land in the field.
  Other labels (a hospital's own barcode, Codabar on older bags) are taken as
  printed.
- **Crossmatch reference**: any barcode on the crossmatch or compatibility
  label; the bag's own barcodes are refused here.

The value is shown in the field with "Scanned" beside it, to check against
the label before registering. "Type it instead" closes the camera.

The camera is a native module, so **v1.2 needs a new APK**:
`apk_create.bat clean` (regenerates `android/` with the camera permission).
Only the camera permission is added, no microphone. Expo Go already includes
the camera. In a browser the camera works on `localhost` or https only.

## Project layout

```
nurse_app/
  App.js
  app.json
  index.js
  src/
    theme.js                 -- colors / spacing tokens
    config.js                -- server address (Settings on the login screen)
    api/endpoints.js         -- login, dashboard
    api/patient.js           -- patient chart (live server or demo)
    api/schedule.js          -- my roster, requests, team (live server or demo)
    data/mockData.js         -- demo login: nurse, beds
    data/mockPatient.js      -- demo login: charts, answering like the server
    data/mockClinical.js     -- demo login: labs, oxygen, assessment scales
    data/mockNursingPlan.js  -- demo login: care plan and this shift's tasks
    data/mockSchedule.js     -- demo login: roster, requests, team, workload
    data/demoTime.js         -- demo login: time, volume and shift helpers
    utils/bloodLabels.js     -- reading scanned blood bag / crossmatch barcodes
    components/
      StatCard.js, Pill.js, BedCard.js
      ui.js                  -- chips, buttons, cards, banners
      Sheet.js               -- bottom sheet for the chart's forms
      BarcodeScanner.js      -- full-screen camera scanner (expo-camera)
      DateRangePicker.js     -- month calendar for leave days (no native picker)
      patient/               -- one file per chart tab
    screens/
      LoginScreen.js
      NurseDashboard.js
      PatientScreen.js       -- the patient chart
      RosterScreen.js        -- my roster, leave and swap requests
      TeamScreen.js          -- the ward team this shift and next
```

## Run on a device with Expo Go (fastest dev loop)

```powershell
cd C:\laragon\www\smartward4\nurse_app
npx expo start
```

Scan the QR with Expo Go on the phone. Phone and PC must share Wi-Fi.

## Build a standalone APK (no Expo Go, no network needed at runtime)

You have:
- JDK 17 at `C:\Program Files\Java\jdk-17.0.19`
  (your `JAVA_HOME` currently points at `C:\Program Files\Java\jdk-17` which
  does NOT exist — fix that first, see below).
- An Android SDK. `apk_create.bat` uses `ANDROID_HOME`, else `ANDROID_SDK_ROOT`,
  else the first of `%LOCALAPPDATA%\Android\Sdk`, `C:\Android\Sdk` and
  `%USERPROFILE%\Android\Sdk` that has `platform-tools\adb.exe` (one PC has it
  at `C:\Users\krich\AppData\Local\Android\Sdk`, another at `C:\Android\Sdk`).
  The build needs platform 36, build-tools 35 or 36, NDK 27.1.12297006 and
  CMake 3.22.1.

### 1. Fix JAVA_HOME

**Quick fix — current PowerShell session only** (use this if you just want to
build right now):

```powershell
$env:JAVA_HOME = "C:\Program Files\Java\jdk-17.0.19"
$env:Path     = "$env:JAVA_HOME\bin;$env:Path"
& "$env:JAVA_HOME\bin\java.exe" -version    # expect 17.0.19
```

**Permanent fix — survives reboots** (run once in an Admin PowerShell, then
reopen all terminals):

```powershell
setx JAVA_HOME "C:\Program Files\Java\jdk-17.0.19" /M
```

### 2. Generate the native Android project

From `nurse_app/`:

```powershell
npx expo prebuild --platform android --clean
```

This creates an `android/` folder with a Gradle project.

### 3. Build a release APK

**One-shot copy-paste — run from `nurse_app\` in a single PowerShell window**
(sets JAVA_HOME for this session and builds):

```powershell
$env:JAVA_HOME = "C:\Program Files\Java\jdk-17.0.19"
$env:Path      = "$env:JAVA_HOME\bin;$env:Path"
cd android
.\gradlew.bat assembleRelease
```

If you already ran the permanent `setx` above and reopened the terminal, just:

```powershell
cd android
.\gradlew.bat assembleRelease
```

The first run downloads Gradle + dependencies — 5–15 minutes on first build.

The APK lands at:

```
nurse_app\android\app\build\outputs\apk\release\app-release.apk
```

> The default release build is signed with Expo's debug keystore (good enough
> for sideloading to your own phone). If Play Store ever wants this, generate a
> proper upload key.

### 4. Transfer to phone

**Option A — USB cable + adb (recommended):**

1. Enable Developer Options on the phone (tap Build number 7x in Settings >
   About phone), then enable USB debugging.
2. Plug phone in, accept the RSA prompt.
3. From `nurse_app/android/`:

   ```powershell
   & "$env:ANDROID_HOME\platform-tools\adb.exe" install -r app\build\outputs\apk\release\app-release.apk
   ```

**Option B — copy the APK:**

Copy `app-release.apk` to the phone via cable / cloud / email, then tap to
install. The phone will prompt to "Allow install from this source" the first
time.

## API

See the header comments of `src/api/endpoints.js` (login, dashboard),
`src/api/patient.js` (the patient chart) and `src/api/schedule.js` (roster,
requests, team). Every chart action answers with the
whole refreshed chart, which the screen simply redraws from; times are sent
as "minutes ago" so a phone on the wrong time zone cannot misfile an entry.
