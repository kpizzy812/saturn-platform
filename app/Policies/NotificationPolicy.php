<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Authorization\ResourceAuthorizationService;
use Illuminate\Database\Eloquent\Model;

class NotificationPolicy
{
    public function __construct(
        protected ResourceAuthorizationService $authService
    ) {}

    /**
     * Determine whether the user can view the notification settings.
     */
    public function view(User $user, Model $notificationSettings): bool
    {
        $team = $notificationSettings->getAttribute('team');
        if (! $team) {
            return false;
        }

        // Any team member can view notification settings
        return $user->teams->contains('id', $team->id);
    }

    /**
     * Determine whether the user can update the notification settings.
     */
    public function update(User $user, Model $notificationSettings): bool
    {
        $team = $notificationSettings->getAttribute('team');
        if (! $team) {
            return false;
        }

        return $this->authService->canManageNotifications($user, $team->id);
    }

    /**
     * Determine whether the user can manage (create, update, delete) notification settings.
     */
    public function manage(User $user, Model $notificationSettings): bool
    {
        return $this->update($user, $notificationSettings);
    }

    /**
     * Determine whether the user can send test notifications.
     */
    public function sendTest(User $user, Model $notificationSettings): bool
    {
        return $this->update($user, $notificationSettings);
    }
}
