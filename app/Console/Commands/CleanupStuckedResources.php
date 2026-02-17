<?php

namespace App\Console\Commands;

use App\Enums\ApplicationDeploymentStatus;
use App\Jobs\CleanupHelperContainersJob;
use App\Jobs\DeleteResourceJob;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\ApplicationPreview;
use App\Models\ScheduledDatabaseBackup;
use App\Models\ScheduledTask;
use App\Models\Server;
use App\Models\Service;
use App\Models\ServiceApplication;
use App\Models\ServiceDatabase;
use App\Models\SslCertificate;
use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDragonfly;
use App\Models\StandaloneKeydb;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use App\Models\Team;
use Illuminate\Console\Command;

class CleanupStuckedResources extends Command
{
    protected $signature = 'cleanup:stucked-resources';

    protected $description = 'Cleanup Stucked Resources';

    public function handle()
    {
        $this->cleanup_stucked_resources();
    }

    private function cleanup_stucked_resources()
    {
        // Mark deployments stuck in in_progress/queued for >1 hour as timed-out
        try {
            $stuckDeployments = ApplicationDeploymentQueue::whereIn('status', [
                ApplicationDeploymentStatus::IN_PROGRESS->value,
                ApplicationDeploymentStatus::QUEUED->value,
            ])
                ->where('updated_at', '<', now()->subHour())
                ->get();

            foreach ($stuckDeployments as $deployment) {
                echo "Marking stuck deployment as timed-out: {$deployment->deployment_uuid} (status: {$deployment->status}, last updated: {$deployment->updated_at})\n";
                $deployment->update([
                    'status' => ApplicationDeploymentStatus::TIMED_OUT->value,
                ]);
                $deployment->addLogEntry('Deployment marked as timed-out by cleanup job (stuck for >1 hour).', 'stderr');
            }
        } catch (\Throwable $e) {
            echo "Error in cleaning stuck deployments: {$e->getMessage()}\n";
        }

        try {
            // Delete teams with no members and no servers (scoped query instead of ::all())
            Team::whereDoesntHave('members')->whereDoesntHave('servers')->each(function ($team) {
                $team->delete();
            });

            // Dispatch cleanup for functional servers (use cursor to avoid loading all)
            $serversQuery = Server::query();
            if (isCloud()) {
                $serversQuery->whereHas('team.subscription', function ($q) {
                    $q->where('stripe_invoice_paid', true);
                });
            }
            $serversQuery->cursor()->each(function ($server) {
                if ($server->isFunctional()) {
                    CleanupHelperContainersJob::dispatch($server);
                }
            });
        } catch (\Throwable $e) {
            echo "Error in cleaning stucked resources: {$e->getMessage()}\n";
        }
        try {
            Server::onlyTrashed()->each(function ($server) {
                echo "Force deleting stuck server: {$server->name}\n";
                $server->forceDelete();
            });
        } catch (\Throwable $e) {
            echo "Error in cleaning stuck servers: {$e->getMessage()}\n";
        }
        try {
            // Delete orphaned deployment queues (application no longer exists)
            ApplicationDeploymentQueue::whereDoesntHave('application')->each(function ($queue) {
                echo "Deleting stuck application deployment queue: {$queue->id}\n";
                $queue->delete();
            });
        } catch (\Throwable $e) {
            echo "Error in cleaning stuck application deployment queue: {$e->getMessage()}\n";
        }
        try {
            // Force delete soft-deleted applications
            Application::onlyTrashed()->each(function ($application) {
                echo "Deleting stuck application: {$application->name}\n";
                DeleteResourceJob::dispatch($application);
            });
        } catch (\Throwable $e) {
            echo "Error in cleaning stuck application: {$e->getMessage()}\n";
        }
        try {
            // Delete orphaned application previews (application no longer exists)
            ApplicationPreview::whereDoesntHave('application')->each(function ($preview) {
                echo "Deleting stuck application preview: {$preview->uuid}\n";
                DeleteResourceJob::dispatch($preview);
            });
        } catch (\Throwable $e) {
            echo "Error in cleaning stuck application: {$e->getMessage()}\n";
        }
        try {
            // Force delete soft-deleted application previews
            ApplicationPreview::onlyTrashed()->each(function ($preview) {
                echo "Deleting stuck application preview: {$preview->fqdn}\n";
                DeleteResourceJob::dispatch($preview);
            });
        } catch (\Throwable $e) {
            echo "Error in cleaning stuck application: {$e->getMessage()}\n";
        }

        // Force delete soft-deleted database resources
        $this->cleanupTrashedResources(StandalonePostgresql::class, 'postgresql');
        $this->cleanupTrashedResources(StandaloneRedis::class, 'redis');
        $this->cleanupTrashedResources(StandaloneKeydb::class, 'keydb');
        $this->cleanupTrashedResources(StandaloneDragonfly::class, 'dragonfly');
        $this->cleanupTrashedResources(StandaloneClickhouse::class, 'clickhouse');
        $this->cleanupTrashedResources(StandaloneMongodb::class, 'mongodb');
        $this->cleanupTrashedResources(StandaloneMysql::class, 'mysql');
        $this->cleanupTrashedResources(StandaloneMariadb::class, 'mariadb');
        $this->cleanupTrashedResources(Service::class, 'service');

        try {
            // Force delete soft-deleted service applications
            ServiceApplication::onlyTrashed()->each(function ($serviceApp) {
                echo "Deleting stuck serviceapp: {$serviceApp->name}\n";
                $serviceApp->forceDelete();
            });
        } catch (\Throwable $e) {
            echo "Error in cleaning stuck serviceapp: {$e->getMessage()}\n";
        }
        try {
            // Force delete soft-deleted service databases
            ServiceDatabase::onlyTrashed()->each(function ($serviceDb) {
                echo "Deleting stuck servicedb: {$serviceDb->name}\n";
                $serviceDb->forceDelete();
            });
        } catch (\Throwable $e) {
            echo "Error in cleaning stuck servicedb: {$e->getMessage()}\n";
        }
        try {
            // Delete orphaned scheduled tasks (no service and no application)
            ScheduledTask::whereDoesntHave('service')->whereDoesntHave('application')->each(function ($task) {
                echo "Deleting stuck scheduledtask: {$task->name}\n";
                $task->delete();
            });
        } catch (\Throwable $e) {
            echo "Error in cleaning stuck scheduledtasks: {$e->getMessage()}\n";
        }

        try {
            // Delete orphaned scheduled backups (server no longer exists)
            ScheduledDatabaseBackup::cursor()->each(function ($backup) {
                try {
                    $server = $backup->server();
                    if (! $server) {
                        echo "Deleting stuck scheduledbackup: {$backup->name}\n";
                        $backup->delete();
                    }
                } catch (\Throwable $e) {
                    echo "Error checking server for scheduledbackup {$backup->id}: {$e->getMessage()}\n";
                }
            });
        } catch (\Throwable $e) {
            echo "Error in cleaning stuck scheduledbackups: {$e->getMessage()}\n";
        }

        // Cleanup resources not attached to any environment, destination, or server
        $this->cleanupOrphanedResources(Application::class, 'Application');
        $this->cleanupOrphanedResources(StandalonePostgresql::class, 'Postgresql', excludeIdZero: true);
        $this->cleanupOrphanedResources(StandaloneRedis::class, 'Redis');
        $this->cleanupOrphanedResources(StandaloneMongodb::class, 'Mongodb');
        $this->cleanupOrphanedResources(StandaloneMysql::class, 'Mysql');
        $this->cleanupOrphanedResources(StandaloneMariadb::class, 'Mariadb');

        // Service orphan check (checks 'server' directly, not 'destination.server')
        try {
            Service::with(['environment', 'destination', 'server'])->cursor()->each(function ($service) {
                if (! data_get($service, 'environment')) {
                    echo "Service without environment: {$service->name}\n";
                    DeleteResourceJob::dispatch($service);
                } elseif (! data_get($service, 'destination')) {
                    echo "Service without destination: {$service->name}\n";
                    DeleteResourceJob::dispatch($service);
                } elseif (! data_get($service, 'server')) {
                    echo "Service without server: {$service->name}\n";
                    DeleteResourceJob::dispatch($service);
                }
            });
        } catch (\Throwable $e) {
            echo "Error in service: {$e->getMessage()}\n";
        }

        // Cleanup orphaned service sub-resources
        try {
            ServiceApplication::whereDoesntHave('service')->each(function ($serviceApp) {
                echo "ServiceApplication without service: {$serviceApp->name}\n";
                $serviceApp->forceDelete();
            });
        } catch (\Throwable $e) {
            echo "Error in serviceApplications: {$e->getMessage()}\n";
        }
        try {
            ServiceDatabase::whereDoesntHave('service')->each(function ($serviceDb) {
                echo "ServiceDatabase without service: {$serviceDb->name}\n";
                $serviceDb->forceDelete();
            });
        } catch (\Throwable $e) {
            echo "Error in ServiceDatabases: {$e->getMessage()}\n";
        }

        try {
            $orphanedCerts = SslCertificate::whereNotIn('server_id', function ($query) {
                $query->select('id')->from('servers');
            })->get();

            foreach ($orphanedCerts as $cert) {
                echo "Deleting orphaned SSL certificate: {$cert->id} (server_id: {$cert->server_id})\n";
                $cert->delete();
            }
        } catch (\Throwable $e) {
            echo "Error in cleaning orphaned SSL certificates: {$e->getMessage()}\n";
        }
    }

