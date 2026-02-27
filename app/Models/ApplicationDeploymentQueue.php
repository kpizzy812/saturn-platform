<?php

namespace App\Models;

use App\Events\DeploymentLogEntry as DeploymentLogEntryEvent;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

/**
 * @property int $id
 * @property int $application_id
 * @property string $deployment_uuid
 * @property int $pull_request_id
 * @property bool $force_rebuild
 * @property string|null $commit
 * @property string $status
 * @property bool $is_webhook
 * @property bool $is_api
 * @property string|null $logs
 * @property string|null $current_process_id
 * @property bool $restart_only
 * @property string|null $git_type
 * @property int|null $server_id
 * @property string|null $application_name
 * @property string|null $server_name
 * @property string|null $deployment_url
 * @property string|null $destination_id
 * @property bool $only_this_server
 * @property bool $rollback
 * @property bool $is_promotion
 * @property string|null $promoted_from_image
 * @property string|null $commit_message
 * @property string|null $horizon_job_id
 * @property array|null $canary_state
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read Application|null $application
 * @property-read Server|null $server
 */
#[OA\Schema(
    description: 'Application Deployment Queue model',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'application_id', type: 'string'),
        new OA\Property(property: 'deployment_uuid', type: 'string'),
        new OA\Property(property: 'pull_request_id', type: 'integer'),
        new OA\Property(property: 'force_rebuild', type: 'boolean'),
        new OA\Property(property: 'commit', type: 'string'),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'is_webhook', type: 'boolean'),
        new OA\Property(property: 'is_api', type: 'boolean'),
        new OA\Property(property: 'created_at', type: 'string'),
        new OA\Property(property: 'updated_at', type: 'string'),
        new OA\Property(property: 'logs', type: 'string'),
        new OA\Property(property: 'current_process_id', type: 'string'),
        new OA\Property(property: 'restart_only', type: 'boolean'),
        new OA\Property(property: 'git_type', type: 'string'),
        new OA\Property(property: 'server_id', type: 'integer'),
        new OA\Property(property: 'application_name', type: 'string'),
        new OA\Property(property: 'server_name', type: 'string'),
        new OA\Property(property: 'deployment_url', type: 'string'),
        new OA\Property(property: 'destination_id', type: 'string'),
        new OA\Property(property: 'only_this_server', type: 'boolean'),
        new OA\Property(property: 'rollback', type: 'boolean'),
        new OA\Property(property: 'is_promotion', type: 'boolean'),
        new OA\Property(property: 'promoted_from_image', type: 'string'),
        new OA\Property(property: 'commit_message', type: 'string'),
    ],
)]
class ApplicationDeploymentQueue extends Model
{
    use HasFactory;

    // Deployment stage constants
    public const STAGE_PREPARE = 'prepare';

    public const STAGE_CLONE = 'clone';

    public const STAGE_BUILD = 'build';

    public const STAGE_PUSH = 'push';

    public const STAGE_DEPLOY = 'deploy';

    public const STAGE_HEALTHCHECK = 'healthcheck';

    protected $with = ['application'];

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
        'is_promotion',
        'promoted_from_image',
        'commit_message',
        'horizon_job_id',
        'started_at',
        'requires_approval',
        'approval_status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'user_id',
        'build_server_id',
        'canary_state',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'application_id' => 'integer',
        'requires_approval' => 'boolean',
        'approved_at' => 'datetime',
        'is_promotion' => 'boolean',
        'canary_state' => 'array',
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

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasOne<DeploymentLogAnalysis, $this> */
    public function logAnalysis(): HasOne
    {
        return $this->hasOne(DeploymentLogAnalysis::class, 'deployment_id');
    }

    /** @return HasOne<CodeReview, $this> */
    public function codeReview(): HasOne
    {
        return $this->hasOne(CodeReview::class, 'deployment_id');
    }

    /**
     * Get log entries for this deployment (new optimized storage).
     */
    /** @return HasMany<DeploymentLogEntry, $this> */
    public function logEntries(): HasMany
    {
        return $this->hasMany(DeploymentLogEntry::class, 'deployment_id')->orderBy('order');
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

        return collect(json_decode($this->logs))->where('name', $name)->first()?->output;
    }

    public function getHorizonJobStatus()
    {
        return getJobStatus($this->horizon_job_id);
    }

    public function commitMessage()
    {
        if (empty($this->commit_message)) {
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

        $lockedVars = $lockedVars->merge(
            $app->environment_variables
                ->where('is_shown_once', true)
                ->pluck('real_value', 'key')
                ->filter()
        );

        if ($this->pull_request_id !== 0) {
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

    /**
     * Add a log entry to this deployment.
     *
     * PERFORMANCE: Uses separate table with INSERT instead of JSON column UPDATE.
     * Each call is O(1) regardless of existing log count.
     * Previous implementation was O(N) per call = O(N²) total for N logs.
     */
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

        // Get next order number with atomic increment
        $order = 1;
        DB::transaction(function () use (&$order, $redactedMessage, $type, $hidden, $effectiveStage) {
            // PostgreSQL forbids FOR UPDATE with aggregate functions (MAX),
            // so we use ORDER BY + LIMIT 1 instead
            $lastEntry = DeploymentLogEntry::where('deployment_id', $this->id)
                ->orderByDesc('order')
                ->lockForUpdate()
                ->first(['order']);

            $order = ($lastEntry->order ?? 0) + 1;

            // Insert new log entry - O(1) operation
            DeploymentLogEntry::create([
                'deployment_id' => $this->id,
                'order' => $order,
                'command' => null,
                'output' => (string) $redactedMessage,
                'type' => $type,
                'hidden' => $hidden,
                'batch' => 1,
                'stage' => $effectiveStage,
            ]);
        });

        // Broadcast the log entry for real-time updates (only non-hidden entries)
        if (! $hidden && $this->deployment_uuid) {
            try {
                event(new DeploymentLogEntryEvent(
                    deploymentUuid: $this->deployment_uuid,
                    message: (string) $redactedMessage,
                    timestamp: $timestamp->toIso8601String(),
                    type: $type,
                    order: $order,
                    stage: $effectiveStage
                ));
            } catch (\Throwable $e) {
                Log::debug('Failed to broadcast deployment log entry', [
                    'deployment_uuid' => $this->deployment_uuid,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get logs in legacy JSON format for backward compatibility.
     *
     * This accessor merges data from both sources:
     * 1. New separate table (deployment_log_entries)
     * 2. Legacy JSON column (for old deployments)
     */
    public function getLogsAttribute(?string $value): ?string
    {
        // Check if we have entries in new table
        $newEntries = $this->logEntries()->get();

        if ($newEntries->isNotEmpty()) {
            // Return logs from new table in legacy JSON format
            $logs = $newEntries->map(fn (DeploymentLogEntry $entry) => $entry->toLegacyFormat())->all();

            return json_encode($logs, JSON_THROW_ON_ERROR);
        }

        // Fallback to legacy JSON column for old deployments
        return $value;
    }

    /**
     * Get the raw logs value from database without accessor transformation.
     * Used for checking if legacy data exists.
     */
    public function getRawLogsAttribute(): ?string
    {
        return $this->attributes['logs'] ?? null;
    }
}
