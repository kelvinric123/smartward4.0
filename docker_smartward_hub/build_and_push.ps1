param (
    [string]$TagName = "latest"
)

$ErrorActionPreference = "Stop"

# Define the images and their build parameters
# Format: @{ ImageName = "..."; Context = "..."; Dockerfile = "..." }
$imagesToBuild = @(
    @{
        ImageName = "kelvinric/smartward4:$TagName"
        Context = ".."
        Dockerfile = "Dockerfile"
    },
    @{
        ImageName = "kelvinric/smartward4-ldap:$TagName"
        Context = "../ldap"
        Dockerfile = "../ldap/Dockerfile"
    },
    @{
        ImageName = "kelvinric/smartward4-adt:$TagName"
        Context = ".."
        Dockerfile = "../HL7/Dockerfile"
    },
    @{
        ImageName = "kelvinric/smartward4-ecg:$TagName"
        Context = ".."
        Dockerfile = "../ecg/Dockerfile"
    },
    @{
        ImageName = "kelvinric/smartward4-bbraun:$TagName"
        Context = ".."
        Dockerfile = "../bbraun/Dockerfile"
    }
)

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

Write-Host "All images built and pushed successfully!" -ForegroundColor Green
