import { memo, useCallback } from 'react';
import { Handle, Position } from '@xyflow/react';
import { cn } from '@/lib/utils';

interface ComposeServiceNodeData {
    label: string;
    status: string;
    type: string;
    description?: string | null;
    uuid?: string;
    onQuickRestart?: () => void;
    onQuickStop?: () => void;
    onQuickViewLogs?: () => void;
}

// Docker Compose Logo
const ComposeLogo = () => (
    <svg viewBox="0 0 24 24" className="h-6 w-6" fill="currentColor">
        <path d="M13.983 11.078h2.119a.186.186 0 00.186-.185V9.006a.186.186 0 00-.186-.186h-2.119a.185.185 0 00-.185.185v1.888c0 .102.083.185.185.185m-2.954-5.43h2.118a.186.186 0 00.186-.186V3.574a.186.186 0 00-.186-.185h-2.118a.185.185 0 00-.185.185v1.888c0 .102.082.185.185.186m0 2.716h2.118a.187.187 0 00.186-.186V6.29a.186.186 0 00-.186-.185h-2.118a.185.185 0 00-.185.185v1.887c0 .102.082.185.185.186m-2.93 0h2.12a.186.186 0 00.184-.186V6.29a.185.185 0 00-.185-.185H8.1a.185.185 0 00-.185.185v1.887c0 .102.083.185.185.186m-2.964 0h2.119a.186.186 0 00.185-.186V6.29a.185.185 0 00-.185-.185H5.136a.186.186 0 00-.186.185v1.887c0 .102.084.185.186.186m5.893 2.715h2.118a.186.186 0 00.186-.185V9.006a.186.186 0 00-.186-.186h-2.118a.185.185 0 00-.185.185v1.888c0 .102.082.185.185.185m-2.93 0h2.12a.185.185 0 00.184-.185V9.006a.185.185 0 00-.184-.186h-2.12a.185.185 0 00-.184.185v1.888c0 .102.083.185.185.185m-2.964 0h2.119a.185.185 0 00.185-.185V9.006a.185.185 0 00-.184-.186h-2.12a.186.186 0 00-.186.186v1.887c0 .102.084.185.186.185m-2.92 0h2.12a.185.185 0 00.184-.185V9.006a.185.185 0 00-.184-.186h-2.12a.185.185 0 00-.184.185v1.888c0 .102.082.185.185.185M23.763 9.89c-.065-.051-.672-.51-1.954-.51-.338.001-.676.03-1.01.087-.248-1.7-1.653-2.53-1.716-2.566l-.344-.199-.226.327c-.284.438-.49.922-.612 1.43-.23.97-.09 1.882.403 2.661-.595.332-1.55.413-1.744.42H.751a.751.751 0 00-.75.748 11.376 11.376 0 00.692 4.062c.545 1.428 1.355 2.48 2.41 3.124 1.18.723 3.1 1.137 5.275 1.137.983.003 1.963-.086 2.93-.266a12.248 12.248 0 003.823-1.389c.98-.567 1.86-1.288 2.61-2.136 1.252-1.418 1.998-2.997 2.553-4.4h.221c1.372 0 2.215-.549 2.68-1.009.309-.293.55-.65.707-1.046l.098-.288Z"/>
    </svg>
);

// Quick action button component
function QuickActionButton({ onClick, title, children }: { onClick: () => void; title: string; children: React.ReactNode }) {
    const handleClick = useCallback((e: React.MouseEvent) => {
        e.stopPropagation();
        onClick();
    }, [onClick]);

    return (
        <button
            onClick={handleClick}
            title={title}
            className="flex h-7 w-7 items-center justify-center rounded-md bg-background-secondary/90 border border-border text-foreground-muted backdrop-blur-sm transition-all duration-150 hover:bg-[#7c3aed] hover:border-[#7c3aed] hover:text-white hover:scale-110"
        >
            {children}
        </button>
    );
}

// Custom comparison to ensure status changes trigger re-render
const arePropsEqual = (prevProps: { data: ComposeServiceNodeData; selected?: boolean }, nextProps: { data: ComposeServiceNodeData; selected?: boolean }) => {
    return (
        prevProps.data.status === nextProps.data.status &&
        prevProps.data.label === nextProps.data.label &&
        prevProps.selected === nextProps.selected
    );
};

