<?php

namespace App\Policies;

use App\Models\AiChatSession;
use App\Models\User;

class AiChatPolicy
{
    /**
     * Determine whether the user can view any sessions.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the session.
     */
    public function view(User $user, AiChatSession $session): bool
    {
        // User can only view their own sessions
        return $session->user_id === $user->id;
    }

    /**
     * Determine whether the user can create sessions.
     */
    public function create(User $user): bool
    {
        // Any authenticated user can create chat sessions
        return true;
    }

    /**
     * Determine whether the user can update the session.
     */
    public function update(User $user, AiChatSession $session): bool
    {
        return $session->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the session.
     */
    public function delete(User $user, AiChatSession $session): bool
    {
        return $session->user_id === $user->id;
    }

    /**
     * Determine whether the user can send messages to the session.
     */
    public function sendMessage(User $user, AiChatSession $session): bool
    {
        return $session->user_id === $user->id && $session->isActive();
    }

    /**
     * Determine whether the user can execute commands via the session.
     */
    public function executeCommand(User $user, AiChatSession $session): bool
    {
        // User must own the session and be part of the team
        if ($session->user_id !== $user->id) {
            return false;
        }

        // Verify team membership
        return $user->teams->pluck('id')->contains($session->team_id);
    }
}
