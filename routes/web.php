<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HospitalController;
use App\Http\Controllers\SpecialtyController;
use App\Http\Controllers\ConsultantController;
use App\Http\Controllers\AnaesthetistController;
use App\Http\Controllers\NurseController;
use App\Http\Controllers\NurseCredentialingController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\WardController;
use App\Http\Controllers\WardTypeController;
use App\Http\Controllers\ClinicalIndicatorController;
use App\Http\Controllers\BedController;
use App\Http\Controllers\WardDashboardController;
use App\Http\Controllers\MedicationMonitoringController;
use App\Http\Controllers\FluidBalanceController;
use App\Http\Controllers\DischargeSummaryController;
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
use App\Http\Controllers\UserActivityController;
use App\Http\Controllers\PatientFlowCommandCentreController;
use App\Http\Controllers\IntegrationDemoController;
use App\Http\Controllers\CommandCenterController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// LDAP Login Routes (must be outside auth middleware)
Route::get('/login/ldap', [LdapConfigurationController::class, 'showLdapLogin'])->name('login.ldap');
Route::post('/login/ldap', [LdapConfigurationController::class, 'ldapLogin'])->name('login.ldap.submit');

// Public ECG PDF Route (for PDF viewer in iframes - authentication handled by signed URL or session)
Route::get('/ecg/pdf/view', [EcgController::class, 'servePdf'])->name('ecg.pdf.public');

