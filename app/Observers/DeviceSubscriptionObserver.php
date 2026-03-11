<?php

namespace App\Observers;

use App\Models\DeviceSubscription;

class DeviceSubscriptionObserver
{
    /**
     * Handle the DeviceSubscription "created" event.
     */
    public function created(DeviceSubscription $deviceSubscription): void
    {
        //
    }

    /**
     * Handle the DeviceSubscription "updated" event.
     */
    public function updated(DeviceSubscription $deviceSubscription): void
    {
        //
    }

    /**
     * Handle the DeviceSubscription "deleted" event.
     */
    public function deleted(DeviceSubscription $deviceSubscription): void
    {
        //
    }

    /**
     * Handle the DeviceSubscription "restored" event.
     */
    public function restored(DeviceSubscription $deviceSubscription): void
    {
        //
    }

    /**
     * Handle the DeviceSubscription "force deleted" event.
     */
    public function forceDeleted(DeviceSubscription $deviceSubscription): void
    {
        //
    }
}
