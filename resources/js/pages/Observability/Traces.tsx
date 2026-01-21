import { AppLayout } from '@/components/layout';
import { useState } from 'react';
import { Card, CardHeader, CardTitle, CardContent, Badge, Button } from '@/components/ui';
import {
    Clock,
    AlertCircle,
    CheckCircle,
    ChevronRight,
    Filter,
    Search,
    GitBranch,
} from 'lucide-react';

interface Span {
    id: string;
    name: string;
    service: string;
    duration: number;
    startTime: number;
    status: 'success' | 'error';
    tags?: Record<string, string>;
}

interface Trace {
    id: string;
    name: string;
    duration: number;
    timestamp: string;
    status: 'success' | 'error';
    spans: Span[];
    services: string[];
}

const mockTraces: Trace[] = [
    {
        id: 'trace-1',
        name: 'GET /api/users',
        duration: 245,
        timestamp: '2 minutes ago',
        status: 'success',
        services: ['API Gateway', 'Auth Service', 'Database'],
        spans: [
            { id: 's1', name: 'HTTP GET', service: 'API Gateway', duration: 5, startTime: 0, status: 'success' },
            { id: 's2', name: 'Authenticate', service: 'Auth Service', duration: 45, startTime: 5, status: 'success' },
            { id: 's3', name: 'Query users', service: 'Database', duration: 180, startTime: 50, status: 'success' },
            { id: 's4', name: 'Format response', service: 'API Gateway', duration: 15, startTime: 230, status: 'success' },
        ],
    },
    {
        id: 'trace-2',
        name: 'POST /api/orders',
        duration: 1250,
        timestamp: '5 minutes ago',
        status: 'error',
        services: ['API Gateway', 'Order Service', 'Payment Service', 'Database'],
        spans: [
            { id: 's1', name: 'HTTP POST', service: 'API Gateway', duration: 8, startTime: 0, status: 'success' },
            { id: 's2', name: 'Validate order', service: 'Order Service', duration: 35, startTime: 8, status: 'success' },
            { id: 's3', name: 'Process payment', service: 'Payment Service', duration: 850, startTime: 43, status: 'error', tags: { error: 'Payment gateway timeout' } },
            { id: 's4', name: 'Rollback transaction', service: 'Database', duration: 320, startTime: 893, status: 'success' },
            { id: 's5', name: 'Error response', service: 'API Gateway', duration: 37, startTime: 1213, status: 'success' },
        ],
    },
    {
        id: 'trace-3',
        name: 'GET /api/products/search',
        duration: 85,
        timestamp: '8 minutes ago',
        status: 'success',
        services: ['API Gateway', 'Search Service', 'Cache'],
        spans: [
            { id: 's1', name: 'HTTP GET', service: 'API Gateway', duration: 3, startTime: 0, status: 'success' },
            { id: 's2', name: 'Cache lookup', service: 'Cache', duration: 5, startTime: 3, status: 'success' },
            { id: 's3', name: 'Search query', service: 'Search Service', duration: 65, startTime: 8, status: 'success' },
            { id: 's4', name: 'Return results', service: 'API Gateway', duration: 12, startTime: 73, status: 'success' },
        ],
    },
    {
        id: 'trace-4',
        name: 'POST /api/auth/login',
        duration: 156,
        timestamp: '12 minutes ago',
        status: 'success',
        services: ['API Gateway', 'Auth Service', 'Database', 'Cache'],
        spans: [
            { id: 's1', name: 'HTTP POST', service: 'API Gateway', duration: 4, startTime: 0, status: 'success' },
            { id: 's2', name: 'Validate credentials', service: 'Auth Service', duration: 25, startTime: 4, status: 'success' },
            { id: 's3', name: 'Query user', service: 'Database', duration: 95, startTime: 29, status: 'success' },
            { id: 's4', name: 'Generate token', service: 'Auth Service', duration: 18, startTime: 124, status: 'success' },
            { id: 's5', name: 'Cache session', service: 'Cache', duration: 8, startTime: 142, status: 'success' },
            { id: 's6', name: 'Return token', service: 'API Gateway', duration: 6, startTime: 150, status: 'success' },
        ],
    },
];

