<?php

namespace App\Observers\FluidBalance;

use App\Models\BloodTransfusion;
use App\Services\FluidBalanceLinks;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * A blood unit that finishes (from the ward dashboard or the nurse app) is
 * charted on the I/O chart. Charting is once per unit, so later saves of a
 * finished unit change nothing. A failure here never stops the unit
 * finishing.
 */
class BloodTransfusionObserver implements ShouldHandleEventsAfterCommit
{
    public function updated(BloodTransfusion $unit): void
    {
        if ($unit->isFinished()) {
            rescue(fn () => FluidBalanceLinks::recordTransfusion($unit));
        }
    }
}
