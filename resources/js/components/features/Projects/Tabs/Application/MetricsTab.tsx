import { useState, useCallback, useMemo } from 'react';
import { RefreshCw } from 'lucide-react';
import { useSentinelMetrics } from '@/hooks/useSentinelMetrics';
import { useApplicationMetrics } from '@/hooks/useApplicationMetrics';
import type { SelectedService } from '../../types';

type DisplayTimeRange = '1h' | '6h' | '1d' | '7d' | '30d';
type ApiTimeRange = '1h' | '24h' | '7d' | '30d';
type ViewMode = 'grid' | 'list';
type MetricMode = 'sum' | 'replicas';

const TIME_RANGE_LABELS: DisplayTimeRange[] = ['1h', '6h', '1d', '7d', '30d'];

// Map display time ranges to API values
const API_TIME_RANGE: Record<DisplayTimeRange, ApiTimeRange> = {
    '1h': '1h',
    '6h': '1h',
    '1d': '24h',
    '7d': '7d',
    '30d': '30d',
};

// --- SVG line chart ---

interface ChartPoint {
    value: number;
    label?: string;
}

function formatYLabel(value: number, unit: 'vcpu' | 'mb' | 'bytes' | 'rps' | '%'): string {
    if (unit === 'vcpu') {
        return value === 0 ? '0 vCPU' : `${value.toFixed(1)} vCPU`;
    }
    if (unit === 'mb') {
        if (value === 0) return '0 B';
        if (value >= 1024) return `${(value / 1024).toFixed(0)} GB`;
        return `${value.toFixed(0)} MB`;
    }
    if (unit === 'bytes') {
        if (value === 0) return '0 B';
        if (value >= 1024 * 1024) return `${(value / 1024 / 1024).toFixed(1)} MB`;
        if (value >= 1024) return `${(value / 1024).toFixed(1)} KB`;
        return `${value.toFixed(0)} B`;
    }
    if (unit === 'rps') {
        return value === 0 ? '0' : value.toFixed(1);
    }
    return `${value.toFixed(0)}%`;
}

interface MetricLineChartProps {
    data: ChartPoint[];
    color: string;
    unit: 'vcpu' | 'mb' | 'bytes' | 'rps' | '%';
    secondaryData?: ChartPoint[];
    secondaryColor?: string;
}

