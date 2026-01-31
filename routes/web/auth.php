<?php

use App\Http\Controllers\Controller;
use App\Http\Controllers\OauthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
|
| Routes for user authentication, password reset, email verification,
| OAuth, and team invitations.
|
*/

// Password reset (throttled)
Route::post('/forgot-password', [Controller::class, 'forgot_password'])
    ->name('password.forgot')
    ->middleware('throttle:forgot-password');

// Realtime test
Route::get('/realtime', [Controller::class, 'realtime_test'])->middleware('auth');

// Email verification
Route::get('/verify', [Controller::class, 'verify'])
    ->middleware('auth')
    ->name('verify.email');

Route::get('/email/verify/{id}/{hash}', [Controller::class, 'email_verify'])
    ->middleware(['auth'])
    ->name('verify.verify');

// Magic link authentication (throttled)
Route::middleware(['throttle:login'])->group(function () {
    Route::get('/auth/link', [Controller::class, 'link'])->name('auth.link');
});

// OAuth redirects
Route::get('/auth/{provider}/redirect', [OauthController::class, 'redirect'])->name('auth.redirect');
Route::get('/auth/{provider}/callback', [OauthController::class, 'callback'])->name('auth.callback');

// Guest auth routes (login, register, password reset)
Route::prefix('auth')->middleware(['web'])->group(function () {
    Route::middleware(['guest'])->group(function () {
        Route::get('/login', fn () => \Inertia\Inertia::render('Auth/Login'))
            ->name('auth.login');

        Route::get('/register', fn () => \Inertia\Inertia::render('Auth/Register'))
            ->name('auth.register');

        Route::get('/forgot-password', fn () => \Inertia\Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]))->name('auth.forgot-password');

        Route::get('/reset-password/{token}', fn (string $token) => \Inertia\Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => request()->query('email'),
        ]))->name('auth.reset-password');
    });

    Route::get('/verify-email', fn () => \Inertia\Inertia::render('Auth/VerifyEmail', [
        'status' => session('status'),
        'email' => auth()->user()?->email,
    ]))->name('auth.verify-email');

    // Team invitation routes
    Route::get('/invitations/{uuid}', function (string $uuid) {
        $invitation = \App\Models\TeamInvitation::where('uuid', $uuid)->firstOrFail();

        if (! $invitation->isValid()) {
            return \Inertia\Inertia::render('Auth/AcceptInvite', [
                'invitation' => null,
                'error' => 'This invitation has expired or is no longer valid.',
                'isAuthenticated' => auth()->check(),
            ]);
        }

        $team = $invitation->team;
        $inviter = $team->members()->wherePivot('role', 'owner')->first()
            ?? $team->members()->first();

        return \Inertia\Inertia::render('Auth/AcceptInvite', [
            'invitation' => [
                'id' => $invitation->uuid,
                'team_name' => $team->name,
                'inviter_name' => $inviter?->name ?? 'Team Admin',
                'inviter_email' => $inviter?->email ?? '',
                'role' => ucfirst($invitation->role ?? 'member'),
                'expires_at' => $invitation->created_at->addDays(
                    config('constants.invitation.link.expiration_days', 7)
                )->toISOString(),
            ],
            'isAuthenticated' => auth()->check(),
        ]);
    })->name('auth.accept-invite');

    Route::post('/invitations/{uuid}/accept', function (string $uuid) {
        $user = auth()->user();
        if (! $user) {
            return redirect()->route('login', ['redirect' => "/invitations/{$uuid}"]);
        }

        $invitation = \App\Models\TeamInvitation::where('uuid', $uuid)->firstOrFail();

        if (! $invitation->isValid()) {
            return redirect()->back()->with('error', 'This invitation has expired.');
        }

        // Check if email matches
        if (strtolower($user->email) !== strtolower($invitation->email)) {
            return redirect()->back()->with('error', 'This invitation was sent to a different email address.');
        }

        $team = $invitation->team;

        // Check if already a member
        if ($team->members()->where('user_id', $user->id)->exists()) {
            $invitation->delete();

            return redirect('/dashboard')->with('info', 'You are already a member of this team.');
        }

        // Add user to team
        $team->members()->attach($user->id, [
            'role' => $invitation->role ?? 'member',
            'invited_by' => $invitation->invited_by,
        ]);

        // Delete the invitation
        $invitation->delete();

        // Switch to the new team
        $user->update(['current_team_id' => $team->id]);

        return redirect('/dashboard')->with('success', "You have joined {$team->name}!");
    })->name('auth.accept-invite.store');

    Route::post('/invitations/{uuid}/decline', function (string $uuid) {
        $invitation = \App\Models\TeamInvitation::where('uuid', $uuid)->first();

        if ($invitation) {
            $invitation->delete();
        }

        if (auth()->check()) {
            return redirect('/dashboard')->with('info', 'Invitation declined.');
        }

        return redirect('/login')->with('info', 'Invitation declined.');
    })->name('auth.decline-invite');
});

// Authenticated auth routes (2FA, OAuth connect)
Route::middleware(['web', 'auth', 'verified'])->prefix('auth')->group(function () {
    Route::get('/two-factor/setup', function () {
        $qrCode = '<svg><!-- QR code SVG here --></svg>';
        $manualEntryCode = 'ABCD1234EFGH5678';

        return \Inertia\Inertia::render('Auth/TwoFactor/Setup', [
            'qrCode' => $qrCode,
            'manualEntryCode' => $manualEntryCode,
        ]);
    })->name('auth.two-factor.setup');

    Route::get('/two-factor/verify', fn () => \Inertia\Inertia::render('Auth/TwoFactor/Verify'))
        ->name('auth.two-factor.verify')
        ->withoutMiddleware(['verified']);

    Route::get('/oauth/connect', function () {
        $providers = [
            ['name' => 'GitHub', 'provider' => 'github', 'connected' => false],
            ['name' => 'Google', 'provider' => 'google', 'connected' => false],
            ['name' => 'GitLab', 'provider' => 'gitlab', 'connected' => false],
        ];

        return \Inertia\Inertia::render('Auth/OAuth/Connect', [
            'providers' => $providers,
        ]);
    })->name('auth.oauth.connect');

    Route::get('/onboarding', fn () => \Inertia\Inertia::render('Auth/Onboarding/Index', [
        'userName' => auth()->user()->name,
        'templates' => [],
    ]))->name('auth.onboarding');
});

// Force password reset (throttled)
Route::middleware(['auth', 'verified', 'throttle:force-password-reset'])->group(function () {
    Route::get('/force-password-reset', fn () => \Inertia\Inertia::render('Auth/ForcePasswordReset'))
        ->name('auth.force-password-reset');
});
