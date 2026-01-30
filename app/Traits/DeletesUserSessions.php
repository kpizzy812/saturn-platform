<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

trait DeletesUserSessions
{
    /**
     * Delete all sessions for the current user.
     * This will force the user to log in again on all devices.
     */
    public function deleteAllSessions(): void
    {
        // Invalidate the current session
        Session::invalidate();
        Session::regenerateToken();
        DB::table('sessions')->where('user_id', $this->id)->delete();
    }

    /**
     * Delete all other sessions for this user except the current one.
     * Used when admin suspends/bans a user - deletes target user's sessions
     * without affecting the admin's current session.
     */
    public function deleteOtherSessions(): void
    {
        $currentSessionId = Session::getId();

        DB::table('sessions')
            ->where('user_id', $this->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }

    /**
     * Delete ALL sessions for this user (including current if it belongs to them).
     * Used when admin suspends/bans another user.
     */
    public function deleteUserSessions(): void
    {
        DB::table('sessions')->where('user_id', $this->id)->delete();
    }

    /**
     * Boot the trait.
     */
    protected static function bootDeletesUserSessions()
    {
        static::updated(function ($user) {
            // Check if password was changed
            if ($user->wasChanged('password')) {
                $user->deleteAllSessions();
            }
        });
    }
}
