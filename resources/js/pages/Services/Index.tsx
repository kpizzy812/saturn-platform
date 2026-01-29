import { useState, useEffect } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Badge, Button, useConfirm } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';
import { useRealtimeStatus } from '@/hooks/useRealtimeStatus';
import { Plus, Container, MoreVertical, Settings, Trash2, Power, RotateCw, FileCode } from 'lucide-react';
import type { Service } from '@/types';

interface Props {
    services: Service[];
}

export default function ServicesIndex({ services: initialServices = [] }: Props) {
    const [services, setServices] = useState<Service[]>(initialServices);
    const { addToast } = useToast();

    // Sync with props when they change (e.g., after navigation)
    useEffect(() => {
        setServices(initialServices);
    }, [initialServices]);

    // Real-time updates via WebSocket
    useRealtimeStatus({
        onServiceStatusChange: () => {
            // Reload services list when any service changes
            router.reload({ only: ['services'] });
        },
    });

    const handleDeleteService = (serviceUuid: string) => {
        // Optimistically remove from UI
        setServices(prev => prev.filter(s => s.uuid !== serviceUuid));
        addToast('success', 'Service deleted successfully');
    };

    return (
        <AppLayout
            title="Services"
            breadcrumbs={[{ label: 'Services' }]}
        >
            <div className="mx-auto max-w-6xl">
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
                        <ServiceCard key={service.id} service={service} onDelete={handleDeleteService} />
                    ))}
                </div>
            )}
            </div>
        </AppLayout>
    );
}

function ServiceCard({ service, onDelete }: { service: Service; onDelete: (uuid: string) => void }) {
    const confirm = useConfirm();
    const [isDeleting, setIsDeleting] = useState(false);

    const handleDelete = async (e: React.MouseEvent) => {
        e.preventDefault();
        const confirmed = await confirm({
            title: 'Delete Service',
            description: `Are you sure you want to delete "${service.name}"? This action cannot be undone.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            setIsDeleting(true);
            router.delete(`/services/${service.uuid}`, {
                preserveScroll: true,
                onSuccess: () => {
                    onDelete(service.uuid);
                },
                onError: () => {
                    setIsDeleting(false);
                },
            });
        }
    };

    if (isDeleting) {
        return null; // Hide card immediately when deleting
    }

    return (
        <Link
            href={`/services/${service.uuid}`}
            className="group relative flex flex-col rounded-xl border border-border/50 bg-gradient-to-br from-background-secondary to-background-secondary/50 p-5 transition-all duration-300 hover:-translate-y-1 hover:border-border hover:shadow-xl hover:shadow-black/20"
        >
            {/* Subtle gradient overlay on hover */}
            <div className="absolute inset-0 rounded-xl bg-gradient-to-br from-white/[0.02] to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100" />

            <div className="relative flex items-start justify-between">
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-500/15 text-purple-400 transition-colors group-hover:bg-purple-500/25">
                        <Container className="h-5 w-5" />
                    </div>
                    <div>
                        <h3 className="font-medium text-foreground transition-colors group-hover:text-white">{service.name}</h3>
                        {service.description && (
                            <p className="text-sm text-foreground-muted">{service.description}</p>
                        )}
                    </div>
                </div>
                <Dropdown>
                    <DropdownTrigger>
                        <button
                            className="rounded-md p-1.5 opacity-0 transition-all duration-200 hover:bg-white/10 group-hover:opacity-100"
                            onClick={(e) => e.preventDefault()}
                        >
                            <MoreVertical className="h-4 w-4 text-foreground-muted" />
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
                            onClick={handleDelete}
                            danger
                        >
                            Delete Service
                        </DropdownItem>
                    </DropdownContent>
                </Dropdown>
            </div>

            {/* Docker Compose indicator */}
            <div className="relative mt-4 flex items-center gap-2">
                <FileCode className="h-4 w-4 text-foreground-subtle" />
                <span className="text-sm text-foreground-muted">Docker Compose</span>
            </div>

            {/* Last updated */}
            <p className="relative mt-4 text-xs text-foreground-subtle">
                Updated {new Date(service.updated_at).toLocaleDateString()}
            </p>
        </Link>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-border/50 bg-background-secondary/30 py-16">
            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary/50">
                <Container className="h-8 w-8 text-foreground-muted" />
            </div>
            <h3 className="mt-4 text-lg font-medium text-foreground">No services yet</h3>
            <p className="mt-1 text-sm text-foreground-muted">
                Create your first Docker Compose service to deploy multi-container applications.
            </p>
            <Link href="/services/create" className="mt-6">
                <Button>
                    <Plus className="mr-2 h-4 w-4" />
                    Create Service
                </Button>
            </Link>
        </div>
    );
}
