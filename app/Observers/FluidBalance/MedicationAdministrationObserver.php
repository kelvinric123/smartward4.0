<?php

namespace App\Observers\FluidBalance;

use App\Models\MedicationAdministration;
use App\Services\FluidBalanceLinks;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Auth;

/**
 * A dose given of an IV medication with a volume is charted on the I/O
 * chart, and struck out again when the dose record is undone. A failure
 * here never stops the dose being recorded.
 */
class MedicationAdministrationObserver implements ShouldHandleEventsAfterCommit
{
    public function created(MedicationAdministration $dose): void
    {
        rescue(fn () => FluidBalanceLinks::recordDose($dose));
    }

    public function deleted(MedicationAdministration $dose): void
    {
        rescue(fn () => FluidBalanceLinks::voidDose($dose, Auth::id()));
    }
}
