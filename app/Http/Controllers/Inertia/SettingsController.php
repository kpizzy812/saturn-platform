<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Controllers\Controller;
use App\Models\InstanceSettings;
use App\Models\TeamInvitation;
use App\Notifications\Test;
use App\Notifications\TransactionalEmails\InvitationLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Display settings overview.
     */
    public function index(): Response
    {
        return Inertia::render('Settings/Index');
    }

    /**
     * Display account settings.
     */
    public function account(): Response
    {
        return Inertia::render('Settings/Account');
    }

    /**
     * Display team settings.
     */
    public function team(): Response
    {
        $team = auth()->user()->currentTeam();
        $user = auth()->user();

        // Get all team members with their roles and additional info
        $members = $team->members->map(function ($member) use ($team) {
            $pivot = $member->pivot;

            // Calculate project access for non-owners using TeamUser pivot
            $projectAccess = null;
            if ($pivot->role !== 'owner') {
                $totalProjects = $team->projects()->count();
                $allowedProjects = $pivot->allowed_projects;

                // Check for full access: null = all projects, '*' in array, or admin role
                $hasFullAccess = $pivot->role === 'admin' ||
                    $allowedProjects === null ||
                    (is_array($allowedProjects) && in_array('*', $allowedProjects, true));

                // Count accessible projects
                $accessibleCount = 0;
                if ($hasFullAccess) {
                    $accessibleCount = $totalProjects;
                } elseif (is_array($allowedProjects)) {
                    // Filter out '*' and count valid project IDs that actually exist
                    $projectIds = array_filter($allowedProjects, fn ($id) => is_int($id));
                    $accessibleCount = $team->projects()->whereIn('id', $projectIds)->count();
                }

                $projectAccess = [
                    'hasFullAccess' => $hasFullAccess || ($totalProjects > 0 && $accessibleCount >= $totalProjects),
                    'hasNoAccess' => $accessibleCount === 0 && ! $hasFullAccess,
                    'hasLimitedAccess' => ! $hasFullAccess && $accessibleCount > 0 && $accessibleCount < $totalProjects,
                    'count' => $accessibleCount,
                    'total' => $totalProjects,
                ];
            }

            // Find who invited this member
            $invitedBy = null;
            $invitation = TeamInvitation::where('email', $member->email)
                ->where('team_id', $team->id)
                ->first();
            if ($invitation && $invitation->invited_by) {
                $inviter = \App\Models\User::find($invitation->invited_by);
                if ($inviter) {
                    $invitedBy = [
                        'id' => $inviter->id,
                        'name' => $inviter->name,
                        'email' => $inviter->email,
                    ];
                }
            }

            return [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'avatar' => null,
                'role' => $pivot->role ?? 'member',
                'joinedAt' => $pivot->created_at ?? $member->created_at,
                'lastActive' => $member->updated_at ?? now(),
                'invitedBy' => $invitedBy,
                'projectAccess' => $projectAccess,
            ];
        });

        // Get pending invitations sent by the team
        $invitations = TeamInvitation::where('team_id', $team->id)
            ->get()
            ->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role ?? 'member',
                    'sentAt' => $invitation->created_at,
                    'link' => $invitation->link,
                ];
            });

        // Get invitations received by the current user for other teams
        $receivedInvitations = TeamInvitation::where('email', $user->email)
            ->where('team_id', '!=', $team->id)
            ->with('team')
            ->get()
            ->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'uuid' => $invitation->uuid,
                    'teamName' => $invitation->team->name ?? 'Unknown Team',
                    'role' => $invitation->role ?? 'member',
                    'sentAt' => $invitation->created_at,
                    'expiresAt' => $invitation->created_at->addDays(7),
                ];
            });

        return Inertia::render('Settings/Team', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'avatar' => null,
                'memberCount' => $team->members->count(),
            ],
            'members' => $members,
            'invitations' => $invitations,
            'receivedInvitations' => $receivedInvitations,
        ]);
    }

    /**
     * Display billing overview.
     */
    public function billing(): Response
    {
        return Inertia::render('Settings/Billing/Index');
    }

    /**
     * Display billing plans.
     */
    public function billingPlans(): Response
    {
        return Inertia::render('Settings/Billing/Plans');
    }

    /**
     * Display payment methods.
     */
    public function paymentMethods(): Response
    {
        return Inertia::render('Settings/Billing/PaymentMethods');
    }

    /**
     * Display invoices.
     */
    public function invoices(): Response
    {
        return Inertia::render('Settings/Billing/Invoices');
    }

    /**
     * Display usage.
     */
    public function usage(): Response
    {
        return Inertia::render('Settings/Billing/Usage');
    }

    /**
     * Display API tokens.
     */
    public function tokens(): Response
    {
        return Inertia::render('Settings/Tokens');
    }

    /**
     * Display integrations.
     */
    public function integrations(): Response
    {
        return Inertia::render('Settings/Index');
    }

    /**
     * Display security settings.
     */
    public function security(): Response
    {
        return Inertia::render('Settings/Security');
    }

    /**
     * Display workspace settings.
     */
    public function workspace(): Response
    {
        return Inertia::render('Settings/Workspace');
    }

    /**
     * Display notification settings.
     */
    public function notifications(): Response
    {
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
    }

    /**
     * Display Discord notification settings.
     */
    public function discord(): Response
    {
        $team = auth()->user()->currentTeam();

        return Inertia::render('Settings/Notifications/Discord', [
            'settings' => $team->discordNotificationSettings,
        ]);
    }

    /**
     * Display Slack notification settings.
     */
    public function slack(): Response
    {
        $team = auth()->user()->currentTeam();

        return Inertia::render('Settings/Notifications/Slack', [
            'settings' => $team->slackNotificationSettings,
        ]);
    }

    /**
     * Display Telegram notification settings.
     */
    public function telegram(): Response
    {
        $team = auth()->user()->currentTeam();

        return Inertia::render('Settings/Notifications/Telegram', [
            'settings' => $team->telegramNotificationSettings,
        ]);
    }

    /**
     * Display email notification settings.
     */
    public function email(): Response
    {
        $team = auth()->user()->currentTeam();

        return Inertia::render('Settings/Notifications/Email', [
            'settings' => $team->emailNotificationSettings,
            'canUseInstanceSettings' => config('saturn.is_self_hosted'),
        ]);
    }

    /**
     * Display webhook notification settings.
     */
    public function webhook(): Response
    {
        $team = auth()->user()->currentTeam();

        return Inertia::render('Settings/Notifications/Webhook', [
            'settings' => $team->webhookNotificationSettings,
        ]);
    }

    /**
     * Display Pushover notification settings.
     */
    public function pushover(): Response
    {
        $team = auth()->user()->currentTeam();

        return Inertia::render('Settings/Notifications/Pushover', [
            'settings' => $team->pushoverNotificationSettings,
        ]);
    }

    /**
     * Update account profile.
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
        ]);
        $user->update($request->only(['name', 'email']));

        return redirect()->back()->with('success', 'Profile updated successfully');
    }

    /**
     * Update account password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = auth()->user();
        if (! Hash::check($request->current_password, $user->password)) {
            return redirect()->back()->withErrors(['current_password' => 'Current password is incorrect']);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->back()->with('success', 'Password changed successfully');
    }

    /**
     * Toggle 2FA.
     */
    public function update2FA(Request $request): RedirectResponse
    {
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
    }

    /**
     * Delete account.
     */
    public function deleteAccount(Request $request): RedirectResponse
    {
        $user = auth()->user();

        // Validate password confirmation
        $request->validate([
            'password' => 'required|string',
        ]);

        if (! Hash::check($request->password, $user->password)) {
            return redirect()->back()->withErrors(['password' => 'The provided password is incorrect']);
        }

        // Log out the user
        Auth::logout();

        // Delete the user (this will trigger all cleanup logic in the User model's deleting event)
        $user->delete();

        return redirect('/')->with('success', 'Account deleted successfully');
    }

    /**
     * Revoke a specific session.
     */
    public function revokeSession(string $id): RedirectResponse
    {
        $user = auth()->user();
        $currentSessionId = session()->getId();

        // Prevent deleting current session
        if ($id === $currentSessionId) {
            return redirect()->back()->withErrors(['session' => 'Cannot revoke your current session']);
        }

        // Delete the specified session
        DB::table('sessions')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->delete();

        return redirect()->back()->with('success', 'Session revoked successfully');
    }

    /**
     * Revoke all other sessions.
     */
    public function revokeAllSessions(): RedirectResponse
    {
        $user = auth()->user();
        $currentSessionId = session()->getId();

        // Delete all sessions except the current one
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();

        return redirect()->back()->with('success', 'All other sessions revoked successfully');
    }

    /**
     * Add IP to allowlist.
     */
    public function storeIpAllowlist(Request $request): RedirectResponse
    {
        $request->validate([
            'ip_address' => 'required|ip',
            'description' => 'nullable|string|max:255',
        ]);

        $settings = InstanceSettings::get();
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
    }

    /**
     * Remove IP from allowlist.
     */
    public function destroyIpAllowlist(string $id): RedirectResponse
    {
        $settings = InstanceSettings::get();
        $allowedIps = $settings->allowed_ips ? json_decode($settings->allowed_ips, true) : [];

        // Remove IP by index (id is the array index)
        $index = (int) $id;
        if (isset($allowedIps[$index])) {
            array_splice($allowedIps, $index, 1);
            $settings->update(['allowed_ips' => json_encode(array_values($allowedIps))]);

            return redirect()->back()->with('success', 'IP address removed from allowlist');
        }

        return redirect()->back()->withErrors(['ip_allowlist' => 'IP address not found']);
    }

    /**
     * Update team member role.
     */
    public function updateMemberRole(string $id, Request $request): RedirectResponse
    {
        $request->validate([
            'role' => 'required|string|in:owner,admin,member',
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
    }

    /**
     * Remove team member.
     */
    public function removeMember(string $id): RedirectResponse
    {
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
    }

    /**
     * Invite team member.
     */
    public function inviteMember(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'role' => 'required|string|in:owner,admin,member',
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
        $existingInvitation = TeamInvitation::where('team_id', $team->id)
            ->where('email', $email)
            ->first();
        if ($existingInvitation) {
            return redirect()->back()->with('error', 'An invitation has already been sent to this email');
        }

        // Create the invitation
        $uuid = (string) Str::uuid();
        $link = url("/invitations/{$uuid}");

        $invitation = TeamInvitation::create([
            'team_id' => $team->id,
            'uuid' => $uuid,
            'email' => $email,
            'role' => $role,
            'link' => $link,
            'via' => 'link',
            'invited_by' => auth()->id(),
        ]);

        // Try to send email notification if email settings are configured
        try {
            $user = \App\Models\User::where('email', $email)->first();
            if ($user) {
                $user->notify(new InvitationLink($user));
            }
        } catch (\Exception $e) {
            // Email sending failed, but invitation still created
            Log::warning('Failed to send invitation email: '.$e->getMessage());
        }

        return redirect()->back()->with('success', 'Invitation sent successfully');
    }

    /**
     * Create API token.
     */
    public function storeToken(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'expires_at' => 'nullable|date',
        ]);

        $user = auth()->user();
        $expiresAt = $request->expires_at ? new \DateTime($request->expires_at) : null;

        // Create the token using Sanctum
        $tokenResult = $user->createToken($request->name, ['*'], $expiresAt);
        $plainTextToken = $tokenResult->plainTextToken;

        // Return with the token (only shown once)
        return redirect()->back()->with([
            'success' => 'API token created successfully',
            'token' => $plainTextToken,
        ]);
    }

    /**
     * Revoke API token.
     */
    public function destroyToken(string $id): RedirectResponse
    {
        $user = auth()->user();

        // Find and delete the token
        $token = $user->tokens()->where('id', $id)->first();
        if (! $token) {
            return redirect()->back()->withErrors(['token' => 'Token not found']);
        }

        $token->delete();

        return redirect()->back()->with('success', 'API token revoked successfully');
    }

    /**
     * Update workspace.
     */
    public function updateWorkspace(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
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
        ]);

        return redirect()->back()->with('success', 'Workspace updated successfully');
    }

    /**
     * Delete workspace.
     */
    public function deleteWorkspace(): RedirectResponse
    {
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
    }

    /**
     * Update Discord notification settings.
     */
    public function updateDiscord(Request $request): RedirectResponse
    {
        $team = auth()->user()->currentTeam();
        $settings = $team->discordNotificationSettings;

        $settings->update($request->all());

        return redirect()->back()->with('success', 'Discord notification settings saved successfully');
    }

    /**
     * Test Discord notification.
     */
    public function testDiscord(): RedirectResponse
    {
        $team = auth()->user()->currentTeam();
        $team->notify(new Test(channel: 'discord'));

        return redirect()->back()->with('success', 'Test notification sent to Discord');
    }

    /**
     * Update Slack notification settings.
     */
    public function updateSlack(Request $request): RedirectResponse
    {
        $team = auth()->user()->currentTeam();
        $settings = $team->slackNotificationSettings;

        $settings->update($request->all());

        return redirect()->back()->with('success', 'Slack notification settings saved successfully');
    }

    /**
     * Test Slack notification.
     */
    public function testSlack(): RedirectResponse
    {
        $team = auth()->user()->currentTeam();
        $team->notify(new Test(channel: 'slack'));

        return redirect()->back()->with('success', 'Test notification sent to Slack');
    }

    /**
     * Update Telegram notification settings.
     */
    public function updateTelegram(Request $request): RedirectResponse
    {
        $team = auth()->user()->currentTeam();
        $settings = $team->telegramNotificationSettings;

        $settings->update($request->all());

        return redirect()->back()->with('success', 'Telegram notification settings saved successfully');
    }

    /**
     * Test Telegram notification.
     */
    public function testTelegram(): RedirectResponse
    {
        $team = auth()->user()->currentTeam();
        $team->notify(new Test(channel: 'telegram'));

        return redirect()->back()->with('success', 'Test notification sent to Telegram');
    }

    /**
     * Update email notification settings.
     */
    public function updateEmail(Request $request): RedirectResponse
    {
        $team = auth()->user()->currentTeam();
        $settings = $team->emailNotificationSettings;

        $settings->update($request->all());

        return redirect()->back()->with('success', 'Email notification settings saved successfully');
    }

    /**
     * Test email notification.
     */
    public function testEmail(): RedirectResponse
    {
        $team = auth()->user()->currentTeam();
        $team->notify(new Test(channel: 'email'));

        return redirect()->back()->with('success', 'Test notification sent to email');
    }

    /**
     * Update webhook notification settings.
     */
    public function updateWebhook(Request $request): RedirectResponse
    {
        $team = auth()->user()->currentTeam();
        $settings = $team->webhookNotificationSettings;

        $settings->update($request->all());

        return redirect()->back()->with('success', 'Webhook notification settings saved successfully');
    }

    /**
     * Test webhook notification.
     */
    public function testWebhook(): RedirectResponse
    {
        $team = auth()->user()->currentTeam();
        $team->notify(new Test(channel: 'webhook'));

        return redirect()->back()->with('success', 'Test notification sent to webhook');
    }

    /**
     * Update Pushover notification settings.
     */
    public function updatePushover(Request $request): RedirectResponse
    {
        $team = auth()->user()->currentTeam();
        $settings = $team->pushoverNotificationSettings;

        $settings->update($request->all());

        return redirect()->back()->with('success', 'Pushover notification settings saved successfully');
    }

    /**
     * Test Pushover notification.
     */
    public function testPushover(): RedirectResponse
    {
        $team = auth()->user()->currentTeam();
        $team->notify(new Test(channel: 'pushover'));

        return redirect()->back()->with('success', 'Test notification sent to Pushover');
    }
}