function MetricLineChart({ data, color, unit, secondaryData, secondaryColor }: MetricLineChartProps) {
    const leftPad = 58;
    const rightPad = 8;
    const topPad = 8;
    const bottomPad = 22;
    const svgW = 400;
    const svgH = 100;
    const chartW = svgW - leftPad - rightPad;
    const chartH = svgH - topPad - bottomPad;

    const allValues = [
        ...data.map((d) => d.value),
        ...(secondaryData?.map((d) => d.value) ?? []),
    ];
    const maxVal = Math.max(...allValues, 0.001);

    // Round max to nice value for y-axis
    const niceMax = useMemo(() => {
        if (unit === 'vcpu') {
            const steps = [0.2, 0.5, 1, 2, 4, 8];
            return steps.find((s) => s >= maxVal * 1.2) ?? maxVal * 1.2;
        }
        if (unit === 'mb') {
            const steps = [100, 200, 500, 1024, 2048, 4096, 8192];
            return steps.find((s) => s >= maxVal * 1.2) ?? maxVal * 1.2;
        }
        return maxVal * 1.2 || 1;
    }, [maxVal, unit]);

    const numTicks = 5;
    const yTicks = useMemo(
        () =>
            Array.from({ length: numTicks }, (_, i) => {
                const value = (niceMax * i) / (numTicks - 1);
                const y = topPad + chartH - (i / (numTicks - 1)) * chartH;
                return { value, y };
            }),
        [niceMax, chartH]
    );

    function buildPath(pts: ChartPoint[]): string {
        if (pts.length < 2) return '';
        return pts
            .map((p, i) => {
                const x = leftPad + (i / (pts.length - 1)) * chartW;
                const y = topPad + chartH - (p.value / niceMax) * chartH;
                return `${i === 0 ? 'M' : 'L'} ${x} ${y}`;
            })
            .join(' ');
    }

    function buildArea(pts: ChartPoint[]): string {
        if (pts.length < 2) return '';
        const bottom = topPad + chartH;
        const firstX = leftPad;
        const lastX = leftPad + chartW;
        const line = pts
            .map((p, i) => {
                const x = leftPad + (i / (pts.length - 1)) * chartW;
                const y = bottom - (p.value / niceMax) * chartH;
                return `${x},${y}`;
            })
            .join(' ');
        return `${firstX},${bottom} ${line} ${lastX},${bottom}`;
    }

    // X-axis labels: first, middle, last of data.label
    const xLabels = useMemo(() => {
        if (data.length < 2) return [];
        const indices = [0, Math.floor(data.length / 2), data.length - 1];
        return indices.map((i) => ({ i, label: data[i]?.label ?? '' }));
    }, [data]);

    const linePath = buildPath(data);
    const areaPath = buildArea(data);
    const secondaryLinePath = secondaryData ? buildPath(secondaryData) : '';
    const secondaryAreaPath = secondaryData ? buildArea(secondaryData) : '';

    return (
        <svg
            viewBox={`0 0 ${svgW} ${svgH}`}
            className="w-full"
            style={{ height: svgH }}
            preserveAspectRatio="none"
        >
            {/* Gridlines */}
            {yTicks.map((tick, i) => (
                <line
                    key={i}
                    x1={leftPad}
                    y1={tick.y}
                    x2={svgW - rightPad}
                    y2={tick.y}
                    stroke="rgba(255,255,255,0.06)"
                    strokeWidth="1"
                />
            ))}

            {/* Y-axis labels */}
            {yTicks.map((tick, i) => (
                <text
                    key={i}
                    x={leftPad - 5}
                    y={tick.y + 3.5}
                    textAnchor="end"
                    fontSize="9"
                    fill="rgba(255,255,255,0.35)"
                    fontFamily="ui-monospace, monospace"
                >
                    {formatYLabel(tick.value, unit)}
                </text>
            ))}

            {/* Area fills */}
            {areaPath && (
                <polygon points={areaPath} fill={`${color}18`} />
            )}
            {secondaryAreaPath && secondaryColor && (
                <polygon points={secondaryAreaPath} fill={`${secondaryColor}18`} />
            )}

            {/* Lines */}
            {linePath && (
                <path
                    d={linePath}
                    fill="none"
                    stroke={color}
                    strokeWidth="1.5"
                    strokeLinejoin="round"
                    strokeLinecap="round"
                />
            )}
            {secondaryLinePath && secondaryColor && (
                <path
                    d={secondaryLinePath}
                    fill="none"
                    stroke={secondaryColor}
                    strokeWidth="1.5"
                    strokeLinejoin="round"
                    strokeLinecap="round"
                />
            )}

            {/* X-axis labels */}
            {xLabels.map(({ i, label }) => {
                const x = leftPad + (i / Math.max(data.length - 1, 1)) * chartW;
                return (
                    <text
                        key={i}
                        x={x}
                        y={svgH - 4}
                        textAnchor={i === 0 ? 'start' : i === data.length - 1 ? 'end' : 'middle'}
                        fontSize="9"
                        fill="rgba(255,255,255,0.3)"
                        fontFamily="ui-sans-serif, sans-serif"
                    >
                        {label}
                    </text>
                );
            })}
        </svg>
    );
}

// --- Empty state for a metric card ---

function MetricEmptyState({ message, hint }: { message: string; hint: string }) {
    return (
        <div className="flex flex-col items-center justify-center py-6 text-center">
            <p className="text-sm font-medium text-foreground-muted">{message}</p>
            <p className="mt-1 text-xs text-foreground-subtle leading-relaxed max-w-[200px]">{hint}</p>
        </div>
    );
}

// --- Metric card header toggle (Sum / Replicas) ---

function SumReplicasToggle({
    mode,
    onChange,
}: {
    mode: MetricMode;
    onChange: (m: MetricMode) => void;
}) {
    return (
        <div className="flex items-center gap-1 text-xs">
            <button
                onClick={() => onChange('sum')}
                className={`flex items-center gap-1 px-1.5 py-0.5 rounded transition-colors ${
                    mode === 'sum'
                        ? 'text-primary font-medium'
                        : 'text-foreground-subtle hover:text-foreground-muted'
                }`}
            >
                {mode === 'sum' && (
                    <span className="h-1.5 w-1.5 rounded-full bg-primary inline-block" />
                )}
                Sum
            </button>
            <span className="text-foreground-disabled">○</span>
            <button
                onClick={() => onChange('replicas')}
                className={`px-1.5 py-0.5 rounded transition-colors ${
                    mode === 'replicas'
                        ? 'text-primary font-medium'
                        : 'text-foreground-subtle hover:text-foreground-muted'
                }`}
            >
                Replicas
            </button>
        </div>
    );
}

