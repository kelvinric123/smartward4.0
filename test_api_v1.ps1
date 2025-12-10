# Test API V1 Connection
# Run this from PowerShell to test the API

$baseUrl = "http://localhost:8000/api/v1"
$passphrase = "qmedno1"
$username = "rasberry1@qmed.asia"
$password = "88888888"

Write-Host "Testing API V1 Connection..." -ForegroundColor Cyan

# Test 1: Device Login
Write-Host "`n=== Test 1: Device Login ===" -ForegroundColor Yellow
$headers = @{
    "X-Passphrase" = $passphrase
    "Content-Type" = "application/json"
}
$body = @{
    username = $username
    password = $password
} | ConvertTo-Json

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/device/login" -Method POST -Headers $headers -Body $body
    Write-Host "SUCCESS: $($response.message)" -ForegroundColor Green
    $response | ConvertTo-Json -Depth 3
} catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        Write-Host "Response: $responseBody" -ForegroundColor Red
    }
}

# Test 2: Send Vital Signs (will fail if no patient exists, but shows auth works)
Write-Host "`n=== Test 2: Send Vital Signs ===" -ForegroundColor Yellow
$body = @{
    username = $username
    password = $password
    patient_code = "TEST001"
    blood_pressure_systolic = 120
    blood_pressure_diastolic = 80
    pulse_rate = 72
    temperature = 36.5
    spo2 = 98
} | ConvertTo-Json

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/vital-signs" -Method POST -Headers $headers -Body $body
    Write-Host "SUCCESS: $($response.message)" -ForegroundColor Green
    $response | ConvertTo-Json -Depth 3
} catch {
    Write-Host "Response Code: $($_.Exception.Response.StatusCode.value__)" -ForegroundColor Yellow
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        Write-Host "Response: $responseBody" -ForegroundColor Yellow
    }
}

Write-Host "`n=== Test Complete ===" -ForegroundColor Cyan








