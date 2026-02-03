import * as React from 'react';
import { ArrowRight, Check, X, AlertTriangle } from 'lucide-react';
import { Button, Alert } from '@/components/ui';
import type { EnvironmentMigrationOptions } from '@/types';

interface MigrateConfirmStepProps {
    sourceType: 'application' | 'service' | 'database';
    sourceName: string;
    sourceEnvironment: string;
    targetEnvironment: string;
    targetServer: string;
    options: EnvironmentMigrationOptions;
    isSubmitting: boolean;
    error: string | null;
    onConfirm: () => void;
    onBack: () => void;
    onCancel: () => void;
}

export function MigrateConfirmStep({
    sourceType,
    sourceName,
    sourceEnvironment,
    targetEnvironment,
    targetServer,
    options,
    isSubmitting,
    error,
    onConfirm,
    onBack,
    onCancel,
}: MigrateConfirmStepProps) {
    const isDatabase = sourceType === 'database';
    const targetIsProduction = targetEnvironment.toLowerCase().includes('prod');

    return (
        <div className="space-y-6">
            {error && <Alert variant="danger">{error}</Alert>}

            {/* Migration summary */}
            <div className="rounded-lg border border-border p-4 space-y-4">
                <div className="flex items-center justify-between">
                    <div className="space-y-1">
                        <p className="text-sm font-medium text-foreground">{sourceName}</p>
                        <p className="text-xs text-foreground-muted capitalize">{sourceType}</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="text-center">
                            <p className="text-sm font-medium text-foreground">{sourceEnvironment}</p>
                            <p className="text-xs text-foreground-muted">Source</p>
                        </div>
                        <ArrowRight className="h-4 w-4 text-foreground-muted" />
                        <div className="text-center">
                            <p className="text-sm font-medium text-foreground">{targetEnvironment}</p>
                            <p className="text-xs text-foreground-muted">Target</p>
                        </div>
                    </div>
                </div>

                <div className="pt-2 border-t border-border">
                    <p className="text-xs text-foreground-muted mb-2">Target Server</p>
                    <p className="text-sm font-medium text-foreground">{targetServer}</p>
                </div>
            </div>

            {/* Options summary */}
            <div className="rounded-lg border border-border p-4 space-y-2">
                <p className="text-sm font-medium text-foreground mb-3">Migration Options</p>
                <div className="space-y-2">
                    <OptionItem
                        label="Copy environment variables"
                        enabled={options.copy_env_vars ?? true}
                    />
                    <OptionItem
                        label="Copy volume configurations"
                        enabled={options.copy_volumes ?? true}
                    />
                    <OptionItem
                        label="Update existing resource"
                        enabled={options.update_existing ?? false}
                    />
                    {isDatabase && (
                        <OptionItem
                            label="Configuration only"
                            enabled={options.config_only ?? false}
                        />
                    )}
                </div>
            </div>

            {/* Production warning */}
            {targetIsProduction && (
                <Alert variant="warning">
                    <div className="flex items-start gap-2">
                        <AlertTriangle className="h-4 w-4 mt-0.5 flex-shrink-0" />
                        <div>
                            <p className="font-medium">Production Migration</p>
                            <p className="text-sm mt-1">
                                This migration targets a production environment. It will require approval from an admin
                                or owner before execution.
                                {isDatabase && (
                                    <>
                                        {' '}
                                        For databases, only configuration will be updated - existing data will not be
                                        affected.
                                    </>
                                )}
                            </p>
                        </div>
                    </div>
                </Alert>
            )}

            {/* Actions */}
            <div className="flex justify-between">
                <Button variant="secondary" onClick={onBack} disabled={isSubmitting}>
                    Back
                </Button>
                <div className="flex gap-2">
                    <Button variant="secondary" onClick={onCancel} disabled={isSubmitting}>
                        Cancel
                    </Button>
                    <Button onClick={onConfirm} disabled={isSubmitting} loading={isSubmitting}>
                        {isSubmitting ? 'Starting...' : 'Start Migration'}
                    </Button>
                </div>
            </div>
        </div>
    );
}

function OptionItem({ label, enabled }: { label: string; enabled: boolean }) {
    return (
        <div className="flex items-center gap-2 text-sm">
            {enabled ? (
                <Check className="h-4 w-4 text-success" />
            ) : (
                <X className="h-4 w-4 text-foreground-muted" />
            )}
            <span className={enabled ? 'text-foreground' : 'text-foreground-muted'}>{label}</span>
        </div>
    );
}
