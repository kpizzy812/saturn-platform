<?php

namespace App\Http\Middleware;

use App\Models\InstanceSettings;
use App\Models\UserNotification;
use App\Services\Authorization\PermissionService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $team = $user?->currentTeam();

        // Get notifications data for the header dropdown
        $notificationsData = $this->getNotificationsData($team);

        // Get system notifications count for admins (Team 0)
        $systemNotificationsData = $this->getSystemNotificationsData($user);

        return [
            ...parent::share($request),
            'auth' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar ? '/storage/'.$user->avatar : null,
                'email_verified_at' => $user->email_verified_at,
                'is_superadmin' => $user->is_superadmin ?? false,
                'two_factor_enabled' => ! is_null($user->two_factor_secret),
                'role' => $user->role(),
                'permissions' => [
                    'isAdmin' => $user->isAdmin(),
                    'isOwner' => $user->isOwner(),
                    'isMember' => $user->isMember(),
                    'isDeveloper' => $user->isDeveloper(),
                    'isViewer' => $user->isViewer(),
                    'granular' => $this->getGranularPermissions($user),
                ],
            ] : null,
            'team' => $team ? [
                'id' => $team->id,
                'name' => $team->name,
                'personal_team' => $team->personal_team ?? false,
                'logo' => $team->logo ? '/storage/'.$team->logo : null,
            ] : null,
            'teams' => $user ? $user->teams->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'personal_team' => $t->personal_team ?? false,
                'role' => $t->pivot->role ?? 'member',
                'logo' => $t->logo ? '/storage/'.$t->logo : null,
            ])->values()->toArray() : [],
            'notifications' => $notificationsData,
            'systemNotifications' => $systemNotificationsData,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
            'appName' => config('app.name'),
            'aiChatEnabled' => $this->isAiChatEnabled(),
        ];
    }

    /**
     * Get notifications data for the header dropdown.
     * Excludes system notifications (type='info') - those are shown in admin panel only.
     *
     * @return array{unreadCount: int, recent: array}
     */
    private function getNotificationsData($team): array
    {
        if (! $team) {
            return [
                'unreadCount' => 0,
                'recent' => [],
            ];
        }

        // Exclude system notifications (type='info') from regular UI
        $unreadCount = UserNotification::where('team_id', $team->id)
            ->where('is_read', false)
            ->where('type', '!=', 'info')
            ->count();

        $recent = UserNotification::where('team_id', $team->id)
            ->where('type', '!=', 'info')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(fn ($n) => $n->toFrontendArray());

        return [
            'unreadCount' => $unreadCount,
            'recent' => $recent->toArray(),
        ];
    }

    /**
     * Get system notifications data for admin panel.
     * System notifications are stored for Team 0 (root team).
     *
     * @return array{unreadCount: int}|null
     */
    private function getSystemNotificationsData($user): ?array
    {
        // Only provide system notifications data to superadmins
        if (! $user || ! ($user->is_superadmin ?? false)) {
            return null;
        }

        $unreadCount = UserNotification::where('team_id', 0)
            ->where('is_read', false)
            ->count();

        return [
            'unreadCount' => $unreadCount,
        ];
    }

    /**
     * Get granular permissions for the current user via PermissionService.
     *
     * @return array<string, bool>
     */
    private function getGranularPermissions($user): array
    {
        try {
            return app(PermissionService::class)->getUserEffectivePermissions($user);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if AI Chat is enabled in instance settings.
     */
    private function isAiChatEnabled(): bool
    {
        try {
            $settings = InstanceSettings::get();

            return $settings->is_ai_chat_enabled ?? true;
        } catch (\Exception $e) {
            // Default to enabled if settings not available
            return true;
        }
    }
}
