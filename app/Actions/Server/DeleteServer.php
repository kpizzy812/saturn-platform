<?php

namespace App\Actions\Server;

use App\Models\CloudProviderToken;
use App\Models\Server;
use App\Models\Team;
use App\Notifications\Server\HetznerDeletionFailed;
use App\Services\HetznerService;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteServer
{
    use AsAction;

    public function handle(int $serverId, bool $deleteFromHetzner = false, ?int $hetznerServerId = null, ?int $cloudProviderTokenId = null, ?int $teamId = null)
    {
        $server = Server::withTrashed()->find($serverId);

        // Delete from Hetzner even if server is already gone from Saturn Platform
        if ($deleteFromHetzner && ($hetznerServerId || ($server && $server->hetzner_server_id))) {
            $this->deleteFromHetznerById(
                $hetznerServerId ?? $server->hetzner_server_id,
                $cloudProviderTokenId ?? $server->cloud_provider_token_id,
                $teamId ?? $server->team_id
            );
        }

        Log::info($server ? 'Deleting server from Saturn Platform' : 'Server already deleted from Saturn Platform, skipping Saturn Platform deletion');

        // If server is already deleted from Saturn Platform, skip this part
        if (! $server) {
            return; // Server already force deleted from Saturn Platform
        }

        Log::info('Force deleting server from Saturn Platform', ['server_id' => $server->id]);

        try {
            $server->forceDelete();
        } catch (\Throwable $e) {
            Log::warning('Failed to force delete server from Saturn Platform', [
                'error' => $e->getMessage(),
                'server_id' => $server->id,
            ]);
            logger()->error('Failed to force delete server from Saturn Platform', [
                'error' => $e->getMessage(),
                'server_id' => $server->id,
            ]);
        }
    }

    private function deleteFromHetznerById(int $hetznerServerId, ?int $cloudProviderTokenId, int $teamId): void
    {
        try {
            // Use the provided token, or fallback to first available team token
            $token = null;

            if ($cloudProviderTokenId) {
                $token = CloudProviderToken::find($cloudProviderTokenId);
            }

            if (! $token) {
                $token = CloudProviderToken::where('team_id', $teamId)
                    ->where('provider', 'hetzner')
                    ->first();
            }

            if (! $token) {
                Log::warning('No Hetzner token found for team, skipping Hetzner deletion', [
                    'team_id' => $teamId,
                    'hetzner_server_id' => $hetznerServerId,
                ]);

                return;
            }

            $hetznerService = new HetznerService($token->token);
            $hetznerService->deleteServer($hetznerServerId);

            Log::info('Deleted server from Hetzner', [
                'hetzner_server_id' => $hetznerServerId,
                'team_id' => $teamId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to delete server from Hetzner', [
                'error' => $e->getMessage(),
                'hetzner_server_id' => $hetznerServerId,
                'team_id' => $teamId,
            ]);

            // Log the error but don't prevent the server from being deleted from Saturn Platform
            logger()->error('Failed to delete server from Hetzner', [
                'error' => $e->getMessage(),
                'hetzner_server_id' => $hetznerServerId,
                'team_id' => $teamId,
            ]);

            // Notify the team about the failure
            $team = Team::find($teamId);
            $team?->notify(new HetznerDeletionFailed($hetznerServerId, $teamId, $e->getMessage()));
        }
    }
}
