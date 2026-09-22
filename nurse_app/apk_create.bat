@echo off
setlocal enabledelayedexpansion
REM ============================================================
REM  QMed Smart Ward - Nurse App release APK builder
REM
REM  Usage:
REM    apk_create.bat          build APK (runs prebuild only if needed)
REM    apk_create.bat clean    wipe android\ and regenerate before building
REM
REM  Output: nurse_app\nurse_app-release.apk
REM ============================================================

cd /d "%~dp0"

REM --- 1. Locate JDK 17 -----------------------------------------------------
if exist "C:\Program Files\Java\jdk-17.0.19\bin\java.exe" (
    set "JAVA_HOME=C:\Program Files\Java\jdk-17.0.19"
) else if exist "C:\Program Files\Microsoft\jdk-17.0.19.10-hotspot\bin\java.exe" (
    set "JAVA_HOME=C:\Program Files\Microsoft\jdk-17.0.19.10-hotspot"
)
if not exist "%JAVA_HOME%\bin\java.exe" (
    echo [ERROR] JDK 17 not found. Install JDK 17 or edit JAVA_HOME in this script.
    goto :fail
)
set "PATH=%JAVA_HOME%\bin;%PATH%"
echo [1/5] Using JAVA_HOME=%JAVA_HOME%

REM --- 2. Locate Android SDK --------------------------------------------------
REM ANDROID_HOME, else ANDROID_SDK_ROOT, else the first usual folder with adb in it
if "%ANDROID_HOME%"=="" if not "%ANDROID_SDK_ROOT%"=="" set "ANDROID_HOME=%ANDROID_SDK_ROOT%"
if "%ANDROID_HOME%"=="" (
    for %%S in ("%LOCALAPPDATA%\Android\Sdk" "C:\Android\Sdk" "%USERPROFILE%\Android\Sdk" "C:\Android\android-sdk") do (
        if "!ANDROID_HOME!"=="" if exist "%%~S\platform-tools\adb.exe" set "ANDROID_HOME=%%~S"
    )
)
if not exist "%ANDROID_HOME%\platform-tools\adb.exe" (
    echo [ERROR] Android SDK not found. Looked at ANDROID_HOME, ANDROID_SDK_ROOT,
    echo         %LOCALAPPDATA%\Android\Sdk and C:\Android\Sdk.
    echo         Install it via Android Studio, or set ANDROID_HOME to the SDK folder.
    goto :fail
)
echo [2/5] Using ANDROID_HOME=%ANDROID_HOME%

REM --- 3. Dependencies --------------------------------------------------------
if not exist "node_modules\expo" (
    echo [3/5] Installing npm dependencies...
    call npm install
    if errorlevel 1 goto :fail
) else (
    echo [3/5] npm dependencies OK
)

REM --- 4. Native android project ---------------------------------------------
if /i "%~1"=="clean" (
    echo [4/5] Regenerating native android project (clean^)...
    call npx expo prebuild --platform android --clean
    if errorlevel 1 goto :fail
) else if not exist "android\gradlew.bat" (
    echo [4/5] Generating native android project...
    call npx expo prebuild --platform android
    if errorlevel 1 goto :fail
) else (
    echo [4/5] Native android project OK ^(run "apk_create.bat clean" to regenerate^)
)

REM Point Gradle at the SDK if prebuild didn't write local.properties
if not exist "android\local.properties" (
    set "SDK_ESCAPED=%ANDROID_HOME:\=\\%"
    > "android\local.properties" echo sdk.dir=!SDK_ESCAPED!
    echo        Wrote android\local.properties
)

REM --- 5. Build ----------------------------------------------------------------
echo [5/5] Building release APK ^(this takes a few minutes^)...
pushd android
call .\gradlew.bat assembleRelease
set "BUILD_RESULT=%ERRORLEVEL%"
popd
if not "%BUILD_RESULT%"=="0" goto :fail

set "APK_SRC=android\app\build\outputs\apk\release\app-release.apk"
if not exist "%APK_SRC%" (
    echo [ERROR] Build reported success but APK not found at %APK_SRC%
    goto :fail
)

copy /y "%APK_SRC%" "nurse_app-release.apk" >nul

echo.
echo ============================================================
echo  BUILD SUCCESSFUL
echo  APK: %~dp0nurse_app-release.apk
echo.
echo  Install on a connected phone with:
echo    "%ANDROID_HOME%\platform-tools\adb.exe" install -r nurse_app-release.apk
echo ============================================================
pause
exit /b 0

:fail
echo.
echo ============================================================
echo  BUILD FAILED - see messages above
echo ============================================================
pause
exit /b 1
