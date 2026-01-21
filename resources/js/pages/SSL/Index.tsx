import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Badge, Input } from '@/components/ui';
import {
    Shield,
    Upload,
    AlertCircle,
    CheckCircle,
    Clock,
    RefreshCw,
    Calendar,
    Globe,
} from 'lucide-react';
import type { SSLCertificate } from '@/types';

interface Props {
    certificates: SSLCertificate[];
}

export default function SSLIndex({ certificates = [] }: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<string>('all');

    // Filter certificates
    const filteredCertificates = certificates.filter((cert) => {
        const matchesSearch =
            cert.domain.toLowerCase().includes(searchQuery.toLowerCase()) ||
            cert.domains.some((d) => d.toLowerCase().includes(searchQuery.toLowerCase())) ||
            cert.issuer.toLowerCase().includes(searchQuery.toLowerCase());
        const matchesStatus = statusFilter === 'all' || cert.status === statusFilter;
        return matchesSearch && matchesStatus;
    });

    // Stats
    const stats = {
        total: certificates.length,
        active: certificates.filter((c) => c.status === 'active').length,
        expiring: certificates.filter((c) => c.status === 'expiring_soon').length,
        expired: certificates.filter((c) => c.status === 'expired').length,
    };

    return (
        <AppLayout
            title="SSL Certificates"
            breadcrumbs={[{ label: 'SSL Certificates' }]}
        >
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">SSL Certificates</h1>
                    <p className="text-foreground-muted">Manage SSL certificates and auto-renewal</p>
                </div>
                <Link href="/ssl/upload">
                    <Button>
                        <Upload className="mr-2 h-4 w-4" />
                        Upload Certificate
                    </Button>
                </Link>
            </div>

            {/* Stats */}
            <div className="mb-6 grid gap-4 md:grid-cols-4">
                <StatsCard
                    label="Total Certificates"
                    value={stats.total}
                    icon={Shield}
                    variant="default"
                />
                <StatsCard
                    label="Active"
                    value={stats.active}
                    icon={CheckCircle}
                    variant="success"
                />
                <StatsCard
                    label="Expiring Soon"
                    value={stats.expiring}
                    icon={AlertCircle}
                    variant="warning"
                />
                <StatsCard
                    label="Expired"
                    value={stats.expired}
                    icon={AlertCircle}
                    variant="danger"
                />
            </div>

            {/* Filters */}
            <Card className="mb-6 p-4">
                <div className="grid gap-4 md:grid-cols-2">
                    <Input
                        placeholder="Search certificates by domain or issuer..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                    />
                    <div className="flex gap-2">
                        {(['all', 'active', 'expiring_soon', 'expired'] as const).map((status) => (
                            <button
                                key={status}
                                onClick={() => setStatusFilter(status)}
                                className={`flex-1 rounded-md border px-3 py-2 text-sm font-medium capitalize transition-colors ${
                                    statusFilter === status
                                        ? 'border-primary bg-primary/10 text-primary'
                                        : 'border-border bg-background-secondary text-foreground hover:bg-background-tertiary'
                                }`}
                            >
                                {status === 'all' ? 'All' : status.replace('_', ' ')}
                            </button>
                        ))}
                    </div>
                </div>
            </Card>

            {/* Certificates List */}
            {filteredCertificates.length === 0 ? (
                certificates.length === 0 ? <EmptyState /> : <NoResultsState />
            ) : (
                <div className="space-y-3">
                    {filteredCertificates.map((certificate) => (
                        <CertificateCard key={certificate.id} certificate={certificate} />
                    ))}
                </div>
            )}
        </AppLayout>
    );
}

interface StatsCardProps {
    label: string;
    value: number;
    icon: React.ElementType;
    variant: 'default' | 'success' | 'warning' | 'danger';
}

function StatsCard({ label, value, icon: Icon, variant }: StatsCardProps) {
    const colors = {
        default: 'text-foreground',
        success: 'text-primary',
        warning: 'text-warning',
        danger: 'text-danger',
    };

    return (
        <Card className="p-4">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm text-foreground-muted">{label}</p>
                    <p className={`mt-1 text-2xl font-bold ${colors[variant]}`}>{value}</p>
                </div>
                <Icon className={`h-8 w-8 ${colors[variant]}`} />
            </div>
        </Card>
    );
}

