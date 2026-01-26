import { Database } from 'lucide-react';

// Database Logo Components
export const PostgreSQLLogo = ({ className = "h-5 w-5" }: { className?: string }) => (
    <svg viewBox="0 0 24 24" className={className} fill="currentColor">
        <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 1.5c4.687 0 8.5 3.813 8.5 8.5s-3.813 8.5-8.5 8.5-8.5-3.813-8.5-8.5 3.813-8.5 8.5-8.5zm-1.5 3.5c-2.21 0-4 1.79-4 4v4c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-4c0-2.21-1.79-4-4-4zm0 2c1.1 0 2 .9 2 2v2h-4v-2c0-1.1.9-2 2-2z"/>
    </svg>
);

export const RedisLogo = ({ className = "h-5 w-5" }: { className?: string }) => (
    <svg viewBox="0 0 24 24" className={className} fill="currentColor">
        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
        <path d="M2 12l10 5 10-5" fillOpacity="0.8"/>
        <path d="M2 17l10 5 10-5" fillOpacity="0.6"/>
    </svg>
);

export const MySQLLogo = ({ className = "h-5 w-5" }: { className?: string }) => (
    <svg viewBox="0 0 24 24" className={className} fill="currentColor">
        <ellipse cx="12" cy="5" rx="8" ry="3"/>
        <path d="M4 5v14c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/>
        <ellipse cx="12" cy="12" rx="8" ry="3" fillOpacity="0.5"/>
    </svg>
);

export const MongoDBLogo = ({ className = "h-5 w-5" }: { className?: string }) => (
    <svg viewBox="0 0 24 24" className={className} fill="currentColor">
        <path d="M12 2s-1 2-1 5 1 5 1 7v6h2v-6c0-2 1-4 1-7s-1-5-1-5h-2z"/>
        <ellipse cx="12" cy="10" rx="4" ry="6" fillOpacity="0.3"/>
    </svg>
);

export const ClickHouseLogo = ({ className = "h-5 w-5" }: { className?: string }) => (
    <svg viewBox="0 0 24 24" className={className} fill="currentColor">
        <rect x="4" y="4" width="4" height="16" rx="0.5"/>
        <rect x="10" y="8" width="4" height="12" rx="0.5"/>
        <rect x="16" y="6" width="4" height="14" rx="0.5"/>
    </svg>
);

export const getDbLogo = (dbType?: string) => {
    const logoClass = "h-5 w-5";
    const type = dbType?.toLowerCase() || '';
    // Handle both 'postgresql' and 'standalone-postgresql' formats
    if (type.includes('postgresql') || type.includes('postgres')) {
        return <PostgreSQLLogo className={logoClass} />;
    }
    if (type.includes('redis') || type.includes('keydb') || type.includes('dragonfly')) {
        return <RedisLogo className={logoClass} />;
    }
    if (type.includes('mysql')) {
        return <MySQLLogo className={logoClass} />;
    }
    if (type.includes('mariadb')) {
        return <MySQLLogo className={logoClass} />;
    }
    if (type.includes('mongodb') || type.includes('mongo')) {
        return <MongoDBLogo className={logoClass} />;
    }
    if (type.includes('clickhouse')) {
        return <ClickHouseLogo className={logoClass} />;
    }
    return <Database className={logoClass} />;
};

export const getDbBgColor = (dbType?: string) => {
    const type = dbType?.toLowerCase() || '';
    // Handle both 'postgresql' and 'standalone-postgresql' formats
    if (type.includes('postgresql') || type.includes('postgres')) {
        return 'bg-blue-500/20 text-blue-400';
    }
    if (type.includes('redis')) {
        return 'bg-red-500/20 text-red-400';
    }
    if (type.includes('keydb')) {
        return 'bg-rose-500/20 text-rose-400';
    }
    if (type.includes('dragonfly')) {
        return 'bg-purple-500/20 text-purple-400';
    }
    if (type.includes('mysql')) {
        return 'bg-orange-500/20 text-orange-400';
    }
    if (type.includes('mariadb')) {
        return 'bg-amber-500/20 text-amber-400';
    }
    if (type.includes('mongodb') || type.includes('mongo')) {
        return 'bg-green-500/20 text-green-400';
    }
    if (type.includes('clickhouse')) {
        return 'bg-yellow-500/20 text-yellow-400';
    }
    return 'bg-violet-500/20 text-violet-400';
};
