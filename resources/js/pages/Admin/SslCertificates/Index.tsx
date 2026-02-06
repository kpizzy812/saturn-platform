import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { router } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import {
    Shield,
    AlertTriangle,
    CheckCircle,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';

interface SslCertificate {
    id: number;
    common_name: string | null;
    subject_alternative_names: string | null;
    valid_until: string | null;
    days_until_expiry: number | null;
    is_ca_certificate: boolean;
    resource_type: string | null;
    resource_id: number | null;
    server_id: number;
    server_name: string;
    created_at: string;
}

interface ServerOption {
    id: number;
    name: string;
}

interface Props {
    certificates: {
        data: SslCertificate[];
        current_page: number;
        last_page: number;
        total: number;
    };
    stats: {
        total: number;
        expiringSoon: number;
        expired: number;
        valid: number;
    };
    servers: ServerOption[];
    filters: {
        expiry?: string;
        server_id?: string;
    };
}

// Helper component for stats cards
function StatCard({
    label,
    value,
    icon,
    variant = 'default',
}: {
    label: string;
    value: number;
    icon: React.ReactNode;
    variant?: 'default' | 'success' | 'danger' | 'warning';
}) {
    const colorMap = {
        default: 'text-primary',
        success: 'text-success',
        danger: 'text-danger',
        warning: 'text-warning',
    };

    const iconColorMap = {
        default: 'text-primary/50',
        success: 'text-success/50',
        danger: 'text-danger/50',
        warning: 'text-warning/50',
    };

    return (
        <Card variant="glass">
            <CardContent className="p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-sm text-foreground-subtle">{label}</p>
                        <p className={`text-2xl font-bold ${colorMap[variant]}`}>{value}</p>
                    </div>
                    <div className={iconColorMap[variant]}>{icon}</div>
                </div>
            </CardContent>
        </Card>
    );
}

// Helper function for relative time formatting
function formatRelativeTime(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (seconds < 60) return `${seconds}s ago`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    if (seconds < 2592000) return `${Math.floor(seconds / 86400)}d ago`;
    return date.toLocaleDateString();
}

function CertificateRow({ certificate }: { certificate: SslCertificate }) {
    const getExpiryBadge = (days: number | null) => {
        if (days === null) return null;
        if (days <= 0) {
            return <Badge variant="danger" size="sm">Expired</Badge>;
        }
        if (days <= 30) {
            return <Badge variant="warning" size="sm">{days}d left</Badge>;
        }
        return <Badge variant="success" size="sm">{days}d left</Badge>;
    };

    return (
        <div className="border-b border-border/50 py-4 last:border-0">
            <div className="flex items-start justify-between">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <Shield className="h-5 w-5 text-foreground-muted" />
                        <div className="flex-1">
                            <div className="flex items-center gap-2">
                                <span className="font-medium text-foreground">
                                    {certificate.common_name || 'N/A'}
                                </span>
                                {certificate.is_ca_certificate && (
                                    <Badge variant="primary" size="sm">CA</Badge>
                                )}
                            </div>
                            <div className="mt-1 flex flex-col gap-1">
                                {certificate.subject_alternative_names && (
                                    <span className="text-xs text-foreground-subtle truncate max-w-[600px]">
                                        SANs: {certificate.subject_alternative_names.length > 80
                                            ? `${certificate.subject_alternative_names.substring(0, 80)}...`
                                            : certificate.subject_alternative_names}
                                    </span>
                                )}
                                <div className="flex items-center gap-2">
                                    <span className="text-xs text-foreground-subtle">
                                        Server: {certificate.server_name}
                                    </span>
                                    {getExpiryBadge(certificate.days_until_expiry)}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <span className="text-xs text-foreground-subtle">
                    {formatRelativeTime(certificate.created_at)}
                </span>
            </div>
        </div>
    );
}

export default function AdminSslCertificatesIndex({ certificates, stats, servers, filters }: Props) {
    const items = certificates?.data ?? [];
    const currentPage = certificates?.current_page ?? 1;
    const lastPage = certificates?.last_page ?? 1;
    const total = certificates?.total ?? 0;

    const handleFilterChange = (key: string, value: string) => {
        const params = new URLSearchParams();
        const merged = { ...filters, [key]: value };

        Object.entries(merged).forEach(([k, v]) => {
            if (v && v !== 'all') {
                params.set(k, v);
            }
        });

        router.get(`/admin/ssl-certificates?${params.toString()}`, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handlePageChange = (page: number) => {
        const params = new URLSearchParams();
        Object.entries(filters).forEach(([k, v]) => {
            if (v && v !== 'all') {
                params.set(k, v);
            }
        });
        params.set('page', String(page));

        router.get(`/admin/ssl-certificates?${params.toString()}`, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout
            title="SSL Certificates"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'SSL Certificates' },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">SSL Certificates</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Certificate management and expiration tracking
                    </p>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-4">
                    <StatCard
                        label="Total"
                        value={stats.total}
                        icon={<Shield className="h-8 w-8" />}
                        variant="default"
                    />
                    <StatCard
                        label="Valid"
                        value={stats.valid}
                        icon={<CheckCircle className="h-8 w-8" />}
                        variant="success"
                    />
                    <StatCard
                        label="Expiring Soon"
                        value={stats.expiringSoon}
                        icon={<AlertTriangle className="h-8 w-8" />}
                        variant="warning"
                    />
                    <StatCard
                        label="Expired"
                        value={stats.expired}
                        icon={<AlertTriangle className="h-8 w-8" />}
                        variant="danger"
                    />
                </div>

                {/* Filters */}
                <Card variant="glass" className="mb-6">
                    <CardContent className="p-4">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                            <div className="flex-1">
                                <select
                                    value={filters.expiry || 'all'}
                                    onChange={(e) => handleFilterChange('expiry', e.target.value)}
                                    className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground"
                                >
                                    <option value="all">All Expiry States</option>
                                    <option value="expiring">Expiring Soon</option>
                                    <option value="expired">Expired</option>
                                </select>
                            </div>
                            <div className="flex-1">
                                <select
                                    value={filters.server_id || ''}
                                    onChange={(e) => handleFilterChange('server_id', e.target.value)}
                                    className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground"
                                >
                                    <option value="">All Servers</option>
                                    {servers.map((server) => (
                                        <option key={server.id} value={String(server.id)}>
                                            {server.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Certificates Table */}
                <Card variant="glass">
                    <CardContent className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <p className="text-sm text-foreground-muted">
                                Showing {items.length} of {total} certificates
                            </p>
                        </div>

                        {items.length === 0 ? (
                            <div className="py-12 text-center">
                                <Shield className="mx-auto h-12 w-12 text-foreground-muted" />
                                <p className="mt-4 text-sm text-foreground-muted">No certificates found</p>
                                <p className="text-xs text-foreground-subtle">
                                    Try adjusting your filters
                                </p>
                            </div>
                        ) : (
                            <>
                                <div>
                                    {items.map((certificate) => (
                                        <CertificateRow key={certificate.id} certificate={certificate} />
                                    ))}
                                </div>

                                {/* Pagination */}
                                {lastPage > 1 && (
                                    <div className="mt-6 flex items-center justify-between border-t border-border/50 pt-4">
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            onClick={() => handlePageChange(currentPage - 1)}
                                            disabled={currentPage === 1}
                                        >
                                            <ChevronLeft className="mr-1 h-4 w-4" />
                                            Previous
                                        </Button>
                                        <span className="text-sm text-foreground-muted">
                                            Page {currentPage} of {lastPage}
                                        </span>
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            onClick={() => handlePageChange(currentPage + 1)}
                                            disabled={currentPage === lastPage}
                                        >
                                            Next
                                            <ChevronRight className="ml-1 h-4 w-4" />
                                        </Button>
                                    </div>
                                )}
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
