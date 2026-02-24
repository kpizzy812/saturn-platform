<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Authorization\ResourceAuthorizationService;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenPolicy
{
    public function __construct(
        protected ResourceAuthorizationService $authService
    ) {}

    /**
     * Determine whether the user can view any API tokens.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the API token.
     */
    public function view(User $user, PersonalAccessToken $token): bool
    {
        return $user->id === $token->tokenable_id && $token->tokenable_type === User::class;
    }

    /**
     * Determine whether the user can create API tokens.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the API token.
     */
    public function update(User $user, PersonalAccessToken $token): bool
    {
        return $user->id === $token->tokenable_id && $token->tokenable_type === User::class;
    }

    /**
     * Determine whether the user can delete the API token.
     */
    public function delete(User $user, PersonalAccessToken $token): bool
    {
        return $user->id === $token->tokenable_id && $token->tokenable_type === User::class;
    }

    /**
     * Determine whether the user can manage their own API tokens.
     */
    public function manage(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can use root permissions for API tokens.
     */
    public function useRootPermissions(User $user): bool
    {
        $team = currentTeam();

        return $team ? $this->authService->canManageTokens($user, $team->id) : false;
    }

    /**
     * Determine whether the user can use write permissions for API tokens.
     */
    public function useWritePermissions(User $user): bool
    {
        $team = currentTeam();

        return $team ? $this->authService->canManageTokens($user, $team->id) : false;
    }
}
