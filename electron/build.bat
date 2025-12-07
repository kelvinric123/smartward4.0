@echo off
title SmartWard 4.0 - Build
echo.
echo  ========================================
echo    Building SmartWard 4.0 Executable
echo  ========================================
echo.

:: Check if node_modules exists
if not exist "node_modules" (
    echo [INFO] Installing dependencies first...
    call npm install
)

echo.
echo Building Windows executable...
echo This may take a few minutes...
echo.

call npm run build

if %errorlevel% neq 0 (
    echo.
    echo [ERROR] Build failed!
    pause
    exit /b 1
)

echo.
echo  ========================================
echo    Build Complete!
echo  ========================================
echo.
echo  Your executable is located at:
echo    dist\smartward4.0.exe
echo.

:: Open dist folder
explorer dist

pause






