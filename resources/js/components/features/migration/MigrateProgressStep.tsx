
import { CheckCircle2, Clock, Loader2, XCircle, AlertCircle } from 'lucide-react';
import { Button, Alert, Progress } from '@/components/ui';
import type { EnvironmentMigration } from '@/types';

interface MigrateProgressStepProps {
    migration: EnvironmentMigration;
    requiresApproval: boolean;
    onClose: () => void;
}

export function MigrateProgressStep({
    migration,
    requiresApproval,
    onClose,
}: MigrateProgressStepProps) {
    if (requiresApproval && migration.status === 'pending') {
        return (
            <div className="space-y-6">
                <div className="flex flex-col items-center justify-center py-8">
                    <div className="rounded-full bg-warning/10 p-3 mb-4">
                        <Clock className="h-8 w-8 text-warning" />
                    </div>
                    <h3 className="text-lg font-semibold text-foreground mb-2">Awaiting Approval</h3>
                    <p className="text-sm text-foreground-muted text-center max-w-sm">
                        Your migration request has been submitted and is pending approval from an admin or owner.
                    </p>
                </div>

                <Alert variant="info">
                    <div className="flex items-start gap-2">
                        <AlertCircle className="h-4 w-4 mt-0.5 flex-shrink-0" />
                        <p className="text-sm">
                            You will be notified when the migration is approved or rejected. You can also check the
                            status in the Approvals page.
                        </p>
                    </div>
                </Alert>

                <div className="flex justify-end">
                    <Button onClick={onClose}>Close</Button>
                </div>
            </div>
        );
    }

    const getStatusIcon = () => {
        switch (migration.status) {
            case 'in_progress':
                return <Loader2 className="h-8 w-8 text-primary animate-spin" />;
            case 'completed':
                return <CheckCircle2 className="h-8 w-8 text-success" />;
            case 'failed':
                return <XCircle className="h-8 w-8 text-danger" />;
            case 'rolled_back':
                return <AlertCircle className="h-8 w-8 text-warning" />;
            default:
                return <Clock className="h-8 w-8 text-foreground-muted" />;
        }
    };

    const getStatusTitle = () => {
        switch (migration.status) {
            case 'in_progress':
                return 'Migration in Progress';
            case 'completed':
                return 'Migration Completed';
            case 'failed':
                return 'Migration Failed';
            case 'rolled_back':
                return 'Migration Rolled Back';
            default:
                return 'Migration Pending';
        }
    };

    const getStatusDescription = () => {
        switch (migration.status) {
            case 'in_progress':
                return migration.current_step || 'Processing...';
            case 'completed':
                return 'Your resource has been successfully migrated to the target environment.';
            case 'failed':
                return migration.error_message || 'An error occurred during migration.';
            case 'rolled_back':
                return 'The migration was rolled back to the previous state.';
            default:
                return 'Waiting to start...';
        }
    };

    const getStatusBgColor = () => {
        switch (migration.status) {
            case 'completed':
                return 'bg-success/10';
            case 'failed':
                return 'bg-danger/10';
            case 'in_progress':
                return 'bg-primary/10';
            default:
                return 'bg-background-secondary';
        }
    };

    return (
        <div className="space-y-6">
            <div className="flex flex-col items-center justify-center py-8">
                <div className={`rounded-full p-3 mb-4 ${getStatusBgColor()}`}>
                    {getStatusIcon()}
                </div>
                <h3 className="text-lg font-semibold text-foreground mb-2">{getStatusTitle()}</h3>
                <p className="text-sm text-foreground-muted text-center max-w-sm">
                    {getStatusDescription()}
                </p>
            </div>

            {migration.status === 'in_progress' && (
                <div className="space-y-2">
                    <div className="flex justify-between text-sm">
                        <span className="text-foreground-muted">Progress</span>
                        <span className="text-foreground">{migration.progress}%</span>
                    </div>
                    <Progress value={migration.progress} variant="success" />
                </div>
            )}

            {migration.status === 'failed' && migration.error_message && (
                <Alert variant="danger">{migration.error_message}</Alert>
            )}

            <div className="flex justify-end">
                <Button onClick={onClose}>
                    {migration.status === 'in_progress' ? 'Run in Background' : 'Close'}
                </Button>
            </div>
        </div>
    );
}