// --- Generate x-axis time labels based on time range and data length ---

function generateTimeLabels(timeRange: DisplayTimeRange, count: number): string[] {
    const now = new Date();
    const labels: string[] = [];
    let totalMs: number;

    switch (timeRange) {
        case '1h':
            totalMs = 60 * 60 * 1000;
            break;
        case '6h':
            totalMs = 6 * 60 * 60 * 1000;
            break;
        case '1d':
            totalMs = 24 * 60 * 60 * 1000;
            break;
        case '7d':
            totalMs = 7 * 24 * 60 * 60 * 1000;
            break;
        case '30d':
            totalMs = 30 * 24 * 60 * 60 * 1000;
            break;
    }

    const step = totalMs / Math.max(count - 1, 1);
    for (let i = 0; i < count; i++) {
        const t = new Date(now.getTime() - totalMs + i * step);
        if (timeRange === '30d') {
            labels.push(t.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }));
        } else if (timeRange === '7d') {
            labels.push(t.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' }));
        } else {
            labels.push(
                t.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })
            );
        }
    }
    return labels;
}

// --- Main MetricsTab component ---

interface MetricsTabProps {
    service: SelectedService;
}

export function MetricsTab({ service }: MetricsTabProps) {
    const [timeRange, setTimeRange] = useState<DisplayTimeRange>('1h');
    const [viewMode, setViewMode] = useState<ViewMode>('grid');
    const [cpuMode] = useState<MetricMode>('sum');
    const [memMode] = useState<MetricMode>('sum');

    const apiTimeRange = API_TIME_RANGE[timeRange];

    const { metrics: sentinelMetrics, historicalData, isLoading, error, refetch } = useSentinelMetrics({
        serverUuid: service.serverUuid || '',
        timeRange: apiTimeRange,
        autoRefresh: !!service.serverUuid,
        refreshInterval: 30000,
    });

    const { metrics: appMetrics } = useApplicationMetrics({
        applicationUuid: service.uuid,
        autoRefresh: true,
        refreshInterval: 15000,
        enabled: service.type === 'app',
    });

    // Build chart data with x-axis labels from historical data
    const cpuPoints: ChartPoint[] = useMemo(() => {
        const raw = historicalData?.cpu?.data ?? [];
        if (raw.length === 0) return [];
        const timeLabels = generateTimeLabels(timeRange, raw.length);
        return raw.map((d, i) => ({ value: d.value, label: timeLabels[i] }));
    }, [historicalData, timeRange]);

    const memPoints: ChartPoint[] = useMemo(() => {
        const raw = historicalData?.memory?.data ?? [];
        if (raw.length === 0) return [];
        const timeLabels = generateTimeLabels(timeRange, raw.length);
        // Convert percentage to MB if total is known
        return raw.map((d, i) => ({ value: d.value, label: timeLabels[i] }));
    }, [historicalData, timeRange]);

    const netInPoints: ChartPoint[] = useMemo(() => {
        const raw = historicalData?.network?.data ?? [];
        if (raw.length === 0) return [];
        const timeLabels = generateTimeLabels(timeRange, raw.length);
        return raw.map((d, i) => ({ value: d.value, label: timeLabels[i] }));
    }, [historicalData, timeRange]);

    // Current values from appMetrics (real-time container stats)
    const cpuCurrent = appMetrics?.cpu?.formatted ?? sentinelMetrics?.cpu?.current ?? null;
    const memCurrent = appMetrics
        ? `${appMetrics.memory.used} / ${appMetrics.memory.limit}`
        : sentinelMetrics?.memory?.current ?? null;
    const netIn = appMetrics?.network?.rx ?? sentinelMetrics?.network?.in ?? null;
    const netOut = appMetrics?.network?.tx ?? sentinelMetrics?.network?.out ?? null;

    const handleRefresh = useCallback(async () => {
        await refetch();
    }, [refetch]);

    if (!service.serverUuid) {
        return (
            <div className="flex flex-col items-center justify-center py-14 text-center">
                <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-background-secondary">
                    <svg className="h-5 w-5 text-foreground-subtle" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                        <path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <p className="text-sm font-medium text-foreground-muted">Metrics unavailable</p>
                <p className="mt-1 text-xs text-foreground-subtle">No server associated with this service</p>
            </div>
        );
    }

    const isGridView = viewMode === 'grid';

    return (
        <div className="flex flex-col gap-3">
            {/* Toolbar: time range + view toggle */}
            <div className="flex items-center justify-between">
                {/* Time range buttons */}
                <div className="flex items-center rounded-md border border-border overflow-hidden">
                    {TIME_RANGE_LABELS.map((range, idx) => (
                        <button
                            key={range}
                            onClick={() => setTimeRange(range)}
                            className={[
                                'px-2.5 py-1 text-xs font-medium transition-colors',
                                idx !== 0 ? 'border-l border-border' : '',
                                timeRange === range
                                    ? 'bg-background-tertiary text-foreground'
                                    : 'bg-background text-foreground-muted hover:text-foreground hover:bg-background-secondary',
                            ].join(' ')}
                        >
                            {range}
                        </button>
                    ))}
                </div>

                {/* Right: refresh + view toggle */}
                <div className="flex items-center gap-1.5">
                    <button
                        onClick={handleRefresh}
                        title="Refresh"
                        className="rounded p-1.5 text-foreground-muted hover:text-foreground hover:bg-background-secondary transition-colors"
                    >
                        <RefreshCw className={`h-3.5 w-3.5 ${isLoading ? 'animate-spin' : ''}`} />
                    </button>

                    <div className="flex items-center rounded-md border border-border overflow-hidden">
                        {/* List view */}
                        <button
                            onClick={() => setViewMode('list')}
                            title="List view"
                            className={`p-1.5 transition-colors ${
                                viewMode === 'list'
                                    ? 'bg-background-tertiary text-foreground'
                                    : 'bg-background text-foreground-muted hover:text-foreground'
                            }`}
                        >
                            <svg className="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none">
                                <rect x="2" y="3" width="12" height="2" rx="1" fill="currentColor" />
                                <rect x="2" y="7" width="12" height="2" rx="1" fill="currentColor" />
                                <rect x="2" y="11" width="12" height="2" rx="1" fill="currentColor" />
                            </svg>
                        </button>
                        {/* Grid view */}
                        <button
                            onClick={() => setViewMode('grid')}
                            title="Grid view"
                            className={`p-1.5 border-l border-border transition-colors ${
                                viewMode === 'grid'
                                    ? 'bg-background-tertiary text-foreground'
                                    : 'bg-background text-foreground-muted hover:text-foreground'
                            }`}
                        >
                            <svg className="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none">
                                <rect x="2" y="2" width="5" height="5" rx="1" fill="currentColor" />
                                <rect x="9" y="2" width="5" height="5" rx="1" fill="currentColor" />
                                <rect x="2" y="9" width="5" height="5" rx="1" fill="currentColor" />
                                <rect x="9" y="9" width="5" height="5" rx="1" fill="currentColor" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {/* Error banner */}
            {error && (
                <div className="rounded-lg border border-yellow-500/20 bg-yellow-500/8 px-3 py-2 text-xs text-yellow-400">
                    Unable to reach metrics agent — showing cached data.
                </div>
            )}

            {/* Metric cards grid */}
            <div className={isGridView ? 'grid grid-cols-2 gap-2' : 'flex flex-col gap-2'}>

                {/* CPU */}
                <MetricCard
                    title="CPU"
                    currentValue={cpuCurrent}
                    currentUnit=""
                    accentColor="#818cf8"
                    toggle={<SumReplicasToggle mode={cpuMode} onChange={() => {}} />}
                    chart={
                        cpuPoints.length > 0 ? (
                            <MetricLineChart
                                data={cpuPoints}
                                color="#818cf8"
                                unit="vcpu"
                            />
                        ) : null
                    }
                    isLoading={isLoading}
                    avg={historicalData?.cpu?.average}
                    peak={historicalData?.cpu?.peak}
                />

                {/* Memory */}
                <MetricCard
                    title="Memory"
                    currentValue={memCurrent}
                    currentUnit=""
                    accentColor="#a78bfa"
                    toggle={<SumReplicasToggle mode={memMode} onChange={() => {}} />}
                    chart={
                        memPoints.length > 0 ? (
                            <MetricLineChart
                                data={memPoints}
                                color="#a78bfa"
                                unit="mb"
                            />
                        ) : null
                    }
                    isLoading={isLoading}
                    avg={historicalData?.memory?.average}
                    peak={historicalData?.memory?.peak}
                />

                {/* Public Network Traffic */}
                <MetricCard
                    title="Public Network Traffic"
                    currentValue={null}
                    currentUnit=""
                    accentColor="#3b82f6"
                    toggle={
                        <div className="flex items-center gap-2 text-xs text-foreground-muted">
                            <span className="flex items-center gap-1">
                                <span className="h-2 w-2 rounded-full bg-amber-400 inline-block" />
                                Egress
                            </span>
                            <span className="flex items-center gap-1">
                                <span className="h-2 w-2 rounded-full bg-blue-400 inline-block" />
                                Ingress
                            </span>
                        </div>
                    }
                    chart={
                        netInPoints.length > 0 ? (
                            <MetricLineChart
                                data={netInPoints}
                                color="#3b82f6"
                                unit="bytes"
                            />
                        ) : null
                    }
                    isLoading={isLoading}
                    subtitle={
                        netIn || netOut
                            ? `In: ${netIn ?? 'N/A'} · Out: ${netOut ?? 'N/A'}`
                            : null
                    }
                />

                {/* Requests */}
                <MetricCard
                    title="Requests"
                    currentValue={null}
                    currentUnit=""
                    accentColor="#34d399"
                    toggle={null}
                    chart={null}
                    isLoading={false}
                    emptyState={
                        <MetricEmptyState
                            message="No request metrics available"
                            hint="Request metrics will appear here once your service receives traffic"
                        />
                    }
                />

                {/* Request Error Rate */}
                <MetricCard
                    title="Request Error Rate"
                    currentValue={null}
                    currentUnit=""
                    accentColor="#f87171"
                    toggle={null}
                    chart={null}
                    isLoading={false}
                    emptyState={
                        <MetricEmptyState
                            message="No error rate metrics available"
                            hint="Request error rate metrics will appear here once your service receives traffic"
                        />
                    }
                />

                {/* Response Time */}
                <MetricCard
                    title="Response Time"
                    currentValue={null}
                    currentUnit=""
                    accentColor="#fb923c"
                    toggle={null}
                    chart={null}
                    isLoading={false}
                    emptyState={
                        <MetricEmptyState
                            message="No response time metrics available"
                            hint="Response time metrics will appear here once your service receives traffic"
                        />
                    }
                />
            </div>
        </div>
    );
}

