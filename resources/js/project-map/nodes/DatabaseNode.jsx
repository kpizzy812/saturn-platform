import React, { memo } from 'react';
import { Handle, Position } from '@xyflow/react';

const dbConfig = {
    postgres: {
        icon: (
            <svg className="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
                <path d="M12 6c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6-2.69-6-6-6zm0 10c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4z"/>
            </svg>
        ),
        gradient: 'from-blue-500/15 to-blue-500/5',
        border: 'border-blue-500/20',
        iconColor: 'text-blue-400',
        label: 'PostgreSQL'
    },
    mysql: {
        icon: (
            <svg className="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 3C7.58 3 4 4.79 4 7v10c0 2.21 3.58 4 8 4s8-1.79 8-4V7c0-2.21-3.58-4-8-4zm0 2c3.87 0 6 1.5 6 2s-2.13 2-6 2-6-1.5-6-2 2.13-2 6-2zm6 12c0 .5-2.13 2-6 2s-6-1.5-6-2v-2.23c1.61.78 3.72 1.23 6 1.23s4.39-.45 6-1.23V17z"/>
            </svg>
        ),
        gradient: 'from-orange-500/15 to-orange-500/5',
        border: 'border-orange-500/20',
        iconColor: 'text-orange-400',
        label: 'MySQL'
    },
    mariadb: {
        icon: (
            <svg className="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 3C7.58 3 4 4.79 4 7v10c0 2.21 3.58 4 8 4s8-1.79 8-4V7c0-2.21-3.58-4-8-4zm0 2c3.87 0 6 1.5 6 2s-2.13 2-6 2-6-1.5-6-2 2.13-2 6-2zm6 12c0 .5-2.13 2-6 2s-6-1.5-6-2v-2.23c1.61.78 3.72 1.23 6 1.23s4.39-.45 6-1.23V17z"/>
            </svg>
        ),
        gradient: 'from-amber-500/15 to-amber-500/5',
        border: 'border-amber-500/20',
        iconColor: 'text-amber-400',
        label: 'MariaDB'
    },
    redis: {
        icon: (
            <svg className="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
            </svg>
        ),
        gradient: 'from-red-500/15 to-red-500/5',
        border: 'border-red-500/20',
        iconColor: 'text-red-400',
        label: 'Redis'
    },
    keydb: {
        icon: (
            <svg className="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
            </svg>
        ),
        gradient: 'from-rose-500/15 to-rose-500/5',
        border: 'border-rose-500/20',
        iconColor: 'text-rose-400',
        label: 'KeyDB'
    },
    dragonfly: {
        icon: (
            <svg className="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
            </svg>
        ),
        gradient: 'from-purple-500/15 to-purple-500/5',
        border: 'border-purple-500/20',
        iconColor: 'text-purple-400',
        label: 'Dragonfly'
    },
    mongodb: {
        icon: (
            <svg className="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93z"/>
            </svg>
        ),
        gradient: 'from-green-500/15 to-green-500/5',
        border: 'border-green-500/20',
        iconColor: 'text-green-400',
        label: 'MongoDB'
    },
    clickhouse: {
        icon: (
            <svg className="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="currentColor">
                <rect x="4" y="4" width="4" height="16" rx="0.5"/>
                <rect x="10" y="8" width="4" height="12" rx="0.5"/>
                <rect x="16" y="6" width="4" height="14" rx="0.5"/>
            </svg>
        ),
        gradient: 'from-yellow-500/15 to-yellow-500/5',
        border: 'border-yellow-500/20',
        iconColor: 'text-yellow-400',
        label: 'ClickHouse'
    },
    database: {
        icon: (
            <svg className="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                <path strokeLinecap="round" strokeLinejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
            </svg>
        ),
        gradient: 'from-violet-500/15 to-violet-500/5',
        border: 'border-violet-500/20',
        iconColor: 'text-violet-400',
        label: 'Database'
    }
};

const statusConfig = {
    running: {
        dotClass: 'bg-emerald-500',
        glowClass: 'shadow-[0_0_8px_rgba(16,185,129,0.5)]',
        pulse: true
    },
    stopped: {
        dotClass: 'bg-neutral-500',
        glowClass: '',
        pulse: false
    },
    error: {
        dotClass: 'bg-red-500',
        glowClass: 'shadow-[0_0_8px_rgba(239,68,68,0.5)]',
        pulse: false
    },
    deploying: {
        dotClass: 'bg-amber-500',
        glowClass: 'shadow-[0_0_8px_rgba(245,158,11,0.5)]',
        pulse: true
    },
};

function DatabaseNode({ data, selected }) {
    const dbType = data.dbType || 'database';
    const db = dbConfig[dbType] || dbConfig.database;
    const status = statusConfig[data.status] || statusConfig.stopped;

    return (
        <div
            className={`
                relative min-w-[200px] rounded-xl overflow-hidden
                bg-gradient-to-b from-[#141414] to-[#101010]
                border transition-all duration-150
                ${selected
                    ? 'border-[#7C3AED] shadow-[0_0_0_1px_#7C3AED,0_8px_24px_rgba(124,58,237,0.2)]'
                    : 'border-[#2A2A2A] hover:border-[#3A3A3A]'}
            `}
        >
            {/* Only target handle - databases receive connections */}
            <Handle
                type="target"
                position={Position.Left}
                className="!w-3 !h-3 !bg-[#7C3AED] !border-2 !border-[#0D0D0D] hover:!scale-110 transition-transform"
            />

            {/* Header */}
            <div className="px-3 py-3 flex items-start gap-3">
                {/* Database Icon */}
                <div className={`flex-shrink-0 w-9 h-9 rounded-lg bg-gradient-to-br ${db.gradient} border ${db.border} flex items-center justify-center`}>
                    <span className={db.iconColor}>{db.icon}</span>
                </div>

                {/* Title */}
                <div className="flex-1 min-w-0 pt-0.5">
                    <h3 className="font-semibold text-white text-[13px] truncate leading-tight">{data.label}</h3>
                    <p className="text-[11px] text-[#6B6B6B] mt-0.5">{db.label}</p>
                </div>

                {/* Status Dot */}
                <div className="flex-shrink-0 pt-1">
                    <span className="relative flex h-2 w-2">
                        {status.pulse && (
                            <span className={`animate-ping absolute inline-flex h-full w-full rounded-full ${status.dotClass} opacity-75`}></span>
                        )}
                        <span className={`relative inline-flex rounded-full h-2 w-2 ${status.dotClass} ${status.glowClass}`}></span>
                    </span>
                </div>
            </div>

            {/* Footer */}
            {data.port && (
                <div className="px-3 py-2 bg-black/30 border-t border-[#2A2A2A] flex items-center gap-2 text-[10px] text-[#6B6B6B]">
                    <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                    </svg>
                    <span className="font-medium">:{data.port}</span>
                </div>
            )}
        </div>
    );
}

export default memo(DatabaseNode);
