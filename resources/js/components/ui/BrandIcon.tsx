import { useState } from 'react';
import { Database } from 'lucide-react';

// Map brand names to their actual files in /svgs/
const BRAND_LOGOS: Record<string, string> = {
    postgresql: '/svgs/postgresql-logo.png',
    postgres: '/svgs/postgresql-logo.png',
    mysql: '/svgs/mysql.svg',
    mariadb: '/svgs/mariadb.svg',
    mongodb: '/svgs/mongodb.svg',
    mongo: '/svgs/mongodb.svg',
    redis: '/svgs/redis.svg',
    keydb: '/svgs/redis.svg',
    dragonfly: '/svgs/redis.svg',
    clickhouse: '/svgs/clickhouse.svg',
    docker: '/svgs/docker.svg',
    github: '/svgs/github.svg',
    gitlab: '/svgs/gitlab.svg',
    minio: '/svgs/minio.svg',
};

interface BrandIconProps {
    name: string;
    className?: string;
    fallback?: React.ReactNode;
}

export function BrandIcon({ name, className = 'h-5 w-5', fallback }: BrandIconProps) {
    const [hasError, setHasError] = useState(false);
    const key = name.toLowerCase();
    const logoPath = BRAND_LOGOS[key];

    if (logoPath && !hasError) {
        return (
            <img
                src={logoPath}
                alt={name}
                className={`${className} object-contain`}
                onError={() => setHasError(true)}
            />
        );
    }

    return <>{fallback ?? <Database className={className} />}</>;
}
