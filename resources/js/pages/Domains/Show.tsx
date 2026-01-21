import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Badge, Input } from '@/components/ui';
import {
    Globe,
    Shield,
    Copy,
    Check,
    RefreshCw,
    Trash2,
    ExternalLink,
    AlertCircle,
    CheckCircle,
    Clock,
    Settings,
    ArrowLeft,
} from 'lucide-react';
import type { Domain, SSLCertificate } from '@/types';

interface Props {
    domain: Domain;
    sslCertificate?: SSLCertificate | null;
}

export default function DomainsShow({ domain, sslCertificate }: Props) {
    const [verifying, setVerifying] = useState(false);
    const [copiedField, setCopiedField] = useState<string | null>(null);

    const copyToClipboard = (text: string, field: string) => {
        navigator.clipboard.writeText(text);
        setCopiedField(field);
        setTimeout(() => setCopiedField(null), 2000);
    };

    const handleVerify = async () => {
        setVerifying(true);
        router.post(`/domains/${domain.id}/verify`, {}, {
            onFinish: () => setVerifying(false),
        });
    };

    const handleDelete = () => {
        if (confirm(`Are you sure you want to delete ${domain.domain}?`)) {
            router.delete(`/domains/${domain.id}`);
        }
    };

    return (
        <AppLayout
            title={domain.domain}
            breadcrumbs={[
                { label: 'Domains', href: '/domains' },
                { label: domain.domain },
            ]}
        >
            {/* Header */}
            <div className="mb-6">
                <Link href="/domains" className="mb-4 inline-flex items-center gap-2 text-sm text-foreground-muted hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" />
                    Back to Domains
                </Link>
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-background-tertiary">
                            <Globe className="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-2xl font-bold text-foreground">{domain.domain}</h1>
                                <a
                                    href={`https://${domain.domain}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-foreground-muted hover:text-foreground"
                                >
                                    <ExternalLink className="h-4 w-4" />
                                </a>
                            </div>
                            <p className="text-foreground-muted">
                                {domain.service_name} • {domain.service_type}
                            </p>
                        </div>
                    </div>

                    <div className="flex gap-2">
                        {!domain.verified_at && (
                            <Button
                                onClick={handleVerify}
                                loading={verifying}
                                variant="secondary"
                            >
                                <RefreshCw className="mr-2 h-4 w-4" />
                                Verify Domain
                            </Button>
                        )}
                        <Link href={`/domains/${domain.id}/redirects`}>
                            <Button variant="secondary">
                                <Settings className="mr-2 h-4 w-4" />
                                Redirects
                            </Button>
                        </Link>
                        <Button variant="danger" onClick={handleDelete}>
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                {/* Domain Status */}
                <Card>
                    <CardHeader>
                        <CardTitle>Domain Status</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-foreground-muted">Status</span>
                            <DomainStatusBadge status={domain.status} />
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-foreground-muted">SSL Status</span>
                            <SSLStatusBadge status={domain.ssl_status} />
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-foreground-muted">Verification</span>
                            {domain.verified_at ? (
                                <div className="flex items-center gap-1 text-sm text-primary">
                                    <CheckCircle className="h-4 w-4" />
                                    Verified
                                </div>
                            ) : (
                                <div className="flex items-center gap-1 text-sm text-warning">
                                    <Clock className="h-4 w-4" />
                                    Pending
                                </div>
                            )}
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-foreground-muted">Force HTTPS</span>
                            {domain.redirect_to_https ? (
                                <CheckCircle className="h-4 w-4 text-primary" />
                            ) : (
                                <span className="text-sm text-foreground-muted">Disabled</span>
                            )}
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-foreground-muted">WWW Redirect</span>
                            {domain.redirect_to_www ? (
                                <CheckCircle className="h-4 w-4 text-primary" />
                            ) : (
                                <span className="text-sm text-foreground-muted">Disabled</span>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* SSL Certificate */}
                {sslCertificate && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Shield className="h-5 w-5 text-primary" />
                                SSL Certificate
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-foreground-muted">Type</span>
                                <Badge variant={sslCertificate.type === 'letsencrypt' ? 'success' : 'info'}>
                                    {sslCertificate.type === 'letsencrypt' ? "Let's Encrypt" : 'Custom'}
                                </Badge>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-foreground-muted">Issuer</span>
                                <span className="text-sm text-foreground">{sslCertificate.issuer}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-foreground-muted">Expires</span>
                                <span className="text-sm text-foreground">
                                    {new Date(sslCertificate.expires_at).toLocaleDateString()}
                                    <span className="ml-1 text-foreground-muted">
                                        ({sslCertificate.days_until_expiry} days)
                                    </span>
                                </span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-foreground-muted">Auto-Renewal</span>
                                {sslCertificate.auto_renew ? (
                                    <div className="flex items-center gap-1 text-sm text-primary">
                                        <CheckCircle className="h-4 w-4" />
                                        Enabled
                                    </div>
                                ) : (
                                    <div className="flex items-center gap-1 text-sm text-warning">
                                        <AlertCircle className="h-4 w-4" />
                                        Disabled
                                    </div>
                                )}
                            </div>
                            {sslCertificate.domains.length > 1 && (
                                <div>
                                    <span className="text-sm text-foreground-muted">Covers Domains:</span>
                                    <div className="mt-2 flex flex-wrap gap-1">
                                        {sslCertificate.domains.map((d) => (
                                            <Badge key={d} variant="default">{d}</Badge>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* DNS Configuration */}
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>DNS Configuration</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {!domain.verified_at && (
                            <div className="mb-4 flex items-start gap-2 rounded-md bg-warning/10 p-3 text-sm text-warning">
                                <AlertCircle className="mt-0.5 h-4 w-4 flex-shrink-0" />
                                <div>
                                    <strong>Domain not verified yet.</strong>
                                    <p className="mt-1 text-foreground-muted">
                                        Add the following DNS records to your domain's DNS settings, then click "Verify Domain" to confirm.
                                    </p>
                                </div>
                            </div>
                        )}

                        <div className="space-y-4">
                            {domain.dns_records.map((record, index) => (
                                <div key={index} className="space-y-2 rounded-lg border border-border p-4">
                                    <div className="flex items-center justify-between">
                                        <Badge variant="info">{record.type} Record</Badge>
                                        {domain.verification_method === 'dns' && index === 0 && (
                                            <Badge variant="warning">Required for verification</Badge>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <div>
                                            <label className="text-xs font-medium text-foreground-muted">Name</label>
                                            <div className="mt-1 flex items-center gap-2">
                                                <Input
                                                    value={record.name}
                                                    readOnly
                                                    className="font-mono text-sm"
                                                />
                                                <Button
                                                    size="icon"
                                                    variant="secondary"
                                                    onClick={() => copyToClipboard(record.name, `name-${index}`)}
                                                >
                                                    {copiedField === `name-${index}` ? (
                                                        <Check className="h-4 w-4 text-primary" />
                                                    ) : (
                                                        <Copy className="h-4 w-4" />
                                                    )}
                                                </Button>
                                            </div>
                                        </div>

                                        <div>
                                            <label className="text-xs font-medium text-foreground-muted">Value</label>
                                            <div className="mt-1 flex items-center gap-2">
                                                <Input
                                                    value={record.value}
                                                    readOnly
                                                    className="font-mono text-sm"
                                                />
                                                <Button
                                                    size="icon"
                                                    variant="secondary"
                                                    onClick={() => copyToClipboard(record.value, `value-${index}`)}
                                                >
                                                    {copiedField === `value-${index}` ? (
                                                        <Check className="h-4 w-4 text-primary" />
                                                    ) : (
                                                        <Copy className="h-4 w-4" />
                                                    )}
                                                </Button>
                                            </div>
                                        </div>

                                        {record.ttl && (
                                            <div>
                                                <label className="text-xs font-medium text-foreground-muted">TTL</label>
                                                <div className="mt-1">
                                                    <Input
                                                        value={`${record.ttl} seconds`}
                                                        readOnly
                                                        className="text-sm"
                                                    />
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>

                        {domain.verification_method === 'http' && !domain.verified_at && (
                            <div className="mt-4 rounded-md bg-info/10 p-3 text-sm text-info">
                                <strong>HTTP Verification:</strong> Make sure your service is accessible at{' '}
                                <code className="rounded bg-background-tertiary px-1 py-0.5">
                                    http://{domain.domain}
                                </code>
                                . We'll verify domain ownership by checking for a verification file.
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function DomainStatusBadge({ status }: { status: Domain['status'] }) {
    const variants = {
        active: { variant: 'success' as const, label: 'Active', icon: CheckCircle },
        pending: { variant: 'warning' as const, label: 'Pending', icon: Clock },
        verifying: { variant: 'info' as const, label: 'Verifying', icon: RefreshCw },
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
