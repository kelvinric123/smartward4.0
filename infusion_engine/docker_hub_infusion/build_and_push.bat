@echo off
REM ===========================================================================
REM Build and push the infusion engine image to Docker Hub
REM (repository: kelvinric/infusion_engine).
REM Usage (from docker_hub_infusion folder):
REM   build_and_push.bat            -> kelvinric/infusion_engine:latest
REM   build_and_push.bat v1.1.0     -> kelvinric/infusion_engine:v1.1.0 + latest
REM Reuses the Dockerfile in ..\docker_infusion (same engine image).
REM ===========================================================================
setlocal

set TAG=%1
if "%TAG%"=="" set TAG=latest
set REPO=kelvinric/infusion_engine
set IMG=%REPO%:%TAG%

echo ========================================
echo Building image: %IMG%
echo ========================================
docker build -f "%~dp0..\docker_infusion\Dockerfile" -t %IMG% "%~dp0.."
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

if not "%TAG%"=="latest" (
    echo Tagging %IMG% as %REPO%:latest and pushing
    docker tag %IMG% %REPO%:latest
    if errorlevel 1 (
        echo Docker tag failed for %REPO%:latest.
        exit /b 1
    )
    docker push %REPO%:latest
    if errorlevel 1 (
        echo Docker push failed for %REPO%:latest.
        exit /b 1
    )
)

echo Successfully built and pushed %IMG%
endlocal
