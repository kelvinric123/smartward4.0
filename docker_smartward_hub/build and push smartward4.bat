@echo off
rem Builds and pushes only the main app image (kelvinric/smartward4:latest).
rem The sidecar images (ldap, adt, ecg, bbraun) are left as they are on Docker Hub.
rem build_and_push.ps1 uses paths relative to this folder, so run it from here.
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0build_and_push.ps1" -Only smartward4 %*
echo.
pause
