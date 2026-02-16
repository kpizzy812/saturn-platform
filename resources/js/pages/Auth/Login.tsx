import { useForm, Link, usePage } from '@inertiajs/react';
import { AuthLayout } from '@/components/layout';
import { Input, Button, Checkbox } from '@/components/ui';
import { Github, Mail, GitBranch } from 'lucide-react';
import { useState } from 'react';

interface OAuthProvider {
    id: number;
    provider: string;
    enabled: boolean;
}

interface Props {
    canResetPassword: boolean;
    status?: string;
    is_registration_enabled?: boolean;
    enabled_oauth_providers?: OAuthProvider[];
}

const providerConfig: Record<string, { label: string; icon: React.ReactNode }> = {
    github: { label: 'GitHub', icon: <Github className="mr-2 h-4 w-4" /> },
    google: { label: 'Google', icon: <Mail className="mr-2 h-4 w-4" /> },
    gitlab: { label: 'GitLab', icon: <GitBranch className="mr-2 h-4 w-4" /> },
    bitbucket: { label: 'Bitbucket', icon: <GitBranch className="mr-2 h-4 w-4" /> },
    azure: { label: 'Azure AD', icon: <Mail className="mr-2 h-4 w-4" /> },
    discord: { label: 'Discord', icon: <Mail className="mr-2 h-4 w-4" /> },
};

export default function Login({
    canResetPassword,
    status,
    is_registration_enabled = true,
    enabled_oauth_providers = [],
}: Props) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });
    const pageErrors = usePage().props.errors as Record<string, string>;
    const [oauthLoading, setOauthLoading] = useState<string | null>(null);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/login');
    };

    const handleOAuthLogin = (provider: string) => {
        setOauthLoading(provider);
        window.location.href = `/auth/${provider}/redirect`;
    };

    const hasOAuthProviders = enabled_oauth_providers.length > 0;

    return (
        <AuthLayout title="Sign In" subtitle="Welcome back! Sign in to your account.">
            {status && (
                <div className="mb-4 rounded-md bg-primary/10 p-3 text-sm text-primary">
                    {status}
                </div>
            )}

            {pageErrors?.oauth && (
                <div className="mb-4 rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                    {pageErrors.oauth}
                </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-4">
                <Input
                    label="Email"
                    type="email"
                    placeholder="you@example.com"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                    required
                    autoFocus
                />

                <Input
                    label="Password"
                    type="password"
                    placeholder="••••••••"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                    required
                />

                <div className="flex items-center justify-between">
                    <Checkbox
                        label="Remember me"
                        checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                    />
                    {canResetPassword && (
                        <Link
                            href="/forgot-password"
                            className="text-sm text-primary hover:underline"
                        >
                            Forgot password?
                        </Link>
                    )}
                </div>

                <Button type="submit" className="w-full" loading={processing}>
                    Sign In
                </Button>

                {hasOAuthProviders && (
                    <>
                        {/* Divider */}
                        <div className="relative my-6">
                            <div className="absolute inset-0 flex items-center">
                                <div className="w-full border-t border-border" />
                            </div>
                            <div className="relative flex justify-center text-xs uppercase">
                                <span className="bg-background-secondary px-2 text-foreground-muted">
                                    Or continue with
                                </span>
                            </div>
                        </div>

                        {/* OAuth Buttons */}
                        <div className={`grid gap-3 ${enabled_oauth_providers.length === 1 ? 'grid-cols-1' : 'grid-cols-2'}`}>
                            {enabled_oauth_providers.map((p) => {
                                const config = providerConfig[p.provider];
                                if (!config) return null;

                                return (
                                    <Button
                                        key={p.provider}
                                        type="button"
                                        variant="secondary"
                                        loading={oauthLoading === p.provider}
                                        disabled={oauthLoading !== null}
                                        onClick={() => handleOAuthLogin(p.provider)}
                                    >
                                        {oauthLoading !== p.provider && config.icon}
                                        {config.label}
                                    </Button>
                                );
                            })}
                        </div>
                    </>
                )}
            </form>

            {/* Register Link */}
            {is_registration_enabled && (
                <p className="mt-6 text-center text-sm text-foreground-muted">
                    Don't have an account?{' '}
                    <Link href="/register" className="text-primary hover:underline">
                        Sign up
                    </Link>
                </p>
            )}
        </AuthLayout>
    );
}
