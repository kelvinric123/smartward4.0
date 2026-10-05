# Builds and pushes the rpa_cplus_smartward image to Docker Hub.
#
#   .\build_and_push.ps1                  kelvinric/rpa_cplus_smartward:latest
#   .\build_and_push.ps1 -TagName 1.0.0   kelvinric/rpa_cplus_smartward:1.0.0
#
# The build context is this folder. .dockerignore keeps .env, cookie jars,
# his_state.json, scratch\ and logs\ out of the image; this script checks it.
param (
    [string]$TagName = "latest"
)

$ErrorActionPreference = "Stop"
Set-Location -Path $PSScriptRoot

$img = "kelvinric/rpa_cplus_smartward:$TagName"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Starting build for image: $img" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

docker build -t $img .
if ($LASTEXITCODE -ne 0) {
    Write-Host "Docker build failed for $img." -ForegroundColor Red
    exit $LASTEXITCODE
}

# Refuse to push an image that carries a secret or a session.
$leaks = docker run --rm --entrypoint sh $img -c "ls -a /app | grep -E '^(\.env|rpa\.env|cookies.*\.txt|his_state\.json|isolation_cache\.json)$|^(scratch|logs)$' || true"
if ($leaks) {
    Write-Host "The image contains files that must stay out of it: $($leaks -join ', '). Not pushing." -ForegroundColor Red
    exit 1
}

Write-Host "Build completed. Pushing to Docker Hub: $img" -ForegroundColor Green
docker push $img
if ($LASTEXITCODE -ne 0) {
    Write-Host "Docker push failed for $img. Logged in? Run: docker login" -ForegroundColor Red
    exit $LASTEXITCODE
}

Write-Host "Successfully built and pushed $img" -ForegroundColor Green
