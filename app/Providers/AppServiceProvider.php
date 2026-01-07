<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Bed;
use App\Models\Patient;
use App\Models\PatientCareProvider;
use App\Observers\BedObserver;
use App\Observers\PatientObserver;
use App\Observers\PatientCareProviderObserver;

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
    }
}
