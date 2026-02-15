<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    /**
     * Boot the auditable trait for a model.
     */
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            if ($model->shouldAudit('create')) {
                AuditLog::log(
                    action: 'create',
                    resource: $model,
                    description: $model->getAuditDescription('create'),
                    metadata: $model->getAuditMetadata('create')
                );
            }
        });

        static::updated(function (Model $model) {
            if ($model->shouldAudit('update')) {
                $changes = $model->getChanges();
                // Don't log if only timestamps were updated
                $significantChanges = array_diff_key($changes, array_flip(['updated_at', 'created_at']));

                if (! empty($significantChanges)) {
                    AuditLog::log(
                        action: 'update',
                        resource: $model,
                        description: $model->getAuditDescription('update'),
                        metadata: array_merge(
                            $model->getAuditMetadata('update'),
                            ['changes' => $model->getAuditableChanges()]
                        )
                    );
                }
            }
        });

        static::deleted(function (Model $model) {
            if ($model->shouldAudit('delete')) {
                AuditLog::log(
                    action: 'delete',
                    resource: $model,
                    description: $model->getAuditDescription('delete'),
                    metadata: array_merge(
                        $model->getAuditMetadata('delete'),
                        ['deleted_attributes' => $model->getOriginal()]
                    )
                );
            }
        });
    }

    /**
     * Determine if the given action should be audited.
     * Override this method in your model to customize auditing behavior.
     */
    protected function shouldAudit(string $action): bool
    {
        // Check if there's a property to exclude specific actions
        if (property_exists($this, 'auditExclude')) {
            return ! in_array($action, $this->auditExclude);
        }

        // Check if there's a property to only include specific actions
        if (property_exists($this, 'auditOnly')) {
            return in_array($action, $this->auditOnly);
        }

        // By default, audit all actions
        return true;
    }

    /**
     * Get the audit description for the given action.
     * Override this method in your model to customize descriptions.
     */
    protected function getAuditDescription(string $action): string
    {
        $resourceName = $this->getAuditResourceName();
        $actionPast = match ($action) {
            'create' => 'created',
            'update' => 'updated',
            'delete' => 'deleted',
            default => $action,
        };

        return ucfirst(class_basename($this))." '{$resourceName}' was {$actionPast}";
    }

    /**
     * Get additional metadata for the audit log.
     * Override this method in your model to include custom metadata.
     */
    protected function getAuditMetadata(string $action): array
    {
        return [];
    }

    /**
     * Get the resource name for audit logs.
     */
    protected function getAuditResourceName(): string
    {
        return $this->getAttribute('name')
            ?? $this->getAttribute('title')
            ?? $this->getAttribute('key')
            ?? (method_exists($this, 'getName') ? $this->getName() : null)
            ?? (method_exists($this, 'getTitle') ? $this->getTitle() : null)
            ?? (string) ($this->id ?? 'Unknown');
    }

    /**
     * Get the changes in a format suitable for audit logs.
     * Only includes attributes that were actually changed.
     */
    protected function getAuditableChanges(): array
    {
        $changes = $this->getChanges();
        $original = $this->getOriginal();

        $auditableChanges = [];
        $hiddenAttributes = $this->getHidden();

        foreach ($changes as $key => $newValue) {
            // Skip timestamps and hidden attributes
            if (in_array($key, ['updated_at', 'created_at']) || in_array($key, $hiddenAttributes)) {
                continue;
            }

            $auditableChanges[$key] = [
                'old' => $original[$key] ?? null,
                'new' => $newValue,
            ];
        }

        return $auditableChanges;
    }

    /**
     * Manually log an audit entry for this model.
     * Use this for custom actions beyond create/update/delete.
     */
    public function audit(string $action, ?string $description = null, array $metadata = []): AuditLog
    {
        return AuditLog::log(
            action: $action,
            resource: $this,
            description: $description ?? $this->getAuditDescription($action),
            metadata: array_merge($this->getAuditMetadata($action), $metadata)
        );
    }

    /**
     * Get all audit logs for this model.
     */
    public function auditLogs()
    {
        return AuditLog::byResource(get_class($this), $this->id)
            ->latest()
            ->get();
    }
}