// Public Command Centre Routes (session-based auth, no Laravel auth)
Route::get('/command-centre/login', [PatientFlowCommandCentreController::class, 'showLogin'])->name('command-centre.login');
Route::post('/command-centre/login', [PatientFlowCommandCentreController::class, 'login'])->name('command-centre.login.submit');
Route::post('/command-centre/logout', [PatientFlowCommandCentreController::class, 'logout'])->name('command-centre.logout');
Route::get('/command-centre/{id}/view', [PatientFlowCommandCentreController::class, 'publicDashboard'])->name('command-centre.view');
Route::get('/command-centre/{id}/data', [PatientFlowCommandCentreController::class, 'publicDashboardData'])->name('command-centre.view.data');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // Command Center (Superadmin / Hospital Admin / IT Admin)
    Route::get('/command-center', [CommandCenterController::class, 'index'])->name('command-center.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/logos', [ProfileController::class, 'updateLogos'])->name('profile.logos.update');

    // Admin Management Routes
    Route::resource('hospitals', HospitalController::class)->except(['show']);
    Route::post('hospitals/{hospital}/deactivate', [HospitalController::class, 'deactivate'])->name('hospitals.deactivate');

    // Slideshow Management Routes
    Route::get('hospitals/{hospital}/slideshows', [\App\Http\Controllers\SlideshowController::class, 'index'])->name('hospitals.slideshows.index');
    Route::post('hospitals/{hospital}/slideshows', [\App\Http\Controllers\SlideshowController::class, 'store'])->name('hospitals.slideshows.store');
    Route::delete('hospitals/{hospital}/slideshows/{slideshow}', [\App\Http\Controllers\SlideshowController::class, 'destroy'])->name('hospitals.slideshows.destroy');

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
    Route::get('nurses/{nurse}/dashboard', [WardDashboardController::class, 'nurseDashboard'])->name('nurses.dashboard');
    Route::put('nurses/{nurse}/ldap-binding', [NurseController::class, 'updateLdapBinding'])->name('nurses.update-ldap-binding');
    Route::post('nurses/{nurse}/deactivate', [NurseController::class, 'deactivate'])->name('nurses.deactivate');
    Route::get('nurses-export', [NurseController::class, 'export'])->name('nurses.export');
    Route::get('nurses-export/count', [NurseController::class, 'exportCount'])->name('nurses.export.count');
    Route::get('nurses-bulk-upload', [NurseController::class, 'bulkUploadForm'])->name('nurses.bulk-upload');
    Route::post('nurses-bulk-upload/preview', [NurseController::class, 'bulkUploadPreview'])->name('nurses.bulk-upload.preview');
    Route::post('nurses-bulk-upload/confirm', [NurseController::class, 'bulkUploadConfirm'])->name('nurses.bulk-upload.confirm');

    // Credentialing and Privileging tab of the nurse edit page (a record only resolves under its own nurse)
    Route::scopeBindings()->group(function () {
        Route::post('nurses/{nurse}/credentials', [NurseCredentialingController::class, 'storeCredential'])->name('nurses.credentials.store');
        Route::put('nurses/{nurse}/credentials/{credential}', [NurseCredentialingController::class, 'updateCredential'])->name('nurses.credentials.update');
        Route::delete('nurses/{nurse}/credentials/{credential}', [NurseCredentialingController::class, 'destroyCredential'])->name('nurses.credentials.destroy');
        Route::post('nurses/{nurse}/privileges', [NurseCredentialingController::class, 'storePrivilege'])->name('nurses.privileges.store');
        Route::put('nurses/{nurse}/privileges/{privilege}', [NurseCredentialingController::class, 'updatePrivilege'])->name('nurses.privileges.update');
        Route::delete('nurses/{nurse}/privileges/{privilege}', [NurseCredentialingController::class, 'destroyPrivilege'])->name('nurses.privileges.destroy');
        Route::post('nurses/{nurse}/privilege-checklist', [NurseCredentialingController::class, 'saveChecklist'])->name('nurses.privileges.checklist');
    });

    Route::resource('users', \App\Http\Controllers\UsersController::class)->except(['show']);
    Route::post('users/{user}/update-role', [\App\Http\Controllers\UsersController::class, 'updateRole'])->name('users.update-role');
    Route::post('users/{user}/toggle-status', [\App\Http\Controllers\UsersController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::get('user-activities', [UserActivityController::class, 'index'])->name('user-activities.index');
    Route::get('user-activities/export', [UserActivityController::class, 'export'])->name('user-activities.export');

    // Patient Flow Command Centre Routes
    Route::resource('patient-flow-command-centres', PatientFlowCommandCentreController::class)->except(['show', 'destroy']);
    Route::post('patient-flow-command-centres/{commandCentre}/toggle-active', [PatientFlowCommandCentreController::class, 'toggleActive'])->name('patient-flow-command-centres.toggle-active');
    Route::get('patient-flow-command-centres/{commandCentre}/dashboard', [PatientFlowCommandCentreController::class, 'dashboard'])->name('patient-flow-command-centres.dashboard');
    Route::get('patient-flow-command-centres/{commandCentre}/dashboard-data', [PatientFlowCommandCentreController::class, 'dashboardData'])->name('patient-flow-command-centres.dashboard-data');
    Route::get('patient-flow-command-centres/{commandCentre}/settings', [PatientFlowCommandCentreController::class, 'settings'])->name('patient-flow-command-centres.settings');
    Route::post('patient-flow-command-centres/{commandCentre}/settings', [PatientFlowCommandCentreController::class, 'updateSettings'])->name('patient-flow-command-centres.settings.update');

    // Patient Additional Field Routes (inside Admin Management)
    Route::resource('diet-types', DietTypeController::class)->except(['show']);
    Route::post('diet-types/{diet_type}/toggle-active', [DietTypeController::class, 'toggleActive'])->name('diet-types.toggle-active');

    Route::resource('isolation-types', IsolationTypeController::class)->except(['show', 'index']);
    Route::post('isolation-types/{isolation_type}/toggle-active', [IsolationTypeController::class, 'toggleActive'])->name('isolation-types.toggle-active');

    // Patient Routes
    Route::resource('patients', PatientController::class);
    Route::post('patients/{patient}/deactivate', [PatientController::class, 'deactivate'])->name('patients.deactivate');

    // Ward Management Routes
    Route::resource('wards', WardController::class)->except(['show']);
    Route::post('wards/{ward}/deactivate', [WardController::class, 'deactivate'])->name('wards.deactivate');

    // Ward Type Routes (the index also hosts the Clinical Indicators tab)
    Route::resource('ward-types', WardTypeController::class)->except(['show']);
    Route::post('ward-types/{ward_type}/toggle-active', [WardTypeController::class, 'toggleActive'])->name('ward-types.toggle-active');

    // Clinical Indicator Routes
    Route::resource('clinical-indicators', ClinicalIndicatorController::class)->except(['show', 'index']);
    Route::post('clinical-indicators/{clinical_indicator}/toggle-active', [ClinicalIndicatorController::class, 'toggleActive'])->name('clinical-indicators.toggle-active');

    // Bed Management Routes
    Route::resource('beds', BedController::class)->except(['show']);
    Route::post('beds/{bed}/deactivate', [BedController::class, 'deactivate'])->name('beds.deactivate');
    Route::post('beds/{bed}/maintenance', [BedController::class, 'maintenance'])->name('beds.maintenance');

    // Discharge Summary Routes
    // {admission} is the admit / check-in admission_logs row that opened the stay.
    Route::get('/discharge-summaries', [DischargeSummaryController::class, 'index'])->name('discharge-summaries.index');
    Route::get('/discharge-summaries/{admission}', [DischargeSummaryController::class, 'show'])->name('discharge-summaries.show');
    Route::get('/discharge-summaries/{admission}/print', [DischargeSummaryController::class, 'print'])->name('discharge-summaries.print');
    // The same summary by patient, for the Patient Details "Discharge Summary" tab
    // (latest admission, or ?admission=<admission log id>)
    Route::get('/ward-dashboard/patients/{patient}/discharge-summary', [\App\Http\Controllers\PatientDischargeSummaryController::class, 'panel'])->name('ward.discharge-summary.panel');
    Route::get('/ward-dashboard/patients/{patient}/discharge-summary/print', [\App\Http\Controllers\PatientDischargeSummaryController::class, 'print'])->name('ward.discharge-summary.print');
    // Nursing Plan tab: the care plan (diagnoses, goals, interventions, per-shift evaluation)
    Route::post('/ward-dashboard/patients/{patient}/care-plan', [\App\Http\Controllers\NursingCarePlanController::class, 'store'])->name('ward.nursing-plan.store');
    Route::post('/ward-dashboard/care-plan/{item}/update', [\App\Http\Controllers\NursingCarePlanController::class, 'update'])->name('ward.nursing-plan.update');
    Route::post('/ward-dashboard/care-plan/{item}/evaluate', [\App\Http\Controllers\NursingCarePlanController::class, 'evaluate'])->name('ward.nursing-plan.evaluate');
    Route::post('/ward-dashboard/care-plan/{item}/close', [\App\Http\Controllers\NursingCarePlanController::class, 'close'])->name('ward.nursing-plan.close');

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
    Route::get('/ward-schedule/print', [WardScheduleController::class, 'printSchedule'])->name('ward.schedule.print');
    Route::get('/ward-schedule/special-duty', [WardScheduleController::class, 'specialDutyFrame'])->name('ward.schedule.special-duty');
    Route::post('/ward-schedule/special-duty', [WardScheduleController::class, 'saveSpecialDuty'])->name('ward.schedule.special-duty.save');

    // AI Nurse Schedule Routes (Schedule > AI Nurse Schedule): staff roster, bed assignment by workload, leave
    Route::get('/ward-schedule/ai', [\App\Http\Controllers\AiNurseScheduleController::class, 'index'])->name('ward.ai-schedule');
    Route::post('/ward-schedule/ai/generate', [\App\Http\Controllers\AiNurseScheduleController::class, 'generate'])->name('ward.ai-schedule.generate');
    Route::post('/ward-schedule/ai/cell', [\App\Http\Controllers\AiNurseScheduleController::class, 'updateCell'])->name('ward.ai-schedule.cell');
    Route::post('/ward-schedule/ai/clear', [\App\Http\Controllers\AiNurseScheduleController::class, 'clear'])->name('ward.ai-schedule.clear');
    Route::post('/ward-schedule/ai/import', [\App\Http\Controllers\AiNurseScheduleController::class, 'importSchedule'])->name('ward.ai-schedule.import');
    Route::post('/ward-schedule/ai/assign', [\App\Http\Controllers\AiNurseScheduleController::class, 'applyAssignments'])->name('ward.ai-schedule.assign');
    Route::post('/ward-schedule/ai/assign-week', [\App\Http\Controllers\AiNurseScheduleController::class, 'applyWeek'])->name('ward.ai-schedule.assign-week');
    Route::post('/ward-schedule/ai/rules', [\App\Http\Controllers\AiNurseScheduleController::class, 'updateRules'])->name('ward.ai-schedule.rules');
    Route::post('/ward-schedule/ai/weights', [\App\Http\Controllers\AiNurseScheduleController::class, 'updateWeights'])->name('ward.ai-schedule.weights');
    Route::post('/ward-schedule/ai/weights/reset', [\App\Http\Controllers\AiNurseScheduleController::class, 'resetWeights'])->name('ward.ai-schedule.weights.reset');
    Route::post('/ward-schedule/ai/leaves', [\App\Http\Controllers\AiNurseScheduleController::class, 'storeLeave'])->name('ward.ai-schedule.leaves.store');
    Route::delete('/ward-schedule/ai/leaves/{leave}', [\App\Http\Controllers\AiNurseScheduleController::class, 'destroyLeave'])->name('ward.ai-schedule.leaves.destroy');
    Route::post('/ward-schedule/ai/holidays', [\App\Http\Controllers\AiNurseScheduleController::class, 'storeHoliday'])->name('ward.ai-schedule.holidays.store');
    Route::delete('/ward-schedule/ai/holidays/{holiday}', [\App\Http\Controllers\AiNurseScheduleController::class, 'destroyHoliday'])->name('ward.ai-schedule.holidays.destroy');

    // Shift Settings Routes
    Route::get('/ward-schedule/shift-settings', [ShiftSettingController::class, 'index'])->name('ward.shift-settings');
    Route::post('/ward-schedule/shift-settings', [ShiftSettingController::class, 'update'])->name('ward.shift-settings.update');
    Route::post('/ward-schedule/shift-settings/reset', [ShiftSettingController::class, 'reset'])->name('ward.shift-settings.reset');

    // Ward Dashboard Routes
    Route::get('/ward-dashboard', [WardDashboardController::class, 'index'])->name('ward.dashboard');
    // Critical Care Ward Dashboard: the ward dashboard for wards whose ward type is marked critical care
    Route::get('/critical-care-dashboard', [\App\Http\Controllers\CriticalCareDashboardController::class, 'index'])->name('critical-care.dashboard');
    Route::post('/ward-dashboard/admit-patient', [WardDashboardController::class, 'admitPatient'])->name('ward.admit-patient');
    Route::post('/ward-dashboard/prebook-patient', [WardDashboardController::class, 'prebookPatient'])->name('ward.prebook-patient');
    Route::post('/ward-dashboard/check-in-prebook/{patient}', [WardDashboardController::class, 'checkInPrebook'])->name('ward.check-in-prebook');
    Route::post('/ward-dashboard/cancel-prebook/{patient}', [WardDashboardController::class, 'cancelPrebook'])->name('ward.cancel-prebook');
    Route::get('/ward-dashboard/admission-logs', [\App\Http\Controllers\AdmissionLogController::class, 'index'])->name('ward.admission-logs');
    Route::get('/ward-dashboard/admission-logs/summary', [\App\Http\Controllers\AdmissionLogController::class, 'summary'])->name('ward.admission-logs.summary');
    Route::get('/ward-dashboard/admission-logs/print', [\App\Http\Controllers\AdmissionLogController::class, 'print'])->name('ward.admission-logs.print');
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
    Route::post('/ward-dashboard/schedule-discharge', [WardDashboardController::class, 'scheduleDischarge'])->name('ward.schedule-discharge');
    Route::post('/ward-dashboard/cancel-scheduled-discharge', [WardDashboardController::class, 'cancelScheduledDischarge'])->name('ward.cancel-scheduled-discharge');
    Route::post('/ward-dashboard/care-providers', [WardDashboardController::class, 'storeCareProvider'])->name('ward.care-providers.store');
    Route::post('/ward-dashboard/care-providers/anaesthetist', [WardDashboardController::class, 'storeAnaesthetistCareProvider'])->name('ward.care-providers.store-anaesthetist');
    Route::delete('/ward-dashboard/care-providers/{careProvider}', [WardDashboardController::class, 'destroyCareProvider'])->name('ward.care-providers.destroy');
    Route::post('/ward-dashboard/update-patient-clinical', [WardDashboardController::class, 'updatePatientClinical'])->name('ward.update-patient-clinical');
    Route::post('/ward-dashboard/save-sugar-reading', [WardDashboardController::class, 'saveSugarReading'])->name('ward.save-sugar-reading');
    Route::post('/ward-dashboard/clinical-indicator-score', [WardDashboardController::class, 'storeClinicalIndicatorScore'])->name('ward.clinical-indicator-score.store');

    // Blood Transfusion Routes
    Route::post('/ward-dashboard/blood-transfusions', [WardDashboardController::class, 'storeBloodTransfusion'])->name('ward.blood-transfusions.store');
    Route::post('/ward-dashboard/blood-transfusions/{transfusion}/checklist', [WardDashboardController::class, 'updateBloodTransfusionChecklist'])->name('ward.blood-transfusions.checklist');
    Route::post('/ward-dashboard/blood-transfusions/{transfusion}/start', [WardDashboardController::class, 'startBloodTransfusion'])->name('ward.blood-transfusions.start');
    Route::post('/ward-dashboard/blood-transfusions/{transfusion}/finish', [WardDashboardController::class, 'finishBloodTransfusion'])->name('ward.blood-transfusions.finish');

    // Consultant Orders Routes (Patient Details > Consultant Orders tab)
    Route::post('/ward-dashboard/consultant-orders', [\App\Http\Controllers\ConsultantOrderController::class, 'store'])->name('ward.consultant-orders.store');
    Route::post('/ward-dashboard/consultant-orders/handover', [\App\Http\Controllers\ConsultantOrderController::class, 'handover'])->name('ward.consultant-orders.handover');
    Route::post('/ward-dashboard/consultant-orders/{consultantOrder}/complete', [\App\Http\Controllers\ConsultantOrderController::class, 'complete'])->name('ward.consultant-orders.complete');
    Route::post('/ward-dashboard/consultant-orders/{consultantOrder}/cancel', [\App\Http\Controllers\ConsultantOrderController::class, 'cancel'])->name('ward.consultant-orders.cancel');

    // Medication Monitoring Routes (Patient Details > Medications tab)
    Route::post('/ward-dashboard/medications', [MedicationMonitoringController::class, 'store'])->name('ward.medications.store');
    Route::post('/ward-dashboard/medications/{patientMedication}/administer', [MedicationMonitoringController::class, 'administer'])->name('ward.medications.administer');
    Route::post('/ward-dashboard/medications/{patientMedication}/stop', [MedicationMonitoringController::class, 'stop'])->name('ward.medications.stop');
    Route::post('/ward-dashboard/medication-administrations/{administration}/undo', [MedicationMonitoringController::class, 'undo'])->name('ward.medications.undo');

    // I/O Chart Routes (Patient Details > I/O Chart tab)
    Route::post('/ward-dashboard/fluid-balance/entries', [FluidBalanceController::class, 'store'])->name('ward.fluid-balance.store');
    Route::post('/ward-dashboard/fluid-balance/entries/{entry}/void', [FluidBalanceController::class, 'void'])->name('ward.fluid-balance.void');
    Route::post('/ward-dashboard/fluid-balance/plan', [FluidBalanceController::class, 'savePlan'])->name('ward.fluid-balance.plan');
    Route::post('/ward-dashboard/fluid-balance/assessments', [FluidBalanceController::class, 'storeAssessment'])->name('ward.fluid-balance.assessments.store');

    Route::post('/ward-dashboard/settings/clinical-options', [WardDashboardController::class, 'updateClinicalIndicatorOptions'])->name('ward.settings.clinical-options');
    Route::get('/ward-dashboard/slideshow-viewer', [WardDashboardController::class, 'slideshowViewer'])->name('ward.slideshow-viewer');

    // Ward Notification Routes
    Route::get('/ward-dashboard/notifications', [WardDashboardController::class, 'getNotifications'])->name('ward.notifications');
    Route::post('/ward-dashboard/notifications/{notification}/respond', [WardDashboardController::class, 'respondNotification'])->name('ward.notifications.respond');

    // Vital Signs Routes
    Route::get('/vital-signs', [VitalSignController::class, 'index'])->name('vital-signs.index');
    Route::post('/vital-signs', [VitalSignController::class, 'store'])->name('vital-signs.store');
    Route::put('/vital-signs/{vitalSign}', [VitalSignController::class, 'update'])->name('vital-signs.update');
    Route::delete('/vital-signs/{vitalSign}', [VitalSignController::class, 'destroy'])->name('vital-signs.destroy');
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
    Route::get('/vital-sign-integration/logs/table', [VitalSignIntegrationController::class, 'logsTable'])->name('vital-sign-integration.logs.table');
    Route::post('/vital-sign-integration/logs/clear', [VitalSignIntegrationController::class, 'clearLogs'])->name('vital-sign-integration.logs.clear');
    Route::get('/vital-sign-integration/logs/export', [VitalSignIntegrationController::class, 'exportLogs'])->name('vital-sign-integration.logs.export');
    Route::get('/vital-sign-integration/logs/print', [VitalSignIntegrationController::class, 'printLogs'])->name('vital-sign-integration.logs.print');

    // Qmed Gateway Routes
    Route::post('/vital-sign-integration/gateway', [VitalSignIntegrationController::class, 'storeGateway'])->name('vital-sign-integration.gateway.store');
    Route::put('/vital-sign-integration/gateway/{gateway}', [VitalSignIntegrationController::class, 'updateGateway'])->name('vital-sign-integration.gateway.update');
    Route::delete('/vital-sign-integration/gateway/{gateway}', [VitalSignIntegrationController::class, 'destroyGateway'])->name('vital-sign-integration.gateway.destroy');
    Route::post('/vital-sign-integration/gateway/{gateway}/ping', [VitalSignIntegrationController::class, 'pingGateway'])->name('vital-sign-integration.gateway.ping');

    // Monitor Device Routes (for mp5sc listener) - REMOVED
    // Route::post('/vital-sign-integration/device', [VitalSignIntegrationController::class, 'storeDevice'])->name('vital-sign-integration.device.store');
    // ...

    // Infusion Integration Routes (B.Braun HL7/MLLP)
    Route::get('/infusion-integration', [InfusionIntegrationController::class, 'index'])->name('infusion-integration.index');
    Route::get('/infusion-integration/export', [InfusionIntegrationController::class, 'export'])->name('infusion-integration.export');
    Route::post('/infusion-integration/pump', [InfusionIntegrationController::class, 'storePump'])->name('infusion-integration.pump.store');
    Route::put('/infusion-integration/pump/{pump}', [InfusionIntegrationController::class, 'updatePump'])->name('infusion-integration.pump.update');
    Route::delete('/infusion-integration/pump/{pump}', [InfusionIntegrationController::class, 'destroyPump'])->name('infusion-integration.pump.destroy');
    Route::get('/infusion-integration/pump/{pump}/status', [InfusionIntegrationController::class, 'pumpStatus'])->name('infusion-integration.pump.status');
    Route::get('/infusion-integration/pump/{pump}/hl7', [InfusionIntegrationController::class, 'pumpHl7Messages'])->name('infusion-integration.pump.hl7');
    Route::post('/infusion-integration/logs/clear', [InfusionIntegrationController::class, 'clearLogs'])->name('infusion-integration.logs.clear');
    Route::post('/infusion-integration/settings', [InfusionIntegrationController::class, 'saveIntegrationSettings'])->name('infusion-integration.settings.save');
    Route::post('/infusion-integration/engine/test', [InfusionIntegrationController::class, 'testEngineConnection'])->name('infusion-integration.engine.test');
    Route::get('/infusion-integration/engine/pump-lookup', [InfusionIntegrationController::class, 'engineLookupPump'])->name('infusion-integration.engine.pump-lookup');

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
    Route::post('/ecg/upload', [EcgController::class, 'uploadPdf'])->name('ecg.upload');
    Route::post('/ecg/delete', [EcgController::class, 'deleteEcg'])->name('ecg.delete');

    // EKad (SEEKINK E-Ink) Routes
    Route::get('/ekad', [EkadController::class, 'index'])->name('ekad.index');
    Route::get('/ekad/config', [EkadController::class, 'getConfiguration'])->name('ekad.configuration');
    Route::post('/ekad/config', [EkadController::class, 'saveConfiguration'])->name('ekad.configuration.save');
    Route::post('/ekad/test-login', [EkadController::class, 'testLogin'])->name('ekad.login');
    Route::get('/ekad/bed-mappings', [EkadController::class, 'getBedMappings'])->name('ekad.bed-mappings');
    Route::post('/ekad/bed-mappings', [EkadController::class, 'storeBedMapping'])->name('ekad.bed-mappings.store');
    Route::put('/ekad/bed-mappings/{mapping}', [EkadController::class, 'updateBedMapping'])->name('ekad.bed-mappings.update');
    Route::delete('/ekad/bed-mappings/{mapping}', [EkadController::class, 'destroyBedMapping'])->name('ekad.bed-mappings.destroy');
    Route::post('/ekad/push-patient', [EkadController::class, 'pushPatientInfo'])->name('ekad.push');
    Route::post('/ekad/preview-masking', [EkadController::class, 'previewMasking'])->name('ekad.preview-masking');
    Route::get('/ekad/activity-logs', [EkadController::class, 'getActivityLogs'])->name('ekad.activity-logs');
    Route::get('/ekad/response-logs', [EkadController::class, 'getResponseLogs'])->name('ekad.response-logs');
    Route::get('/ekad/response-logs/export', [EkadController::class, 'exportResponseLogs'])->name('ekad.response-logs.export');
    Route::post('/ekad/sync-all', [EkadController::class, 'syncAll'])->name('ekad.sync-all');
    Route::get('/ekad/sync-preview', [EkadController::class, 'syncPreview'])->name('ekad.sync-preview');
    Route::post('/ekad/sync-selected', [EkadController::class, 'syncSelected'])->name('ekad.sync-selected');


    // ADT Integration Routes
    Route::get('/adt', [AdtConfigurationController::class, 'index'])->name('adt.index');
    Route::put('/adt/configuration', [AdtConfigurationController::class, 'updateConfiguration'])->name('adt.update-configuration');
    Route::get('/adt/test-connection', [AdtConfigurationController::class, 'testConnection'])->name('adt.test-connection');
    Route::get('/adt/logs', [AdtConfigurationController::class, 'getLogs'])->name('adt.logs');
    Route::get('/adt/logs/{log}', [AdtConfigurationController::class, 'viewLog'])->name('adt.logs.view');
    Route::delete('/adt/logs/clear', [AdtConfigurationController::class, 'clearLogs'])->name('adt.logs.clear');
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
    Route::get('/adt/replay-preview', [AdtConfigurationController::class, 'replayAdtPreview'])->name('adt.replay-preview');
    Route::post('/adt/replay-execute', [AdtConfigurationController::class, 'replayAdtExecute'])->name('adt.replay-execute');
    Route::get('/adt-test', [AdtConfigurationController::class, 'testPage'])->name('adt.test');
    Route::post('/adt-test/send', [AdtConfigurationController::class, 'sendTestMessage'])->name('adt.test.send');

    // Demo Integration Routes
    Route::get('/integration/demo', [IntegrationDemoController::class, 'index'])->name('integration.demo.index');
    Route::post('/integration/demo/seed-patients', [IntegrationDemoController::class, 'seedPatients'])->name('integration.demo.seed-patients');
    Route::post('/integration/demo/seed-vital-signs', [IntegrationDemoController::class, 'seedVitalSigns'])->name('integration.demo.seed-vital-signs');
    Route::post('/integration/demo/seed-infusion', [IntegrationDemoController::class, 'seedInfusion'])->name('integration.demo.seed-infusion');
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
    Route::get('/pumps', [InfusionIntegrationController::class, 'apiListPumps']);
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
    // Ward list (for setup.sh ward selection)
    Route::get('/wards', [\App\Http\Controllers\VitalSignApiV1Controller::class, 'wards']);
    // Gateway registration (server-assigned name) + heartbeat / health telemetry (mp5sc_v2)
    Route::post('/gateway/register', [\App\Http\Controllers\VitalSignApiV1Controller::class, 'registerGateway']);
    Route::post('/gateway/heartbeat', [\App\Http\Controllers\VitalSignApiV1Controller::class, 'heartbeat']);
    // ECG recordings forwarded by ECG gateways (gateway/ecg, Philips TC35)
    Route::post('/ecg', [\App\Http\Controllers\VitalSignApiV1Controller::class, 'receiveEcg']);
    // Monitor devices list for mp5sc listener
    Route::get('/monitor-devices', [VitalSignIntegrationController::class, 'apiGetDevices']);
    Route::post('/monitor-devices/{device}/status', [VitalSignIntegrationController::class, 'apiUpdateDeviceStatus']);

    // Monitor status log endpoint
    Route::post('/monitor/status', [VitalSignIntegrationController::class, 'apiReceiveMonitorStatus']);
});