export const ComposeServiceNode = memo(({ data, selected }: { data: ComposeServiceNodeData; selected?: boolean }) => {
    const statusBase = (data.status || '').split(':')[0];
    const isOnline = statusBase === 'running';
    const isStarting = statusBase === 'starting' || statusBase === 'restarting';
    const isError = statusBase === 'exited' || statusBase === 'stopped' || statusBase === 'crashed' || statusBase === 'failed';
    const hasQuickActions = data.onQuickRestart || data.onQuickStop || data.onQuickViewLogs;

    return (
        <>
            <Handle
                type="target"
                position={Position.Left}
                className="!w-3 !h-3 !bg-transparent !border-2 !border-border hover:!border-purple-500 !-left-1.5 transition-colors duration-200"
            />
            <div
                className={cn(
                    'group relative w-[220px] rounded-lg border transition-all duration-200 cursor-pointer',
                    'bg-background-secondary/95 backdrop-blur-xl hover:bg-background-tertiary/95',
                    'hover:-translate-y-0.5 hover:border-purple-500 hover:shadow-[0_0_20px_rgba(168,85,247,0.3)]',
                    selected
                        ? 'border-purple-500 shadow-[0_0_15px_rgba(168,85,247,0.4)]'
                        : 'border-border'
                )}
            >
                {/* Quick Actions - visible on hover */}
                {hasQuickActions && (
                    <div className="absolute -top-3.5 right-2 z-10 flex items-center gap-1 opacity-100 translate-y-0 md:opacity-0 md:translate-y-1 transition-all duration-200 md:group-hover:opacity-100 md:group-hover:translate-y-0">
                        {data.onQuickRestart && (
                            <QuickActionButton onClick={data.onQuickRestart} title="Restart">
                                {/* Restart icon */}
                                <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M21 2v6h-6"/>
                                    <path d="M3 12a9 9 0 0 1 15-6.7L21 8"/>
                                    <path d="M3 22v-6h6"/>
                                    <path d="M21 12a9 9 0 0 1-15 6.7L3 16"/>
                                </svg>
                            </QuickActionButton>
                        )}
                        {data.onQuickStop && (
                            <QuickActionButton onClick={data.onQuickStop} title="Stop">
                                {/* Stop icon */}
                                <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <rect x="6" y="6" width="12" height="12" rx="2"/>
                                </svg>
                            </QuickActionButton>
                        )}
                        {data.onQuickViewLogs && (
                            <QuickActionButton onClick={data.onQuickViewLogs} title="View Logs">
                                {/* Terminal icon */}
                                <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <polyline points="4 17 10 11 4 5"/>
                                    <line x1="12" y1="19" x2="20" y2="19"/>
                                </svg>
                            </QuickActionButton>
                        )}
                    </div>
                )}

                {/* Header with Logo */}
                <div className="p-4 pb-3">
                    <div className="flex items-start gap-3">
                        <div className="flex-shrink-0 w-10 h-10 rounded-lg bg-purple-500/20 flex items-center justify-center text-purple-400 transition-all duration-200 group-hover:bg-purple-500/30">
                            <ComposeLogo />
                        </div>
                        <div className="flex-1 min-w-0 pt-0.5">
                            <h3 className="font-medium text-foreground text-sm truncate">{data.label}</h3>
                            {data.description && (
                                <p className="text-xs text-foreground-muted truncate mt-0.5">{data.description}</p>
                            )}
                        </div>
                    </div>
                </div>

                {/* Status */}
                <div className="px-4 pb-4">
                    <div className="flex items-center gap-2">
                        <div className={cn(
                            'w-2 h-2 rounded-full transition-all duration-200',
                            isOnline && 'status-online',
                            isStarting && 'status-deploying',
                            isError && 'bg-red-500',
                            !isOnline && !isStarting && !isError && 'bg-foreground-subtle'
                        )} />
                        <span className={cn(
                            'text-sm transition-colors duration-200',
                            isOnline && 'text-success',
                            isStarting && 'text-primary',
                            isError && 'text-red-400',
                            !isOnline && !isStarting && !isError && 'text-foreground-muted'
                        )}>
                            {isOnline ? 'Online' : statusBase || 'unknown'}
                        </span>
                    </div>
                </div>

                {/* Docker Compose badge */}
                <div className="border-t border-border px-4 py-2.5">
                    <div className="flex items-center gap-2 text-xs text-foreground-muted">
                        <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                            <polyline points="14 2 14 8 20 8"/>
                        </svg>
                        <span>Docker Compose</span>
                    </div>
                </div>
            </div>
            <Handle
                type="source"
                position={Position.Right}
                className="!w-3 !h-3 !bg-transparent !border-2 !border-border hover:!border-purple-500 !-right-1.5 transition-colors duration-200"
            />
        </>
    );
}, arePropsEqual);

ComposeServiceNode.displayName = 'ComposeServiceNode';
