import { useState } from 'react';
import { Card, CardHeader, CardTitle, CardContent, Badge, Button, Input, Select } from '@/components/ui';
import { Activity, CheckCircle, XCircle, AlertTriangle, Clock } from 'lucide-react';
import type { Service } from '@/types';

interface Props {
    service: Service;
}

type HealthCheckType = 'http' | 'tcp';
type ProbeType = 'startup' | 'liveness' | 'readiness';
type HealthStatus = 'healthy' | 'unhealthy' | 'degraded';

interface HealthCheckHistory {
    timestamp: string;
    status: HealthStatus;
    responseTime: number;
}

// Mock health check history data
const mockHealthHistory: HealthCheckHistory[] = [
    { timestamp: '2 minutes ago', status: 'healthy', responseTime: 45 },
    { timestamp: '5 minutes ago', status: 'healthy', responseTime: 52 },
    { timestamp: '8 minutes ago', status: 'healthy', responseTime: 48 },
    { timestamp: '11 minutes ago', status: 'degraded', responseTime: 890 },
    { timestamp: '14 minutes ago', status: 'healthy', responseTime: 43 },
    { timestamp: '17 minutes ago', status: 'healthy', responseTime: 51 },
    { timestamp: '20 minutes ago', status: 'unhealthy', responseTime: 0 },
    { timestamp: '23 minutes ago', status: 'healthy', responseTime: 47 },
];

