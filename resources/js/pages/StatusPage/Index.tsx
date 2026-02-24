import { PublicLayout } from '@/layouts/PublicLayout';
import { UptimeBar } from '@/components/StatusPage/UptimeBar';
import { IncidentBanner } from '@/components/StatusPage/IncidentBanner';
import { CheckCircle, AlertTriangle, XCircle, Wrench } from 'lucide-react';

interface UptimeDay {
    date: string;
    status: 'operational' | 'degraded' | 'outage' | 'no_data';
    uptimePercent: number | null;
}

interface ServiceData {
    name: string;
    status: 'operational' | 'degraded' | 'major_outage' | 'maintenance' | 'unknown';
    group: string;
    resourceType: string;
    resourceId: number;
    uptimePercent: number | null;
    uptimeDays: UptimeDay[];
}

interface IncidentUpdate {
    status: string;
    message: string;
    postedAt: string;
}

interface Incident {
    id: number;
    title: string;
    severity: 'minor' | 'major' | 'critical' | 'maintenance';
    status: string;
    startedAt: string;
    resolvedAt: string | null;
    updates: IncidentUpdate[];
}

interface Props {
    title?: string;
    description?: string;
    overallStatus?: string;
    services?: ServiceData[];
    incidents?: Incident[];
    // Legacy support
    groups?: Record<string, { name: string; status: string }[]>;
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
    services = [],
    incidents = [],
}: Props) {
    const config = statusConfig[overallStatus as keyof typeof statusConfig] || statusConfig.unknown;
    const StatusIcon = config.icon;

    // Group services by their group field
    const groupedServices: Record<string, ServiceData[]> = {};
    for (const service of services) {
        const group = service.group || 'Services';
        if (!groupedServices[group]) {
            groupedServices[group] = [];
        }
        groupedServices[group].push(service);
    }

    const activeIncidents = incidents.filter((i) => !i.resolvedAt);
    const resolvedIncidents = incidents.filter((i) => i.resolvedAt);

    const hasServices = services.length > 0;

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

                {/* Active Incidents */}
                {activeIncidents.length > 0 && (
                    <div className="mb-8">
                        <IncidentBanner incidents={activeIncidents} />
                    </div>
                )}

                {/* Service Groups with Uptime Bars */}
                {!hasServices ? (
                    <div className="rounded-xl border border-white/[0.06] bg-white/[0.02] p-8 text-center">
                        <p className="text-foreground-muted">No services configured</p>
                    </div>
                ) : (
                    <div className="space-y-6">
                        {Object.entries(groupedServices).map(([groupName, groupServices]) => (
                            <div key={groupName}>
                                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wider text-foreground-muted">
                                    {groupName}
                                </h2>
                                <div className="overflow-hidden rounded-xl border border-white/[0.06] bg-white/[0.02]">
                                    {groupServices.map((service, idx) => (
                                        <div
                                            key={`${service.resourceType}-${service.resourceId}`}
                                            className={idx < groupServices.length - 1 ? 'border-b border-white/[0.04]' : ''}
                                        >
                                            <div className="flex items-center justify-between px-4 pt-3 pb-1">
                                                <span className="text-sm font-medium text-foreground">{service.name}</span>
                                                <div className="flex items-center gap-2">
                                                    {service.uptimePercent !== null && (
                                                        <span className="text-xs text-foreground-muted">
                                                            {service.uptimePercent}% uptime
                                                        </span>
                                                    )}
                                                    <span className="text-xs text-foreground-muted">
                                                        {serviceStatusLabel(service.status)}
                                                    </span>
                                                    <div className={`h-2.5 w-2.5 rounded-full ${serviceStatusIndicator(service.status)}`} />
                                                </div>
                                            </div>
                                            {service.uptimeDays && service.uptimeDays.length > 0 && (
                                                <div className="px-4 pb-3">
                                                    <UptimeBar days={service.uptimeDays} />
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* Past Incidents (resolved within 7 days) */}
                {resolvedIncidents.length > 0 && (
                    <div className="mt-10">
                        <h2 className="mb-4 text-sm font-semibold uppercase tracking-wider text-foreground-muted">
                            Past Incidents
                        </h2>
                        <div className="space-y-4">
                            {resolvedIncidents.map((incident) => (
                                <div
                                    key={incident.id}
                                    className="rounded-xl border border-white/[0.06] bg-white/[0.02] p-4"
                                >
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-sm font-medium text-foreground">{incident.title}</h3>
                                        <span className="text-xs text-emerald-400">Resolved</span>
                                    </div>
                                    {incident.updates && incident.updates.length > 0 && (
                                        <div className="mt-2 space-y-1 border-l-2 border-white/10 pl-3">
                                            {incident.updates.map((update, idx) => (
                                                <div key={idx} className="text-xs text-foreground-muted">
                                                    <span className="font-medium">{update.status}</span>
                                                    <span className="mx-1">—</span>
                                                    <span>{update.message}</span>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </PublicLayout>
    );
}
