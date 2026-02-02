import { useState } from 'react';
import { Link, router, useForm } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Input, Textarea } from '@/components/ui';
import { ArrowLeft, Server, Save, Info } from 'lucide-react';
import type { Server as ServerType } from '@/types';

interface Props {
    server: ServerType;
}

export default function ServerGeneralSettings({ server }: Props) {
    const { data, setData, patch, processing, errors } = useForm({
        name: server.name || '',
        description: server.description || '',
        ip: server.ip || '',
        port: server.port || 22,
        user: server.user || 'root',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(`/servers/${server.uuid}/settings/general`);
    };

    return (
        <AppLayout
            title={`${server.name} - General Settings`}
            breadcrumbs={[
                { label: 'Servers', href: '/servers' },
                { label: server.name, href: `/servers/${server.uuid}` },
                { label: 'Settings', href: `/servers/${server.uuid}/settings` },
                { label: 'General' },
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
                    <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10">
                        <Server className="h-7 w-7 text-primary" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">General Settings</h1>
                        <p className="text-foreground-muted">{server.name}</p>
                    </div>
                </div>
            </div>

            <form onSubmit={handleSubmit}>
                {/* Basic Information */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>Basic Information</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <Input
                            label="Server Name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            error={errors.name}
                            placeholder="My Server"
                            hint="A friendly name to identify this server"
                        />
                        <Textarea
                            label="Description"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            error={errors.description}
                            placeholder="Optional description for this server"
                            rows={3}
                        />
                    </CardContent>
                </Card>

                {/* Connection Settings */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>Connection Settings</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-2">
                            <Input
                                label="IP Address / Hostname"
                                value={data.ip}
                                onChange={(e) => setData('ip', e.target.value)}
                                error={errors.ip}
                                placeholder="192.168.1.1 or server.example.com"
                            />
                            <Input
                                label="SSH Port"
                                type="number"
                                value={String(data.port)}
                                onChange={(e) => setData('port', parseInt(e.target.value) || 22)}
                                error={errors.port}
                                placeholder="22"
                            />
                        </div>
                        <Input
                            label="SSH User"
                            value={data.user}
                            onChange={(e) => setData('user', e.target.value)}
                            error={errors.user}
                            placeholder="root"
                            hint="The user to connect with via SSH"
                        />
                    </CardContent>
                </Card>

                {/* Server Info (Read-only) */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Info className="h-5 w-5" />
                            Server Information
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-foreground-muted">UUID</label>
                                <div className="rounded-md bg-background-secondary px-3 py-2 font-mono text-sm text-foreground">
                                    {server.uuid}
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-foreground-muted">Created At</label>
                                <div className="rounded-md bg-background-secondary px-3 py-2 text-sm text-foreground">
                                    {new Date(server.created_at).toLocaleString()}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Save Button */}
                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={processing}>
                        <Save className="mr-2 h-4 w-4" />
                        {processing ? 'Saving...' : 'Save General Settings'}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
