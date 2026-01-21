import { useState, FormEvent } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Textarea, Select } from '@/components/ui';
import { Shield, ArrowLeft, Info, AlertCircle, CheckCircle, Upload } from 'lucide-react';

// Mock domains for the dropdown
const mockDomains = [
    { id: 1, domain: 'example.com' },
    { id: 2, domain: 'api.example.com' },
    { id: 3, domain: 'app.example.com' },
    { id: 4, domain: 'staging.example.com' },
];

export default function SSLUpload() {
    const [certificate, setCertificate] = useState('');
    const [privateKey, setPrivateKey] = useState('');
    const [certificateChain, setCertificateChain] = useState('');
    const [domainId, setDomainId] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [validationErrors, setValidationErrors] = useState<string[]>([]);
    const [validationSuccess, setValidationSuccess] = useState<string[]>([]);
    const [submitting, setSubmitting] = useState(false);

    const validateCertificate = () => {
        const newErrors: string[] = [];
        const success: string[] = [];

        // Basic PEM format validation
        if (certificate && !certificate.includes('-----BEGIN CERTIFICATE-----')) {
            newErrors.push('Certificate must be in PEM format');
        } else if (certificate) {
            success.push('Certificate format is valid');
        }

        if (privateKey && !privateKey.includes('-----BEGIN PRIVATE KEY-----') && !privateKey.includes('-----BEGIN RSA PRIVATE KEY-----')) {
            newErrors.push('Private key must be in PEM format');
        } else if (privateKey) {
            success.push('Private key format is valid');
        }

        if (certificateChain && !certificateChain.includes('-----BEGIN CERTIFICATE-----')) {
            newErrors.push('Certificate chain must be in PEM format');
        } else if (certificateChain) {
            success.push('Certificate chain format is valid');
        }

        setValidationErrors(newErrors);
        setValidationSuccess(success);
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();

        // Validate
        const newErrors: Record<string, string> = {};

        if (!certificate.trim()) {
            newErrors.certificate = 'Certificate is required';
        } else if (!certificate.includes('-----BEGIN CERTIFICATE-----')) {
            newErrors.certificate = 'Invalid certificate format (must be PEM)';
        }

        if (!privateKey.trim()) {
            newErrors.privateKey = 'Private key is required';
        } else if (!privateKey.includes('-----BEGIN PRIVATE KEY-----') && !privateKey.includes('-----BEGIN RSA PRIVATE KEY-----')) {
            newErrors.privateKey = 'Invalid private key format (must be PEM)';
        }

        if (!domainId) {
            newErrors.domainId = 'Please select a domain';
        }

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        setSubmitting(true);
        router.post('/ssl/upload', {
            certificate,
            private_key: privateKey,
            certificate_chain: certificateChain || null,
            domain_id: domainId,
        }, {
            onError: (errors) => {
                setErrors(errors);
                setSubmitting(false);
            },
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <AppLayout
            title="Upload SSL Certificate"
            breadcrumbs={[
                { label: 'SSL Certificates', href: '/ssl' },
                { label: 'Upload' },
            ]}
        >
            {/* Header */}
            <div className="mb-6">
                <Link href="/ssl" className="mb-4 inline-flex items-center gap-2 text-sm text-foreground-muted hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" />
                    Back to SSL Certificates
                </Link>
                <div className="flex items-center gap-4">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-background-tertiary">
                        <Shield className="h-6 w-6 text-primary" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Upload Custom SSL Certificate</h1>
                        <p className="text-foreground-muted">Import your own SSL/TLS certificate</p>
                    </div>
                </div>
            </div>

            <form onSubmit={handleSubmit}>
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Main Form */}
                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Domain Association</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Select
                                    label="Select Domain"
                                    value={domainId}
                                    onChange={(e) => {
                                        setDomainId(e.target.value);
                                        if (errors.domainId) {
                                            setErrors({ ...errors, domainId: '' });
                                        }
                                    }}
                                    options={[
                                        { value: '', label: 'Choose a domain...' },
                                        ...mockDomains.map(d => ({
                                            value: d.id.toString(),
                                            label: d.domain
                                        }))
                                    ]}
                                    error={errors.domainId}
                                    hint="The domain this certificate will secure"
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Certificate Details</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <label className="mb-1.5 block text-sm font-medium text-foreground">
                                        Certificate (PEM Format) *
                                    </label>
                                    <Textarea
                                        value={certificate}
                                        onChange={(e) => {
                                            setCertificate(e.target.value);
                                            if (errors.certificate) {
                                                setErrors({ ...errors, certificate: '' });
                                            }
                                        }}
                                        placeholder="-----BEGIN CERTIFICATE-----&#10;MIIDXTCCAkWgAwIBAgIJAKL...&#10;-----END CERTIFICATE-----"
                                        rows={8}
                                        className="font-mono text-xs"
                                    />
                                    {errors.certificate && (
                                        <p className="mt-1 text-sm text-danger">{errors.certificate}</p>
                                    )}
                                    <p className="mt-1 text-sm text-foreground-muted">
                                        Your public SSL certificate in PEM format
                                    </p>
                                </div>

                                <div>
                                    <label className="mb-1.5 block text-sm font-medium text-foreground">
                                        Private Key (PEM Format) *
                                    </label>
                                    <Textarea
                                        value={privateKey}
                                        onChange={(e) => {
                                            setPrivateKey(e.target.value);
                                            if (errors.privateKey) {
                                                setErrors({ ...errors, privateKey: '' });
                                            }
                                        }}
                                        placeholder="-----BEGIN PRIVATE KEY-----&#10;MIIEvQIBADANBgkqhkiG9w...&#10;-----END PRIVATE KEY-----"
                                        rows={8}
                                        className="font-mono text-xs"
                                    />
                                    {errors.privateKey && (
                                        <p className="mt-1 text-sm text-danger">{errors.privateKey}</p>
                                    )}
                                    <p className="mt-1 text-sm text-foreground-muted">
                                        The private key for your certificate (kept secure and encrypted)
                                    </p>
                                </div>

                                <div>
                                    <label className="mb-1.5 block text-sm font-medium text-foreground">
                                        Certificate Chain (Optional)
                                    </label>
                                    <Textarea
                                        value={certificateChain}
                                        onChange={(e) => setCertificateChain(e.target.value)}
                                        placeholder="-----BEGIN CERTIFICATE-----&#10;MIIEkjCCA3qgAwIBAgIQCg...&#10;-----END CERTIFICATE-----"
                                        rows={6}
                                        className="font-mono text-xs"
                                    />
                                    <p className="mt-1 text-sm text-foreground-muted">
                                        Intermediate certificates (CA bundle) if required by your provider
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Validation Feedback */}
                        {(validationErrors.length > 0 || validationSuccess.length > 0) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Validation Feedback</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    {validationSuccess.map((msg, idx) => (
                                        <div key={idx} className="flex items-center gap-2 text-sm text-primary">
                                            <CheckCircle className="h-4 w-4" />
                                            <span>{msg}</span>
                                        </div>
                                    ))}
                                    {validationErrors.map((msg, idx) => (
                                        <div key={idx} className="flex items-center gap-2 text-sm text-danger">
                                            <AlertCircle className="h-4 w-4" />
                                            <span>{msg}</span>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        )}

                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={validateCertificate}
                                disabled={!certificate && !privateKey}
                            >
                                Validate Format
                            </Button>
                            <Button type="submit" loading={submitting}>
                                <Upload className="mr-2 h-4 w-4" />
                                Upload Certificate
                            </Button>
                            <Link href="/ssl">
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </Link>
                        </div>
                    </div>

                    {/* Sidebar - Info */}
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Info className="h-5 w-5 text-info" />
                                    About Custom Certificates
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-3 text-sm text-foreground-muted">
                                    <p>
                                        Upload your own SSL/TLS certificate instead of using Let's Encrypt auto-generated certificates.
                                    </p>
                                    <div className="rounded-lg border border-border bg-background p-3">
                                        <h4 className="mb-2 font-medium text-foreground">When to use:</h4>
                                        <ul className="space-y-1 text-xs">
                                            <li className="flex items-start gap-2">
                                                <span className="text-foreground">•</span>
                                                <span>Extended Validation (EV) certificates</span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <span className="text-foreground">•</span>
                                                <span>Organization Validated (OV) certificates</span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <span className="text-foreground">•</span>
                                                <span>Wildcard certificates for multiple subdomains</span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <span className="text-foreground">•</span>
                                                <span>Certificates from specific CAs required by compliance</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>PEM Format Guide</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3 text-sm">
                                    <p className="text-foreground-muted">
                                        PEM files are Base64 encoded and contain header/footer lines:
                                    </p>
                                    <div className="rounded-lg border border-border bg-background p-3">
                                        <p className="mb-2 text-xs font-medium text-foreground">Certificate:</p>
                                        <code className="block text-xs text-foreground-muted">
                                            -----BEGIN CERTIFICATE-----<br />
                                            MIIDXTCCAkWgAwIBAgI...<br />
                                            -----END CERTIFICATE-----
                                        </code>
                                    </div>
                                    <div className="rounded-lg border border-border bg-background p-3">
                                        <p className="mb-2 text-xs font-medium text-foreground">Private Key:</p>
                                        <code className="block text-xs text-foreground-muted">
                                            -----BEGIN PRIVATE KEY-----<br />
                                            MIIEvQIBADANBgkqhki...<br />
                                            -----END PRIVATE KEY-----
                                        </code>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-warning">
                                    <AlertCircle className="h-5 w-5" />
                                    Security Notice
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul className="space-y-2 text-sm text-foreground-muted">
                                    <li className="flex gap-2">
                                        <span className="text-foreground">•</span>
                                        Private keys are encrypted at rest
                                    </li>
                                    <li className="flex gap-2">
                                        <span className="text-foreground">•</span>
                                        Never share your private key
                                    </li>
                                    <li className="flex gap-2">
                                        <span className="text-foreground">•</span>
                                        Certificates must be valid and not expired
                                    </li>
                                    <li className="flex gap-2">
                                        <span className="text-foreground">•</span>
                                        Domain must match certificate CN/SAN
                                    </li>
                                    <li className="flex gap-2">
                                        <span className="text-foreground">•</span>
                                        You're responsible for renewal
                                    </li>
                                </ul>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Common Issues</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3 text-sm">
                                    <div className="rounded-lg border border-border bg-background p-3">
                                        <h4 className="mb-1 font-medium text-foreground">Format Error</h4>
                                        <p className="text-xs text-foreground-muted">
                                            Ensure files are in PEM format, not DER or P12
                                        </p>
                                    </div>
                                    <div className="rounded-lg border border-border bg-background p-3">
                                        <h4 className="mb-1 font-medium text-foreground">Key Mismatch</h4>
                                        <p className="text-xs text-foreground-muted">
                                            Certificate and private key must be a matching pair
                                        </p>
                                    </div>
                                    <div className="rounded-lg border border-border bg-background p-3">
                                        <h4 className="mb-1 font-medium text-foreground">Chain Order</h4>
                                        <p className="text-xs text-foreground-muted">
                                            Chain should be ordered: intermediate, then root CA
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
