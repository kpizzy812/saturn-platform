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

        // Determine access type based on allow-by-default model
        // null = all projects (full access), [] = no access, [1,2,3] = specific projects
        $hasFullAccess = $allowedProjects === null || (is_array($allowedProjects) && in_array('*', $allowedProjects, true));
        $hasNoAccess = is_array($allowedProjects) && empty($allowedProjects);
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
            'avatar' => $user->avatar ? '/storage/'.$user->avatar : null,
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

    // Get current user's role and permissions
    $currentUserRole = $user->teams()->where('team_id', $team->id)->first()?->pivot->role ?? 'member';
    $canManageTeam = in_array($currentUserRole, ['owner', 'admin']);
    $canManageRoles = $currentUserRole === 'owner';

    return Inertia::render('Settings/Team', [
        'team' => [
            'id' => $team->id,
            'name' => $team->name,
            'avatar' => null, // Team avatar not implemented yet
            'memberCount' => $members->count(),
        ],
        'members' => $members,
        'invitations' => $canManageTeam ? $invitations : collect([]), // Only show invitations to admins
        'receivedInvitations' => $receivedInvitations,
        'currentUserRole' => $currentUserRole,
        'canManageTeam' => $canManageTeam,
        'canManageRoles' => $canManageRoles,
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
    $user = auth()->user();

    $slug = \Illuminate\Support\Str::slug($team->name);

    $timezones = \DateTimeZone::listIdentifiers(\DateTimeZone::ALL);

    $environmentOptions = [
        ['value' => 'production', 'label' => 'Production'],
        ['value' => 'staging', 'label' => 'Staging'],
        ['value' => 'development', 'label' => 'Development'],
    ];

    $localeOptions = [
        ['value' => 'en', 'label' => 'English'],
        ['value' => 'ru', 'label' => 'Russian'],
        ['value' => 'de', 'label' => 'German'],
        ['value' => 'fr', 'label' => 'French'],
        ['value' => 'es', 'label' => 'Spanish'],
        ['value' => 'pt', 'label' => 'Portuguese'],
        ['value' => 'ja', 'label' => 'Japanese'],
        ['value' => 'zh', 'label' => 'Chinese'],
        ['value' => 'ko', 'label' => 'Korean'],
    ];

    $dateFormatOptions = [
        ['value' => 'YYYY-MM-DD', 'label' => '2026-02-16 (ISO)'],
        ['value' => 'DD/MM/YYYY', 'label' => '16/02/2026'],
        ['value' => 'MM/DD/YYYY', 'label' => '02/16/2026'],
        ['value' => 'DD.MM.YYYY', 'label' => '16.02.2026'],
        ['value' => 'MMM DD, YYYY', 'label' => 'Feb 16, 2026'],
    ];

    // Workspace statistics
    $projectsCount = $team->projects()->count();
    $serversCount = $team->servers()->count();
    $applicationsCount = $team->applications()->count();
    $membersCount = $team->members()->count();

    // Owner info
    $owner = $team->members()->wherePivot('role', 'owner')->first();

    // Use permission sets system for edit access
    $permService = app(\App\Services\Authorization\PermissionService::class);
    $canEdit = $permService->userHasPermission($user, 'settings.update');

    return Inertia::render('Settings/Workspace', [
        'workspace' => [
            'id' => $team->id,
            'name' => $team->name,
            'slug' => $slug,
            'logo' => $team->logo,
            'description' => $team->description ?? '',
            'timezone' => $team->timezone ?? 'UTC',
            'defaultEnvironment' => $team->default_environment ?? 'production',
            'locale' => $team->workspace_locale ?? 'en',
            'dateFormat' => $team->workspace_date_format ?? 'YYYY-MM-DD',
            'personalTeam' => $team->personal_team,
            'createdAt' => $team->created_at->toISOString(),
            'owner' => $owner ? [
                'name' => $owner->name,
                'email' => $owner->email,
            ] : null,
        ],
        'stats' => [
            'projects' => $projectsCount,
            'servers' => $serversCount,
            'applications' => $applicationsCount,
            'members' => $membersCount,
        ],
        'timezones' => $timezones,
        'environmentOptions' => $environmentOptions,
        'localeOptions' => $localeOptions,
        'dateFormatOptions' => $dateFormatOptions,
        'canEdit' => $canEdit,
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

Route::post('/settings/team/members/{id}/permission-set', function (Request $request, string $id) {
    $request->validate([
        'permission_set_id' => 'nullable|integer',
    ]);

    $team = currentTeam();
    $currentUser = auth()->user();

    $errorResponse = function (string $message) use ($request) {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return redirect()->back()->withErrors(['permission_set' => $message]);
    };

    // Only owner/admin can assign permission sets
    if (! $currentUser->isAdmin()) {
        return $errorResponse('You do not have permission to assign permission sets');
    }

    $member = $team->members()->where('user_id', $id)->first();
    if (! $member) {
        return $errorResponse('Member not found in this team');
    }

    $memberRole = $member->pivot->role;

    // Cannot assign permission set to self
    if ((int) $id === $currentUser->id) {
        return $errorResponse('You cannot change your own permission set');
    }

    // Admin cannot modify admin/owner
    if ($currentUser->isAdmin() && ! $currentUser->isOwner()) {
        if (in_array($memberRole, ['owner', 'admin'])) {
            return $errorResponse('Only owner can configure permission sets for admins');
        }
    }

    // Owner cannot be restricted
    if ($memberRole === 'owner') {
        return $errorResponse('Owner cannot have a restricted permission set');
    }

    // Validate permission set belongs to this team
    $permissionSetId = $request->permission_set_id;
    if ($permissionSetId !== null) {
        $exists = \App\Models\PermissionSet::forTeam($team->id)
            ->where('id', $permissionSetId)
            ->exists();

        if (! $exists) {
            return $errorResponse('Permission set not found for this team');
        }
    }

    $team->members()->updateExistingPivot($id, ['permission_set_id' => $permissionSetId]);

    // Clear permission cache
    $permissionService = app(\App\Services\Authorization\PermissionService::class);
    $permissionService->clearTeamCache($team);

    if ($request->expectsJson()) {
        return response()->json(['message' => 'Permission set updated successfully']);
    }

    return redirect()->back()->with('success', 'Permission set updated successfully');
})->name('settings.team.members.update-permission-set');

// Get member permissions data (JSON) for quick permissions modal
Route::get('/settings/team/members/{id}/permissions', function (string $id) {
    $team = currentTeam();
    $currentUser = auth()->user();

    // Only owner/admin can view permission settings
    if (! $currentUser->isAdmin()) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $member = $team->members()->where('user_id', $id)->first();
    if (! $member) {
        return response()->json(['message' => 'Member not found'], 404);
    }

    $memberRole = $member->pivot->role;

    // Cannot edit own permissions
    if ((int) $id === $currentUser->id) {
        return response()->json(['message' => 'Cannot edit your own permissions'], 403);
    }

    // Owner cannot be restricted
    if ($memberRole === 'owner') {
        return response()->json(['message' => 'Owner cannot have restricted permissions'], 403);
    }

    // Admin cannot modify admin/owner
    if (! $currentUser->isOwner() && in_array($memberRole, ['owner', 'admin'])) {
        return response()->json(['message' => 'Only owner can configure permissions for admins'], 403);
    }

    // Get all permission sets for this team
    $permissionSets = \App\Models\PermissionSet::forTeam($team->id)
        ->withCount('permissions')
        ->orderBy('is_system', 'desc')
        ->orderBy('name')
        ->get()
        ->map(fn ($set) => [
            'id' => $set->id,
            'name' => $set->name,
            'slug' => $set->slug,
            'description' => $set->description,
            'is_system' => $set->is_system,
            'color' => $set->color,
            'icon' => $set->icon,
            'permissions_count' => $set->permissions_count,
        ]);

    // Get all permissions grouped by category
    $allPermissions = \App\Models\Permission::orderBy('sort_order')
        ->get()
        ->groupBy('category')
        ->map(fn ($group) => $group->map(fn ($p) => [
            'id' => $p->id,
            'key' => $p->key,
            'name' => $p->name,
            'description' => $p->description,
            'resource' => $p->resource,
            'action' => $p->action,
            'is_sensitive' => $p->is_sensitive,
        ])->values()->all());

    // Get current permission set assignment
    $currentPermissionSetId = $member->pivot->permission_set_id;
    $currentPermissions = [];
    $isPersonalSet = false;
    $personalSetId = null;

    // Check if the current set is a personal set
    if ($currentPermissionSetId) {
        $currentSet = \App\Models\PermissionSet::with('permissions')->find($currentPermissionSetId);
        if ($currentSet) {
            $isPersonalSet = str_starts_with($currentSet->slug, 'personal-') && ! $currentSet->is_system;
            if ($isPersonalSet) {
                $personalSetId = $currentSet->id;
            }
            $currentPermissions = $currentSet->permissions->map(fn ($p) => [
                'permission_id' => $p->id,
                'environment_restrictions' => $p->pivot->environment_restrictions ?? [],
            ])->values()->all();
        }
    }

    // Get environments for restriction options
    $environments = \App\Models\Environment::whereHas('project', function ($query) use ($team) {
        $query->where('team_id', $team->id);
    })
        ->select('id', 'name')
        ->distinct('name')
        ->get()
        ->map(fn ($e) => ['id' => $e->id, 'name' => $e->name]);

    // Get effective permissions (what the user actually has right now)
    $permissionService = app(\App\Services\Authorization\PermissionService::class);
    $effectivePermissions = $permissionService->getUserEffectivePermissions($member);
    $activePermissionIds = [];
    foreach (\App\Models\Permission::all() as $permission) {
        if (! empty($effectivePermissions[$permission->key])) {
            $activePermissionIds[] = $permission->id;
        }
    }

    return response()->json([
        'permissionSets' => $permissionSets,
        'allPermissions' => $allPermissions,
        'currentPermissionSetId' => $currentPermissionSetId,
        'currentPermissions' => $currentPermissions,
        'isPersonalSet' => $isPersonalSet,
        'personalSetId' => $personalSetId,
        'environments' => $environments,
        'activePermissionIds' => $activePermissionIds,
        'member' => [
            'id' => $member->id,
            'name' => $member->name,
            'role' => $memberRole,
        ],
    ]);
})->name('settings.team.members.permissions');

// Save custom permissions for a member (creates/updates personal permission set)
Route::post('/settings/team/members/{id}/permissions/custom', function (Request $request, string $id) {
    $request->validate([
        'permissions' => 'required|array',
        'permissions.*.permission_id' => 'required|integer|exists:permissions,id',
        'permissions.*.environment_restrictions' => 'nullable|array',
    ]);

    $team = currentTeam();
    $currentUser = auth()->user();

    // Only owner/admin can assign permissions
    if (! $currentUser->isAdmin()) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $member = $team->members()->where('user_id', $id)->first();
    if (! $member) {
        return response()->json(['message' => 'Member not found'], 404);
    }

    $memberRole = $member->pivot->role;

    // Cannot edit own permissions
    if ((int) $id === $currentUser->id) {
        return response()->json(['message' => 'Cannot edit your own permissions'], 403);
    }

    // Owner cannot be restricted
    if ($memberRole === 'owner') {
        return response()->json(['message' => 'Owner cannot have restricted permissions'], 403);
    }

    // Admin cannot modify admin/owner
    if (! $currentUser->isOwner() && in_array($memberRole, ['owner', 'admin'])) {
        return response()->json(['message' => 'Only owner can configure permissions for admins'], 403);
    }

    // Find or create personal permission set
    $nameSlug = \Illuminate\Support\Str::slug($member->name);
    $personalSlug = "personal-{$nameSlug}-{$member->id}";

    $personalSet = \App\Models\PermissionSet::forTeam($team->id)
        ->where('slug', $personalSlug)
        ->first();

    if (! $personalSet) {
        $personalSet = \App\Models\PermissionSet::create([
            'name' => "Personal ({$member->name})",
            'slug' => $personalSlug,
            'description' => "Custom permissions for {$member->name}",
            'scope_type' => 'team',
            'scope_id' => $team->id,
            'is_system' => false,
            'color' => 'foreground-muted',
            'icon' => 'user',
        ]);
    }

    // Sync permissions
    $personalSet->syncPermissionsWithRestrictions($request->permissions);

    // Assign permission set to the member
    $permissionService = app(\App\Services\Authorization\PermissionService::class);
    $permissionService->assignPermissionSet($member, $personalSet, 'team', $team->id);

    // Clear cache
    $permissionService->clearTeamCache($team);

    return response()->json([
        'message' => 'Custom permissions saved successfully',
        'permission_set_id' => $personalSet->id,
    ]);
})->name('settings.team.members.permissions.custom');

Route::delete('/settings/team/members/{id}', function (string $id) {
    $team = currentTeam();
    $currentUser = auth()->user();

    // Find the member in the team
    $member = $team->members()->where('user_id', $id)->first();
    if (! $member) {
        return redirect()->back()->withErrors(['member' => 'Member not found in this team']);
    }

    $isLeavingTeam = (int) $id === $currentUser->id;

    // If user is trying to leave the team (remove themselves)
    if ($isLeavingTeam) {
        // Check if this is the last owner - cannot leave as last owner
        $owners = $team->members()->wherePivot('role', 'owner')->get();
        if ($member->pivot->role === 'owner' && $owners->count() <= 1) {
            return redirect()->back()->withErrors(['member' => 'Cannot leave the team as the last owner. Please transfer ownership first.']);
        }

        // Archive the member's contributions before leaving
        $action = new \App\Actions\Team\ArchiveAndKickMemberAction;
        $action->execute($team, $member, $currentUser, 'Self-removal: member left the team');

        return redirect('/dashboard')->with('success', 'You have left the team');
    }

    // If user is trying to remove another member - check admin permission
    if (! $currentUser->isAdmin()) {
        return redirect()->back()->withErrors(['member' => 'You do not have permission to remove members']);
    }

    // Check if this is the last owner
    $owners = $team->members()->wherePivot('role', 'owner')->get();
    if ($member->pivot->role === 'owner' && $owners->count() <= 1) {
        return redirect()->back()->withErrors(['member' => 'Cannot remove the last owner of the team']);
    }

    // Archive and kick using the action (backward compatible with simple DELETE)
    $action = new \App\Actions\Team\ArchiveAndKickMemberAction;
    $action->execute($team, $member, $currentUser);

    return redirect()->back()->with('success', 'Member removed from team successfully');
})->name('settings.team.members.destroy');

// Get member contributions (JSON) for kick modal preview
Route::get('/settings/team/members/{id}/contributions', function (string $id) {
    $team = currentTeam();
    $currentUser = auth()->user();

    if (! $currentUser->isAdmin()) {
        abort(403);
    }

    $member = $team->members()->where('user_id', $id)->first();
    if (! $member) {
        abort(404);
    }

    $action = new \App\Actions\Team\ArchiveAndKickMemberAction;
    $contributions = $action->getContributions($team, $member);

    // Also return other team members (for transfer target selection)
    $otherMembers = $team->members()
        ->where('user_id', '!=', $id)
        ->where('user_id', '!=', $currentUser->id)
        ->get()
        ->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'email' => $m->email,
        ]);

    return response()->json([
        'contributions' => $contributions,
        'teamMembers' => $otherMembers,
    ]);
})->name('settings.team.members.contributions');

// Kick member with archive and optional transfers
Route::post('/settings/team/members/{id}/kick', function (Request $request, string $id) {
    $team = currentTeam();
    $currentUser = auth()->user();

    if (! $currentUser->isAdmin()) {
        return redirect()->back()->withErrors(['member' => 'You do not have permission to remove members']);
    }

    $member = $team->members()->where('user_id', $id)->first();
    if (! $member) {
        return redirect()->back()->withErrors(['member' => 'Member not found in this team']);
    }

    // Cannot kick owner if last owner
    $owners = $team->members()->wherePivot('role', 'owner')->get();
    if ($member->pivot->role === 'owner' && $owners->count() <= 1) {
        return redirect()->back()->withErrors(['member' => 'Cannot remove the last owner of the team']);
    }

    $reason = $request->input('reason');
    $transfers = $request->input('transfers', []);

    $action = new \App\Actions\Team\ArchiveAndKickMemberAction;
    $archive = $action->execute($team, $member, $currentUser, $reason, $transfers);

    return response()->json([
        'success' => true,
        'archive_id' => $archive->id,
        'archive_uuid' => $archive->uuid,
    ]);
})->name('settings.team.members.kick');

// Member Archives list page
Route::get('/settings/team/archives', function (Request $request) {
    $team = currentTeam();
    $permService = app(\App\Services\Authorization\PermissionService::class);
    if (! $permService->userHasPermission(auth()->user(), 'team.archives')) {
        abort(403);
    }

    $showDeleted = $request->boolean('show_deleted');

    $query = \App\Models\MemberArchive::forTeam($team->id)->orderByDesc('created_at');
    if ($showDeleted) {
        $query->withTrashed();
    }

    $archives = $query->get()->map(fn ($a) => [
        'id' => $a->id,
        'uuid' => $a->uuid,
        'member_name' => $a->member_name,
        'member_email' => $a->member_email,
        'member_role' => $a->member_role,
        'member_joined_at' => $a->member_joined_at?->toISOString(),
        'kicked_by_name' => $a->kicked_by_name,
        'kick_reason' => $a->kick_reason,
        'total_actions' => $a->contribution_summary['total_actions'] ?? 0,
        'deploy_count' => $a->contribution_summary['deploy_count'] ?? 0,
        'status' => $a->status,
        'created_at' => $a->created_at->toISOString(),
        'deleted_at' => $a->deleted_at?->toISOString(),
    ]);

    return Inertia::render('Settings/Team/Archives', [
        'archives' => $archives,
        'showDeleted' => $showDeleted,
    ]);
})->name('settings.team.archives');

// Bulk export archives
Route::get('/settings/team/archives/export-all', function (Request $request) {
    $team = currentTeam();
    $permService = app(\App\Services\Authorization\PermissionService::class);
    if (! $permService->userHasPermission(auth()->user(), 'team.archives')) {
        abort(403);
    }

    $format = $request->query('format', 'json');
    $ids = $request->query('ids', []);

    $query = \App\Models\MemberArchive::forTeam($team->id)->orderByDesc('created_at');
    if (! empty($ids)) {
        $query->whereIn('id', $ids);
    }
    $archives = $query->get();

    if ($format === 'csv') {
        $filename = 'archives-bulk-'.now()->format('Y-m-d').'.csv';

        return response()->stream(function () use ($archives) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Archive', 'Section', 'Key', 'Value']);

            foreach ($archives as $archive) {
                $label = preg_replace('/[^a-zA-Z0-9_-]/', '_', $archive->member_name);
                $archiveData = [
                    'member_name' => $archive->member_name,
                    'member_email' => $archive->member_email,
                    'member_role' => $archive->member_role,
                    'member_joined_at' => $archive->member_joined_at?->toISOString(),
                    'removed_at' => $archive->created_at->toISOString(),
                    'kicked_by' => $archive->kicked_by_name,
                    'kick_reason' => $archive->kick_reason,
                    'notes' => $archive->notes,
                ];
                foreach ($archiveData as $key => $value) {
                    fputcsv($handle, [$label, 'Archive', $key, $value ?? '']);
                }

                if (! empty($archive->contribution_summary)) {
                    foreach ($archive->contribution_summary as $key => $value) {
                        fputcsv($handle, [$label, 'Contributions', $key, is_array($value) ? json_encode($value) : ($value ?? '')]);
                    }
                }
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // Default: JSON
    $filename = 'archives-bulk-'.now()->format('Y-m-d').'.json';
    $exportData = $archives->map(function ($archive) {
        $transfers = $archive->getTransfers()->map(fn ($t) => [
            'id' => $t->id,
            'resource_type' => class_basename($t->transferable_type),
            'resource_name' => $t->resource_snapshot['name'] ?? 'Unknown',
            'to_user' => $t->toUser?->name ?? 'Unknown',
            'status' => $t->status,
            'completed_at' => $t->completed_at?->toISOString(),
        ]);

        return [
            'archive' => [
                'member_name' => $archive->member_name,
                'member_email' => $archive->member_email,
                'member_role' => $archive->member_role,
                'member_joined_at' => $archive->member_joined_at?->toISOString(),
                'removed_at' => $archive->created_at->toISOString(),
                'kicked_by' => $archive->kicked_by_name,
                'kick_reason' => $archive->kick_reason,
                'notes' => $archive->notes,
            ],
            'contributions' => $archive->contribution_summary,
            'access_snapshot' => $archive->access_snapshot,
            'transfers' => $transfers->toArray(),
        ];
    });

    return response()->json($exportData)->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
})->name('settings.team.archives.export-all');

// Restore soft-deleted archive
Route::post('/settings/team/archives/{id}/restore', function (string $id) {
    $team = currentTeam();
    $permService = app(\App\Services\Authorization\PermissionService::class);
    if (! $permService->userHasPermission(auth()->user(), 'team.archives')) {
        abort(403);
    }

    $archive = \App\Models\MemberArchive::withTrashed()->forTeam($team->id)->findOrFail($id);
    $archive->restore();

    return redirect('/settings/team/archives')->with('success', 'Archive restored successfully.');
})->name('settings.team.archive.restore');

// Member Archive detail page
Route::get('/settings/team/archives/{id}', function (string $id) {
    $team = currentTeam();
    $permService = app(\App\Services\Authorization\PermissionService::class);
    if (! $permService->userHasPermission(auth()->user(), 'team.archives')) {
        abort(403);
    }

    $archive = \App\Models\MemberArchive::forTeam($team->id)->findOrFail($id);

    $transfers = $archive->getTransfers()->map(fn ($t) => [
        'id' => $t->id,
        'resource_type' => class_basename($t->transferable_type),
        'resource_name' => $t->resource_snapshot['name'] ?? 'Unknown',
        'to_user' => $t->toUser?->name ?? 'Unknown',
        'status' => $t->status,
        'completed_at' => $t->completed_at?->toISOString(),
    ]);

    // Team members for post-kick transfer dropdown
    $teamMembers = $team->members->map(fn ($m) => [
        'id' => $m->id,
        'name' => $m->name,
        'email' => $m->email,
    ]);

    // Filter top_resources to only existing ones for transfer UI
    $memberResources = [];
    $topResources = $archive->contribution_summary['top_resources'] ?? [];
    foreach ($topResources as $resource) {
        try {
            $type = $resource['full_type'] ?? null;
            if ($type && class_exists($type) && $type::find($resource['id'])) {
                $memberResources[] = $resource;
            }
        } catch (\Exception $e) {
            // Skip non-existing resources
        }
    }

    return Inertia::render('Settings/Team/ArchiveDetail', [
        'archive' => [
            'id' => $archive->id,
            'uuid' => $archive->uuid,
            'member_name' => $archive->member_name,
            'member_email' => $archive->member_email,
            'member_role' => $archive->member_role,
            'member_joined_at' => $archive->member_joined_at?->toISOString(),
            'kicked_by_name' => $archive->kicked_by_name,
            'kick_reason' => $archive->kick_reason,
            'contribution_summary' => $archive->contribution_summary,
            'access_snapshot' => $archive->access_snapshot,
            'status' => $archive->status,
            'notes' => $archive->notes,
            'created_at' => $archive->created_at->toISOString(),
        ],
        'transfers' => $transfers,
        'teamMembers' => $teamMembers,
        'memberResources' => $memberResources,
    ]);
})->name('settings.team.archive.detail');

// Archive export (JSON/CSV download)
Route::get('/settings/team/archives/{id}/export', function (string $id, Request $request) {
    $team = currentTeam();
    $permService = app(\App\Services\Authorization\PermissionService::class);
    if (! $permService->userHasPermission(auth()->user(), 'team.archives')) {
        abort(403);
    }

    $archive = \App\Models\MemberArchive::forTeam($team->id)->findOrFail($id);
    $format = $request->query('format', 'json');

    $transfers = $archive->getTransfers()->map(fn ($t) => [
        'id' => $t->id,
        'resource_type' => class_basename($t->transferable_type),
        'resource_name' => $t->resource_snapshot['name'] ?? 'Unknown',
        'to_user' => $t->toUser?->name ?? 'Unknown',
        'status' => $t->status,
        'completed_at' => $t->completed_at?->toISOString(),
    ]);

    $exportData = [
        'archive' => [
            'member_name' => $archive->member_name,
            'member_email' => $archive->member_email,
            'member_role' => $archive->member_role,
            'member_joined_at' => $archive->member_joined_at?->toISOString(),
            'removed_at' => $archive->created_at->toISOString(),
            'kicked_by' => $archive->kicked_by_name,
            'kick_reason' => $archive->kick_reason,
            'notes' => $archive->notes,
        ],
        'contributions' => $archive->contribution_summary,
        'access_snapshot' => $archive->access_snapshot,
        'transfers' => $transfers->toArray(),
    ];

    if ($format === 'csv') {
        $filename = 'archive-'.preg_replace('/[^a-zA-Z0-9_-]/', '_', $archive->member_name).'-'.now()->format('Y-m-d').'.csv';

        return response()->stream(function () use ($exportData) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Section', 'Key', 'Value']);

            // Archive info
            foreach ($exportData['archive'] as $key => $value) {
                fputcsv($handle, ['Archive', $key, $value ?? '']);
            }

            // Contributions
            if (! empty($exportData['contributions'])) {
                foreach ($exportData['contributions'] as $key => $value) {
                    if (is_array($value)) {
                        fputcsv($handle, ['Contributions', $key, json_encode($value)]);
                    } else {
                        fputcsv($handle, ['Contributions', $key, $value ?? '']);
                    }
                }
            }

            // Access snapshot
            if (! empty($exportData['access_snapshot'])) {
                foreach ($exportData['access_snapshot'] as $key => $value) {
                    if (is_array($value)) {
                        fputcsv($handle, ['Access Snapshot', $key, json_encode($value)]);
                    } else {
                        fputcsv($handle, ['Access Snapshot', $key, $value ?? '']);
                    }
                }
            }

            // Transfers
            foreach ($exportData['transfers'] as $i => $transfer) {
                foreach ($transfer as $key => $value) {
                    fputcsv($handle, ['Transfer #'.($i + 1), $key, $value ?? '']);
                }
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // Default: JSON
    $filename = 'archive-'.preg_replace('/[^a-zA-Z0-9_-]/', '_', $archive->member_name).'-'.now()->format('Y-m-d').'.json';

    return response()->json($exportData)->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
})->name('settings.team.archive.export');

// Archive notes update
Route::patch('/settings/team/archives/{id}/notes', function (string $id, Request $request) {
    $team = currentTeam();
    $permService = app(\App\Services\Authorization\PermissionService::class);
    if (! $permService->userHasPermission(auth()->user(), 'team.archives')) {
        abort(403);
    }

    $request->validate([
        'notes' => 'nullable|string|max:5000',
    ]);

    $archive = \App\Models\MemberArchive::forTeam($team->id)->findOrFail($id);
    $archive->update(['notes' => $request->input('notes')]);

    \App\Models\AuditLog::log(
        'archive_notes_updated',
        $archive,
        "Updated notes for archived member {$archive->member_name}",
        ['archive_id' => $archive->id]
    );

    return redirect()->back();
})->name('settings.team.archive.notes');

// Archive resource transfer (post-kick)
Route::post('/settings/team/archives/{id}/transfer', function (string $id, Request $request) {
    $team = currentTeam();
    $permService = app(\App\Services\Authorization\PermissionService::class);
    if (! $permService->userHasPermission(auth()->user(), 'team.archives')) {
        abort(403);
    }

    $request->validate([
        'transfers' => 'required|array|min:1',
        'transfers.*.resource_type' => 'required|string',
        'transfers.*.resource_id' => 'required|integer',
        'transfers.*.resource_name' => 'required|string|max:255',
        'transfers.*.to_user_id' => 'required|integer',
    ]);

    // Whitelist allowed resource types to prevent class injection
    $allowedResourceTypes = [
        'App\\Models\\Application',
        'App\\Models\\Service',
        'App\\Models\\Server',
        'App\\Models\\Project',
        'App\\Models\\StandalonePostgresql',
        'App\\Models\\StandaloneMysql',
        'App\\Models\\StandaloneMariadb',
        'App\\Models\\StandaloneRedis',
        'App\\Models\\StandaloneKeydb',
        'App\\Models\\StandaloneDragonfly',
        'App\\Models\\StandaloneClickhouse',
        'App\\Models\\StandaloneMongodb',
    ];

    $archive = \App\Models\MemberArchive::forTeam($team->id)->findOrFail($id);

    $newTransferIds = [];
    foreach ($request->input('transfers') as $transfer) {
        // Skip invalid resource types (security: prevent class injection)
        if (! in_array($transfer['resource_type'], $allowedResourceTypes, true)) {
            continue;
        }

        // Verify the target user is in the team
        $targetMember = $team->members()->where('user_id', $transfer['to_user_id'])->first();
        if (! $targetMember) {
            continue;
        }

        $record = \App\Models\TeamResourceTransfer::create([
            'transfer_type' => \App\Models\TeamResourceTransfer::TYPE_ARCHIVE,
            'transferable_type' => $transfer['resource_type'],
            'transferable_id' => $transfer['resource_id'],
            'from_team_id' => $team->id,
            'to_team_id' => $team->id,
            'from_user_id' => $archive->user_id,
            'to_user_id' => $transfer['to_user_id'],
            'initiated_by' => $user->id,
            'reason' => "Post-kick transfer for {$transfer['resource_name']}",
            'resource_snapshot' => [
                'name' => $transfer['resource_name'],
                'type' => class_basename($transfer['resource_type']),
            ],
        ]);
        $record->markAsCompleted();
        $newTransferIds[] = $record->id;
    }

    // Append new transfer IDs to archive
    $existingIds = $archive->transfer_ids ?? [];
    $archive->update(['transfer_ids' => array_merge($existingIds, $newTransferIds)]);

    \App\Models\AuditLog::log(
        'archive_resources_transferred',
        $archive,
        'Transferred '.count($newTransferIds)." resource(s) from archived member {$archive->member_name}",
        [
            'archive_id' => $archive->id,
            'transfer_ids' => $newTransferIds,
        ]
    );

    return response()->json([
        'success' => true,
        'transferred' => count($newTransferIds),
    ]);
})->name('settings.team.archive.transfer');

// Archive delete (soft delete — data preserved for audit)
Route::delete('/settings/team/archives/{id}', function (string $id) {
    $team = currentTeam();
    $permService = app(\App\Services\Authorization\PermissionService::class);
    if (! $permService->userHasPermission(auth()->user(), 'team.archives')) {
        abort(403);
    }

    $archive = \App\Models\MemberArchive::forTeam($team->id)->findOrFail($id);

    \App\Models\AuditLog::log(
        'archive_deleted',
        $archive,
        "Soft-deleted archive for member {$archive->member_name} ({$archive->member_email})",
        [
            'archive_id' => $archive->id,
            'member_name' => $archive->member_name,
            'member_email' => $archive->member_email,
        ]
    );

    $archive->delete();

    return redirect('/settings/team/archives')->with('success', 'Archive deleted successfully.');
})->name('settings.team.archive.delete');

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
    $validLocales = ['en', 'ru', 'de', 'fr', 'es', 'pt', 'ja', 'zh', 'ko'];
    $validDateFormats = ['YYYY-MM-DD', 'DD/MM/YYYY', 'MM/DD/YYYY', 'DD.MM.YYYY', 'MMM DD, YYYY'];

    $request->validate([
        'name' => 'required|string|max:255',
        'description' => 'nullable|string|max:1000',
        'timezone' => ['nullable', 'string', \Illuminate\Validation\Rule::in($validTimezones)],
        'defaultEnvironment' => ['nullable', 'string', \Illuminate\Validation\Rule::in($validEnvironments)],
        'locale' => ['nullable', 'string', \Illuminate\Validation\Rule::in($validLocales)],
        'dateFormat' => ['nullable', 'string', \Illuminate\Validation\Rule::in($validDateFormats)],
    ]);

    $team = currentTeam();
    $user = auth()->user();
    $permService = app(\App\Services\Authorization\PermissionService::class);

    if (! $permService->userHasPermission($user, 'settings.update')) {
        return redirect()->back()->withErrors(['workspace' => 'You do not have permission to update workspace settings']);
    }

    $team->update([
        'name' => $request->name,
        'description' => $request->description,
        'timezone' => $request->timezone ?? 'UTC',
        'default_environment' => $request->defaultEnvironment ?? 'production',
        'workspace_locale' => $request->locale ?? 'en',
        'workspace_date_format' => $request->dateFormat ?? 'YYYY-MM-DD',
    ]);

    return redirect()->back()->with('success', 'Workspace updated successfully');
})->name('settings.workspace.update');

Route::post('/settings/workspace/logo', function (Request $request) {
    $request->validate([
        'logo' => 'required|image|mimes:jpeg,jpg,png,gif,webp,svg|max:2048', // 2MB max
    ]);

    $team = currentTeam();
    $user = auth()->user();
    $permService = app(\App\Services\Authorization\PermissionService::class);

    if (! $permService->userHasPermission($user, 'settings.update')) {
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
    $permService = app(\App\Services\Authorization\PermissionService::class);

    if (! $permService->userHasPermission($user, 'settings.update')) {
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

    // Check if viewing own profile
    $isCurrentUser = (int) $id === $currentUser->id;

    // Get current user's role and permissions
    $currentUserRole = $currentUser->teams()->where('team_id', $team->id)->first()?->pivot->role ?? 'member';
    $canManageTeam = in_array($currentUserRole, ['owner', 'admin', 'developer']);

    // Determine if current user can edit this member's permissions/projects
    // Admin/developer cannot modify admin or owner; only owner can modify everyone
    $memberRole = $member->pivot->role ?? 'member';
    $canEditPermissions = $canManageTeam
        && ! $isCurrentUser
        && $memberRole !== 'owner'
        && ($currentUserRole === 'owner' || ! in_array($memberRole, ['owner', 'admin']));

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
        'avatar' => $member->avatar ? '/storage/'.$member->avatar : null,
        'role' => $member->pivot->role ?? 'member',
        'permissionSetId' => $member->pivot->permission_set_id,
        'joinedAt' => $member->pivot->created_at?->toDateString() ?? $member->created_at->toDateString(),
        'lastActive' => $lastActive,
    ];

    // Fetch permission sets for the team
    $permissionSets = \App\Models\PermissionSet::forTeam($team->id)
        ->withCount('permissions')
        ->with('permissions')
        ->orderBy('is_system', 'desc')
        ->orderBy('name')
        ->get()
        ->map(fn ($set) => [
            'id' => $set->id,
            'name' => $set->name,
            'slug' => $set->slug,
            'description' => $set->description,
            'is_system' => $set->is_system,
            'color' => $set->color,
            'icon' => $set->icon,
            'permissions_count' => $set->permissions_count,
            'permissions' => $set->permissions->map(fn ($p) => [
                'id' => $p->id,
                'key' => $p->key,
                'name' => $p->name,
                'description' => $p->description,
                'category' => $p->category,
                'is_sensitive' => $p->is_sensitive ?? false,
            ])->values()->all(),
        ]);

    // Get allowed projects info
    $allowedProjects = $member->pivot->allowed_projects;
    $hasFullAccess = $allowedProjects === null
        || (is_array($allowedProjects) && in_array('*', $allowedProjects, true));

    // Get team projects with access info
    $teamProjectIds = is_array($allowedProjects) ? $allowedProjects : [];
    $projects = $team->projects()->get()->map(fn ($project) => [
        'id' => $project->id,
        'name' => $project->name,
        'role' => $member->pivot->role ?? 'member',
        'hasAccess' => $hasFullAccess || in_array($project->id, $teamProjectIds),
        'lastAccessed' => $project->updated_at->toISOString(),
    ]);

    // Get recent activities from AuditLog (uses stored resource_name, survives deletion)
    $activities = \App\Models\AuditLog::forTeam($team->id)
        ->byUser($member->id)
        ->latest()
        ->limit(20)
        ->get()
        ->map(function ($auditLog) use ($member) {
            // Map resource_type to frontend type
            $resourceType = match (true) {
                str_contains($auditLog->resource_type ?? '', 'Application') => 'application',
                str_contains($auditLog->resource_type ?? '', 'Service') => 'application',
                str_contains($auditLog->resource_type ?? '', 'Standalone') => 'database',
                str_contains($auditLog->resource_type ?? '', 'Server') => 'server',
                str_contains($auditLog->resource_type ?? '', 'Project') => 'project',
                str_contains($auditLog->resource_type ?? '', 'Team') => 'team',
                default => 'application',
            };

            // Map action to ActivityAction enum
            $action = match (true) {
                $auditLog->action === 'deploy' => 'deployment_started',
                $auditLog->action === 'create' && $resourceType === 'database' => 'database_created',
                $auditLog->action === 'create' && $resourceType === 'server' => 'server_connected',
                $auditLog->action === 'start' => 'application_started',
                $auditLog->action === 'stop' => 'application_stopped',
                $auditLog->action === 'restart' => 'application_restarted',
                $auditLog->action === 'delete' && $resourceType === 'database' => 'database_deleted',
                $auditLog->action === 'update' => 'settings_updated',
                default => 'settings_updated',
            };

            return [
                'id' => (string) $auditLog->id,
                'action' => $action,
                'description' => $auditLog->description ?? $auditLog->formatted_action,
                'user' => [
                    'name' => $member->name,
                    'email' => $member->email,
                ],
                'resource' => [
                    'type' => $resourceType,
                    'name' => $auditLog->resource_name ?? 'Unknown',
                    'id' => (string) ($auditLog->resource_id ?? 0),
                ],
                'timestamp' => $auditLog->created_at->toISOString(),
            ];
        });

    // Other team members for transfer target selection in kick modal
    $teamMembers = $team->members()
        ->where('user_id', '!=', $id)
        ->get()
        ->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'email' => $m->email,
        ]);

    return Inertia::render('Settings/Members/Show', [
        'member' => $memberData,
        'projects' => $projects,
        'activities' => $activities,
        'isCurrentUser' => $isCurrentUser,
        'canManageTeam' => $canManageTeam,
        'canEditPermissions' => $canEditPermissions,
        'permissionSets' => $permissionSets,
        'allowedProjects' => $allowedProjects,
        'hasFullProjectAccess' => $hasFullAccess,
        'teamMembers' => $teamMembers,
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
            'avatar' => $user->avatar ? '/storage/'.$user->avatar : null,
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

// Permission Sets (CRUD routes)
Route::get('/settings/team/permission-sets', [\App\Http\Controllers\Inertia\PermissionSetController::class, 'index'])
    ->name('settings.team.permission-sets.index');
Route::get('/settings/team/permission-sets/create', [\App\Http\Controllers\Inertia\PermissionSetController::class, 'create'])
    ->name('settings.team.permission-sets.create');
Route::post('/settings/team/permission-sets', [\App\Http\Controllers\Inertia\PermissionSetController::class, 'store'])
    ->name('settings.team.permission-sets.store');
Route::get('/settings/team/permission-sets/{id}', [\App\Http\Controllers\Inertia\PermissionSetController::class, 'show'])
    ->name('settings.team.permission-sets.show');
Route::get('/settings/team/permission-sets/{id}/edit', [\App\Http\Controllers\Inertia\PermissionSetController::class, 'edit'])
    ->name('settings.team.permission-sets.edit');
Route::put('/settings/team/permission-sets/{id}', [\App\Http\Controllers\Inertia\PermissionSetController::class, 'update'])
    ->name('settings.team.permission-sets.update');
Route::delete('/settings/team/permission-sets/{id}', [\App\Http\Controllers\Inertia\PermissionSetController::class, 'destroy'])
    ->name('settings.team.permission-sets.destroy');

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
    // null = all projects (full access), [] = no access, [1,2,3] = specific projects
    $hasFullAccess = $allowedProjects === null || (is_array($allowedProjects) && in_array('*', $allowedProjects, true));
    $hasNoAccess = is_array($allowedProjects) && empty($allowedProjects);

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
        if ($request->wantsJson()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return redirect()->back()->withErrors(['projects' => 'Unauthorized']);
    }

    $member = $team->members()->where('user_id', $id)->first();
    if (! $member) {
        if ($request->wantsJson()) {
            return response()->json(['message' => 'Member not found'], 404);
        }

        return redirect()->back()->withErrors(['projects' => 'Member not found']);
    }

    $memberRole = $member->pivot->role;

    // Only owner can manage everyone
    // Admin cannot manage owner or other admins
    if ($currentUser->isAdmin() && ! $currentUser->isOwner()) {
        if (in_array($memberRole, ['owner', 'admin'])) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Only owner can configure project access for admins'], 403);
            }

            return redirect()->back()->withErrors(['projects' => 'Only owner can configure project access for admins']);
        }
    }

    // Owner always has full access - cannot be restricted
    if ($memberRole === 'owner') {
        if ($request->wantsJson()) {
            return response()->json(['message' => 'Owner always has full access and cannot be restricted'], 422);
        }

        return redirect()->back()->withErrors(['projects' => 'Owner always has full access and cannot be restricted']);
    }

    // Determine allowed_projects value based on allow-by-default model
    // null = all projects (full access, default for existing members)
    // [] = no access (explicit deny)
    // [1, 2, 3] = access to specific projects only

    $allowedProjects = []; // default: no access when explicitly setting

    if ($request->has('grant_all') && $request->grant_all === true) {
        // Grant access to all projects (null means all)
        $allowedProjects = null;
    } elseif ($request->has('allowed_projects')) {
        $allowedProjects = $request->allowed_projects;

        // If '*' is in array, treat as full access (null)
        if (is_array($allowedProjects) && in_array('*', $allowedProjects, true)) {
            $allowedProjects = null;
        } elseif (! empty($allowedProjects)) {
            // Validate that all project IDs belong to this team
            $teamProjectIds = $team->projects()->pluck('id')->toArray();
            $invalidIds = array_diff((array) $allowedProjects, $teamProjectIds);
            if (! empty($invalidIds)) {
                if ($request->wantsJson()) {
                    return response()->json(['message' => 'Invalid project IDs'], 422);
                }

                return redirect()->back()->withErrors(['projects' => 'Invalid project IDs']);
            }
        }
        // If empty array, keep it as [] - means no access
    }

    $team->members()->updateExistingPivot($id, [
        'allowed_projects' => $allowedProjects,
    ]);

    if ($request->wantsJson()) {
        return response()->json(['message' => 'Project access updated successfully']);
    }

    return redirect()->back()->with('success', 'Project access updated successfully');
})->name('settings.team.members.projects.update');

// Team Switcher - switch to a different team
Route::post('/teams/switch/{id}', function (string $id) {
    $user = auth()->user();

    // Verify user is a member of this team
    $team = $user->teams()->where('teams.id', $id)->first();
    if (! $team) {
        return redirect()->back()->with('error', 'You are not a member of this team');
    }

    // Update session with new team
    refreshSession($team);

    return redirect('/dashboard')->with('success', "Switched to {$team->name}");
})->name('teams.switch');
