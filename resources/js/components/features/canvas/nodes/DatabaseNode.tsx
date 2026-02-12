import { memo, useCallback } from 'react';
import { Handle, Position } from '@xyflow/react';
import { Database, HardDrive } from 'lucide-react';
import { cn } from '@/lib/utils';
import { BrandIcon } from '@/components/ui/BrandIcon';

interface DatabaseNodeData {
    label: string;
    status: string;
    type: string;
    databaseType?: string;
    volume?: string;
    uuid?: string;
    onQuickViewLogs?: () => void;
    onQuickBrowseData?: () => void;
}

const getDbLogo = (dbType?: string) => {
    const type = dbType?.toLowerCase() || '';
    if (type.includes('postgresql') || type.includes('postgres')) {
        return <BrandIcon name="postgresql" className="h-6 w-6" />;
    }
    if (type.includes('redis') || type.includes('keydb') || type.includes('dragonfly')) {
        return <BrandIcon name="redis" className="h-6 w-6" />;
    }
    if (type.includes('mysql')) {
        return <BrandIcon name="mysql" className="h-6 w-6" />;
    }
    if (type.includes('mariadb')) {
        return <BrandIcon name="mariadb" className="h-6 w-6" />;
    }
    if (type.includes('mongodb') || type.includes('mongo')) {
        return <BrandIcon name="mongodb" className="h-6 w-6" />;
    }
    if (type.includes('clickhouse')) {
        return <BrandIcon name="clickhouse" className="h-6 w-6" />;
    }
    return <Database className="h-6 w-6 text-white" />;
};

const getDbBgColor = (dbType?: string) => {
    const type = dbType?.toLowerCase() || '';
    // Handle both 'postgresql' and 'standalone-postgresql' formats
    if (type.includes('postgresql') || type.includes('postgres')) {
        return 'bg-[#336791]';
    }
    if (type.includes('redis') || type.includes('keydb')) {
        return 'bg-[#D82C20]';
    }
    if (type.includes('dragonfly')) {
        return 'bg-[#6366f1]';
    }
    if (type.includes('mysql')) {
        return 'bg-[#00758F]';
    }
    if (type.includes('mariadb')) {
        return 'bg-[#c0765a]';
    }
    if (type.includes('mongodb') || type.includes('mongo')) {
        return 'bg-[#47A248]';
    }
    if (type.includes('clickhouse')) {
        return 'bg-[#FFCC00]';
    }
    return 'bg-gray-600';
};

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

// Check if database type is Redis-like (key-value store)
const isRedisLike = (dbType?: string): boolean => {
    const type = dbType?.toLowerCase() || '';
    return type.includes('redis') || type.includes('keydb') || type.includes('dragonfly');
};

// Custom comparison to ensure status changes trigger re-render
const arePropsEqual = (prevProps: { data: DatabaseNodeData; selected?: boolean }, nextProps: { data: DatabaseNodeData; selected?: boolean }) => {
    return (
        prevProps.data.status === nextProps.data.status &&
        prevProps.data.label === nextProps.data.label &&
        prevProps.selected === nextProps.selected
    );
};

