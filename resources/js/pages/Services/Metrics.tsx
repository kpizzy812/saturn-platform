import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, Button } from '@/components/ui';
import { BarChart3, Cpu, MemoryStick, Network, Activity, RefreshCw } from 'lucide-react';
import type { Service } from '@/types';

interface Props {
    service: Service;
}

type TimeRange = '1h' | '6h' | '24h' | '7d';

export function MetricsTab({ service }: Props) {
    const [timeRange, setTimeRange] = useState<TimeRange>('24h');
    const [autoRefresh, setAutoRefresh] = useState(false);

    const timeRangeOptions: { value: TimeRange; label: string }[] = [
        { value: '1h', label: '1 Hour' },
        { value: '6h', label: '6 Hours' },
        { value: '24h', label: '24 Hours' },
        { value: '7d', label: '7 Days' },
    ];

    return (
        <div className="space-y-4">
            {/* Controls */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-medium text-foreground">Time Range:</span>
                            {timeRangeOptions.map((option) => (
                                <button
                                    key={option.value}
                                    onClick={() => setTimeRange(option.value)}
                                    className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                                        timeRange === option.value
                                            ? 'bg-foreground text-background'
                                            : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
                                    }`}
                                >
                                    {option.label}
                                </button>
                            ))}
                        </div>
                        <div className="flex items-center gap-2">
                            <button
                                onClick={() => setAutoRefresh(!autoRefresh)}
                                className={`flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                                    autoRefresh
                                        ? 'bg-primary/10 text-primary'
                                        : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
                                }`}
                            >
                                <RefreshCw className={`h-4 w-4 ${autoRefresh ? 'animate-spin' : ''}`} />
                                Auto-refresh {autoRefresh ? 'On' : 'Off'}
                            </button>
                            <Button variant="secondary" size="sm">
                                <RefreshCw className="mr-2 h-4 w-4" />
                                Refresh Now
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Current Metrics Summary */}
            <div className="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-info/10">
                                <Cpu className="h-5 w-5 text-info" />
                            </div>
                            <div>
                                <p className="text-sm text-foreground-muted">CPU Usage</p>
                                <p className="text-2xl font-bold text-foreground">23%</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-warning/10">
                                <MemoryStick className="h-5 w-5 text-warning" />
                            </div>
                            <div>
                                <p className="text-sm text-foreground-muted">Memory</p>
                                <p className="text-2xl font-bold text-foreground">512 MB</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                <Network className="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <p className="text-sm text-foreground-muted">Network I/O</p>
                                <p className="text-2xl font-bold text-foreground">1.2 MB/s</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-success/10">
                                <Activity className="h-5 w-5 text-success" />
                            </div>
                            <div>
                                <p className="text-sm text-foreground-muted">Requests</p>
                                <p className="text-2xl font-bold text-foreground">1.2k/min</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* CPU Usage Graph */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Cpu className="h-5 w-5 text-info" />
                        CPU Usage
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <MetricChart
                        title="CPU Usage Over Time"
                        color="info"
                        currentValue="23%"
                        avgValue="18%"
                        peakValue="45%"
                    />
                </CardContent>
            </Card>

            {/* Memory Usage Graph */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <MemoryStick className="h-5 w-5 text-warning" />
                        Memory Usage
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <MetricChart
                        title="Memory Usage Over Time"
                        color="warning"
                        currentValue="512 MB"
                        avgValue="480 MB"
                        peakValue="768 MB"
                    />
                </CardContent>
            </Card>

            {/* Network I/O Graph */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Network className="h-5 w-5 text-primary" />
                        Network I/O
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <MetricChart
                        title="Network Traffic Over Time"
                        color="primary"
                        currentValue="1.2 MB/s"
                        avgValue="0.8 MB/s"
                        peakValue="3.4 MB/s"
                    />
                </CardContent>
            </Card>

            {/* Request Count Graph */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <BarChart3 className="h-5 w-5 text-success" />
                        Request Count
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <MetricChart
                        title="Requests Per Minute"
                        color="success"
                        currentValue="1.2k/min"
                        avgValue="980/min"
                        peakValue="2.1k/min"
                    />
                </CardContent>
            </Card>
        </div>
    );
}

interface MetricChartProps {
    title: string;
    color: 'info' | 'warning' | 'primary' | 'success';
    currentValue: string;
    avgValue: string;
    peakValue: string;
}

function MetricChart({ title, color, currentValue, avgValue, peakValue }: MetricChartProps) {
    const colorClasses = {
        info: 'bg-info/10 border-info/20',
        warning: 'bg-warning/10 border-warning/20',
        primary: 'bg-primary/10 border-primary/20',
        success: 'bg-success/10 border-success/20',
    };

    return (
        <div>
            {/* Stats Summary */}
            <div className="mb-4 grid grid-cols-3 gap-4">
                <div>
                    <p className="text-xs text-foreground-muted">Current</p>
                    <p className="text-lg font-semibold text-foreground">{currentValue}</p>
                </div>
                <div>
                    <p className="text-xs text-foreground-muted">Average</p>
                    <p className="text-lg font-semibold text-foreground">{avgValue}</p>
                </div>
                <div>
                    <p className="text-xs text-foreground-muted">Peak</p>
                    <p className="text-lg font-semibold text-foreground">{peakValue}</p>
                </div>
            </div>

            {/* Chart Placeholder */}
            <div
                className={`flex h-64 items-center justify-center rounded-lg border ${colorClasses[color]}`}
            >
                <div className="text-center">
                    <BarChart3 className={`mx-auto h-12 w-12 text-${color}`} />
                    <p className="mt-2 text-sm font-medium text-foreground-muted">
                        {title} Chart
                    </p>
                    <p className="mt-1 text-xs text-foreground-subtle">
                        Chart visualization will be displayed here
                    </p>
                </div>
            </div>
        </div>
    );
}
