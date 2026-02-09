import { memo, useCallback } from 'react';
import { Handle, Position } from '@xyflow/react';
import { cn } from '@/lib/utils';

interface ServiceNodeData {
    label: string;
    status: string;
    type: string;
    fqdn?: string | null;
    buildPack?: string;
    repository?: string;
    uuid?: string;
    onQuickDeploy?: () => void;
    onQuickOpenUrl?: () => void;
    onQuickViewLogs?: () => void;
}

// GitHub Logo SVG
const GitHubLogo = () => (
    <svg viewBox="0 0 24 24" className="h-6 w-6" fill="currentColor">
        <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
    </svg>
);

// Docker Logo SVG
const DockerLogo = () => (
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
const arePropsEqual = (prevProps: { data: ServiceNodeData; selected?: boolean }, nextProps: { data: ServiceNodeData; selected?: boolean }) => {
    return (
        prevProps.data.status === nextProps.data.status &&
        prevProps.data.label === nextProps.data.label &&
        prevProps.data.fqdn === nextProps.data.fqdn &&
        prevProps.selected === nextProps.selected
    );
};

export const ServiceNode = memo(({ data, selected }: { data: ServiceNodeData; selected?: boolean }) => {
    const statusBase = (data.status || '').split(':')[0];
    const isOnline = statusBase === 'running';
    const isDeploying = statusBase === 'deploying' || statusBase === 'restarting';
    const isError = statusBase === 'exited' || statusBase === 'stopped' || statusBase === 'crashed' || statusBase === 'failed';
    const hasQuickActions = data.onQuickDeploy || data.onQuickOpenUrl || data.onQuickViewLogs;

    return (
        <>
            <Handle
                type="target"
                position={Position.Left}
                className="!w-3 !h-3 !bg-transparent !border-2 !border-border hover:!border-primary !-left-1.5 transition-colors duration-200"
            />
            <div
                className={cn(
                    'group relative w-[220px] rounded-lg border transition-all duration-200 cursor-pointer',
                    'bg-background-secondary/95 backdrop-blur-xl hover:bg-background-tertiary/95',
                    'hover:-translate-y-0.5 hover:border-[#7c3aed] hover:shadow-[var(--shadow-glow-primary-hover)]',
                    selected
                        ? 'border-primary shadow-glow-primary'
                        : 'border-border'
                )}
            >
                {/* Quick Actions - visible on hover */}
                {hasQuickActions && (
                    <div className="absolute -top-3.5 right-2 z-10 flex items-center gap-1 opacity-100 translate-y-0 md:opacity-0 md:translate-y-1 transition-all duration-200 md:group-hover:opacity-100 md:group-hover:translate-y-0">
                        {data.onQuickDeploy && (
                            <QuickActionButton onClick={data.onQuickDeploy} title="Deploy">
                                {/* Rocket icon */}
                                <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 00-2.91-.09z"/>
                                    <path d="M12 15l-3-3a22 22 0 012-3.95A12.88 12.88 0 0122 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 01-4 2z"/>
                                    <path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/>
                                    <path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>
                                </svg>
                            </QuickActionButton>
                        )}
                        {data.onQuickOpenUrl && (
                            <QuickActionButton onClick={data.onQuickOpenUrl} title="Open URL">
                                {/* External link icon */}
                                <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/>
                                    <polyline points="15 3 21 3 21 9"/>
                                    <line x1="10" y1="14" x2="21" y2="3"/>
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
                        <div className="flex-shrink-0 w-10 h-10 rounded-lg bg-background border border-border flex items-center justify-center text-foreground transition-all duration-200 hover:border-border-hover">
                            <GitHubLogo />
                        </div>
                        <div className="flex-1 min-w-0 pt-0.5">
                            <h3 className="font-medium text-foreground text-sm truncate">{data.label}</h3>
                            {data.fqdn && (
                                <p className="text-xs text-foreground-muted truncate mt-0.5">{data.fqdn}</p>
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
                            isDeploying && 'status-deploying',
                            isError && 'bg-red-500',
                            !isOnline && !isDeploying && !isError && 'bg-foreground-subtle'
                        )} />
                        <span className={cn(
                            'text-sm transition-colors duration-200',
                            isOnline && 'text-success',
                            isDeploying && 'text-primary',
                            isError && 'text-red-400',
                            !isOnline && !isDeploying && !isError && 'text-foreground-muted'
                        )}>
                            {isOnline ? 'Online' : statusBase || 'unknown'}
                        </span>
                    </div>
                </div>
            </div>
            <Handle
                type="source"
                position={Position.Right}
                className="!w-3 !h-3 !bg-transparent !border-2 !border-border hover:!border-primary !-right-1.5 transition-colors duration-200"
            />
        </>
    );
}, arePropsEqual);

ServiceNode.displayName = 'ServiceNode';
