<?php

namespace App\Http\Controllers\Web;

use App\Enums\ProxyTypes;
use App\Http\Controllers\Controller;
use App\Models\CloudProviderToken;
use App\Models\PrivateKey;
use App\Models\Server;
use App\Services\Authorization\ResourceAuthorizationService;
use App\Services\HetznerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Visus\Cuid2\Cuid2;

class WebHetznerController extends Controller
{
    public function __construct(
        private readonly ResourceAuthorizationService $authorizationService
    ) {}

    /**
     * Return available locations and server types for a given token.
     * Used by the Hetzner wizard in the frontend.
     */
    public function options(Request $request)
    {
        if (! $this->authorizationService->canManageCloudProviders(auth()->user())) {
            abort(403, 'Cloud providers permission required');
        }

        $request->validate([
            'token_uuid' => 'required|string',
        ]);

        $token = CloudProviderToken::ownedByCurrentTeam()
            ->where('uuid', $request->token_uuid)
            ->where('provider', 'hetzner')
            ->firstOrFail();

        try {
            $service = new HetznerService($token->token);

            $locations = collect($service->getLocations())->map(fn ($l) => [
                'id' => $l['id'],
                'name' => $l['name'],
                'description' => $l['description'] ?? '',
                'country' => $l['country'] ?? '',
                'city' => $l['city'] ?? '',
            ])->values();

            $serverTypes = collect($service->getServerTypes())->map(fn ($s) => [
                'id' => $s['id'],
                'name' => $s['name'],
                'description' => $s['description'] ?? '',
                'cores' => $s['cores'] ?? 0,
                'memory' => $s['memory'] ?? 0,
                'disk' => $s['disk'] ?? 0,
            ])->values();

            return response()->json([
                'locations' => $locations,
                'server_types' => $serverTypes,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to fetch Hetzner options: '.$e->getMessage()], 500);
        }
    }

    /**
     * Provision a new server through Hetzner Cloud.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', Server::class);

        if (! $this->authorizationService->canManageCloudProviders(auth()->user())) {
            abort(403, 'Cloud providers permission required');
        }

        $validated = $request->validate([
            'cloud_provider_token_uuid' => 'required|string',
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:50',
            'server_type' => 'required|string|max:50',
            'private_key' => 'required_without:private_key_id|nullable|string',
            'private_key_id' => 'required_without:private_key|nullable|exists:private_keys,id',
        ]);

        $team = currentTeam();

        $token = CloudProviderToken::ownedByCurrentTeam()
            ->where('uuid', $validated['cloud_provider_token_uuid'])
            ->where('provider', 'hetzner')
            ->firstOrFail();

        // Resolve SSH private key
        if (! empty($validated['private_key'])) {
            $privateKey = PrivateKey::create([
                'name' => $validated['name'].' SSH Key',
                'private_key' => $validated['private_key'],
                'team_id' => $team->id,
            ]);
        } else {
            $privateKey = PrivateKey::ownedByCurrentTeam()->findOrFail($validated['private_key_id']);
        }

        try {
            $service = new HetznerService($token->token);

            // Create server in Hetzner Cloud
            $hetznerServer = $service->createServer([
                'name' => $validated['name'],
                'server_type' => $validated['server_type'],
                'location' => $validated['location'],
                'image' => 'ubuntu-24.04',
                'start_after_create' => true,
            ]);

            // Wait for public IP (it may take a moment after server creation)
            $ip = data_get($hetznerServer, 'public_net.ipv4.ip', '');

            // Create local Server record
            $server = Server::create([
                'uuid' => (string) new Cuid2,
                'name' => $validated['name'],
                'ip' => $ip,
                'port' => 22,
                'user' => 'root',
                'team_id' => $team->id,
                'private_key_id' => $privateKey->id,
                'cloud_provider_token_id' => $token->id,
                'hetzner_server_id' => $hetznerServer['id'] ?? null,
                'proxy_type' => ProxyTypes::TRAEFIK->value,
            ]);

            return redirect()->route('servers.show', $server->uuid)
                ->with('success', 'Server is being provisioned through Hetzner Cloud.');
        } catch (\Throwable $e) {
            return back()->withErrors(['hetzner' => 'Hetzner provisioning failed: '.$e->getMessage()]);
        }
    }
}
