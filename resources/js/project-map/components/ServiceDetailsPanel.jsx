import React from 'react';

const statusConfig = {
    running: { label: 'Running', badgeClass: 'bg-emerald-500/10 text-emerald-400', dotClass: 'bg-emerald-500' },
    stopped: { label: 'Stopped', badgeClass: 'bg-neutral-500/10 text-neutral-400', dotClass: 'bg-neutral-500' },
    error: { label: 'Error', badgeClass: 'bg-red-500/10 text-red-400', dotClass: 'bg-red-500' },
    deploying: { label: 'Deploying', badgeClass: 'bg-amber-500/10 text-amber-400', dotClass: 'bg-amber-500' },
    pending: { label: 'Pending', badgeClass: 'bg-fuchsia-500/10 text-fuchsia-400', dotClass: 'bg-fuchsia-500' },
};

export default function ServiceDetailsPanel({ node, onClose, onDelete }) {
    const data = node.data;
    const status = statusConfig[data.status] || statusConfig.pending;

    // Handle action clicks - dispatch to Livewire
    const handleAction = (action) => {
        const event = new CustomEvent('projectmap:action', {
            detail: {
                nodeId: node.id,
                action: action,
                type: node.type,
            }
        });
        document.dispatchEvent(event);
    };

    return (
        <div className="absolute top-0 right-0 h-full w-80 bg-[#1D1B22] border-l border-[#2D2B33] shadow-[0_0_32px_rgba(0,0,0,0.5)] flex flex-col z-50">
            {/* Header */}
            <div className="flex items-center justify-between px-4 py-3 border-b border-[#2D2B33]">
                <div className="flex items-center gap-3 min-w-0">
                    <div className="w-9 h-9 rounded-lg bg-gradient-to-br from-fuchsia-500/15 to-fuchsia-500/5 border border-fuchsia-500/20 flex items-center justify-center flex-shrink-0">
                        <svg className="w-[18px] h-[18px] text-fuchsia-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                        </svg>
                    </div>
                    <div className="min-w-0">
                        <h2 className="font-semibold text-[13px] text-[#FAFBFC] truncate">{data.label}</h2>
                        <span className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-medium ${status.badgeClass}`}>
                            <span className={`w-1 h-1 rounded-full ${status.dotClass}`}></span>
                            {status.label}
                        </span>
                    </div>
                </div>
                <button
                    onClick={onClose}
                    className="p-1.5 rounded-md hover:bg-[#252329] text-[#6B7280] hover:text-[#FAFBFC] transition-colors flex-shrink-0"
                >
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {/* Content */}
            <div className="flex-1 overflow-y-auto p-4 space-y-5">
                {/* Quick Actions */}
                <div>
                    <h3 className="text-[10px] font-medium uppercase tracking-wider text-[#6B7280] mb-2">Quick Actions</h3>
                    <div className="grid grid-cols-2 gap-1.5">
                        {data.status === 'running' ? (
                            <button
                                onClick={() => handleAction('stop')}
                                className="flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-[#252329] hover:bg-[#2D2B33] text-[#FAFBFC] text-[12px] font-medium transition-colors"
                            >
                                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                                </svg>
                                Stop
                            </button>
                        ) : (
                            <button
                                onClick={() => handleAction('start')}
                                className="flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-[12px] font-medium transition-colors"
                            >
                                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Start
                            </button>
                        )}
                        <button
                            onClick={() => handleAction('restart')}
                            className="flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-[#252329] hover:bg-[#2D2B33] text-[#FAFBFC] text-[12px] font-medium transition-colors"
                        >
                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Restart
                        </button>
                        <button
                            onClick={() => handleAction('deploy')}
                            className="flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-[#D946EF] hover:bg-[#E879F9] text-white text-[12px] font-medium transition-colors"
                        >
                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            Deploy
                        </button>
                        <button
                            onClick={() => handleAction('logs')}
                            className="flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-[#252329] hover:bg-[#2D2B33] text-[#FAFBFC] text-[12px] font-medium transition-colors"
                        >
                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Logs
                        </button>
                    </div>
                </div>

                {/* Details */}
                <div>
                    <h3 className="text-[10px] font-medium uppercase tracking-wider text-[#6B7280] mb-2">Details</h3>
                    <div className="space-y-2">
                        {data.fqdn && (
                            <div className="flex items-start justify-between gap-2">
                                <span className="text-[#6B7280] text-[11px]">Domain</span>
                                <a
                                    href={`https://${data.fqdn}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-[#D946EF] hover:underline text-[11px] text-right truncate max-w-[55%]"
                                >
                                    {data.fqdn}
                                </a>
                            </div>
                        )}
                        {data.gitRepository && (
                            <div className="flex items-start justify-between gap-2">
                                <span className="text-[#6B7280] text-[11px]">Repository</span>
                                <span className="text-[#FAFBFC] text-[11px] text-right truncate max-w-[55%]">{data.gitRepository}</span>
                            </div>
                        )}
                        {data.gitBranch && (
                            <div className="flex items-start justify-between gap-2">
                                <span className="text-[#6B7280] text-[11px]">Branch</span>
                                <span className="text-[#FAFBFC] text-[11px]">{data.gitBranch}</span>
                            </div>
                        )}
                        {data.buildPack && (
                            <div className="flex items-start justify-between gap-2">
                                <span className="text-[#6B7280] text-[11px]">Build Pack</span>
                                <span className="text-[#FAFBFC] text-[11px] capitalize">{data.buildPack}</span>
                            </div>
                        )}
                        {data.port && (
                            <div className="flex items-start justify-between gap-2">
                                <span className="text-[#6B7280] text-[11px]">Port</span>
                                <span className="text-[#FAFBFC] text-[11px]">{data.port}</span>
                            </div>
                        )}
                    </div>
                </div>

                {/* Environment Variables Preview */}
                {data.envCount > 0 && (
                    <div>
                        <h3 className="text-[10px] font-medium uppercase tracking-wider text-[#6B7280] mb-2">Environment</h3>
                        <div className="p-2.5 rounded-lg bg-[#131415] border border-[#2D2B33]">
                            <p className="text-[11px] text-[#9CA3AF]">{data.envCount} variables configured</p>
                        </div>
                    </div>
                )}
            </div>

            {/* Footer Actions */}
            <div className="p-3 border-t border-[#2D2B33] space-y-1.5">
                <button
                    onClick={() => handleAction('settings')}
                    className="w-full flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-[#252329] hover:bg-[#2D2B33] text-[#FAFBFC] text-[12px] font-medium transition-colors"
                >
                    <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Open Settings
                </button>
                {!data.isNew && (
                    <button
                        onClick={onDelete}
                        className="w-full flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 text-[12px] font-medium transition-colors"
                    >
                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Delete Resource
                    </button>
                )}
            </div>
        </div>
    );
}
