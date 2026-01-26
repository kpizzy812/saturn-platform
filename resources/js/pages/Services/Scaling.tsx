import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent, Badge, Button, Input, Alert, useToast } from '@/components/ui';
import { Cpu, MemoryStick, AlertTriangle, Info, CheckCircle } from 'lucide-react';
import type { Service } from '@/types';

interface Props {
    service: Service;
}

// Helper to convert memory string to MB for slider
function memoryToMb(value: string): number {
    if (value === '0' || !value) return 0;
    const num = parseFloat(value);
    if (value.toLowerCase().endsWith('g')) {
        return num * 1024;
    }
    if (value.toLowerCase().endsWith('m')) {
        return num;
    }
    // Assume MB if no suffix
    return num;
}

// Helper to convert MB to memory string
function mbToMemory(mb: number): string {
    if (mb === 0) return '0';
    if (mb >= 1024) {
        return `${mb / 1024}g`;
    }
    return `${mb}m`;
}

// Helper to parse CPU value
function parseCpus(value: string): number {
    if (value === '0' || !value) return 0;
    return parseFloat(value);
}

export function ScalingTab({ service }: Props) {
    const { addToast } = useToast();
    const [isSaving, setIsSaving] = useState(false);
    const [hasChanges, setHasChanges] = useState(false);

    // Resource limits state - initialized from service props
    const [memoryLimit, setMemoryLimit] = useState(() => memoryToMb(service.limits_memory || '0'));
    const [memorySwap, setMemorySwap] = useState(() => memoryToMb(service.limits_memory_swap || '0'));
    const [memorySwappiness, setMemorySwappiness] = useState(service.limits_memory_swappiness ?? 60);
    const [memoryReservation, setMemoryReservation] = useState(() => memoryToMb(service.limits_memory_reservation || '0'));
    const [cpuLimit, setCpuLimit] = useState(() => parseCpus(service.limits_cpus || '0') * 1000); // millicores
    const [cpuShares, setCpuShares] = useState(service.limits_cpu_shares ?? 1024);
    const [cpuSet, setCpuSet] = useState(service.limits_cpuset || '');

    // Track initial values to detect changes
    useEffect(() => {
        const initialMemory = memoryToMb(service.limits_memory || '0');
        const initialSwap = memoryToMb(service.limits_memory_swap || '0');
        const initialReservation = memoryToMb(service.limits_memory_reservation || '0');
        const initialCpu = parseCpus(service.limits_cpus || '0') * 1000;

        const changed =
            memoryLimit !== initialMemory ||
            memorySwap !== initialSwap ||
            memorySwappiness !== (service.limits_memory_swappiness ?? 60) ||
            memoryReservation !== initialReservation ||
            cpuLimit !== initialCpu ||
            cpuShares !== (service.limits_cpu_shares ?? 1024) ||
            cpuSet !== (service.limits_cpuset || '');

        setHasChanges(changed);
    }, [
        memoryLimit,
        memorySwap,
        memorySwappiness,
        memoryReservation,
        cpuLimit,
        cpuShares,
        cpuSet,
        service,
    ]);

    const handleApplyChanges = () => {
        setIsSaving(true);

        const data = {
            limits_memory: mbToMemory(memoryLimit),
            limits_memory_swap: mbToMemory(memorySwap),
            limits_memory_swappiness: memorySwappiness,
            limits_memory_reservation: mbToMemory(memoryReservation),
            limits_cpus: cpuLimit === 0 ? '0' : String(cpuLimit / 1000),
            limits_cpu_shares: cpuShares,
            limits_cpuset: cpuSet || null,
        };

        router.patch(`/api/v1/services/${service.uuid}`, data, {
            onSuccess: () => {
                addToast({
                    title: 'Resource limits saved',
                    description: 'Changes will apply on next service restart.',
                    variant: 'success',
                });
                setHasChanges(false);
            },
            onError: () => {
                addToast({
                    title: 'Failed to save resource limits',
                    description: 'Please check your inputs and try again.',
                    variant: 'destructive',
                });
            },
            onFinish: () => {
                setIsSaving(false);
            },
        });
    };

    const isNoLimitsSet =
        memoryLimit === 0 && cpuLimit === 0 && memoryReservation === 0;

    return (
        <div className="space-y-6">
            {/* Info Alert */}
            <Alert variant="default">
                <Info className="h-4 w-4" />
                <div className="ml-2">
                    <p className="text-sm font-medium">Resource Limits</p>
                    <p className="text-sm text-foreground-muted">
                        Configure CPU and memory limits for all containers in this service.
                        Set to 0 for no limit. Changes will apply after restarting the service.
                    </p>
                </div>
            </Alert>

            {/* Memory Configuration */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-warning/10">
                                <MemoryStick className="h-4 w-4 text-warning" />
                            </div>
                            <CardTitle>Memory Limits</CardTitle>
                        </div>
                        <Badge variant={memoryLimit > 0 ? 'success' : 'default'}>
                            {memoryLimit > 0 ? 'Configured' : 'Unlimited'}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="space-y-6">
                        {/* Memory Limit */}
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Memory Limit:{' '}
                                <span className="text-warning">
                                    {memoryLimit === 0
                                        ? 'No limit'
                                        : memoryLimit >= 1024
                                          ? `${(memoryLimit / 1024).toFixed(1)} GB`
                                          : `${memoryLimit} MB`}
                                </span>
                            </label>
                            <input
                                type="range"
                                min="0"
                                max="8192"
                                step="128"
                                value={memoryLimit}
                                onChange={(e) => setMemoryLimit(Number(e.target.value))}
                                className="mt-2 w-full accent-warning"
                            />
                            <div className="mt-2 flex justify-between text-xs text-foreground-muted">
                                <span>No limit</span>
                                <span>8 GB</span>
                            </div>
                        </div>

                        {/* Memory Reservation (soft limit) */}
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Memory Reservation (soft limit):{' '}
                                <span className="text-info">
                                    {memoryReservation === 0
                                        ? 'Not set'
                                        : memoryReservation >= 1024
                                          ? `${(memoryReservation / 1024).toFixed(1)} GB`
                                          : `${memoryReservation} MB`}
                                </span>
                            </label>
                            <input
                                type="range"
                                min="0"
                                max="8192"
                                step="128"
                                value={memoryReservation}
                                onChange={(e) => setMemoryReservation(Number(e.target.value))}
                                className="mt-2 w-full accent-info"
                            />
                            <div className="mt-2 flex justify-between text-xs text-foreground-muted">
                                <span>Not set</span>
                                <span>8 GB</span>
                            </div>
                            <p className="mt-1 text-xs text-foreground-muted">
                                Soft limit. Docker will try to keep container memory below this value.
                            </p>
                        </div>

                        {/* Memory Swap */}
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Swap Limit:{' '}
                                <span className="text-foreground-muted">
                                    {memorySwap === 0
                                        ? 'Same as memory limit'
                                        : memorySwap >= 1024
                                          ? `${(memorySwap / 1024).toFixed(1)} GB`
                                          : `${memorySwap} MB`}
                                </span>
                            </label>
                            <input
                                type="range"
                                min="0"
                                max="16384"
                                step="256"
                                value={memorySwap}
                                onChange={(e) => setMemorySwap(Number(e.target.value))}
                                className="mt-2 w-full"
                            />
                            <div className="mt-2 flex justify-between text-xs text-foreground-muted">
                                <span>Same as memory</span>
                                <span>16 GB</span>
                            </div>
                        </div>

                        {/* Memory Swappiness */}
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Swappiness: <span className="text-foreground-muted">{memorySwappiness}%</span>
                            </label>
                            <input
                                type="range"
                                min="0"
                                max="100"
                                value={memorySwappiness}
                                onChange={(e) => setMemorySwappiness(Number(e.target.value))}
                                className="mt-2 w-full"
                            />
                            <div className="mt-2 flex justify-between text-xs text-foreground-muted">
                                <span>0% (avoid swap)</span>
                                <span>100% (aggressive swap)</span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* CPU Configuration */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-info/10">
                                <Cpu className="h-4 w-4 text-info" />
                            </div>
                            <CardTitle>CPU Limits</CardTitle>
                        </div>
                        <Badge variant={cpuLimit > 0 ? 'success' : 'default'}>
                            {cpuLimit > 0 ? 'Configured' : 'Unlimited'}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="space-y-6">
                        {/* CPU Limit */}
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                CPU Limit:{' '}
                                <span className="text-info">
                                    {cpuLimit === 0
                                        ? 'No limit'
                                        : cpuLimit >= 1000
                                          ? `${(cpuLimit / 1000).toFixed(1)} CPU${cpuLimit >= 2000 ? 's' : ''}`
                                          : `${cpuLimit} millicores`}
                                </span>
                            </label>
                            <input
                                type="range"
                                min="0"
                                max="4000"
                                step="100"
                                value={cpuLimit}
                                onChange={(e) => setCpuLimit(Number(e.target.value))}
                                className="mt-2 w-full accent-info"
                            />
                            <div className="mt-2 flex justify-between text-xs text-foreground-muted">
                                <span>No limit</span>
                                <span>4 CPUs</span>
                            </div>
                        </div>

                        {/* CPU Shares */}
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                CPU Shares (relative weight):{' '}
                                <span className="text-foreground-muted">{cpuShares}</span>
                            </label>
                            <input
                                type="range"
                                min="2"
                                max="8192"
                                step="2"
                                value={cpuShares}
                                onChange={(e) => setCpuShares(Number(e.target.value))}
                                className="mt-2 w-full"
                            />
                            <div className="mt-2 flex justify-between text-xs text-foreground-muted">
                                <span>2 (low priority)</span>
                                <span>8192 (high priority)</span>
                            </div>
                            <p className="mt-1 text-xs text-foreground-muted">
                                Default is 1024. Higher values give more CPU time when system is under load.
                            </p>
                        </div>

                        {/* CPU Set */}
                        <Input
                            label="CPU Set (pin to specific CPUs)"
                            placeholder="e.g., 0,1 or 0-3"
                            value={cpuSet}
                            onChange={(e) => setCpuSet(e.target.value)}
                            hint="Leave empty to use all available CPUs. Specify CPUs like '0,1' or '0-3'."
                        />
                    </div>
                </CardContent>
            </Card>

            {/* Warning for no limits */}
            {isNoLimitsSet && (
                <Alert variant="warning">
                    <AlertTriangle className="h-4 w-4" />
                    <div className="ml-2">
                        <p className="text-sm font-medium">No resource limits configured</p>
                        <p className="text-sm text-foreground-muted">
                            Without limits, containers can consume all available server resources,
                            potentially affecting other services.
                        </p>
                    </div>
                </Alert>
            )}

            {/* Apply Button */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2 text-sm text-foreground-muted">
                    {hasChanges ? (
                        <>
                            <AlertTriangle className="h-4 w-4 text-warning" />
                            <span>You have unsaved changes</span>
                        </>
                    ) : (
                        <>
                            <CheckCircle className="h-4 w-4 text-success" />
                            <span>All changes saved</span>
                        </>
                    )}
                </div>
                <Button
                    onClick={handleApplyChanges}
                    size="lg"
                    disabled={isSaving || !hasChanges}
                    loading={isSaving}
                >
                    {isSaving ? 'Saving...' : 'Save Changes'}
                </Button>
            </div>
        </div>
    );
}