    /**
     * Force delete soft-deleted (trashed) resources.
     */
    private function cleanupTrashedResources(string $modelClass, string $label): void
    {
        try {
            $modelClass::onlyTrashed()->each(function ($resource) use ($label) {
                echo "Deleting stuck {$label}: {$resource->name}\n";
                DeleteResourceJob::dispatch($resource);
            });
        } catch (\Throwable $e) {
            echo "Error in cleaning stuck {$label}: {$e->getMessage()}\n";
        }
    }

    /**
     * Delete resources not attached to environment, destination, or destination.server.
     * Uses cursor() to avoid loading all records into memory.
     */
    private function cleanupOrphanedResources(string $modelClass, string $label, bool $excludeIdZero = false): void
    {
        try {
            $query = $modelClass::with(['environment', 'destination']);
            if ($excludeIdZero) {
                $query->where('id', '!=', 0);
            }
            $query->cursor()->each(function ($resource) use ($label) {
                if (! $resource->environment) {
                    echo "{$label} without environment: {$resource->name}\n";
                    DeleteResourceJob::dispatch($resource);
                } elseif (! $resource->destination) {
                    echo "{$label} without destination: {$resource->name}\n";
                    DeleteResourceJob::dispatch($resource);
                } elseif (! data_get($resource, 'destination.server')) {
                    echo "{$label} without server: {$resource->name}\n";
                    DeleteResourceJob::dispatch($resource);
                }
            });
        } catch (\Throwable $e) {
            echo "Error in {$label}: {$e->getMessage()}\n";
        }
    }
}
