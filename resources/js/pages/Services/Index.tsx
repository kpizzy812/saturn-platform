import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Badge, Button } from '@/components/ui';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';
import { Plus, Container, MoreVertical, Settings, Trash2, Power, RotateCw, FileCode } from 'lucide-react';
import type { Service } from '@/types';

interface Props {
    services: Service[];
}

export default function ServicesIndex({ services = [] }: Props) {
    return (
        <AppLayout
            title="Services"
            breadcrumbs={[{ label: 'Services' }]}
        >
            <div className="mx-auto max-w-6xl px-6 py-8">
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Services</h1>
                    <p className="text-foreground-muted">Manage your Docker Compose services</p>
                </div>
                <Link href="/services/create">
                    <Button>
                        <Plus className="mr-2 h-4 w-4" />
                        New Service
                    </Button>
                </Link>
            </div>

            {/* Services Grid */}
            {services.length === 0 ? (
                <EmptyState />
            ) : (
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {services.map((service) => (
                        <ServiceCard key={service.id} service={service} />
                    ))}
                </div>
            )}
            </div>
        </AppLayout>
    );
}

function ServiceCard({ service }: { service: Service }) {
    return (
        <Link href={`/services/${service.uuid}`}>
            <Card className="transition-colors hover:border-primary/50">
                <CardContent className="p-4">
                    <div className="flex items-start justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-500/15 text-purple-400">
                                <Container className="h-5 w-5" />
                            </div>
                            <div>
                                <h3 className="font-medium text-foreground">{service.name}</h3>
                                {service.description && (
                                    <p className="text-sm text-foreground-muted">{service.description}</p>
                                )}
                            </div>
                        </div>
                        <Dropdown>
                            <DropdownTrigger>
                                <button
                                    className="rounded-md p-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                                    onClick={(e) => e.preventDefault()}
                                >
                                    <MoreVertical className="h-4 w-4" />
                                </button>
                            </DropdownTrigger>
                            <DropdownContent align="right">
                                <DropdownItem
                                    icon={<Settings className="h-4 w-4" />}
                                    onClick={(e) => {
                                        e.preventDefault();
                                        router.visit(`/services/${service.uuid}/settings`);
                                    }}
                                >
                                    Service Settings
                                </DropdownItem>
                                <DropdownItem
                                    icon={<RotateCw className="h-4 w-4" />}
                                    onClick={(e) => {
                                        e.preventDefault();
                                        router.post(`/services/${service.uuid}/restart`);
                                    }}
                                >
                                    Restart Service
                                </DropdownItem>
                                <DropdownItem
                                    icon={<Power className="h-4 w-4" />}
                                    onClick={(e) => {
                                        e.preventDefault();
                                        router.post(`/services/${service.uuid}/stop`);
                                    }}
                                >
                                    Stop Service
                                </DropdownItem>
                                <DropdownDivider />
                                <DropdownItem
                                    icon={<Trash2 className="h-4 w-4" />}
                                    onClick={(e) => {
                                        e.preventDefault();
                                        if (confirm(`Are you sure you want to delete "${service.name}"? This action cannot be undone.`)) {
                                            router.delete(`/services/${service.uuid}`);
                                        }
                                    }}
                                    danger
                                >
                                    Delete Service
                                </DropdownItem>
                            </DropdownContent>
                        </Dropdown>
                    </div>

                    {/* Docker Compose indicator */}
                    <div className="mt-4 flex items-center gap-2">
                        <FileCode className="h-4 w-4 text-foreground-subtle" />
                        <span className="text-sm text-foreground-muted">Docker Compose</span>
                    </div>

                    {/* Last updated */}
                    <p className="mt-4 text-xs text-foreground-subtle">
                        Updated {new Date(service.updated_at).toLocaleDateString()}
                    </p>
                </CardContent>
            </Card>
        </Link>
    );
}

function EmptyState() {
    return (
        <Card className="p-12 text-center">
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                <Container className="h-8 w-8 text-foreground-muted" />
            </div>
            <h3 className="mt-4 text-lg font-medium text-foreground">No services yet</h3>
            <p className="mt-2 text-foreground-muted">
                Create your first Docker Compose service to deploy multi-container applications.
            </p>
            <Link href="/services/create" className="mt-6 inline-block">
                <Button>
                    <Plus className="mr-2 h-4 w-4" />
                    Create Service
                </Button>
            </Link>
        </Card>
    );
}
