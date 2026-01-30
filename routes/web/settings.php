<?php

/**
 * Settings routes for Saturn Platform
 *
 * These routes handle user and team settings, notifications, security, and workspace management.
 * All routes require authentication and email verification.
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

// Settings
Route::get('/settings', function () {
    return Inertia::render('Settings/Index');
})->name('settings.index');

Route::get('/settings/account', function () {
    return Inertia::render('Settings/Account');
})->name('settings.account');

Route::get('/settings/team', function () {
    $team = currentTeam();
    $user = auth()->user();

    $members = $team->members->map(function ($user) use ($team) {
        // Get last activity from sessions table
        $lastSession = \Illuminate\Support\Facades\DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->first();

        $lastActive = $lastSession
            ? \Carbon\Carbon::createFromTimestamp($lastSession->last_activity)->toISOString()
            : $user->updated_at->toISOString();

        $allowedProjects = $user->pivot->allowed_projects;

        // Determine access type based on deny-by-default model
        $hasFullAccess = is_array($allowedProjects) && in_array('*', $allowedProjects, true);
        $hasNoAccess = $allowedProjects === null || (is_array($allowedProjects) && empty($allowedProjects));
        $hasLimitedAccess = ! $hasFullAccess && ! $hasNoAccess;

        // Count accessible projects for limited access
        $accessibleProjectsCount = 0;
        if ($hasLimitedAccess && is_array($allowedProjects)) {
            $accessibleProjectsCount = count($allowedProjects);
        }

        // Get inviter information
        $inviter = null;
        if ($user->pivot->invited_by) {
            $inviterUser = \App\Models\User::find($user->pivot->invited_by);
            if ($inviterUser) {
                $inviter = [
                    'id' => $inviterUser->id,
                    'name' => $inviterUser->name,
                    'email' => $inviterUser->email,
                ];
            }
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => null, // Gravatar can be implemented later on frontend
            'role' => $user->pivot->role ?? 'member',
            'joinedAt' => $user->pivot->created_at?->toISOString() ?? $user->created_at->toISOString(),
            'lastActive' => $lastActive,
            'invitedBy' => $inviter,
            'projectAccess' => [
                'hasFullAccess' => $hasFullAccess,
                'hasNoAccess' => $hasNoAccess,
                'hasLimitedAccess' => $hasLimitedAccess,
                'count' => $accessibleProjectsCount,
                'total' => $team->projects()->count(),
            ],
        ];
    });

    // Invitations sent by this team (for admins/owners)
    $invitations = $team->invitations->map(fn ($invitation) => [
        'id' => $invitation->id,
        'email' => $invitation->email,
        'role' => $invitation->role ?? 'member',
        'sentAt' => $invitation->created_at->toISOString(),
        'link' => $invitation->link,
    ]);

    // Invitations received by current user (pending invitations to join other teams)
    $receivedInvitations = \App\Models\TeamInvitation::where('email', $user->email)
        ->whereHas('team', function ($query) use ($user) {
            // Exclude teams user is already a member of
            $query->whereNotIn('id', $user->teams->pluck('id'));
        })
        ->with('team')
        ->get()
        ->filter(fn ($invitation) => $invitation->isValid())
        ->map(fn ($invitation) => [
            'id' => $invitation->id,
            'uuid' => $invitation->uuid,
            'teamName' => $invitation->team->name,
            'role' => $invitation->role ?? 'member',
            'sentAt' => $invitation->created_at->toISOString(),
            'expiresAt' => $invitation->created_at->addDays(
                config('constants.invitation.link.expiration_days', 7)
            )->toISOString(),
        ]);

    return Inertia::render('Settings/Team', [
        'team' => [
            'id' => $team->id,
            'name' => $team->name,
            'avatar' => null, // Team avatar not implemented yet
            'memberCount' => $members->count(),
        ],
        'members' => $members,
        'invitations' => $invitations,
        'receivedInvitations' => $receivedInvitations,
    ]);
})->name('settings.team');

Route::get('/settings/billing', function () {
    return Inertia::render('Settings/Billing/Index');
})->name('settings.billing');

Route::get('/settings/billing/plans', function () {
    return Inertia::render('Settings/Billing/Plans');
})->name('settings.billing.plans');

Route::get('/settings/billing/payment-methods', function () {
    return Inertia::render('Settings/Billing/PaymentMethods');
})->name('settings.billing.payment-methods');

Route::get('/settings/billing/invoices', function () {
    return Inertia::render('Settings/Billing/Invoices');
})->name('settings.billing.invoices');

Route::get('/settings/billing/usage', function () {
    return Inertia::render('Settings/Billing/Usage');
})->name('settings.billing.usage');

Route::get('/settings/tokens', function () {
    $tokens = auth()->user()->tokens->map(fn ($token) => [
        'id' => $token->id,
        'name' => $token->name,
        'abilities' => $token->abilities,
        'last_used_at' => $token->last_used_at?->toISOString(),
        'created_at' => $token->created_at->toISOString(),
        'expires_at' => $token->expires_at?->toISOString(),
    ]);

    return Inertia::render('Settings/Tokens', [
        'tokens' => $tokens,
    ]);
})->name('settings.tokens');

// Legacy route redirects to the new integrations page
Route::get('/settings/integrations-legacy', function () {
    return redirect()->route('settings.integrations');
})->name('settings.integrations.legacy');

Route::get('/settings/security', function () {
    $user = auth()->user();
    $currentSessionId = session()->getId();

    // Get user sessions from database
    $sessions = \Illuminate\Support\Facades\DB::table('sessions')
        ->where('user_id', $user->id)
        ->orderByDesc('last_activity')
        ->get()
        ->map(fn ($session) => [
            'id' => $session->id,
            'ip' => $session->ip_address,
            'userAgent' => $session->user_agent,
            'lastActive' => \Carbon\Carbon::createFromTimestamp($session->last_activity)->toISOString(),
            'current' => $session->id === $currentSessionId,
        ]);

    // Get login history
    $loginHistory = \App\Models\LoginHistory::where('user_id', $user->id)
        ->orderByDesc('logged_at')
        ->limit(50)
        ->get()
        ->map(fn ($log) => [
            'id' => $log->id,
            'timestamp' => $log->logged_at->toISOString(),
            'ip' => $log->ip_address,
            'userAgent' => $log->user_agent,
            'success' => $log->status === 'success',
            'location' => $log->location ?? 'Unknown',
        ]);

    // Get IP allowlist from InstanceSettings
    $settings = \App\Models\InstanceSettings::get();
    $ipAllowlist = $settings->allowed_ips
        ? collect(json_decode($settings->allowed_ips, true))->map(fn ($item, $index) => [
            'id' => $index,
            'ip' => $item['ip'] ?? '',
            'description' => $item['description'] ?? '',
            'createdAt' => $item['created_at'] ?? now()->toISOString(),
        ])->values()->all()
        : [];

    // Get security notification preferences
    $preferences = \App\Models\UserNotificationPreference::getOrCreateForUser($user->id);
    $securityNotifications = $preferences->getSecurityNotifications();

    return Inertia::render('Settings/Security', [
        'sessions' => $sessions,
        'loginHistory' => $loginHistory,
        'ipAllowlist' => $ipAllowlist,
        'securityNotifications' => $securityNotifications,
    ]);
})->name('settings.security');

Route::get('/settings/workspace', function () {
    $team = currentTeam();

    // Generate slug from team name
    $slug = \Illuminate\Support\Str::slug($team->name);

    // Get list of all timezones
    $timezones = \DateTimeZone::listIdentifiers(\DateTimeZone::ALL);

    // Default environment options
    $environmentOptions = [
        ['value' => 'production', 'label' => 'Production'],
        ['value' => 'staging', 'label' => 'Staging'],
        ['value' => 'development', 'label' => 'Development'],
    ];

    return Inertia::render('Settings/Workspace', [
        'workspace' => [
            'id' => $team->id,
            'name' => $team->name,
            'slug' => $slug,
            'logo' => $team->logo,
            'description' => $team->description,
            'timezone' => $team->timezone ?? 'UTC',
            'defaultEnvironment' => $team->default_environment ?? 'production',
            'personalTeam' => $team->personal_team,
        ],
        'timezones' => $timezones,
        'environmentOptions' => $environmentOptions,
    ]);
})->name('settings.workspace');

// Notification Settings
Route::get('/settings/notifications', function () {
    $team = auth()->user()->currentTeam();

    return Inertia::render('Settings/Notifications/Index', [
        'channels' => [
            'discord' => [
                'enabled' => $team->discordNotificationSettings->discord_enabled ?? false,
                'configured' => ! empty($team->discordNotificationSettings->discord_webhook_url),
            ],
            'slack' => [
                'enabled' => $team->slackNotificationSettings->slack_enabled ?? false,
                'configured' => ! empty($team->slackNotificationSettings->slack_webhook_url),
            ],
            'telegram' => [
                'enabled' => $team->telegramNotificationSettings->telegram_enabled ?? false,
                'configured' => ! empty($team->telegramNotificationSettings->telegram_token),
            ],
            'email' => [
                'enabled' => $team->emailNotificationSettings->isEnabled() ?? false,
                'configured' => $team->emailNotificationSettings->smtp_enabled || $team->emailNotificationSettings->resend_enabled || $team->emailNotificationSettings->use_instance_email_settings,
            ],
            'webhook' => [
                'enabled' => $team->webhookNotificationSettings->webhook_enabled ?? false,
                'configured' => ! empty($team->webhookNotificationSettings->webhook_url),
            ],
            'pushover' => [
                'enabled' => $team->pushoverNotificationSettings->pushover_enabled ?? false,
                'configured' => ! empty($team->pushoverNotificationSettings->pushover_user_key),
            ],
        ],
    ]);
})->name('settings.notifications');

Route::get('/settings/notifications/discord', function () {
    $team = auth()->user()->currentTeam();

    return Inertia::render('Settings/Notifications/Discord', [
        'settings' => $team->discordNotificationSettings,
    ]);
})->name('settings.notifications.discord');

Route::get('/settings/notifications/slack', function () {
    $team = auth()->user()->currentTeam();

    return Inertia::render('Settings/Notifications/Slack', [
        'settings' => $team->slackNotificationSettings,
    ]);
})->name('settings.notifications.slack');

Route::get('/settings/notifications/telegram', function () {
    $team = auth()->user()->currentTeam();

    return Inertia::render('Settings/Notifications/Telegram', [
        'settings' => $team->telegramNotificationSettings,
    ]);
})->name('settings.notifications.telegram');

Route::get('/settings/notifications/email', function () {
    $team = auth()->user()->currentTeam();

    return Inertia::render('Settings/Notifications/Email', [
        'settings' => $team->emailNotificationSettings,
        'canUseInstanceSettings' => config('saturn.is_self_hosted'),
    ]);
})->name('settings.notifications.email');

Route::get('/settings/notifications/webhook', function () {
    $team = auth()->user()->currentTeam();

    return Inertia::render('Settings/Notifications/Webhook', [
        'settings' => $team->webhookNotificationSettings,
    ]);
})->name('settings.notifications.webhook');

Route::get('/settings/notifications/pushover', function () {
    $team = auth()->user()->currentTeam();

    return Inertia::render('Settings/Notifications/Pushover', [
        'settings' => $team->pushoverNotificationSettings,
    ]);
})->name('settings.notifications.pushover');

// Account Settings POST routes
Route::post('/settings/account/profile', function (Request $request) {
    $user = auth()->user();
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255|unique:users,email,'.$user->id,
    ]);
    $user->update($request->only(['name', 'email']));

    return redirect()->back()->with('success', 'Profile updated successfully');
})->name('settings.account.profile');

Route::post('/settings/account/avatar', function (Request $request) {
    $request->validate([
        'avatar' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:2048', // 2MB max
    ]);

    $user = auth()->user();

    // Delete old avatar if exists
    if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
        Storage::disk('public')->delete($user->avatar);
    }

    // Store new avatar
    $path = $request->file('avatar')->store('avatars', 'public');
    $user->update(['avatar' => $path]);

    return redirect()->back()->with('success', 'Avatar updated successfully');
})->name('settings.account.avatar');

Route::delete('/settings/account/avatar', function () {
    $user = auth()->user();

    if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
        Storage::disk('public')->delete($user->avatar);
    }

    $user->update(['avatar' => null]);

    return redirect()->back()->with('success', 'Avatar removed successfully');
})->name('settings.account.avatar.delete');

Route::post('/settings/account/password', function (Request $request) {
    $request->validate([
        'current_password' => 'required|string',
        'password' => 'required|string|min:8|confirmed',
    ]);

    $user = auth()->user();
    if (! \Illuminate\Support\Facades\Hash::check($request->current_password, $user->password)) {
        return redirect()->back()->withErrors(['current_password' => 'Current password is incorrect']);
    }

    $user->update([
        'password' => \Illuminate\Support\Facades\Hash::make($request->password),
    ]);

    return redirect()->back()->with('success', 'Password changed successfully');
})->name('settings.account.password');

Route::post('/settings/account/2fa', function (Request $request) {
    $user = auth()->user();

    // Toggle 2FA based on current state
    if ($user->two_factor_secret) {
        // 2FA is enabled, disable it
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return redirect()->back()->with('success', 'Two-factor authentication has been disabled');
    }

    return redirect()->back()->with('info', 'Please enable two-factor authentication from your account settings');
})->name('settings.account.2fa');

Route::delete('/settings/account', function (Request $request) {
    $user = auth()->user();

    // Validate password confirmation
    $request->validate([
        'password' => 'required|string',
    ]);

    if (! \Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
        return redirect()->back()->withErrors(['password' => 'The provided password is incorrect']);
    }

    // Log out the user
    \Illuminate\Support\Facades\Auth::logout();

    // Delete the user (this will trigger all cleanup logic in the User model's deleting event)
    $user->delete();

    return redirect('/')->with('success', 'Account deleted successfully');
})->name('settings.account.delete');

// Security Settings POST/DELETE routes
Route::delete('/settings/security/sessions/{id}', function (string $id) {
    $user = auth()->user();
    $currentSessionId = session()->getId();

    // Prevent deleting current session
    if ($id === $currentSessionId) {
        return redirect()->back()->withErrors(['session' => 'Cannot revoke your current session']);
    }

    // Delete the specified session
    \Illuminate\Support\Facades\DB::table('sessions')
        ->where('id', $id)
        ->where('user_id', $user->id)
        ->delete();

    return redirect()->back()->with('success', 'Session revoked successfully');
})->name('settings.security.sessions.revoke');

Route::delete('/settings/security/sessions/all', function () {
    $user = auth()->user();
    $currentSessionId = session()->getId();

    // Delete all sessions except the current one
    \Illuminate\Support\Facades\DB::table('sessions')
        ->where('user_id', $user->id)
        ->where('id', '!=', $currentSessionId)
        ->delete();

    return redirect()->back()->with('success', 'All other sessions revoked successfully');
})->name('settings.security.sessions.revoke-all');

Route::post('/settings/security/ip-allowlist', function (Request $request) {
    $request->validate([
        'ip_address' => 'required|ip',
        'description' => 'nullable|string|max:255',
    ]);

    $settings = \App\Models\InstanceSettings::get();
    $allowedIps = $settings->allowed_ips ? json_decode($settings->allowed_ips, true) : [];

    // Check if IP already exists
    foreach ($allowedIps as $item) {
        if ($item['ip'] === $request->ip_address) {
            return redirect()->back()->withErrors(['ip_address' => 'This IP address is already in the allowlist']);
        }
    }

    // Add new IP to the list
    $allowedIps[] = [
        'ip' => $request->ip_address,
        'description' => $request->description ?? '',
        'created_at' => now()->toISOString(),
    ];

    $settings->update(['allowed_ips' => json_encode($allowedIps)]);

    return redirect()->back()->with('success', 'IP address added to allowlist');
})->name('settings.security.ip-allowlist.store');

Route::delete('/settings/security/ip-allowlist/{id}', function (string $id) {
    $settings = \App\Models\InstanceSettings::get();
    $allowedIps = $settings->allowed_ips ? json_decode($settings->allowed_ips, true) : [];

    // Remove IP by index (id is the array index)
    $index = (int) $id;
    if (isset($allowedIps[$index])) {
        array_splice($allowedIps, $index, 1);
        $settings->update(['allowed_ips' => json_encode(array_values($allowedIps))]);

        return redirect()->back()->with('success', 'IP address removed from allowlist');
    }

    return redirect()->back()->withErrors(['ip_allowlist' => 'IP address not found']);
})->name('settings.security.ip-allowlist.destroy');

Route::post('/settings/security/notifications', function (Request $request) {
    $user = auth()->user();
    $preferences = \App\Models\UserNotificationPreference::getOrCreateForUser($user->id);

    $preferences->updateSecurityNotifications([
        'newLogin' => $request->boolean('newLogin'),
        'failedLogin' => $request->boolean('failedLogin'),
        'apiAccess' => $request->boolean('apiAccess'),
    ]);

    return redirect()->back()->with('success', 'Security notification settings updated');
})->name('settings.security.notifications');

// Team Settings POST/DELETE routes
Route::post('/settings/team/members/{id}/role', function (string $id, Request $request) {
    $request->validate([
        'role' => 'required|string|in:owner,admin,developer,member,viewer',
    ]);

    $team = currentTeam();
    $currentUser = auth()->user();

    // Check if the current user is an admin or owner
    if (! $currentUser->isAdmin()) {
        return redirect()->back()->withErrors(['role' => 'You do not have permission to update member roles']);
    }

    // Find the member in the team
    $member = $team->members()->where('user_id', $id)->first();
    if (! $member) {
        return redirect()->back()->withErrors(['role' => 'Member not found in this team']);
    }

    // Prevent changing own role
    if ($member->id == $currentUser->id) {
        return redirect()->back()->withErrors(['role' => 'You cannot change your own role']);
    }

    // Update the role in the pivot table
    $team->members()->updateExistingPivot($id, ['role' => $request->role]);

    return redirect()->back()->with('success', 'Member role updated successfully');
})->name('settings.team.members.update-role');

Route::delete('/settings/team/members/{id}', function (string $id) {
    $team = currentTeam();
    $currentUser = auth()->user();

    // Check if the current user is an admin or owner
    if (! $currentUser->isAdmin()) {
        return redirect()->back()->withErrors(['member' => 'You do not have permission to remove members']);
    }

    // Find the member in the team
    $member = $team->members()->where('user_id', $id)->first();
    if (! $member) {
        return redirect()->back()->withErrors(['member' => 'Member not found in this team']);
    }

    // Prevent removing yourself
    if ($member->id == $currentUser->id) {
        return redirect()->back()->withErrors(['member' => 'You cannot remove yourself from the team']);
    }

    // Check if this is the last owner
    $owners = $team->members()->wherePivot('role', 'owner')->get();
    if ($member->pivot->role === 'owner' && $owners->count() <= 1) {
        return redirect()->back()->withErrors(['member' => 'Cannot remove the last owner of the team']);
    }

    // Remove the member from the team
    $team->members()->detach($id);

    return redirect()->back()->with('success', 'Member removed from team successfully');
})->name('settings.team.members.destroy');

Route::post('/settings/team/invite', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'role' => 'required|string|in:owner,admin,developer,member,viewer',
    ]);

    $email = strtolower($request->email);
    $role = $request->role;
    $team = currentTeam();

    // Check if user is already a member
    $existingMember = $team->members()->where('email', $email)->first();
    if ($existingMember) {
        return redirect()->back()->with('error', 'User is already a member of this team');
    }

    // Check for existing pending invitation
    $existingInvitation = \App\Models\TeamInvitation::where('team_id', $team->id)
        ->where('email', $email)
        ->first();
    if ($existingInvitation) {
        return redirect()->back()->with('error', 'An invitation has already been sent to this email');
    }

    // Create the invitation
    $uuid = (string) \Illuminate\Support\Str::uuid();
    $link = url("/invitations/{$uuid}");

    $invitation = \App\Models\TeamInvitation::create([
        'team_id' => $team->id,
        'uuid' => $uuid,
        'email' => $email,
        'role' => $role,
        'link' => $link,
        'via' => 'link',
    ]);

    // Try to send email notification if email settings are configured
    try {
        $user = \App\Models\User::where('email', $email)->first();
        if ($user) {
            $user->notify(new \App\Notifications\TransactionalEmails\InvitationLink($user));
        }
    } catch (\Exception $e) {
        // Email sending failed, but invitation still created
        \Illuminate\Support\Facades\Log::warning('Failed to send invitation email: '.$e->getMessage());
    }

    return redirect()->back()->with('success', 'Invitation sent successfully');
})->name('settings.team.invite.store');

Route::delete('/settings/team/invitations/{id}', function (string $id) {
    $team = currentTeam();
    $invitation = \App\Models\TeamInvitation::where('team_id', $team->id)
        ->where('id', $id)
        ->firstOrFail();

    $invitation->delete();

    return redirect()->back()->with('success', 'Invitation cancelled successfully');
})->name('settings.team.invitations.destroy');

Route::post('/settings/team/invitations/{id}/resend', function (string $id) {
    $team = currentTeam();
    $invitation = \App\Models\TeamInvitation::where('team_id', $team->id)
        ->where('id', $id)
        ->firstOrFail();

    // Regenerate UUID and link
    $uuid = (string) \Illuminate\Support\Str::uuid();
    $invitation->update([
        'uuid' => $uuid,
        'link' => url("/invitations/{$uuid}"),
    ]);

    // Try to send email notification
    try {
        $user = \App\Models\User::where('email', $invitation->email)->first();
        if ($user) {
            $user->notify(new \App\Notifications\TransactionalEmails\InvitationLink($user));
        }
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::warning('Failed to send invitation email: '.$e->getMessage());
    }

    return redirect()->back()->with('success', 'Invitation resent successfully');
})->name('settings.team.invitations.resend');

// Tokens POST/DELETE routes
Route::post('/settings/tokens', function (Request $request) {
    $request->validate([
        'name' => 'required|string|max:255',
        'abilities' => 'array',
        'abilities.*' => 'string|in:read,write,deploy,root,read:sensitive',
        'expires_at' => 'nullable|date|after:today',
    ]);

    $user = auth()->user();
    $abilities = $request->abilities ?? ['read'];
    $expiresAt = $request->expires_at ? new \DateTime($request->expires_at) : null;

    // Create the token using Sanctum
    $tokenResult = $user->createToken($request->name, $abilities, $expiresAt);

    // Return JSON response with the token (only shown once)
    return response()->json([
        'token' => $tokenResult->plainTextToken,
        'id' => $tokenResult->accessToken->id,
        'name' => $request->name,
        'abilities' => $abilities,
        'created_at' => $tokenResult->accessToken->created_at->toISOString(),
        'expires_at' => $expiresAt?->format('c'),
    ]);
})->name('settings.tokens.store');

Route::delete('/settings/tokens/{id}', function (string $id) {
    $user = auth()->user();

    // Find and delete the token
    $token = $user->tokens()->where('id', $id)->first();
    if (! $token) {
        return redirect()->back()->withErrors(['token' => 'Token not found']);
    }

    $token->delete();

    return redirect()->back()->with('success', 'API token revoked successfully');
})->name('settings.tokens.destroy');

// Workspace POST/DELETE routes
Route::post('/settings/workspace', function (Request $request) {
    $validTimezones = \DateTimeZone::listIdentifiers(\DateTimeZone::ALL);
    $validEnvironments = ['production', 'staging', 'development'];

    $request->validate([
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'timezone' => ['nullable', 'string', \Illuminate\Validation\Rule::in($validTimezones)],
        'defaultEnvironment' => ['nullable', 'string', \Illuminate\Validation\Rule::in($validEnvironments)],
    ]);

    $team = currentTeam();
    $user = auth()->user();

    // Check if the user is an admin or owner
    if (! $user->isAdmin()) {
        return redirect()->back()->withErrors(['workspace' => 'You do not have permission to update workspace settings']);
    }

    // Update the team
    $team->update([
        'name' => $request->name,
        'description' => $request->description,
        'timezone' => $request->timezone ?? 'UTC',
        'default_environment' => $request->defaultEnvironment ?? 'production',
    ]);

    return redirect()->back()->with('success', 'Workspace updated successfully');
})->name('settings.workspace.update');

Route::post('/settings/workspace/logo', function (Request $request) {
    $request->validate([
        'logo' => 'required|image|mimes:jpeg,jpg,png,gif,webp,svg|max:2048', // 2MB max
    ]);

    $team = currentTeam();
    $user = auth()->user();

    // Check if the user is an admin or owner
    if (! $user->isAdmin()) {
        return redirect()->back()->withErrors(['logo' => 'You do not have permission to update workspace logo']);
    }

    // Delete old logo if exists
    if ($team->logo && Storage::disk('public')->exists($team->logo)) {
        Storage::disk('public')->delete($team->logo);
    }

    // Store new logo
    $path = $request->file('logo')->store('logos', 'public');
    $team->update(['logo' => $path]);

    return redirect()->back()->with('success', 'Workspace logo updated successfully');
})->name('settings.workspace.logo');

Route::delete('/settings/workspace/logo', function () {
    $team = currentTeam();
    $user = auth()->user();

    if (! $user->isAdmin()) {
        return redirect()->back()->withErrors(['logo' => 'You do not have permission to remove workspace logo']);
    }

    if ($team->logo && Storage::disk('public')->exists($team->logo)) {
        Storage::disk('public')->delete($team->logo);
    }

    $team->update(['logo' => null]);

    return redirect()->back()->with('success', 'Workspace logo removed successfully');
})->name('settings.workspace.logo.delete');

Route::delete('/settings/workspace', function () {
    $team = currentTeam();
    $user = auth()->user();

    // Check if the user is an owner
    if (! $user->isOwner()) {
        return redirect()->back()->withErrors(['workspace' => 'Only workspace owners can delete the workspace']);
    }

    // Prevent deletion of personal team
    if ($team->personal_team) {
        return redirect()->back()->withErrors(['workspace' => 'Cannot delete your personal workspace']);
    }

    // Prevent deletion of root team
    if ($team->id === 0) {
        return redirect()->back()->withErrors(['workspace' => 'Cannot delete the root team']);
    }

    // Switch to personal team before deleting
    $personalTeam = $user->teams()->where('personal_team', true)->first();
    if ($personalTeam) {
        session(['currentTeam' => $personalTeam]);
    }

    // Delete the team (this will trigger all cleanup logic in the Team model's deleting event)
    $team->delete();

    return redirect('/')->with('success', 'Workspace deleted successfully');
})->name('settings.workspace.delete');

// Notification Settings POST routes
Route::post('/settings/notifications/discord', function (Request $request) {
    $team = auth()->user()->currentTeam();
    $settings = $team->discordNotificationSettings;

    $settings->update($request->all());

    return redirect()->back()->with('success', 'Discord notification settings saved successfully');
})->name('settings.notifications.discord.update');

Route::post('/settings/notifications/discord/test', function () {
    $team = auth()->user()->currentTeam();
    $team->notify(new \App\Notifications\Test(channel: 'discord'));

    return redirect()->back()->with('success', 'Test notification sent to Discord');
})->name('settings.notifications.discord.test');

Route::post('/settings/notifications/slack', function (Request $request) {
    $team = auth()->user()->currentTeam();
    $settings = $team->slackNotificationSettings;

    $settings->update($request->all());

    return redirect()->back()->with('success', 'Slack notification settings saved successfully');
})->name('settings.notifications.slack.update');

Route::post('/settings/notifications/slack/test', function () {
    $team = auth()->user()->currentTeam();
    $team->notify(new \App\Notifications\Test(channel: 'slack'));

    return redirect()->back()->with('success', 'Test notification sent to Slack');
})->name('settings.notifications.slack.test');

Route::post('/settings/notifications/telegram', function (Request $request) {
    $team = auth()->user()->currentTeam();
    $settings = $team->telegramNotificationSettings;

    $settings->update($request->all());

    return redirect()->back()->with('success', 'Telegram notification settings saved successfully');
})->name('settings.notifications.telegram.update');

Route::post('/settings/notifications/telegram/test', function () {
    $team = auth()->user()->currentTeam();
    $team->notify(new \App\Notifications\Test(channel: 'telegram'));

    return redirect()->back()->with('success', 'Test notification sent to Telegram');
})->name('settings.notifications.telegram.test');

Route::post('/settings/notifications/email', function (Request $request) {
    $team = auth()->user()->currentTeam();
    $settings = $team->emailNotificationSettings;

    $settings->update($request->all());

    return redirect()->back()->with('success', 'Email notification settings saved successfully');
})->name('settings.notifications.email.update');

Route::post('/settings/notifications/email/test', function () {
    $team = auth()->user()->currentTeam();
    $team->notify(new \App\Notifications\Test(channel: 'email'));

    return redirect()->back()->with('success', 'Test email sent');
})->name('settings.notifications.email.test');

Route::post('/settings/notifications/webhook', function (Request $request) {
    $team = auth()->user()->currentTeam();
    $settings = $team->webhookNotificationSettings;

    $settings->update($request->all());

    return redirect()->back()->with('success', 'Webhook notification settings saved successfully');
})->name('settings.notifications.webhook.update');

Route::post('/settings/notifications/webhook/test', function () {
    $team = auth()->user()->currentTeam();
    $team->notify(new \App\Notifications\Test(channel: 'webhook'));

    return redirect()->back()->with('success', 'Test webhook sent');
})->name('settings.notifications.webhook.test');

Route::post('/settings/notifications/pushover', function (Request $request) {
    $team = auth()->user()->currentTeam();
    $settings = $team->pushoverNotificationSettings;

    $settings->update($request->all());

    return redirect()->back()->with('success', 'Pushover notification settings saved successfully');
})->name('settings.notifications.pushover.update');

Route::post('/settings/notifications/pushover/test', function () {
    $team = auth()->user()->currentTeam();
    $team->notify(new \App\Notifications\Test(channel: 'pushover'));

    return redirect()->back()->with('success', 'Test notification sent to Pushover');
})->name('settings.notifications.pushover.test');

Route::post('/settings/notifications/{channel}/toggle', function (string $channel, Request $request) {
    $team = auth()->user()->currentTeam();

    $settingsMap = [
        'discord' => 'discordNotificationSettings',
        'slack' => 'slackNotificationSettings',
        'telegram' => 'telegramNotificationSettings',
        'email' => 'emailNotificationSettings',
        'webhook' => 'webhookNotificationSettings',
        'pushover' => 'pushoverNotificationSettings',
    ];

    if (! isset($settingsMap[$channel])) {
        return redirect()->back()->withErrors(['channel' => 'Invalid notification channel']);
    }

    $settings = $team->{$settingsMap[$channel]};
    $enabledField = $channel.'_enabled';

    if ($channel === 'email') {
        $settings->update(['smtp_enabled' => $request->input('enabled', false)]);
    } else {
        $settings->update([$enabledField => $request->input('enabled', false)]);
    }

    return redirect()->back()->with('success', ucfirst($channel).' notifications '.($request->input('enabled', false) ? 'enabled' : 'disabled'));
})->name('settings.notifications.toggle');

// Additional Settings routes
// Redirect legacy api-tokens route to new tokens route
Route::get('/settings/api-tokens', function () {
    return redirect()->route('settings.tokens');
})->name('settings.api-tokens');

Route::get('/settings/audit-log', function () {
    return Inertia::render('Settings/AuditLog');
})->name('settings.audit-log');

Route::get('/settings/usage', function () {
    return Inertia::render('Settings/Usage');
})->name('settings.usage');

Route::get('/settings/integrations', function () {
    $team = currentTeam();

    // Get GitHub Apps
    $githubApps = \App\Models\GithubApp::where(function ($query) use ($team) {
        $query->where('team_id', $team->id)
            ->orWhere('is_system_wide', true);
    })->get()->map(fn ($app) => [
        'id' => $app->id,
        'uuid' => $app->uuid,
        'name' => $app->name,
        'organization' => $app->organization,
        'type' => 'github',
        'connected' => ! is_null($app->app_id) && ! is_null($app->installation_id),
        'lastSync' => $app->updated_at?->toISOString(),
        'applicationsCount' => $app->applications()->count(),
    ]);

    // Get GitLab Apps
    $gitlabApps = \App\Models\GitlabApp::where('team_id', $team->id)
        ->get()->map(fn ($app) => [
            'id' => $app->id,
            'uuid' => $app->uuid ?? $app->id,
            'name' => $app->name,
            'organization' => $app->deploy_key_id ? 'Deploy Key' : 'OAuth',
            'type' => 'gitlab',
            'connected' => ! is_null($app->app_id) || ! is_null($app->deploy_key_id),
            'lastSync' => $app->updated_at?->toISOString(),
            'applicationsCount' => $app->applications()->count(),
        ]);

    // Combine all sources
    $sources = $githubApps->merge($gitlabApps);

    // Get notification channel statuses
    $notificationChannels = [
        'slack' => [
            'enabled' => $team->slackNotificationSettings?->slack_enabled ?? false,
            'configured' => ! empty($team->slackNotificationSettings?->slack_webhook_url),
            'channel' => $team->slackNotificationSettings?->slack_webhook_url ? 'Webhook configured' : null,
        ],
        'discord' => [
            'enabled' => $team->discordNotificationSettings?->discord_enabled ?? false,
            'configured' => ! empty($team->discordNotificationSettings?->discord_webhook_url),
            'channel' => $team->discordNotificationSettings?->discord_webhook_url ? 'Webhook configured' : null,
        ],
    ];

    return Inertia::render('Settings/Integrations', [
        'sources' => $sources,
        'notificationChannels' => $notificationChannels,
    ]);
})->name('settings.integrations');

Route::get('/settings/members/{id}', function (string $id) {
    $team = currentTeam();
    $currentUser = auth()->user();

    // Find the member in the current team
    $member = $team->members()->where('users.id', $id)->first();

    if (! $member) {
        abort(404, 'Team member not found');
    }

    // Get last activity from sessions table
    $lastSession = \Illuminate\Support\Facades\DB::table('sessions')
        ->where('user_id', $member->id)
        ->orderByDesc('last_activity')
        ->first();

    $lastActive = $lastSession
        ? \Carbon\Carbon::createFromTimestamp($lastSession->last_activity)->toISOString()
        : $member->updated_at->toISOString();

    // Format member data
    $memberData = [
        'id' => $member->id,
        'name' => $member->name,
        'email' => $member->email,
        'avatar' => null,
        'role' => $member->pivot->role ?? 'member',
        'joinedAt' => $member->pivot->created_at?->toDateString() ?? $member->created_at->toDateString(),
        'lastActive' => $lastActive,
    ];

    // Get team projects (all members have access to team projects)
    $projects = $team->projects()->get()->map(fn ($project) => [
        'id' => $project->id,
        'name' => $project->name,
        'role' => $member->pivot->role ?? 'member',
        'lastAccessed' => $project->updated_at->toISOString(),
    ]);

    // Get recent activities for this user from activity_log table
    $activities = \Spatie\Activitylog\Models\Activity::query()
        ->where('causer_type', 'App\\Models\\User')
        ->where('causer_id', $member->id)
        ->orderByDesc('created_at')
        ->limit(20)
        ->get()
        ->map(function ($activity) use ($member) {
            // Map activity to frontend format
            $resourceType = match ($activity->subject_type) {
                'App\\Models\\Application' => 'application',
                'App\\Models\\Service' => 'service',
                'App\\Models\\StandalonePostgresql', 'App\\Models\\StandaloneMysql',
                'App\\Models\\StandaloneRedis', 'App\\Models\\StandaloneMongodb' => 'database',
                'App\\Models\\Server' => 'server',
                'App\\Models\\Project' => 'project',
                'App\\Models\\Team' => 'team',
                default => 'application',
            };

            $resourceName = $activity->subject?->name ?? 'Unknown';

            return [
                'id' => (string) $activity->id,
                'action' => $activity->event ?? $activity->log_name ?? 'action',
                'description' => $activity->description,
                'user' => [
                    'name' => $member->name,
                    'email' => $member->email,
                ],
                'resource' => [
                    'type' => $resourceType,
                    'name' => $resourceName,
                    'id' => (string) ($activity->subject_id ?? 0),
                ],
                'timestamp' => $activity->created_at->toISOString(),
            ];
        });

    return Inertia::render('Settings/Members/Show', [
        'member' => $memberData,
        'projects' => $projects,
        'activities' => $activities,
    ]);
})->name('settings.members.show');

Route::get('/settings/team/activity', function () {
    return Inertia::render('Settings/Team/Activity');
})->name('settings.team.activity');

Route::get('/settings/team/index', function () {
    $team = currentTeam();

    $members = $team->members->map(function ($user) {
        // Get last activity from sessions table
        $lastSession = \Illuminate\Support\Facades\DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->first();

        $lastActive = $lastSession
            ? \Carbon\Carbon::createFromTimestamp($lastSession->last_activity)->toISOString()
            : $user->updated_at->toISOString();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => null, // Gravatar can be implemented later on frontend
            'role' => $user->pivot->role ?? 'member',
            'joinedAt' => $user->pivot->created_at?->toISOString() ?? $user->created_at->toISOString(),
            'lastActive' => $lastActive,
            'hasRestrictedAccess' => $user->pivot->allowed_projects !== null,
        ];
    });

    return Inertia::render('Settings/Team/Index', [
        'team' => [
            'id' => $team->id,
            'name' => $team->name,
            'avatar' => null, // Team avatar not implemented yet
            'memberCount' => $members->count(),
        ],
        'members' => $members,
    ]);
})->name('settings.team.team-index');

Route::get('/settings/team/invite', function () {
    return Inertia::render('Settings/Team/Invite');
})->name('settings.team.invite');

Route::get('/settings/team/roles', function () {
    return Inertia::render('Settings/Team/Roles');
})->name('settings.team.roles');

// Team Member Project Access Routes
Route::get('/settings/team/members/{id}/projects', function (string $id) {
    $team = currentTeam();
    $currentUser = auth()->user();

    // Only owner/admin can view project access settings
    if (! $currentUser->isOwner() && ! $currentUser->isAdmin()) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $member = $team->members()->where('user_id', $id)->first();
    if (! $member) {
        return response()->json(['message' => 'Member not found'], 404);
    }

    // Admin cannot manage other admins or owners
    if ($currentUser->isAdmin() && ! $currentUser->isOwner()) {
        $memberRole = $member->pivot->role;
        if (in_array($memberRole, ['owner', 'admin'])) {
            return response()->json(['message' => 'Cannot configure project access for admins or owners'], 403);
        }
    }

    $projects = $team->projects()->orderByRaw('LOWER(name)')->get()->map(fn ($p) => [
        'id' => $p->id,
        'name' => $p->name,
    ]);

    $allowedProjects = $member->pivot->allowed_projects;

    // Determine access type for frontend
    $hasFullAccess = is_array($allowedProjects) && in_array('*', $allowedProjects, true);
    $hasNoAccess = $allowedProjects === null || (is_array($allowedProjects) && empty($allowedProjects));

    return response()->json([
        'member' => [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'role' => $member->pivot->role,
        ],
        'projects' => $projects,
        'allowed_projects' => $allowedProjects,
        'has_full_access' => $hasFullAccess,
        'has_no_access' => $hasNoAccess,
    ]);
})->name('settings.team.members.projects');

Route::post('/settings/team/members/{id}/projects', function (Request $request, string $id) {
    $request->validate([
        'grant_all' => 'boolean',
        'allowed_projects' => 'array',
        'allowed_projects.*' => ['required', function ($attribute, $value, $fail) {
            // Allow '*' for full access or numeric values for project IDs
            if ($value !== '*' && ! is_numeric($value)) {
                $fail('The '.$attribute.' must be a project ID or "*" for full access.');
            }
        }],
    ]);

    $team = currentTeam();
    $currentUser = auth()->user();

    // Only owner/admin can update project access
    if (! $currentUser->isOwner() && ! $currentUser->isAdmin()) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $member = $team->members()->where('user_id', $id)->first();
    if (! $member) {
        return response()->json(['message' => 'Member not found'], 404);
    }

    $memberRole = $member->pivot->role;

    // Only owner can manage everyone
    // Admin cannot manage owner or other admins
    if ($currentUser->isAdmin() && ! $currentUser->isOwner()) {
        if (in_array($memberRole, ['owner', 'admin'])) {
            return response()->json(['message' => 'Only owner can configure project access for admins'], 403);
        }
    }

    // Owner always has full access - cannot be restricted
    if ($memberRole === 'owner') {
        return response()->json(['message' => 'Owner always has full access and cannot be restricted'], 422);
    }

    // Determine allowed_projects value based on new deny-by-default model
    // null or [] = no access (default)
    // ['*'] = full access to all projects
    // [1, 2, 3] = access to specific projects

    $allowedProjects = null; // default: no access

    if ($request->has('grant_all') && $request->grant_all === true) {
        // Grant access to all projects
        $allowedProjects = ['*'];
    } elseif ($request->has('allowed_projects')) {
        $allowedProjects = $request->allowed_projects;

        // If empty array provided, it means no access (explicit deny)
        if (empty($allowedProjects)) {
            $allowedProjects = null;
        } else {
            // Validate that all project IDs belong to this team (unless '*')
            if (! in_array('*', $allowedProjects, true)) {
                $teamProjectIds = $team->projects()->pluck('id')->toArray();
                $invalidIds = array_diff($allowedProjects, $teamProjectIds);
                if (! empty($invalidIds)) {
                    return response()->json(['message' => 'Invalid project IDs'], 422);
                }
            }
        }
    }

    $team->members()->updateExistingPivot($id, [
        'allowed_projects' => $allowedProjects,
    ]);

    return response()->json(['message' => 'Project access updated successfully']);
})->name('settings.team.members.projects.update');
