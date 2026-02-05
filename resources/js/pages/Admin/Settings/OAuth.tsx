import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Checkbox } from '@/components/ui/Checkbox';
import { useConfirm } from '@/components/ui';
import {
    Save,
    Eye,
    EyeOff,
    KeyRound,
    ExternalLink,
    CheckCircle2,
    XCircle,
} from 'lucide-react';

interface OAuthProvider {
    id: number;
    provider: string;
    enabled: boolean;
    client_id: string | null;
    client_secret: string | null;
    redirect_uri: string | null;
    tenant: string | null;
    base_url: string | null;
}

interface Props {
    providers: OAuthProvider[];
}

// Provider display info
const providerMeta: Record<string, { label: string; color: string; icon: string; docsUrl: string; fields: string[] }> = {
    github: {
        label: 'GitHub',
        color: 'from-gray-700 to-gray-900',
        icon: 'GH',
        docsUrl: 'https://docs.github.com/en/apps/oauth-apps/building-oauth-apps/creating-an-oauth-app',
        fields: ['client_id', 'client_secret'],
    },
    gitlab: {
        label: 'GitLab',
        color: 'from-orange-500 to-orange-700',
        icon: 'GL',
        docsUrl: 'https://docs.gitlab.com/ee/integration/oauth_provider.html',
        fields: ['client_id', 'client_secret', 'base_url'],
    },
    google: {
        label: 'Google',
        color: 'from-blue-500 to-blue-700',
        icon: 'G',
        docsUrl: 'https://console.cloud.google.com/apis/credentials',
        fields: ['client_id', 'client_secret', 'tenant'],
    },
    azure: {
        label: 'Azure AD',
        color: 'from-sky-500 to-sky-700',
        icon: 'AZ',
        docsUrl: 'https://learn.microsoft.com/en-us/entra/identity-platform/quickstart-register-app',
        fields: ['client_id', 'client_secret', 'tenant'],
    },
    bitbucket: {
        label: 'Bitbucket',
        color: 'from-blue-600 to-blue-800',
        icon: 'BB',
        docsUrl: 'https://support.atlassian.com/bitbucket-cloud/docs/use-oauth-on-bitbucket-cloud/',
        fields: ['client_id', 'client_secret'],
    },
    discord: {
        label: 'Discord',
        color: 'from-indigo-500 to-indigo-700',
        icon: 'DC',
        docsUrl: 'https://discord.com/developers/applications',
        fields: ['client_id', 'client_secret'],
    },
    authentik: {
        label: 'Authentik',
        color: 'from-amber-500 to-amber-700',
        icon: 'AK',
        docsUrl: 'https://docs.goauthentik.io/docs/providers/oauth2/',
        fields: ['client_id', 'client_secret', 'base_url'],
    },
    clerk: {
        label: 'Clerk',
        color: 'from-violet-500 to-violet-700',
        icon: 'CK',
        docsUrl: 'https://clerk.com/docs',
        fields: ['client_id', 'client_secret', 'base_url'],
    },
    infomaniak: {
        label: 'Infomaniak',
        color: 'from-green-500 to-green-700',
        icon: 'IM',
        docsUrl: 'https://developer.infomaniak.com/',
        fields: ['client_id', 'client_secret'],
    },
    zitadel: {
        label: 'Zitadel',
        color: 'from-cyan-500 to-cyan-700',
        icon: 'ZT',
        docsUrl: 'https://zitadel.com/docs/guides/integrate/login/oidc',
        fields: ['client_id', 'client_secret', 'base_url'],
    },
};

const SECRET_PLACEHOLDER = '••••••••';

const fieldLabels: Record<string, { label: string; placeholder: string; hint?: string }> = {
    client_id: { label: 'Client ID', placeholder: 'Enter OAuth client ID' },
    client_secret: { label: 'Client Secret', placeholder: 'Enter OAuth client secret' },
    redirect_uri: { label: 'Redirect URI', placeholder: 'Auto-generated', hint: 'Leave empty for auto-detection' },
    tenant: { label: 'Tenant / Domain', placeholder: 'e.g., your-org.onmicrosoft.com or allowed-domain.com' },
    base_url: { label: 'Base URL', placeholder: 'e.g., https://authentik.your-domain.com' },
};

