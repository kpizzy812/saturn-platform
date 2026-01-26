import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent, CardDescription, Badge, Button, Input } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { Activity, Loader2, RefreshCw, AlertTriangle } from 'lucide-react';
import type { Service } from '@/types';
import { getStatusIcon, getStatusVariant } from '@/lib/statusUtils';

interface Props {
    service: Service;
}

type HealthCheckType = 'http' | 'tcp';

interface HealthcheckConfig {
    enabled: boolean;
    type: HealthCheckType;
    test: string;
    interval: number;
    timeout: number;
    retries: number;
    start_period: number;
    service_name: string | null;
    status?: string;
    services_status?: Record<string, { has_healthcheck: boolean; healthcheck: unknown }>;
}

export function HealthChecksTab({ service }: Props) {
    const { toast } = useToast();
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);

    // Health check configuration
    const [enabled, setEnabled] = useState(true);
    const [checkType, setCheckType] = useState<HealthCheckType>('http');
    const [testCommand, setTestCommand] = useState('curl -f http://localhost/ || exit 1');
    const [interval, setInterval] = useState(30);
    const [timeout, setTimeout] = useState(10);
    const [retries, setRetries] = useState(3);
    const [startPeriod, setStartPeriod] = useState(30);
    const [serviceName, setServiceName] = useState<string | null>(null);
    const [currentStatus, setCurrentStatus] = useState<string>('unknown');
    const [servicesStatus, setServicesStatus] = useState<Record<string, { has_healthcheck: boolean }>>({});

    // Load healthcheck configuration
    useEffect(() => {
        const loadConfig = async () => {
            try {
                const response = await fetch(`/api/v1/services/${service.uuid}/healthcheck`, {
                    credentials: 'include',
                });

                if (!response.ok) {
                    throw new Error('Failed to load healthcheck configuration');
                }

                const config: HealthcheckConfig = await response.json();

                setEnabled(config.enabled);
                setCheckType(config.type);
                setTestCommand(config.test);
                setInterval(config.interval);
                setTimeout(config.timeout);
                setRetries(config.retries);
                setStartPeriod(config.start_period);
                setServiceName(config.service_name);
                setCurrentStatus(config.status || 'unknown');
                setServicesStatus(config.services_status || {});
            } catch {
                toast({
                    title: 'Error',
                    description: 'Failed to load healthcheck configuration',
                    variant: 'destructive',
                });
            } finally {
                setIsLoading(false);
            }
        };

        loadConfig();
    }, [service.uuid, toast]);

    // Generate test command based on type
    const generateTestCommand = (type: HealthCheckType, path = '/', port = '80') => {
        if (type === 'http') {
            return `curl -f http://localhost:${port}${path} || exit 1`;
        } else {
            return `nc -z localhost ${port} || exit 1`;
        }
    };

    const handleTypeChange = (type: HealthCheckType) => {
        setCheckType(type);
        // Update test command based on type
        if (type === 'http' && testCommand.includes('nc ')) {
            setTestCommand(generateTestCommand('http'));
        } else if (type === 'tcp' && testCommand.includes('curl')) {
            setTestCommand(generateTestCommand('tcp'));
        }
    };

    const handleSaveConfiguration = async () => {
        setIsSaving(true);

        try {
            const response = await fetch(`/api/v1/services/${service.uuid}/healthcheck`, {
                method: 'PATCH',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    enabled,
                    type: checkType,
                    test: testCommand,
                    interval,
                    timeout,
                    retries,
                    start_period: startPeriod,
                    service_name: serviceName,
                }),
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to save configuration');
            }

            toast({
                title: 'Configuration saved',
                description: 'Health check configuration has been updated. Restart the service to apply changes.',
                variant: 'success',
            });

            // Refresh page data
            router.reload();
        } catch (error) {
            toast({
                title: 'Error',
                description: error instanceof Error ? error.message : 'Failed to save configuration',
                variant: 'destructive',
            });
        } finally {
            setIsSaving(false);
        }
    };

    // Parse status for display
    const parseStatus = (status: string) => {
        const parts = status.split(':');
        const runState = parts[0] || 'unknown';
        const health = parts[1] || 'unknown';
        return { runState, health };
    };

    const { runState, health } = parseStatus(currentStatus);

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-12">
                <Loader2 className="h-8 w-8 animate-spin text-foreground-muted" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Current Health Status */}
            <Card>
                <CardContent className="p-6">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <div className={`flex h-16 w-16 items-center justify-center rounded-xl ${
                                health === 'healthy' ? 'bg-primary/10' :
                                health === 'unhealthy' ? 'bg-danger/10' : 'bg-warning/10'
                            }`}>
                                <Activity className={`h-8 w-8 ${
                                    health === 'healthy' ? 'text-primary' :
                                    health === 'unhealthy' ? 'text-danger' : 'text-warning'
                                }`} />
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-foreground">Current Health Status</h3>
                                <div className="mt-1 flex items-center gap-2">
                                    <Badge variant={getStatusVariant(runState)} className="capitalize">
                                        {runState}
                                    </Badge>
                                    {health !== 'unknown' && (
                                        <Badge variant={getStatusVariant(health)} className="capitalize">
                                            {health}
                                        </Badge>
                                    )}
                                </div>
                            </div>
                        </div>
                        <Button
                            variant="secondary"
                            onClick={() => router.reload()}
                        >
                            <RefreshCw className="mr-2 h-4 w-4" />
                            Refresh
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Health Check Configuration */}
            <Card>
                <CardHeader>
                    <CardTitle>Health Check Configuration</CardTitle>
                    <CardDescription>
                        Configure how Docker monitors the health of your service containers
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="space-y-6">
                        {/* Enable/Disable Toggle */}
                        <div className="flex items-center justify-between rounded-lg border border-border bg-background-tertiary p-4">
                            <div>
                                <label className="text-sm font-medium text-foreground">Enable Health Check</label>
                                <p className="text-sm text-foreground-muted">
                                    Docker will periodically check if your container is healthy
                                </p>
                            </div>
                            <label className="relative inline-flex cursor-pointer items-center">
                                <input
                                    type="checkbox"
                                    checked={enabled}
                                    onChange={(e) => setEnabled(e.target.checked)}
                                    className="peer sr-only"
                                />
                                <div className="peer h-6 w-11 rounded-full bg-background-secondary after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-border after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary"></div>
                            </label>
                        </div>

                        {enabled && (
                            <>
                                {/* Check Type */}
                                <div>
                                    <label className="text-sm font-medium text-foreground">Health Check Type</label>
                                    <div className="mt-2 flex gap-2">
                                        <button
                                            onClick={() => handleTypeChange('http')}
                                            className={`flex-1 rounded-lg border px-4 py-3 text-sm font-medium transition-all ${
                                                checkType === 'http'
                                                    ? 'border-primary bg-primary/10 text-primary'
                                                    : 'border-border bg-background-secondary text-foreground hover:border-border/80'
                                            }`}
                                        >
                                            HTTP Health Check
                                        </button>
                                        <button
                                            onClick={() => handleTypeChange('tcp')}
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

                                {/* Test Command */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-foreground">Test Command</label>
                                    <Input
                                        value={testCommand}
                                        onChange={(e) => setTestCommand(e.target.value)}
                                        placeholder="curl -f http://localhost/ || exit 1"
                                    />
                                    <p className="text-xs text-foreground-muted">
                                        The command Docker runs to check container health. Exit code 0 = healthy, non-zero = unhealthy.
                                    </p>
                                </div>

                                {/* Timing Settings */}
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Input
                                        label="Interval (seconds)"
                                        type="number"
                                        value={interval}
                                        onChange={(e) => setInterval(Number(e.target.value))}
                                        min={1}
                                        hint="How often to perform the check"
                                    />
                                    <Input
                                        label="Timeout (seconds)"
                                        type="number"
                                        value={timeout}
                                        onChange={(e) => setTimeout(Number(e.target.value))}
                                        min={1}
                                        hint="Maximum time to wait for response"
                                    />
                                    <Input
                                        label="Retries"
                                        type="number"
                                        value={retries}
                                        onChange={(e) => setRetries(Number(e.target.value))}
                                        min={1}
                                        hint="Consecutive failures to mark unhealthy"
                                    />
                                    <Input
                                        label="Start Period (seconds)"
                                        type="number"
                                        value={startPeriod}
                                        onChange={(e) => setStartPeriod(Number(e.target.value))}
                                        min={0}
                                        hint="Grace period for container startup"
                                    />
                                </div>
                            </>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Services Healthcheck Status */}
            {Object.keys(servicesStatus).length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Container Health Status</CardTitle>
                        <CardDescription>
                            Health check status for each container in this service
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {Object.entries(servicesStatus).map(([name, status]) => (
                                <div
                                    key={name}
                                    className="flex items-center justify-between rounded-lg border border-border bg-background-tertiary p-3"
                                >
                                    <div className="flex items-center gap-3">
                                        {getStatusIcon(status.has_healthcheck ? 'healthy' : 'unknown')}
                                        <span className="font-medium text-foreground">{name}</span>
                                    </div>
                                    <Badge variant={status.has_healthcheck ? 'success' : 'default'}>
                                        {status.has_healthcheck ? 'Configured' : 'No Healthcheck'}
                                    </Badge>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Info Card */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex items-start gap-3">
                        <AlertTriangle className="h-5 w-5 flex-shrink-0 text-warning" />
                        <div className="text-sm text-foreground-muted">
                            <p className="font-medium text-foreground">Important Notes</p>
                            <ul className="mt-1 list-disc space-y-1 pl-4">
                                <li>Changes require a service restart to take effect</li>
                                <li>Health checks modify your docker-compose configuration</li>
                                <li>Ensure your application has a health endpoint before enabling HTTP checks</li>
                            </ul>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Save Button */}
            <div className="flex justify-end">
                <Button onClick={handleSaveConfiguration} size="lg" disabled={isSaving}>
                    {isSaving ? (
                        <>
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            Saving...
                        </>
                    ) : (
                        'Save Configuration'
                    )}
                </Button>
            </div>
        </div>
    );
}
