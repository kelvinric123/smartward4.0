@echo off
REM ===========================================================================
REM Build and push the Comen NC5 gateway image to Docker Hub.
REM Usage (from docker_nc5 folder):
REM   build_and_push.bat            -> kelvinric/nc5:latest
REM   build_and_push.bat v1.1.0     -> kelvinric/nc5:v1.1.0
REM
REM The NAS pulls this image, so it must be pushed before updating the UGOS
REM project. The NAS needs its own `sudo docker login -u kelvinric` over SSH -
REM the UGOS GUI registry credential is not used by project pulls.
REM ===========================================================================
setlocal

set TAG=%1
if "%TAG%"=="" set TAG=latest
set IMG=kelvinric/nc5:%TAG%

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
