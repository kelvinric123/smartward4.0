<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HospitalController;
use App\Http\Controllers\SpecialtyController;
use App\Http\Controllers\ConsultantController;
use App\Http\Controllers\AnaesthetistController;
use App\Http\Controllers\NurseController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\WardController;
use App\Http\Controllers\BedController;
use App\Http\Controllers\WardDashboardController;
use App\Http\Controllers\WardScheduleController;
use App\Http\Controllers\VitalSignController;
use App\Http\Controllers\LdapConfigurationController;
use App\Http\Controllers\VitalSignIntegrationController;
use App\Http\Controllers\InfusionIntegrationController;
use App\Http\Controllers\AdtConfigurationController;
use App\Http\Controllers\EcgController;
use App\Http\Controllers\EkadController;
use App\Http\Controllers\ShiftSettingController;
use App\Http\Controllers\DietTypeController;
use App\Http\Controllers\IsolationTypeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// LDAP Login Routes (must be outside auth middleware)
Route::get('/login/ldap', [LdapConfigurationController::class, 'showLdapLogin'])->name('login.ldap');
Route::post('/login/ldap', [LdapConfigurationController::class, 'ldapLogin'])->name('login.ldap.submit');

// Public ECG PDF Route (for PDF viewer in iframes - authentication handled by signed URL or session)
Route::get('/ecg/pdf/view', [EcgController::class, 'servePdf'])->name('ecg.pdf.public');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Admin Management Routes
    Route::resource('hospitals', HospitalController::class)->except(['show']);
    Route::post('hospitals/{hospital}/deactivate', [HospitalController::class, 'deactivate'])->name('hospitals.deactivate');

    Route::resource('specialties', SpecialtyController::class)->except(['show']);
    Route::post('specialties/{specialty}/deactivate', [SpecialtyController::class, 'deactivate'])->name('specialties.deactivate');

    Route::resource('consultants', ConsultantController::class);
    Route::post('consultants/{consultant}/deactivate', [ConsultantController::class, 'deactivate'])->name('consultants.deactivate');
    Route::get('consultants-bulk-upload', [ConsultantController::class, 'bulkUploadForm'])->name('consultants.bulk-upload');
    Route::post('consultants-bulk-upload/preview', [ConsultantController::class, 'bulkUploadPreview'])->name('consultants.bulk-upload.preview');
    Route::post('consultants-bulk-upload/confirm', [ConsultantController::class, 'bulkUploadConfirm'])->name('consultants.bulk-upload.confirm');

    Route::resource('anaesthetists', AnaesthetistController::class);
    Route::post('anaesthetists/{anaesthetist}/deactivate', [AnaesthetistController::class, 'deactivate'])->name('anaesthetists.deactivate');
    Route::get('anaesthetists-bulk-upload', [AnaesthetistController::class, 'bulkUploadForm'])->name('anaesthetists.bulk-upload');
    Route::post('anaesthetists-bulk-upload/preview', [AnaesthetistController::class, 'bulkUploadPreview'])->name('anaesthetists.bulk-upload.preview');
    Route::post('anaesthetists-bulk-upload/confirm', [AnaesthetistController::class, 'bulkUploadConfirm'])->name('anaesthetists.bulk-upload.confirm');

    Route::resource('nurses', NurseController::class)->except(['show']);
    Route::put('nurses/{nurse}/ldap-binding', [NurseController::class, 'updateLdapBinding'])->name('nurses.update-ldap-binding');
    Route::post('nurses/{nurse}/deactivate', [NurseController::class, 'deactivate'])->name('nurses.deactivate');
    Route::get('nurses-bulk-upload', [NurseController::class, 'bulkUploadForm'])->name('nurses.bulk-upload');
    Route::post('nurses-bulk-upload/preview', [NurseController::class, 'bulkUploadPreview'])->name('nurses.bulk-upload.preview');
    Route::post('nurses-bulk-upload/confirm', [NurseController::class, 'bulkUploadConfirm'])->name('nurses.bulk-upload.confirm');

    Route::resource('users', \App\Http\Controllers\UsersController::class)->except(['show']);
    Route::post('users/{user}/update-role', [\App\Http\Controllers\UsersController::class, 'updateRole'])->name('users.update-role');
    Route::post('users/{user}/toggle-status', [\App\Http\Controllers\UsersController::class, 'toggleStatus'])->name('users.toggle-status');


    // Patient Additional Field Routes (inside Admin Management)
    Route::resource('diet-types', DietTypeController::class)->except(['show']);
    Route::post('diet-types/{diet_type}/toggle-active', [DietTypeController::class, 'toggleActive'])->name('diet-types.toggle-active');

    Route::resource('isolation-types', IsolationTypeController::class)->except(['show', 'index']);
    Route::post('isolation-types/{isolation_type}/toggle-active', [IsolationTypeController::class, 'toggleActive'])->name('isolation-types.toggle-active');

    // Patient Routes
    Route::resource('patients', PatientController::class)->except(['show']);
    Route::post('patients/{patient}/deactivate', [PatientController::class, 'deactivate'])->name('patients.deactivate');

    // Ward Management Routes
    Route::resource('wards', WardController::class)->except(['show']);
    Route::post('wards/{ward}/deactivate', [WardController::class, 'deactivate'])->name('wards.deactivate');

    // Bed Management Routes
    Route::resource('beds', BedController::class)->except(['show']);
    Route::post('beds/{bed}/deactivate', [BedController::class, 'deactivate'])->name('beds.deactivate');

    // Ward Schedule Routes
    Route::get('/ward-schedule', [WardScheduleController::class, 'index'])
        ->name('ward.schedule');
    Route::get('/ward-schedule/individual', [WardScheduleController::class, 'individual'])
        ->name('ward.schedule.individual');
    Route::post('/ward-schedule/assign', [WardScheduleController::class, 'assignNurses'])
        ->name('ward.schedule.assign');
    Route::get('/ward-schedule/patient-details', [WardScheduleController::class, 'patientDetailsIframe'])->name('ward.schedule.patient-details');
    Route::get('/ward-schedule/download-template', [WardScheduleController::class, 'downloadTemplate'])->name('ward.schedule.download-template');
    Route::post('/ward-schedule/upload', [WardScheduleController::class, 'uploadRoster'])->name('ward.schedule.upload');

    // Shift Settings Routes
    Route::get('/ward-schedule/shift-settings', [ShiftSettingController::class, 'index'])->name('ward.shift-settings');
    Route::post('/ward-schedule/shift-settings', [ShiftSettingController::class, 'update'])->name('ward.shift-settings.update');
    Route::post('/ward-schedule/shift-settings/reset', [ShiftSettingController::class, 'reset'])->name('ward.shift-settings.reset');

    // Ward Dashboard Routes
    Route::get('/ward-dashboard', [WardDashboardController::class, 'index'])->name('ward.dashboard');
    Route::post('/ward-dashboard/admit-patient', [WardDashboardController::class, 'admitPatient'])->name('ward.admit-patient');
    Route::post('/ward-dashboard/prebook-patient', [WardDashboardController::class, 'prebookPatient'])->name('ward.prebook-patient');
    Route::post('/ward-dashboard/check-in-prebook/{patient}', [WardDashboardController::class, 'checkInPrebook'])->name('ward.check-in-prebook');
    Route::post('/ward-dashboard/cancel-prebook/{patient}', [WardDashboardController::class, 'cancelPrebook'])->name('ward.cancel-prebook');
    Route::get('/ward-dashboard/admission-logs', [WardDashboardController::class, 'admissionLogs'])->name('ward.admission-logs');
    Route::get('/ward-dashboard/patients', [WardDashboardController::class, 'patientsList'])->name('ward.patients-list');
    Route::get('/ward-dashboard/patient-details', [WardDashboardController::class, 'patientDetails'])->name('ward.patient-details');
    Route::get('/ward-dashboard/settings', [WardDashboardController::class, 'settings'])->name('ward.settings');
    Route::post('/ward-dashboard/settings', [WardDashboardController::class, 'updateSettings'])->name('ward.settings.update');
    Route::post('/ward-dashboard/patient-movements', [WardDashboardController::class, 'storeMovement'])->name('ward.patient-movements.store');
    Route::post('/ward-dashboard/patient-movements/{movement}/send', [WardDashboardController::class, 'sendMovement'])->name('ward.patient-movements.send');
    Route::post('/ward-dashboard/patient-movements/{movement}/return', [WardDashboardController::class, 'returnMovement'])->name('ward.patient-movements.return');
    Route::post('/ward-dashboard/patient-referrals', [WardDashboardController::class, 'storeReferral'])->name('ward.patient-referrals.store');
    Route::post('/ward-dashboard/transfer-bed', [WardDashboardController::class, 'transferBed'])->name('ward.transfer-bed');
    Route::post('/ward-dashboard/discharge-patient', [WardDashboardController::class, 'dischargePatient'])->name('ward.discharge-patient');
    Route::post('/ward-dashboard/update-patient-clinical', [WardDashboardController::class, 'updatePatientClinical'])->name('ward.update-patient-clinical');
    Route::post('/ward-dashboard/save-sugar-reading', [WardDashboardController::class, 'saveSugarReading'])->name('ward.save-sugar-reading');
    Route::post('/ward-dashboard/settings/clinical-options', [WardDashboardController::class, 'updateClinicalIndicatorOptions'])->name('ward.settings.clinical-options');

    // Ward Notification Routes
    Route::get('/ward-dashboard/notifications', [WardDashboardController::class, 'getNotifications'])->name('ward.notifications');
    Route::post('/ward-dashboard/notifications/{notification}/respond', [WardDashboardController::class, 'respondNotification'])->name('ward.notifications.respond');

    // Vital Signs Routes
    Route::get('/vital-signs', [VitalSignController::class, 'index'])->name('vital-signs.index');
    Route::post('/vital-signs', [VitalSignController::class, 'store'])->name('vital-signs.store');
    Route::get('/vital-signs/patient', [VitalSignController::class, 'patientVitals'])->name('vital-signs.patient');
    Route::get('/vital-signs/latest', [VitalSignController::class, 'latestVitals'])->name('vital-signs.latest');
    Route::get('/vital-signs/check-new', [VitalSignController::class, 'checkNewVitalSigns'])->name('vital-signs.check-new');


    // LDAP Integration Routes
    Route::get('/ldap', [LdapConfigurationController::class, 'index'])->name('ldap.index');
    Route::post('/ldap/manual-sync', [LdapConfigurationController::class, 'manualSync'])->name('ldap.manual-sync');

    // Vital Sign Integration Routes
    Route::get('/vital-sign-integration', [VitalSignIntegrationController::class, 'index'])->name('vital-sign-integration.index');
    Route::post('/vital-sign-integration/bind', [VitalSignIntegrationController::class, 'storeBinding'])->name('vital-sign-integration.bind');
    Route::delete('/vital-sign-integration/unbind/{binding}', [VitalSignIntegrationController::class, 'unbindGateway'])->name('vital-sign-integration.unbind');
    Route::post('/vital-sign-integration/api-user', [VitalSignIntegrationController::class, 'storeApiUser'])->name('vital-sign-integration.api-user.store');
    Route::put('/vital-sign-integration/api-user/{apiUser}', [VitalSignIntegrationController::class, 'updateApiUser'])->name('vital-sign-integration.api-user.update');
    Route::delete('/vital-sign-integration/api-user/{apiUser}', [VitalSignIntegrationController::class, 'destroyApiUser'])->name('vital-sign-integration.api-user.destroy');
    Route::post('/vital-sign-integration/api-user/{apiUser}/regenerate-token', [VitalSignIntegrationController::class, 'regenerateToken'])->name('vital-sign-integration.api-user.regenerate-token');
    Route::get('/vital-sign-integration/logs', [VitalSignIntegrationController::class, 'getLogs'])->name('vital-sign-integration.logs');
    Route::post('/vital-sign-integration/logs/clear', [VitalSignIntegrationController::class, 'clearLogs'])->name('vital-sign-integration.logs.clear');

    // Qmed Gateway Routes
    Route::post('/vital-sign-integration/gateway', [VitalSignIntegrationController::class, 'storeGateway'])->name('vital-sign-integration.gateway.store');
    Route::put('/vital-sign-integration/gateway/{gateway}', [VitalSignIntegrationController::class, 'updateGateway'])->name('vital-sign-integration.gateway.update');
    Route::delete('/vital-sign-integration/gateway/{gateway}', [VitalSignIntegrationController::class, 'destroyGateway'])->name('vital-sign-integration.gateway.destroy');

    // Monitor Device Routes (for mp5sc listener) - REMOVED
    // Route::post('/vital-sign-integration/device', [VitalSignIntegrationController::class, 'storeDevice'])->name('vital-sign-integration.device.store');
    // ...

    // Infusion Integration Routes (B.Braun HL7/MLLP)
    Route::get('/infusion-integration', [InfusionIntegrationController::class, 'index'])->name('infusion-integration.index');
    Route::post('/infusion-integration/pump', [InfusionIntegrationController::class, 'storePump'])->name('infusion-integration.pump.store');
    Route::put('/infusion-integration/pump/{pump}', [InfusionIntegrationController::class, 'updatePump'])->name('infusion-integration.pump.update');
    Route::delete('/infusion-integration/pump/{pump}', [InfusionIntegrationController::class, 'destroyPump'])->name('infusion-integration.pump.destroy');
    Route::post('/infusion-integration/logs/clear', [InfusionIntegrationController::class, 'clearLogs'])->name('infusion-integration.logs.clear');

    // Ward Infusion Overview (iframe)
    Route::get('/ward-dashboard/infusion-overview', [InfusionIntegrationController::class, 'wardOverview'])->name('ward.infusion-overview');
    Route::get('/ward-dashboard/patient-infusions', [InfusionIntegrationController::class, 'patientInfusions'])->name('ward.patient-infusions');
    Route::get('/ward-dashboard/patient-pump-link', [InfusionIntegrationController::class, 'patientPumpLink'])->name('ward.patient-pump-link');
    Route::post('/ward-dashboard/link-pump', [InfusionIntegrationController::class, 'linkPumpToPatient'])->name('ward.link-pump');
    Route::post('/ward-dashboard/link-pump-by-device-id', [InfusionIntegrationController::class, 'linkPumpByDeviceId'])->name('ward.link-pump-by-device-id');
    Route::post('/ward-dashboard/unlink-pump/{pump}', [InfusionIntegrationController::class, 'unlinkPumpFromPatient'])->name('ward.unlink-pump');

    // ECG Routes
    Route::get('/ecg', [EcgController::class, 'index'])->name('ecg.index');
    Route::get('/ecg/patient', [EcgController::class, 'patientEcg'])->name('ecg.patient');
    Route::get('/ecg/pdf', [EcgController::class, 'servePdf'])->name('ecg.pdf');
    Route::get('/ecg/list', [EcgController::class, 'listFiles'])->name('ecg.list');

    // EKad Integration Routes (SEEKINK E-Ink)
    Route::get('/ekad', [EkadController::class, 'index'])->name('ekad.index');
    Route::post('/ekad/login', [EkadController::class, 'testLogin'])->name('ekad.login');
    Route::post('/ekad/push', [EkadController::class, 'pushPatientInfo'])->name('ekad.push');
    Route::get('/ekad/configuration', [EkadController::class, 'getConfiguration'])->name('ekad.configuration');
    Route::post('/ekad/configuration', [EkadController::class, 'saveConfiguration'])->name('ekad.configuration.save');
    Route::get('/ekad/bed-mappings', [EkadController::class, 'getBedMappings'])->name('ekad.bed-mappings');
    Route::post('/ekad/bed-mappings', [EkadController::class, 'storeBedMapping'])->name('ekad.bed-mappings.store');
    Route::put('/ekad/bed-mappings/{mapping}', [EkadController::class, 'updateBedMapping'])->name('ekad.bed-mappings.update');
    Route::delete('/ekad/bed-mappings/{mapping}', [EkadController::class, 'destroyBedMapping'])->name('ekad.bed-mappings.destroy');
    Route::post('/ekad/preview-masking', [EkadController::class, 'previewMasking'])->name('ekad.preview-masking');
    Route::get('/ekad/activity-logs', [EkadController::class, 'getActivityLogs'])->name('ekad.activity-logs');
    Route::get('/ekad/response-logs', [EkadController::class, 'getResponseLogs'])->name('ekad.response-logs');
    Route::post('/ekad/sync-all', [EkadController::class, 'syncAll'])->name('ekad.sync-all');


    // ADT Integration Routes
    Route::get('/adt', [AdtConfigurationController::class, 'index'])->name('adt.index');
    Route::put('/adt/configuration', [AdtConfigurationController::class, 'updateConfiguration'])->name('adt.update-configuration');
    Route::get('/adt/test-connection', [AdtConfigurationController::class, 'testConnection'])->name('adt.test-connection');
    Route::get('/adt/logs', [AdtConfigurationController::class, 'getLogs'])->name('adt.logs');
    Route::get('/adt/logs/{log}', [AdtConfigurationController::class, 'viewLog'])->name('adt.logs.view');
    Route::post('/adt/logs/clear', [AdtConfigurationController::class, 'clearLogs'])->name('adt.logs.clear');
    Route::get('/adt/log-files', [AdtConfigurationController::class, 'readLogFiles'])->name('adt.log-files');
    Route::post('/adt/hospital-mapping', [AdtConfigurationController::class, 'storeHospitalMapping'])->name('adt.hospital-mapping.store');
    Route::delete('/adt/hospital-mapping/{hospitalMapping}', [AdtConfigurationController::class, 'destroyHospitalMapping'])->name('adt.hospital-mapping.destroy');
    Route::post('/adt/ward-mapping', [AdtConfigurationController::class, 'storeWardMapping'])->name('adt.ward-mapping.store');
    Route::delete('/adt/ward-mapping/{wardMapping}', [AdtConfigurationController::class, 'destroyWardMapping'])->name('adt.ward-mapping.destroy');
    Route::post('/adt/bed-mapping', [AdtConfigurationController::class, 'storeBedMapping'])->name('adt.bed-mapping.store');
    Route::delete('/adt/bed-mapping/{bedMapping}', [AdtConfigurationController::class, 'destroyBedMapping'])->name('adt.bed-mapping.destroy');
    Route::post('/adt/doctor-mapping', [AdtConfigurationController::class, 'storeDoctorMapping'])->name('adt.doctor-mapping.store');
    Route::delete('/adt/doctor-mapping/{doctorMapping}', [AdtConfigurationController::class, 'destroyDoctorMapping'])->name('adt.doctor-mapping.destroy');
    Route::post('/adt/diet-mapping', [AdtConfigurationController::class, 'storeDietMapping'])->name('adt.diet-mapping.store');
    Route::delete('/adt/diet-mapping/{dietMapping}', [AdtConfigurationController::class, 'destroyDietMapping'])->name('adt.diet-mapping.destroy');
    Route::post('/adt/isolation-mapping', [AdtConfigurationController::class, 'storeIsolationMapping'])->name('adt.isolation-mapping.store');
    Route::delete('/adt/isolation-mapping/{isolationMapping}', [AdtConfigurationController::class, 'destroyIsolationMapping'])->name('adt.isolation-mapping.destroy');
    Route::get('/adt/mappings-frame', [AdtConfigurationController::class, 'mappingsFrame'])->name('adt.mappings.frame');
    Route::get('/adt-test', [AdtConfigurationController::class, 'testPage'])->name('adt.test');
    Route::post('/adt-test/send', [AdtConfigurationController::class, 'sendTestMessage'])->name('adt.test.send');
});

