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

Broadcast::channel('team.{teamId}', function (User $user, int $teamId) {
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

Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});

Broadcast::channel('application.{applicationId}.logs', function (User $user, int $applicationId) {
    $application = \App\Models\Application::find($applicationId);

    return $application && $user->teams->pluck('id')->contains($application->team_id);
});

Broadcast::channel('deployment.{deploymentId}', function (User $user, int $deploymentId) {
    $deployment = \App\Models\ApplicationDeploymentQueue::find($deploymentId);

    return $deployment && $user->teams->pluck('id')->contains($deployment->application?->team_id);
});

Broadcast::channel('server.{serverId}', function (User $user, int $serverId) {
    $server = \App\Models\Server::find($serverId);

    return $server && $user->teams->pluck('id')->contains($server->team_id);
});

Broadcast::channel('database.{databaseId}', function (User $user, int $databaseId) {
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

Broadcast::channel('database.{databaseId}.logs', function (User $user, int $databaseId) {
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

Broadcast::channel('service.{serviceId}.logs', function (User $user, int $serviceId) {
    $service = \App\Models\Service::find($serviceId);

    return $service && $user->teams->pluck('id')->contains($service->team_id);
});
