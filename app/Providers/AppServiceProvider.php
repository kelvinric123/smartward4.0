<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Bed;
use App\Models\Patient;
use App\Models\PatientCareProvider;
use App\Observers\BedObserver;
use App\Observers\PatientObserver;
use App\Observers\PatientCareProviderObserver;
use App\Listeners\UserActivityListener;
use Illuminate\Support\Facades\Event;
use App\Observers\ConfigObserver;
use App\Models\LdapConfiguration;
use App\Models\AdtConfiguration;
use App\Models\WardDashboardSetting;
use App\Models\ShiftSetting;
use App\Models\WardScheduleAssignment;
use App\Observers\WardScheduleAssignmentObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS in production
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');

            // Trust Nginx proxy
            $request = $this->app['request'];
            $request->setTrustedProxies(
                ['*'],
                \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_AWS_ELB
            );
        }

        // Register observers for EKAD E-Ink display updates
        // BedObserver: Triggers EKad push when patient_id changes (admit/transfer/discharge)
        Bed::observe(BedObserver::class);

        // PatientObserver: Triggers EKad push when patient info changes (diet, name, etc.)
        Patient::observe(PatientObserver::class);

        // PatientCareProviderObserver: Triggers EKad push when care providers change (anaesthetist updates)
        PatientCareProvider::observe(PatientCareProviderObserver::class);

        // WardScheduleAssignmentObserver: Triggers EKad push when nurse schedule changes
        WardScheduleAssignment::observe(WardScheduleAssignmentObserver::class);

        // Register User Activity Subscriber
        Event::subscribe(UserActivityListener::class);

        // Register Config Observers
        LdapConfiguration::observe(ConfigObserver::class);
        AdtConfiguration::observe(ConfigObserver::class);
        WardDashboardSetting::observe(ConfigObserver::class);
        ShiftSetting::observe(ConfigObserver::class);

        // The I/O chart fills itself from blood units, IV doses, pump infusions
        // and consultant orders with a fluid restriction (App\Services\FluidBalanceLinks)
        \App\Models\BloodTransfusion::observe(\App\Observers\FluidBalance\BloodTransfusionObserver::class);
        \App\Models\MedicationAdministration::observe(\App\Observers\FluidBalance\MedicationAdministrationObserver::class);
        \App\Models\Infusion::observe(\App\Observers\FluidBalance\InfusionObserver::class);
        \App\Models\ConsultantOrder::observe(\App\Observers\FluidBalance\ConsultantOrderObserver::class);
    }
}
