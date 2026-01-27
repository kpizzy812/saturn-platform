import { memo } from 'react';
import { Handle, Position, NodeProps } from '@xyflow/react';
import { HardDrive } from 'lucide-react';
import { cn } from '@/lib/utils';

interface DatabaseNodeData {
    label: string;
    status: string;
    type: string;
    databaseType?: string;
    volume?: string;
}

// PostgreSQL Logo - Elephant head simplified
const PostgreSQLLogo = () => (
    <svg viewBox="0 0 24 24" className="h-6 w-6" fill="#fff">
        <circle cx="12" cy="10" r="8" fillOpacity="0.9"/>
        <circle cx="9" cy="8" r="1.5" fill="#336791"/>
        <ellipse cx="12" cy="14" rx="3" ry="2" fill="#336791"/>
        <path d="M16 6c2 2 2 6 0 8" stroke="#336791" strokeWidth="2" fill="none"/>
    </svg>
);

// Redis Logo - Stacked layers (classic Redis look)
const RedisLogo = () => (
    <svg viewBox="0 0 24 24" className="h-6 w-6" fill="#fff">
        <polygon points="12,2 22,7 12,12 2,7"/>
        <polygon points="12,12 22,7 22,12 12,17 2,12 2,7" fillOpacity="0.8"/>
        <polygon points="12,17 22,12 22,17 12,22 2,17 2,12" fillOpacity="0.6"/>
    </svg>
);

// MySQL Logo - Dolphin simplified
const MySQLLogo = () => (
    <svg viewBox="0 0 24 24" className="h-6 w-6" fill="#fff">
        <path d="M4 12c0-4 3-8 8-8s8 4 8 8c0 2-1 4-3 5l-2-3c1-0.5 2-1.5 2-3 0-2.5-2-4.5-5-4.5s-5 2-5 4.5c0 1.5 1 2.5 2 3l-2 3c-2-1-3-3-3-5z"/>
        <circle cx="12" cy="12" r="2"/>
    </svg>
);

// MongoDB Logo - Leaf shape
const MongoDBLogo = () => (
    <svg viewBox="0 0 24 24" className="h-6 w-6" fill="#fff">
        <path d="M13 3c0 0-1 2-1 5s1 5 1 7v6h-2v-6c0-2-1-4-1-7s1-5 1-5h2z"/>
        <ellipse cx="12" cy="8" rx="4" ry="6" fillOpacity="0.3"/>
    </svg>
);

// MariaDB Logo - Sea lion simplified
const MariaDBLogo = () => (
    <svg viewBox="0 0 24 24" className="h-6 w-6" fill="#fff">
        <circle cx="12" cy="10" r="7"/>
        <path d="M8 13c2 3 6 3 8 0" stroke="#c0765a" strokeWidth="2" fill="none"/>
        <circle cx="9" cy="8" r="1.5" fill="#c0765a"/>
        <circle cx="15" cy="8" r="1.5" fill="#c0765a"/>
    </svg>
);

// ClickHouse Logo - Stacked bars
const ClickHouseLogo = () => (
    <svg viewBox="0 0 24 24" className="h-6 w-6" fill="#fff">
        <rect x="4" y="4" width="4" height="16" rx="0.5"/>
        <rect x="10" y="8" width="4" height="12" rx="0.5"/>
        <rect x="16" y="6" width="4" height="14" rx="0.5"/>
    </svg>
);

// Generic Database Icon (cylinder)
const GenericDBLogo = () => (
    <svg viewBox="0 0 24 24" className="h-6 w-6" fill="#fff">
        <ellipse cx="12" cy="5" rx="8" ry="3"/>
        <path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5" fillOpacity="0.7"/>
        <ellipse cx="12" cy="12" rx="8" ry="3" fillOpacity="0.5"/>
        <ellipse cx="12" cy="19" rx="8" ry="3" fillOpacity="0.3"/>
    </svg>
);

const getDbLogo = (dbType?: string) => {
    const type = dbType?.toLowerCase() || '';
    // Handle both 'postgresql' and 'standalone-postgresql' formats
    if (type.includes('postgresql') || type.includes('postgres')) {
        return <PostgreSQLLogo />;
    }
    if (type.includes('redis') || type.includes('keydb') || type.includes('dragonfly')) {
        return <RedisLogo />;
    }
    if (type.includes('mysql')) {
        return <MySQLLogo />;
    }
    if (type.includes('mariadb')) {
        return <MariaDBLogo />;
    }
    if (type.includes('mongodb') || type.includes('mongo')) {
        return <MongoDBLogo />;
    }
    if (type.includes('clickhouse')) {
        return <ClickHouseLogo />;
    }
    return <GenericDBLogo />;
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

export const DatabaseNode = memo(({ data, selected }: NodeProps<DatabaseNodeData>) => {
    const statusBase = (data.status || '').split(':')[0];
    const isOnline = statusBase === 'running';
    const bgColor = getDbBgColor(data.databaseType);

    return (
        <>
            <Handle
                type="target"
                position={Position.Left}
                className="!w-3 !h-3 !bg-transparent !border-2 !border-border hover:!border-info !-left-1.5"
            />
            <div
                className={cn(
                    'w-[220px] rounded-lg border transition-all duration-200 cursor-pointer',
                    'bg-background-secondary/95 hover:bg-background-tertiary/95',
                    selected
                        ? 'border-info shadow-glow-info'
                        : 'border-border hover:border-border-hover'
                )}
            >
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
                    <div className="flex items-center gap-2">
                        <div className={cn(
                            'w-2 h-2 rounded-full',
                            isOnline ? 'bg-success' : 'bg-foreground-subtle'
                        )} />
                        <span className={cn(
                            'text-sm',
                            isOnline ? 'text-success' : 'text-foreground-muted'
                        )}>
                            {isOnline ? 'Online' : statusBase || 'unknown'}
                        </span>
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
});

DatabaseNode.displayName = 'DatabaseNode';
