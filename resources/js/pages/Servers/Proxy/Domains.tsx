import { useState } from 'react';
import { router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Badge, useConfirm } from '@/components/ui';
import {
    Globe, Plus, Trash2, ShieldCheck, ShieldAlert,
    ExternalLink, RefreshCw, Lock, Unlock
} from 'lucide-react';
import type { Server as ServerType } from '@/types';

interface Domain {
    id: number;
    domain: string;
    ssl_enabled: boolean;
    ssl_certificate: {
        status: 'active' | 'pending' | 'expired' | 'error';
        issuer: string;
        expires_at: string;
    } | null;
    force_https: boolean;
    created_at: string;
}

interface Props {
    server: ServerType;
    domains: Domain[];
}

export default function ProxyDomains({ server, domains: initialDomains }: Props) {
    const confirm = useConfirm();
    const [domains, setDomains] = useState<Domain[]>(initialDomains);
    const [showAddModal, setShowAddModal] = useState(false);
    const [newDomain, setNewDomain] = useState('');

    const handleAddDomain = () => {
        if (!newDomain.trim()) return;

        router.post(`/servers/${server.uuid}/proxy/domains`, {
            domain: newDomain,
        }, {
            onSuccess: () => {
                setNewDomain('');
                setShowAddModal(false);
            },
        });
    };

    const handleDeleteDomain = async (domainId: number) => {
        const confirmed = await confirm({
            title: 'Remove Domain',
            description: 'Are you sure you want to remove this domain?',
            confirmText: 'Remove',
            variant: 'danger',
        });
        if (!confirmed) return;

        router.delete(`/servers/${server.uuid}/proxy/domains/${domainId}`);
    };

    const handleToggleHttps = (domainId: number, currentValue: boolean) => {
        router.patch(`/servers/${server.uuid}/proxy/domains/${domainId}`, {
            force_https: !currentValue,
        });
    };

    const handleRenewCertificate = (domainId: number) => {
        router.post(`/servers/${server.uuid}/proxy/domains/${domainId}/renew-certificate`);
    };

    const getSslStatusColor = (status: Domain['ssl_certificate']['status']) => {
        switch (status) {
            case 'active': return 'success';
            case 'pending': return 'warning';
            case 'expired': return 'danger';
            case 'error': return 'danger';
            default: return 'secondary';
        }
    };

    const getSslStatusIcon = (status: Domain['ssl_certificate']['status']) => {
        switch (status) {
            case 'active': return <ShieldCheck className="h-4 w-4" />;
            case 'pending': return <RefreshCw className="h-4 w-4" />;
            case 'expired': return <ShieldAlert className="h-4 w-4" />;
            case 'error': return <ShieldAlert className="h-4 w-4" />;
            default: return <ShieldAlert className="h-4 w-4" />;
        }
    };

    return (
        <AppLayout
            title={`Proxy Domains - ${server.name}`}
            breadcrumbs={[
                { label: 'Servers', href: '/servers' },
                { label: server.name, href: `/servers/${server.uuid}` },
                { label: 'Proxy', href: `/servers/${server.uuid}/proxy` },
                { label: 'Domains' },
            ]}
        >
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10">
                        <Globe className="h-7 w-7 text-primary" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Domain Management</h1>
                        <p className="text-foreground-muted">{domains.length} configured domains</p>
                    </div>
                </div>
                <Button
                    variant="primary"
                    size="sm"
                    onClick={() => setShowAddModal(true)}
                >
                    <Plus className="mr-2 h-4 w-4" />
                    Add Domain
                </Button>
            </div>

            {/* Add Domain Modal */}
            {showAddModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <Card className="w-full max-w-md">
                        <CardHeader>
                            <CardTitle>Add Domain</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <div>
                                    <label className="mb-2 block text-sm font-medium text-foreground">
                                        Domain Name
                                    </label>
                                    <input
                                        type="text"
                                        placeholder="example.com"
                                        value={newDomain}
                                        onChange={(e) => setNewDomain(e.target.value)}
                                        className="w-full rounded-lg border border-border bg-background px-4 py-2 text-foreground placeholder:text-foreground-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') handleAddDomain();
                                            if (e.key === 'Escape') setShowAddModal(false);
                                        }}
                                    />
                                    <p className="mt-1 text-xs text-foreground-muted">
                                        Enter the domain without http:// or https://
                                    </p>
                                </div>
                                <div className="flex justify-end gap-2">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setShowAddModal(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        variant="primary"
                                        size="sm"
                                        onClick={handleAddDomain}
                                        disabled={!newDomain.trim()}
                                    >
                                        Add Domain
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            )}

            {/* Domains List */}
            {domains.length === 0 ? (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16">
                        <Globe className="h-12 w-12 text-foreground-subtle" />
                        <h3 className="mt-4 font-medium text-foreground">No domains configured</h3>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Add a domain to start managing SSL certificates and routing
                        </p>
                        <Button
                            variant="primary"
                            size="sm"
                            className="mt-4"
                            onClick={() => setShowAddModal(true)}
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Add Domain
                        </Button>
                    </CardContent>
                </Card>
            ) : (
                <div className="grid gap-4">
                    {domains.map((domain) => (
                        <Card key={domain.id}>
                            <CardContent className="p-6">
                                <div className="flex items-start justify-between">
                                    <div className="flex-1">
                                        <div className="flex items-center gap-3">
                                            <h3 className="text-lg font-semibold text-foreground">
                                                {domain.domain}
                                            </h3>
                                            <a
                                                href={`https://${domain.domain}`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="text-primary hover:text-primary-hover"
                                            >
                                                <ExternalLink className="h-4 w-4" />
                                            </a>
                                        </div>

                                        {/* SSL Certificate Status */}
                                        <div className="mt-3 space-y-2">
                                            <div className="flex items-center gap-2">
                                                <span className="text-sm text-foreground-muted">SSL Certificate:</span>
                                                {domain.ssl_certificate ? (
                                                    <Badge variant={getSslStatusColor(domain.ssl_certificate.status)}>
                                                        <span className="mr-1">{getSslStatusIcon(domain.ssl_certificate.status)}</span>
                                                        {domain.ssl_certificate.status.charAt(0).toUpperCase() + domain.ssl_certificate.status.slice(1)}
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">Not configured</Badge>
                                                )}
                                            </div>

                                            {domain.ssl_certificate && (
                                                <div className="space-y-1 text-sm text-foreground-muted">
                                                    <div>Issuer: {domain.ssl_certificate.issuer}</div>
                                                    <div>Expires: {new Date(domain.ssl_certificate.expires_at).toLocaleDateString()}</div>
                                                </div>
                                            )}

                                            {/* HTTPS Toggle */}
                                            <div className="flex items-center gap-2">
                                                <button
                                                    onClick={() => handleToggleHttps(domain.id, domain.force_https)}
                                                    className="flex items-center gap-2 text-sm text-foreground-muted hover:text-foreground"
                                                >
                                                    {domain.force_https ? (
                                                        <>
                                                            <Lock className="h-4 w-4 text-success" />
                                                            <span>Force HTTPS enabled</span>
                                                        </>
                                                    ) : (
                                                        <>
                                                            <Unlock className="h-4 w-4 text-warning" />
                                                            <span>Force HTTPS disabled</span>
                                                        </>
                                                    )}
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Actions */}
                                    <div className="flex items-center gap-2">
                                        {domain.ssl_certificate && (
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                onClick={() => handleRenewCertificate(domain.id)}
                                            >
                                                <RefreshCw className="mr-2 h-4 w-4" />
                                                Renew SSL
                                            </Button>
                                        )}
                                        <Button
                                            variant="danger"
                                            size="sm"
                                            onClick={() => handleDeleteDomain(domain.id)}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}

            {/* Help Card */}
            <div className="mt-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Domain Configuration Help</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-start gap-2">
                            <ShieldCheck className="mt-0.5 h-4 w-4 text-primary" />
                            <div>
                                <p className="text-sm font-medium text-foreground">Automatic SSL</p>
                                <p className="text-sm text-foreground-muted">
                                    SSL certificates are automatically generated using Let's Encrypt when you add a domain.
                                </p>
                            </div>
                        </div>
                        <div className="flex items-start gap-2">
                            <Lock className="mt-0.5 h-4 w-4 text-primary" />
                            <div>
                                <p className="text-sm font-medium text-foreground">Force HTTPS</p>
                                <p className="text-sm text-foreground-muted">
                                    Enable Force HTTPS to automatically redirect HTTP traffic to HTTPS.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
