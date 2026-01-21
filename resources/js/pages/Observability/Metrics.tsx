import { AppLayout } from '@/components/layout';
import { useState } from 'react';
import { Card, CardHeader, CardTitle, CardContent, Button, Badge } from '@/components/ui';
import { LineChart, BarChart } from '@/components/ui/Chart';
import {
    Download,
    RefreshCw,
    Cpu,
    HardDrive,
    Network,
    MemoryStick,
    ChevronDown,
} from 'lucide-react';

interface MetricData {
    label: string;
    value: number;
}

interface MetricChart {
    id: string;
    title: string;
    type: 'cpu' | 'memory' | 'network' | 'disk';
    unit: string;
    data: MetricData[];
    current: string;
    avg: string;
    max: string;
    icon: any;
}

const timeRanges = [
    { label: 'Last 1 hour', value: '1h' },
    { label: 'Last 6 hours', value: '6h' },
    { label: 'Last 24 hours', value: '24h' },
    { label: 'Last 7 days', value: '7d' },
    { label: 'Last 30 days', value: '30d' },
];

const aggregations = [
    { label: 'Average', value: 'avg' },
    { label: 'Maximum', value: 'max' },
    { label: 'Minimum', value: 'min' },
    { label: '95th Percentile', value: 'p95' },
    { label: '99th Percentile', value: 'p99' },
];

const services = [
    { label: 'All Services', value: 'all' },
    { label: 'API Gateway', value: 'api-gateway' },
    { label: 'Auth Service', value: 'auth-service' },
    { label: 'Database Primary', value: 'database-primary' },
    { label: 'Cache Layer', value: 'cache-layer' },
    { label: 'Worker Queue', value: 'worker-queue' },
];

// Generate mock metric data
const generateMetricData = (points: number, min: number, max: number): MetricData[] => {
    const labels = ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00', '23:59'];
    return labels.slice(0, points).map((label, i) => ({
        label,
        value: Math.floor(Math.random() * (max - min) + min),
    }));
};

const metricCharts: MetricChart[] = [
    {
        id: '1',
        title: 'CPU Usage',
        type: 'cpu',
        unit: '%',
        data: generateMetricData(7, 20, 85),
        current: '42.3%',
        avg: '38.7%',
        max: '85.2%',
        icon: Cpu,
    },
    {
        id: '2',
        title: 'Memory Usage',
        type: 'memory',
        unit: 'GB',
        data: generateMetricData(7, 2, 8),
        current: '6.2 GB',
        avg: '5.8 GB',
        max: '7.9 GB',
        icon: MemoryStick,
    },
    {
        id: '3',
        title: 'Network I/O',
        type: 'network',
        unit: 'MB/s',
        data: generateMetricData(7, 10, 150),
        current: '85.3 MB/s',
        avg: '72.4 MB/s',
        max: '145.8 MB/s',
        icon: Network,
    },
    {
        id: '4',
        title: 'Disk Usage',
        type: 'disk',
        unit: '%',
        data: generateMetricData(7, 45, 75),
        current: '62.8%',
        avg: '58.3%',
        max: '74.1%',
        icon: HardDrive,
    },
];

