import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Modal, ModalFooter } from '@/components/ui/Modal';
import { Button, Badge } from '@/components/ui';
import { Copy, Server, Folder, Loader2, AlertCircle, CheckCircle, Rocket, Box } from 'lucide-react';
import type { Application, Service, Environment, Project } from '@/types';

interface CloneTarget {
    environments: Array<{
        id: number;
        name: string;
        project_name: string;
    }>;
    servers: Array<{
        id: number;
        name: string;
        ip: string;
        environment_id: number;
    }>;
}

interface CloneModalProps {
    isOpen: boolean;
    onClose: () => void;
    resource: Application | Service;
    resourceType: 'application' | 'service';
}

type CloneStep = 'options' | 'target' | 'confirm';

export function CloneModal({ isOpen, onClose, resource, resourceType }: CloneModalProps) {
    const [step, setStep] = useState<CloneStep>('options');
    const [newName, setNewName] = useState('');
    const [copyEnvVars, setCopyEnvVars] = useState(true);
    const [copyVolumes, setCopyVolumes] = useState(true);
    const [copyTags, setCopyTags] = useState(true);
    const [instantDeploy, setInstantDeploy] = useState(false);
    const [targetEnvironmentId, setTargetEnvironmentId] = useState<number | null>(null);
    const [targetServerId, setTargetServerId] = useState<number | null>(null);
    const [targets, setTargets] = useState<CloneTarget | null>(null);
    const [isLoadingTargets, setIsLoadingTargets] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState(false);

    // Fetch available targets when modal opens
    useEffect(() => {
        if (isOpen && !targets) {
            fetchTargets();
        }
    }, [isOpen]);

    // Reset state when modal closes
    useEffect(() => {
        if (!isOpen) {
            setTimeout(() => {
                setStep('options');
                setNewName('');
                setCopyEnvVars(true);
                setCopyVolumes(true);
                setCopyTags(true);
                setInstantDeploy(false);
                setTargetEnvironmentId(null);
                setTargetServerId(null);
                setError(null);
                setSuccess(false);
            }, 300);
        }
    }, [isOpen]);

    const fetchTargets = async () => {
        setIsLoadingTargets(true);
        try {
            const response = await fetch(`/transfers/targets?source_type=${resourceType}&source_uuid=${resource.uuid}`);
            if (response.ok) {
                const data = await response.json();
                setTargets(data);
            } else {
                setError('Failed to load available targets');
            }
        } catch {
            setError('Failed to load available targets');
        } finally {
            setIsLoadingTargets(false);
        }
    };

    // Filter servers based on selected environment
    const availableServers = targets?.servers.filter(
        (s) => s.environment_id === targetEnvironmentId
    ) || [];

    const handleBack = () => {
        if (step === 'target') setStep('options');
        else if (step === 'confirm') setStep('target');
    };

    const handleNext = () => {
        setError(null);

        if (step === 'options') {
            setStep('target');
        } else if (step === 'target') {
            if (!targetEnvironmentId || !targetServerId) {
                setError('Please select a target environment and server');
                return;
            }
            setStep('confirm');
        }
    };

    const handleSubmit = async () => {
        if (!targetEnvironmentId || !targetServerId) {
            setError('Target not selected');
            return;
        }

        setIsSubmitting(true);
        setError(null);

        try {
            // Create the clone transfer
            const response = await fetch('/transfers', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    source_type: resourceType,
                    source_uuid: resource.uuid,
                    target_environment_id: targetEnvironmentId,
                    target_server_id: targetServerId,
                    transfer_mode: 'clone',
                    transfer_options: {
                        new_name: newName || null,
                        copy_env_vars: copyEnvVars,
                        copy_volumes: copyVolumes,
                        copy_tags: copyTags,
                        instant_deploy: instantDeploy,
                    },
                }),
            });

            if (response.ok) {
                const data = await response.json();
                setSuccess(true);
                setTimeout(() => {
                    onClose();
                    // Redirect based on whether approval is required
                    if (data.requires_approval) {
                        router.visit('/approvals');
                    } else if (data.uuid) {
                        router.visit(`/transfers/${data.uuid}`);
                    } else {
                        router.visit('/transfers');
                    }
                }, 1500);
            } else {
                const errorData = await response.json();
                setError(errorData.message || 'Failed to start clone');
            }
        } catch {
            setError('Failed to start clone');
        } finally {
            setIsSubmitting(false);
        }
    };

    const resourceIcon = resourceType === 'application' ? (
        <Rocket className="h-5 w-5 text-primary" />
    ) : (
        <Box className="h-5 w-5 text-primary" />
    );

    const resourceLabel = resourceType === 'application' ? 'Application' : 'Service';

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title={`Clone ${resourceLabel}`}
            size="lg"
        >
            {success ? (
                <div className="flex flex-col items-center justify-center py-8">
                    <CheckCircle className="h-12 w-12 text-success mb-4" />
                    <p className="text-lg font-medium">Clone submitted successfully!</p>
                    <p className="text-sm text-foreground-muted mt-2">
                        Redirecting...
                    </p>
                </div>
            ) : (
                <>
                    {/* Step indicator */}
                    <div className="flex items-center justify-center gap-2 mb-6">
                        {['options', 'target', 'confirm'].map((s, i) => (
                            <div key={s} className="flex items-center">
                                <div
                                    className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium ${
                                        step === s
                                            ? 'bg-primary text-white'
                                            : i < ['options', 'target', 'confirm'].indexOf(step)
                                            ? 'bg-success text-white'
                                            : 'bg-muted text-foreground-muted'
                                    }`}
                                >
                                    {i + 1}
                                </div>
                                {i < 2 && (
                                    <div
                                        className={`w-12 h-0.5 ${
                                            i < ['options', 'target', 'confirm'].indexOf(step)
                                                ? 'bg-success'
                                                : 'bg-muted'
                                        }`}
                                    />
                                )}
                            </div>
                        ))}
                    </div>

                    {/* Source info */}
                    <div className="mb-6 p-4 rounded-lg bg-muted/30 border border-border">
                        <div className="flex items-center gap-3">
                            {resourceIcon}
                            <div>
                                <p className="font-medium">{resource.name}</p>
                                <p className="text-sm text-foreground-muted">
                                    Source {resourceLabel}
                                </p>
                            </div>
                        </div>
                    </div>

                    {error && (
                        <div className="mb-4 p-3 rounded-lg bg-destructive/10 text-destructive flex items-center gap-2">
                            <AlertCircle className="h-4 w-4" />
                            {error}
                        </div>
                    )}

                    {/* Options step */}
                    {step === 'options' && (
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium mb-2">
                                    New Name (optional)
                                </label>
                                <input
                                    type="text"
                                    value={newName}
                                    onChange={(e) => setNewName(e.target.value)}
                                    placeholder={`${resource.name} (Clone)`}
                                    className="w-full px-3 py-2 rounded-md border border-border bg-background focus:outline-none focus:ring-2 focus:ring-primary"
                                />
                                <p className="text-xs text-foreground-muted mt-1">
                                    Leave empty to use "{resource.name} (Clone)"
                                </p>
                            </div>

                            <div className="space-y-3">
                                <label className="flex items-center gap-3 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={copyEnvVars}
                                        onChange={(e) => setCopyEnvVars(e.target.checked)}
                                        className="w-4 h-4 rounded border-border"
                                    />
                                    <div>
                                        <span className="font-medium">Copy Environment Variables</span>
                                        <p className="text-sm text-foreground-muted">
                                            Include all environment variables in the clone
                                        </p>
                                    </div>
                                </label>

                                <label className="flex items-center gap-3 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={copyVolumes}
                                        onChange={(e) => setCopyVolumes(e.target.checked)}
                                        className="w-4 h-4 rounded border-border"
                                    />
                                    <div>
                                        <span className="font-medium">Copy Volume Configuration</span>
                                        <p className="text-sm text-foreground-muted">
                                            Include persistent storage configuration (not data)
                                        </p>
                                    </div>
                                </label>

                                <label className="flex items-center gap-3 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={copyTags}
                                        onChange={(e) => setCopyTags(e.target.checked)}
                                        className="w-4 h-4 rounded border-border"
                                    />
                                    <div>
                                        <span className="font-medium">Copy Tags</span>
                                        <p className="text-sm text-foreground-muted">
                                            Apply the same tags to the cloned resource
                                        </p>
                                    </div>
                                </label>

                                {resourceType === 'application' && (
                                    <label className="flex items-center gap-3 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={instantDeploy}
                                            onChange={(e) => setInstantDeploy(e.target.checked)}
                                            className="w-4 h-4 rounded border-border"
                                        />
                                        <div>
                                            <span className="font-medium">Deploy After Clone</span>
                                            <p className="text-sm text-foreground-muted">
                                                Automatically trigger deployment after cloning
                                            </p>
                                        </div>
                                    </label>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Target step */}
                    {step === 'target' && (
                        <div className="space-y-4">
                            {isLoadingTargets ? (
                                <div className="flex items-center justify-center py-8">
                                    <Loader2 className="h-6 w-6 animate-spin text-primary" />
                                    <span className="ml-2">Loading available targets...</span>
                                </div>
                            ) : (
                                <>
                                    <div>
                                        <label className="block text-sm font-medium mb-2">
                                            <Folder className="inline h-4 w-4 mr-1" />
                                            Target Environment
                                        </label>
                                        <select
                                            value={targetEnvironmentId || ''}
                                            onChange={(e) => {
                                                setTargetEnvironmentId(Number(e.target.value) || null);
                                                setTargetServerId(null);
                                            }}
                                            className="w-full px-3 py-2 rounded-md border border-border bg-background focus:outline-none focus:ring-2 focus:ring-primary"
                                        >
                                            <option value="">Select environment...</option>
                                            {targets?.environments.map((env) => (
                                                <option key={env.id} value={env.id}>
                                                    {env.project_name} / {env.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    {targetEnvironmentId && (
                                        <div>
                                            <label className="block text-sm font-medium mb-2">
                                                <Server className="inline h-4 w-4 mr-1" />
                                                Target Server
                                            </label>
                                            <select
                                                value={targetServerId || ''}
                                                onChange={(e) => setTargetServerId(Number(e.target.value) || null)}
                                                className="w-full px-3 py-2 rounded-md border border-border bg-background focus:outline-none focus:ring-2 focus:ring-primary"
                                            >
                                                <option value="">Select server...</option>
                                                {availableServers.map((server) => (
                                                    <option key={server.id} value={server.id}>
                                                        {server.name} ({server.ip})
                                                    </option>
                                                ))}
                                            </select>
                                            {availableServers.length === 0 && (
                                                <p className="text-sm text-warning mt-1">
                                                    No servers available in this environment
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </>
                            )}
                        </div>
                    )}

                    {/* Confirm step */}
                    {step === 'confirm' && (
                        <div className="space-y-4">
                            <div className="p-4 rounded-lg bg-muted/30 border border-border">
                                <h4 className="font-medium mb-3">Clone Summary</h4>
                                <div className="space-y-2 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-foreground-muted">Source:</span>
                                        <span className="font-medium">{resource.name}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-foreground-muted">New Name:</span>
                                        <span className="font-medium">
                                            {newName || `${resource.name} (Clone)`}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-foreground-muted">Target Environment:</span>
                                        <span className="font-medium">
                                            {targets?.environments.find(e => e.id === targetEnvironmentId)?.name}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-foreground-muted">Target Server:</span>
                                        <span className="font-medium">
                                            {targets?.servers.find(s => s.id === targetServerId)?.name}
                                        </span>
                                    </div>
                                </div>

                                <div className="mt-4 pt-4 border-t border-border">
                                    <p className="text-sm text-foreground-muted mb-2">Options:</p>
                                    <div className="flex flex-wrap gap-2">
                                        {copyEnvVars && (
                                            <Badge variant="secondary">Environment Variables</Badge>
                                        )}
                                        {copyVolumes && (
                                            <Badge variant="secondary">Volume Config</Badge>
                                        )}
                                        {copyTags && (
                                            <Badge variant="secondary">Tags</Badge>
                                        )}
                                        {instantDeploy && (
                                            <Badge variant="primary">Auto Deploy</Badge>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="p-3 rounded-lg bg-warning/10 text-warning-foreground text-sm">
                                <AlertCircle className="inline h-4 w-4 mr-1" />
                                This will create a new {resourceLabel.toLowerCase()} in the target environment.
                                The clone will be in stopped state until deployed.
                            </div>
                        </div>
                    )}

                    <ModalFooter>
                        <div className="flex justify-between w-full">
                            <Button
                                variant="outline"
                                onClick={step === 'options' ? onClose : handleBack}
                                disabled={isSubmitting}
                            >
                                {step === 'options' ? 'Cancel' : 'Back'}
                            </Button>

                            {step === 'confirm' ? (
                                <Button
                                    onClick={handleSubmit}
                                    disabled={isSubmitting}
                                >
                                    {isSubmitting ? (
                                        <>
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            Cloning...
                                        </>
                                    ) : (
                                        <>
                                            <Copy className="mr-2 h-4 w-4" />
                                            Start Clone
                                        </>
                                    )}
                                </Button>
                            ) : (
                                <Button onClick={handleNext}>
                                    Next
                                </Button>
                            )}
                        </div>
                    </ModalFooter>
                </>
            )}
        </Modal>
    );
}
