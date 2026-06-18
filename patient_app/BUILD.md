# Patient App — Expo

A React Native (Expo SDK 56) inpatient companion app. Mirrors the framework of
`nurse_app/` — same Expo version, same JDK 17 Android toolchain — so you can
build either app with the same workstation setup.

All data is mocked in [`src/data/mockData.js`](src/data/mockData.js). Swap the
exports / the `api.*` helpers for real fetch calls when you wire up the
backend; the components do not need to change.

## Features

- **Home** — greeting, today's next event, recovery goals, notifications inbox.
- **Care Team Directory** — photos (initials avatars), names, roles, and
  on-shift / next-visit info for every nurse, physician and specialist.
- **Health** — live vitals from the bedside monitor, simplified medication list
  with plain-language explanations, daily recovery overview.
- **Smart Room Controls** — lighting scenes + brightness, room temperature,
  motorized blinds, TV power + volume, do-not-disturb.
- **Meals** — restriction-aware menu (foods that conflict with the active
  clinical profile are visibly disabled), diet snapshot, fluid intake tracker.
- **Smart Requests** — replaces the generic nurse call with categorized
  requests (pain, bathroom, water, breathing, etc.) routed to the correct staff
  with per-category response ETAs.
- **Schedule / Discharge / Visitors** — predictable daily itinerary, a
  step-by-step discharge checklist showing which clinical sign-offs are still
  outstanding, plus visiting-hours info.
- A persistent floating **Nurse Call** button is reachable from every tab.

## Project layout

```
patient_app/
  App.js
  app.json
  index.js
  src/
    theme.js                 -- colors / spacing / shadow tokens
    data/mockData.js         -- single source of mock API data
    components/
      Avatar.js
      Pill.js
      SectionCard.js
      Slider.js
      Stepper.js
      Toast.js
    screens/
      PatientHome.js         -- header + bottom tab nav + FAB
      tabs/
        HomeTab.js
        CareTeamTab.js
        HealthTab.js
        RoomTab.js
        MealsTab.js
        RequestsTab.js
        ScheduleTab.js
```

## Run on a device with Expo Go (fastest dev loop)

```powershell
cd C:\laragon\www\smartward4\patient_app
npm install
npx expo start
```

Scan the QR with Expo Go on the phone. Phone and PC must share Wi-Fi.

## Build a standalone APK

Same toolchain as the nurse app:

- JDK 17 at `C:\Program Files\Java\jdk-17.0.19`
- Android SDK at `C:\Users\krich\AppData\Local\Android\Sdk` (`ANDROID_HOME`).

From `patient_app/`:

```powershell
$env:JAVA_HOME = "C:\Program Files\Java\jdk-17.0.19"
$env:Path      = "$env:JAVA_HOME\bin;$env:Path"
npx expo prebuild --platform android --clean
cd android
.\gradlew.bat assembleRelease
```

APK lands at:

```
patient_app\android\app\build\outputs\apk\release\app-release.apk
```

## Wiring real data later

Every screen calls into the exports from `src/data/mockData.js`. The simplest
migration path:

```js
// src/data/api.js
const BASE = 'https://your-backend/api';

export async function fetchPatientDashboard(patientId) {
  const r = await fetch(`${BASE}/patients/${patientId}/dashboard.json`);
  return r.json();
}
```

Then in `PatientHome.js`, replace the static imports with a `useEffect` that
calls `fetchPatientDashboard()` and stores the result in state — the
components don't need to change because they receive their data as props.

A simulated async layer is already provided as `api.*` (e.g. `api.getVitals()`,
`api.submitRequest('pain')`) — replace those bodies with `fetch()` calls and
the rest of the app keeps working.
