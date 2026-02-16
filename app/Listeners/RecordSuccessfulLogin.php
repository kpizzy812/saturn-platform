<?php

namespace App\Listeners;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Auth\Events\Login;

class RecordSuccessfulLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;
        LoginHistory::record($user, 'success');
    }
}
