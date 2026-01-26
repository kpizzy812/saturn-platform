<?php

/**
 * Resource helper functions.
 *
 * Contains functions for working with resources (applications, services, databases),
 * service templates, webhooks, and deployment operations.
 */

use App\Enums\ApplicationDeploymentStatus;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\Service;
use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDragonfly;
use App\Models\StandaloneKeydb;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Laravel\Horizon\Contracts\JobRepository;
use Spatie\Url\Url;

/**
 * Get service templates from the official source or local file.
 */
function get_service_templates(bool $force = false): Collection
{

    if ($force) {
        try {
            $response = Http::retry(3, 1000)->get(config('constants.services.official'));
            if ($response->failed()) {
                return collect([]);
            }
            $services = $response->json();

            return collect($services);
        } catch (\Throwable) {
            $services = File::get(base_path('templates/'.config('constants.services.file_name')));

            return collect(json_decode($services))->sortKeys();
        }
    } else {
        $services = File::get(base_path('templates/'.config('constants.services.file_name')));

        return collect(json_decode($services))->sortKeys();
    }
}

/**
 * Get a resource by UUID within a specific team.
 */
function getResourceByUuid(string $uuid, ?int $teamId = null)
{
    if (is_null($teamId)) {
        return null;
    }
    $resource = queryResourcesByUuid($uuid);
    if (! is_null($resource) && $resource->environment->project->team_id === $teamId) {
        return $resource;
    }

    return null;
}

/**
 * Query a database by UUID within a specific team.
 */
function queryDatabaseByUuidWithinTeam(string $uuid, string $teamId)
{
    $postgresql = StandalonePostgresql::whereUuid($uuid)->first();
    if ($postgresql && $postgresql->team()->id == $teamId) {
        return $postgresql->unsetRelation('environment');
    }
    $redis = StandaloneRedis::whereUuid($uuid)->first();
    if ($redis && $redis->team()->id == $teamId) {
        return $redis->unsetRelation('environment');
    }
    $mongodb = StandaloneMongodb::whereUuid($uuid)->first();
    if ($mongodb && $mongodb->team()->id == $teamId) {
        return $mongodb->unsetRelation('environment');
    }
    $mysql = StandaloneMysql::whereUuid($uuid)->first();
    if ($mysql && $mysql->team()->id == $teamId) {
        return $mysql->unsetRelation('environment');
    }
    $mariadb = StandaloneMariadb::whereUuid($uuid)->first();
    if ($mariadb && $mariadb->team()->id == $teamId) {
        return $mariadb->unsetRelation('environment');
    }
    $keydb = StandaloneKeydb::whereUuid($uuid)->first();
    if ($keydb && $keydb->team()->id == $teamId) {
        return $keydb->unsetRelation('environment');
    }
    $dragonfly = StandaloneDragonfly::whereUuid($uuid)->first();
    if ($dragonfly && $dragonfly->team()->id == $teamId) {
        return $dragonfly->unsetRelation('environment');
    }
    $clickhouse = StandaloneClickhouse::whereUuid($uuid)->first();
    if ($clickhouse && $clickhouse->team()->id == $teamId) {
        return $clickhouse->unsetRelation('environment');
    }

    return null;
}

/**
 * Query resources (applications, services, databases) by UUID.
 */
function queryResourcesByUuid(string $uuid)
{
    $resource = null;
    $application = Application::whereUuid($uuid)->first();
    if ($application) {
        return $application;
    }
    $service = Service::whereUuid($uuid)->first();
    if ($service) {
        return $service;
    }
    $postgresql = StandalonePostgresql::whereUuid($uuid)->first();
    if ($postgresql) {
        return $postgresql;
    }
    $redis = StandaloneRedis::whereUuid($uuid)->first();
    if ($redis) {
        return $redis;
    }
    $mongodb = StandaloneMongodb::whereUuid($uuid)->first();
    if ($mongodb) {
        return $mongodb;
    }
    $mysql = StandaloneMysql::whereUuid($uuid)->first();
    if ($mysql) {
        return $mysql;
    }
    $mariadb = StandaloneMariadb::whereUuid($uuid)->first();
    if ($mariadb) {
        return $mariadb;
    }
    $keydb = StandaloneKeydb::whereUuid($uuid)->first();
    if ($keydb) {
        return $keydb;
    }
    $dragonfly = StandaloneDragonfly::whereUuid($uuid)->first();
    if ($dragonfly) {
        return $dragonfly;
    }
    $clickhouse = StandaloneClickhouse::whereUuid($uuid)->first();
    if ($clickhouse) {
        return $clickhouse;
    }

    return $resource;
}

