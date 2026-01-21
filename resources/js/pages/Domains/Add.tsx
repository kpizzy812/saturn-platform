import { useState, FormEvent } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Input, Select, Checkbox } from '@/components/ui';
import { Globe, ArrowLeft, AlertCircle, Info, Copy, Check } from 'lucide-react';
import type { Application, StandaloneDatabase, Service } from '@/types';

interface Props {
    applications: Application[];
    databases: StandaloneDatabase[];
    services: Service[];
}

export default function DomainsAdd({ applications = [], databases = [], services = [] }: Props) {
    const [domain, setDomain] = useState('');
    const [serviceType, setServiceType] = useState<'application' | 'database' | 'service'>('application');
    const [serviceId, setServiceId] = useState('');
    const [verificationMethod, setVerificationMethod] = useState<'dns' | 'http'>('dns');
    const [enableSSL, setEnableSSL] = useState(true);
    const [redirectToHTTPS, setRedirectToHTTPS] = useState(true);
    const [redirectToWWW, setRedirectToWWW] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);
    const [copiedField, setCopiedField] = useState<string | null>(null);

    // Get services list based on selected type
    const getServiceOptions = () => {
        switch (serviceType) {
            case 'application':
                return applications.map(app => ({ value: app.uuid, label: app.name }));
            case 'database':
                return databases.map(db => ({ value: db.uuid, label: db.name }));
            case 'service':
                return services.map(svc => ({ value: svc.uuid, label: svc.name }));
            default:
                return [];
        }
    };

    const serviceOptions = getServiceOptions();

    // Set first service as default when type changes
    const handleServiceTypeChange = (newType: 'application' | 'database' | 'service') => {
        setServiceType(newType);
        setServiceId('');
    };

    const validateDomain = (value: string): boolean => {
        const domainRegex = /^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/i;
        return domainRegex.test(value);
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();

        // Validate
        const newErrors: Record<string, string> = {};

        if (!domain) {
            newErrors.domain = 'Domain is required';
        } else if (!validateDomain(domain)) {
            newErrors.domain = 'Invalid domain format';
        }

        if (!serviceId) {
            newErrors.serviceId = 'Please select a service';
        }

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        setSubmitting(true);
        router.post('/domains', {
            domain,
            service_type: serviceType,
            service_id: serviceId,
            verification_method: verificationMethod,
            enable_ssl: enableSSL,
            redirect_to_https: redirectToHTTPS,
            redirect_to_www: redirectToWWW,
        }, {
            onError: (errors) => {
                setErrors(errors);
                setSubmitting(false);
            },
            onFinish: () => setSubmitting(false),
        });
    };

    const copyToClipboard = (text: string, field: string) => {
        navigator.clipboard.writeText(text);
        setCopiedField(field);
        setTimeout(() => setCopiedField(null), 2000);
    };

    // Generate example DNS records
    const exampleDNSRecords = domain ? [
        { type: 'A', name: domain, value: '123.45.67.89', description: 'Points your domain to the server IP' },
        { type: 'CNAME', name: `www.${domain}`, value: domain, description: 'Points www subdomain to main domain' },
    ] : [];

    return (
        <AppLayout
            title="Add Domain"
            breadcrumbs={[
                { label: 'Domains', href: '/domains' },
                { label: 'Add Domain' },
            ]}
        >
            {/* Header */}
            <div className="mb-6">
                <Link href="/domains" className="mb-4 inline-flex items-center gap-2 text-sm text-foreground-muted hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" />
                    Back to Domains
                </Link>
                <div className="flex items-center gap-4">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-background-tertiary">
                        <Globe className="h-6 w-6 text-primary" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Add Custom Domain</h1>
                        <p className="text-foreground-muted">Configure a custom domain for your service</p>
                    </div>
                </div>
            </div>

            <form onSubmit={handleSubmit}>
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Main Form */}
                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Domain Configuration</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <Input
                                    label="Domain Name"
                                    placeholder="example.com"
                                    value={domain}
                                    onChange={(e) => {
                                        setDomain(e.target.value.toLowerCase());
                                        if (errors.domain) {
                                            setErrors({ ...errors, domain: '' });
                                        }
                                    }}
                                    error={errors.domain}
                                    hint="Enter your domain without http:// or https://"
                                />

                                <div>
                                    <label className="mb-1.5 block text-sm font-medium text-foreground">
                                        Service Type
                                    </label>
                                    <div className="grid grid-cols-3 gap-2">
                                        {(['application', 'database', 'service'] as const).map((type) => (
                                            <button
                                                key={type}
                                                type="button"
                                                onClick={() => handleServiceTypeChange(type)}
                                                className={`rounded-md border px-4 py-2 text-sm font-medium capitalize transition-colors ${
                                                    serviceType === type
                                                        ? 'border-primary bg-primary/10 text-primary'
                                                        : 'border-border bg-background-secondary text-foreground hover:bg-background-tertiary'
                                                }`}
                                            >
                                                {type}
                                            </button>
                                        ))}
                                    </div>
                                </div>

                                <Select
                                    label="Select Service"
                                    value={serviceId}
                                    onChange={(e) => {
                                        setServiceId(e.target.value);
                                        if (errors.serviceId) {
                                            setErrors({ ...errors, serviceId: '' });
                                        }
                                    }}
                                    options={[
                                        { value: '', label: `Select a ${serviceType}...` },
                                        ...serviceOptions,
                                    ]}
                                    error={errors.serviceId}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Verification Method</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-3 md:grid-cols-2">
                                    <button
                                        type="button"
                                        onClick={() => setVerificationMethod('dns')}
                                        className={`rounded-lg border p-4 text-left transition-colors ${
                                            verificationMethod === 'dns'
                                                ? 'border-primary bg-primary/10'
                                                : 'border-border bg-background-secondary hover:bg-background-tertiary'
                                        }`}
                                    >
                                        <div className="font-medium text-foreground">DNS Verification</div>
                                        <p className="mt-1 text-sm text-foreground-muted">
                                            Add DNS records to verify ownership (recommended)
                                        </p>
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() => setVerificationMethod('http')}
                                        className={`rounded-lg border p-4 text-left transition-colors ${
                                            verificationMethod === 'http'
                                                ? 'border-primary bg-primary/10'
                                                : 'border-border bg-background-secondary hover:bg-background-tertiary'
                                        }`}
                                    >
                                        <div className="font-medium text-foreground">HTTP Verification</div>
                                        <p className="mt-1 text-sm text-foreground-muted">
                                            Verify via HTTP file (requires service running)
                                        </p>
                                    </button>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>SSL & Redirect Options</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <Checkbox
                                    id="enable_ssl"
                                    checked={enableSSL}
                                    onChange={(e) => setEnableSSL(e.target.checked)}
                                    label="Enable automatic SSL certificate"
                                    hint="Automatically provision a free Let's Encrypt SSL certificate"
                                />

                                <Checkbox
                                    id="redirect_https"
                                    checked={redirectToHTTPS}
                                    onChange={(e) => setRedirectToHTTPS(e.target.checked)}
                                    label="Force HTTPS redirect"
                                    hint="Automatically redirect HTTP requests to HTTPS"
                                    disabled={!enableSSL}
                                />

                                <Checkbox
                                    id="redirect_www"
                                    checked={redirectToWWW}
                                    onChange={(e) => setRedirectToWWW(e.target.checked)}
                                    label="Redirect to www"
                                    hint={`Redirect ${domain || 'example.com'} to www.${domain || 'example.com'}`}
                                />
                            </CardContent>
                        </Card>

                        <div className="flex gap-2">
                            <Button type="submit" loading={submitting}>
                                Add Domain
                            </Button>
                            <Link href="/domains">
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </Link>
                        </div>
                    </div>

                    {/* Sidebar - DNS Instructions */}
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Info className="h-5 w-5 text-info" />
                                    DNS Configuration
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="rounded-md bg-info/10 p-3 text-sm text-info">
                                    <p>
                                        After adding your domain, you'll need to update your DNS settings with your domain registrar.
                                    </p>
                                </div>

                                {domain && (
                                    <div className="space-y-3">
                                        <p className="text-sm font-medium text-foreground">Example DNS Records:</p>

                                        {exampleDNSRecords.map((record, index) => (
                                            <div key={index} className="space-y-2 rounded-lg border border-border bg-background p-3">
                                                <div className="flex items-center justify-between">
                                                    <span className="text-xs font-medium text-foreground-muted">
                                                        {record.type} Record
                                                    </span>
                                                </div>

                                                <div className="space-y-1">
                                                    <div className="flex items-center gap-1">
                                                        <code className="flex-1 overflow-auto rounded bg-background-tertiary px-2 py-1 text-xs">
                                                            {record.name}
                                                        </code>
                                                        <button
                                                            type="button"
                                                            onClick={() => copyToClipboard(record.name, `example-name-${index}`)}
                                                            className="rounded p-1 hover:bg-background-tertiary"
                                                        >
                                                            {copiedField === `example-name-${index}` ? (
                                                                <Check className="h-3 w-3 text-primary" />
                                                            ) : (
                                                                <Copy className="h-3 w-3 text-foreground-muted" />
                                                            )}
                                                        </button>
                                                    </div>
                                                    <div className="flex items-center gap-1">
                                                        <code className="flex-1 overflow-auto rounded bg-background-tertiary px-2 py-1 text-xs">
                                                            {record.value}
                                                        </code>
                                                        <button
                                                            type="button"
                                                            onClick={() => copyToClipboard(record.value, `example-value-${index}`)}
                                                            className="rounded p-1 hover:bg-background-tertiary"
                                                        >
                                                            {copiedField === `example-value-${index}` ? (
                                                                <Check className="h-3 w-3 text-primary" />
                                                            ) : (
                                                                <Copy className="h-3 w-3 text-foreground-muted" />
                                                            )}
                                                        </button>
                                                    </div>
                                                </div>

                                                <p className="text-xs text-foreground-muted">
                                                    {record.description}
                                                </p>
                                            </div>
                                        ))}

                                        <div className="rounded-md bg-warning/10 p-3 text-xs text-warning">
                                            <AlertCircle className="mb-1 inline h-3 w-3" /> DNS changes can take up to 24-48 hours to propagate globally.
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Next Steps</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ol className="space-y-2 text-sm text-foreground-muted">
                                    <li className="flex gap-2">
                                        <span className="font-medium text-foreground">1.</span>
                                        Add your domain here
                                    </li>
                                    <li className="flex gap-2">
                                        <span className="font-medium text-foreground">2.</span>
                                        Configure DNS records with your registrar
                                    </li>
                                    <li className="flex gap-2">
                                        <span className="font-medium text-foreground">3.</span>
                                        Wait for DNS propagation (up to 48 hours)
                                    </li>
                                    <li className="flex gap-2">
                                        <span className="font-medium text-foreground">4.</span>
                                        Verify domain ownership
                                    </li>
                                    <li className="flex gap-2">
                                        <span className="font-medium text-foreground">5.</span>
                                        SSL certificate will be auto-generated
                                    </li>
                                </ol>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
