@echo off
title SmartWard 4.0 - Setup
echo.
echo  ========================================
echo    SmartWard 4.0 Electron Setup
echo  ========================================
echo.

:: Check Node.js
where node >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Node.js is not installed!
    echo Please install Node.js from https://nodejs.org/
    echo.
    pause
    exit /b 1
)

echo [OK] Node.js found: 
node --version

:: Check npm
where npm >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] npm is not installed!
    pause
    exit /b 1
)

echo [OK] npm found:
npm --version

echo.
echo Installing dependencies...
echo.

call npm install

if %errorlevel% neq 0 (
    echo.
    echo [ERROR] Failed to install dependencies!
    pause
    exit /b 1
)

echo.
echo  ========================================
echo    Setup Complete!
echo  ========================================
echo.
echo  To run in development mode:
echo    npm start
echo.
echo  To build the executable:
echo    npm run build
echo.
echo  The built exe will be in: dist\
echo.
pause






