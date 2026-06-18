# Doctor (Consultant) Dashboard — Expo App

React Native (Expo SDK 56) app for QMed Smart Ward consultants. The login
screen is a mock — any credentials log in — and all data lives in
`src/data/mockData.js`. Swap that file (and `src/api/endpoints.js`) for real
fetches when the Laravel backend is ready.

## Project layout

```
doctor_app/
  App.js                       -- Login -> Dashboard router (useState)
  app.json
  index.js
  src/
    theme.js                   -- colors / radius / spacing tokens
    api/endpoints.js           -- API client. Currently returns mock data.
                                  Documents the planned Laravel endpoints.
    data/mockData.js           -- replace with API calls later
    components/
      Pill.js
      StatCard.js
      BedCard.js               -- doctor view: diagnosis, meds, vitals, infusions
    screens/
      LoginScreen.js           -- mock consultant login
      DoctorDashboard.js       -- ward filter + bed queue + bed detail
```

## What the app shows

- **Login**: clearly states "QMed Smart Ward · Consultant Mobile Access" and
  "Login as Consultant". Includes a yellow "DEMO BUILD" note so showcase
  viewers know it's mock.
- **Dashboard**: shows total beds under the consultant's care, ward count,
  critical patients, pending reviews / orders / discharges. Beds can be
  filtered by ward, then paged through one-at-a-time with the bottom
  Prev/Next bar.

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

## Wiring real data later

The API client in `src/api/endpoints.js` already documents the Laravel
endpoints we plan to hit:

```
POST  /auth/consultant/login
GET   /consultants/{doctorId}/dashboard
GET   /consultants/{doctorId}/wards
GET   /consultants/{doctorId}/wards/{wardId}/beds
GET   /beds/{bedId}
POST  /auth/logout
```

To go live:

1. Set `BASE_URL` in `src/api/endpoints.js` to the real Laravel host.
2. Replace each function body with a `fetch` call. Keep the return shape
   identical to the mock data — the screens won't need to change.
3. Store the auth token (e.g. via `expo-secure-store`) and pass it as
   `Authorization: Bearer <token>` on subsequent requests.
