<?php

namespace App\Http\Middleware;

use App\Models\UserNotification;
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

        return [
            ...parent::share($request),
            'auth' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
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
                ],
            ] : null,
            'team' => $team ? [
                'id' => $team->id,
                'name' => $team->name,
                'personal_team' => $team->personal_team ?? false,
            ] : null,
            'notifications' => $notificationsData,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
            'appName' => config('app.name'),
        ];
    }

    /**
     * Get notifications data for the header dropdown.
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

        $unreadCount = UserNotification::where('team_id', $team->id)
            ->where('is_read', false)
            ->count();

        $recent = UserNotification::where('team_id', $team->id)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(fn ($n) => $n->toFrontendArray());

        return [
            'unreadCount' => $unreadCount,
            'recent' => $recent,
        ];
    }
}
