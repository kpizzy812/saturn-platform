import * as React from 'react';
import { useState } from 'react';
import { ArrowRight, Check, X, Clock, Box, Database, Settings2 } from 'lucide-react';
import { Button, Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter, Badge, Modal, ModalFooter, Textarea } from '@/components/ui';
import type { EnvironmentMigration } from '@/types';

interface MigrationApprovalCardProps {
    migration: EnvironmentMigration;
    onApprove: (uuid: string) => Promise<void>;
    onReject: (uuid: string, reason: string) => Promise<void>;
}

export function MigrationApprovalCard({
    migration,
    onApprove,
    onReject,
}: MigrationApprovalCardProps) {
    const [isApproving, setIsApproving] = useState(false);
    const [isRejecting, setIsRejecting] = useState(false);
    const [showRejectDialog, setShowRejectDialog] = useState(false);
    const [rejectReason, setRejectReason] = useState('');
    const [error, setError] = useState<string | null>(null);

    const handleApprove = async () => {
        setIsApproving(true);
        setError(null);
        try {
            await onApprove(migration.uuid);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to approve migration');
        } finally {
            setIsApproving(false);
        }
    };

    const handleReject = async () => {
        if (!rejectReason.trim()) {
            setError('Please provide a reason for rejection');
            return;
        }
        setIsRejecting(true);
        setError(null);
        try {
            await onReject(migration.uuid, rejectReason);
            setShowRejectDialog(false);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to reject migration');
        } finally {
            setIsRejecting(false);
        }
    };

    const getResourceIcon = () => {
        const sourceType = migration.source_type_name || 'Application';
        if (sourceType.includes('PostgreSQL') || sourceType.includes('MySQL') || sourceType.includes('Database')) {
            return Database;
        }
        if (sourceType === 'Service') {
            return Settings2;
        }
        return Box;
    };

    const ResourceIcon = getResourceIcon();
    const sourceName = migration.source?.name || 'Unknown Resource';
    const sourceType = migration.source_type_name || 'Unknown';
    const requestedBy = migration.requested_by_user?.name || 'Unknown User';
    const sourceEnv = migration.source_environment?.name || 'Unknown';
    const targetEnv = migration.target_environment?.name || 'Unknown';
    const createdAt = new Date(migration.created_at).toLocaleString();

    return (
        <>
            <Card>
                <CardHeader>
                    <div className="flex items-start justify-between">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-background-secondary p-2">
                                <ResourceIcon className="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle className="text-lg">{sourceName}</CardTitle>
                                <CardDescription>{sourceType}</CardDescription>
                            </div>
                        </div>
                        <Badge variant="warning">
                            <Clock className="mr-1 h-3 w-3" />
                            Pending Approval
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent className="space-y-4">
                    {/* Migration direction */}
                    <div className="flex items-center justify-center gap-4 py-2">
                        <div className="text-center">
                            <p className="text-sm font-medium text-foreground">{sourceEnv}</p>
                            <p className="text-xs text-foreground-muted">Source</p>
                        </div>
                        <ArrowRight className="h-4 w-4 text-foreground-muted" />
                        <div className="text-center">
                            <p className="text-sm font-medium text-foreground">{targetEnv}</p>
                            <p className="text-xs text-foreground-muted">Target</p>
                        </div>
                    </div>

                    {/* Details */}
                    <div className="rounded-lg border border-border p-3 space-y-2 text-sm">
                        <div className="flex justify-between">
                            <span className="text-foreground-muted">Requested by</span>
                            <span className="text-foreground">{requestedBy}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-foreground-muted">Requested at</span>
                            <span className="text-foreground">{createdAt}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-foreground-muted">Target server</span>
                            <span className="text-foreground">{migration.target_server?.name || 'Unknown'}</span>
                        </div>
                    </div>

                    {/* Options */}
                    {migration.options && (
                        <div className="text-xs text-foreground-muted">
                            Options:{' '}
                            {[
                                migration.options.copy_env_vars && 'Copy Env Vars',
                                migration.options.copy_volumes && 'Copy Volumes',
                                migration.options.update_existing && 'Update Existing',
                                migration.options.config_only && 'Config Only',
                            ]
                                .filter(Boolean)
                                .join(', ') || 'Default'}
                        </div>
                    )}

                    {error && <p className="text-sm text-danger">{error}</p>}
                </CardContent>
                <CardFooter className="flex justify-end gap-2">
                    <Button
                        variant="secondary"
                        onClick={() => setShowRejectDialog(true)}
                        disabled={isApproving || isRejecting}
                    >
                        <X className="mr-2 h-4 w-4" />
                        Reject
                    </Button>
                    <Button onClick={handleApprove} disabled={isApproving || isRejecting} loading={isApproving}>
                        <Check className="mr-2 h-4 w-4" />
                        {isApproving ? 'Approving...' : 'Approve'}
                    </Button>
                </CardFooter>
            </Card>

            {/* Reject Dialog */}
            <Modal
                isOpen={showRejectDialog}
                onClose={() => setShowRejectDialog(false)}
                title="Reject Migration"
                description="Please provide a reason for rejecting this migration request."
            >
                <div className="space-y-4">
                    <Textarea
                        label="Reason"
                        placeholder="Enter the reason for rejection..."
                        value={rejectReason}
                        onChange={(e) => setRejectReason(e.target.value)}
                        rows={3}
                    />
                    {error && <p className="text-sm text-danger">{error}</p>}
                </div>
                <ModalFooter>
                    <Button variant="secondary" onClick={() => setShowRejectDialog(false)} disabled={isRejecting}>
                        Cancel
                    </Button>
                    <Button variant="danger" onClick={handleReject} disabled={isRejecting || !rejectReason.trim()} loading={isRejecting}>
                        {isRejecting ? 'Rejecting...' : 'Reject Migration'}
                    </Button>
                </ModalFooter>
            </Modal>
        </>
    );
}
