
import { Database, Box, Settings2 } from 'lucide-react';
import { Button, Select, Checkbox, Alert, Spinner } from '@/components/ui';
import type { EnvironmentMigrationOptions, MigrationTargets } from '@/types';

interface MigrateConfigureStepProps {
    sourceType: 'application' | 'service' | 'database';
    sourceName: string;
    targets: MigrationTargets | null;
    isLoading: boolean;
    error: string | null;
    selectedEnvironmentId: number | null;
    selectedServerId: number | null;
    options: EnvironmentMigrationOptions;
    onEnvironmentChange: (id: number) => void;
    onServerChange: (id: number) => void;
    onOptionsChange: (options: EnvironmentMigrationOptions) => void;
    onNext: () => void;
    onCancel: () => void;
}

export function MigrateConfigureStep({
    sourceType,
    sourceName,
    targets,
    isLoading,
    error,
    selectedEnvironmentId,
    selectedServerId,
    options,
    onEnvironmentChange,
    onServerChange,
    onOptionsChange,
    onNext,
    onCancel,
}: MigrateConfigureStepProps) {
    const isDatabase = sourceType === 'database';
    const hasTargets = targets?.target_environments && targets.target_environments.length > 0;
    const hasServers = targets?.servers && targets.servers.length > 0;
    const selectedEnv = targets?.target_environments.find(e => e.id === selectedEnvironmentId);
    const isTargetProduction = selectedEnv?.type === 'production';

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-12">
                <Spinner className="h-8 w-8" />
            </div>
        );
    }

    if (!hasTargets) {
        return (
            <div className="space-y-4">
                <Alert variant="danger">
                    No target environments available for migration. The resource may already be in the final
                    environment (production) or no environments of the next type exist.
                </Alert>
                <div className="flex justify-end">
                    <Button variant="secondary" onClick={onCancel}>
                        Close
                    </Button>
                </div>
            </div>
        );
    }

    const SourceIcon = sourceType === 'application' ? Box : sourceType === 'service' ? Settings2 : Database;

    return (
        <div className="space-y-6">
            {/* Source info */}
            <div className="flex items-center gap-3 rounded-lg border border-border p-3 bg-background-secondary">
                <SourceIcon className="h-5 w-5 text-foreground-muted" />
                <div>
                    <p className="text-sm font-medium text-foreground">{sourceName}</p>
                    <p className="text-xs text-foreground-muted">
                        From: {targets?.source.environment} ({targets?.source.environment_type})
                    </p>
                </div>
            </div>

            {error && <Alert variant="danger">{error}</Alert>}

            {/* Target environment */}
            <Select
                label="Target Environment"
                value={selectedEnvironmentId?.toString() || ''}
                onChange={(e) => onEnvironmentChange(Number(e.target.value))}
            >
                <option value="">Select target environment</option>
                {targets?.target_environments.map((env) => (
                    <option key={env.id} value={env.id.toString()}>
                        {env.name}
                    </option>
                ))}
            </Select>

            {/* Target server */}
            {hasServers ? (
                <Select
                    label="Target Server"
                    value={selectedServerId?.toString() || ''}
                    onChange={(e) => onServerChange(Number(e.target.value))}
                >
                    <option value="">Select target server</option>
                    {targets?.servers.map((server) => (
                        <option key={server.id} value={server.id.toString()}>
                            {server.name} ({server.ip})
                        </option>
                    ))}
                </Select>
            ) : (
                <Alert>No servers available. Please add a server first.</Alert>
            )}

            {/* Promotion Info */}
            <Alert variant="info" className="text-sm">
                <strong>Promote Mode:</strong> Updates code/config in target environment.
                Environment variables are NOT copied (they are environment-specific).
                Service connections are automatically rewired to target resources.
            </Alert>

            {/* Options */}
            <div className="space-y-4">
                <p className="text-sm font-medium text-foreground">Promotion Options</p>

                <div className="space-y-3 rounded-lg border border-border p-3">
                    <Checkbox
                        label="Rewire service connections"
                        hint="Automatically update DATABASE_URL, REDIS_URL etc. to target environment resources"
                        checked={options.rewire_connections ?? true}
                        onCheckedChange={(checked) =>
                            onOptionsChange({ ...options, rewire_connections: checked })
                        }
                    />

                    <Checkbox
                        label="Copy volume configurations"
                        hint="Include persistent storage settings (not data)"
                        checked={options.copy_volumes}
                        onCheckedChange={(checked) =>
                            onOptionsChange({ ...options, copy_volumes: checked })
                        }
                    />

                    <Checkbox
                        label="Auto-deploy after promotion"
                        hint="Automatically trigger deployment after config update"
                        checked={options.auto_deploy ?? false}
                        onCheckedChange={(checked) =>
                            onOptionsChange({ ...options, auto_deploy: checked })
                        }
                    />

                    {isDatabase && (
                        <Checkbox
                            label="Configuration only"
                            hint="Update config without recreating container (safe for production)"
                            checked={options.config_only}
                            onCheckedChange={(checked) =>
                                onOptionsChange({ ...options, config_only: checked })
                            }
                        />
                    )}

                    {isDatabase && !isTargetProduction && (
                        <Checkbox
                            label="Copy test data"
                            hint="Copy database contents from source to target. WARNING: All data in the target database will be replaced!"
                            checked={options.copy_data ?? false}
                            onCheckedChange={(checked) =>
                                onOptionsChange({ ...options, copy_data: checked })
                            }
                        />
                    )}
                </div>
            </div>

            {/* Actions */}
            <div className="flex justify-end gap-2">
                <Button variant="secondary" onClick={onCancel}>
                    Cancel
                </Button>
                <Button onClick={onNext} disabled={!selectedEnvironmentId || !selectedServerId}>
                    Continue
                </Button>
            </div>
        </div>
    );
}
