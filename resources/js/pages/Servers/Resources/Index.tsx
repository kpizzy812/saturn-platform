import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Badge } from '@/components/ui';
import { ArrowLeft, Package, Database, Globe, Boxes } from 'lucide-react';
import type { Server as ServerType } from '@/types';
import { StaggerList, StaggerItem, FadeIn } from '@/components/animation';

interface Props {
    server: ServerType;
    applications?: number;
    databases?: number;
    services?: number;
}

export default function ServerResourcesIndex({ server, applications = 0, databases = 0, services = 0 }: Props) {
    const totalResources = applications + databases + services;

    const resourceTypes = [
        {
            title: 'Applications',
            count: applications,
            icon: <Package className="h-6 w-6" />,
            href: `/servers/${server.uuid}/resources/applications`,
            iconBg: 'bg-primary/10',
            iconColor: 'text-primary',
            description: 'Deployed applications',
        },
        {
            title: 'Databases',
            count: databases,
            icon: <Database className="h-6 w-6" />,
            href: `/servers/${server.uuid}/resources/databases`,
            iconBg: 'bg-info/10',
            iconColor: 'text-info',
            description: 'Database instances',
        },
        {
            title: 'Services',
            count: services,
            icon: <Globe className="h-6 w-6" />,
            href: `/servers/${server.uuid}/resources/services`,
            iconBg: 'bg-success/10',
            iconColor: 'text-success',
            description: 'Docker Compose services',
        },
    ];

    return (
        <AppLayout
            title={`${server.name} - Resources`}
            breadcrumbs={[
                { label: 'Servers', href: '/servers' },
                { label: server.name, href: `/servers/${server.uuid}` },
                { label: 'Resources' },
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
                        <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10">
                            <Boxes className="h-7 w-7 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-foreground">Server Resources</h1>
                            <p className="text-foreground-muted">
                                {totalResources} {totalResources === 1 ? 'resource' : 'resources'} running on {server.name}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {/* Resource Stats */}
            <StaggerList className="mb-6 grid gap-4 md:grid-cols-3">
                {resourceTypes.map((resource, i) => (
                    <StaggerItem key={resource.title} index={i}>
                        <Link href={resource.href}>
                            <Card className="cursor-pointer transition-all hover:border-primary/50">
                                <CardContent className="p-5">
                                    <div className="flex items-start justify-between">
                                        <div className="flex items-center gap-3">
                                            <div className={`flex h-12 w-12 items-center justify-center rounded-lg ${resource.iconBg}`}>
                                                <div className={resource.iconColor}>{resource.icon}</div>
                                            </div>
                                            <div>
                                                <p className="text-sm text-foreground-muted">{resource.title}</p>
                                                <p className="text-2xl font-bold text-foreground">{resource.count}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <p className="mt-3 text-sm text-foreground-muted">{resource.description}</p>
                                </CardContent>
                            </Card>
                        </Link>
                    </StaggerItem>
                ))}
            </StaggerList>

            {/* Resource Details */}
            {totalResources === 0 ? (
                <FadeIn>
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16">
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-secondary">
                                <Boxes className="h-8 w-8 animate-pulse-soft text-foreground-subtle" />
                            </div>
                            <h3 className="mt-4 font-medium text-foreground">No resources yet</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Deploy applications, databases, or services to see them here
                            </p>
                        </CardContent>
                    </Card>
                </FadeIn>
            ) : (
                <div className="space-y-4">
                    {applications > 0 && (
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle>Applications</CardTitle>
                                    <Badge>{applications}</Badge>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-foreground-muted">
                                    View all applications running on this server
                                </p>
                                <Link
                                    href={`/servers/${server.uuid}/resources/applications`}
                                    className="mt-3 inline-block text-sm font-medium text-primary hover:text-primary/80"
                                >
                                    View Applications →
                                </Link>
                            </CardContent>
                        </Card>
                    )}

                    {databases > 0 && (
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle>Databases</CardTitle>
                                    <Badge>{databases}</Badge>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-foreground-muted">
                                    Manage database instances on this server
                                </p>
                                <Link
                                    href={`/servers/${server.uuid}/resources/databases`}
                                    className="mt-3 inline-block text-sm font-medium text-primary hover:text-primary/80"
                                >
                                    View Databases →
                                </Link>
                            </CardContent>
                        </Card>
                    )}

                    {services > 0 && (
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle>Services</CardTitle>
                                    <Badge>{services}</Badge>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-foreground-muted">
                                    Docker Compose services deployed on this server
                                </p>
                                <Link
                                    href={`/servers/${server.uuid}/resources/services`}
                                    className="mt-3 inline-block text-sm font-medium text-primary hover:text-primary/80"
                                >
                                    View Services →
                                </Link>
                            </CardContent>
                        </Card>
                    )}
                </div>
            )}
        </AppLayout>
    );
}
