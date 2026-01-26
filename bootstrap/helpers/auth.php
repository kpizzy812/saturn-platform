<?php

/**
 * Authentication and session helper functions.
 *
 * Contains functions for user authentication, team management,
 * session handling, and error handling.
 */

use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

/**
 * Check if the current user is an instance admin.
 */
function isInstanceAdmin()
{
    return auth()?->user()?->isInstanceAdmin() ?? false;
}

/**
 * Get the current user's team.
 */
function currentTeam()
{
    return Auth::user()?->currentTeam() ?? null;
}

/**
 * Check if boarding should be shown to the current user.
 */
function showBoarding(): bool
{
    if (Auth::user()?->isMember()) {
        return false;
    }

    return currentTeam()->show_boarding ?? false;
}

/**
 * Refresh the user's session with team data.
 */
function refreshSession(?Team $team = null): void
{
    if (! $team) {
        if (Auth::user()->currentTeam()) {
            $team = Team::find(Auth::user()->currentTeam()->id);
        } else {
            $team = User::find(Auth::id())->teams->first();
        }
    }
    Cache::forget('team:'.Auth::id());
    Cache::remember('team:'.Auth::id(), 3600, function () use ($team) {
        return $team;
    });
    session(['currentTeam' => $team]);
}

/**
 * Handle errors with optional Livewire dispatch or exception throwing.
 */
function handleError(?Throwable $error = null, ?Livewire\Component $livewire = null, ?string $customErrorMessage = null)
{
    if ($error instanceof TooManyRequestsException) {
        if (isset($livewire)) {
            return $livewire->dispatch('error', "Too many requests. Please try again in {$error->secondsUntilAvailable} seconds.");
        }

        return "Too many requests. Please try again in {$error->secondsUntilAvailable} seconds.";
    }
    if ($error instanceof UniqueConstraintViolationException) {
        if (isset($livewire)) {
            return $livewire->dispatch('error', 'Duplicate entry found. Please use a different name.');
        }

        return 'Duplicate entry found. Please use a different name.';
    }

    if ($error instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
        abort(404);
    }

    if ($error instanceof Throwable) {
        $message = $error->getMessage();
    } else {
        $message = null;
    }
    if ($customErrorMessage) {
        $message = $customErrorMessage.' '.$message;
    }

    if (isset($livewire)) {
        return $livewire->dispatch('error', $message);
    }
    throw new Exception($message);
}

/**
 * Get the current route parameters.
 */
function get_route_parameters(): array
{
    return Route::current()->parameters();
}

/**
 * Check if user has an active subscription or is an instance admin.
 */
function isSubscribed()
{
    return isSubscriptionActive() || auth()->user()->isInstanceAdmin();
}

/**
 * Check if the application is running in production mode.
 */
function isProduction(): bool
{
    return ! isDev();
}

/**
 * Check if the application is running in development mode.
 */
function isDev(): bool
{
    return config('app.env') === 'local';
}

/**
 * Check if this is a cloud (not self-hosted) instance.
 */
function isCloud(): bool
{
    return ! config('constants.saturn.self_hosted');
}

/**
 * Check if password confirmation should be skipped.
 * Returns true if:
 * - Two-step confirmation is globally disabled
 * - User has no password (OAuth users)
 *
 * Used by modal-confirmation.blade.php to determine if password step should be shown.
 *
 * @return bool True if password confirmation should be skipped
 */
function shouldSkipPasswordConfirmation(): bool
{
    // Skip if two-step confirmation is globally disabled
    if (data_get(InstanceSettings::get(), 'disable_two_step_confirmation')) {
        return true;
    }

    // Skip if user has no password (OAuth users)
    if (! Auth::user()?->hasPassword()) {
        return true;
    }

    return false;
}

/**
 * Verify password for two-step confirmation.
 * Skips verification if:
 * - Two-step confirmation is globally disabled
 * - User has no password (OAuth users)
 *
 * @param  mixed  $password  The password to verify (may be array if skipped by frontend)
 * @param  \Livewire\Component|null  $component  Optional Livewire component to add errors to
 * @return bool True if verification passed (or skipped), false if password is incorrect
 */
function verifyPasswordConfirmation(mixed $password, ?Livewire\Component $component = null): bool
{
    // Skip if password confirmation should be skipped
    if (shouldSkipPasswordConfirmation()) {
        return true;
    }

    // Verify the password
    if (! Hash::check($password, Auth::user()->password)) {
        if ($component) {
            $component->addError('password', 'The provided password is incorrect.');
        }

        return false;
    }

    return true;
}
