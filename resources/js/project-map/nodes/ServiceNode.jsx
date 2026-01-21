import React, { memo } from 'react';
import { Handle, Position } from '@xyflow/react';

const statusConfig = {
    running: {
        dotClass: 'bg-emerald-500',
        glowClass: 'shadow-[0_0_8px_rgba(16,185,129,0.5)]',
        label: 'Running',
        badgeClass: 'bg-emerald-500/10 text-emerald-400',
        pulse: true
    },
    stopped: {
        dotClass: 'bg-neutral-500',
        glowClass: '',
        label: 'Stopped',
        badgeClass: 'bg-neutral-500/10 text-neutral-400',
        pulse: false
    },
    error: {
        dotClass: 'bg-red-500',
        glowClass: 'shadow-[0_0_8px_rgba(239,68,68,0.5)]',
        label: 'Error',
        badgeClass: 'bg-red-500/10 text-red-400',
        pulse: false
    },
    deploying: {
        dotClass: 'bg-amber-500',
        glowClass: 'shadow-[0_0_8px_rgba(245,158,11,0.5)]',
        label: 'Deploying',
        badgeClass: 'bg-amber-500/10 text-amber-400',
        pulse: true
    },
    pending: {
        dotClass: 'bg-violet-500',
        glowClass: 'shadow-[0_0_8px_rgba(124,58,237,0.5)]',
        label: 'Pending',
        badgeClass: 'bg-violet-500/10 text-violet-400',
        pulse: false
    },
};

function ServiceNode({ data, selected }) {
    const status = statusConfig[data.status] || statusConfig.pending;

    return (
        <div
            className={`
                relative min-w-[220px] rounded-xl overflow-hidden
                bg-gradient-to-b from-[#141414] to-[#101010]
                border transition-all duration-150
                ${selected
                    ? 'border-[#7C3AED] shadow-[0_0_0_1px_#7C3AED,0_8px_24px_rgba(124,58,237,0.2)]'
                    : 'border-[#2A2A2A] hover:border-[#3A3A3A]'}
            `}
        >
            {/* Connection Handles */}
            <Handle
                type="target"
                position={Position.Left}
                className="!w-3 !h-3 !bg-[#7C3AED] !border-2 !border-[#0D0D0D] hover:!scale-110 transition-transform"
            />
            <Handle
                type="source"
                position={Position.Right}
                className="!w-3 !h-3 !bg-[#7C3AED] !border-2 !border-[#0D0D0D] hover:!scale-110 transition-transform"
            />

            {/* Header */}
            <div className="px-3 py-3 flex items-start gap-3">
                {/* Service Icon */}
                <div className="flex-shrink-0 w-9 h-9 rounded-lg bg-gradient-to-br from-[#7C3AED]/15 to-[#7C3AED]/5 border border-[#7C3AED]/20 flex items-center justify-center">
                    <svg className="w-[18px] h-[18px] text-[#7C3AED]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                    </svg>
                </div>

                {/* Title and Domain */}
                <div className="flex-1 min-w-0 pt-0.5">
                    <h3 className="font-semibold text-white text-[13px] truncate leading-tight">{data.label}</h3>
                    {data.fqdn && (
                        <p className="text-[11px] text-[#6B6B6B] truncate mt-0.5">{data.fqdn}</p>
                    )}
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
            <div className="px-3 py-2 bg-black/30 border-t border-[#2A2A2A] flex items-center justify-between gap-2">
                <span className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-medium ${status.badgeClass}`}>
                    <span className={`w-1 h-1 rounded-full ${status.dotClass}`}></span>
                    {status.label}
                </span>

                <div className="flex items-center gap-2">
                    {data.buildPack && (
                        <span className="text-[9px] uppercase tracking-wider text-[#6B6B6B] font-medium">{data.buildPack}</span>
                    )}
                    {data.gitBranch && (
                        <div className="flex items-center gap-1 text-[10px] text-[#6B6B6B]">
                            <svg className="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 16 16">
                                <path fillRule="evenodd" d="M11.75 2.5a.75.75 0 1 0 0 1.5.75.75 0 0 0 0-1.5zm-2.25.75a2.25 2.25 0 1 1 3 2.122V6A2.5 2.5 0 0 1 10 8.5H6a1 1 0 0 0-1 1v1.128a2.251 2.251 0 1 1-1.5 0V5.372a2.25 2.25 0 1 1 1.5 0v1.836A2.493 2.493 0 0 1 6 7h4a1 1 0 0 0 1-1v-.628A2.25 2.25 0 0 1 9.5 3.25zM4.25 12a.75.75 0 1 0 0 1.5.75.75 0 0 0 0-1.5zM3.5 3.25a.75.75 0 1 1 1.5 0 .75.75 0 0 1-1.5 0z"/>
                            </svg>
                            <span className="truncate max-w-[50px]">{data.gitBranch}</span>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

export default memo(ServiceNode);
