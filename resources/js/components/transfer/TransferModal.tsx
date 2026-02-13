import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Modal, ModalFooter } from '@/components/ui/Modal';
import { Button, Badge } from '@/components/ui';
import { ArrowRightLeft, Server, Folder, Database, Loader2, AlertCircle, CheckCircle } from 'lucide-react';
import { useTransferTargets } from '@/hooks/useTransfers';
import { useDatabaseStructure } from '@/hooks/useDatabaseStructure';
import { PartialTransferSelector } from './PartialTransferSelector';
import type { TransferMode, StandaloneDatabase } from '@/types';

interface TransferModalProps {
    isOpen: boolean;
    onClose: () => void;
    database: StandaloneDatabase;
}

type TransferStep = 'mode' | 'target' | 'partial' | 'confirm';

const modeDescriptions: Record<TransferMode, { title: string; description: string }> = {
    clone: {
        title: 'Full Clone',
        description: 'Create a complete copy of the database with all data and configuration',
    },
    data_only: {
        title: 'Data Only',
        description: 'Transfer data to an existing database (overwrites target data)',
    },
    partial: {
        title: 'Partial Transfer',
        description: 'Select specific tables, collections, or key patterns to transfer',
    },
};

export function TransferModal({ isOpen, onClose, database }: TransferModalProps) {
    const [step, setStep] = useState<TransferStep>('mode');
    const [mode, setMode] = useState<TransferMode>('clone');
    const [targetEnvironmentId, setTargetEnvironmentId] = useState<number | null>(null);
    const [targetServerId, setTargetServerId] = useState<number | null>(null);
    const [targetDatabaseUuid, setTargetDatabaseUuid] = useState<string | null>(null);
    const [selectedItems, setSelectedItems] = useState<string[]>([]);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState(false);
    const [successRequiresApproval, setSuccessRequiresApproval] = useState(false);

    // Fetch available targets
    const { targets, isLoading: isLoadingTargets } = useTransferTargets({
        sourceType: database.database_type,
        sourceUuid: database.uuid,
    });

    // Fetch database structure for partial transfer
    const { structure, isLoading: isLoadingStructure } = useDatabaseStructure({
        uuid: database.uuid,
        enabled: mode === 'partial' && step === 'partial',
    });

    // Reset state when modal closes
    useEffect(() => {
        if (!isOpen) {
            setTimeout(() => {
                setStep('mode');
                setMode('clone');
                setTargetEnvironmentId(null);
                setTargetServerId(null);
                setTargetDatabaseUuid(null);
                setSelectedItems([]);
                setError(null);
                setSuccess(false);
                setSuccessRequiresApproval(false);
            }, 300);
        }
    }, [isOpen]);

    // Filter servers based on selected environment
    const availableServers = targets?.servers.filter(
        (s) => s.environment_id === targetEnvironmentId
    ) || [];

    // Filter existing databases for data_only mode
    const availableDatabases = targets?.existing_databases.filter(
        (d) => d.server_id === targetServerId
    ) || [];

    const handleBack = () => {
        if (step === 'target') setStep('mode');
        else if (step === 'partial') setStep('target');
        else if (step === 'confirm') setStep(mode === 'partial' ? 'partial' : 'target');
    };

    const handleNext = () => {
        setError(null);

        if (step === 'mode') {
            setStep('target');
        } else if (step === 'target') {
            if (targetEnvironmentId === null || targetServerId === null) {
                setError('Please select a target environment and server');
                return;
            }
            if (mode === 'data_only' && targetDatabaseUuid === null) {
                setError('Please select a target database');
                return;
            }
            if (mode === 'partial') {
                setStep('partial');
            } else {
                setStep('confirm');
            }
        } else if (step === 'partial') {
            if (selectedItems.length === 0) {
                setError('Please select at least one item to transfer');
                return;
            }
            setStep('confirm');
        }
    };

    const handleSubmit = async () => {
        if (targetEnvironmentId === null || targetServerId === null) return;

        setIsSubmitting(true);
        setError(null);

        try {
            const transferOptions: Record<string, string[]> = {};
            if (mode === 'partial' && selectedItems.length > 0) {
                // Determine the key based on database type
                const isRedisLike = ['redis', 'keydb', 'dragonfly'].includes(database.database_type);
                const isMongoDB = database.database_type === 'mongodb';

                if (isRedisLike) {
                    transferOptions.key_patterns = selectedItems;
                } else if (isMongoDB) {
                    transferOptions.collections = selectedItems;
                } else {
                    transferOptions.tables = selectedItems;
                }
            }

            const response = await fetch('/transfers', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    source_uuid: database.uuid,
                    source_type: 'database',
                    target_environment_id: targetEnvironmentId,
                    target_server_id: targetServerId,
                    transfer_mode: mode,
                    target_uuid: targetDatabaseUuid || undefined,
                    transfer_options: Object.keys(transferOptions).length > 0 ? transferOptions : undefined,
                }),
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || 'Failed to create transfer');
            }

            const transfer = await response.json();
            setSuccessRequiresApproval(!!transfer.requires_approval);
            setSuccess(true);

            // Redirect based on whether approval is required
            setTimeout(() => {
                if (transfer.requires_approval) {
                    router.visit('/approvals');
                } else {
                    router.visit(`/transfers/${transfer.uuid}`);
                }
            }, 1500);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to start transfer');
        } finally {
            setIsSubmitting(false);
        }
    };

    const getSelectedEnvironment = () => {
        return targets?.environments.find((e) => e.id === targetEnvironmentId);
    };

    const getSelectedServer = () => {
        return targets?.servers.find((s) => s.id === targetServerId);
    };

    const getSelectedDatabase = () => {
        return targets?.existing_databases.find((d) => d.uuid === targetDatabaseUuid);
    };

    // Success state
    if (success) {
        const needsApproval = successRequiresApproval;
        return (
            <Modal isOpen={isOpen} onClose={onClose} title={needsApproval ? 'Transfer Submitted' : 'Transfer Started'}>
                <div className="flex flex-col items-center py-6 text-center">
                    <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-500/10">
                        <CheckCircle className="h-8 w-8 text-green-500" />
                    </div>
                    <h3 className="mb-2 text-lg font-medium text-foreground">
                        {needsApproval ? 'Transfer Submitted for Approval' : 'Transfer Initiated Successfully'}
                    </h3>
                    <p className="text-sm text-foreground-muted">
                        {needsApproval ? 'Redirecting to approvals page...' : 'Redirecting to transfer details...'}
                    </p>
                </div>
            </Modal>
        );
    }

    return (
        <Modal isOpen={isOpen} onClose={onClose} title="Transfer Database" size="lg">
            <div className="space-y-6">
                {/* Step indicator */}
                <div className="flex items-center justify-between text-sm">
                    <StepIndicator step={1} current={step === 'mode'} completed={step !== 'mode'} label="Mode" />
                    <StepConnector />
                    <StepIndicator step={2} current={step === 'target'} completed={['partial', 'confirm'].includes(step)} label="Target" />
                    {mode === 'partial' && (
                        <>
                            <StepConnector />
                            <StepIndicator step={3} current={step === 'partial'} completed={step === 'confirm'} label="Select" />
                        </>
                    )}
                    <StepConnector />
                    <StepIndicator step={mode === 'partial' ? 4 : 3} current={step === 'confirm'} completed={false} label="Confirm" />
                </div>

                {/* Step content */}
                {step === 'mode' && (
                    <div className="space-y-3">
                        <p className="text-sm text-foreground-muted">
                            Select how you want to transfer <strong>{database.name}</strong>
                        </p>
                        {(Object.entries(modeDescriptions) as [TransferMode, { title: string; description: string }][]).map(
                            ([modeKey, { title, description }]) => (
                                <button
                                    key={modeKey}
                                    onClick={() => setMode(modeKey)}
                                    className={`w-full rounded-lg border p-4 text-left transition-colors ${
                                        mode === modeKey
                                            ? 'border-primary bg-primary/5'
                                            : 'border-border hover:border-border-hover'
                                    }`}
                                >
                                    <div className="flex items-center gap-3">
                                        <div
                                            className={`h-4 w-4 rounded-full border-2 ${
                                                mode === modeKey
                                                    ? 'border-primary bg-primary'
                                                    : 'border-foreground-muted'
                                            }`}
                                        >
                                            {mode === modeKey && (
                                                <div className="h-full w-full rounded-full bg-white scale-50" />
                                            )}
                                        </div>
                                        <div>
                                            <p className="font-medium text-foreground">{title}</p>
                                            <p className="text-sm text-foreground-muted">{description}</p>
                                        </div>
                                    </div>
                                </button>
                            )
                        )}
                    </div>
                )}

                {step === 'target' && (
                    <div className="space-y-4">
                        {isLoadingTargets ? (
                            <div className="flex items-center justify-center py-8">
                                <Loader2 className="h-6 w-6 animate-spin text-foreground-muted" />
                            </div>
                        ) : (
                            <>
                                {/* Environment selection */}
                                <div>
                                    <label className="mb-2 block text-sm font-medium text-foreground">
                                        Target Environment
                                    </label>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {targets?.environments.map((env) => (
                                            <button
                                                key={env.id}
                                                onClick={() => {
                                                    setTargetEnvironmentId(env.id);
                                                    setTargetServerId(null);
                                                    setTargetDatabaseUuid(null);
                                                    setError(null);
                                                }}
                                                className={`flex items-center gap-3 rounded-lg border p-3 text-left transition-colors ${
                                                    targetEnvironmentId === env.id
                                                        ? 'border-primary bg-primary/5'
                                                        : 'border-border hover:border-border-hover'
                                                }`}
                                            >
                                                <Folder className="h-4 w-4 text-foreground-muted" />
                                                <div>
                                                    <p className="font-medium text-foreground">{env.name}</p>
                                                    <p className="text-xs text-foreground-muted">{env.project_name}</p>
                                                </div>
                                            </button>
                                        ))}
                                    </div>
                                    {targets?.environments.length === 0 && (
                                        <p className="text-sm text-foreground-muted">
                                            No environments available
                                        </p>
                                    )}
                                </div>

                                {/* Server selection */}
                                {targetEnvironmentId !== null && (
                                    <div>
                                        <label className="mb-2 block text-sm font-medium text-foreground">
                                            Target Server
                                        </label>
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            {availableServers.map((server) => (
                                                <button
                                                    key={server.id}
                                                    onClick={() => {
                                                        setTargetServerId(server.id);
                                                        setTargetDatabaseUuid(null);
                                                        setError(null);
                                                    }}
                                                    className={`flex items-center gap-3 rounded-lg border p-3 text-left transition-colors ${
                                                        targetServerId === server.id
                                                            ? 'border-primary bg-primary/5'
                                                            : 'border-border hover:border-border-hover'
                                                    }`}
                                                >
                                                    <Server className="h-4 w-4 text-foreground-muted" />
                                                    <div>
                                                        <p className="font-medium text-foreground">{server.name}</p>
                                                        <p className="text-xs text-foreground-muted">{server.ip}</p>
                                                    </div>
                                                </button>
                                            ))}
                                        </div>
                                        {availableServers.length === 0 && (
                                            <p className="text-sm text-foreground-muted">
                                                No servers available in this environment
                                            </p>
                                        )}
                                    </div>
                                )}

                                {/* Existing database selection for data_only mode */}
                                {mode === 'data_only' && targetServerId !== null && (
                                    <div>
                                        <label className="mb-2 block text-sm font-medium text-foreground">
                                            Target Database
                                        </label>
                                        <div className="grid gap-2">
                                            {availableDatabases.map((db) => (
                                                <button
                                                    key={db.uuid}
                                                    onClick={() => { setTargetDatabaseUuid(db.uuid); setError(null); }}
                                                    className={`flex items-center gap-3 rounded-lg border p-3 text-left transition-colors ${
                                                        targetDatabaseUuid === db.uuid
                                                            ? 'border-primary bg-primary/5'
                                                            : 'border-border hover:border-border-hover'
                                                    }`}
                                                >
                                                    <Database className="h-4 w-4 text-foreground-muted" />
                                                    <div>
                                                        <p className="font-medium text-foreground">{db.name}</p>
                                                        <p className="text-xs text-foreground-muted">
                                                            {db.database_type}
                                                        </p>
                                                    </div>
                                                </button>
                                            ))}
                                        </div>
                                        {availableDatabases.length === 0 && (
                                            <p className="text-sm text-foreground-muted">
                                                No compatible databases on this server
                                            </p>
                                        )}
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                )}

                {step === 'partial' && (
                    <PartialTransferSelector
                        databaseType={database.database_type}
                        structure={structure}
                        isLoading={isLoadingStructure}
                        selectedItems={selectedItems}
                        onSelectionChange={setSelectedItems}
                    />
                )}

                {step === 'confirm' && (
                    <div className="space-y-4">
                        <div className="rounded-lg border border-border bg-background-tertiary p-4">
                            <h4 className="mb-3 font-medium text-foreground">Transfer Summary</h4>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-foreground-muted">Source</span>
                                    <span className="font-medium text-foreground">{database.name}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-foreground-muted">Mode</span>
                                    <Badge variant="outline">{modeDescriptions[mode].title}</Badge>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-foreground-muted">Target Environment</span>
                                    <span className="font-medium text-foreground">
                                        {getSelectedEnvironment()?.name}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-foreground-muted">Target Server</span>
                                    <span className="font-medium text-foreground">
                                        {getSelectedServer()?.name}
                                    </span>
                                </div>
                                {mode === 'data_only' && targetDatabaseUuid !== null && (
                                    <div className="flex justify-between">
                                        <span className="text-foreground-muted">Target Database</span>
                                        <span className="font-medium text-foreground">
                                            {getSelectedDatabase()?.name}
                                        </span>
                                    </div>
                                )}
                                {mode === 'partial' && selectedItems.length > 0 && (
                                    <div className="flex justify-between">
                                        <span className="text-foreground-muted">Selected Items</span>
                                        <span className="font-medium text-foreground">
                                            {selectedItems.length} item(s)
                                        </span>
                                    </div>
                                )}
                            </div>
                        </div>

                        {mode === 'data_only' && (
                            <div className="rounded-lg border border-yellow-500/20 bg-yellow-500/5 p-4">
                                <div className="flex gap-3">
                                    <AlertCircle className="h-5 w-5 flex-shrink-0 text-yellow-500" />
                                    <div className="text-sm">
                                        <p className="font-medium text-yellow-500">Warning</p>
                                        <p className="text-foreground-muted">
                                            This will overwrite existing data in the target database. This action
                                            cannot be undone.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* Error message */}
                {error && (
                    <div className="rounded-md border border-red-500/20 bg-red-500/5 p-3 text-sm text-red-500">
                        {error}
                    </div>
                )}
            </div>

            <ModalFooter>
                {step !== 'mode' && (
                    <Button variant="secondary" onClick={handleBack} disabled={isSubmitting}>
                        Back
                    </Button>
                )}
                <Button variant="secondary" onClick={onClose} disabled={isSubmitting}>
                    Cancel
                </Button>
                {step === 'confirm' ? (
                    <Button onClick={handleSubmit} disabled={isSubmitting}>
                        {isSubmitting ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                Starting...
                            </>
                        ) : (
                            <>
                                <ArrowRightLeft className="mr-2 h-4 w-4" />
                                Start Transfer
                            </>
                        )}
                    </Button>
                ) : (
                    <Button onClick={handleNext}>Next</Button>
                )}
            </ModalFooter>
        </Modal>
    );
}

function StepIndicator({
    step,
    current,
    completed,
    label,
}: {
    step: number;
    current: boolean;
    completed: boolean;
    label: string;
}) {
    return (
        <div className="flex flex-col items-center">
            <div
                className={`flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium ${
                    current
                        ? 'bg-primary text-primary-foreground'
                        : completed
                          ? 'bg-green-500 text-white'
                          : 'bg-background-tertiary text-foreground-muted'
                }`}
            >
                {completed ? <CheckCircle className="h-4 w-4" /> : step}
            </div>
            <span className={`mt-1 text-xs ${current ? 'text-foreground' : 'text-foreground-muted'}`}>
                {label}
            </span>
        </div>
    );
}

function StepConnector() {
    return <div className="h-0.5 flex-1 bg-border mx-2" />;
}
