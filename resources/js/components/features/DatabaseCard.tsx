import { Link, router } from '@inertiajs/react';
import { useConfirm, Badge, StatusBadge } from '@/components/ui';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';
import { MoreVertical, Settings, Trash2, RotateCw } from 'lucide-react';
import type { StandaloneDatabase, DatabaseType, EnvironmentType } from '@/types';
import { getStatusColor } from '@/lib/statusUtils';
import { getDbLogo } from '@/components/features/Projects/DatabaseLogos';

interface DatabaseCardProps {
    database: StandaloneDatabase;
}

const databaseTypeConfig: Record<DatabaseType, { color: string; bgColor: string; gradient: string }> = {
    postgresql: {
        color: 'text-blue-400',
        bgColor: 'bg-blue-500/15',
        gradient: 'bg-gradient-to-br from-blue-500 to-blue-600'
    },
    mysql: {
        color: 'text-orange-400',
        bgColor: 'bg-orange-500/15',
        gradient: 'bg-gradient-to-br from-orange-500 to-orange-600'
    },
    mariadb: {
        color: 'text-amber-400',
        bgColor: 'bg-amber-500/15',
        gradient: 'bg-gradient-to-br from-amber-600 to-amber-700'
    },
    mongodb: {
        color: 'text-green-400',
        bgColor: 'bg-green-500/15',
        gradient: 'bg-gradient-to-br from-green-500 to-green-600'
    },
    redis: {
        color: 'text-red-400',
        bgColor: 'bg-red-500/15',
        gradient: 'bg-gradient-to-br from-red-500 to-red-600'
    },
    keydb: {
        color: 'text-rose-400',
        bgColor: 'bg-rose-500/15',
        gradient: 'bg-gradient-to-br from-rose-600 to-rose-700'
    },
    dragonfly: {
        color: 'text-purple-400',
        bgColor: 'bg-purple-500/15',
        gradient: 'bg-gradient-to-br from-purple-500 to-purple-600'
    },
    clickhouse: {
        color: 'text-yellow-400',
        bgColor: 'bg-yellow-500/15',
        gradient: 'bg-gradient-to-br from-yellow-500 to-yellow-600'
    },
};

const formatDatabaseType = (type: DatabaseType): string => {
    const typeMap: Record<DatabaseType, string> = {
        postgresql: 'PostgreSQL',
        mysql: 'MySQL',
        mariadb: 'MariaDB',
        mongodb: 'MongoDB',
        redis: 'Redis',
        keydb: 'KeyDB',
        dragonfly: 'Dragonfly',
        clickhouse: 'ClickHouse',
    };
    return typeMap[type] || type;
};

// Get badge variant based on environment type
const getEnvironmentVariant = (type: EnvironmentType): 'default' | 'primary' | 'success' | 'warning' | 'info' => {
    switch (type) {
        case 'production':
            return 'warning';
        case 'uat':
            return 'warning';
        case 'development':
            return 'info';
        default:
            return 'default';
    }
};

export function DatabaseCard({ database }: DatabaseCardProps) {
    const confirm = useConfirm();
    const config = databaseTypeConfig[database.database_type] || databaseTypeConfig.postgresql;

    return (
        <Link
            href={`/databases/${database.uuid}`}
            className="group relative flex flex-col rounded-xl border border-border/50 bg-gradient-to-br from-background-secondary to-background-secondary/50 p-5 transition-all duration-300 hover:-translate-y-1 hover:border-border hover:shadow-xl hover:shadow-black/20"
        >
            {/* Subtle gradient overlay on hover */}
            <div className="absolute inset-0 rounded-xl bg-gradient-to-br from-white/[0.02] to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100" />

            <div className="relative flex items-start justify-between">
                <div className="flex items-center gap-3">
                    <div className={`flex h-10 w-10 items-center justify-center rounded-lg transition-colors ${config.bgColor} ${config.color}`}>
                        {getDbLogo(database.database_type)}
                    </div>
                    <div>
                        <h3 className="font-medium text-foreground transition-colors group-hover:text-white">{database.name}</h3>
                        <p className="text-sm text-foreground-muted">
                            {formatDatabaseType(database.database_type)}
                        </p>
                    </div>
                </div>
                <Dropdown>
                    <DropdownTrigger>
                        <button
                            className="rounded-md p-1.5 opacity-0 transition-all duration-200 hover:bg-white/10 group-hover:opacity-100"
                            onClick={(e) => e.preventDefault()}
                        >
                            <MoreVertical className="h-4 w-4 text-foreground-muted" />
                        </button>
                    </DropdownTrigger>
                    <DropdownContent align="right">
                        <DropdownItem onClick={(e) => {
                            e.preventDefault();
                            router.visit(`/databases/${database.uuid}/settings`);
                        }}>
                            <Settings className="h-4 w-4" />
                            Database Settings
                        </DropdownItem>
                        <DropdownItem onClick={(e) => {
                            e.preventDefault();
                            router.post(`/databases/${database.uuid}/restart`);
                        }}>
                            <RotateCw className="h-4 w-4" />
                            Restart
                        </DropdownItem>
                        <DropdownDivider />
                        <DropdownItem onClick={async (e) => {
                            e.preventDefault();
                            const confirmed = await confirm({
                                title: 'Delete Database',
                                description: `Are you sure you want to delete "${database.name}"? This action cannot be undone.`,
                                confirmText: 'Delete',
                                variant: 'danger',
                            });
                            if (confirmed) {
                                router.delete(`/databases/${database.uuid}`);
                            }
                        }} danger>
                            <Trash2 className="h-4 w-4" />
                            Delete Database
                        </DropdownItem>
                    </DropdownContent>
                </Dropdown>
            </div>

            {/* Status and Environment badges */}
            <div className="relative mt-4 flex flex-wrap items-center gap-2">
                <StatusBadge status={typeof database.status === 'object' ? (database.status?.state || 'unknown') : String(database.status || '').split(':')[0] || 'unknown'} size="sm" />
                <StatusBadge status={typeof database.status === 'object' ? (database.status?.health || 'unknown') : String(database.status || '').split(':')[1] || 'unknown'} size="sm" />
                {database.environment && (
                    <Badge
                        variant={getEnvironmentVariant(database.environment.type)}
                        size="sm"
                    >
                        {database.environment.name}
                    </Badge>
                )}
            </div>

            {/* Last updated */}
            <p className="relative mt-4 text-xs text-foreground-subtle">
                Updated {new Date(database.updated_at).toLocaleDateString()}
            </p>
        </Link>
    );
}
