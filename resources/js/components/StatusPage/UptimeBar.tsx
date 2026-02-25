import * as React from 'react';

interface UptimeDay {
    date: string;
    status: 'operational' | 'degraded' | 'outage' | 'no_data';
    uptimePercent: number | null;
}

interface UptimeBarProps {
    days: UptimeDay[];
    className?: string;
}

const statusColors: Record<string, string> = {
    operational: 'bg-emerald-500',
    degraded: 'bg-yellow-500',
    outage: 'bg-red-500',
    no_data: 'bg-gray-700',
};

function formatDate(dateStr: string): string {
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

export function UptimeBar({ days, className = '' }: UptimeBarProps) {
    const [tooltip, setTooltip] = React.useState<{ x: number; day: UptimeDay } | null>(null);

    return (
        <div className={`relative ${className}`}>
            <div className="flex items-end gap-[1px]" role="img" aria-label="Uptime history">
                {days.map((day, idx) => (
                    <div
                        key={day.date}
                        className={`h-8 flex-1 rounded-[1px] transition-opacity hover:opacity-80 ${statusColors[day.status] || statusColors.no_data}`}
                        data-testid={`uptime-bar-${idx}`}
                        data-status={day.status}
                        onMouseEnter={(e) => {
                            const rect = e.currentTarget.getBoundingClientRect();
                            setTooltip({ x: rect.left + rect.width / 2, day });
                        }}
                        onMouseLeave={() => setTooltip(null)}
                    />
                ))}
            </div>
            <div className="mt-1 flex justify-between text-[10px] text-foreground-muted">
                <span>90 days ago</span>
                <span>Today</span>
            </div>
            {tooltip && (
                <div
                    className="pointer-events-none absolute -top-12 z-50 -translate-x-1/2 rounded-md bg-gray-900 px-2.5 py-1.5 text-xs text-white shadow-lg"
                    style={{ left: `${((days.findIndex((d) => d.date === tooltip.day.date) + 0.5) / days.length) * 100}%` }}
                    data-testid="uptime-tooltip"
                >
                    <div className="font-medium">{formatDate(tooltip.day.date)}</div>
                    <div>
                        {tooltip.day.uptimePercent !== null ? `${tooltip.day.uptimePercent}% uptime` : 'No data'}
                    </div>
                </div>
            )}
        </div>
    );
}
