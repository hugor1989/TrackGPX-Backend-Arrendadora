<?php

namespace App\Observers;

use App\Models\BillingCycle;

class BillingCycleObserver
{
    /**
     * Handle the BillingCycle "created" event.
     */
    public function created(BillingCycle $billingCycle): void
    {
        //
    }

    /**
     * Handle the BillingCycle "updated" event.
     */
    public function updated(BillingCycle $billingCycle): void
    {
        //
    }

    /**
     * Handle the BillingCycle "deleted" event.
     */
    public function deleted(BillingCycle $billingCycle): void
    {
        //
    }

    /**
     * Handle the BillingCycle "restored" event.
     */
    public function restored(BillingCycle $billingCycle): void
    {
        //
    }

    /**
     * Handle the BillingCycle "force deleted" event.
     */
    public function forceDeleted(BillingCycle $billingCycle): void
    {
        //
    }
}