function TraceListItem({ trace, onClick }: { trace: Trace; onClick: () => void }) {
    const statusConfig = {
        success: {
            icon: CheckCircle,
            color: 'text-emerald-400',
            badge: 'success' as const,
        },
        error: {
            icon: AlertCircle,
            color: 'text-red-400',
            badge: 'danger' as const,
        },
    };

    const config = statusConfig[trace.status];
    const Icon = config.icon;

    return (
        <div
            onClick={onClick}
            className="group cursor-pointer rounded-lg border border-border bg-background-secondary p-4 transition-all hover:border-border-hover hover:bg-background-tertiary"
        >
            <div className="flex items-start justify-between">
                <div className="flex items-start gap-3">
                    <Icon className={`mt-1 h-5 w-5 ${config.color}`} />
                    <div>
                        <div className="flex items-center gap-2">
                            <h3 className="font-medium text-foreground">{trace.name}</h3>
                            <Badge variant={config.badge}>{trace.status}</Badge>
                        </div>
                        <div className="mt-1 flex items-center gap-3 text-sm text-foreground-muted">
                            <span className="flex items-center gap-1">
                                <Clock className="h-3 w-3" />
                                {trace.duration}ms
                            </span>
                            <span>{trace.timestamp}</span>
                            <span>{trace.spans.length} spans</span>
                        </div>
                        <div className="mt-2 flex flex-wrap gap-1">
                            {trace.services.map((service) => (
                                <span
                                    key={service}
                                    className="rounded-full bg-background-tertiary px-2 py-0.5 text-xs text-foreground-muted"
                                >
                                    {service}
                                </span>
                            ))}
                        </div>
                    </div>
                </div>
                <ChevronRight className="h-5 w-5 text-foreground-muted transition-transform group-hover:translate-x-1" />
            </div>
        </div>
    );
}

