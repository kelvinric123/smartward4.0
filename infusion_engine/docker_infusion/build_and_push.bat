@echo off
REM ===========================================================================
REM Build and push the infusion engine image to Docker Hub.
REM Usage (from docker_infusion folder):
REM   build_and_push.bat            -> kelvinric/infusion:latest
REM   build_and_push.bat v1.1.0     -> kelvinric/infusion:v1.1.0
REM ===========================================================================
setlocal

set TAG=%1
if "%TAG%"=="" set TAG=latest
set IMG=kelvinric/infusion:%TAG%

echo ========================================
echo Building image: %IMG%
echo ========================================
docker build -f "%~dp0Dockerfile" -t %IMG% "%~dp0.."
if errorlevel 1 (
    echo Docker build failed for %IMG%.
    exit /b 1
)

echo Build completed. Pushing to Docker Hub: %IMG%
docker push %IMG%
if errorlevel 1 (
    echo Docker push failed for %IMG%.
    exit /b 1
)

echo Successfully built and pushed %IMG%
endlocal
