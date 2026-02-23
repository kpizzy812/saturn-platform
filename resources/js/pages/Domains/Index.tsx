import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, Button, Badge, Input, Select } from '@/components/ui';
import { Plus, Globe, Shield, ExternalLink, AlertCircle, CheckCircle, Clock } from 'lucide-react';
import { StaggerList, StaggerItem, FadeIn } from '@/components/animation';
import type { Domain } from '@/types';

interface Props {
    domains: Domain[];
}

export default function DomainsIndex({ domains = [] }: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<string>('all');
    const [serviceFilter, setServiceFilter] = useState<string>('all');

    // Filter domains
    const filteredDomains = domains.filter((domain) => {
        const matchesSearch = domain.domain.toLowerCase().includes(searchQuery.toLowerCase()) ||
            domain.service_name.toLowerCase().includes(searchQuery.toLowerCase());
        const matchesStatus = statusFilter === 'all' || domain.status === statusFilter;
        const matchesService = serviceFilter === 'all' || domain.service_id === serviceFilter;
        return matchesSearch && matchesStatus && matchesService;
    });

    // Get unique services for filter
    const services = Array.from(new Set(domains.map(d => ({ id: d.service_id, name: d.service_name }))))
        .filter((v, i, a) => a.findIndex(t => t.id === v.id) === i);

    return (
        <AppLayout
            title="Domains"
            breadcrumbs={[{ label: 'Domains' }]}
        >
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Custom Domains</h1>
                    <p className="text-foreground-muted">Manage custom domains and SSL certificates</p>
                </div>
                <Link href="/domains/add">
                    <Button className="group">
                        <Plus className="mr-2 h-4 w-4 group-hover:animate-wiggle" />
                        Add Domain
                    </Button>
                </Link>
            </div>

            {/* Filters */}
            <Card className="mb-6 p-4">
                <div className="grid gap-4 md:grid-cols-3">
                    <Input
                        placeholder="Search domains..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                    />
                    <Select
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        options={[
                            { value: 'all', label: 'All Statuses' },
                            { value: 'active', label: 'Active' },
                            { value: 'pending', label: 'Pending' },
                            { value: 'verifying', label: 'Verifying' },
                            { value: 'failed', label: 'Failed' },
                        ]}
                    />
                    <Select
                        value={serviceFilter}
                        onChange={(e) => setServiceFilter(e.target.value)}
                        options={[
                            { value: 'all', label: 'All Services' },
                            ...services.map(s => ({ value: s.id, label: s.name })),
                        ]}
                    />
                </div>
            </Card>

            {/* Domains List */}
            {filteredDomains.length === 0 ? (
                domains.length === 0 ? <EmptyState /> : <NoResultsState />
            ) : (
                <StaggerList className="space-y-3">
                    {filteredDomains.map((domain, i) => (
                        <StaggerItem key={domain.id} index={i}>
                            <DomainCard domain={domain} />
                        </StaggerItem>
                    ))}
                </StaggerList>
            )}
        </AppLayout>
    );
}

function DomainCard({ domain }: { domain: Domain }) {
    return (
        <Link href={`/domains/${domain.id}`}>
            <Card className="p-4 transition-all hover:border-primary hover:shadow-lg">
                <div className="flex items-start justify-between">
                    <div className="flex-1">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-background-tertiary">
                                <Globe className="h-5 w-5 text-primary" />
                            </div>
                            <div className="flex-1">
                                <div className="flex items-center gap-2">
                                    <h3 className="font-semibold text-foreground">{domain.domain}</h3>
                                    <ExternalLink className="h-3.5 w-3.5 text-foreground-muted" />
                                </div>
                                <p className="text-sm text-foreground-muted">
                                    {domain.service_name} • {domain.service_type}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-col items-end gap-2">
                        <div className="flex items-center gap-2">
                            <DomainStatusBadge status={domain.status} />
                            <SSLStatusBadge status={domain.ssl_status} />
                        </div>
                        {domain.verified_at ? (
                            <div className="flex items-center gap-1 text-xs text-foreground-muted">
                                <CheckCircle className="h-3 w-3 text-primary" />
                                <span>Verified</span>
                            </div>
                        ) : (
                            <div className="flex items-center gap-1 text-xs text-warning">
                                <Clock className="h-3 w-3" />
                                <span>Pending verification</span>
                            </div>
                        )}
                    </div>
                </div>

                {/* Redirects info */}
                {(domain.redirect_to_www || domain.redirect_to_https) && (
                    <div className="mt-3 flex gap-2 text-xs text-foreground-muted">
                        {domain.redirect_to_https && (
                            <div className="flex items-center gap-1">
                                <Shield className="h-3 w-3" />
                                <span>Force HTTPS</span>
                            </div>
                        )}
                        {domain.redirect_to_www && (
                            <span>→ www redirect</span>
                        )}
                    </div>
                )}
            </Card>
        </Link>
    );
}

function DomainStatusBadge({ status }: { status: Domain['status'] }) {
    const variants = {
        active: { variant: 'success' as const, label: 'Active' },
        pending: { variant: 'warning' as const, label: 'Pending' },
        verifying: { variant: 'info' as const, label: 'Verifying' },
        failed: { variant: 'danger' as const, label: 'Failed' },
    };

    const config = variants[status];
    return <Badge variant={config.variant}>{config.label}</Badge>;
}

function SSLStatusBadge({ status }: { status: Domain['ssl_status'] }) {
    const variants = {
        active: { variant: 'success' as const, label: 'SSL Active', icon: Shield },
        pending: { variant: 'warning' as const, label: 'SSL Pending', icon: Clock },
        expired: { variant: 'danger' as const, label: 'SSL Expired', icon: AlertCircle },
        expiring_soon: { variant: 'warning' as const, label: 'SSL Expiring', icon: AlertCircle },
        failed: { variant: 'danger' as const, label: 'SSL Failed', icon: AlertCircle },
    };

    const config = variants[status];
    const Icon = config.icon;

    return (
        <Badge variant={config.variant} className="flex items-center gap-1">
            <Icon className="h-3 w-3" />
            {config.label}
        </Badge>
    );
}

function EmptyState() {
    return (
        <FadeIn>
            <Card className="p-12 text-center">
                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                    <Globe className="h-8 w-8 text-foreground-muted animate-pulse-soft" />
                </div>
                <h3 className="mt-4 text-lg font-medium text-foreground">No domains yet</h3>
                <p className="mt-2 text-foreground-muted">
                    Add a custom domain to your services and configure SSL certificates.
                </p>
                <Link href="/domains/add" className="mt-6 inline-block">
                    <Button>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Domain
                    </Button>
                </Link>
            </Card>
        </FadeIn>
    );
}

function NoResultsState() {
    return (
        <FadeIn>
            <Card className="p-12 text-center">
                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                    <Globe className="h-8 w-8 text-foreground-muted animate-pulse-soft" />
                </div>
                <h3 className="mt-4 text-lg font-medium text-foreground">No domains found</h3>
                <p className="mt-2 text-foreground-muted">
                    Try adjusting your search query or filters.
                </p>
            </Card>
        </FadeIn>
    );
}
