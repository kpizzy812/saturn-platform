<?php

namespace App\Listeners;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Auth\Events\Failed;

class RecordFailedLogin
{
    /**
     * Handle the event.
     */
    public function handle(Failed $event): void
    {
        // Try to find the user by email if available
        $user = null;
        $email = $event->credentials['email'] ?? null;

        if ($email) {
            $user = User::where('email', strtolower($email))->first();
        }

        LoginHistory::record(
            $user,
            'failed',
            $user ? 'Invalid credentials' : 'User not found'
        );
    }
}