// Public API Routes for the Doctor mobile app (token auth, no CSRF)
Route::prefix('api/doctor')->group(function () {
    Route::post('/ping', [\App\Http\Controllers\DoctorAppApiController::class, 'ping']);
    Route::get('/ping', [\App\Http\Controllers\DoctorAppApiController::class, 'ping']);
    Route::post('/login', [\App\Http\Controllers\DoctorAppApiController::class, 'login']);
    Route::post('/logout', [\App\Http\Controllers\DoctorAppApiController::class, 'logout']);
    Route::get('/dashboard', [\App\Http\Controllers\DoctorAppApiController::class, 'dashboard']);
    Route::get('/patients/{patient}/notes', [\App\Http\Controllers\DoctorAppApiController::class, 'listNotes']);
    Route::post('/patients/{patient}/notes', [\App\Http\Controllers\DoctorAppApiController::class, 'addNote']);
    // Patient chart: I/O, medications and consultant orders
    Route::get('/patients/{patient}/chart', [\App\Http\Controllers\DoctorAppPatientController::class, 'show']);
    Route::post('/patients/{patient}/orders', [\App\Http\Controllers\DoctorAppPatientController::class, 'storeOrder']);
    Route::post('/patients/{patient}/orders/{order}/cancel', [\App\Http\Controllers\DoctorAppPatientController::class, 'cancelOrder']);
});

