import { memo } from 'react';
import { Handle, Position } from '@xyflow/react';
import { Globe } from 'lucide-react';
import { cn } from '@/lib/utils';

interface ServiceNodeData {
    label: string;
    status: string;
    type: string;
    fqdn?: string | null;
    buildPack?: string;
    repository?: string;
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

export const ServiceNode = memo(({ data, selected }: { data: ServiceNodeData; selected?: boolean }) => {
    const statusBase = (data.status || '').split(':')[0];
    const isOnline = statusBase === 'running';
    const isDeploying = statusBase === 'deploying' || statusBase === 'restarting';

    return (
        <>
            <Handle
                type="target"
                position={Position.Left}
                className="!w-3 !h-3 !bg-transparent !border-2 !border-border hover:!border-primary !-left-1.5 transition-colors duration-200"
            />
            <div
                className={cn(
                    'w-[220px] rounded-lg border transition-all duration-200 cursor-pointer',
                    'bg-background-secondary/95 backdrop-blur-xl hover:bg-background-tertiary/95',
                    'hover:-translate-y-0.5 hover:shadow-lg',
                    selected
                        ? 'border-primary shadow-glow-primary'
                        : 'border-border hover:border-border-hover'
                )}
            >
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
                            !isOnline && !isDeploying && 'bg-foreground-subtle'
                        )} />
                        <span className={cn(
                            'text-sm transition-colors duration-200',
                            isOnline && 'text-success',
                            isDeploying && 'text-primary',
                            !isOnline && !isDeploying && 'text-foreground-muted'
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
});

ServiceNode.displayName = 'ServiceNode';
