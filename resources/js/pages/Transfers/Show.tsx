import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Button, Badge, Card, CardContent, CardHeader, CardTitle } from '@/components/ui';
import {
    ArrowRightLeft,
    Clock,
    CheckCircle,
    XCircle,
    AlertCircle,
    Loader2,
    ArrowLeft,
    Database,
    Server,
    Folder,
    FileText,
    ChevronDown,
    ChevronUp,
} from 'lucide-react';
import { useRealtimeStatus } from '@/hooks/useRealtimeStatus';
import type { ResourceTransfer } from '@/types';
import { formatDistanceToNow, format } from 'date-fns';
import { useState } from 'react';

interface Props {
    transfer: ResourceTransfer;
}

const statusConfig: Record<string, { icon: typeof Clock; color: string; bgColor: string; label: string }> = {
    pending: { icon: Clock, color: 'text-yellow-500', bgColor: 'bg-yellow-500/10', label: 'Pending' },
    preparing: { icon: Loader2, color: 'text-blue-500', bgColor: 'bg-blue-500/10', label: 'Preparing' },
    transferring: { icon: ArrowRightLeft, color: 'text-blue-500', bgColor: 'bg-blue-500/10', label: 'Transferring' },
    restoring: { icon: Loader2, color: 'text-purple-500', bgColor: 'bg-purple-500/10', label: 'Restoring' },
    completed: { icon: CheckCircle, color: 'text-green-500', bgColor: 'bg-green-500/10', label: 'Completed' },
    failed: { icon: XCircle, color: 'text-red-500', bgColor: 'bg-red-500/10', label: 'Failed' },
    cancelled: { icon: AlertCircle, color: 'text-gray-500', bgColor: 'bg-gray-500/10', label: 'Cancelled' },
};