// Public API Routes for Vital Sign Gateway (no CSRF, no auth)
Route::prefix('api/vital-sign')->group(function () {
    Route::post('/login', [VitalSignIntegrationController::class, 'apiLogin']);
    Route::post('/logout', [VitalSignIntegrationController::class, 'apiLogout']);
    Route::post('/reading', [VitalSignIntegrationController::class, 'apiReceiveSingleVitalSign']);
    Route::post('/readings', [VitalSignIntegrationController::class, 'apiReceiveVitalSigns']);
});

// Public API Routes for Infusion Pump Gateway (no CSRF, no auth)
Route::prefix('api/infusion')->group(function () {
    Route::post('/login', [InfusionIntegrationController::class, 'apiLogin']);
    Route::post('/logout', [InfusionIntegrationController::class, 'apiLogout']);
    Route::post('/status', [InfusionIntegrationController::class, 'apiReceiveStatus']);
    Route::post('/batch-status', [InfusionIntegrationController::class, 'apiReceiveBatchStatus']);
});

// Public API Routes for ADT HL7 Integration (no CSRF, no auth)
Route::prefix('api/adt')->group(function () {
    Route::post('/message', [\App\Http\Controllers\AdtApiController::class, 'receiveMessage']);
    Route::get('/debug', [\App\Http\Controllers\AdtApiController::class, 'debug']);
});

// Public API V1 Routes for Vital Sign Gateway (Raspberry Pi - comennc5)
// Uses X-Passphrase header + username/password per-request authentication
Route::prefix('api/v1')->group(function () {
    Route::post('/vital-signs', [\App\Http\Controllers\VitalSignApiV1Controller::class, 'receiveVitalSigns']);
    Route::get('/patients/{patientCode}', [\App\Http\Controllers\VitalSignApiV1Controller::class, 'searchPatient']);
    Route::post('/device/login', [\App\Http\Controllers\VitalSignApiV1Controller::class, 'deviceLogin']);
    Route::post('/ping', [\App\Http\Controllers\VitalSignApiV1Controller::class, 'ping']);
    // Monitor devices list for mp5sc listener
    Route::get('/monitor-devices', [VitalSignIntegrationController::class, 'apiGetDevices']);
    Route::post('/monitor-devices/{device}/status', [VitalSignIntegrationController::class, 'apiUpdateDeviceStatus']);

    // Monitor status log endpoint
    Route::post('/monitor/status', [VitalSignIntegrationController::class, 'apiReceiveMonitorStatus']);
});

require __DIR__ . '/auth.php';
