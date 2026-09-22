<?php

namespace App\Observers\FluidBalance;

use App\Models\ConsultantOrder;
use App\Services\FluidBalanceLinks;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * A consultant order with a fluid restriction sets the I/O fluid plan;
 * cancelling it lifts the restriction again. A failure here never stops the
 * order being saved.
 */
class ConsultantOrderObserver implements ShouldHandleEventsAfterCommit
{
    public function created(ConsultantOrder $order): void
    {
        if (FluidBalanceLinks::hasFluidRestriction($order)) {
            rescue(fn () => FluidBalanceLinks::applyOrder($order));
        }
    }

    /**
     * Lifting only happens while this order's plan is the one in force, so
     * later saves of the cancelled order change nothing.
     */
    public function updated(ConsultantOrder $order): void
    {
        if ($order->status === ConsultantOrder::STATUS_CANCELLED && FluidBalanceLinks::hasFluidRestriction($order)) {
            rescue(fn () => FluidBalanceLinks::liftOrder($order));
        }
    }
}
