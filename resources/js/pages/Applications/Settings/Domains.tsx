import * as React from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Input, Badge } from '@/components/ui';
import { Globe, Plus, Trash2, RefreshCw, CheckCircle, XCircle, Clock, Shield } from 'lucide-react';
import type { Application, Domain } from '@/types';

interface Props {
    application: Application;
    domains?: Domain[];
    projectUuid?: string;
    environmentUuid?: string;
}

// Mock data for demo
const MOCK_DOMAINS: Domain[] = [
    {
        id: '1',
        domain: 'app.example.com',
        status: 'active',
        ssl_status: 'active',
        service_id: '1',
        service_name: 'API Server',
        service_type: 'application',
        verification_method: 'dns',
        verified_at: new Date(Date.now() - 1000 * 60 * 60 * 24).toISOString(),
        redirect_to_www: false,
        redirect_to_https: true,
        ssl_certificate_id: 'cert-1',
        dns_records: [
            { type: 'A', name: 'app.example.com', value: '192.0.2.1' },
        ],
        created_at: new Date(Date.now() - 1000 * 60 * 60 * 24 * 30).toISOString(),
        updated_at: new Date(Date.now() - 1000 * 60 * 60 * 24).toISOString(),
    },
    {
        id: '2',
        domain: 'api.example.com',
        status: 'active',
        ssl_status: 'expiring_soon',
        service_id: '1',
        service_name: 'API Server',
        service_type: 'application',
        verification_method: 'dns',
        verified_at: new Date(Date.now() - 1000 * 60 * 60 * 24 * 15).toISOString(),
        redirect_to_www: false,
        redirect_to_https: true,
        ssl_certificate_id: 'cert-2',
        dns_records: [
            { type: 'A', name: 'api.example.com', value: '192.0.2.1' },
        ],
        created_at: new Date(Date.now() - 1000 * 60 * 60 * 24 * 20).toISOString(),
        updated_at: new Date(Date.now() - 1000 * 60 * 60 * 24 * 15).toISOString(),
    },
];

