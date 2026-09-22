@echo off
rem Builds (or updates) one hospital's Docker config in "hospital config\<Hospital name>\"
rem from docker-compose.yml in this folder. The work is done by build-hospital-config.ps1.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0build-hospital-config.ps1" %*
echo.
pause
