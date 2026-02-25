import { useForm, Link } from '@inertiajs/react';
import { AuthLayout } from '@/components/layout';
import { Input, Button, Badge } from '@/components/ui';
import { Github, Mail, GitBranch, Users, Shield } from 'lucide-react';
import { useState } from 'react';

interface OAuthProvider {
    id: number;
    provider: string;
    enabled: boolean;
}

interface InvitationData {
    uuid: string;
    email: string;
    team_name: string;
    role: string;
}

interface Props {
    isFirstUser?: boolean;
    enabled_oauth_providers?: OAuthProvider[];
    invitation?: InvitationData | null;
}

const providerConfig: Record<string, { label: string; icon: React.ReactNode }> = {
    github: { label: 'GitHub', icon: <Github className="mr-2 h-4 w-4" /> },
    google: { label: 'Google', icon: <Mail className="mr-2 h-4 w-4" /> },
    gitlab: { label: 'GitLab', icon: <GitBranch className="mr-2 h-4 w-4" /> },
    bitbucket: { label: 'Bitbucket', icon: <GitBranch className="mr-2 h-4 w-4" /> },
    azure: { label: 'Azure AD', icon: <Mail className="mr-2 h-4 w-4" /> },
    discord: { label: 'Discord', icon: <Mail className="mr-2 h-4 w-4" /> },
};

export default function Register({ enabled_oauth_providers = [], invitation }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: invitation?.email ?? '',
        password: '',
        password_confirmation: '',
        invite: invitation?.uuid ?? '',
    });
    const [oauthLoading, setOauthLoading] = useState<string | null>(null);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/register');
    };

    const handleOAuthLogin = (provider: string) => {
        setOauthLoading(provider);
        window.location.href = `/auth/${provider}/redirect`;
    };

    const hasOAuthProviders = enabled_oauth_providers.length > 0;

    const title = invitation ? 'Join Team' : 'Create Account';
    const subtitle = invitation
        ? `Create an account to join ${invitation.team_name}.`
        : 'Start deploying your applications for free.';

    return (
        <AuthLayout title={title} subtitle={subtitle}>
            {/* Invitation context banner */}
            {invitation && (
                <div className="mb-6 rounded-lg border border-primary/20 bg-primary/5 p-4">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-primary/10">
                            <Users className="h-5 w-5 text-primary" />
                        </div>
                        <div className="flex-1">
                            <p className="font-semibold text-foreground">
                                {invitation.team_name}
                            </p>
                            <div className="flex items-center gap-2 text-sm text-foreground-muted">
                                <span>You'll join as</span>
                                <Badge variant="secondary" size="sm">
                                    <Shield className="mr-1 h-3 w-3" />
                                    {invitation.role}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-4">
                {/* Hidden invite UUID */}
                {invitation && (
                    <input type="hidden" name="invite" value={data.invite} />
                )}

                <Input
                    label="Name"
                    type="text"
                    placeholder="John Doe"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    error={errors.name}
                    required
                    autoFocus
                />

                <Input
                    label="Email"
                    type="email"
                    placeholder="you@example.com"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                    required
                    disabled={!!invitation}
                />
                {invitation && (
                    <p className="!mt-1 text-xs text-foreground-muted">
                        Email is locked to the invitation address.
                    </p>
                )}

                <Input
                    label="Password"
                    type="password"
                    placeholder="••••••••"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                    hint="Must be at least 8 characters"
                    required
                />

                <Input
                    label="Confirm Password"
                    type="password"
                    placeholder="••••••••"
                    value={data.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                    error={errors.password_confirmation}
                    required
                />

                <Button type="submit" className="w-full" loading={processing}>
                    {invitation ? 'Create Account & Join Team' : 'Create Account'}
                </Button>

                {hasOAuthProviders && !invitation && (
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

            {/* Login Link */}
            <p className="mt-4 text-center text-sm text-foreground-muted">
                Already have an account?{' '}
                <Link href="/login" className="text-primary hover:underline">
                    Sign in
                </Link>
            </p>
        </AuthLayout>
    );
}
