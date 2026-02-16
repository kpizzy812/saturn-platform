import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, Button, Badge, Input, useConfirm } from '@/components/ui';
import {
    Plus, Trash2, Star, CheckCircle, XCircle, AlertCircle,
    Copy, ExternalLink, Shield, Globe
} from 'lucide-react';
import type { Service } from '@/types';

interface Domain {
    id: number;
    domain: string;
    isPrimary: boolean;
    sslStatus: 'active' | 'pending' | 'failed' | 'none';
    sslProvider: 'letsencrypt' | 'custom' | null;
    createdAt: string;
}

interface Props {
    service: Service;
    domains?: Domain[];
}

export function DomainsTab({ service: _service, domains: initialDomains = [] }: Props) {
    const confirm = useConfirm();
    const [domains, setDomains] = useState<Domain[]>(initialDomains);
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);
    const [newDomain, setNewDomain] = useState('');
    const [showDnsInstructions, setShowDnsInstructions] = useState<number | null>(null);

    const handleAddDomain = (e: React.FormEvent) => {
        e.preventDefault();
        if (!newDomain) return;

        const domain: Domain = {
            id: Math.max(...domains.map((d) => d.id), 0) + 1,
            domain: newDomain,
            isPrimary: domains.length === 0,
            sslStatus: 'pending',
            sslProvider: 'letsencrypt',
            createdAt: new Date().toISOString().split('T')[0],
        };

        setDomains((prev) => [...prev, domain]);
        setNewDomain('');
        setIsAddModalOpen(false);
    };

    const handleSetPrimary = (id: number) => {
        setDomains((prev) =>
            prev.map((d) => ({
                ...d,
                isPrimary: d.id === id,
            }))
        );
    };

    const handleDelete = async (id: number) => {
        const confirmed = await confirm({
            title: 'Delete Domain',
            description: 'Are you sure you want to delete this domain? The domain will no longer point to this service.',
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            setDomains((prev) => prev.filter((d) => d.id !== id));
        }
    };

    const handleCopyDomain = async (domain: string) => {
        await navigator.clipboard.writeText(domain);
    };

    const getSSLStatusIcon = (status: Domain['sslStatus']) => {
        switch (status) {
            case 'active':
                return <CheckCircle className="h-4 w-4 text-primary" />;
            case 'pending':
                return <AlertCircle className="h-4 w-4 animate-pulse text-warning" />;
            case 'failed':
                return <XCircle className="h-4 w-4 text-danger" />;
            case 'none':
                return <Shield className="h-4 w-4 text-foreground-muted" />;
        }
    };

    const getSSLStatusBadge = (status: Domain['sslStatus']) => {
        switch (status) {
            case 'active':
                return <Badge variant="success">SSL Active</Badge>;
            case 'pending':
                return <Badge variant="warning">SSL Pending</Badge>;
            case 'failed':
                return <Badge variant="danger">SSL Failed</Badge>;
            case 'none':
                return <Badge variant="default">No SSL</Badge>;
        }
    };

    return (
        <div className="space-y-4">
            {/* Header Actions */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="font-medium text-foreground">Domain Management</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Manage custom domains and SSL certificates for your service
                            </p>
                        </div>
                        <Button onClick={() => setIsAddModalOpen(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Domain
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Domains List */}
            <div className="space-y-2">
                {domains.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <Globe className="h-12 w-12 text-foreground-subtle" />
                            <h3 className="mt-4 font-medium text-foreground">No domains configured</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Add a custom domain to get started
                            </p>
                            <Button className="mt-4" onClick={() => setIsAddModalOpen(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Domain
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    domains.map((domain) => (
                        <Card key={domain.id}>
                            <CardContent className="p-4">
                                <div className="flex items-start justify-between gap-4">
                                    <div className="flex-1">
                                        <div className="flex items-center gap-2">
                                            {getSSLStatusIcon(domain.sslStatus)}
                                            <code className="text-sm font-medium text-foreground">
                                                {domain.domain}
                                            </code>
                                            {domain.isPrimary && (
                                                <Badge variant="default" className="flex items-center gap-1">
                                                    <Star className="h-3 w-3" />
                                                    Primary
                                                </Badge>
                                            )}
                                            {getSSLStatusBadge(domain.sslStatus)}
                                        </div>

                                        <div className="mt-2 flex items-center gap-3 text-xs text-foreground-muted">
                                            {domain.sslProvider && (
                                                <>
                                                    <span>SSL Provider: {domain.sslProvider}</span>
                                                    <span>·</span>
                                                </>
                                            )}
                                            <span>Added {domain.createdAt}</span>
                                        </div>

                                        {/* DNS Instructions Toggle */}
                                        {showDnsInstructions === domain.id && (
                                            <div className="mt-4 rounded-lg border border-border bg-background-secondary p-4">
                                                <h4 className="text-sm font-medium text-foreground mb-2">
                                                    DNS Configuration
                                                </h4>
                                                <p className="text-xs text-foreground-muted mb-3">
                                                    Add the following DNS record to your domain provider:
                                                </p>
                                                <div className="space-y-2">
                                                    <div className="rounded bg-background p-3 font-mono text-xs">
                                                        <div className="grid grid-cols-4 gap-2 text-foreground-muted mb-1">
                                                            <span>Type</span>
                                                            <span>Name</span>
                                                            <span className="col-span-2">Value</span>
                                                        </div>
                                                        <div className="grid grid-cols-4 gap-2 text-foreground">
                                                            <span>A</span>
                                                            <span>@</span>
                                                            <span className="col-span-2">192.0.2.1</span>
                                                        </div>
                                                    </div>
                                                    <p className="text-xs text-foreground-muted">
                                                        Note: DNS propagation can take up to 48 hours
                                                    </p>
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    <div className="flex items-center gap-1">
                                        <button
                                            onClick={() => handleCopyDomain(domain.domain)}
                                            className="rounded p-2 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                                            title="Copy domain"
                                        >
                                            <Copy className="h-4 w-4" />
                                        </button>
                                        <a
                                            href={`https://${domain.domain}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="rounded p-2 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                                            title="Open domain"
                                        >
                                            <ExternalLink className="h-4 w-4" />
                                        </a>
                                        <button
                                            onClick={() =>
                                                setShowDnsInstructions(
                                                    showDnsInstructions === domain.id ? null : domain.id
                                                )
                                            }
                                            className="rounded px-3 py-2 text-xs font-medium text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                                        >
                                            DNS
                                        </button>
                                        {!domain.isPrimary && (
                                            <button
                                                onClick={() => handleSetPrimary(domain.id)}
                                                className="rounded p-2 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                                                title="Set as primary"
                                            >
                                                <Star className="h-4 w-4" />
                                            </button>
                                        )}
                                        <button
                                            onClick={() => handleDelete(domain.id)}
                                            className="rounded p-2 text-danger transition-colors hover:bg-danger/10"
                                            title="Delete domain"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))
                )}
            </div>

            {/* SSL Information */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Shield className="h-5 w-5" />
                        SSL Certificate Information
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="space-y-3 text-sm">
                        <p className="text-foreground-muted">
                            All domains are automatically secured with free SSL certificates from Let's Encrypt.
                        </p>
                        <ul className="list-disc list-inside space-y-1 text-foreground-muted">
                            <li>Certificates are automatically renewed before expiration</li>
                            <li>HTTPS is enforced by default for all domains</li>
                            <li>Certificate issuance typically takes 1-5 minutes</li>
                            <li>Ensure DNS records are properly configured before adding domains</li>
                        </ul>
                    </div>
                </CardContent>
            </Card>

            {/* Add Domain Modal */}
            {isAddModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div className="w-full max-w-md rounded-lg border border-border bg-background p-6 shadow-xl">
                        <h2 className="text-xl font-semibold text-foreground">Add Domain</h2>
                        <form onSubmit={handleAddDomain} className="mt-4 space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-foreground mb-2">
                                    Domain Name
                                </label>
                                <Input
                                    type="text"
                                    value={newDomain}
                                    onChange={(e) => setNewDomain(e.target.value)}
                                    placeholder="api.your-domain.com"
                                    required
                                />
                                <p className="mt-2 text-xs text-foreground-muted">
                                    Enter the full domain name (e.g., api.your-domain.com)
                                </p>
                            </div>
                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => {
                                        setIsAddModalOpen(false);
                                        setNewDomain('');
                                    }}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit">Add Domain</Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
