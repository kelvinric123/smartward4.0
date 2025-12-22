<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Bed;
use App\Observers\BedObserver;

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
        // Register observers
        // BedObserver: triggers EKad push when patient_id changes (admit/transfer/discharge)
        Bed::observe(BedObserver::class);
    }
}