// Public API Routes for the Nurse mobile app (token auth, no CSRF)
Route::prefix('api/nurse')->group(function () {
    Route::post('/ping', [\App\Http\Controllers\NurseAppApiController::class, 'ping']);
    Route::get('/ping', [\App\Http\Controllers\NurseAppApiController::class, 'ping']);
    Route::post('/login', [\App\Http\Controllers\NurseAppApiController::class, 'login']);
    Route::post('/logout', [\App\Http\Controllers\NurseAppApiController::class, 'logout']);
    Route::get('/dashboard', [\App\Http\Controllers\NurseAppApiController::class, 'dashboard']);

    // One patient: orders, I/O chart, doses, infusions, alerts, stay timeline
    Route::get('/patients/{patient}', [\App\Http\Controllers\NurseAppPatientController::class, 'show']);
    Route::get('/patients/{patient}/timeline', [\App\Http\Controllers\NurseAppPatientController::class, 'timeline']);
    Route::post('/patients/{patient}/orders', [\App\Http\Controllers\NurseAppPatientController::class, 'storeOrder']);
    Route::post('/patients/{patient}/orders/handover', [\App\Http\Controllers\NurseAppPatientController::class, 'handoverOrders']);
    Route::post('/patients/{patient}/orders/{order}/complete', [\App\Http\Controllers\NurseAppPatientController::class, 'completeOrder']);
    Route::post('/patients/{patient}/orders/{order}/cancel', [\App\Http\Controllers\NurseAppPatientController::class, 'cancelOrder']);
    Route::post('/patients/{patient}/io/entries', [\App\Http\Controllers\NurseAppPatientController::class, 'storeIoEntry']);
    Route::post('/patients/{patient}/io/entries/{entry}/void', [\App\Http\Controllers\NurseAppPatientController::class, 'voidIoEntry']);
    Route::post('/patients/{patient}/io/plan', [\App\Http\Controllers\NurseAppPatientController::class, 'saveIoPlan']);
    Route::post('/patients/{patient}/io/assessments', [\App\Http\Controllers\NurseAppPatientController::class, 'storeIoAssessment']);
    Route::post('/patients/{patient}/medications/{medication}/doses', [\App\Http\Controllers\NurseAppPatientController::class, 'recordDose']);
    Route::post('/patients/{patient}/transfusions', [\App\Http\Controllers\NurseAppPatientController::class, 'storeTransfusion']);
    Route::post('/patients/{patient}/transfusions/{transfusion}/checklist', [\App\Http\Controllers\NurseAppPatientController::class, 'updateTransfusionChecklist']);
    Route::post('/patients/{patient}/transfusions/{transfusion}/start', [\App\Http\Controllers\NurseAppPatientController::class, 'startTransfusion']);
    Route::post('/patients/{patient}/transfusions/{transfusion}/finish', [\App\Http\Controllers\NurseAppPatientController::class, 'finishTransfusion']);
    Route::post('/patients/{patient}/care-plan', [\App\Http\Controllers\NurseAppPatientController::class, 'storeCarePlanItem']);
    Route::post('/patients/{patient}/care-plan/{item}/update', [\App\Http\Controllers\NurseAppPatientController::class, 'updateCarePlanItem']);
    Route::post('/patients/{patient}/care-plan/{item}/evaluate', [\App\Http\Controllers\NurseAppPatientController::class, 'evaluateCarePlanItem']);
    Route::post('/patients/{patient}/care-plan/{item}/close', [\App\Http\Controllers\NurseAppPatientController::class, 'closeCarePlanItem']);
    Route::post('/notifications/{notification}/respond', [\App\Http\Controllers\NurseAppPatientController::class, 'respondNotification']);
});

