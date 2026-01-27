import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Input } from '@/components/ui';
import { ArrowLeft, Server, Save, HardDrive, RefreshCw, CheckCircle, XCircle } from 'lucide-react';
import type { Server as ServerType } from '@/types';

interface Props {
    server: ServerType;
}

export default function ServerDockerSettings({ server }: Props) {
    const [isBuildServer, setIsBuildServer] = useState(server.settings?.is_build_server || false);
    const [concurrentBuilds, setConcurrentBuilds] = useState(String(server.settings?.concurrent_builds || 2));
    const [dockerCleanup, setDockerCleanup] = useState(true);

    const handleSave = () => {
        router.patch(`/servers/${server.uuid}/settings/docker`, {
            is_build_server: isBuildServer,
            concurrent_builds: parseInt(concurrentBuilds),
            docker_cleanup: dockerCleanup,
        });
    };

    const hasChanges =
        isBuildServer !== (server.settings?.is_build_server || false) ||
        concurrentBuilds !== String(server.settings?.concurrent_builds || 2);

    return (
        <AppLayout
            title={`${server.name} - Docker Settings`}
            breadcrumbs={[
                { label: 'Servers', href: '/servers' },
                { label: server.name, href: `/servers/${server.uuid}` },
                { label: 'Settings', href: `/servers/${server.uuid}/settings` },
                { label: 'Docker' },
            ]}
        >
            {/* Header */}
            <div className="mb-6">
                <Link
                    href={`/servers/${server.uuid}/settings`}
                    className="mb-4 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="mr-2 h-4 w-4" />
                    Back to Settings
                </Link>
                <div className="flex items-center gap-4">
                    <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-info/10">
                        <HardDrive className="h-7 w-7 text-info" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Docker Configuration</h1>
                        <p className="text-foreground-muted">{server.name}</p>
                    </div>
                </div>
            </div>

            {/* Docker Status */}
            <Card className="mb-6">
                <CardHeader>
                    <CardTitle>Docker Status</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-success/10">
                                <CheckCircle className="h-5 w-5 text-success" />
                            </div>
                            <div>
                                <p className="font-medium text-foreground">Docker is installed and running</p>
                                <p className="text-sm text-foreground-muted">Docker Engine installed</p>
                            </div>
                        </div>
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={() => router.post(`/servers/${server.uuid}/docker/validate`)}
                        >
                            <RefreshCw className="mr-2 h-4 w-4" />
                            Validate
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Build Settings */}
            <Card className="mb-6">
                <CardHeader>
                    <CardTitle>Build Settings</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4">
                        <div>
                            <h4 className="font-medium text-foreground">Build Server</h4>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Use this server for building Docker images
                            </p>
                        </div>
                        <label className="relative inline-flex cursor-pointer items-center">
                            <input
                                type="checkbox"
                                checked={isBuildServer}
                                onChange={(e) => setIsBuildServer(e.target.checked)}
                                className="peer sr-only"
                            />
                            <div className="peer h-6 w-11 rounded-full bg-background-tertiary after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-border after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary peer-focus:ring-offset-2"></div>
                        </label>
                    </div>

                    <Input
                        label="Concurrent Builds"
                        value={concurrentBuilds}
                        onChange={(e) => setConcurrentBuilds(e.target.value)}
                        type="number"
                        min="1"
                        max="10"
                        hint="Maximum number of concurrent builds on this server"
                    />

                    <div className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-4">
                        <div>
                            <h4 className="font-medium text-foreground">Auto Cleanup</h4>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Automatically clean up unused Docker images and containers
                            </p>
                        </div>
                        <label className="relative inline-flex cursor-pointer items-center">
                            <input
                                type="checkbox"
                                checked={dockerCleanup}
                                onChange={(e) => setDockerCleanup(e.target.checked)}
                                className="peer sr-only"
                            />
                            <div className="peer h-6 w-11 rounded-full bg-background-tertiary after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-border after:bg-white after:transition-all after:content-[''] peer-checked:bg-primary peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary peer-focus:ring-offset-2"></div>
                        </label>
                    </div>

                    <div className="flex items-center gap-3">
                        <Button onClick={handleSave} disabled={!hasChanges}>
                            <Save className="mr-2 h-4 w-4" />
                            Save Docker Settings
                        </Button>
                        {hasChanges && (
                            <span className="text-sm text-foreground-muted">You have unsaved changes</span>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Docker Info */}
            <Card>
                <CardHeader>
                    <CardTitle>Docker Information</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                    <InfoRow label="Docker Version" value={server.settings?.docker_version || '—'} />
                    <InfoRow label="Docker Compose Version" value={server.settings?.docker_compose_version || '—'} />
                    <InfoRow label="Running Containers" value="—" />
                    <InfoRow label="Total Images" value="—" />
                    <InfoRow label="Total Volumes" value="—" />
                </CardContent>
            </Card>
        </AppLayout>
    );
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex items-center justify-between">
            <span className="text-sm text-foreground-muted">{label}</span>
            <span className="text-sm font-medium text-foreground">{value}</span>
        </div>
    );
}
