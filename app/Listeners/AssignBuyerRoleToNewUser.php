<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class AssignBuyerRoleToNewUser
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        // Assign the buyer role to the newly registered user if it exists
        try {
            $event->user->assignRole('buyer');
        } catch (\Exception $e) {
            // Role doesn't exist yet - silently skip
        }
    }
}