function WaterfallView({ trace }: { trace: Trace }) {
    const totalDuration = trace.duration;

    return (
        <div className="space-y-2">
            {trace.spans.map((span) => {
                const leftPercent = (span.startTime / totalDuration) * 100;
                const widthPercent = (span.duration / totalDuration) * 100;
                const color = span.status === 'error' ? 'bg-red-500' : 'bg-primary';

                return (
                    <div key={span.id} className="group relative">
                        <div className="mb-1 flex items-center justify-between text-sm">
                            <div className="flex items-center gap-2">
                                <span className="font-mono text-xs text-foreground-muted">{span.service}</span>
                                <span className="text-foreground">{span.name}</span>
                            </div>
                            <span className="text-foreground-muted">{span.duration}ms</span>
                        </div>
                        <div className="relative h-8 rounded-md bg-background-tertiary">
                            <div
                                className={`absolute h-full rounded-md ${color} transition-all group-hover:opacity-80`}
                                style={{
                                    left: `${leftPercent}%`,
                                    width: `${widthPercent}%`,
                                }}
                            >
                                <div className="flex h-full items-center justify-center text-xs font-medium text-white">
                                    {span.duration > 50 && `${span.duration}ms`}
                                </div>
                            </div>
                        </div>
                        {span.tags && (
                            <div className="mt-1 text-xs text-red-400">
                                {Object.entries(span.tags).map(([key, value]) => (
                                    <span key={key}>
                                        {key}: {value}
                                    </span>
                                ))}
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

function ServiceDependencyGraph({ trace }: { trace: Trace }) {
    return (
        <div className="flex items-center justify-center gap-3 py-6">
            {trace.services.map((service, index) => (
                <div key={service} className="flex items-center gap-3">
                    <div className="flex flex-col items-center">
                        <div className="flex h-12 w-12 items-center justify-center rounded-full border-2 border-primary bg-primary/10">
                            <GitBranch className="h-5 w-5 text-primary" />
                        </div>
                        <span className="mt-2 text-xs font-medium text-foreground">{service}</span>
                    </div>
                    {index < trace.services.length - 1 && (
                        <ChevronRight className="h-5 w-5 text-foreground-muted" />
                    )}
                </div>
            ))}
        </div>
    );
}

export default function ObservabilityTraces() {
    const [selectedTrace, setSelectedTrace] = useState<Trace | null>(null);
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<'all' | 'success' | 'error'>('all');
    const [durationFilter, setDurationFilter] = useState<'all' | 'fast' | 'slow'>('all');

    const filteredTraces = mockTraces.filter((trace) => {
        const searchMatch =
            !searchQuery ||
            trace.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            trace.services.some((s) => s.toLowerCase().includes(searchQuery.toLowerCase()));
        const statusMatch = statusFilter === 'all' || trace.status === statusFilter;
        const durationMatch =
            durationFilter === 'all' ||
            (durationFilter === 'fast' && trace.duration < 200) ||
            (durationFilter === 'slow' && trace.duration >= 200);

        return searchMatch && statusMatch && durationMatch;
    });

    return (
        <AppLayout
            title="Traces"
            breadcrumbs={[{ label: 'Observability', href: '/observability' }, { label: 'Traces' }]}
        >
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Distributed Tracing</h1>
                        <p className="text-foreground-muted">Track requests across your microservices</p>
                    </div>
                </div>

                {/* Filters */}
                <Card>
                    <CardContent className="p-4">
                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                <input
                                    type="text"
                                    placeholder="Search traces..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="w-full rounded-md border border-border bg-background-secondary py-2 pl-10 pr-3 text-sm text-foreground placeholder-foreground-muted focus:outline-none focus:ring-2 focus:ring-primary"
                                />
                            </div>
                            <select
                                value={statusFilter}
                                onChange={(e) => setStatusFilter(e.target.value as 'all' | 'success' | 'error')}
                                className="rounded-md border border-border bg-background-secondary px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                            >
                                <option value="all">All Statuses</option>
                                <option value="success">Success</option>
                                <option value="error">Error</option>
                            </select>
                            <select
                                value={durationFilter}
                                onChange={(e) => setDurationFilter(e.target.value as 'all' | 'fast' | 'slow')}
                                className="rounded-md border border-border bg-background-secondary px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                            >
                                <option value="all">All Durations</option>
                                <option value="fast">Fast (&lt;200ms)</option>
                                <option value="slow">Slow (≥200ms)</option>
                            </select>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Trace List */}
                    <div>
                        <h2 className="mb-4 text-lg font-semibold text-foreground">Recent Traces</h2>
                        <div className="space-y-3">
                            {filteredTraces.map((trace) => (
                                <TraceListItem
                                    key={trace.id}
                                    trace={trace}
                                    onClick={() => setSelectedTrace(trace)}
                                />
                            ))}
                        </div>
                    </div>

                    {/* Trace Detail */}
                    <div>
                        <h2 className="mb-4 text-lg font-semibold text-foreground">Trace Detail</h2>
                        {selectedTrace ? (
                            <div className="space-y-4">
                                <Card>
                                    <CardHeader>
                                        <div className="flex items-center justify-between">
                                            <CardTitle>{selectedTrace.name}</CardTitle>
                                            <Badge variant={selectedTrace.status === 'success' ? 'success' : 'danger'}>
                                                {selectedTrace.status}
                                            </Badge>
                                        </div>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-4">
                                            <div className="grid grid-cols-2 gap-4 text-sm">
                                                <div>
                                                    <p className="text-foreground-muted">Trace ID</p>
                                                    <p className="font-mono text-foreground">{selectedTrace.id}</p>
                                                </div>
                                                <div>
                                                    <p className="text-foreground-muted">Duration</p>
                                                    <p className="font-semibold text-foreground">{selectedTrace.duration}ms</p>
                                                </div>
                                                <div>
                                                    <p className="text-foreground-muted">Timestamp</p>
                                                    <p className="text-foreground">{selectedTrace.timestamp}</p>
                                                </div>
                                                <div>
                                                    <p className="text-foreground-muted">Spans</p>
                                                    <p className="text-foreground">{selectedTrace.spans.length}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle>Service Dependencies</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <ServiceDependencyGraph trace={selectedTrace} />
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle>Waterfall View</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <WaterfallView trace={selectedTrace} />
                                    </CardContent>
                                </Card>
                            </div>
                        ) : (
                            <Card className="p-12 text-center">
                                <GitBranch className="mx-auto h-12 w-12 text-foreground-muted" />
                                <h3 className="mt-4 text-lg font-medium text-foreground">No trace selected</h3>
                                <p className="mt-2 text-sm text-foreground-muted">
                                    Select a trace from the list to view details
                                </p>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
