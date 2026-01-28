import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Input } from '@/components/ui';
import { GitBranch, ArrowLeft, Info, ExternalLink } from 'lucide-react';

export default function GitLabCreate() {
    const [name, setName] = useState('');
    const [apiUrl, setApiUrl] = useState('https://gitlab.com/api/v4');
    const [htmlUrl, setHtmlUrl] = useState('https://gitlab.com');
    const [appId, setAppId] = useState('');
    const [appSecret, setAppSecret] = useState('');
    const [groupName, setGroupName] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        router.post('/sources/gitlab', {
            name,
            api_url: apiUrl,
            html_url: htmlUrl,
            app_id: appId ? parseInt(appId, 10) : null,
            app_secret: appSecret || null,
            group_name: groupName || null,
        }, {
            onError: () => setSubmitting(false),
        });
    };

    return (
        <AppLayout
            title="Connect GitLab"
            breadcrumbs={[
                { label: 'Dashboard', href: '/new' },
                { label: 'Sources', href: '/sources' },
                { label: 'GitLab', href: '/sources/gitlab' },
                { label: 'Create' },
            ]}
        >
            <Head title="Connect GitLab" />

            <div className="max-w-2xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Link href="/sources/gitlab">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back
                        </Button>
                    </Link>
                </div>

                <div className="flex items-center gap-4">
                    <div className="h-14 w-14 rounded-xl bg-[#FC6D26] flex items-center justify-center">
                        <GitBranch className="h-7 w-7 text-white" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold">Connect GitLab</h1>
                        <p className="text-foreground-muted">
                            Connect to GitLab.com or a self-hosted GitLab instance
                        </p>
                    </div>
                </div>

                {/* Form */}
                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Connection Settings</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium mb-2">
                                    Name <span className="text-danger">*</span>
                                </label>
                                <Input
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    placeholder="My GitLab"
                                    required
                                />
                                <p className="text-xs text-foreground-muted mt-1">
                                    A display name for this connection
                                </p>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium mb-2">
                                        HTML URL <span className="text-danger">*</span>
                                    </label>
                                    <Input
                                        value={htmlUrl}
                                        onChange={(e) => setHtmlUrl(e.target.value)}
                                        placeholder="https://gitlab.com"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-2">
                                        API URL <span className="text-danger">*</span>
                                    </label>
                                    <Input
                                        value={apiUrl}
                                        onChange={(e) => setApiUrl(e.target.value)}
                                        placeholder="https://gitlab.com/api/v4"
                                        required
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-medium mb-2">
                                    Group Name
                                </label>
                                <Input
                                    value={groupName}
                                    onChange={(e) => setGroupName(e.target.value)}
                                    placeholder="my-organization"
                                />
                                <p className="text-xs text-foreground-muted mt-1">
                                    Optional: restrict to a specific GitLab group
                                </p>
                            </div>

                            <hr className="border-border" />

                            <div className="bg-background-secondary p-4 rounded-lg">
                                <h4 className="font-medium text-sm mb-3">OAuth Application (optional)</h4>
                                <p className="text-xs text-foreground-muted mb-4">
                                    Create an OAuth application in your GitLab settings to enable private repository access.
                                </p>

                                <div className="space-y-3">
                                    <div>
                                        <label className="block text-sm font-medium mb-2">
                                            Application ID
                                        </label>
                                        <Input
                                            value={appId}
                                            onChange={(e) => setAppId(e.target.value)}
                                            placeholder="123456"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-sm font-medium mb-2">
                                            Application Secret
                                        </label>
                                        <Input
                                            type="password"
                                            value={appSecret}
                                            onChange={(e) => setAppSecret(e.target.value)}
                                            placeholder="••••••••••••••••"
                                        />
                                    </div>
                                </div>
                            </div>

                            <Button type="submit" className="w-full" disabled={submitting}>
                                <GitBranch className="h-4 w-4 mr-2" />
                                {submitting ? 'Creating...' : 'Create GitLab Connection'}
                            </Button>
                        </CardContent>
                    </Card>
                </form>

                {/* Help */}
                <Card className="bg-background-secondary">
                    <CardContent className="p-4 flex items-start gap-3">
                        <Info className="h-5 w-5 text-primary flex-shrink-0 mt-0.5" />
                        <div className="text-sm">
                            <p className="font-medium">Setting up a GitLab OAuth Application</p>
                            <ol className="text-foreground-muted mt-2 space-y-1 list-decimal list-inside">
                                <li>Go to GitLab Settings &gt; Applications</li>
                                <li>Create a new application with &quot;api&quot; scope</li>
                                <li>Set the redirect URI to your Saturn Platform URL</li>
                                <li>Copy the Application ID and Secret into the form above</li>
                            </ol>
                            <a
                                href="https://docs.gitlab.com/ee/integration/oauth_provider.html"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1 text-primary hover:underline mt-2"
                            >
                                GitLab OAuth Documentation
                                <ExternalLink className="h-3 w-3" />
                            </a>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
