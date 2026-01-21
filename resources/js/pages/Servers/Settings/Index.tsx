import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button } from '@/components/ui';
import { ArrowLeft, Server, Settings as SettingsIcon, Network, HardDrive, Trash2 } from 'lucide-react';
import type { Server as ServerType } from '@/types';

interface Props {
    server: ServerType;
}

export default function ServerSettingsIndex({ server }: Props) {
    const settingsSections = [
        {
            title: 'General Settings',
            description: 'Configure server name, description, and basic information',
            icon: <SettingsIcon className="h-6 w-6" />,
            href: `/servers/${server.uuid}/settings/general`,
            iconBg: 'bg-primary/10',
            iconColor: 'text-primary',
        },
        {
            title: 'Docker Configuration',
            description: 'Manage Docker settings and build configurations',
            icon: <HardDrive className="h-6 w-6" />,
            href: `/servers/${server.uuid}/settings/docker`,
            iconBg: 'bg-info/10',
            iconColor: 'text-info',
        },
        {
            title: 'Network Settings',
            description: 'Configure network, firewall, and connectivity settings',
            icon: <Network className="h-6 w-6" />,
            href: `/servers/${server.uuid}/settings/network`,
            iconBg: 'bg-success/10',
            iconColor: 'text-success',
        },
    ];

    return (
        <AppLayout
            title={`${server.name} - Settings`}
            breadcrumbs={[
                { label: 'Servers', href: '/servers' },
                { label: server.name, href: `/servers/${server.uuid}` },
                { label: 'Settings' },
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
                <div className="flex items-center gap-4">
                    <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10">
                        <Server className="h-7 w-7 text-primary" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Server Settings</h1>
                        <p className="text-foreground-muted">{server.name}</p>
                    </div>
                </div>
            </div>

            {/* Settings Grid */}
            <div className="mb-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {settingsSections.map((section) => (
                    <Link key={section.href} href={section.href}>
                        <Card className="cursor-pointer transition-all hover:border-primary/50">
                            <CardContent className="p-6">
                                <div className="mb-4 flex items-center gap-3">
                                    <div className={`flex h-12 w-12 items-center justify-center rounded-lg ${section.iconBg}`}>
                                        <div className={section.iconColor}>{section.icon}</div>
                                    </div>
                                </div>
                                <h3 className="font-semibold text-foreground">{section.title}</h3>
                                <p className="mt-1 text-sm text-foreground-muted">{section.description}</p>
                            </CardContent>
                        </Card>
                    </Link>
                ))}
            </div>

            {/* Danger Zone */}
            <Card className="border-danger/50">
                <CardHeader>
                    <CardTitle className="text-danger">Danger Zone</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="flex items-center justify-between">
                        <div>
                            <h4 className="font-medium text-foreground">Delete this server</h4>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Once you delete a server, there is no going back. Please be certain.
                            </p>
                        </div>
                        <Button
                            variant="danger"
                            onClick={() => {
                                if (confirm(`Are you sure you want to delete "${server.name}"? This action cannot be undone.`)) {
                                    router.delete(`/servers/${server.uuid}`);
                                }
                            }}
                        >
                            <Trash2 className="mr-2 h-4 w-4" />
                            Delete Server
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
