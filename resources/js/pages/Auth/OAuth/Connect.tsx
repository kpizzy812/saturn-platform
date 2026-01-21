import { useForm } from '@inertiajs/react';
import { AuthLayout } from '@/components/layout';
import { Button, Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter, Badge } from '@/components/ui';
import { Github, Mail, GitBranch, CheckCircle2, XCircle } from 'lucide-react';

interface OAuthProvider {
    name: string;
    provider: string;
    icon: React.ComponentType<{ className?: string }>;
    connected: boolean;
    lastSynced?: string;
    email?: string;
}

interface Props {
    providers: OAuthProvider[];
}

export default function Connect({ providers: initialProviders }: Props) {
    const defaultProviders: OAuthProvider[] = [
        {
            name: 'GitHub',
            provider: 'github',
            icon: Github,
            connected: false,
        },
        {
            name: 'Google',
            provider: 'google',
            icon: Mail,
            connected: false,
        },
        {
            name: 'GitLab',
            provider: 'gitlab',
            icon: GitBranch,
            connected: false,
        },
    ];

    // Merge with provided data
    const providers = defaultProviders.map((defaultProvider) => {
        const providedData = initialProviders?.find(
            (p) => p.provider === defaultProvider.provider
        );
        return providedData ? { ...defaultProvider, ...providedData } : defaultProvider;
    });

    const { post, processing } = useForm();

    const handleConnect = (provider: string) => {
        window.location.href = `/auth/${provider}/redirect`;
    };

    const handleDisconnect = (provider: string) => {
        if (confirm('Are you sure you want to disconnect this provider?')) {
            post(`/auth/${provider}/disconnect`);
        }
    };

    const formatDate = (dateString?: string) => {
        if (!dateString) return null;
        const date = new Date(dateString);
        return new Intl.DateTimeFormat('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
    };

    return (
        <AuthLayout
            title="Connect Accounts"
            subtitle="Link your accounts to enable seamless deployments and collaboration."
        >
            <div className="space-y-4">
                {providers.map((provider) => {
                    const Icon = provider.icon;
                    const isConnected = provider.connected;

                    return (
                        <Card key={provider.provider} className="transition-all hover:border-primary/50">
                            <CardContent className="p-4">
                                <div className="flex items-start gap-4">
                                    {/* Icon */}
                                    <div className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-lg bg-background">
                                        <Icon className="h-6 w-6 text-foreground" />
                                    </div>

                                    {/* Content */}
                                    <div className="flex-1 space-y-1">
                                        <div className="flex items-center gap-2">
                                            <h3 className="font-semibold text-foreground">
                                                {provider.name}
                                            </h3>
                                            {isConnected ? (
                                                <Badge variant="success" className="flex items-center gap-1">
                                                    <CheckCircle2 className="h-3 w-3" />
                                                    Connected
                                                </Badge>
                                            ) : (
                                                <Badge variant="default">Not connected</Badge>
                                            )}
                                        </div>

                                        {isConnected && provider.email && (
                                            <p className="text-sm text-foreground-muted">
                                                {provider.email}
                                            </p>
                                        )}

                                        {isConnected && provider.lastSynced && (
                                            <p className="text-xs text-foreground-subtle">
                                                Last synced: {formatDate(provider.lastSynced)}
                                            </p>
                                        )}

                                        {!isConnected && (
                                            <p className="text-sm text-foreground-muted">
                                                Connect your {provider.name} account to enable deployments
                                                {provider.provider === 'github' && ' from GitHub repositories'}
                                                {provider.provider === 'gitlab' && ' from GitLab repositories'}
                                                {provider.provider === 'google' && ' and Google Cloud integrations'}
                                            </p>
                                        )}
                                    </div>

                                    {/* Action Button */}
                                    <div className="flex-shrink-0">
                                        {isConnected ? (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleDisconnect(provider.provider)}
                                                disabled={processing}
                                            >
                                                <XCircle className="mr-2 h-4 w-4" />
                                                Disconnect
                                            </Button>
                                        ) : (
                                            <Button
                                                type="button"
                                                size="sm"
                                                onClick={() => handleConnect(provider.provider)}
                                                disabled={processing}
                                            >
                                                Connect
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    );
                })}

                {/* Info Card */}
                <Card className="border-blue-500/20 bg-blue-500/10">
                    <CardContent className="p-4">
                        <p className="text-sm text-blue-600 dark:text-blue-400">
                            <strong>Why connect accounts?</strong> Linking your Git providers allows you
                            to deploy directly from your repositories, receive webhooks for automatic
                            deployments, and manage your infrastructure seamlessly.
                        </p>
                    </CardContent>
                </Card>

                {/* Actions */}
                <div className="flex justify-end gap-3 pt-4">
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={() => window.history.back()}
                    >
                        Back
                    </Button>
                    <Button
                        type="button"
                        onClick={() => (window.location.href = '/dashboard')}
                    >
                        Continue to Dashboard
                    </Button>
                </div>
            </div>
        </AuthLayout>
    );
}
