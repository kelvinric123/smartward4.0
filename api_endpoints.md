# Verified API Endpoints

Based on the current configuration in `routes/web.php` and verification tests, these are the active API endpoints.

## Base URL
Ensure you are using the correct protocol (`http` or `https`) and domain.
Example: `https://phklsmartward.ppl.ihh.com`

## Endpoints

All endpoints below are prefixed with `/api/v1`.

| Method | Endpoint Path | Description | Controller Method |
| :--- | :--- | :--- | :--- |
| **POST** | `/api/v1/vital-signs` | Submit vital signs data | `VitalSignApiV1Controller@receiveVitalSigns` |
| **POST** | `/api/v1/ping` | Device ping/heartbeat | `VitalSignApiV1Controller@ping` |
| **POST** | `/api/v1/device/login` | Device authentication | `VitalSignApiV1Controller@deviceLogin` |
| **GET** | `/api/v1/patients/{patientCode}` | Look up patient details | `VitalSignApiV1Controller@searchPatient` |
| **GET** | `/api/v1/monitor-devices` | List monitor devices | `VitalSignIntegrationController@apiGetDevices` |
| **POST** | `/api/v1/monitor-devices/{device}/status`| Update specific device status | `VitalSignIntegrationController@apiUpdateDeviceStatus` |
| **POST** | `/api/v1/monitor/status` | Receive generic monitor status | `VitalSignIntegrationController@apiReceiveMonitorStatus` |

## Authentication
These endpoints expect:
- **Header**: `X-Passphrase: <your-configured-passphrase>`
- **Body**: `username` and `password` fields for user authentication (for most actions).

## Convention Note
Currently, these routes are defined in `routes/web.php`.
- **Standard Convention**: API routes typically reside in `routes/api.php`. This file is automatically mapped to the `/api` prefix and the `api` middleware group (stateless, throttling, etc.) by `bootstrap/app.php` (or `RouteServiceProvider` in older versions).
- **Current Setup**: Your routes are in `web.php` but manually prefixed with `api/v1`. This works because they are explicitly exempted from CSRF protection in `bootstrap/app.php`.

**Recommendation**: Moving them to `routes/api.php` would align with Laravel best practices, separating browser-based logic from API logic.
