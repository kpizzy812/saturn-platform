import { Database } from 'lucide-react';
import { BrandIcon } from '@/components/ui/BrandIcon';

export const getDbLogo = (dbType?: string) => {
    const type = dbType?.toLowerCase() || '';
    // Handle both 'postgresql' and 'standalone-postgresql' formats
    if (type.includes('postgresql') || type.includes('postgres')) {
        return <BrandIcon name="postgresql" className="h-5 w-5" />;
    }
    if (type.includes('redis') || type.includes('keydb') || type.includes('dragonfly')) {
        return <BrandIcon name="redis" className="h-5 w-5" />;
    }
    if (type.includes('mysql')) {
        return <BrandIcon name="mysql" className="h-5 w-5" />;
    }
    if (type.includes('mariadb')) {
        return <BrandIcon name="mariadb" className="h-5 w-5" />;
    }
    if (type.includes('mongodb') || type.includes('mongo')) {
        return <BrandIcon name="mongodb" className="h-5 w-5" />;
    }
    if (type.includes('clickhouse')) {
        return <BrandIcon name="clickhouse" className="h-5 w-5" />;
    }
    return <Database className="h-5 w-5" />;
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