// --- Reusable MetricCard component ---

interface MetricCardProps {
    title: string;
    currentValue: string | null;
    currentUnit: string;
    accentColor: string;
    toggle: React.ReactNode;
    chart: React.ReactNode;
    isLoading: boolean;
    avg?: string;
    peak?: string;
    subtitle?: string | null;
    emptyState?: React.ReactNode;
}

function MetricCard({
    title,
    currentValue,
    accentColor,
    toggle,
    chart,
    isLoading,
    avg,
    peak,
    subtitle,
    emptyState,
}: MetricCardProps) {
    const hasData = !!chart;

    return (
        <div className="rounded-lg border border-border bg-background-secondary overflow-hidden">
            {/* Card header */}
            <div className="flex items-center justify-between px-3 pt-3 pb-1">
                <div className="flex items-center gap-1.5">
                    <span
                        className="inline-block h-2 w-2 rounded-full flex-shrink-0"
                        style={{ backgroundColor: accentColor }}
                    />
                    <span className="text-xs font-semibold text-foreground leading-tight">{title}</span>
                </div>
                {toggle && <div className="ml-2 shrink-0">{toggle}</div>}
            </div>

            {/* Current value row */}
            {currentValue && (
                <div className="px-3 pb-1">
                    <span className="text-[11px] text-foreground-muted font-mono">{currentValue}</span>
                </div>
            )}
            {subtitle && (
                <div className="px-3 pb-1">
                    <span className="text-[11px] text-foreground-muted font-mono">{subtitle}</span>
                </div>
            )}

            {/* Chart or empty state */}
            <div className="px-2 pb-2">
                {isLoading && !hasData ? (
                    <div className="flex items-center justify-center py-6">
                        <RefreshCw className="h-4 w-4 animate-spin text-foreground-subtle" />
                    </div>
                ) : hasData ? (
                    <>
                        {chart}
                        {(avg || peak) && (
                            <div className="mt-1 flex justify-between px-1 text-[10px] text-foreground-subtle">
                                {avg && <span>Avg: {avg}</span>}
                                {peak && <span>Peak: {peak}</span>}
                            </div>
                        )}
                    </>
                ) : emptyState ? (
                    emptyState
                ) : (
                    <div className="py-6 text-center text-xs text-foreground-subtle">No data</div>
                )}
            </div>
        </div>
    );
}
