import { useState } from 'react';
import { Card, CardHeader, CardTitle, CardContent, Badge, Button, Input, Select, Checkbox } from '@/components/ui';
import { Server, Cpu, MemoryStick, Globe, Moon } from 'lucide-react';
import type { Service } from '@/types';

interface Props {
    service: Service;
}

// Mock regions data
const regions = [
    { value: 'us-east-1', label: 'US East (N. Virginia)' },
    { value: 'us-west-2', label: 'US West (Oregon)' },
    { value: 'eu-west-1', label: 'EU West (Ireland)' },
    { value: 'eu-central-1', label: 'EU Central (Frankfurt)' },
    { value: 'ap-southeast-1', label: 'Asia Pacific (Singapore)' },
    { value: 'ap-northeast-1', label: 'Asia Pacific (Tokyo)' },
];

export function ScalingTab({ service }: Props) {
    // Horizontal scaling state
    const [replicaCount, setReplicaCount] = useState(3);

    // Vertical scaling state
    const [cpuLimit, setCpuLimit] = useState(1000); // millicores (1000 = 1 CPU)
    const [memoryLimit, setMemoryLimit] = useState(2048); // MB

    // Auto-scaling state
    const [autoScalingEnabled, setAutoScalingEnabled] = useState(false);
    const [minReplicas, setMinReplicas] = useState(1);
    const [maxReplicas, setMaxReplicas] = useState(10);

    // Region and sleep mode
    const [selectedRegion, setSelectedRegion] = useState('us-east-1');
    const [sleepModeEnabled, setSleepModeEnabled] = useState(false);
    const [inactivityTimeout, setInactivityTimeout] = useState(15); // minutes

    const handleApplyChanges = () => {
        // Mock action - would call API to apply scaling changes
        console.log('Applying scaling changes:', {
            replicaCount,
            cpuLimit,
            memoryLimit,
            autoScalingEnabled,
            minReplicas,
            maxReplicas,
            selectedRegion,
            sleepModeEnabled,
            inactivityTimeout,
        });
    };

    return (
        <div className="space-y-6">
            {/* Horizontal Scaling */}
            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10">
                            <Server className="h-4 w-4 text-primary" />
                        </div>
                        <CardTitle>Horizontal Scaling</CardTitle>
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="space-y-4">
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Replica Count: <span className="text-primary">{replicaCount}</span>
                            </label>
                            <input
                                type="range"
                                min="1"
                                max="10"
                                value={replicaCount}
                                onChange={(e) => setReplicaCount(Number(e.target.value))}
                                className="mt-2 w-full accent-primary"
                            />
                            <div className="mt-2 flex justify-between text-xs text-foreground-muted">
                                <span>1 replica</span>
                                <span>10 replicas</span>
                            </div>
                        </div>
                        <p className="text-sm text-foreground-muted">
                            Scale your service horizontally by running multiple instances (replicas) across servers.
                        </p>
                    </div>
                </CardContent>
            </Card>

            {/* Vertical Scaling */}
            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-info/10">
                            <Cpu className="h-4 w-4 text-info" />
                        </div>
                        <CardTitle>Vertical Scaling</CardTitle>
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="space-y-6">
                        {/* CPU Limit */}
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                CPU Limit: <span className="text-info">{cpuLimit / 1000} CPU{cpuLimit >= 2000 ? 's' : ''}</span>
                            </label>
                            <input
                                type="range"
                                min="100"
                                max="4000"
                                step="100"
                                value={cpuLimit}
                                onChange={(e) => setCpuLimit(Number(e.target.value))}
                                className="mt-2 w-full accent-info"
                            />
                            <div className="mt-2 flex justify-between text-xs text-foreground-muted">
                                <span>0.1 CPU</span>
                                <span>4 CPUs</span>
                            </div>
                        </div>

                        {/* Memory Limit */}
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Memory Limit: <span className="text-warning">{memoryLimit} MB</span>
                            </label>
                            <input
                                type="range"
                                min="128"
                                max="8192"
                                step="128"
                                value={memoryLimit}
                                onChange={(e) => setMemoryLimit(Number(e.target.value))}
                                className="mt-2 w-full accent-warning"
                            />
                            <div className="mt-2 flex justify-between text-xs text-foreground-muted">
                                <span>128 MB</span>
                                <span>8 GB</span>
                            </div>
                        </div>

                        <p className="text-sm text-foreground-muted">
                            Scale your service vertically by allocating more CPU and memory resources.
                        </p>
                    </div>
                </CardContent>
            </Card>

            {/* Auto-Scaling */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-success/10">
                                <MemoryStick className="h-4 w-4 text-success" />
                            </div>
                            <CardTitle>Auto-Scaling</CardTitle>
                        </div>
                        <Badge variant={autoScalingEnabled ? 'success' : 'default'}>
                            {autoScalingEnabled ? 'Enabled' : 'Disabled'}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="space-y-4">
                        <Checkbox
                            label="Enable auto-scaling based on CPU and memory usage"
                            checked={autoScalingEnabled}
                            onChange={(e) => setAutoScalingEnabled(e.target.checked)}
                        />

                        {autoScalingEnabled && (
                            <div className="space-y-4 rounded-lg border border-border bg-background-tertiary p-4">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Input
                                        type="number"
                                        label="Minimum Replicas"
                                        value={minReplicas}
                                        onChange={(e) => setMinReplicas(Number(e.target.value))}
                                        min="1"
                                        max={maxReplicas}
                                    />
                                    <Input
                                        type="number"
                                        label="Maximum Replicas"
                                        value={maxReplicas}
                                        onChange={(e) => setMaxReplicas(Number(e.target.value))}
                                        min={minReplicas}
                                        max="20"
                                    />
                                </div>
                                <p className="text-sm text-foreground-muted">
                                    Auto-scaling will automatically adjust replica count between {minReplicas} and {maxReplicas} based on resource usage.
                                </p>
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Region Selection */}
            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-info/10">
                            <Globe className="h-4 w-4 text-info" />
                        </div>
                        <CardTitle>Region</CardTitle>
                    </div>
                </CardHeader>
                <CardContent>
                    <Select
                        label="Deployment Region"
                        options={regions}
                        value={selectedRegion}
                        onChange={(e) => setSelectedRegion(e.target.value)}
                    />
                    <p className="mt-2 text-sm text-foreground-muted">
                        Choose the geographic region where your service will be deployed.
                    </p>
                </CardContent>
            </Card>

            {/* Sleep Mode */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-warning/10">
                                <Moon className="h-4 w-4 text-warning" />
                            </div>
                            <CardTitle>Sleep Mode</CardTitle>
                        </div>
                        <Badge variant={sleepModeEnabled ? 'warning' : 'default'}>
                            {sleepModeEnabled ? 'Enabled' : 'Disabled'}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="space-y-4">
                        <Checkbox
                            label="Put service to sleep after period of inactivity"
                            checked={sleepModeEnabled}
                            onChange={(e) => setSleepModeEnabled(e.target.checked)}
                        />

                        {sleepModeEnabled && (
                            <div className="space-y-4 rounded-lg border border-border bg-background-tertiary p-4">
                                <Input
                                    type="number"
                                    label="Inactivity Timeout (minutes)"
                                    value={inactivityTimeout}
                                    onChange={(e) => setInactivityTimeout(Number(e.target.value))}
                                    min="5"
                                    max="1440"
                                    hint="Service will sleep after this many minutes of no activity"
                                />
                                <p className="text-sm text-foreground-muted">
                                    Save resources by automatically scaling down to zero when there's no traffic. The service will wake up automatically when a request is received.
                                </p>
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Apply Button */}
            <div className="flex justify-end">
                <Button onClick={handleApplyChanges} size="lg">
                    Apply Changes
                </Button>
            </div>
        </div>
    );
}
