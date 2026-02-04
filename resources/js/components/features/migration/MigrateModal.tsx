import * as React from 'react';
import { useState } from 'react';
import { Modal } from '@/components/ui';
import { MigrateConfigureStep } from './MigrateConfigureStep';
import { MigrateConfirmStep } from './MigrateConfirmStep';
import { MigrateProgressStep } from './MigrateProgressStep';
import type {
    EnvironmentMigration,
    EnvironmentMigrationOptions,
    MigrationTargets,
} from '@/types';

type MigrationStep = 'configure' | 'confirm' | 'progress';

interface MigrateModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    sourceType: 'application' | 'service' | 'database';
    sourceUuid: string;
    sourceName: string;
    targets: MigrationTargets | null;
    isLoadingTargets: boolean;
    onMigrate: (data: {
        targetEnvironmentId: number;
        targetServerId: number;
        options: EnvironmentMigrationOptions;
    }) => Promise<{ migration: EnvironmentMigration; requires_approval: boolean }>;
}

export function MigrateModal({
    open,
    onOpenChange,
    sourceType,
    sourceUuid: _sourceUuid,
    sourceName,
    targets,
    isLoadingTargets,
    onMigrate,
}: MigrateModalProps) {
    void _sourceUuid; // Kept for future use
    const [step, setStep] = useState<MigrationStep>('configure');
    const [error, setError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [migration, setMigration] = useState<EnvironmentMigration | null>(null);
    const [requiresApproval, setRequiresApproval] = useState(false);

    // Configuration state
    const [selectedEnvironmentId, setSelectedEnvironmentId] = useState<number | null>(null);
    const [selectedServerId, setSelectedServerId] = useState<number | null>(null);
    // Promote mode: updates config without copying env vars (they're environment-specific)
    const [options, setOptions] = useState<EnvironmentMigrationOptions>({
        mode: 'promote',
        copy_env_vars: false, // Never copy env vars - they're environment-specific
        copy_volumes: true,
        update_existing: true, // Promote always updates existing
        config_only: false,
        rewire_connections: true, // Auto-rewire database/service connections
        auto_deploy: false,
    });

    // Reset state when modal closes
    React.useEffect(() => {
        if (!open) {
            setStep('configure');
            setError(null);
            setIsSubmitting(false);
            setMigration(null);
            setRequiresApproval(false);
            setSelectedEnvironmentId(null);
            setSelectedServerId(null);
            setOptions({
                mode: 'promote',
                copy_env_vars: false,
                copy_volumes: true,
                update_existing: true,
                config_only: false,
                rewire_connections: true,
                auto_deploy: false,
            });
        }
    }, [open]);

    // Auto-select first environment and server when targets load
    React.useEffect(() => {
        if (targets) {
            if (targets.target_environments?.length > 0 && !selectedEnvironmentId) {
                setSelectedEnvironmentId(targets.target_environments[0].id);
            }
            if (targets.servers?.length > 0 && !selectedServerId) {
                setSelectedServerId(targets.servers[0].id);
            }
        }
    }, [targets, selectedEnvironmentId, selectedServerId]);

    const handleConfigure = () => {
        if (!selectedEnvironmentId || !selectedServerId) {
            setError('Please select target environment and server.');
            return;
        }
        setError(null);
        setStep('confirm');
    };

    const handleConfirm = async () => {
        if (!selectedEnvironmentId || !selectedServerId) {
            return;
        }

        setIsSubmitting(true);
        setError(null);

        try {
            const result = await onMigrate({
                targetEnvironmentId: selectedEnvironmentId,
                targetServerId: selectedServerId,
                options,
            });

            setMigration(result.migration);
            setRequiresApproval(result.requires_approval);
            setStep('progress');
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to start migration');
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleClose = () => {
        onOpenChange(false);
    };

    const getStepTitle = () => {
        switch (step) {
            case 'configure':
                return 'Configure Migration';
            case 'confirm':
                return 'Confirm Migration';
            case 'progress':
                return requiresApproval ? 'Awaiting Approval' : 'Migration in Progress';
        }
    };

    const getStepDescription = () => {
        switch (step) {
            case 'configure':
                return `Select target environment and options for migrating "${sourceName}".`;
            case 'confirm':
                return 'Review and confirm the migration settings.';
            case 'progress':
                return requiresApproval
                    ? 'Your migration request has been submitted for approval.'
                    : 'Migration is being executed...';
        }
    };

    return (
        <Modal
            isOpen={open}
            onClose={handleClose}
            title={getStepTitle()}
            description={getStepDescription()}
            size="lg"
        >
            {step === 'configure' && (
                <MigrateConfigureStep
                    sourceType={sourceType}
                    sourceName={sourceName}
                    targets={targets}
                    isLoading={isLoadingTargets}
                    error={error}
                    selectedEnvironmentId={selectedEnvironmentId}
                    selectedServerId={selectedServerId}
                    options={options}
                    onEnvironmentChange={setSelectedEnvironmentId}
                    onServerChange={setSelectedServerId}
                    onOptionsChange={setOptions}
                    onNext={handleConfigure}
                    onCancel={handleClose}
                />
            )}

            {step === 'confirm' && (
                <MigrateConfirmStep
                    sourceType={sourceType}
                    sourceName={sourceName}
                    sourceEnvironment={targets?.source.environment || ''}
                    targetEnvironment={
                        targets?.target_environments.find((e) => e.id === selectedEnvironmentId)?.name || ''
                    }
                    targetServer={targets?.servers.find((s) => s.id === selectedServerId)?.name || ''}
                    options={options}
                    isSubmitting={isSubmitting}
                    error={error}
                    onConfirm={handleConfirm}
                    onBack={() => setStep('configure')}
                    onCancel={handleClose}
                />
            )}

            {step === 'progress' && migration && (
                <MigrateProgressStep
                    migration={migration}
                    requiresApproval={requiresApproval}
                    onClose={handleClose}
                />
            )}
        </Modal>
    );
}
