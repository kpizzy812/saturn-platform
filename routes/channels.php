<?php

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('team.{teamId}', function (User $user, string|int $teamId) {
    $teamId = (int) $teamId;
    \Illuminate\Support\Facades\Log::debug('WebSocket auth attempt', [
        'user_id' => $user->id,
        'team_id' => $teamId,
        'user_teams' => $user->teams->pluck('id')->toArray(),
    ]);

    if ($user->teams->pluck('id')->contains($teamId)) {
        \Illuminate\Support\Facades\Log::debug('WebSocket auth SUCCESS', ['team_id' => $teamId]);

        return true;
    }

    \Illuminate\Support\Facades\Log::warning('WebSocket auth FAILED', ['team_id' => $teamId]);

    return false;
});

Broadcast::channel('user.{userId}', function (User $user, string|int $userId) {
    return $user->id === (int) $userId;
});

Broadcast::channel('application.{applicationId}.logs', function (User $user, string|int $applicationId) {
    $applicationId = (int) $applicationId;
    $application = \App\Models\Application::find($applicationId);

    return $application && $user->teams->pluck('id')->contains($application->team_id);
});

Broadcast::channel('deployment.{deploymentId}', function (User $user, string|int $deploymentId) {
    $deploymentId = (int) $deploymentId;
    $deployment = \App\Models\ApplicationDeploymentQueue::find($deploymentId);

    return $deployment && $user->teams->pluck('id')->contains($deployment->application?->team_id);
});

Broadcast::channel('server.{serverId}', function (User $user, string|int $serverId) {
    $serverId = (int) $serverId;
    $server = \App\Models\Server::find($serverId);

    return $server && $user->teams->pluck('id')->contains($server->team_id);
});

Broadcast::channel('database.{databaseId}', function (User $user, string|int $databaseId) {
    $databaseId = (int) $databaseId;
    $database = \App\Models\StandalonePostgresql::find($databaseId)
        ?? \App\Models\StandaloneMysql::find($databaseId)
        ?? \App\Models\StandaloneMongodb::find($databaseId)
        ?? \App\Models\StandaloneRedis::find($databaseId)
        ?? \App\Models\StandaloneKeydb::find($databaseId)
        ?? \App\Models\StandaloneDragonfly::find($databaseId)
        ?? \App\Models\StandaloneClickhouse::find($databaseId)
        ?? \App\Models\StandaloneMariadb::find($databaseId);

    return $database && $user->teams->pluck('id')->contains($database->team()?->id);
});

Broadcast::channel('database.{databaseId}.logs', function (User $user, string|int $databaseId) {
    $databaseId = (int) $databaseId;
    $database = \App\Models\StandalonePostgresql::find($databaseId)
        ?? \App\Models\StandaloneMysql::find($databaseId)
        ?? \App\Models\StandaloneMongodb::find($databaseId)
        ?? \App\Models\StandaloneRedis::find($databaseId)
        ?? \App\Models\StandaloneKeydb::find($databaseId)
        ?? \App\Models\StandaloneDragonfly::find($databaseId)
        ?? \App\Models\StandaloneClickhouse::find($databaseId)
        ?? \App\Models\StandaloneMariadb::find($databaseId);

    return $database && $user->teams->pluck('id')->contains($database->team()?->id);
});

Broadcast::channel('deployment.{deploymentId}.logs', function (User $user, string $deploymentId) {
    $deployment = \App\Models\ApplicationDeploymentQueue::where('deployment_uuid', $deploymentId)->first();

    return $deployment && $user->teams->pluck('id')->contains($deployment->application?->team_id);
});

Broadcast::channel('service.{serviceId}.logs', function (User $user, string|int $serviceId) {
    $serviceId = (int) $serviceId;
    $service = \App\Models\Service::find($serviceId);

    \Illuminate\Support\Facades\Log::debug('Service logs channel auth', [
        'user_id' => $user->id,
        'service_id' => $serviceId,
        'service_found' => $service !== null,
        'service_team_id' => $service?->team_id,
        'user_teams' => $user->teams->pluck('id')->toArray(),
    ]);

    return $service && $user->teams->pluck('id')->contains($service->team_id);
});
