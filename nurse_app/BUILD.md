# Nurse Dashboard — Expo App

A React Native (Expo SDK 56) port of `/nurses/{id}/dashboard`. All data is
hardcoded in `src/data/mockData.js`; swap that file's exports for real fetch
calls when you wire up the backend.

## Project layout

```
nurse_app/
  App.js
  app.json
  index.js
  src/
    theme.js                 -- colors / spacing tokens
    data/mockData.js         -- replace with API calls later
    components/
      StatCard.js
      Pill.js
      BedCard.js
    screens/NurseDashboard.js
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
- Android SDK at `C:\Users\krich\AppData\Local\Android\Sdk` (`ANDROID_HOME`).

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

## Wiring real data later

Each export in `src/data/mockData.js` matches one piece of state the screen
reads. The simplest path:

```js
// src/data/api.js
const BASE = 'https://your-backend/api';

export async function fetchNurseDashboard(nurseId) {
  const res = await fetch(`${BASE}/nurses/${nurseId}/dashboard.json`);
  return res.json();   // { nurse, currentShift, wards, selectedWard, summary, assignedBeds }
}
```

Then in `NurseDashboard.js`, replace the static imports with a `useEffect` that
calls `fetchNurseDashboard` and stores the result in state — the rendering
components don't need to change.
