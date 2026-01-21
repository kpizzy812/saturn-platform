import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Input, Badge } from '@/components/ui';
import {
    Github, ArrowLeft, Copy, CheckCircle2, ExternalLink,
    AlertCircle, Info, ChevronRight
} from 'lucide-react';

interface Props {
    webhookUrl: string;
    callbackUrl: string;
}

export default function GitHubCreate({ webhookUrl, callbackUrl }: Props) {
    const [step, setStep] = useState(1);
    const [appName, setAppName] = useState('');
    const [appId, setAppId] = useState('');
    const [clientId, setClientId] = useState('');
    const [clientSecret, setClientSecret] = useState('');
    const [webhookSecret, setWebhookSecret] = useState('');
    const [privateKey, setPrivateKey] = useState('');
    const [copied, setCopied] = useState<string | null>(null);

    const copyToClipboard = (text: string, key: string) => {
        navigator.clipboard.writeText(text);
        setCopied(key);
        setTimeout(() => setCopied(null), 2000);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/sources/github', {
            name: appName,
            app_id: appId,
            client_id: clientId,
            client_secret: clientSecret,
            webhook_secret: webhookSecret,
            private_key: privateKey,
        });
    };

    const steps = [
        { number: 1, title: 'Create GitHub App', description: 'Register a new GitHub App' },
        { number: 2, title: 'Configure Settings', description: 'Set permissions and webhooks' },
        { number: 3, title: 'Install App', description: 'Install on your account' },
        { number: 4, title: 'Complete Setup', description: 'Enter credentials in Saturn Platform' },
    ];

    return (
        <AppLayout
            title="Create GitHub App"
            breadcrumbs={[
                { label: 'Dashboard', href: '/new' },
                { label: 'Sources', href: '/sources' },
                { label: 'GitHub', href: '/sources/github' },
                { label: 'Create' },
            ]}
        >
            <Head title="Create GitHub App" />

            <div className="max-w-4xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4 mb-6">
                    <Link href="/sources/github">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back
                        </Button>
                    </Link>
                </div>

                <div className="flex items-center gap-4">
                    <div className="h-14 w-14 rounded-xl bg-foreground flex items-center justify-center">
                        <Github className="h-7 w-7 text-background" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold">Create GitHub App</h1>
                        <p className="text-foreground-muted">
                            Connect Saturn Platform to your GitHub repositories
                        </p>
                    </div>
                </div>

                {/* Progress Steps */}
                <Card>
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between">
                            {steps.map((s, index) => (
                                <div key={s.number} className="flex items-center flex-1">
                                    <div className={`flex flex-col items-center flex-1 ${
                                        step >= s.number ? 'text-primary' : 'text-foreground-muted'
                                    }`}>
                                        <div className={`w-10 h-10 rounded-full flex items-center justify-center font-semibold ${
                                            step >= s.number
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-background-secondary'
                                        }`}>
                                            {step > s.number ? (
                                                <CheckCircle2 className="h-5 w-5" />
                                            ) : (
                                                s.number
                                            )}
                                        </div>
                                        <p className="text-xs font-medium mt-2">{s.title}</p>
                                    </div>
                                    {index < steps.length - 1 && (
                                        <div className={`h-0.5 flex-1 mx-2 ${
                                            step > s.number ? 'bg-primary' : 'bg-border'
                                        }`} />
                                    )}
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Step 1: Create GitHub App */}
                {step === 1 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Step 1: Create GitHub App</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-3">
                                <p className="text-sm text-foreground-muted">
                                    Go to GitHub to create a new GitHub App for Saturn Platform:
                                </p>
                                <a
                                    href="https://github.com/settings/apps/new"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="block"
                                >
                                    <Button className="w-full">
                                        <Github className="h-4 w-4 mr-2" />
                                        Create GitHub App
                                        <ExternalLink className="h-4 w-4 ml-2" />
                                    </Button>
                                </a>
                            </div>

                            <div className="bg-background-secondary p-4 rounded-lg space-y-3">
                                <h4 className="font-medium text-sm">Basic Information</h4>
                                <ul className="text-sm text-foreground-muted space-y-2 list-disc list-inside">
                                    <li>GitHub App name: Choose a unique name (e.g., "Saturn Platform - Your Team")</li>
                                    <li>Homepage URL: Your Saturn Platform instance URL</li>
                                    <li>Description: "Saturn Platform integration for automatic deployments"</li>
                                </ul>
                            </div>

                            <div className="bg-background-secondary p-4 rounded-lg space-y-3">
                                <h4 className="font-medium text-sm">Callback URL</h4>
                                <div className="flex items-center gap-2">
                                    <code className="flex-1 text-xs bg-background px-3 py-2 rounded font-mono">
                                        {callbackUrl}
                                    </code>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => copyToClipboard(callbackUrl, 'callback')}
                                    >
                                        {copied === 'callback' ? (
                                            <CheckCircle2 className="h-4 w-4 text-success" />
                                        ) : (
                                            <Copy className="h-4 w-4" />
                                        )}
                                    </Button>
                                </div>
                            </div>

                            <div className="bg-background-secondary p-4 rounded-lg space-y-3">
                                <h4 className="font-medium text-sm">Webhook URL</h4>
                                <div className="flex items-center gap-2">
                                    <code className="flex-1 text-xs bg-background px-3 py-2 rounded font-mono">
                                        {webhookUrl}
                                    </code>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => copyToClipboard(webhookUrl, 'webhook')}
                                    >
                                        {copied === 'webhook' ? (
                                            <CheckCircle2 className="h-4 w-4 text-success" />
                                        ) : (
                                            <Copy className="h-4 w-4" />
                                        )}
                                    </Button>
                                </div>
                                <p className="text-xs text-foreground-muted">
                                    Enable "Active" and select "application/json" as content type
                                </p>
                            </div>

                            <Button onClick={() => setStep(2)} className="w-full">
                                Continue to Permissions
                                <ChevronRight className="h-4 w-4 ml-2" />
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {/* Step 2: Configure Permissions */}
                {step === 2 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Step 2: Configure Permissions</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="bg-background-secondary p-4 rounded-lg space-y-3">
                                <h4 className="font-medium text-sm">Repository Permissions</h4>
                                <ul className="text-sm text-foreground-muted space-y-2">
                                    <li className="flex items-center gap-2">
                                        <CheckCircle2 className="h-4 w-4 text-success" />
                                        <span><strong>Contents:</strong> Read & Write</span>
                                    </li>
                                    <li className="flex items-center gap-2">
                                        <CheckCircle2 className="h-4 w-4 text-success" />
                                        <span><strong>Metadata:</strong> Read-only</span>
                                    </li>
                                    <li className="flex items-center gap-2">
                                        <CheckCircle2 className="h-4 w-4 text-success" />
                                        <span><strong>Pull requests:</strong> Read & Write</span>
                                    </li>
                                    <li className="flex items-center gap-2">
                                        <CheckCircle2 className="h-4 w-4 text-success" />
                                        <span><strong>Webhooks:</strong> Read & Write</span>
                                    </li>
                                </ul>
                            </div>

                            <div className="bg-background-secondary p-4 rounded-lg space-y-3">
                                <h4 className="font-medium text-sm">Subscribe to Events</h4>
                                <ul className="text-sm text-foreground-muted space-y-2">
                                    <li className="flex items-center gap-2">
                                        <CheckCircle2 className="h-4 w-4 text-success" />
                                        Push
                                    </li>
                                    <li className="flex items-center gap-2">
                                        <CheckCircle2 className="h-4 w-4 text-success" />
                                        Pull request
                                    </li>
                                    <li className="flex items-center gap-2">
                                        <CheckCircle2 className="h-4 w-4 text-success" />
                                        Repository
                                    </li>
                                </ul>
                            </div>

                            <div className="bg-info/5 border border-info/20 p-4 rounded-lg flex items-start gap-3">
                                <Info className="h-5 w-5 text-info flex-shrink-0 mt-0.5" />
                                <p className="text-sm text-foreground-muted">
                                    Make sure "Where can this GitHub App be installed?" is set to
                                    <strong> "Any account"</strong> or the appropriate option for your use case.
                                </p>
                            </div>

                            <div className="flex gap-2">
                                <Button variant="ghost" onClick={() => setStep(1)} className="flex-1">
                                    Back
                                </Button>
                                <Button onClick={() => setStep(3)} className="flex-1">
                                    Continue to Installation
                                    <ChevronRight className="h-4 w-4 ml-2" />
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Step 3: Install App */}
                {step === 3 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Step 3: Install GitHub App</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-3">
                                <p className="text-sm text-foreground-muted">
                                    After creating the GitHub App, install it on your account or organization:
                                </p>

                                <div className="bg-background-secondary p-4 rounded-lg space-y-3">
                                    <h4 className="font-medium text-sm">Installation Steps</h4>
                                    <ol className="text-sm text-foreground-muted space-y-2 list-decimal list-inside">
                                        <li>Click "Create GitHub App" button</li>
                                        <li>You'll be redirected to the app settings page</li>
                                        <li>Click "Install App" in the left sidebar</li>
                                        <li>Select your account or organization</li>
                                        <li>Choose "All repositories" or select specific ones</li>
                                        <li>Click "Install"</li>
                                    </ol>
                                </div>

                                <div className="bg-warning/5 border border-warning/20 p-4 rounded-lg flex items-start gap-3">
                                    <AlertCircle className="h-5 w-5 text-warning flex-shrink-0 mt-0.5" />
                                    <div className="text-sm">
                                        <p className="font-medium">Important</p>
                                        <p className="text-foreground-muted mt-1">
                                            You can add or remove repositories later from the GitHub App installation settings.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="flex gap-2">
                                <Button variant="ghost" onClick={() => setStep(2)} className="flex-1">
                                    Back
                                </Button>
                                <Button onClick={() => setStep(4)} className="flex-1">
                                    Continue to Setup
                                    <ChevronRight className="h-4 w-4 ml-2" />
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Step 4: Complete Setup */}
                {step === 4 && (
                    <form onSubmit={handleSubmit}>
                        <Card>
                            <CardHeader>
                                <CardTitle>Step 4: Complete Setup</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p className="text-sm text-foreground-muted mb-4">
                                    Enter the credentials from your GitHub App settings page:
                                </p>

                                <div>
                                    <label className="block text-sm font-medium mb-2">
                                        App Name <span className="text-danger">*</span>
                                    </label>
                                    <Input
                                        value={appName}
                                        onChange={(e) => setAppName(e.target.value)}
                                        placeholder="My GitHub App"
                                        required
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-medium mb-2">
                                        App ID <span className="text-danger">*</span>
                                    </label>
                                    <Input
                                        value={appId}
                                        onChange={(e) => setAppId(e.target.value)}
                                        placeholder="123456"
                                        required
                                    />
                                    <p className="text-xs text-foreground-muted mt-1">
                                        Found at the top of your GitHub App settings page
                                    </p>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium mb-2">
                                        Client ID <span className="text-danger">*</span>
                                    </label>
                                    <Input
                                        value={clientId}
                                        onChange={(e) => setClientId(e.target.value)}
                                        placeholder="Iv1.1234567890abcdef"
                                        required
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-medium mb-2">
                                        Client Secret <span className="text-danger">*</span>
                                    </label>
                                    <Input
                                        type="password"
                                        value={clientSecret}
                                        onChange={(e) => setClientSecret(e.target.value)}
                                        placeholder="••••••••••••••••"
                                        required
                                    />
                                    <p className="text-xs text-foreground-muted mt-1">
                                        Generate a new client secret if needed
                                    </p>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium mb-2">
                                        Webhook Secret <span className="text-danger">*</span>
                                    </label>
                                    <Input
                                        type="password"
                                        value={webhookSecret}
                                        onChange={(e) => setWebhookSecret(e.target.value)}
                                        placeholder="••••••••••••••••"
                                        required
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-medium mb-2">
                                        Private Key <span className="text-danger">*</span>
                                    </label>
                                    <textarea
                                        value={privateKey}
                                        onChange={(e) => setPrivateKey(e.target.value)}
                                        placeholder="-----BEGIN RSA PRIVATE KEY-----&#10;...&#10;-----END RSA PRIVATE KEY-----"
                                        required
                                        className="w-full min-h-32 px-3 py-2 text-sm bg-background border border-border rounded-md focus:outline-none focus:ring-2 focus:ring-primary font-mono"
                                    />
                                    <p className="text-xs text-foreground-muted mt-1">
                                        Generate and download from the "Private keys" section
                                    </p>
                                </div>

                                <div className="flex gap-2 pt-4">
                                    <Button type="button" variant="ghost" onClick={() => setStep(3)} className="flex-1">
                                        Back
                                    </Button>
                                    <Button type="submit" className="flex-1">
                                        <Github className="h-4 w-4 mr-2" />
                                        Complete Setup
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </form>
                )}

                {/* Help */}
                <Card className="bg-background-secondary">
                    <CardContent className="p-4 flex items-start gap-3">
                        <Info className="h-5 w-5 text-primary flex-shrink-0 mt-0.5" />
                        <div className="text-sm">
                            <p className="font-medium">Need Help?</p>
                            <p className="text-foreground-muted mt-1">
                                Check out the{' '}
                                <a
                                    href="https://docs.github.com/en/developers/apps/building-github-apps/creating-a-github-app"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-primary hover:underline"
                                >
                                    GitHub App documentation
                                </a>{' '}
                                for detailed instructions on creating and configuring GitHub Apps.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
