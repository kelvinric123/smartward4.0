@echo off
REM Stop RoadRunner server

echo Stopping RoadRunner server...
taskkill /F /IM rr.exe /T 2>nul
if %errorlevel% == 0 (
    echo Server stopped successfully
) else (
    echo No RoadRunner server running
)
pause