// Public API Routes for the bedside Patient Information Terminal (no CSRF)
Route::prefix('api/terminal')->group(function () {
    Route::get('/ping', [\App\Http\Controllers\TerminalApiController::class, 'ping']);
    Route::post('/ping', [\App\Http\Controllers\TerminalApiController::class, 'ping']);
    Route::get('/wards', [\App\Http\Controllers\TerminalApiController::class, 'wards']);
    Route::get('/beds', [\App\Http\Controllers\TerminalApiController::class, 'beds']);
    Route::get('/beds/{bed}/snapshot', [\App\Http\Controllers\TerminalApiController::class, 'snapshot']);

    // Patient smart call -> ward dashboard notifications
    Route::get('/beds/{bed}/requests', [\App\Http\Controllers\TerminalApiController::class, 'listRequests']);
    Route::post('/beds/{bed}/requests', [\App\Http\Controllers\TerminalApiController::class, 'storeRequest']);
});

// Bedside Patient Information Terminal web app (static SPA build in public/terminal).
// Deep links (e.g. /terminal/care/vitals) all serve the SPA's index.html;
// real asset files under public/terminal/ are served directly by the web server.
Route::get('/terminal/{any?}', function () {
    $index = public_path('terminal/index.html');
    if (!\Illuminate\Support\Facades\File::exists($index)) {
        abort(404, 'Patient terminal build not deployed. Copy the terminal dist folder to public/terminal.');
    }
    return response()->file($index);
})->where('any', '.*');

// Route to serve static slideshow pictures from base_path('picture')
Route::get('/picture-slides/{filename}', function ($filename) {
    $path = base_path('picture/' . $filename);
    if (!\Illuminate\Support\Facades\File::exists($path)) {
        abort(404);
    }
    $file = \Illuminate\Support\Facades\File::get($path);
    $type = \Illuminate\Support\Facades\File::mimeType($path);
    return response($file, 200)->header("Content-Type", $type);
})->name('picture.serve');

require __DIR__ . '/auth.php';
