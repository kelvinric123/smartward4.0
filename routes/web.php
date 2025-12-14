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
use App\Http\Controllers\ShiftSettingController;
use App\Http\Controllers\DietTypeController;
use App\Http\Controllers\IsolationTypeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

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

    Route::resource('anaesthetists', AnaesthetistController::class);
    Route::post('anaesthetists/{anaesthetist}/deactivate', [AnaesthetistController::class, 'deactivate'])->name('anaesthetists.deactivate');

    Route::resource('nurses', NurseController::class)->except(['show']);
    Route::post('nurses/{nurse}/deactivate', [NurseController::class, 'deactivate'])->name('nurses.deactivate');
    Route::get('nurses-bulk-upload', [NurseController::class, 'bulkUploadForm'])->name('nurses.bulk-upload');
    Route::post('nurses-bulk-upload/preview', [NurseController::class, 'bulkUploadPreview'])->name('nurses.bulk-upload.preview');
    Route::post('nurses-bulk-upload/confirm', [NurseController::class, 'bulkUploadConfirm'])->name('nurses.bulk-upload.confirm');

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
    Route::get('/ward-schedule', [WardScheduleController::class, 'index'])->name('ward.schedule');
    Route::post('/ward-schedule/assign', [WardScheduleController::class, 'assignNurses'])->name('ward.schedule.assign');
    Route::get('/ward-schedule/patient-details', [WardScheduleController::class, 'patientDetailsIframe'])->name('ward.schedule.patient-details');

    // Shift Settings Routes
    Route::get('/ward-schedule/shift-settings', [ShiftSettingController::class, 'index'])->name('ward.shift-settings');
    Route::post('/ward-schedule/shift-settings', [ShiftSettingController::class, 'update'])->name('ward.shift-settings.update');
    Route::post('/ward-schedule/shift-settings/reset', [ShiftSettingController::class, 'reset'])->name('ward.shift-settings.reset');

    // Ward Dashboard Routes
    Route::get('/ward-dashboard', [WardDashboardController::class, 'index'])->name('ward.dashboard');
    Route::post('/ward-dashboard/admit-patient', [WardDashboardController::class, 'admitPatient'])->name('ward.admit-patient');
    Route::post('/ward-dashboard/prebook-patient', [WardDashboardController::class, 'prebookPatient'])->name('ward.prebook-patient');
    Route::post('/ward-dashboard/check-in-prebook/{patient}', [WardDashboardController::class, 'checkInPrebook'])->name('ward.check-in-prebook');
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
    Route::post('/ward-dashboard/settings/clinical-options', [WardDashboardController::class, 'updateClinicalIndicatorOptions'])->name('ward.settings.clinical-options');

    // Vital Signs Routes
    Route::get('/vital-signs', [VitalSignController::class, 'index'])->name('vital-signs.index');
    Route::post('/vital-signs', [VitalSignController::class, 'store'])->name('vital-signs.store');
    Route::get('/vital-signs/patient', [VitalSignController::class, 'patientVitals'])->name('vital-signs.patient');
    Route::get('/vital-signs/latest', [VitalSignController::class, 'latestVitals'])->name('vital-signs.latest');

    // LDAP Integration Routes
    Route::get('/ldap', [LdapConfigurationController::class, 'index'])->name('ldap.index');
    Route::post('/ldap', [LdapConfigurationController::class, 'store'])->name('ldap.store');
    Route::put('/ldap/{ldapConfiguration}', [LdapConfigurationController::class, 'update'])->name('ldap.update');
    Route::delete('/ldap/{ldapConfiguration}', [LdapConfigurationController::class, 'destroy'])->name('ldap.destroy');
    Route::post('/ldap/test', [LdapConfigurationController::class, 'testConnection'])->name('ldap.test');
    Route::post('/ldap/{ldapConfiguration}/sync', [LdapConfigurationController::class, 'syncUsers'])->name('ldap.sync');
    Route::post('/ldap/{ldapConfiguration}/role-mapping', [LdapConfigurationController::class, 'storeRoleMapping'])->name('ldap.role-mapping.store');
    Route::delete('/ldap/role-mapping/{roleMapping}', [LdapConfigurationController::class, 'destroyRoleMapping'])->name('ldap.role-mapping.destroy');
    Route::get('/ldap/{ldapConfiguration}/groups', [LdapConfigurationController::class, 'fetchGroups'])->name('ldap.groups');

    // Vital Sign Integration Routes
    Route::get('/vital-sign-integration', [VitalSignIntegrationController::class, 'index'])->name('vital-sign-integration.index');
    Route::post('/vital-sign-integration/api-user', [VitalSignIntegrationController::class, 'storeApiUser'])->name('vital-sign-integration.api-user.store');
    Route::put('/vital-sign-integration/api-user/{apiUser}', [VitalSignIntegrationController::class, 'updateApiUser'])->name('vital-sign-integration.api-user.update');
    Route::delete('/vital-sign-integration/api-user/{apiUser}', [VitalSignIntegrationController::class, 'destroyApiUser'])->name('vital-sign-integration.api-user.destroy');
    Route::post('/vital-sign-integration/api-user/{apiUser}/regenerate-token', [VitalSignIntegrationController::class, 'regenerateToken'])->name('vital-sign-integration.api-user.regenerate-token');
    Route::get('/vital-sign-integration/logs', [VitalSignIntegrationController::class, 'getLogs'])->name('vital-sign-integration.logs');
    Route::post('/vital-sign-integration/logs/clear', [VitalSignIntegrationController::class, 'clearLogs'])->name('vital-sign-integration.logs.clear');
    
    // Monitor Device Routes (for mp5sc listener)
    Route::post('/vital-sign-integration/device', [VitalSignIntegrationController::class, 'storeDevice'])->name('vital-sign-integration.device.store');
    Route::put('/vital-sign-integration/device/{device}', [VitalSignIntegrationController::class, 'updateDevice'])->name('vital-sign-integration.device.update');
    Route::delete('/vital-sign-integration/device/{device}', [VitalSignIntegrationController::class, 'destroyDevice'])->name('vital-sign-integration.device.destroy');

    // Infusion Integration Routes
    Route::get('/infusion-integration', [InfusionIntegrationController::class, 'index'])->name('infusion-integration.index');
    Route::post('/infusion-integration/api-user', [InfusionIntegrationController::class, 'storeApiUser'])->name('infusion-integration.api-user.store');
    Route::put('/infusion-integration/api-user/{apiUser}', [InfusionIntegrationController::class, 'updateApiUser'])->name('infusion-integration.api-user.update');
    Route::delete('/infusion-integration/api-user/{apiUser}', [InfusionIntegrationController::class, 'destroyApiUser'])->name('infusion-integration.api-user.destroy');
    Route::post('/infusion-integration/logs/clear', [InfusionIntegrationController::class, 'clearLogs'])->name('infusion-integration.logs.clear');

    // Ward Infusion Overview (iframe)
    Route::get('/ward-dashboard/infusion-overview', [InfusionIntegrationController::class, 'wardOverview'])->name('ward.infusion-overview');
    Route::get('/ward-dashboard/patient-infusions', [InfusionIntegrationController::class, 'patientInfusions'])->name('ward.patient-infusions');

    // ECG Routes
    Route::get('/ecg', [EcgController::class, 'index'])->name('ecg.index');
    Route::get('/ecg/patient', [EcgController::class, 'patientEcg'])->name('ecg.patient');
    Route::get('/ecg/pdf', [EcgController::class, 'servePdf'])->name('ecg.pdf');
    Route::get('/ecg/list', [EcgController::class, 'listFiles'])->name('ecg.list');

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
    // Monitor devices list for mp5sc listener
    Route::get('/monitor-devices', [VitalSignIntegrationController::class, 'apiGetDevices']);
    Route::post('/monitor-devices/{device}/status', [VitalSignIntegrationController::class, 'apiUpdateDeviceStatus']);
});

require __DIR__.'/auth.php';
