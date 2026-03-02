<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Controllers\Controller;
use App\Models\CloudProviderToken;
use App\Services\Authorization\ResourceAuthorizationService;
use Inertia\Inertia;

class SettingsCloudProvidersController extends Controller
{
    public function __construct(
        private readonly ResourceAuthorizationService $authorizationService
    ) {}

    public function index()
    {
        $user = auth()->user();
        $team = currentTeam();

        if (! $this->authorizationService->canManageCloudProviders($user, $team?->id)) {
            abort(403, 'Cloud providers permission required');
        }

        $tokens = CloudProviderToken::ownedByCurrentTeam()
            ->withCount('servers')
            ->get()
            ->map(fn ($token) => [
                'uuid' => $token->uuid,
                'name' => $token->name,
                'provider' => $token->provider,
                'servers_count' => $token->servers_count,
                'created_at' => $token->created_at,
            ]);

        return Inertia::render('Settings/CloudProviders/Index', [
            'tokens' => $tokens,
        ]);
    }
}
