<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CloudProviderToken;
use App\Services\Authorization\ResourceAuthorizationService;
use App\Services\HetznerService;
use Illuminate\Http\Request;
use Visus\Cuid2\Cuid2;

class WebCloudTokensController extends Controller
{
    public function __construct(
        protected ResourceAuthorizationService $authService
    ) {}

    public function store(Request $request)
    {
        if (! $this->authService->canManageCloudProviders(auth()->user())) {
            abort(403, 'Insufficient permissions to manage cloud provider tokens.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'provider' => 'required|in:hetzner,digitalocean',
            'token' => 'required|string|max:1000',
        ]);

        $validated['uuid'] = (string) new Cuid2;
        $validated['team_id'] = currentTeam()->id;

        CloudProviderToken::create($validated);

        return back()->with('success', 'Cloud provider token added.');
    }

    public function destroy(string $uuid)
    {
        if (! $this->authService->canManageCloudProviders(auth()->user())) {
            abort(403, 'Insufficient permissions to manage cloud provider tokens.');
        }

        $token = CloudProviderToken::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        if ($token->hasServers()) {
            return back()->withErrors(['token' => 'Cannot delete token: it has associated servers.']);
        }

        $token->delete();

        return back()->with('success', 'Cloud provider token deleted.');
    }

    public function checkToken(string $uuid)
    {
        if (! $this->authService->canManageCloudProviders(auth()->user())) {
            abort(403, 'Insufficient permissions to manage cloud provider tokens.');
        }

        $token = CloudProviderToken::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        try {
            if ($token->provider === 'hetzner') {
                $service = new HetznerService($token->token);
                $service->getLocations();
            }

            return response()->json(['valid' => true, 'message' => 'Token is valid.']);
        } catch (\Throwable $e) {
            return response()->json(['valid' => false, 'message' => 'Token validation failed: '.$e->getMessage()]);
        }
    }
}
