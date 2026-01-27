import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Badge, useConfirm } from '@/components/ui';
import { ArrowLeft, Trash2, AlertTriangle, CheckCircle, HardDrive, Package, Database as DatabaseIcon } from 'lucide-react';
import type { Server as ServerType } from '@/types';

interface Props {
    server: ServerType;
    cleanupStats?: CleanupStats;
}

interface CleanupStats {
    unused_images: number;
    unused_containers: number;
    unused_volumes: number;
    unused_networks: number;
    total_size: string;
}

export default function ServerCleanupIndex({ server, cleanupStats }: Props) {
    const confirm = useConfirm();
    const [isRunning, setIsRunning] = useState(false);
    const [lastCleanup, setLastCleanup] = useState<string | null>(null);

    const stats = cleanupStats || {
        unused_images: 0,
        unused_containers: 0,
        unused_volumes: 0,
        unused_networks: 0,
        total_size: 'N/A',
    };

    const handleCleanup = async (type: string) => {
        const confirmed = await confirm({
            title: 'Clean Up Resources',
            description: `Are you sure you want to clean up ${type}? This action cannot be undone.`,
            confirmText: 'Clean Up',
            variant: 'danger',
        });
        if (confirmed) {
            setIsRunning(true);
            router.post(`/servers/${server.uuid}/cleanup/${type}`, {}, {
                onFinish: () => {
                    setIsRunning(false);
                    setLastCleanup(new Date().toISOString());
                },
            });
        }
    };

    const handleCleanupAll = async () => {
        const confirmed = await confirm({
            title: 'Clean Up All Resources',
            description: 'Are you sure you want to clean up all unused resources? This action cannot be undone.',
            confirmText: 'Clean Up All',
            variant: 'danger',
        });
        if (confirmed) {
            setIsRunning(true);
            router.post(`/servers/${server.uuid}/cleanup/all`, {}, {
                onFinish: () => {
                    setIsRunning(false);
                    setLastCleanup(new Date().toISOString());
                },
            });
        }
    };

    const cleanupItems = [
        {
            title: 'Unused Docker Images',
            count: stats.unused_images,
            description: 'Remove Docker images that are not being used by any containers',
            icon: <Package className="h-6 w-6" />,
            type: 'images',
            color: 'primary',
        },
        {
            title: 'Stopped Containers',
            count: stats.unused_containers,
            description: 'Remove containers that have been stopped',
            icon: <HardDrive className="h-6 w-6" />,
            type: 'containers',
            color: 'info',
        },
        {
            title: 'Unused Volumes',
            count: stats.unused_volumes,
            description: 'Remove Docker volumes not attached to any containers',
            icon: <DatabaseIcon className="h-6 w-6" />,
            type: 'volumes',
            color: 'warning',
        },
        {
            title: 'Unused Networks',
            count: stats.unused_networks,
            description: 'Remove Docker networks with no connected containers',
            icon: <HardDrive className="h-6 w-6" />,
            type: 'networks',
            color: 'success',
        },
    ];

    return (
        <AppLayout
            title={`${server.name} - Cleanup`}
            breadcrumbs={[
                { label: 'Servers', href: '/servers' },
                { label: server.name, href: `/servers/${server.uuid}` },
                { label: 'Cleanup' },
            ]}
        >
            {/* Header */}
            <div className="mb-6">
                <Link
                    href={`/servers/${server.uuid}`}
                    className="mb-4 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="mr-2 h-4 w-4" />
                    Back to Server
                </Link>
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-warning/10">
                            <Trash2 className="h-7 w-7 text-warning" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-foreground">Server Cleanup</h1>
                            <p className="text-foreground-muted">Free up disk space on {server.name}</p>
                        </div>
                    </div>
                </div>
            </div>

            {/* Warning Banner */}
            <Card className="mb-6 border-warning/50">
                <CardContent className="p-4">
                    <div className="flex items-start gap-3">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-warning/10">
                            <AlertTriangle className="h-5 w-5 text-warning" />
                        </div>
                        <div>
                            <h4 className="font-medium text-warning">Caution Required</h4>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Cleanup operations are permanent and cannot be undone. Make sure you understand what
                                will be removed before proceeding. Active containers and their dependencies will not be affected.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Cleanup Stats */}
            <Card className="mb-6">
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <CardTitle>Cleanup Summary</CardTitle>
                        <Badge variant="warning" size="lg">{stats.total_size}</Badge>
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-foreground-muted">
                            Total reclaimable disk space from unused resources
                        </p>
                        <Button
                            variant="danger"
                            onClick={handleCleanupAll}
                            disabled={isRunning}
                        >
                            <Trash2 className="mr-2 h-4 w-4" />
                            {isRunning ? 'Cleaning...' : 'Clean Up All'}
                        </Button>
                    </div>
                    {lastCleanup && (
                        <div className="mt-3 flex items-center gap-2 text-sm text-success">
                            <CheckCircle className="h-4 w-4" />
                            <span>Last cleanup: {new Date(lastCleanup).toLocaleString()}</span>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Cleanup Items */}
            <div className="grid gap-4 md:grid-cols-2">
                {cleanupItems.map((item) => (
                    <Card key={item.type}>
                        <CardContent className="p-5">
                            <div className="flex items-start gap-3">
                                <div className={`flex h-12 w-12 items-center justify-center rounded-lg bg-${item.color}/10`}>
                                    <div className={`text-${item.color}`}>{item.icon}</div>
                                </div>
                                <div className="flex-1">
                                    <div className="flex items-center justify-between">
                                        <h3 className="font-semibold text-foreground">{item.title}</h3>
                                        <Badge variant={item.count > 0 ? 'warning' : 'secondary'}>
                                            {item.count}
                                        </Badge>
                                    </div>
                                    <p className="mt-1 text-sm text-foreground-muted">{item.description}</p>
                                    <Button
                                        className="mt-3"
                                        size="sm"
                                        variant="secondary"
                                        onClick={() => handleCleanup(item.type)}
                                        disabled={isRunning || item.count === 0}
                                    >
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Clean Up
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {/* Automatic Cleanup */}
            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Automatic Cleanup (Coming Soon)</CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-foreground-muted">
                        Configure automatic cleanup schedules to keep your server optimized without manual intervention.
                        This feature will be available in a future update.
                    </p>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
