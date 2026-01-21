import React from 'react';

const serviceTypes = [
    {
        id: 'application',
        name: 'Application',
        description: 'Deploy from Git repository',
        icon: (
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
            </svg>
        ),
        gradient: 'from-fuchsia-500/15 to-fuchsia-500/5',
        border: 'border-fuchsia-500/20',
        iconColor: 'text-fuchsia-400',
    },
    {
        id: 'postgres',
        name: 'PostgreSQL',
        description: 'Relational database',
        icon: (
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
            </svg>
        ),
        gradient: 'from-blue-500/15 to-blue-500/5',
        border: 'border-blue-500/20',
        iconColor: 'text-blue-400',
    },
    {
        id: 'mysql',
        name: 'MySQL',
        description: 'Relational database',
        icon: (
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
            </svg>
        ),
        gradient: 'from-orange-500/15 to-orange-500/5',
        border: 'border-orange-500/20',
        iconColor: 'text-orange-400',
    },
    {
        id: 'redis',
        name: 'Redis',
        description: 'In-memory data store',
        icon: (
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
            </svg>
        ),
        gradient: 'from-red-500/15 to-red-500/5',
        border: 'border-red-500/20',
        iconColor: 'text-red-400',
    },
    {
        id: 'mongodb',
        name: 'MongoDB',
        description: 'Document database',
        icon: (
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
            </svg>
        ),
        gradient: 'from-green-500/15 to-green-500/5',
        border: 'border-green-500/20',
        iconColor: 'text-green-400',
    },
    {
        id: 'service',
        name: 'Docker Compose',
        description: 'Multi-container service',
        icon: (
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M6.429 9.75L2.25 12l4.179 2.25m0-4.5l5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0l4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0l-5.571 3-5.571-3" />
            </svg>
        ),
        gradient: 'from-violet-500/15 to-violet-500/5',
        border: 'border-violet-500/20',
        iconColor: 'text-violet-400',
    },
];

export default function AddServicePanel({ onSelect, onClose }) {
    return (
        <div className="w-72 bg-[#1D1B22] rounded-xl border border-[#2D2B33] shadow-[0_8px_32px_rgba(0,0,0,0.4)] overflow-hidden">
            <div className="px-4 py-3 border-b border-[#2D2B33] flex items-center justify-between">
                <h3 className="font-semibold text-[13px] text-[#FAFBFC]">Add Resource</h3>
                <button
                    onClick={onClose}
                    className="p-1 rounded-md hover:bg-[#252329] text-[#6B7280] hover:text-[#FAFBFC] transition-colors"
                >
                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div className="p-2 max-h-80 overflow-y-auto">
                {serviceTypes.map((service) => (
                    <button
                        key={service.id}
                        onClick={() => onSelect(service.id)}
                        className="w-full flex items-center gap-3 p-2.5 rounded-lg hover:bg-[#252329] transition-colors text-left group"
                    >
                        <div className={`w-9 h-9 rounded-lg bg-gradient-to-br ${service.gradient} border ${service.border} flex items-center justify-center flex-shrink-0`}>
                            <span className={service.iconColor}>{service.icon}</span>
                        </div>
                        <div className="min-w-0">
                            <p className="font-medium text-[13px] text-[#FAFBFC] group-hover:text-[#D946EF] transition-colors">{service.name}</p>
                            <p className="text-[11px] text-[#6B7280]">{service.description}</p>
                        </div>
                    </button>
                ))}
            </div>
        </div>
    );
}