/**
 * Generate a tag-based deploy webhook URL.
 */
function generateTagDeployWebhook($tag_name)
{
    $baseUrl = base_url();
    $api = Url::fromString($baseUrl).'/api/v1';
    $endpoint = "/deploy?tag=$tag_name";

    return $api.$endpoint;
}

/**
 * Generate a deploy webhook URL for a resource.
 */
function generateDeployWebhook($resource)
{
    $baseUrl = base_url();
    $api = Url::fromString($baseUrl).'/api/v1';
    $endpoint = '/deploy';
    $uuid = data_get($resource, 'uuid');

    return $api.$endpoint."?uuid=$uuid&force=false";
}

/**
 * Generate a manual Git webhook URL for a resource.
 */
function generateGitManualWebhook($resource, $type)
{
    if ($resource->source_id !== 0 && ! is_null($resource->source_id)) {
        return null;
    }
    if ($resource->getMorphClass() === \App\Models\Application::class) {
        $baseUrl = base_url();

        return Url::fromString($baseUrl)."/webhooks/source/$type/events/manual";
    }

    return null;
}

/**
 * Check if any deployment is in progress.
 */
function isAnyDeploymentInprogress()
{
    $runningJobs = ApplicationDeploymentQueue::where('horizon_job_worker', gethostname())->where('status', ApplicationDeploymentStatus::IN_PROGRESS->value)->get();

    if ($runningJobs->isEmpty()) {
        echo "No deployments in progress.\n";
        exit(0);
    }

    $horizonJobIds = [];
    $deploymentDetails = [];

    foreach ($runningJobs as $runningJob) {
        $horizonJobStatus = getJobStatus($runningJob->horizon_job_id);
        if ($horizonJobStatus === 'unknown' || $horizonJobStatus === 'reserved') {
            $horizonJobIds[] = $runningJob->horizon_job_id;

            // Get application and team information
            $application = Application::find($runningJob->application_id);
            $teamMembers = [];
            $deploymentUrl = '';

            if ($application) {
                // Get team members through the application's project
                $team = $application->team();
                if ($team) {
                    $teamMembers = $team->members()->pluck('email')->toArray();
                }

                // Construct the full deployment URL
                if ($runningJob->deployment_url) {
                    $baseUrl = base_url();
                    $deploymentUrl = $baseUrl.$runningJob->deployment_url;
                }
            }

            $deploymentDetails[] = [
                'id' => $runningJob->id,
                'application_name' => $runningJob->application_name ?? 'Unknown',
                'server_name' => $runningJob->server_name ?? 'Unknown',
                'deployment_url' => $deploymentUrl,
                'team_members' => $teamMembers,
                'created_at' => $runningJob->created_at->format('Y-m-d H:i:s'),
                'horizon_job_id' => $runningJob->horizon_job_id,
            ];
        }
    }

    if (count($horizonJobIds) === 0) {
        echo "No active deployments in progress (all jobs completed or failed).\n";
        exit(0);
    }

    // Display enhanced deployment information
    echo "\n=== Running Deployments ===\n";
    echo 'Total active deployments: '.count($horizonJobIds)."\n\n";

    foreach ($deploymentDetails as $index => $deployment) {
        echo 'Deployment #'.($index + 1).":\n";
        echo '  Application: '.$deployment['application_name']."\n";
        echo '  Server: '.$deployment['server_name']."\n";
        echo '  Started: '.$deployment['created_at']."\n";
        if ($deployment['deployment_url']) {
            echo '  URL: '.$deployment['deployment_url']."\n";
        }
        if (! empty($deployment['team_members'])) {
            echo '  Team members: '.implode(', ', $deployment['team_members'])."\n";
        } else {
            echo "  Team members: No team members found\n";
        }
        echo '  Horizon Job ID: '.$deployment['horizon_job_id']."\n";
        echo "\n";
    }

    exit(1);
}

/**
 * Get the status of a Horizon job.
 */
function getJobStatus(?string $jobId = null)
{
    if (is_null($jobId)) {
        return 'unknown';
    }
    $jobRepository = app(JobRepository::class);
    $job = $jobRepository->getJobs([$jobId])->first();
    if (is_null($job)) {
        return 'unknown';
    }

    return $job->status;
}