export default function ApplicationDomains({ application, domains: propDomains, projectUuid, environmentUuid }: Props) {
    const [domains, setDomains] = React.useState<Domain[]>(propDomains || MOCK_DOMAINS);
    const [newDomain, setNewDomain] = React.useState('');
    const [isAdding, setIsAdding] = React.useState(false);

    const handleAddDomain = async () => {
        if (!newDomain.trim()) return;

        setIsAdding(true);
        try {
            router.post(`/api/v1/applications/${application.uuid}/domains`, {
                domain: newDomain,
            }, {
                onSuccess: () => {
                    setNewDomain('');
                },
                onFinish: () => {
                    setIsAdding(false);
                },
            });
        } catch (error) {
            setIsAdding(false);
        }
    };

    const handleRemoveDomain = async (domainId: string) => {
        if (!confirm('Are you sure you want to remove this domain?')) return;

        router.delete(`/api/v1/applications/${application.uuid}/domains/${domainId}`);
    };

    const handleRenewCertificate = async (domainId: string) => {
        router.post(`/api/v1/applications/${application.uuid}/domains/${domainId}/renew-certificate`);
    };

    const getStatusBadge = (status: Domain['status']) => {
        switch (status) {
            case 'active':
                return <Badge variant="success">Active</Badge>;
            case 'pending':
                return <Badge variant="warning">Pending</Badge>;
            case 'failed':
                return <Badge variant="error">Failed</Badge>;
            case 'verifying':
                return <Badge variant="info">Verifying</Badge>;
        }
    };

    const getSSLBadge = (sslStatus: Domain['ssl_status']) => {
        switch (sslStatus) {
            case 'active':
                return <Badge variant="success" className="flex items-center gap-1">
                    <Shield className="h-3 w-3" />
                    Active
                </Badge>;
            case 'pending':
                return <Badge variant="warning">Pending</Badge>;
            case 'expired':
                return <Badge variant="error">Expired</Badge>;
            case 'expiring_soon':
                return <Badge variant="warning">Expiring Soon</Badge>;
            case 'failed':
                return <Badge variant="error">Failed</Badge>;
        }
    };

    const breadcrumbs = [
        { label: 'Projects', href: '/projects' },
        ...(projectUuid ? [{ label: 'Project', href: `/projects/${projectUuid}` }] : []),
        ...(environmentUuid ? [{ label: 'Environment', href: `/projects/${projectUuid}/environments/${environmentUuid}` }] : []),
        { label: application.name, href: `/applications/${application.uuid}` },
        { label: 'Domains' },
    ];

    return (
        <AppLayout title="Domain Management" breadcrumbs={breadcrumbs}>
            {/* Header */}
            <div className="mb-6">
                <div className="flex items-start gap-4 mb-4">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/15 text-primary">
                        <Globe className="h-6 w-6" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Domain Management</h1>
                        <p className="text-foreground-muted">
                            Manage custom domains and SSL certificates for your application
                        </p>
                    </div>
                </div>
            </div>

            {/* Add Domain */}
            <Card className="mb-6">
                <CardContent className="p-6">
                    <h2 className="text-lg font-semibold text-foreground mb-4">Add New Domain</h2>
                    <div className="flex gap-3">
                        <Input
                            value={newDomain}
                            onChange={(e) => setNewDomain(e.target.value)}
                            placeholder="example.com"
                            className="flex-1"
                            onKeyPress={(e) => e.key === 'Enter' && handleAddDomain()}
                        />
                        <Button
                            variant="primary"
                            onClick={handleAddDomain}
                            disabled={isAdding || !newDomain.trim()}
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            {isAdding ? 'Adding...' : 'Add Domain'}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Domains List */}
            <div className="space-y-4">
                {domains.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16">
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                                <Globe className="h-8 w-8 text-foreground-muted" />
                            </div>
                            <h3 className="mt-4 text-lg font-medium text-foreground">No domains configured</h3>
                            <p className="mt-2 text-center text-sm text-foreground-muted">
                                Add a custom domain to make your application accessible
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    domains.map((domain) => (
                        <Card key={domain.id}>
                            <CardContent className="p-6">
                                <div className="flex items-start justify-between">
                                    <div className="flex-1">
                                        <div className="flex items-center gap-3 mb-2">
                                            <h3 className="text-lg font-semibold text-foreground">{domain.domain}</h3>
                                            {getStatusBadge(domain.status)}
                                            {getSSLBadge(domain.ssl_status)}
                                        </div>

                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                            <div>
                                                <p className="text-sm text-foreground-muted mb-1">DNS Configuration</p>
                                                {domain.dns_records.map((record, idx) => (
                                                    <div key={idx} className="bg-background-secondary rounded px-3 py-2 mb-2">
                                                        <code className="text-xs text-foreground">
                                                            <span className="text-primary">{record.type}</span> {record.name} → {record.value}
                                                        </code>
                                                    </div>
                                                ))}
                                            </div>

                                            <div>
                                                <p className="text-sm text-foreground-muted mb-2">Settings</p>
                                                <div className="space-y-2">
                                                    <div className="flex items-center gap-2">
                                                        {domain.redirect_to_https ? (
                                                            <CheckCircle className="h-4 w-4 text-success" />
                                                        ) : (
                                                            <XCircle className="h-4 w-4 text-foreground-muted" />
                                                        )}
                                                        <span className="text-sm text-foreground">Redirect to HTTPS</span>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        {domain.redirect_to_www ? (
                                                            <CheckCircle className="h-4 w-4 text-success" />
                                                        ) : (
                                                            <XCircle className="h-4 w-4 text-foreground-muted" />
                                                        )}
                                                        <span className="text-sm text-foreground">Redirect to WWW</span>
                                                    </div>
                                                    {domain.verified_at && (
                                                        <div className="flex items-center gap-2">
                                                            <Clock className="h-4 w-4 text-foreground-muted" />
                                                            <span className="text-sm text-foreground-muted">
                                                                Verified {new Date(domain.verified_at).toLocaleDateString()}
                                                            </span>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex gap-2 ml-4">
                                        {domain.ssl_status === 'expiring_soon' && (
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                onClick={() => handleRenewCertificate(domain.id)}
                                            >
                                                <RefreshCw className="mr-2 h-4 w-4" />
                                                Renew SSL
                                            </Button>
                                        )}
                                        <Button
                                            size="sm"
                                            variant="danger"
                                            onClick={() => handleRemoveDomain(domain.id)}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))
                )}
            </div>
        </AppLayout>
    );
}
