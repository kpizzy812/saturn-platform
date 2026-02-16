<?php

namespace App\Http\Controllers;

use App\Models\OauthSetting;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OauthController extends Controller
{
    /**
     * Validate that the OAuth provider exists and is enabled.
     */
    private function validateProvider(string $provider): OauthSetting
    {
        $allowedProviders = [
            'github', 'google', 'gitlab', 'bitbucket', 'azure',
            'discord', 'authentik', 'clerk', 'infomaniak', 'zitadel',
        ];

        if (! in_array($provider, $allowedProviders, true)) {
            abort(404, 'Unknown OAuth provider.');
        }

        $oauthSetting = OauthSetting::where('provider', $provider)
            ->where('enabled', true)
            ->first();

        if (! $oauthSetting) {
            abort(404, 'OAuth provider is not enabled.');
        }

        return $oauthSetting;
    }

    public function redirect(string $provider)
    {
        $this->validateProvider($provider);

        $socialite_provider = get_socialite_provider($provider);

        // Request email scope for GitHub (email may be private)
        if ($provider === 'github') {
            $socialite_provider->scopes(['user:email']);
        }

        return $socialite_provider->redirect();
    }

    public function callback(string $provider)
    {
        $this->validateProvider($provider);

        try {
            $oauthUser = get_socialite_provider($provider)->user();

            if (empty($oauthUser->email)) {
                return redirect()->route('login')->withErrors([
                    'oauth' => 'Could not retrieve email from '.$provider.'. Please ensure your email is public or grant email access.',
                ]);
            }

            $email = strtolower($oauthUser->email);
            $user = User::whereEmail($email)->first();

            if (! $user) {
                $settings = instanceSettings();
                if (! $settings->is_registration_enabled) {
                    return redirect()->route('login')->withErrors([
                        'oauth' => 'Registration is disabled. Contact admin for an invitation.',
                    ]);
                }

                $user = User::create([
                    'name' => $oauthUser->name ?? $oauthUser->nickname ?? 'User',
                    'email' => $email,
                ]);

                // OAuth email is verified by the provider
                $user->markEmailAsVerified();
            }

            // Set up session team (same logic as Fortify login)
            $invitation = TeamInvitation::whereEmail($email)->first();
            if ($invitation && $invitation->isValid()) {
                if (! $user->teams()->where('team_id', $invitation->team->id)->exists()) {
                    $user->teams()->attach($invitation->team->id, ['role' => $invitation->role]);
                }
                $currentTeam = $invitation->team;
                $invitation->delete();
            } else {
                $currentTeam = $user->teams->firstWhere('personal_team', true);
                if (! $currentTeam) {
                    $currentTeam = $user->recreate_personal_team();
                }
            }

            session(['currentTeam' => $currentTeam]);

            $user->updated_at = now();
            $user->save();

            Auth::login($user);

            return redirect('/');
        } catch (\Exception $e) {
            Log::warning('OAuth callback failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            $errorCode = $e instanceof HttpException ? 'auth.failed' : 'auth.failed.callback';

            return redirect()->route('login')->withErrors(['oauth' => __($errorCode)]);
        }
    }
}
