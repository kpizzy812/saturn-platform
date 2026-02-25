import * as React from 'react';
import { router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Input, Badge, useConfirm } from '@/components/ui';
import { Globe, Plus, Trash2, Star, ExternalLink } from 'lucide-react';
import type { Application } from '@/types';

interface Props {
    application: Application;
    domains?: SimpleDomain[];
    projectUuid?: string;
    projectName?: string;
    environmentUuid?: string;
    environmentName?: string;
}

// Simple structure from backend (matches routes/web.php)
interface SimpleDomain {
    id: number;
    domain: string;
    is_primary: boolean;
}

export default function ApplicationDomains({ application, domains: propDomains, projectUuid, projectName }: Props) {
    const confirm = useConfirm();
    const [domains, setDomains] = React.useState<SimpleDomain[]>(propDomains || []);
    const [newDomain, setNewDomain] = React.useState('');
    const [isAdding, setIsAdding] = React.useState(false);

    const handleAddDomain = async () => {
        if (!newDomain.trim()) return;

        setIsAdding(true);

        // Build new fqdn string (comma-separated)
        const currentFqdns = domains.map(d => d.domain);
        const newFqdns = [...currentFqdns, newDomain.trim()].join(',');

        router.patch(`/api/v1/applications/${application.uuid}`, {
            fqdn: newFqdns,
        }, {
            onSuccess: () => {
                // Add to local state
                setDomains([...domains, {
                    id: domains.length,
                    domain: newDomain.trim(),
                    is_primary: domains.length === 0,
                }]);
                setNewDomain('');
            },
            onFinish: () => {
                setIsAdding(false);
            },
        });
    };

    const handleRemoveDomain = async (domainToRemove: string) => {
        const confirmed = await confirm({
            title: 'Remove Domain',
            description: `Are you sure you want to remove "${domainToRemove}"? The domain will no longer point to this application.`,
            confirmText: 'Remove',
            variant: 'danger',
        });
        if (!confirmed) return;

        const newFqdns = domains
            .filter(d => d.domain !== domainToRemove)
            .map(d => d.domain)
            .join(',');

        router.patch(`/api/v1/applications/${application.uuid}`, {
            fqdn: newFqdns || null,
        }, {
            onSuccess: () => {
                setDomains(domains.filter(d => d.domain !== domainToRemove));
            },
        });
    };

    const handleSetPrimary = async (domain: string) => {
        // Primary domain should be first in the list
        const otherDomains = domains.filter(d => d.domain !== domain);
        const newFqdns = [domain, ...otherDomains.map(d => d.domain)].join(',');

        router.patch(`/api/v1/applications/${application.uuid}`, {
            fqdn: newFqdns,
        }, {
            onSuccess: () => {
                setDomains(domains.map((d, _idx) => ({
                    ...d,
                    is_primary: d.domain === domain,
                })));
            },
        });
    };

    const breadcrumbs = [
        { label: 'Projects', href: '/projects' },
        ...(projectUuid ? [{ label: projectName || 'Project', href: `/projects/${projectUuid}` }] : []),
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
                            Manage custom domains for your application
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
                            placeholder="app.your-domain.com"
                            className="flex-1"
                            onKeyPress={(e) => e.key === 'Enter' && handleAddDomain()}
                        />
                        <Button
                            variant="default"
                            onClick={handleAddDomain}
                            disabled={isAdding || !newDomain.trim()}
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            {isAdding ? 'Adding...' : 'Add Domain'}
                        </Button>
                    </div>
                    <p className="text-xs text-foreground-muted mt-2">
                        Make sure to point your domain's DNS to your server's IP address.
                    </p>
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
                                Add a custom domain to make your application accessible via your own URL.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    domains.map((domain) => (
                        <Card key={domain.id}>
                            <CardContent className="p-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-3">
                                        <Globe className="h-5 w-5 text-foreground-muted" />
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <a
                                                    href={`https://${domain.domain}`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-lg font-medium text-foreground hover:text-primary flex items-center gap-1"
                                                >
                                                    {domain.domain}
                                                    <ExternalLink className="h-3.5 w-3.5" />
                                                </a>
                                                {domain.is_primary && (
                                                    <Badge variant="success" className="flex items-center gap-1">
                                                        <Star className="h-3 w-3" />
                                                        Primary
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex gap-2">
                                        {!domain.is_primary && (
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                onClick={() => handleSetPrimary(domain.domain)}
                                            >
                                                <Star className="mr-1 h-4 w-4" />
                                                Set Primary
                                            </Button>
                                        )}
                                        <Button
                                            size="sm"
                                            variant="danger"
                                            onClick={() => handleRemoveDomain(domain.domain)}
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

            {/* Help Card */}
            {domains.length > 0 && (
                <Card className="mt-6 border-info/50 bg-info/5">
                    <CardContent className="p-4">
                        <h3 className="text-sm font-semibold text-foreground mb-2">DNS Configuration</h3>
                        <p className="text-sm text-foreground-muted">
                            Point your domain to your server's IP address using an A record or CNAME record at your DNS provider.
                            SSL certificates are automatically provisioned via Let's Encrypt.
                        </p>
                    </CardContent>
                </Card>
            )}
        </AppLayout>
    );
}
