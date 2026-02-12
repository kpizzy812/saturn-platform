import * as React from 'react';
import { useState, useEffect, useCallback } from 'react';
import { Modal, Button, Alert, Spinner, Checkbox, Select, Input } from '@/components/ui';
import {
    ArrowRight, Box, Database, Layers, Server as ServerIcon,
    AlertTriangle, CheckCircle2, Clock, Info, Plus, RefreshCw, XCircle,
    Globe, Shield,
} from 'lucide-react';
import { useEnvironmentMigrationTargets } from '@/hooks/useMigrations';
import type {
    Application, StandaloneDatabase, Service, Environment, Server,
    BulkCheckResult, BulkCheckResourceResult, MigrationMode,
} from '@/types';
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
    auto_deploy: boolean;
}

interface MigrationResult {
    resource_type: string;
    resource_name: string;
    status: 'pending' | 'success' | 'error';
    migration_uuid?: string;
    requires_approval?: boolean;
    error?: string;
}

interface DomainConfig {
    [uuid: string]: string; // resource uuid -> domain string
}

type MigrationStep = 'configure' | 'validate' | 'review' | 'confirm' | 'progress';

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
    const [isValidating, setIsValidating] = useState(false);
    const [results, setResults] = useState<MigrationResult[]>([]);
    const [anyRequiresApproval, setAnyRequiresApproval] = useState(false);

    // Configuration state
    const [selectedEnvironmentId, setSelectedEnvironmentId] = useState<string>('');
    const [selectedServerId, setSelectedServerId] = useState<string>('');
    const [options, setOptions] = useState<MigrationOptions>({
        copy_env_vars: true,
        copy_volumes: true,
        auto_deploy: true,
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

    // Bulk check results (auto-detect clone/promote + pre-checks + diff)
    const [checkResult, setCheckResult] = useState<BulkCheckResult | null>(null);

    // Domain configuration for production clone
    const [domainConfigs, setDomainConfigs] = useState<DomainConfig>({});

    // Fetch targets for environment migration
    const { targets, isLoading: isLoadingTargets } = useEnvironmentMigrationTargets(
        environment?.uuid || '',
        open && !!environment
    );

    // Total count of resources
    const totalResources = applications.length + databases.length + services.length;

    // Computed: is target environment production?
    const isTargetProduction = React.useMemo(() => {
        if (!selectedEnvironmentId || !targets?.target_environments) return false;
        const targetEnv = targets.target_environments.find(
            (e: any) => e.id === parseInt(selectedEnvironmentId, 10)
        ) as any;
        return targetEnv?.type === 'production';
    }, [selectedEnvironmentId, targets]);

    // Reset state when modal closes
    useEffect(() => {
        if (!open) {
            setStep('configure');
            setError(null);
            setIsSubmitting(false);
            setIsValidating(false);
            setResults([]);
            setAnyRequiresApproval(false);
            setSelectedEnvironmentId('');
            setSelectedServerId('');
            setOptions({
                copy_env_vars: true,
                copy_volumes: true,
                auto_deploy: true,
            });
            setCheckResult(null);
            setDomainConfigs({});
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

    // Get resource check result from bulk check
    const getResourceCheck = useCallback((uuid: string): BulkCheckResourceResult | undefined => {
        return checkResult?.resources?.find(r => r.uuid === uuid);
    }, [checkResult]);

    // Run bulk pre-migration validation
    const runValidation = async () => {
        if (!selectedEnvironmentId || !selectedServerId || !environment) return;

        setIsValidating(true);
        setError(null);

        try {
            // Build resources list
            const resources: Array<{ type: string; uuid: string }> = [];
            for (const uuid of selectedResources.databases) {
                resources.push({ type: 'database', uuid });
            }
            for (const uuid of selectedResources.services) {
                resources.push({ type: 'service', uuid });
            }
            for (const uuid of selectedResources.applications) {
                resources.push({ type: 'application', uuid });
            }

            const response = await axios.post('/api/v1/migrations/environment-check', {
                source_environment_uuid: environment.uuid,
                target_environment_id: parseInt(selectedEnvironmentId, 10),
                target_server_id: parseInt(selectedServerId, 10),
                resources,
            });

            setCheckResult(response.data);

            // Check if there are any blocking errors
            const hasBlockingErrors = response.data.resources?.some(
                (r: BulkCheckResourceResult) => r.pre_checks && !r.pre_checks.pass
            );

            if (hasBlockingErrors) {
                setStep('validate');
            } else {
                // Skip validation step if no errors — go directly to review
                setStep('review');
            }
        } catch (err) {
            setError(axios.isAxiosError(err) ? err.response?.data?.message || 'Validation failed' : 'Validation failed');
        } finally {
            setIsValidating(false);
        }
    };

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
        runValidation();
    };

    const handleValidationContinue = () => {
        setStep('review');
    };

    const handleReviewContinue = () => {
        // Validate domain inputs for production clone apps
        if (isTargetProduction && checkResult) {
            for (const res of checkResult.resources) {
                if (res.type === 'application' && res.mode === 'clone') {
                    const domain = domainConfigs[res.uuid];
                    if (!domain || domain.trim() === '') {
                        setError(`Please enter a domain for "${res.name}".`);
                        return;
                    }
                }
            }
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

        // Migrate each selected resource
        const migrateResource = async (type: 'application' | 'database' | 'service', uuid: string, name: string, index: number) => {
            try {
                const resourceCheck = getResourceCheck(uuid);
                const mode: MigrationMode = resourceCheck?.mode || 'clone';

                const migrationOptions: Record<string, unknown> = {
                    ...options,
                    mode,
                    // For databases going to production, force config_only
                    config_only: type === 'database' && isTargetProduction ? true : false,
                };

                // Add FQDN for production clone apps
                if (isTargetProduction && mode === 'clone' && type === 'application') {
                    const domain = domainConfigs[uuid];
                    if (domain) {
                        migrationOptions.fqdn = domain;
                    }
                }

                const response = await axios.post('/api/v1/migrations', {
                    source_type: type,
                    source_uuid: uuid,
                    target_environment_id: targetEnvId,
                    target_server_id: targetServId,
                    options: migrationOptions,
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
        // Order: databases → services → applications (dependencies first)
        let idx = 0;

        for (const uuid of selectedResources.databases) {
            const db = databases.find(d => d.uuid === uuid);
            if (db) {
                allResults.push({ resource_type: 'database', resource_name: db.name, status: 'pending' });
            }
        }
        for (const uuid of selectedResources.services) {
            const svc = services.find(s => s.uuid === uuid);
            if (svc) {
                allResults.push({ resource_type: 'service', resource_name: svc.name, status: 'pending' });
            }
        }
        for (const uuid of selectedResources.applications) {
            const app = applications.find(a => a.uuid === uuid);
            if (app) {
                allResults.push({ resource_type: 'application', resource_name: app.name, status: 'pending' });
            }
        }
        setResults([...allResults]);

        // 1. Migrate databases first (dependencies)
        idx = 0;
        for (const uuid of selectedResources.databases) {
            const db = databases.find(d => d.uuid === uuid);
            if (db) {
                await migrateResource('database', uuid, db.name, idx);
                idx++;
            }
        }

        // 2. Migrate services second
        for (const uuid of selectedResources.services) {
            const svc = services.find(s => s.uuid === uuid);
            if (svc) {
                await migrateResource('service', uuid, svc.name, idx);
                idx++;
            }
        }

        // 3. Migrate applications last (depend on databases/services)
        for (const uuid of selectedResources.applications) {
            const app = applications.find(a => a.uuid === uuid);
            if (app) {
                await migrateResource('application', uuid, app.name, idx);
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
            case 'validate':
                return 'Validation Results';
            case 'review':
                return 'Review Changes';
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
            case 'validate':
                return 'Some checks found issues that need your attention.';
            case 'review':
                return 'Review what will happen for each resource.';
            case 'confirm':
                return `You are about to migrate ${selectedCount} resource(s).`;
            case 'progress':
                return anyRequiresApproval
                    ? 'Some migrations require admin approval before execution.'
                    : 'All migrations have been initiated.';
        }
    };

    const selectedEnvName = targets?.target_environments.find((e: any) => e.id === parseInt(selectedEnvironmentId, 10))?.name || '';
    const selectedServerName = targets?.servers.find((s: { id: number; name: string; ip: string }) => s.id === parseInt(selectedServerId, 10))?.name || '';

    if (!environment) return null;

    return (
        <Modal
            isOpen={open}
            onClose={handleClose}
            title={getStepTitle()}
            description={getStepDescription()}
            size="lg"
        >
            {/* Step 1: Configure */}
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
                            options={(targets.target_environments as any).map((env: { id: number; name: string; type: string }) => ({
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
                            options={targets.servers.map((server: { id: number; name: string; ip: string }) => ({
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
                            {/* Hide copy volumes for production targets */}
                            {!isTargetProduction && (
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
                            )}
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="auto-deploy"
                                    checked={options.auto_deploy}
                                    onCheckedChange={(checked) => setOptions({ ...options, auto_deploy: !!checked })}
                                />
                                <label htmlFor="auto-deploy" className="text-sm cursor-pointer">
                                    Auto-deploy resources after migration
                                </label>
                            </div>
                        </div>
                    </div>

                    {error && <Alert variant="danger">{error}</Alert>}

                    {/* Actions */}
                    <div className="flex justify-end gap-3">
                        <Button variant="outline" onClick={handleClose}>
                            Cancel
                        </Button>
                        <Button onClick={handleConfigure} disabled={selectedCount === 0 || isValidating}>
                            {isValidating ? (
                                <>
                                    <Spinner size="sm" className="mr-2" />
                                    Checking...
                                </>
                            ) : (
                                <>
                                    Next
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </>
                            )}
                        </Button>
                    </div>
                </div>
            )}

            {/* Step 2: Validation Results (only shown if there are errors/warnings) */}
            {step === 'validate' && checkResult && (
                <div className="space-y-6">
                    <div className="space-y-3">
                        {checkResult.resources.map((res) => (
                            <div key={res.uuid} className="rounded-lg border border-border p-4 space-y-2">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        {res.type === 'application' && <Box className="h-4 w-4" />}
                                        {res.type === 'database' && <Database className="h-4 w-4" />}
                                        {res.type === 'service' && <Layers className="h-4 w-4" />}
                                        <span className="font-medium text-sm">{res.name}</span>
                                    </div>
                                    <ModeLabel mode={res.mode} />
                                </div>

                                {/* Errors */}
                                {res.pre_checks?.errors?.length > 0 && (
                                    <div className="space-y-1">
                                        {res.pre_checks.errors.map((err, i) => (
                                            <div key={i} className="flex items-start gap-2 text-sm text-destructive">
                                                <XCircle className="h-4 w-4 mt-0.5 shrink-0" />
                                                <span>{err}</span>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                {/* Warnings */}
                                {res.pre_checks?.warnings?.length > 0 && (
                                    <div className="space-y-1">
                                        {res.pre_checks.warnings.map((warn, i) => (
                                            <div key={i} className="flex items-start gap-2 text-sm text-warning">
                                                <AlertTriangle className="h-4 w-4 mt-0.5 shrink-0" />
                                                <span>{warn}</span>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                {/* All clear */}
                                {res.pre_checks?.pass && !res.pre_checks?.warnings?.length && (
                                    <div className="flex items-center gap-2 text-sm text-success">
                                        <CheckCircle2 className="h-4 w-4" />
                                        All checks passed
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>

                    {/* Block continue if any critical errors */}
                    {checkResult.resources.some(r => r.pre_checks && !r.pre_checks.pass) && (
                        <Alert variant="danger">
                            <XCircle className="h-4 w-4" />
                            <div>
                                <p className="font-medium">Critical issues found</p>
                                <p className="text-sm">Fix the errors above before proceeding.</p>
                            </div>
                        </Alert>
                    )}

                    <div className="flex justify-end gap-3">
                        <Button variant="outline" onClick={() => setStep('configure')}>
                            Back
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => runValidation()}
                            disabled={isValidating}
                        >
                            <RefreshCw className={`mr-2 h-4 w-4 ${isValidating ? 'animate-spin' : ''}`} />
                            Re-check
                        </Button>
                        <Button
                            onClick={handleValidationContinue}
                            disabled={checkResult.resources.some(r => r.pre_checks && !r.pre_checks.pass)}
                        >
                            Continue with warnings
                            <ArrowRight className="ml-2 h-4 w-4" />
                        </Button>
                    </div>
                </div>
            )}

            {/* Step 3: Review Changes + Domain Config */}
            {step === 'review' && checkResult && (
                <div className="space-y-6">
                    {/* Per-resource review */}
                    <div className="space-y-4 max-h-[50vh] overflow-y-auto">
                        {checkResult.resources.map((res) => (
                            <div key={res.uuid} className="rounded-lg border border-border p-4 space-y-3">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        {res.type === 'application' && <Box className="h-4 w-4" />}
                                        {res.type === 'database' && <Database className="h-4 w-4" />}
                                        {res.type === 'service' && <Layers className="h-4 w-4" />}
                                        <span className="font-medium text-sm">{res.name}</span>
                                    </div>
                                    <ModeLabel mode={res.mode} />
                                </div>

                                {/* Clone summary */}
                                {res.mode === 'clone' && res.preview && (
                                    <div className="text-sm text-foreground-muted space-y-1">
                                        <div className="flex items-center gap-2">
                                            <Plus className="h-3 w-3" />
                                            New {res.preview.summary.resource_type} will be created
                                        </div>
                                        {res.preview.summary.env_vars_count !== undefined && res.preview.summary.env_vars_count > 0 && (
                                            <div className="ml-5">{res.preview.summary.env_vars_count} environment variable(s) will be copied</div>
                                        )}
                                        {res.preview.summary.persistent_volumes_count !== undefined && res.preview.summary.persistent_volumes_count > 0 && (
                                            <div className="ml-5">{res.preview.summary.persistent_volumes_count} volume(s) will be configured</div>
                                        )}
                                    </div>
                                )}

                                {/* Promote diff */}
                                {res.mode === 'promote' && res.preview && (
                                    <div className="text-sm space-y-2">
                                        {res.preview.attribute_diff && Object.keys(res.preview.attribute_diff).length > 0 ? (
                                            <div className="space-y-1">
                                                <p className="text-xs font-medium text-foreground-muted">Configuration changes:</p>
                                                {Object.entries(res.preview.attribute_diff).map(([field, diff]) => (
                                                    <div key={field} className="ml-2 flex items-center gap-2 text-xs">
                                                        <span className="font-mono text-warning">{field}</span>
                                                        <span className="text-foreground-muted">→</span>
                                                        <span className="text-success truncate max-w-[200px]">{String(diff.to)}</span>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <div className="flex items-center gap-2 text-foreground-muted">
                                                <Info className="h-3 w-3" />
                                                No configuration changes detected
                                            </div>
                                        )}

                                        {/* Rewire preview */}
                                        {res.preview.rewire_preview && res.preview.rewire_preview.length > 0 && (
                                            <div className="space-y-1">
                                                <p className="text-xs font-medium text-foreground-muted">Connections to rewire:</p>
                                                {res.preview.rewire_preview.map((rw) => (
                                                    <div key={rw.key} className="ml-2 text-xs">
                                                        <span className="font-mono">{rw.key}</span>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                )}

                                {/* Domain input for production clone applications */}
                                {isTargetProduction && res.type === 'application' && res.mode === 'clone' && (
                                    <div className="border-t border-border pt-3 space-y-2">
                                        <div className="flex items-center gap-2">
                                            <Globe className="h-4 w-4 text-primary" />
                                            <p className="text-sm font-medium">Production Domain</p>
                                        </div>
                                        <p className="text-xs text-foreground-muted">
                                            Enter a subdomain (e.g., myapp.saturn.ac) or custom domain (e.g., app.company.com)
                                        </p>
                                        <Input
                                            value={domainConfigs[res.uuid] || ''}
                                            onChange={(e: React.ChangeEvent<HTMLInputElement>) =>
                                                setDomainConfigs(prev => ({ ...prev, [res.uuid]: e.target.value }))
                                            }
                                            placeholder="myapp.saturn.ac"
                                        />
                                    </div>
                                )}

                                {/* Show existing FQDN for promote apps */}
                                {isTargetProduction && res.type === 'application' && res.mode === 'promote' && res.target_fqdn && (
                                    <div className="flex items-center gap-2 text-sm text-foreground-muted">
                                        <Globe className="h-3 w-3" />
                                        Domain: <span className="font-mono text-foreground">{res.target_fqdn}</span>
                                        <span className="text-xs">(unchanged)</span>
                                    </div>
                                )}

                                {/* Production database protection */}
                                {isTargetProduction && res.type === 'database' && (
                                    <div className="flex items-center gap-2 text-sm text-warning">
                                        <Shield className="h-3 w-3" />
                                        Config-only mode (production data protected)
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>

                    {error && <Alert variant="danger">{error}</Alert>}

                    <div className="flex justify-end gap-3">
                        <Button variant="outline" onClick={() => setStep('configure')}>
                            Back
                        </Button>
                        <Button onClick={handleReviewContinue}>
                            Continue
                            <ArrowRight className="ml-2 h-4 w-4" />
                        </Button>
                    </div>
                </div>
            )}

            {/* Step 4: Confirm */}
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

                    {/* Resources to migrate with mode badges */}
                    <div className="space-y-2">
                        <p className="text-sm font-medium">Resources to migrate ({selectedCount})</p>
                        <div className="max-h-48 space-y-1 overflow-y-auto">
                            {checkResult?.resources.map(res => (
                                <div key={res.uuid} className="flex items-center gap-2 text-sm">
                                    {res.type === 'application' && <Box className="h-4 w-4 text-foreground-muted" />}
                                    {res.type === 'database' && <Database className="h-4 w-4 text-foreground-muted" />}
                                    {res.type === 'service' && <Layers className="h-4 w-4 text-foreground-muted" />}
                                    <span>{res.name}</span>
                                    <ModeLabel mode={res.mode} />
                                    {isTargetProduction && res.type === 'database' && (
                                        <span className="text-xs text-warning">(config only)</span>
                                    )}
                                    {isTargetProduction && res.type === 'application' && res.mode === 'clone' && domainConfigs[res.uuid] && (
                                        <span className="text-xs text-primary">{domainConfigs[res.uuid]}</span>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Warning for production */}
                    {isTargetProduction && (
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

                    {error && <Alert variant="danger">{error}</Alert>}

                    {/* Actions */}
                    <div className="flex justify-end gap-3">
                        <Button variant="outline" onClick={() => setStep('review')} disabled={isSubmitting}>
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

            {/* Step 5: Progress */}
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

/**
 * Mode label component — shows "New" or "Update" badge
 */
function ModeLabel({ mode }: { mode: MigrationMode }) {
    if (mode === 'clone') {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-success/10 px-2 py-0.5 text-xs font-medium text-success">
                <Plus className="h-3 w-3" />
                New
            </span>
        );
    }
    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
            <RefreshCw className="h-3 w-3" />
            Update
        </span>
    );
}
