import * as React from 'react';
import { useState, useEffect } from 'react';
import { Modal, Button, Alert, Spinner, Checkbox, Select } from '@/components/ui';
import { ArrowRight, Box, Database, Layers, Server as ServerIcon, AlertTriangle, CheckCircle2, Clock } from 'lucide-react';
import { useEnvironmentMigrationTargets } from '@/hooks/useMigrations';
import type { Application, StandaloneDatabase, Service, Environment, Server } from '@/types';
import axios from 'axios';

interface EnvironmentMigrateModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    environment: Environment | null;
    applications: Application[];
    databases: StandaloneDatabase[];
    services: Service[];
    projectUuid: string;
}

interface MigrationOptions {
    copy_env_vars: boolean;
    copy_volumes: boolean;
    update_existing: boolean;
    config_only: boolean;
}

interface MigrationResult {
    resource_type: string;
    resource_name: string;
    status: 'pending' | 'success' | 'error';
    migration_uuid?: string;
    requires_approval?: boolean;
    error?: string;
}

type MigrationStep = 'configure' | 'confirm' | 'progress';

export function EnvironmentMigrateModal({
    open,
    onOpenChange,
    environment,
    applications,
    databases,
    services,
    projectUuid: _projectUuid,
}: EnvironmentMigrateModalProps) {
    void _projectUuid; // Reserved for future use

    const [step, setStep] = useState<MigrationStep>('configure');
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [results, setResults] = useState<MigrationResult[]>([]);
    const [anyRequiresApproval, setAnyRequiresApproval] = useState(false);

    // Configuration state
    const [selectedEnvironmentId, setSelectedEnvironmentId] = useState<string>('');
    const [selectedServerId, setSelectedServerId] = useState<string>('');
    const [options, setOptions] = useState<MigrationOptions>({
        copy_env_vars: true,
        copy_volumes: true,
        update_existing: false,
        config_only: false,
    });

    // Selected resources to migrate
    const [selectedResources, setSelectedResources] = useState<{
        applications: string[];
        databases: string[];
        services: string[];
    }>({
        applications: [],
        databases: [],
        services: [],
    });

    // Fetch targets for environment migration
    const { targets, isLoading: isLoadingTargets } = useEnvironmentMigrationTargets(
        environment?.uuid || '',
        open && !!environment
    );

    // Total count of resources
    const totalResources = applications.length + databases.length + services.length;

    // Reset state when modal closes
    useEffect(() => {
        if (!open) {
            setStep('configure');
            setError(null);
            setIsSubmitting(false);
            setResults([]);
            setAnyRequiresApproval(false);
            setSelectedEnvironmentId('');
            setSelectedServerId('');
            setOptions({
                copy_env_vars: true,
                copy_volumes: true,
                update_existing: false,
                config_only: false,
            });
        }
    }, [open]);

    // Auto-select all resources when modal opens
    useEffect(() => {
        if (open) {
            setSelectedResources({
                applications: applications.map(a => a.uuid),
                databases: databases.map(d => d.uuid),
                services: services.map(s => s.uuid),
            });
        }
    }, [open, applications, databases, services]);

    // Auto-select first environment and server when targets load
    useEffect(() => {
        if (targets) {
            if (targets.target_environments?.length > 0 && !selectedEnvironmentId) {
                setSelectedEnvironmentId(targets.target_environments[0].id.toString());
            }
            if (targets.servers?.length > 0 && !selectedServerId) {
                setSelectedServerId(targets.servers[0].id.toString());
            }
        }
    }, [targets, selectedEnvironmentId, selectedServerId]);

    const toggleResource = (type: 'applications' | 'databases' | 'services', uuid: string) => {
        setSelectedResources(prev => {
            const list = prev[type];
            if (list.includes(uuid)) {
                return { ...prev, [type]: list.filter(id => id !== uuid) };
            }
            return { ...prev, [type]: [...list, uuid] };
        });
    };

    const selectedCount = selectedResources.applications.length +
        selectedResources.databases.length +
        selectedResources.services.length;

    const handleConfigure = () => {
        if (!selectedEnvironmentId || !selectedServerId) {
            setError('Please select target environment and server.');
            return;
        }
        if (selectedCount === 0) {
            setError('Please select at least one resource to migrate.');
            return;
        }
        setError(null);
        setStep('confirm');
    };

    const handleConfirm = async () => {
        if (!selectedEnvironmentId || !selectedServerId) return;

        setIsSubmitting(true);
        setError(null);

        const allResults: MigrationResult[] = [];
        let hasApproval = false;
        const targetEnvId = parseInt(selectedEnvironmentId, 10);
        const targetServId = parseInt(selectedServerId, 10);
        const isTargetProduction = targets?.target_environments.find(e => e.id === targetEnvId)?.type === 'production';

        // Migrate each selected resource
        const migrateResource = async (type: 'application' | 'database' | 'service', uuid: string, name: string, index: number) => {
            try {
                const response = await axios.post('/api/v1/migrations', {
                    source_type: type,
                    source_uuid: uuid,
                    target_environment_id: targetEnvId,
                    target_server_id: targetServId,
                    options: {
                        ...options,
                        // For databases going to production, force config_only (protect prod data!)
                        config_only: type === 'database' && isTargetProduction ? true : options.config_only,
                    },
                });

                if (response.data.requires_approval) {
                    hasApproval = true;
                }

                allResults[index] = {
                    resource_type: type,
                    resource_name: name,
                    status: 'success',
                    migration_uuid: response.data.migration?.uuid,
                    requires_approval: response.data.requires_approval,
                };
            } catch (err) {
                allResults[index] = {
                    resource_type: type,
                    resource_name: name,
                    status: 'error',
                    error: axios.isAxiosError(err) ? err.response?.data?.message : 'Unknown error',
                };
            }
            setResults([...allResults]);
        };

        // Initialize all results as pending
        let idx = 0;

        for (const uuid of selectedResources.applications) {
            const app = applications.find(a => a.uuid === uuid);
            if (app) {
                allResults.push({
                    resource_type: 'application',
                    resource_name: app.name,
                    status: 'pending',
                });
            }
        }
        for (const uuid of selectedResources.databases) {
            const db = databases.find(d => d.uuid === uuid);
            if (db) {
                allResults.push({
                    resource_type: 'database',
                    resource_name: db.name,
                    status: 'pending',
                });
            }
        }
        for (const uuid of selectedResources.services) {
            const svc = services.find(s => s.uuid === uuid);
            if (svc) {
                allResults.push({
                    resource_type: 'service',
                    resource_name: svc.name,
                    status: 'pending',
                });
            }
        }
        setResults([...allResults]);

        // Migrate applications
        idx = 0;
        for (const uuid of selectedResources.applications) {
            const app = applications.find(a => a.uuid === uuid);
            if (app) {
                await migrateResource('application', uuid, app.name, idx);
                idx++;
            }
        }

        // Migrate databases
        for (const uuid of selectedResources.databases) {
            const db = databases.find(d => d.uuid === uuid);
            if (db) {
                await migrateResource('database', uuid, db.name, idx);
                idx++;
            }
        }

        // Migrate services
        for (const uuid of selectedResources.services) {
            const svc = services.find(s => s.uuid === uuid);
            if (svc) {
                await migrateResource('service', uuid, svc.name, idx);
                idx++;
            }
        }

        setAnyRequiresApproval(hasApproval);
        setIsSubmitting(false);
        setStep('progress');
    };

    const handleClose = () => {
        onOpenChange(false);
    };

    const getStepTitle = () => {
        switch (step) {
            case 'configure':
                return 'Migrate Environment';
            case 'confirm':
                return 'Confirm Migration';
            case 'progress':
                return anyRequiresApproval ? 'Awaiting Approval' : 'Migration Complete';
        }
    };

    const getStepDescription = () => {
        switch (step) {
            case 'configure':
                return `Migrate resources from "${environment?.name || 'environment'}" to another environment.`;
            case 'confirm':
                return `You are about to migrate ${selectedCount} resource(s).`;
            case 'progress':
                return anyRequiresApproval
                    ? 'Some migrations require admin approval before execution.'
                    : 'All migrations have been initiated.';
        }
    };

    const selectedEnvName = targets?.target_environments.find(e => e.id === parseInt(selectedEnvironmentId, 10))?.name || '';
    const selectedServerName = targets?.servers.find((s: Server) => s.id === parseInt(selectedServerId, 10))?.name || '';

    if (!environment) return null;

    return (
        <Modal
            isOpen={open}
            onClose={handleClose}
            title={getStepTitle()}
            description={getStepDescription()}
            size="lg"
        >
            {step === 'configure' && (
                <div className="space-y-6">
                    {/* Source environment info */}
                    <div className="rounded-lg border border-border bg-background-secondary p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                <Layers className="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <p className="font-medium">{environment.name}</p>
                                <p className="text-sm text-foreground-muted">
                                    {totalResources} resource(s) available
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Resource selection */}
                    <div className="space-y-3">
                        <p className="text-sm font-medium">Select resources to migrate</p>

                        {applications.length > 0 && (
                            <div className="space-y-2">
                                <p className="text-xs font-medium text-foreground-muted">Applications</p>
                                {applications.map(app => (
                                    <div key={app.uuid} className="flex items-center gap-3 rounded border border-border p-2">
                                        <Checkbox
                                            id={`app-${app.uuid}`}
                                            checked={selectedResources.applications.includes(app.uuid)}
                                            onCheckedChange={() => toggleResource('applications', app.uuid)}
                                        />
                                        <Box className="h-4 w-4 text-foreground-muted" />
                                        <label htmlFor={`app-${app.uuid}`} className="flex-1 cursor-pointer text-sm">
                                            {app.name}
                                        </label>
                                    </div>
                                ))}
                            </div>
                        )}

                        {databases.length > 0 && (
                            <div className="space-y-2">
                                <p className="text-xs font-medium text-foreground-muted">Databases</p>
                                {databases.map(db => (
                                    <div key={db.uuid} className="flex items-center gap-3 rounded border border-border p-2">
                                        <Checkbox
                                            id={`db-${db.uuid}`}
                                            checked={selectedResources.databases.includes(db.uuid)}
                                            onCheckedChange={() => toggleResource('databases', db.uuid)}
                                        />
                                        <Database className="h-4 w-4 text-foreground-muted" />
                                        <label htmlFor={`db-${db.uuid}`} className="flex-1 cursor-pointer text-sm">
                                            {db.name}
                                        </label>
                                    </div>
                                ))}
                            </div>
                        )}

                        {services.length > 0 && (
                            <div className="space-y-2">
                                <p className="text-xs font-medium text-foreground-muted">Services</p>
                                {services.map(svc => (
                                    <div key={svc.uuid} className="flex items-center gap-3 rounded border border-border p-2">
                                        <Checkbox
                                            id={`svc-${svc.uuid}`}
                                            checked={selectedResources.services.includes(svc.uuid)}
                                            onCheckedChange={() => toggleResource('services', svc.uuid)}
                                        />
                                        <Layers className="h-4 w-4 text-foreground-muted" />
                                        <label htmlFor={`svc-${svc.uuid}`} className="flex-1 cursor-pointer text-sm">
                                            {svc.name}
                                        </label>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Target environment */}
                    {isLoadingTargets ? (
                        <div className="flex items-center gap-2 text-sm text-foreground-muted">
                            <Spinner size="sm" />
                            Loading environments...
                        </div>
                    ) : targets?.target_environments && targets.target_environments.length > 0 ? (
                        <Select
                            label="Target Environment"
                            value={selectedEnvironmentId}
                            onChange={(e) => setSelectedEnvironmentId(e.target.value)}
                            options={targets.target_environments.map((env: { id: number; name: string; type: string }) => ({
                                value: env.id.toString(),
                                label: `${env.name} (${env.type})`,
                            }))}
                        />
                    ) : (
                        <Alert variant="warning">
                            No target environments available for migration.
                        </Alert>
                    )}

                    {/* Target server */}
                    {isLoadingTargets ? (
                        <div className="flex items-center gap-2 text-sm text-foreground-muted">
                            <Spinner size="sm" />
                            Loading servers...
                        </div>
                    ) : targets?.servers && targets.servers.length > 0 ? (
                        <Select
                            label="Target Server"
                            value={selectedServerId}
                            onChange={(e) => setSelectedServerId(e.target.value)}
                            options={targets.servers.map((server: Server) => ({
                                value: server.id.toString(),
                                label: server.name,
                            }))}
                        />
                    ) : (
                        <Alert variant="warning">
                            No servers available for deployment.
                        </Alert>
                    )}

                    {/* Options */}
                    <div className="space-y-3">
                        <p className="text-sm font-medium">Options</p>
                        <div className="space-y-2">
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="copy-env-vars"
                                    checked={options.copy_env_vars}
                                    onCheckedChange={(checked) => setOptions({ ...options, copy_env_vars: !!checked })}
                                />
                                <label htmlFor="copy-env-vars" className="text-sm cursor-pointer">
                                    Copy environment variables
                                </label>
                            </div>
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="copy-volumes"
                                    checked={options.copy_volumes}
                                    onCheckedChange={(checked) => setOptions({ ...options, copy_volumes: !!checked })}
                                />
                                <label htmlFor="copy-volumes" className="text-sm cursor-pointer">
                                    Copy volume configurations
                                </label>
                            </div>
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="update-existing"
                                    checked={options.update_existing}
                                    onCheckedChange={(checked) => setOptions({ ...options, update_existing: !!checked })}
                                />
                                <label htmlFor="update-existing" className="text-sm cursor-pointer">
                                    Update existing resources (if found)
                                </label>
                            </div>
                        </div>
                    </div>

                    {error && <Alert variant="error">{error}</Alert>}

                    {/* Actions */}
                    <div className="flex justify-end gap-3">
                        <Button variant="outline" onClick={handleClose}>
                            Cancel
                        </Button>
                        <Button onClick={handleConfigure} disabled={selectedCount === 0}>
                            Next
                            <ArrowRight className="ml-2 h-4 w-4" />
                        </Button>
                    </div>
                </div>
            )}

            {step === 'confirm' && (
                <div className="space-y-6">
                    {/* Summary */}
                    <div className="rounded-lg border border-border p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm text-foreground-muted">From</p>
                                <p className="font-medium">{environment.name}</p>
                            </div>
                            <ArrowRight className="h-5 w-5 text-foreground-muted" />
                            <div className="text-right">
                                <p className="text-sm text-foreground-muted">To</p>
                                <p className="font-medium">{selectedEnvName}</p>
                            </div>
                        </div>
                        <div className="mt-3 border-t border-border pt-3">
                            <p className="text-sm text-foreground-muted">Server</p>
                            <div className="flex items-center gap-2 font-medium">
                                <ServerIcon className="h-4 w-4" />
                                {selectedServerName}
                            </div>
                        </div>
                    </div>

                    {/* Resources to migrate */}
                    <div className="space-y-2">
                        <p className="text-sm font-medium">Resources to migrate ({selectedCount})</p>
                        <div className="max-h-48 space-y-1 overflow-y-auto">
                            {selectedResources.applications.map(uuid => {
                                const app = applications.find(a => a.uuid === uuid);
                                return app && (
                                    <div key={uuid} className="flex items-center gap-2 text-sm">
                                        <Box className="h-4 w-4 text-foreground-muted" />
                                        {app.name}
                                    </div>
                                );
                            })}
                            {selectedResources.databases.map(uuid => {
                                const db = databases.find(d => d.uuid === uuid);
                                return db && (
                                    <div key={uuid} className="flex items-center gap-2 text-sm">
                                        <Database className="h-4 w-4 text-foreground-muted" />
                                        {db.name}
                                        {targets?.target_environments.find(e => e.id === parseInt(selectedEnvironmentId, 10))?.type === 'production' && (
                                            <span className="text-xs text-warning">(config only)</span>
                                        )}
                                    </div>
                                );
                            })}
                            {selectedResources.services.map(uuid => {
                                const svc = services.find(s => s.uuid === uuid);
                                return svc && (
                                    <div key={uuid} className="flex items-center gap-2 text-sm">
                                        <Layers className="h-4 w-4 text-foreground-muted" />
                                        {svc.name}
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Warning for production */}
                    {targets?.target_environments.find(e => e.id === parseInt(selectedEnvironmentId, 10))?.type === 'production' && (
                        <Alert variant="warning">
                            <AlertTriangle className="h-4 w-4" />
                            <div>
                                <p className="font-medium">Production Migration</p>
                                <p className="text-sm">
                                    Databases will be migrated as config-only (no data will be copied or overwritten).
                                    This migration may require admin approval.
                                </p>
                            </div>
                        </Alert>
                    )}

                    {error && <Alert variant="error">{error}</Alert>}

                    {/* Actions */}
                    <div className="flex justify-end gap-3">
                        <Button variant="outline" onClick={() => setStep('configure')} disabled={isSubmitting}>
                            Back
                        </Button>
                        <Button onClick={handleConfirm} disabled={isSubmitting}>
                            {isSubmitting ? (
                                <>
                                    <Spinner size="sm" className="mr-2" />
                                    Migrating...
                                </>
                            ) : (
                                'Start Migration'
                            )}
                        </Button>
                    </div>
                </div>
            )}

            {step === 'progress' && (
                <div className="space-y-6">
                    {/* Results */}
                    <div className="space-y-2">
                        {results.map((result, index) => (
                            <div
                                key={index}
                                className="flex items-center justify-between rounded border border-border p-3"
                            >
                                <div className="flex items-center gap-3">
                                    {result.resource_type === 'application' && <Box className="h-4 w-4" />}
                                    {result.resource_type === 'database' && <Database className="h-4 w-4" />}
                                    {result.resource_type === 'service' && <Layers className="h-4 w-4" />}
                                    <span>{result.resource_name}</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    {result.status === 'pending' && (
                                        <Spinner size="sm" />
                                    )}
                                    {result.status === 'success' && result.requires_approval && (
                                        <span className="flex items-center gap-1 text-sm text-warning">
                                            <Clock className="h-4 w-4" />
                                            Pending Approval
                                        </span>
                                    )}
                                    {result.status === 'success' && !result.requires_approval && (
                                        <span className="flex items-center gap-1 text-sm text-success">
                                            <CheckCircle2 className="h-4 w-4" />
                                            Started
                                        </span>
                                    )}
                                    {result.status === 'error' && (
                                        <span className="flex items-center gap-1 text-sm text-destructive">
                                            <AlertTriangle className="h-4 w-4" />
                                            {result.error || 'Failed'}
                                        </span>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>

                    {anyRequiresApproval && (
                        <Alert>
                            <Clock className="h-4 w-4" />
                            <div>
                                <p className="font-medium">Approval Required</p>
                                <p className="text-sm">
                                    Some migrations require admin approval. You can track them in the Approvals section.
                                </p>
                            </div>
                        </Alert>
                    )}

                    {/* Actions */}
                    <div className="flex justify-end">
                        <Button onClick={handleClose}>
                            Close
                        </Button>
                    </div>
                </div>
            )}
        </Modal>
    );
}
