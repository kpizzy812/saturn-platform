import { AlertTriangle, XCircle, Wrench, Info } from 'lucide-react';

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

interface IncidentBannerProps {
    incidents: Incident[];
}

const severityConfig = {
    critical: {
        bg: 'bg-red-500/10 border-red-500/20',
        text: 'text-red-400',
        icon: XCircle,
    },
    major: {
        bg: 'bg-orange-500/10 border-orange-500/20',
        text: 'text-orange-400',
        icon: AlertTriangle,
    },
    minor: {
        bg: 'bg-yellow-500/10 border-yellow-500/20',
        text: 'text-yellow-400',
        icon: Info,
    },
    maintenance: {
        bg: 'bg-blue-500/10 border-blue-500/20',
        text: 'text-blue-400',
        icon: Wrench,
    },
};

function formatDateTime(iso: string): string {
    return new Date(iso).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function statusLabel(status: string): string {
    const labels: Record<string, string> = {
        investigating: 'Investigating',
        identified: 'Identified',
        monitoring: 'Monitoring',
        resolved: 'Resolved',
    };
    return labels[status] || status;
}

export function IncidentBanner({ incidents }: IncidentBannerProps) {
    if (!incidents || incidents.length === 0) {
        return null;
    }

    return (
        <div className="space-y-3" data-testid="incident-banner">
            {incidents.map((incident) => {
                const config = severityConfig[incident.severity] || severityConfig.minor;
                const Icon = config.icon;

                return (
                    <div
                        key={incident.id}
                        className={`rounded-xl border p-4 ${config.bg}`}
                    >
                        <div className="flex items-start gap-3">
                            <Icon className={`mt-0.5 h-5 w-5 flex-shrink-0 ${config.text}`} />
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2">
                                    <h3 className={`font-semibold ${config.text}`}>
                                        {incident.title}
                                    </h3>
                                    <span className={`rounded-full px-2 py-0.5 text-[10px] font-medium uppercase ${config.text} bg-white/5`}>
                                        {statusLabel(incident.status)}
                                    </span>
                                </div>
                                {incident.updates && incident.updates.length > 0 && (
                                    <div className="mt-3 space-y-2 border-l-2 border-white/10 pl-3">
                                        {incident.updates.map((update, idx) => (
                                            <div key={idx} className="text-sm">
                                                <span className="font-medium text-foreground-muted">
                                                    {statusLabel(update.status)}
                                                </span>
                                                <span className="mx-1.5 text-foreground-muted/50">—</span>
                                                <span className="text-foreground-muted">{update.message}</span>
                                                <span className="ml-2 text-xs text-foreground-muted/50">
                                                    {formatDateTime(update.postedAt)}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