function CertificateCard({ certificate }: { certificate: SSLCertificate }) {
    const expiryDate = new Date(certificate.expires_at);
    const isExpiring = certificate.status === 'expiring_soon';
    const isExpired = certificate.status === 'expired';

    return (
        <Card className="p-4 transition-all hover:border-primary hover:shadow-lg">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <div className={`flex h-10 w-10 items-center justify-center rounded-lg ${
                            isExpired ? 'bg-danger/10' : isExpiring ? 'bg-warning/10' : 'bg-primary/10'
                        }`}>
                            <Shield className={`h-5 w-5 ${
                                isExpired ? 'text-danger' : isExpiring ? 'text-warning' : 'text-primary'
                            }`} />
                        </div>
                        <div className="flex-1">
                            <div className="flex items-center gap-2">
                                <h3 className="font-semibold text-foreground">{certificate.domain}</h3>
                                <Badge variant={certificate.type === 'letsencrypt' ? 'success' : 'info'}>
                                    {certificate.type === 'letsencrypt' ? "Let's Encrypt" : 'Custom'}
                                </Badge>
                            </div>
                            <p className="text-sm text-foreground-muted">
                                Issued by {certificate.issuer}
                            </p>
                        </div>
                    </div>

                    {/* Additional Domains */}
                    {certificate.domains.length > 1 && (
                        <div className="mt-3 flex flex-wrap gap-1">
                            {certificate.domains.slice(0, 5).map((domain) => (
                                <div
                                    key={domain}
                                    className="flex items-center gap-1 rounded-md bg-background-tertiary px-2 py-1 text-xs text-foreground-muted"
                                >
                                    <Globe className="h-3 w-3" />
                                    {domain}
                                </div>
                            ))}
                            {certificate.domains.length > 5 && (
                                <div className="rounded-md bg-background-tertiary px-2 py-1 text-xs text-foreground-muted">
                                    +{certificate.domains.length - 5} more
                                </div>
                            )}
                        </div>
                    )}
                </div>

                <div className="flex flex-col items-end gap-2">
                    <SSLStatusBadge status={certificate.status} />

                    <div className="flex items-center gap-1 text-xs text-foreground-muted">
                        <Calendar className="h-3 w-3" />
                        <span>
                            Expires {expiryDate.toLocaleDateString()}
                        </span>
                    </div>

                    <div className="text-xs text-foreground-muted">
                        {certificate.days_until_expiry > 0 ? (
                            <span>{certificate.days_until_expiry} days remaining</span>
                        ) : (
                            <span className="text-danger">Expired {Math.abs(certificate.days_until_expiry)} days ago</span>
                        )}
                    </div>
                </div>
            </div>

            {/* Auto-renewal status */}
            <div className="mt-3 flex items-center justify-between border-t border-border pt-3">
                <div className="flex items-center gap-2">
                    <RefreshCw className={`h-4 w-4 ${certificate.auto_renew ? 'text-primary' : 'text-foreground-muted'}`} />
                    <span className="text-sm text-foreground-muted">
                        Auto-renewal {certificate.auto_renew ? 'enabled' : 'disabled'}
                    </span>
                </div>

                <div className="flex gap-2">
                    {certificate.type === 'letsencrypt' && certificate.auto_renew && (
                        <Badge variant="success" className="text-xs">
                            Will auto-renew
                        </Badge>
                    )}
                    {!certificate.auto_renew && certificate.days_until_expiry < 30 && (
                        <Badge variant="warning" className="text-xs">
                            Action required
                        </Badge>
                    )}
                </div>
            </div>
        </Card>
    );
}

function SSLStatusBadge({ status }: { status: SSLCertificate['status'] }) {
    const variants = {
        active: { variant: 'success' as const, label: 'Active', icon: CheckCircle },
        pending: { variant: 'warning' as const, label: 'Pending', icon: Clock },
        expired: { variant: 'danger' as const, label: 'Expired', icon: AlertCircle },
        expiring_soon: { variant: 'warning' as const, label: 'Expiring Soon', icon: AlertCircle },
        failed: { variant: 'danger' as const, label: 'Failed', icon: AlertCircle },
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
        <Card className="p-12 text-center">
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                <Shield className="h-8 w-8 text-foreground-muted" />
            </div>
            <h3 className="mt-4 text-lg font-medium text-foreground">No SSL certificates yet</h3>
            <p className="mt-2 text-foreground-muted">
                SSL certificates are automatically generated when you add a domain. You can also upload custom certificates.
            </p>
            <div className="mt-6 flex justify-center gap-2">
                <Link href="/domains/add">
                    <Button>
                        Add Domain
                    </Button>
                </Link>
                <Link href="/ssl/upload">
                    <Button variant="secondary">
                        <Upload className="mr-2 h-4 w-4" />
                        Upload Certificate
                    </Button>
                </Link>
            </div>
        </Card>
    );
}

function NoResultsState() {
    return (
        <Card className="p-12 text-center">
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                <Shield className="h-8 w-8 text-foreground-muted" />
            </div>
            <h3 className="mt-4 text-lg font-medium text-foreground">No certificates found</h3>
            <p className="mt-2 text-foreground-muted">
                Try adjusting your search query or filters.
            </p>
        </Card>
    );
}