export const DatabaseNode = memo(({ data, selected }: { data: DatabaseNodeData; selected?: boolean }) => {
    const statusParts = (data.status || '').split(':');
    const statusBase = statusParts[0];
    const healthBase = statusParts[1] || '';
    const isOnline = statusBase === 'running';
    const isError = statusBase === 'exited' || statusBase === 'stopped' || statusBase === 'crashed' || statusBase === 'failed';
    const isHealthy = healthBase === 'healthy';
    const isUnhealthy = healthBase === 'unhealthy';
    const bgColor = getDbBgColor(data.databaseType);
    const hasQuickActions = !!(data.onQuickViewLogs || data.onQuickBrowseData);
    const isKeyValueStore = isRedisLike(data.databaseType);

    return (
        <>
            <Handle
                type="target"
                position={Position.Left}
                className="!w-3 !h-3 !bg-transparent !border-2 !border-border hover:!border-info !-left-1.5"
            />
            <div
                className={cn(
                    'group relative w-[220px] rounded-lg border transition-all duration-200 cursor-pointer',
                    'bg-background-secondary/95 hover:bg-background-tertiary/95',
                    'hover:-translate-y-0.5 hover:border-[#7c3aed] hover:shadow-[var(--shadow-glow-primary-hover)]',
                    selected
                        ? 'border-info shadow-glow-info'
                        : 'border-border'
                )}
            >
                {/* Quick Actions - visible on hover */}
                {hasQuickActions && (
                    <div className="absolute -top-3.5 right-2 z-10 flex items-center gap-1 opacity-100 translate-y-0 md:opacity-0 md:translate-y-1 transition-all duration-200 md:group-hover:opacity-100 md:group-hover:translate-y-0">
                        {data.onQuickBrowseData && (
                            <QuickActionButton
                                onClick={data.onQuickBrowseData}
                                title={isKeyValueStore ? "Browse Keys" : "Browse Tables"}
                            >
                                {isKeyValueStore ? (
                                    /* Key icon for Redis-like databases */
                                    <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                        <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
                                    </svg>
                                ) : (
                                    /* Table icon for SQL databases */
                                    <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                        <line x1="3" y1="9" x2="21" y2="9"/>
                                        <line x1="9" y1="21" x2="9" y2="9"/>
                                    </svg>
                                )}
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
                        <div className={cn(
                            'flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center',
                            bgColor
                        )}>
                            {getDbLogo(data.databaseType)}
                        </div>
                        <div className="flex-1 min-w-0 pt-0.5">
                            <h3 className="font-medium text-foreground text-sm truncate">{data.label}</h3>
                        </div>
                    </div>
                </div>

                {/* Status */}
                <div className="px-4 pb-3">
                    <div className="flex items-center gap-2 flex-wrap">
                        <div className="flex items-center gap-1.5">
                            <div className={cn(
                                'w-2 h-2 rounded-full',
                                isOnline && 'bg-success',
                                isError && 'bg-red-500',
                                !isOnline && !isError && 'bg-foreground-subtle'
                            )} />
                            <span className={cn(
                                'text-sm',
                                isOnline && 'text-success',
                                isError && 'text-red-400',
                                !isOnline && !isError && 'text-foreground-muted'
                            )}>
                                {isOnline ? 'Online' : statusBase || 'unknown'}
                            </span>
                        </div>
                        {/* Health indicator */}
                        {healthBase && healthBase !== 'unknown' && (
                            <div className="flex items-center gap-1.5">
                                <div className={cn(
                                    'w-2 h-2 rounded-full',
                                    isHealthy && 'bg-success',
                                    isUnhealthy && 'bg-red-500',
                                    !isHealthy && !isUnhealthy && 'bg-foreground-subtle'
                                )} />
                                <span className={cn(
                                    'text-xs capitalize',
                                    isHealthy && 'text-success',
                                    isUnhealthy && 'text-red-400',
                                    !isHealthy && !isUnhealthy && 'text-foreground-muted'
                                )}>
                                    {healthBase}
                                </span>
                            </div>
                        )}
                    </div>
                </div>

                {/* Volume Info */}
                {data.volume && (
                    <div className="border-t border-border px-4 py-2.5">
                        <div className="flex items-center gap-2 text-xs text-foreground-muted">
                            <HardDrive className="h-3.5 w-3.5" />
                            <span>{data.volume}</span>
                        </div>
                    </div>
                )}
            </div>
            <Handle
                type="source"
                position={Position.Right}
                className="!w-3 !h-3 !bg-transparent !border-2 !border-border hover:!border-info !-right-1.5"
            />
        </>
    );
}, arePropsEqual);

DatabaseNode.displayName = 'DatabaseNode';
