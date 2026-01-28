import { useState } from 'react';
import { Modal, ModalFooter } from '@/components/ui/Modal';
import { Button } from '@/components/ui';
import { ShieldAlert, Clock, CheckCircle, AlertTriangle } from 'lucide-react';

interface ApprovalRequiredModalProps {
    isOpen: boolean;
    onClose: () => void;
    onRequestApproval: () => Promise<void>;
    environmentName: string;
    environmentType: string;
    applicationName: string;
}

export function ApprovalRequiredModal({
    isOpen,
    onClose,
    onRequestApproval,
    environmentName,
    environmentType,
    applicationName,
}: ApprovalRequiredModalProps) {
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [submitted, setSubmitted] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleRequestApproval = async () => {
        setIsSubmitting(true);
        setError(null);
        try {
            await onRequestApproval();
            setSubmitted(true);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to request approval');
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleClose = () => {
        setSubmitted(false);
        setError(null);
        onClose();
    };

    const getEnvironmentBadgeColor = () => {
        switch (environmentType) {
            case 'production':
                return 'bg-red-500/10 text-red-500 border-red-500/20';
            case 'uat':
                return 'bg-yellow-500/10 text-yellow-500 border-yellow-500/20';
            default:
                return 'bg-blue-500/10 text-blue-500 border-blue-500/20';
        }
    };

    if (submitted) {
        return (
            <Modal isOpen={isOpen} onClose={handleClose} title="Approval Requested">
                <div className="flex flex-col items-center py-6 text-center">
                    <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-500/10">
                        <CheckCircle className="h-8 w-8 text-green-500" />
                    </div>
                    <h3 className="mb-2 text-lg font-medium text-foreground">
                        Request Submitted Successfully
                    </h3>
                    <p className="text-sm text-foreground-muted">
                        Your deployment request for <strong>{applicationName}</strong> to{' '}
                        <strong>{environmentName}</strong> has been submitted for approval.
                    </p>
                    <p className="mt-4 text-sm text-foreground-muted">
                        You will be notified when an administrator approves or rejects your request.
                    </p>
                </div>
                <ModalFooter>
                    <Button onClick={handleClose}>Close</Button>
                </ModalFooter>
            </Modal>
        );
    }

    return (
        <Modal isOpen={isOpen} onClose={handleClose} title="Approval Required" size="lg">
            <div className="space-y-6">
                {/* Warning Header */}
                <div className="flex items-start gap-4 rounded-lg border border-yellow-500/20 bg-yellow-500/5 p-4">
                    <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-yellow-500/10">
                        <ShieldAlert className="h-5 w-5 text-yellow-500" />
                    </div>
                    <div>
                        <h3 className="font-medium text-foreground">
                            This environment requires approval
                        </h3>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Deployments to <strong>{environmentName}</strong> must be approved by a
                            project administrator before they can proceed.
                        </p>
                    </div>
                </div>

                {/* Details */}
                <div className="space-y-3">
                    <div className="flex items-center justify-between rounded-md bg-background-tertiary px-4 py-3">
                        <span className="text-sm text-foreground-muted">Application</span>
                        <span className="text-sm font-medium text-foreground">{applicationName}</span>
                    </div>
                    <div className="flex items-center justify-between rounded-md bg-background-tertiary px-4 py-3">
                        <span className="text-sm text-foreground-muted">Environment</span>
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-medium text-foreground">{environmentName}</span>
                            <span
                                className={`rounded-full border px-2 py-0.5 text-xs font-medium ${getEnvironmentBadgeColor()}`}
                            >
                                {environmentType}
                            </span>
                        </div>
                    </div>
                </div>

                {/* What happens next */}
                <div className="rounded-lg border border-border bg-background-secondary p-4">
                    <h4 className="mb-3 text-sm font-medium text-foreground">What happens next?</h4>
                    <ul className="space-y-2 text-sm text-foreground-muted">
                        <li className="flex items-start gap-2">
                            <Clock className="mt-0.5 h-4 w-4 flex-shrink-0 text-blue-500" />
                            <span>Your deployment request will be sent to project administrators</span>
                        </li>
                        <li className="flex items-start gap-2">
                            <CheckCircle className="mt-0.5 h-4 w-4 flex-shrink-0 text-green-500" />
                            <span>Once approved, the deployment will start automatically</span>
                        </li>
                        <li className="flex items-start gap-2">
                            <AlertTriangle className="mt-0.5 h-4 w-4 flex-shrink-0 text-yellow-500" />
                            <span>If rejected, you will receive a notification with the reason</span>
                        </li>
                    </ul>
                </div>

                {/* Error message */}
                {error && (
                    <div className="rounded-md border border-red-500/20 bg-red-500/5 p-3 text-sm text-red-500">
                        {error}
                    </div>
                )}
            </div>

            <ModalFooter>
                <Button variant="secondary" onClick={handleClose} disabled={isSubmitting}>
                    Cancel
                </Button>
                <Button onClick={handleRequestApproval} disabled={isSubmitting}>
                    {isSubmitting ? 'Requesting...' : 'Request Approval'}
                </Button>
            </ModalFooter>
        </Modal>
    );
}