function ProviderCard({
    provider,
    onSave,
}: {
    provider: OAuthProvider;
    onSave: (id: number, data: Partial<OAuthProvider>) => void;
}) {
    const meta = providerMeta[provider.provider] || {
        label: provider.provider,
        color: 'from-gray-500 to-gray-700',
        icon: provider.provider.charAt(0).toUpperCase(),
        docsUrl: '#',
        fields: ['client_id', 'client_secret'],
    };

    const [formData, setFormData] = React.useState<Partial<OAuthProvider>>({
        enabled: provider.enabled,
        client_id: provider.client_id || '',
        client_secret: provider.client_secret || '',
        redirect_uri: provider.redirect_uri || '',
        tenant: provider.tenant || '',
        base_url: provider.base_url || '',
    });
    const [showSecret, setShowSecret] = React.useState(false);
    const [isSaving, setIsSaving] = React.useState(false);
    const [expanded, setExpanded] = React.useState(provider.enabled);

    const hasCredentials = !!(formData.client_id && formData.client_secret && formData.client_secret !== SECRET_PLACEHOLDER);
    const canEnable = !!formData.client_id && !!formData.client_secret;

    const handleSave = () => {
        setIsSaving(true);
        onSave(provider.id, formData);
        setTimeout(() => setIsSaving(false), 500);
    };

    const update = (fields: Partial<OAuthProvider>) => {
        setFormData((prev) => ({ ...prev, ...fields }));
    };

    return (
        <Card variant="glass" className="overflow-hidden">
            {/* Provider header - always visible */}
            <div
                className="flex cursor-pointer items-center justify-between p-5"
                onClick={() => setExpanded(!expanded)}
            >
                <div className="flex items-center gap-4">
                    <div className={`flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br ${meta.color} text-sm font-bold text-white shadow-lg`}>
                        {meta.icon}
                    </div>
                    <div>
                        <p className="text-base font-semibold text-foreground">{meta.label}</p>
                        <p className="text-xs text-foreground-muted">
                            {provider.redirect_uri || 'Not configured'}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-3">
                    {provider.enabled ? (
                        <Badge variant="success" className="gap-1">
                            <CheckCircle2 className="h-3 w-3" />
                            Active
                        </Badge>
                    ) : provider.client_id ? (
                        <Badge variant="warning" className="gap-1">
                            Configured
                        </Badge>
                    ) : (
                        <Badge variant="default" className="gap-1">
                            <XCircle className="h-3 w-3" />
                            Disabled
                        </Badge>
                    )}
                    <ChevronIcon expanded={expanded} />
                </div>
            </div>

            {/* Expandable configuration */}
            {expanded && (
                <div className="border-t border-white/[0.06] px-5 pb-5 pt-4">
                    <div className="space-y-4">
                        {/* Enable toggle */}
                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-3">
                            <div>
                                <p className="text-sm font-medium text-foreground">Enable {meta.label}</p>
                                <p className="text-xs text-foreground-muted">
                                    Allow users to sign in with {meta.label}
                                </p>
                            </div>
                            <Checkbox
                                checked={formData.enabled || false}
                                onCheckedChange={(checked) => update({ enabled: checked === true })}
                                disabled={!canEnable && !formData.enabled}
                            />
                        </div>

                        {/* Credentials */}
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Input
                                value={formData.client_id || ''}
                                onChange={(e) => update({ client_id: e.target.value })}
                                placeholder={fieldLabels.client_id.placeholder}
                                label={fieldLabels.client_id.label}
                            />
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    {fieldLabels.client_secret.label}
                                </label>
                                <div className="relative">
                                    <Input
                                        type={showSecret ? 'text' : 'password'}
                                        value={formData.client_secret || ''}
                                        onChange={(e) => update({ client_secret: e.target.value })}
                                        placeholder={fieldLabels.client_secret.placeholder}
                                        className="pr-10"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowSecret(!showSecret)}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-foreground-muted hover:text-foreground"
                                    >
                                        {showSecret ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                    </button>
                                </div>
                            </div>
                        </div>

                        {/* Provider-specific fields */}
                        {meta.fields.includes('tenant') && (
                            <Input
                                value={formData.tenant || ''}
                                onChange={(e) => update({ tenant: e.target.value })}
                                placeholder={fieldLabels.tenant.placeholder}
                                label={fieldLabels.tenant.label}
                                hint={provider.provider === 'google' ? 'Restrict login to this Google Workspace domain' : 'Azure AD tenant ID or domain'}
                            />
                        )}

                        {meta.fields.includes('base_url') && (
                            <Input
                                value={formData.base_url || ''}
                                onChange={(e) => update({ base_url: e.target.value })}
                                placeholder={fieldLabels.base_url.placeholder}
                                label={fieldLabels.base_url.label}
                                hint={`Base URL of your ${meta.label} instance`}
                            />
                        )}

                        <Input
                            value={formData.redirect_uri || ''}
                            onChange={(e) => update({ redirect_uri: e.target.value })}
                            placeholder={fieldLabels.redirect_uri.placeholder}
                            label={fieldLabels.redirect_uri.label}
                            hint={fieldLabels.redirect_uri.hint}
                        />

                        {/* Actions */}
                        <div className="flex items-center justify-between border-t border-white/[0.06] pt-4">
                            <a
                                href={meta.docsUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="flex items-center gap-1.5 text-sm text-primary hover:text-primary/80"
                            >
                                <ExternalLink className="h-3.5 w-3.5" />
                                Setup Guide
                            </a>
                            <Button onClick={handleSave} disabled={isSaving} size="sm">
                                <Save className="h-4 w-4" />
                                {isSaving ? 'Saving...' : 'Save'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </Card>
    );
}

function ChevronIcon({ expanded }: { expanded: boolean }) {
    return (
        <svg
            className={`h-5 w-5 text-foreground-muted transition-transform ${expanded ? 'rotate-180' : ''}`}
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={2}
        >
            <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
    );
}

export default function AdminOAuthSettings({ providers }: Props) {
    const handleSave = (id: number, data: Partial<OAuthProvider>) => {
        // Filter out placeholder secrets
        const payload: Record<string, unknown> = { ...data };
        if (payload.client_secret === SECRET_PLACEHOLDER) {
            delete payload.client_secret;
        }

        router.post(
            `/admin/settings/oauth/${id}`,
            payload as any,
            { preserveScroll: true }
        );
    };

    const enabledCount = providers.filter((p) => p.enabled).length;

    return (
        <AdminLayout
            title="OAuth / SSO"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Settings', href: '/admin/settings' },
                { label: 'OAuth / SSO' },
            ]}
        >
            <div className="mx-auto max-w-4xl">
                {/* Header */}
                <div className="mb-8">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-purple-500">
                            <KeyRound className="h-5 w-5 text-white" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold text-foreground">OAuth / SSO Providers</h1>
                            <p className="text-sm text-foreground-muted">
                                {enabledCount} of {providers.length} providers active
                            </p>
                        </div>
                    </div>
                </div>

                {/* Provider cards */}
                <div className="space-y-4">
                    {providers.map((provider) => (
                        <ProviderCard
                            key={provider.id}
                            provider={provider}
                            onSave={handleSave}
                        />
                    ))}
                </div>

                {providers.length === 0 && (
                    <Card variant="glass" className="py-12 text-center">
                        <p className="text-foreground-muted">
                            No OAuth providers found. Run database seeder to initialize providers.
                        </p>
                    </Card>
                )}
            </div>
        </AdminLayout>
    );
}