export function HealthChecksTab({ service }: Props) {
    // Health check configuration
    const [checkType, setCheckType] = useState<HealthCheckType>('http');
    const [httpPath, setHttpPath] = useState('/health');
    const [httpPort, setHttpPort] = useState('3000');
    const [tcpPort, setTcpPort] = useState('3000');
    const [interval, setInterval] = useState(30); // seconds
    const [timeout, setTimeout] = useState(5); // seconds
    const [successThreshold, setSuccessThreshold] = useState(1);
    const [failureThreshold, setFailureThreshold] = useState(3);

    // Probe configuration
    const [startupProbeEnabled, setStartupProbeEnabled] = useState(true);
    const [livenessProbeEnabled, setLivenessProbeEnabled] = useState(true);
    const [readinessProbeEnabled, setReadinessProbeEnabled] = useState(true);

    // Current health status (mock)
    const currentStatus: HealthStatus = 'healthy';

    const getStatusIcon = (status: HealthStatus) => {
        switch (status) {
            case 'healthy':
                return <CheckCircle className="h-5 w-5 text-primary" />;
            case 'unhealthy':
                return <XCircle className="h-5 w-5 text-danger" />;
            case 'degraded':
                return <AlertTriangle className="h-5 w-5 text-warning" />;
        }
    };

    const getStatusVariant = (status: HealthStatus): 'success' | 'danger' | 'warning' => {
        switch (status) {
            case 'healthy':
                return 'success';
            case 'unhealthy':
                return 'danger';
            case 'degraded':
                return 'warning';
        }
    };

    const handleSaveConfiguration = () => {
        console.log('Saving health check configuration:', {
            checkType,
            httpPath,
            httpPort,
            tcpPort,
            interval,
            timeout,
            successThreshold,
            failureThreshold,
            startupProbeEnabled,
            livenessProbeEnabled,
            readinessProbeEnabled,
        });
    };

    return (
        <div className="space-y-6">
            {/* Current Health Status */}
            <Card>
                <CardContent className="p-6">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <div className={`flex h-16 w-16 items-center justify-center rounded-xl ${
                                currentStatus === 'healthy' ? 'bg-primary/10' :
                                currentStatus === 'degraded' ? 'bg-warning/10' : 'bg-danger/10'
                            }`}>
                                <Activity className={`h-8 w-8 ${
                                    currentStatus === 'healthy' ? 'text-primary' :
                                    currentStatus === 'degraded' ? 'text-warning' : 'text-danger'
                                }`} />
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-foreground">Current Health Status</h3>
                                <div className="mt-1 flex items-center gap-2">
                                    <Badge variant={getStatusVariant(currentStatus)} className="capitalize">
                                        {currentStatus}
                                    </Badge>
                                    <span className="text-sm text-foreground-muted">Last checked 2 minutes ago</span>
                                </div>
                            </div>
                        </div>
                        <div className="text-right">
                            <div className="text-2xl font-bold text-foreground">45ms</div>
                            <div className="text-sm text-foreground-muted">Avg response time</div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Health Check Configuration */}
            <Card>
                <CardHeader>
                    <CardTitle>Health Check Configuration</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="space-y-6">
                        {/* Check Type */}
                        <div>
                            <label className="text-sm font-medium text-foreground">Health Check Type</label>
                            <div className="mt-2 flex gap-2">
                                <button
                                    onClick={() => setCheckType('http')}
                                    className={`flex-1 rounded-lg border px-4 py-3 text-sm font-medium transition-all ${
                                        checkType === 'http'
                                            ? 'border-primary bg-primary/10 text-primary'
                                            : 'border-border bg-background-secondary text-foreground hover:border-border/80'
                                    }`}
                                >
                                    HTTP Health Check
                                </button>
                                <button
                                    onClick={() => setCheckType('tcp')}
                                    className={`flex-1 rounded-lg border px-4 py-3 text-sm font-medium transition-all ${
                                        checkType === 'tcp'
                                            ? 'border-primary bg-primary/10 text-primary'
                                            : 'border-border bg-background-secondary text-foreground hover:border-border/80'
                                    }`}
                                >
                                    TCP Health Check
                                </button>
                            </div>
                        </div>

                        {/* HTTP Configuration */}
                        {checkType === 'http' && (
                            <div className="space-y-4 rounded-lg border border-border bg-background-tertiary p-4">
                                <Input
                                    label="Health Check Path"
                                    value={httpPath}
                                    onChange={(e) => setHttpPath(e.target.value)}
                                    placeholder="/health"
                                    hint="The endpoint to check for health status"
                                />
                                <Input
                                    label="Port"
                                    type="number"
                                    value={httpPort}
                                    onChange={(e) => setHttpPort(e.target.value)}
                                    placeholder="3000"
                                />
                            </div>
                        )}

                        {/* TCP Configuration */}
                        {checkType === 'tcp' && (
                            <div className="space-y-4 rounded-lg border border-border bg-background-tertiary p-4">
                                <Input
                                    label="Port"
                                    type="number"
                                    value={tcpPort}
                                    onChange={(e) => setTcpPort(e.target.value)}
                                    placeholder="3000"
                                    hint="The TCP port to check for connectivity"
                                />
                            </div>
                        )}

                        {/* Common Settings */}
                        <div className="grid gap-4 md:grid-cols-2">
                            <Input
                                label="Interval (seconds)"
                                type="number"
                                value={interval}
                                onChange={(e) => setInterval(Number(e.target.value))}
                                min="5"
                                hint="How often to perform the check"
                            />
                            <Input
                                label="Timeout (seconds)"
                                type="number"
                                value={timeout}
                                onChange={(e) => setTimeout(Number(e.target.value))}
                                min="1"
                                hint="Maximum time to wait for response"
                            />
                            <Input
                                label="Success Threshold"
                                type="number"
                                value={successThreshold}
                                onChange={(e) => setSuccessThreshold(Number(e.target.value))}
                                min="1"
                                hint="Consecutive successes to mark healthy"
                            />
                            <Input
                                label="Failure Threshold"
                                type="number"
                                value={failureThreshold}
                                onChange={(e) => setFailureThreshold(Number(e.target.value))}
                                min="1"
                                hint="Consecutive failures to mark unhealthy"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Probe Configuration */}
            <Card>
                <CardHeader>
                    <CardTitle>Kubernetes Probes</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="space-y-4">
                        {/* Startup Probe */}
                        <div className="flex items-start justify-between rounded-lg border border-border bg-background-tertiary p-4">
                            <div className="flex-1">
                                <div className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        id="startup-probe"
                                        checked={startupProbeEnabled}
                                        onChange={(e) => setStartupProbeEnabled(e.target.checked)}
                                        className="h-4 w-4 rounded border-border bg-background-secondary text-primary focus:ring-2 focus:ring-primary focus:ring-offset-2"
                                    />
                                    <label htmlFor="startup-probe" className="font-medium text-foreground">
                                        Startup Probe
                                    </label>
                                </div>
                                <p className="mt-1 text-sm text-foreground-muted">
                                    Checks if the application has started successfully. Useful for slow-starting containers.
                                </p>
                            </div>
                            <Badge variant={startupProbeEnabled ? 'success' : 'default'}>
                                {startupProbeEnabled ? 'Enabled' : 'Disabled'}
                            </Badge>
                        </div>

                        {/* Liveness Probe */}
                        <div className="flex items-start justify-between rounded-lg border border-border bg-background-tertiary p-4">
                            <div className="flex-1">
                                <div className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        id="liveness-probe"
                                        checked={livenessProbeEnabled}
                                        onChange={(e) => setLivenessProbeEnabled(e.target.checked)}
                                        className="h-4 w-4 rounded border-border bg-background-secondary text-primary focus:ring-2 focus:ring-primary focus:ring-offset-2"
                                    />
                                    <label htmlFor="liveness-probe" className="font-medium text-foreground">
                                        Liveness Probe
                                    </label>
                                </div>
                                <p className="mt-1 text-sm text-foreground-muted">
                                    Checks if the application is running. Failed checks will restart the container.
                                </p>
                            </div>
                            <Badge variant={livenessProbeEnabled ? 'success' : 'default'}>
                                {livenessProbeEnabled ? 'Enabled' : 'Disabled'}
                            </Badge>
                        </div>

                        {/* Readiness Probe */}
                        <div className="flex items-start justify-between rounded-lg border border-border bg-background-tertiary p-4">
                            <div className="flex-1">
                                <div className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        id="readiness-probe"
                                        checked={readinessProbeEnabled}
                                        onChange={(e) => setReadinessProbeEnabled(e.target.checked)}
                                        className="h-4 w-4 rounded border-border bg-background-secondary text-primary focus:ring-2 focus:ring-primary focus:ring-offset-2"
                                    />
                                    <label htmlFor="readiness-probe" className="font-medium text-foreground">
                                        Readiness Probe
                                    </label>
                                </div>
                                <p className="mt-1 text-sm text-foreground-muted">
                                    Checks if the application is ready to accept traffic. Failed checks remove it from load balancer.
                                </p>
                            </div>
                            <Badge variant={readinessProbeEnabled ? 'success' : 'default'}>
                                {readinessProbeEnabled ? 'Enabled' : 'Disabled'}
                            </Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Health Status History */}
            <Card>
                <CardHeader>
                    <CardTitle>Health Status History</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="space-y-3">
                        {mockHealthHistory.map((check, index) => (
                            <div
                                key={index}
                                className="flex items-center justify-between rounded-lg border border-border bg-background-tertiary p-3"
                            >
                                <div className="flex items-center gap-3">
                                    {getStatusIcon(check.status)}
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-medium capitalize text-foreground">
                                                {check.status}
                                            </span>
                                            {check.status === 'healthy' && (
                                                <span className="text-xs text-foreground-muted">
                                                    {check.responseTime}ms
                                                </span>
                                            )}
                                        </div>
                                        <div className="mt-0.5 flex items-center gap-1 text-xs text-foreground-muted">
                                            <Clock className="h-3 w-3" />
                                            <span>{check.timestamp}</span>
                                        </div>
                                    </div>
                                </div>
                                <Badge variant={getStatusVariant(check.status)} className="capitalize">
                                    {check.status}
                                </Badge>
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>

            {/* Save Button */}
            <div className="flex justify-end">
                <Button onClick={handleSaveConfiguration} size="lg">
                    Save Configuration
                </Button>
            </div>
        </div>
    );
}
