@echo off
rem Builds and pushes kelvinric/rpa_cplus_smartward:latest to Docker Hub.
rem Docker Desktop must be running and logged in (docker login).
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0build_and_push.ps1" %*
echo.
pause
