import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Button, Badge } from '@/components/ui';
import { ArrowRightLeft, Clock, CheckCircle, XCircle, AlertCircle, Loader2 } from 'lucide-react';
import { useRealtimeStatus } from '@/hooks/useRealtimeStatus';
import type { ResourceTransfer } from '@/types';
import { formatDistanceToNow } from 'date-fns';

interface Props {
    transfers: {
        data: ResourceTransfer[];
        current_page: number;
        last_page: number;
        total: number;
    };
    statusFilter?: string;
}

const statusConfig: Record<string, { icon: typeof Clock; color: string; label: string }> = {
    pending: { icon: Clock, color: 'bg-yellow-500/10 text-yellow-500', label: 'Pending' },
    preparing: { icon: Loader2, color: 'bg-blue-500/10 text-blue-500', label: 'Preparing' },
    transferring: { icon: ArrowRightLeft, color: 'bg-blue-500/10 text-blue-500', label: 'Transferring' },
    restoring: { icon: Loader2, color: 'bg-purple-500/10 text-purple-500', label: 'Restoring' },
    completed: { icon: CheckCircle, color: 'bg-green-500/10 text-green-500', label: 'Completed' },
    failed: { icon: XCircle, color: 'bg-red-500/10 text-red-500', label: 'Failed' },
    cancelled: { icon: AlertCircle, color: 'bg-gray-500/10 text-gray-500', label: 'Cancelled' },
};

export default function TransfersIndex({ transfers, statusFilter }: Props) {
    // Real-time status updates via WebSocket
    useRealtimeStatus({
        onTransferStatusChange: () => {
            router.reload({ only: ['transfers'] });
        },
    });

    const filterByStatus = (status: string | null) => {
        router.get('/transfers', status ? { status } : {}, { preserveState: true });
    };

    return (
        <AppLayout
            title="Transfers"
            breadcrumbs={[{ label: 'Transfers' }]}
        >
            <div className="mx-auto max-w-6xl">
                {/* Header */}
                <div className="mb-6">
                    <h1 className="text-2xl font-bold text-foreground">Resource Transfers</h1>
                    <p className="text-foreground-muted">Transfer databases between environments and servers</p>
                </div>

                {/* Status Filter */}
                <div className="mb-4 flex flex-wrap gap-2">
                    <Button
                        variant={!statusFilter ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => filterByStatus(null)}
                    >
                        All
                    </Button>
                    {Object.entries(statusConfig).map(([status, config]) => (
                        <Button
                            key={status}
                            variant={statusFilter === status ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => filterByStatus(status)}
                        >
                            {config.label}
                        </Button>
                    ))}
                </div>

                {/* Transfers List */}
                {transfers.data.length === 0 ? (
                    <EmptyState />
                ) : (
                    <div className="space-y-4">
                        {transfers.data.map((transfer) => (
                            <TransferCard key={transfer.id} transfer={transfer} />
                        ))}
                    </div>
                )}

                {/* Pagination */}
                {transfers.last_page > 1 && (
                    <div className="mt-6 flex justify-center gap-2">
                        {Array.from({ length: transfers.last_page }, (_, i) => i + 1).map((page) => (
                            <Button
                                key={page}
                                variant={page === transfers.current_page ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => router.get('/transfers', { page, status: statusFilter })}
                            >
                                {page}
                            </Button>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function TransferCard({ transfer }: { transfer: ResourceTransfer }) {
    const config = statusConfig[transfer.status] || statusConfig.pending;
    const StatusIcon = config.icon;
    const isAnimated = ['preparing', 'transferring', 'restoring'].includes(transfer.status);

    return (
        <Link href={`/transfers/${transfer.uuid}`}>
            <div className="rounded-lg border border-border/50 bg-background-secondary/30 p-4 hover:border-border transition-colors">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className={`rounded-full p-2 ${config.color}`}>
                            <StatusIcon className={`h-4 w-4 ${isAnimated ? 'animate-spin' : ''}`} />
                        </div>
                        <div>
                            <div className="font-medium text-foreground">
                                {transfer.source?.name || 'Unknown'}
                                <span className="mx-2 text-foreground-muted">→</span>
                                {transfer.target_environment?.name || 'Target Environment'}
                            </div>
                            <div className="text-sm text-foreground-muted">
                                {transfer.source_type_name || transfer.source_type?.split('\\').pop()?.replace('Standalone', '')}
                                {' • '}
                                {transfer.mode_label || transfer.transfer_mode}
                                {' • '}
                                {transfer.target_server?.name || 'Unknown Server'}
                            </div>
                        </div>
                    </div>
                    <div className="text-right">
                        <Badge variant="outline" className={config.color}>
                            {config.label}
                        </Badge>
                        <div className="mt-1 text-xs text-foreground-muted">
                            {formatDistanceToNow(new Date(transfer.created_at), { addSuffix: true })}
                        </div>
                    </div>
                </div>

                {/* Progress bar for in-progress transfers */}
                {isAnimated && (
                    <div className="mt-3">
                        <div className="flex items-center justify-between text-xs text-foreground-muted mb-1">
                            <span>{transfer.current_step || 'Processing...'}</span>
                            <span>{transfer.progress}%</span>
                        </div>
                        <div className="h-1.5 bg-background-tertiary rounded-full overflow-hidden">
                            <div
                                className="h-full bg-primary transition-all duration-300"
                                style={{ width: `${transfer.progress}%` }}
                            />
                        </div>
                    </div>
                )}

                {/* Error message for failed transfers */}
                {transfer.status === 'failed' && transfer.error_message && (
                    <div className="mt-3 text-sm text-red-500 truncate">
                        {transfer.error_message}
                    </div>
                )}
            </div>
        </Link>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-border/50 bg-background-secondary/30 py-16">
            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary/50">
                <ArrowRightLeft className="h-8 w-8 text-foreground-muted" />
            </div>
            <h3 className="mt-4 text-lg font-medium text-foreground">No transfers yet</h3>
            <p className="mt-1 text-sm text-foreground-muted text-center max-w-md">
                Transfer databases between environments to migrate data or create backups.
                Start a transfer from any database's page.
            </p>
        </div>
    );
}
