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

    return $user->teams->pluck('id')->contains($teamId);
});

Broadcast::channel('user.{userId}', function (User $user, string|int $userId) {
    return $user->id === (int) $userId;
});

Broadcast::channel('application.{applicationId}.logs', function (User $user, string $applicationId) {
    // applicationId can be UUID or numeric ID
    $application = \App\Models\Application::where('uuid', $applicationId)->first()
        ?? \App\Models\Application::find($applicationId);

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
    $databaseModels = [
        \App\Models\StandalonePostgresql::class,
        \App\Models\StandaloneMysql::class,
        \App\Models\StandaloneMongodb::class,
        \App\Models\StandaloneRedis::class,
        \App\Models\StandaloneKeydb::class,
        \App\Models\StandaloneDragonfly::class,
        \App\Models\StandaloneClickhouse::class,
        \App\Models\StandaloneMariadb::class,
    ];

    foreach ($databaseModels as $model) {
        $database = $model::find($databaseId);
        if ($database) {
            return $user->teams->pluck('id')->contains($database->team()?->id);
        }
    }

    return false;
});

Broadcast::channel('database.{databaseId}.logs', function (User $user, string $databaseId) {
    $databaseModels = [
        \App\Models\StandalonePostgresql::class,
        \App\Models\StandaloneMysql::class,
        \App\Models\StandaloneMongodb::class,
        \App\Models\StandaloneRedis::class,
        \App\Models\StandaloneKeydb::class,
        \App\Models\StandaloneDragonfly::class,
        \App\Models\StandaloneClickhouse::class,
        \App\Models\StandaloneMariadb::class,
    ];

    foreach ($databaseModels as $model) {
        $database = $model::where('uuid', $databaseId)->first()
            ?? $model::find($databaseId);
        if ($database) {
            return $user->teams->pluck('id')->contains($database->team()?->id);
        }
    }

    return false;
});

Broadcast::channel('deployment.{deploymentId}.logs', function (User $user, string $deploymentId) {
    $deployment = \App\Models\ApplicationDeploymentQueue::where('deployment_uuid', $deploymentId)->first();

    return $deployment && $user->teams->pluck('id')->contains($deployment->application?->team_id);
});

Broadcast::channel('service.{serviceId}.logs', function (User $user, string $serviceId) {
    // serviceId can be UUID or numeric ID
    $service = \App\Models\Service::where('uuid', $serviceId)->first()
        ?? \App\Models\Service::find($serviceId);

    return $service && $user->teams->pluck('id')->contains($service->team_id);
});

Broadcast::channel('ai-chat.{sessionUuid}', function (User $user, string $sessionUuid) {
    $session = \App\Models\AiChatSession::where('uuid', $sessionUuid)->first();

    return $session && $session->user_id === $user->id;
});

Broadcast::channel('migration.{migrationUuid}', function (User $user, string $migrationUuid) {
    $migration = \App\Models\EnvironmentMigration::where('uuid', $migrationUuid)->first();

    return $migration && $user->teams->pluck('id')->contains($migration->team_id);
});