export default function TransferShow({ transfer }: Props) {
    const [showLogs, setShowLogs] = useState(false);

    // Real-time status updates via WebSocket
    useRealtimeStatus({
        onTransferStatusChange: (data) => {
            if (data.uuid === transfer.uuid) {
                router.reload({ only: ['transfer'] });
            }
        },
    });

    const config = statusConfig[transfer.status] || statusConfig.pending;
    const StatusIcon = config.icon;
    const isAnimated = ['preparing', 'transferring', 'restoring'].includes(transfer.status);
    const canCancel = ['pending', 'preparing', 'transferring'].includes(transfer.status);

    const handleCancel = () => {
        if (confirm('Are you sure you want to cancel this transfer?')) {
            router.post(`/transfers/${transfer.uuid}/cancel`);
        }
    };

    return (
        <AppLayout
            title={`Transfer - ${transfer.source?.name || 'Unknown'}`}
            breadcrumbs={[
                { label: 'Transfers', href: '/transfers' },
                { label: transfer.source?.name || transfer.uuid },
            ]}
        >
            <div className="mx-auto max-w-4xl">
                {/* Back button */}
                <Link href="/transfers" className="inline-flex items-center gap-1 text-sm text-foreground-muted hover:text-foreground mb-4">
                    <ArrowLeft className="h-4 w-4" />
                    Back to transfers
                </Link>

                {/* Header */}
                <div className="mb-6 flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground flex items-center gap-3">
                            <div className={`rounded-full p-2 ${config.bgColor}`}>
                                <StatusIcon className={`h-5 w-5 ${config.color} ${isAnimated ? 'animate-spin' : ''}`} />
                            </div>
                            {transfer.source?.name || 'Unknown Database'}
                            <span className="text-foreground-muted">→</span>
                            {transfer.target_environment?.name || 'Target'}
                        </h1>
                        <p className="mt-1 text-foreground-muted">
                            {transfer.mode_label || transfer.transfer_mode} transfer
                            {' • '}
                            Created {formatDistanceToNow(new Date(transfer.created_at), { addSuffix: true })}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {canCancel && (
                            <Button variant="danger" onClick={handleCancel}>
                                Cancel Transfer
                            </Button>
                        )}
                        <Badge variant="outline" className={`${config.bgColor} ${config.color}`}>
                            {config.label}
                        </Badge>
                    </div>
                </div>

                {/* Progress */}
                {isAnimated && (
                    <Card className="mb-6">
                        <CardContent className="py-4">
                            <div className="flex items-center justify-between text-sm text-foreground-muted mb-2">
                                <span>{transfer.current_step || 'Processing...'}</span>
                                <span>{transfer.formatted_progress || `${transfer.progress}%`}</span>
                            </div>
                            <div className="h-2 bg-background-tertiary rounded-full overflow-hidden">
                                <div
                                    className="h-full bg-primary transition-all duration-300"
                                    style={{ width: `${transfer.progress}%` }}
                                />
                            </div>
                            {transfer.estimated_time_remaining && (
                                <p className="mt-2 text-xs text-foreground-muted">
                                    Estimated time remaining: {transfer.estimated_time_remaining}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Error message */}
                {transfer.status === 'failed' && transfer.error_message && (
                    <Card className="mb-6 border-red-500/50">
                        <CardContent className="py-4">
                            <div className="flex items-start gap-3">
                                <XCircle className="h-5 w-5 text-red-500 shrink-0 mt-0.5" />
                                <div>
                                    <p className="font-medium text-red-500">Transfer Failed</p>
                                    <p className="text-sm text-foreground-muted mt-1">{transfer.error_message}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Details Grid */}
                <div className="grid gap-6 md:grid-cols-2">
                    {/* Source */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <Database className="h-4 w-4" />
                                Source
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <DetailRow label="Database" value={transfer.source?.name || 'Unknown'} />
                            <DetailRow label="Type" value={transfer.source_type_name || transfer.source_type?.split('\\').pop()?.replace('Standalone', '') || 'Unknown'} />
                            {transfer.source?.environment && (
                                <DetailRow label="Environment" value={transfer.source.environment.name} />
                            )}
                        </CardContent>
                    </Card>

                    {/* Target */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <Folder className="h-4 w-4" />
                                Target
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <DetailRow
                                label="Environment"
                                value={transfer.target_environment?.name || 'Unknown'}
                            />
                            <DetailRow
                                label="Server"
                                value={
                                    <span className="flex items-center gap-1">
                                        <Server className="h-3 w-3" />
                                        {transfer.target_server?.name || 'Unknown'}
                                    </span>
                                }
                            />
                            {transfer.target && (
                                <DetailRow label="Target Database" value={transfer.target.name} />
                            )}
                        </CardContent>
                    </Card>

                    {/* Transfer Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <ArrowRightLeft className="h-4 w-4" />
                                Transfer Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <DetailRow label="Mode" value={transfer.mode_label || transfer.transfer_mode} />
                            <DetailRow
                                label="Data Transferred"
                                value={formatBytes(transfer.transferred_bytes)}
                            />
                            {transfer.total_bytes && (
                                <DetailRow label="Total Size" value={formatBytes(transfer.total_bytes)} />
                            )}
                            {transfer.transfer_options && Object.keys(transfer.transfer_options).length > 0 && (
                                <DetailRow
                                    label="Selection"
                                    value={
                                        transfer.transfer_options.tables?.join(', ') ||
                                        transfer.transfer_options.collections?.join(', ') ||
                                        transfer.transfer_options.key_patterns?.join(', ') ||
                                        'Full database'
                                    }
                                />
                            )}
                        </CardContent>
                    </Card>

                    {/* Timeline */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <Clock className="h-4 w-4" />
                                Timeline
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <DetailRow
                                label="Created"
                                value={format(new Date(transfer.created_at), 'PPpp')}
                            />
                            {transfer.started_at && (
                                <DetailRow
                                    label="Started"
                                    value={format(new Date(transfer.started_at), 'PPpp')}
                                />
                            )}
                            {transfer.completed_at && (
                                <DetailRow
                                    label="Completed"
                                    value={format(new Date(transfer.completed_at), 'PPpp')}
                                />
                            )}
                            {transfer.user && (
                                <DetailRow label="Initiated By" value={transfer.user.name} />
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Logs */}
                {transfer.logs && (
                    <Card className="mt-6">
                        <CardHeader
                            className="cursor-pointer"
                            onClick={() => setShowLogs(!showLogs)}
                        >
                            <CardTitle className="text-base flex items-center justify-between">
                                <span className="flex items-center gap-2">
                                    <FileText className="h-4 w-4" />
                                    Logs
                                </span>
                                {showLogs ? (
                                    <ChevronUp className="h-4 w-4" />
                                ) : (
                                    <ChevronDown className="h-4 w-4" />
                                )}
                            </CardTitle>
                        </CardHeader>
                        {showLogs && (
                            <CardContent>
                                <pre className="text-xs bg-background-tertiary rounded-lg p-4 overflow-x-auto max-h-96 overflow-y-auto font-mono">
                                    {transfer.logs}
                                </pre>
                            </CardContent>
                        )}
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}

function DetailRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex justify-between">
            <span className="text-sm text-foreground-muted">{label}</span>
            <span className="text-sm font-medium text-foreground">{value}</span>
        </div>
    );
}

function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}
