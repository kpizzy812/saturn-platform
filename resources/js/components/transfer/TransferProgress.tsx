import { ArrowRightLeft, Loader2, CheckCircle, XCircle, AlertCircle, Clock } from 'lucide-react';
import type { TransferStatus } from '@/types';

interface TransferProgressProps {
    status: TransferStatus;
    progress: number;
    currentStep?: string;
    estimatedTimeRemaining?: string;
    size?: 'sm' | 'default';
}

const statusConfig: Record<TransferStatus, { icon: typeof Clock; color: string; bgColor: string; label: string }> = {
    pending: { icon: Clock, color: 'text-yellow-500', bgColor: 'bg-yellow-500/10', label: 'Pending' },
    preparing: { icon: Loader2, color: 'text-blue-500', bgColor: 'bg-blue-500/10', label: 'Preparing' },
    transferring: { icon: ArrowRightLeft, color: 'text-blue-500', bgColor: 'bg-blue-500/10', label: 'Transferring' },
    restoring: { icon: Loader2, color: 'text-purple-500', bgColor: 'bg-purple-500/10', label: 'Restoring' },
    completed: { icon: CheckCircle, color: 'text-green-500', bgColor: 'bg-green-500/10', label: 'Completed' },
    failed: { icon: XCircle, color: 'text-red-500', bgColor: 'bg-red-500/10', label: 'Failed' },
    cancelled: { icon: AlertCircle, color: 'text-gray-500', bgColor: 'bg-gray-500/10', label: 'Cancelled' },
};

export function TransferProgress({
    status,
    progress,
    currentStep,
    estimatedTimeRemaining,
    size = 'default',
}: TransferProgressProps) {
    const config = statusConfig[status] || statusConfig.pending;
    const StatusIcon = config.icon;
    const isAnimated = ['preparing', 'transferring', 'restoring'].includes(status);
    const isComplete = status === 'completed';
    const isFailed = status === 'failed';

    if (size === 'sm') {
        return (
            <div className="flex items-center gap-2">
                <StatusIcon className={`h-4 w-4 ${config.color} ${isAnimated ? 'animate-spin' : ''}`} />
                <div className="flex-1">
                    <div className="flex items-center justify-between text-xs">
                        <span className={config.color}>{config.label}</span>
                        {isAnimated && <span className="text-foreground-muted">{progress}%</span>}
                    </div>
                    {isAnimated && (
                        <div className="mt-1 h-1 bg-background-tertiary rounded-full overflow-hidden">
                            <div
                                className={`h-full transition-all duration-300 ${
                                    isFailed ? 'bg-red-500' : 'bg-primary'
                                }`}
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                    )}
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-3">
            <div className="flex items-center gap-3">
                <div className={`rounded-full p-2 ${config.bgColor}`}>
                    <StatusIcon className={`h-5 w-5 ${config.color} ${isAnimated ? 'animate-spin' : ''}`} />
                </div>
                <div className="flex-1">
                    <div className="flex items-center justify-between">
                        <span className={`font-medium ${config.color}`}>{config.label}</span>
                        {isAnimated && (
                            <span className="text-sm text-foreground-muted">{progress}%</span>
                        )}
                    </div>
                    {currentStep && isAnimated && (
                        <p className="text-sm text-foreground-muted">{currentStep}</p>
                    )}
                </div>
            </div>

            {isAnimated && (
                <>
                    <div className="h-2 bg-background-tertiary rounded-full overflow-hidden">
                        <div
                            className={`h-full transition-all duration-300 ${
                                isFailed ? 'bg-red-500' : isComplete ? 'bg-green-500' : 'bg-primary'
                            }`}
                            style={{ width: `${progress}%` }}
                        />
                    </div>
                    {estimatedTimeRemaining && (
                        <p className="text-xs text-foreground-muted">
                            Estimated time remaining: {estimatedTimeRemaining}
                        </p>
                    )}
                </>
            )}
        </div>
    );
}

// Inline progress indicator for lists
export function TransferProgressInline({
    status,
    progress,
}: {
    status: TransferStatus;
    progress: number;
}) {
    const config = statusConfig[status] || statusConfig.pending;
    const StatusIcon = config.icon;
    const isAnimated = ['preparing', 'transferring', 'restoring'].includes(status);

    return (
        <div className="flex items-center gap-2">
            <StatusIcon className={`h-4 w-4 ${config.color} ${isAnimated ? 'animate-spin' : ''}`} />
            {isAnimated ? (
                <div className="flex items-center gap-2">
                    <div className="h-1.5 w-16 bg-background-tertiary rounded-full overflow-hidden">
                        <div
                            className="h-full bg-primary transition-all duration-300"
                            style={{ width: `${progress}%` }}
                        />
                    </div>
                    <span className="text-xs text-foreground-muted">{progress}%</span>
                </div>
            ) : (
                <span className={`text-xs ${config.color}`}>{config.label}</span>
            )}
        </div>
    );
}
