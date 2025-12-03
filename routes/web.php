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
use App\Http\Controllers\VitalSignController;
use App\Http\Controllers\LdapConfigurationController;
use App\Http\Controllers\VitalSignIntegrationController;
use App\Http\Controllers\InfusionIntegrationController;
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

    Route::resource('consultants', ConsultantController::class)->except(['show']);
    Route::post('consultants/{consultant}/deactivate', [ConsultantController::class, 'deactivate'])->name('consultants.deactivate');

    Route::resource('anaesthetists', AnaesthetistController::class)->except(['show']);
    Route::post('anaesthetists/{anaesthetist}/deactivate', [AnaesthetistController::class, 'deactivate'])->name('anaesthetists.deactivate');

    Route::resource('nurses', NurseController::class)->except(['show']);
    Route::post('nurses/{nurse}/deactivate', [NurseController::class, 'deactivate'])->name('nurses.deactivate');

    // Patient Routes
    Route::resource('patients', PatientController::class)->except(['show']);
    Route::post('patients/{patient}/deactivate', [PatientController::class, 'deactivate'])->name('patients.deactivate');

    // Ward Management Routes
    Route::resource('wards', WardController::class)->except(['show']);
    Route::post('wards/{ward}/deactivate', [WardController::class, 'deactivate'])->name('wards.deactivate');

    // Bed Management Routes
    Route::resource('beds', BedController::class)->except(['show']);
    Route::post('beds/{bed}/deactivate', [BedController::class, 'deactivate'])->name('beds.deactivate');

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

    // Infusion Integration Routes
    Route::get('/infusion-integration', [InfusionIntegrationController::class, 'index'])->name('infusion-integration.index');
    Route::post('/infusion-integration/api-user', [InfusionIntegrationController::class, 'storeApiUser'])->name('infusion-integration.api-user.store');
    Route::put('/infusion-integration/api-user/{apiUser}', [InfusionIntegrationController::class, 'updateApiUser'])->name('infusion-integration.api-user.update');
    Route::delete('/infusion-integration/api-user/{apiUser}', [InfusionIntegrationController::class, 'destroyApiUser'])->name('infusion-integration.api-user.destroy');
    Route::post('/infusion-integration/logs/clear', [InfusionIntegrationController::class, 'clearLogs'])->name('infusion-integration.logs.clear');

    // Ward Infusion Overview (iframe)
    Route::get('/ward-dashboard/infusion-overview', [InfusionIntegrationController::class, 'wardOverview'])->name('ward.infusion-overview');
    Route::get('/ward-dashboard/patient-infusions', [InfusionIntegrationController::class, 'patientInfusions'])->name('ward.patient-infusions');
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

require __DIR__.'/auth.php';
