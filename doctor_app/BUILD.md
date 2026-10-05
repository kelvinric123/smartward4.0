# Doctor (Consultant) Dashboard — Expo App

React Native (Expo SDK 56) app for QMed Smart Ward consultants, fully
integrated with the Laravel backend (`DoctorAppApiController`, routes under
`/api/doctor/*`).

## Server integration

- **Consultant credentials** are configured on the SmartWard Consultant edit
  page (`/consultants/{id}/edit` → "Doctor App Login" section: App Username +
  App Password). A consultant can only log in once both are set.
- **API path**: defaults to `http://192.168.0.88:18080`. Change it from the
  login screen → gear icon → config password `1324` → API Path (has a
  "Test Connection" button; persisted with AsyncStorage).
- **Auth**: login returns a bearer token (72 h expiry); all requests send
  `Authorization: Bearer <token>`. The backend scopes every response to the
  patients under that consultant's care (primary consultant, ADT care
  provider link, or bed_consultant assignment).

Endpoints used:

```
POST  /api/doctor/login                      { username, password }
GET   /api/doctor/dashboard                  doctor + summary + wards + beds
GET   /api/doctor/patients/{id}/notes
POST  /api/doctor/patients/{id}/notes        { text }
GET   /api/doctor/patients/{id}/chart        I/O, meds, orders, oxygen, labs (?io_day=Y-m-d)
POST  /api/doctor/patients/{id}/orders       { instruction, urgency, fluid_limit_ml?, urine_min_ml_per_hour? }
POST  /api/doctor/patients/{id}/orders/{order}/cancel   { reason }
POST  /api/doctor/patients/{id}/labs/{lab}/review       marks a lab result reviewed
POST  /api/doctor/logout
POST  /api/doctor/ping                       connectivity test
```

## Project layout

```
doctor_app/
  App.js                       -- Login -> Dashboard router (useState)
  app.json
  index.js
  src/
    theme.js                   -- colors / radius / spacing tokens
    config.js                  -- API base URL store (AsyncStorage) + config password
    api/endpoints.js           -- API client (fetch + bearer token)
    data/notesStore.js         -- consultant notes cache, backed by the API
    components/
      Pill.js
      StatCard.js
      BedCard.js               -- doctor view: allergies, VIP, chart buttons, vitals, I/O, meds, infusions
      chart/
        PatientChartModal.js   -- full-screen chart: I/O · Meds · Orders · O₂ · Labs tabs
        IoTab.js, MedsTab.js, OrdersTab.js
        OxygenTab.js           -- oxygen now, SpO₂ against target, progression charts, history
        LabsTab.js             -- lab results (flags, review due), "Mark reviewed"
    screens/
      LoginScreen.js           -- consultant login + settings dialog (API path)
      DoctorDashboard.js       -- ward filter + bed queue + bed detail (auto-refresh 60s)
```

## What the app shows

- **Login**: authenticates against the SmartWard server using the consultant's
  configured app credentials. Gear icon opens the connection settings
  (config password `1324`).
- **Dashboard**: shows total beds under the consultant's care, ward count,
  critical patients (EWS >= 5) and pending discharges, live from the ward
  system. Beds can be filtered by ward, then paged through one-at-a-time with
  the bottom Prev/Next bar. Pull down to refresh; data also auto-refreshes
  every 60 seconds.
- **Consultant notes** are persisted per patient on the server and visible to
  the whole care team.
- **Bed card**: VIP / VVIP badge, active allergies with their severity
  (severity is optional, as ADT may not send it), a flag for lab results
  waiting for review, and chart buttons for I/O, medications, orders, oxygen
  and labs. The REVIEWS stat counts lab results waiting for review.
- **Patient chart** (tap a chart button):
  - **O₂**: the oxygen now (changed on the ward's Oxygen Therapy tab or
    recorded with the vital signs), the SpO₂ target, the latest SpO₂ against
    it, charts of SpO₂ and oxygen given over the admission, and every change.
    Read-only: "Order an oxygen change" opens a consultant order for the ward.
  - **Labs**: lab investigations from the HIS (Patient Details > Lab
    Investigations): results with their flags, when each is due for review,
    and **Mark reviewed** (recorded under the consultant's name).
- **Demo Login** shows all of this with sample data and no server, and
  behaves like the server: every sample time is moved to "now"
  (`src/data/demoClock.js`), the O₂ tab's SpO₂ comes from the bed's own vital
  signs, and the dashboard is worked out from the demo charts
  (`src/data/demoDashboard.js`), so an order written or a result reviewed in
  a chart shows on the bed card and in the stats at the next refresh.

## Run on a device with Expo Go (fastest dev loop)

```powershell
cd C:\laragon\www\smartward4\doctor_app
npm install
npx expo start
```

Scan the QR with Expo Go on the phone. Phone and PC must share Wi-Fi.

## Build a standalone APK (for showcase, no Expo Go needed)

Same toolchain as `nurse_app` — JDK 17 and the Android SDK.

### 1. Fix JAVA_HOME (only if not already done)

JDK 17 on this machine is installed under Microsoft, not Java:

```powershell
$env:JAVA_HOME = "C:\Program Files\Microsoft\jdk-17.0.19.10-hotspot"
$env:Path      = "$env:JAVA_HOME\bin;$env:Path"
& "$env:JAVA_HOME\bin\java.exe" -version    # expect 17.0.19
```

Permanent (run once in Admin PowerShell, then reopen terminals):

```powershell
setx JAVA_HOME "C:\Program Files\Microsoft\jdk-17.0.19.10-hotspot" /M
```

### 2. Install deps + generate native android project

From `doctor_app/`:

```powershell
npm install
npx expo prebuild --platform android --clean
```

### 3. Build a release APK

```powershell
$env:JAVA_HOME = "C:\Program Files\Microsoft\jdk-17.0.19.10-hotspot"
$env:Path      = "$env:JAVA_HOME\bin;$env:Path"
cd android
.\gradlew.bat assembleRelease
```

The APK lands at:

```
doctor_app\android\app\build\outputs\apk\release\app-release.apk
```

### 4. Install on phone

```powershell
& "$env:ANDROID_HOME\platform-tools\adb.exe" install -r app\build\outputs\apk\release\app-release.apk
```

Or copy the APK to the phone and tap to install.

## Android cleartext HTTP note

The default server URL uses plain `http://`. Expo prebuild enables
`usesCleartextTraffic` for debug builds automatically; for **release** builds
`app.json` sets `expo.android.usesCleartextTraffic: true` so the app can talk
to the LAN server without HTTPS. If the server later moves behind TLS, switch
the API path to `https://...` in the app settings.
