import { useRef, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button } from '@/components/ui';
import {
    Github, ArrowLeft, CheckCircle2, ExternalLink,
    AlertCircle, Info, Loader2, Shield, Zap
} from 'lucide-react';

interface Props {
    webhookUrl: string;
}

export default function GitHubCreate({ webhookUrl }: Props) {
    const [isPublic, setIsPublic] = useState(false);
    const [creating, setCreating] = useState(false);
    const formRef = useRef<HTMLFormElement>(null);

    const appName = 'saturn-' + Math.random().toString(36).substring(2, 8);

    const handleCreateApp = async () => {
        setCreating(true);

        try {
            // Create a placeholder GithubApp record to get a UUID for the state parameter
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
            const response = await fetch('/sources/github', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ is_public: isPublic, name: appName }),
            });

            if (!response.ok) {
                throw new Error('Failed to create GitHub App record');
            }

            const data = await response.json();

            // Build manifest with the UUID in setup_url so install callback can find the app
            const manifest = JSON.stringify({
                name: appName,
                url: window.location.origin,
                hook_attributes: {
                    url: webhookUrl,
                    active: true,
                },
                redirect_url: `${window.location.origin}/webhooks/source/github/redirect`,
                callback_urls: [`${window.location.origin}/webhooks/source/github/redirect`],
                setup_url: `${window.location.origin}/webhooks/source/github/install?source=${data.uuid}`,
                setup_on_update: true,
                public: isPublic,
                default_permissions: {
                    contents: 'write',
                    metadata: 'read',
                    pull_requests: 'write',
                    administration: 'read',
                },
                default_events: ['push', 'pull_request'],
            });

            // Submit the manifest form to GitHub with the uuid as state
            if (formRef.current) {
                const manifestInput = formRef.current.querySelector<HTMLInputElement>('input[name="manifest"]');
                const stateInput = formRef.current.querySelector<HTMLInputElement>('input[name="state"]');
                if (manifestInput) {
                    manifestInput.value = manifest;
                }
                if (stateInput) {
                    stateInput.value = data.uuid;
                }
                formRef.current.submit();
                // Reset button state since GitHub opens in a new tab
                setTimeout(() => setCreating(false), 1000);
            }
        } catch {
            setCreating(false);
        }
    };

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

            {/* Hidden form for GitHub App Manifest submission */}
            <form ref={formRef} method="post" action="https://github.com/settings/apps/new" target="_blank" className="hidden">
                <input type="hidden" name="manifest" value="" />
                <input type="hidden" name="state" value="" />
            </form>

            <div className="max-w-3xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
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
                            Automatic setup via GitHub App Manifest
                        </p>
                    </div>
                </div>

                {/* How it works */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base flex items-center gap-2">
                            <Zap className="h-5 w-5 text-primary" />
                            How It Works
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div className="flex items-start gap-3 p-3 rounded-lg bg-background-secondary">
                                    <div className="w-7 h-7 rounded-full bg-primary text-primary-foreground flex items-center justify-center text-sm font-semibold flex-shrink-0">
                                        1
                                    </div>
                                    <div>
                                        <p className="font-medium text-sm">Click the button</p>
                                        <p className="text-xs text-foreground-muted mt-1">
                                            You'll be redirected to GitHub
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-start gap-3 p-3 rounded-lg bg-background-secondary">
                                    <div className="w-7 h-7 rounded-full bg-primary text-primary-foreground flex items-center justify-center text-sm font-semibold flex-shrink-0">
                                        2
                                    </div>
                                    <div>
                                        <p className="font-medium text-sm">Confirm on GitHub</p>
                                        <p className="text-xs text-foreground-muted mt-1">
                                            Review and create the app
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-start gap-3 p-3 rounded-lg bg-background-secondary">
                                    <div className="w-7 h-7 rounded-full bg-primary text-primary-foreground flex items-center justify-center text-sm font-semibold flex-shrink-0">
                                        3
                                    </div>
                                    <div>
                                        <p className="font-medium text-sm">Done!</p>
                                        <p className="text-xs text-foreground-muted mt-1">
                                            Credentials are set up automatically
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Configuration that will be applied */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">What will be configured</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="bg-background-secondary p-4 rounded-lg space-y-3">
                                <h4 className="font-medium text-sm">Repository Permissions</h4>
                                <ul className="text-sm text-foreground-muted space-y-2">
                                    <li className="flex items-center gap-2">
                                        <CheckCircle2 className="h-4 w-4 text-success flex-shrink-0" />
                                        <span><strong>Contents:</strong> Read & Write</span>
                                    </li>
                                    <li className="flex items-center gap-2">
                                        <CheckCircle2 className="h-4 w-4 text-success flex-shrink-0" />
                                        <span><strong>Metadata:</strong> Read-only</span>
                                    </li>
                                    <li className="flex items-center gap-2">
                                        <CheckCircle2 className="h-4 w-4 text-success flex-shrink-0" />
                                        <span><strong>Pull Requests:</strong> Read & Write</span>
                                    </li>
                                    <li className="flex items-center gap-2">
                                        <CheckCircle2 className="h-4 w-4 text-success flex-shrink-0" />
                                        <span><strong>Administration:</strong> Read-only</span>
                                    </li>
                                </ul>
                            </div>
                            <div className="bg-background-secondary p-4 rounded-lg space-y-3">
                                <h4 className="font-medium text-sm">Webhook Events</h4>
                                <ul className="text-sm text-foreground-muted space-y-2">
                                    <li className="flex items-center gap-2">
                                        <CheckCircle2 className="h-4 w-4 text-success flex-shrink-0" />
                                        Push events
                                    </li>
                                    <li className="flex items-center gap-2">
                                        <CheckCircle2 className="h-4 w-4 text-success flex-shrink-0" />
                                        Pull Request events
                                    </li>
                                </ul>
                                <h4 className="font-medium text-sm mt-4">URLs</h4>
                                <ul className="text-sm text-foreground-muted space-y-2">
                                    <li className="flex items-center gap-2">
                                        <CheckCircle2 className="h-4 w-4 text-success flex-shrink-0" />
                                        <span>Webhook: <code className="text-xs bg-background px-1.5 py-0.5 rounded">{webhookUrl}</code></span>
                                    </li>
                                    <li className="flex items-center gap-2">
                                        <CheckCircle2 className="h-4 w-4 text-success flex-shrink-0" />
                                        <span>Callback: <code className="text-xs bg-background px-1.5 py-0.5 rounded">{window.location.origin}/webhooks/source/github/redirect</code></span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        {/* Visibility toggle */}
                        <div className="border border-border rounded-lg p-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <Shield className="h-5 w-5 text-foreground-muted" />
                                    <div>
                                        <p className="font-medium text-sm">App Visibility</p>
                                        <p className="text-xs text-foreground-muted mt-0.5">
                                            {isPublic
                                                ? 'Anyone can install this GitHub App'
                                                : 'Only you can install this GitHub App'}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        variant={!isPublic ? 'default' : 'ghost'}
                                        size="sm"
                                        onClick={() => setIsPublic(false)}
                                    >
                                        Private
                                    </Button>
                                    <Button
                                        variant={isPublic ? 'default' : 'ghost'}
                                        size="sm"
                                        onClick={() => setIsPublic(true)}
                                    >
                                        Public
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Create Button */}
                <Card className="border-primary/30 bg-primary/5">
                    <CardContent className="p-6">
                        <div className="text-center space-y-4">
                            <p className="text-sm text-foreground-muted">
                                Click the button below to create a GitHub App automatically.
                                You'll be redirected to GitHub to confirm, then back to Saturn Platform.
                            </p>
                            <Button
                                onClick={handleCreateApp}
                                disabled={creating}
                                size="lg"
                                className="w-full max-w-md"
                            >
                                {creating ? (
                                    <>
                                        <Loader2 className="h-5 w-5 mr-2 animate-spin" />
                                        Redirecting to GitHub...
                                    </>
                                ) : (
                                    <>
                                        <Github className="h-5 w-5 mr-2" />
                                        Create GitHub App
                                        <ExternalLink className="h-4 w-4 ml-2" />
                                    </>
                                )}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* After installation note */}
                <Card className="bg-background-secondary">
                    <CardContent className="p-4 flex items-start gap-3">
                        <AlertCircle className="h-5 w-5 text-warning flex-shrink-0 mt-0.5" />
                        <div className="text-sm">
                            <p className="font-medium">After Creating the App</p>
                            <p className="text-foreground-muted mt-1">
                                After the app is created, you'll need to <strong>install it</strong> on your GitHub account or organization.
                                GitHub will prompt you to select which repositories the app can access.
                                You can change repository access later in your GitHub App settings.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                {/* Help */}
                <Card className="bg-background-secondary">
                    <CardContent className="p-4 flex items-start gap-3">
                        <Info className="h-5 w-5 text-primary flex-shrink-0 mt-0.5" />
                        <div className="text-sm">
                            <p className="font-medium">Need Help?</p>
                            <p className="text-foreground-muted mt-1">
                                Check out the{' '}
                                <a
                                    href="https://docs.github.com/en/apps/creating-github-apps/registering-a-github-app/registering-a-github-app"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-primary hover:underline"
                                >
                                    GitHub App documentation
                                </a>{' '}
                                for detailed information about GitHub Apps and permissions.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