function MetricChartCard({ chart }: { chart: MetricChart }) {
    const Icon = chart.icon;

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                            <Icon className="h-4 w-4 text-primary" />
                        </div>
                        <CardTitle>{chart.title}</CardTitle>
                    </div>
                    <Badge variant="default">{chart.current}</Badge>
                </div>
            </CardHeader>
            <CardContent>
                <LineChart data={chart.data} height={180} />
                <div className="mt-4 grid grid-cols-3 gap-4 border-t border-border pt-4">
                    <div>
                        <p className="text-xs text-foreground-muted">Current</p>
                        <p className="text-sm font-semibold text-foreground">{chart.current}</p>
                    </div>
                    <div>
                        <p className="text-xs text-foreground-muted">Average</p>
                        <p className="text-sm font-semibold text-foreground">{chart.avg}</p>
                    </div>
                    <div>
                        <p className="text-xs text-foreground-muted">Peak</p>
                        <p className="text-sm font-semibold text-foreground">{chart.max}</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

export default function ObservabilityMetrics() {
    const [selectedService, setSelectedService] = useState('all');
    const [selectedTimeRange, setSelectedTimeRange] = useState('24h');
    const [selectedAggregation, setSelectedAggregation] = useState('avg');
    const [customQuery, setCustomQuery] = useState('');

    const handleExport = () => {
        // Mock export functionality
        console.log('Exporting metrics data...');
    };

    const handleRefresh = () => {
        // Mock refresh functionality
        console.log('Refreshing metrics...');
    };

    return (
        <AppLayout
            title="Metrics"
            breadcrumbs={[{ label: 'Observability', href: '/observability' }, { label: 'Metrics' }]}
        >
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Metrics Dashboard</h1>
                        <p className="text-foreground-muted">Monitor system performance and resource utilization</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="secondary" size="sm" onClick={handleRefresh}>
                            <RefreshCw className="mr-2 h-4 w-4" />
                            Refresh
                        </Button>
                        <Button variant="secondary" size="sm" onClick={handleExport}>
                            <Download className="mr-2 h-4 w-4" />
                            Export Data
                        </Button>
                    </div>
                </div>

                {/* Filters */}
                <Card>
                    <CardContent className="p-4">
                        <div className="grid gap-4 md:grid-cols-3">
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Service
                                </label>
                                <select
                                    value={selectedService}
                                    onChange={(e) => setSelectedService(e.target.value)}
                                    className="w-full rounded-md border border-border bg-background-secondary px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                                >
                                    {services.map((service) => (
                                        <option key={service.value} value={service.value}>
                                            {service.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Time Range
                                </label>
                                <select
                                    value={selectedTimeRange}
                                    onChange={(e) => setSelectedTimeRange(e.target.value)}
                                    className="w-full rounded-md border border-border bg-background-secondary px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                                >
                                    {timeRanges.map((range) => (
                                        <option key={range.value} value={range.value}>
                                            {range.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Aggregation
                                </label>
                                <select
                                    value={selectedAggregation}
                                    onChange={(e) => setSelectedAggregation(e.target.value)}
                                    className="w-full rounded-md border border-border bg-background-secondary px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                                >
                                    {aggregations.map((agg) => (
                                        <option key={agg.value} value={agg.value}>
                                            {agg.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Custom Query */}
                <Card>
                    <CardHeader>
                        <CardTitle>Custom Metric Query</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            <textarea
                                value={customQuery}
                                onChange={(e) => setCustomQuery(e.target.value)}
                                placeholder="Enter PromQL or custom metric query..."
                                className="min-h-[100px] w-full rounded-md border border-border bg-background-secondary px-3 py-2 text-sm text-foreground placeholder-foreground-muted focus:outline-none focus:ring-2 focus:ring-primary"
                            />
                            <Button size="sm">
                                <ChevronDown className="mr-2 h-4 w-4" />
                                Execute Query
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Metric Charts */}
                <div className="grid gap-6 md:grid-cols-2">
                    {metricCharts.map((chart) => (
                        <MetricChartCard key={chart.id} chart={chart} />
                    ))}
                </div>

                {/* Additional Metrics */}
                <Card>
                    <CardHeader>
                        <CardTitle>Request Metrics</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div>
                                <div className="mb-2 flex items-center justify-between">
                                    <span className="text-sm font-medium text-foreground">
                                        Requests per Second
                                    </span>
                                    <span className="text-sm text-foreground-muted">~2.5k req/s</span>
                                </div>
                                <BarChart
                                    data={generateMetricData(7, 1000, 3000)}
                                    height={150}
                                    color="rgb(52, 211, 153)"
                                />
                            </div>
                            <div className="border-t border-border pt-4">
                                <div className="mb-2 flex items-center justify-between">
                                    <span className="text-sm font-medium text-foreground">
                                        Error Rate
                                    </span>
                                    <span className="text-sm text-foreground-muted">0.23%</span>
                                </div>
                                <BarChart
                                    data={generateMetricData(7, 5, 25)}
                                    height={150}
                                    color="rgb(248, 113, 113)"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
