<?php

namespace App\Observers\FluidBalance;

use App\Models\Infusion;
use App\Services\FluidBalanceLinks;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Pump updates chart what has been infused on the I/O chart. Every save is
 * looked at, since FluidBalanceLinks only charts new volume and at most
 * hourly. A failure here never rejects pump data.
 */
class InfusionObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(Infusion $infusion): void
    {
        rescue(fn () => FluidBalanceLinks::recordInfusion($infusion));
    }
}
