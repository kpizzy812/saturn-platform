import { PublicLayout } from '@/layouts/PublicLayout';
import { CheckCircle, AlertTriangle, XCircle, Wrench } from 'lucide-react';

interface ServiceStatus {
    name: string;
    status: 'operational' | 'degraded' | 'major_outage' | 'maintenance' | 'unknown';
}

interface Props {
    title?: string;
    description?: string;
    overallStatus?: string;
    groups?: Record<string, ServiceStatus[]>;
}

const statusConfig = {
    operational: {
        label: 'All Systems Operational',
        color: 'bg-emerald-500',
        textColor: 'text-emerald-400',
        icon: CheckCircle,
        bannerBg: 'bg-emerald-500/10 border-emerald-500/20',
    },
    partial_outage: {
        label: 'Partial System Outage',
        color: 'bg-yellow-500',
        textColor: 'text-yellow-400',
        icon: AlertTriangle,
        bannerBg: 'bg-yellow-500/10 border-yellow-500/20',
    },
    major_outage: {
        label: 'Major System Outage',
        color: 'bg-red-500',
        textColor: 'text-red-400',
        icon: XCircle,
        bannerBg: 'bg-red-500/10 border-red-500/20',
    },
    maintenance: {
        label: 'Under Maintenance',
        color: 'bg-blue-500',
        textColor: 'text-blue-400',
        icon: Wrench,
        bannerBg: 'bg-blue-500/10 border-blue-500/20',
    },
    unknown: {
        label: 'Status Unknown',
        color: 'bg-gray-500',
        textColor: 'text-gray-400',
        icon: AlertTriangle,
        bannerBg: 'bg-gray-500/10 border-gray-500/20',
    },
};

function serviceStatusIndicator(status: string) {
    const colors: Record<string, string> = {
        operational: 'bg-emerald-400',
        degraded: 'bg-yellow-400',
        major_outage: 'bg-red-400',
        maintenance: 'bg-blue-400',
        unknown: 'bg-gray-400',
    };
    return colors[status] || colors.unknown;
}

function serviceStatusLabel(status: string) {
    const labels: Record<string, string> = {
        operational: 'Operational',
        degraded: 'Degraded',
        major_outage: 'Outage',
        maintenance: 'Maintenance',
        unknown: 'Unknown',
    };
    return labels[status] || 'Unknown';
}

export default function StatusPage({
    title = 'Saturn',
    description = '',
    overallStatus = 'operational',
    groups = {},
}: Props) {
    const config = statusConfig[overallStatus as keyof typeof statusConfig] || statusConfig.unknown;
    const StatusIcon = config.icon;

    return (
        <PublicLayout title={`${title} - Status`}>
            <div className="mx-auto max-w-3xl px-4 py-12">
                {/* Header */}
                <div className="mb-8 text-center">
                    <h1 className="text-3xl font-bold text-foreground">{title}</h1>
                    {description && (
                        <p className="mt-2 text-sm text-foreground-muted">{description}</p>
                    )}
                </div>

                {/* Overall Status Banner */}
                <div className={`mb-8 flex items-center justify-center gap-3 rounded-xl border p-6 ${config.bannerBg}`}>
                    <StatusIcon className={`h-6 w-6 ${config.textColor}`} />
                    <span className={`text-lg font-semibold ${config.textColor}`}>{config.label}</span>
                </div>

                {/* Service Groups */}
                {Object.keys(groups).length === 0 ? (
                    <div className="rounded-xl border border-white/[0.06] bg-white/[0.02] p-8 text-center">
                        <p className="text-foreground-muted">No services configured</p>
                    </div>
                ) : (
                    <div className="space-y-6">
                        {Object.entries(groups).map(([groupName, services]) => (
                            <div key={groupName}>
                                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wider text-foreground-muted">
                                    {groupName}
                                </h2>
                                <div className="overflow-hidden rounded-xl border border-white/[0.06] bg-white/[0.02]">
                                    {services.map((service, idx) => (
                                        <div
                                            key={`${groupName}-${idx}`}
                                            className={`flex items-center justify-between px-4 py-3 ${
                                                idx < services.length - 1 ? 'border-b border-white/[0.04]' : ''
                                            }`}
                                        >
                                            <span className="text-sm font-medium text-foreground">{service.name}</span>
                                            <div className="flex items-center gap-2">
                                                <span className="text-xs text-foreground-muted">
                                                    {serviceStatusLabel(service.status)}
                                                </span>
                                                <div className={`h-2.5 w-2.5 rounded-full ${serviceStatusIndicator(service.status)}`} />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </PublicLayout>
    );
}
