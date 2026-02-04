<?php

namespace App\Models;

use App\Events\DeploymentLogEntry;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Schema(
    description: 'Project model',
    type: 'object',
    properties: [
        'id' => ['type' => 'integer'],
        'application_id' => ['type' => 'string'],
        'deployment_uuid' => ['type' => 'string'],
        'pull_request_id' => ['type' => 'integer'],
        'force_rebuild' => ['type' => 'boolean'],
        'commit' => ['type' => 'string'],
        'status' => ['type' => 'string'],
        'is_webhook' => ['type' => 'boolean'],
        'is_api' => ['type' => 'boolean'],
        'created_at' => ['type' => 'string'],
        'updated_at' => ['type' => 'string'],
        'logs' => ['type' => 'string'],
        'current_process_id' => ['type' => 'string'],
        'restart_only' => ['type' => 'boolean'],
        'git_type' => ['type' => 'string'],
        'server_id' => ['type' => 'integer'],
        'application_name' => ['type' => 'string'],
        'server_name' => ['type' => 'string'],
        'deployment_url' => ['type' => 'string'],
        'destination_id' => ['type' => 'string'],
        'only_this_server' => ['type' => 'boolean'],
        'rollback' => ['type' => 'boolean'],
        'commit_message' => ['type' => 'string'],
    ],
)]
class ApplicationDeploymentQueue extends Model
{
    // Deployment stage constants
    public const STAGE_PREPARE = 'prepare';

    public const STAGE_CLONE = 'clone';

    public const STAGE_BUILD = 'build';

    public const STAGE_PUSH = 'push';

    public const STAGE_DEPLOY = 'deploy';

    public const STAGE_HEALTHCHECK = 'healthcheck';

    /**
     * The attributes that are mass assignable.
     * SECURITY: Using $fillable to prevent mass assignment vulnerabilities.
     */
    protected $fillable = [
        'application_id',
        'deployment_uuid',
        'pull_request_id',
        'force_rebuild',
        'commit',
        'status',
        'is_webhook',
        'is_api',
        'logs',
        'current_process_id',
        'restart_only',
        'git_type',
        'server_id',
        'application_name',
        'server_name',
        'deployment_url',
        'destination_id',
        'only_this_server',
        'rollback',
        'commit_message',
        'horizon_job_id',
        'started_at',
        'requires_approval',
        'approval_status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'user_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'application_id' => 'integer',
        'requires_approval' => 'boolean',
        'approved_at' => 'datetime',
    ];

    // Current deployment stage (not persisted, used for log entries)
    public ?string $currentStage = null;

    /**
     * Set the current deployment stage. All subsequent log entries will include this stage.
     */
    public function setStage(string $stage): self
    {
        $this->currentStage = $stage;

        return $this;
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function logAnalysis()
    {
        return $this->hasOne(DeploymentLogAnalysis::class, 'deployment_id');
    }

    public function codeReview()
    {
        return $this->hasOne(CodeReview::class, 'deployment_id');
    }

    public function server(): Attribute
    {
        return Attribute::make(
            get: fn () => Server::find($this->server_id),
        );
    }

    public function setStatus(string $status)
    {
        $this->update([
            'status' => $status,
        ]);
    }

    public function getOutput($name)
    {
        if (! $this->logs) {
            return null;
        }

        return collect(json_decode($this->logs))->where('name', $name)->first()?->output ?? null;
    }

    public function getHorizonJobStatus()
    {
        return getJobStatus($this->horizon_job_id);
    }

    public function commitMessage()
    {
        if (empty($this->commit_message) || is_null($this->commit_message)) {
            return null;
        }

        return str($this->commit_message)->value();
    }

    private function redactSensitiveInfo($text)
    {
        $text = remove_iip($text);

        $app = $this->application;
        if (! $app) {
            return $text;
        }

        $lockedVars = collect([]);

        if ($app->environment_variables) {
            $lockedVars = $lockedVars->merge(
                $app->environment_variables
                    ->where('is_shown_once', true)
                    ->pluck('real_value', 'key')
                    ->filter()
            );
        }

        if ($this->pull_request_id !== 0 && $app->environment_variables_preview) {
            $lockedVars = $lockedVars->merge(
                $app->environment_variables_preview
                    ->where('is_shown_once', true)
                    ->pluck('real_value', 'key')
                    ->filter()
            );
        }

        foreach ($lockedVars as $key => $value) {
            $escapedValue = preg_quote($value, '/');
            $text = preg_replace(
                '/'.$escapedValue.'/',
                REDACTED,
                $text
            );
        }

        return $text;
    }

    public function addLogEntry(string $message, string $type = 'stdout', bool $hidden = false, ?string $stage = null)
    {
        if ($type === 'error') {
            $type = 'stderr';
        }
        $message = str($message)->trim();
        if ($message->startsWith('╔')) {
            $message = "\n".$message;
        }
        $redactedMessage = $this->redactSensitiveInfo($message);
        $timestamp = Carbon::now('UTC');

        // Use explicitly passed stage, or fall back to currentStage property
        $effectiveStage = $stage ?? $this->currentStage;

        $newLogEntry = [
            'command' => null,
            'output' => $redactedMessage,
            'type' => $type,
            'timestamp' => $timestamp,
            'hidden' => $hidden,
            'batch' => 1,
            'stage' => $effectiveStage,
        ];

        $order = 1;

        // Use a transaction with pessimistic lock to prevent race conditions (lost updates)
        DB::transaction(function () use ($newLogEntry, &$order) {
            // SECURITY FIX: Use lockForUpdate() to prevent lost updates when multiple processes
            // write logs concurrently. Without this lock, parallel addLogEntry() calls could
            // overwrite each other's logs.
            $lockedInstance = static::where('id', $this->id)->lockForUpdate()->first();

            if (! $lockedInstance) {
                return; // Deployment was deleted, skip logging
            }

            if ($lockedInstance->logs) {
                $previousLogs = json_decode($lockedInstance->logs, associative: true, flags: JSON_THROW_ON_ERROR);
                $order = count($previousLogs) + 1;
                $newLogEntry['order'] = $order;
                $previousLogs[] = $newLogEntry;
                $lockedInstance->logs = json_encode($previousLogs, flags: JSON_THROW_ON_ERROR);
            } else {
                $lockedInstance->logs = json_encode([$newLogEntry], flags: JSON_THROW_ON_ERROR);
            }

            // Save without triggering events to prevent potential race conditions
            $lockedInstance->saveQuietly();

            // Update local instance to reflect changes
            $this->logs = $lockedInstance->logs;
        });

        // Broadcast the log entry for real-time updates (only non-hidden entries)
        if (! $hidden && $this->deployment_uuid) {
            try {
                event(new DeploymentLogEntry(
                    deploymentUuid: $this->deployment_uuid,
                    message: (string) $redactedMessage,
                    timestamp: $timestamp->toIso8601String(),
                    type: $type,
                    order: $order,
                    stage: $effectiveStage
                ));
            } catch (\Throwable) {
                // Silently fail broadcasting - don't break deployment for WebSocket issues
            }
        }
    }
}
