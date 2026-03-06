<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\OauthSetting;
use App\Models\PlatformInvite;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->instance(RegisterResponse::class, new class implements RegisterResponse
        {
            public function toResponse($request)
            {
                // First user (root) will be redirected to /settings instead of / on registration.
                if (session('currentTeam')?->id === 0) {
                    return redirect()->route('settings.index');
                }

                return redirect(RouteServiceProvider::HOME);
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::registerView(function () {
            $isFirstUser = User::count() === 0;
            $settings = instanceSettings();
            $inviteData = null;

            // Allow registration via valid team invitation even if registration is disabled
            $inviteUuid = request()->query('invite');
            if ($inviteUuid) {
                $invitation = TeamInvitation::where('uuid', $inviteUuid)->first();
                if ($invitation && $invitation->isValid()) {
                    $inviteData = [
                        'uuid' => $invitation->uuid,
                        'email' => $invitation->email,
                        'team_name' => $invitation->team->name,
                        'role' => ucfirst($invitation->role ?? 'member'),
                    ];
                }
            }

            // Allow registration via platform invite from admin
            $platformInviteData = null;
            $platformInviteUuid = request()->query('platform_invite');
            if ($platformInviteUuid) {
                $platformInvite = PlatformInvite::where('uuid', $platformInviteUuid)->first();
                if ($platformInvite && $platformInvite->isValid()) {
                    $platformInviteData = [
                        'uuid' => $platformInvite->uuid,
                        'email' => $platformInvite->email,
                    ];
                }
            }

            if (! $settings->is_registration_enabled && ! $inviteData && ! $platformInviteData) {
                return redirect()->route('login');
            }

            $enabled_oauth_providers = OauthSetting::where('enabled', true)
                ->get(['id', 'provider', 'enabled']);

            return Inertia::render('Auth/Register', [
                'isFirstUser' => $isFirstUser,
                'enabled_oauth_providers' => $enabled_oauth_providers,
                'invitation' => $inviteData,
                'platformInvite' => $platformInviteData,
            ]);
        });

        Fortify::loginView(function () {
            $settings = instanceSettings();
            $enabled_oauth_providers = OauthSetting::where('enabled', true)
                ->get(['id', 'provider', 'enabled']);
            $users = User::count();
            if ($users == 0) {
                // If there are no users, redirect to registration
                return redirect()->route('register');
            }

            // Preserve the intended URL from ?redirect= query param so Fortify
            // can redirect back to the invite page after successful login.
            // Only allow internal paths (no scheme/host) to prevent open redirect.
            $redirect = request()->query('redirect');
            $registrationInvite = null;
            if ($redirect && is_string($redirect)) {
                $parsed = parse_url($redirect);
                $isSafeRelative = empty($parsed['scheme']) && empty($parsed['host']);
                if ($isSafeRelative) {
                    session()->put('url.intended', url($redirect));

                    // If redirected from a team invitation page, pass register link so
                    // unauthenticated users can create an account directly from the login page.
                    if (preg_match('#^/auth/invitations/([a-f0-9\-]+)$#i', $redirect, $m)) {
                        $invitation = TeamInvitation::where('uuid', $m[1])->first();
                        if ($invitation && $invitation->isValid()) {
                            $registrationInvite = [
                                'uuid' => $invitation->uuid,
                                'team_name' => $invitation->team->name,
                                'register_url' => '/register?invite='.$invitation->uuid,
                            ];
                        }
                    }
                }
            }

            return Inertia::render('Auth/Login', [
                'is_registration_enabled' => $settings->is_registration_enabled,
                'enabled_oauth_providers' => $enabled_oauth_providers,
                'registration_invite' => $registrationInvite,
            ]);
        });

        Fortify::authenticateUsing(function (Request $request) {
            $email = strtolower($request->email);
            $user = User::where('email', $email)->with('teams')->first();
            if (
                $user &&
                Hash::check($request->password, $user->password)
            ) {
                $user->updated_at = now();
                $user->save();

                // Check if user has a pending invitation they haven't accepted yet
                $invitation = \App\Models\TeamInvitation::whereEmail($email)->first();
                if ($invitation && $invitation->isValid()) {
                    // User is logging in for the first time after being invited
                    // Attach them to the invited team if not already attached
                    if (! $user->teams()->where('team_id', $invitation->team->id)->exists()) {
                        $pivotData = [
                            'role' => $invitation->role ?? 'member',
                            'invited_by' => $invitation->invited_by,
                            'allowed_projects' => $invitation->allowed_projects,
                        ];
                        if ($invitation->permission_set_id) {
                            $pivotData['permission_set_id'] = $invitation->permission_set_id;
                        }
                        $user->teams()->attach($invitation->team->id, $pivotData);

                        // Handle custom permissions if specified
                        /** @var array<int, array{permission_id: int, environment_restrictions: array<string, bool>}>|null $customPerms */
                        $customPerms = $invitation->custom_permissions;
                        if (! empty($customPerms)) {
                            $personalSet = \App\Models\PermissionSet::create([
                                'name' => "Personal - {$user->name}",
                                'slug' => 'personal-'.$user->id.'-'.time(),
                                'description' => 'Custom permissions assigned during invitation',
                                'team_id' => $invitation->team->id,
                                'is_system' => false,
                            ]);
                            foreach ($customPerms as $perm) {
                                $personalSet->permissions()->attach($perm['permission_id'], [
                                    'environment_restrictions' => json_encode($perm['environment_restrictions']),
                                ]);
                            }
                            $user->teams()->updateExistingPivot($invitation->team->id, [
                                'permission_set_id' => $personalSet->id,
                            ]);
                        }
                    }
                    $currentTeam = $invitation->team;
                    $invitation->delete();
                } else {
                    // Normal login - use personal team
                    $currentTeam = $user->teams->firstWhere('personal_team', true);
                    if (! $currentTeam) {
                        $currentTeam = $user->recreate_personal_team();
                    }
                }
                session(['currentTeam' => $currentTeam]);

                return $user;
            }
        });
        Fortify::requestPasswordResetLinkView(function () {
            return Inertia::render('Auth/ForgotPassword');
        });
        Fortify::resetPasswordView(function ($request) {
            return Inertia::render('Auth/ResetPassword', [
                'token' => $request->route('token'),
                'email' => $request->email,
            ]);
        });
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);

        Fortify::confirmPasswordView(function () {
            return Inertia::render('Auth/ConfirmPassword');
        });

        Fortify::twoFactorChallengeView(function () {
            return Inertia::render('Auth/TwoFactorChallenge');
        });

        RateLimiter::for('force-password-reset', function (Request $request) {
            return Limit::perMinute(15)->by($request->user()->id);
        });

        RateLimiter::for('forgot-password', function (Request $request) {
            // Use real client IP (not spoofable forwarded headers)
            $realIp = $request->server('REMOTE_ADDR') ?? $request->ip();

            return Limit::perMinute(5)->by($realIp);
        });

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->email;
            // Use email + real client IP (not spoofable forwarded headers)
            // server('REMOTE_ADDR') gives the actual connecting IP before proxy headers
            $realIp = $request->server('REMOTE_ADDR') ?? $request->ip();

            return Limit::perMinute(5)->by($email.'|'.$realIp);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
