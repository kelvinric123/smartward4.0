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
      BedCard.js               -- doctor view: diagnosis, meds, vitals, infusions
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
