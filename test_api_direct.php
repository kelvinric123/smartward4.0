<?php
/**
 * Direct API V1 Test Script
 * Run this from browser: http://localhost:8000/test_api_direct.php
 * Or from command line: php test_api_direct.php
 */

// Configuration
$baseUrl = 'http://127.0.0.1:8000/api/v1';
$passphrase = 'qmedno1';
$username = 'rasberry1@qmed.asia'; // Change to your API user
$password = '88888888';  // Change to your API user password
$patientCode = 'P001';  // Change to an actual patient MRN

echo "=== API V1 Direct Test ===\n\n";

// Test 1: Device Login (just to verify auth)
echo "1. Testing Device Login...\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "$baseUrl/device/login",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        "X-Passphrase: $passphrase",
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'username' => $username,
        'password' => $password,
    ]),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "   HTTP Code: $httpCode\n";
if ($error) {
    echo "   CURL Error: $error\n";
} else {
    $data = json_decode($response, true);
    echo "   Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
}

echo "\n";

// Test 2: Send Vital Signs
echo "2. Testing Send Vital Signs...\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "$baseUrl/vital-signs",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        "X-Passphrase: $passphrase",
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'username' => $username,
        'password' => $password,
        'patient_code' => $patientCode,
        'blood_pressure_systolic' => 120,
        'blood_pressure_diastolic' => 80,
        'pulse_rate' => 72,
        'temperature' => 36.5,
        'spo2' => 98,
        'measured_at' => date('Y-m-d H:i:s'),
    ]),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "   HTTP Code: $httpCode\n";
if ($error) {
    echo "   CURL Error: $error\n";
} else {
    $data = json_decode($response, true);
    echo "   Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
}

echo "\n=== Test Complete ===\n";

// If running in browser
if (php_sapi_name() !== 'cli') {
    echo "<pre>";
}























