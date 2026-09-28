# Builds and pushes the SmartWard images to Docker Hub.
#
#   .\build_and_push.ps1                          all five images
#   .\build_and_push.ps1 -Only smartward4         just the main app image
#   .\build_and_push.ps1 -Only adt,ecg            a few of them
#
# Names for -Only: smartward4, ldap, adt, ecg, bbraun
param (
    [string]$TagName = "latest",
    [string[]]$Only = @()
)

$ErrorActionPreference = "Stop"

# Define the images and their build parameters
# Format: @{ Name = "..."; ImageName = "..."; Context = "..."; Dockerfile = "..." }
$imagesToBuild = @(
    @{
        Name = "smartward4"
        ImageName = "kelvinric/smartward4:$TagName"
        Context = ".."
        Dockerfile = "Dockerfile"
    },
    @{
        Name = "ldap"
        ImageName = "kelvinric/smartward4-ldap:$TagName"
        Context = "../ldap"
        Dockerfile = "../ldap/Dockerfile"
    },
    @{
        Name = "adt"
        ImageName = "kelvinric/smartward4-adt:$TagName"
        Context = ".."
        Dockerfile = "../HL7/Dockerfile"
    },
    @{
        Name = "ecg"
        ImageName = "kelvinric/smartward4-ecg:$TagName"
        Context = ".."
        Dockerfile = "../ecg/Dockerfile"
    },
    @{
        Name = "bbraun"
        ImageName = "kelvinric/smartward4-bbraun:$TagName"
        Context = ".."
        Dockerfile = "../bbraun/Dockerfile"
    }
)

if ($Only.Count -gt 0) {
    # Accept "-Only adt,ecg" and "-Only 'adt,ecg'" alike
    $Only = $Only | ForEach-Object { $_ -split "," } | ForEach-Object { $_.Trim() } | Where-Object { $_ }
    $known = $imagesToBuild | ForEach-Object { $_.Name }
    $unknown = $Only | Where-Object { $known -notcontains $_ }
    if ($unknown) {
        Write-Host "Unknown image name(s): $($unknown -join ', '). Use: $($known -join ', ')" -ForegroundColor Red
        exit 1
    }
    $imagesToBuild = @($imagesToBuild | Where-Object { $Only -contains $_.Name })
}

foreach ($target in $imagesToBuild) {
    $img = $target.ImageName
    $ctx = $target.Context
    $df = $target.Dockerfile

    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "Starting build for image: $img" -ForegroundColor Cyan
    Write-Host "========================================" -ForegroundColor Cyan

    docker build -f $df -t $img $ctx

    if ($LASTEXITCODE -ne 0) {
        Write-Host "Docker build failed for $img." -ForegroundColor Red
        exit $LASTEXITCODE
    }

    Write-Host "Build completed. Pushing to Docker Hub: $img" -ForegroundColor Green
    docker push $img

    if ($LASTEXITCODE -ne 0) {
        Write-Host "Docker push failed for $img." -ForegroundColor Red
        exit $LASTEXITCODE
    }

    Write-Host "Successfully built and pushed $img" -ForegroundColor Green
    Write-Host ""
}

Write-Host "Built and pushed: $(($imagesToBuild | ForEach-Object { $_.Name }) -join ', ')" -ForegroundColor Green
