import { useState } from 'react';
import { router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button } from '@/components/ui';
import { Settings, Save, ShieldCheck, Globe, Zap } from 'lucide-react';
import type { Server as ServerType } from '@/types';

interface ProxySettings {
    ssl_provider: 'letsencrypt' | 'custom';
    letsencrypt_email?: string;
    default_redirect_url?: string;
    enable_rate_limiting: boolean;
    rate_limit_requests: number;
    rate_limit_window: number;
    custom_headers: Record<string, string>;
}

interface Props {
    server: ServerType;
    settings: ProxySettings;
}

export default function ProxySettingsPage({ server, settings: initialSettings }: Props) {
    const [settings, setSettings] = useState<ProxySettings>(initialSettings);
    const [isDirty, setIsDirty] = useState(false);
    const [newHeaderKey, setNewHeaderKey] = useState('');
    const [newHeaderValue, setNewHeaderValue] = useState('');

    const handleChange = (key: keyof ProxySettings, value: any) => {
        setSettings((prev) => ({ ...prev, [key]: value }));
        setIsDirty(true);
    };

    const handleSave = () => {
        router.post(`/servers/${server.uuid}/proxy/settings`, settings as any, {
            onSuccess: () => {
                setIsDirty(false);
            },
        });
    };

    const handleAddHeader = () => {
        if (!newHeaderKey.trim() || !newHeaderValue.trim()) return;

        setSettings((prev) => ({
            ...prev,
            custom_headers: {
                ...prev.custom_headers,
                [newHeaderKey]: newHeaderValue,
            },
        }));
        setNewHeaderKey('');
        setNewHeaderValue('');
        setIsDirty(true);
    };

    const handleRemoveHeader = (key: string) => {
        setSettings((prev) => {
            const { [key]: _, ...rest } = prev.custom_headers;
            return { ...prev, custom_headers: rest };
        });
        setIsDirty(true);
    };

    return (
        <AppLayout
            title={`Proxy Settings - ${server.name}`}
            breadcrumbs={[
                { label: 'Servers', href: '/servers' },
                { label: server.name, href: `/servers/${server.uuid}` },
                { label: 'Proxy', href: `/servers/${server.uuid}/proxy` },
                { label: 'Settings' },
            ]}
        >
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10">
                        <Settings className="h-7 w-7 text-primary" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Proxy Settings</h1>
                        <p className="text-foreground-muted">Configure proxy behavior and security</p>
                    </div>
                </div>
                <Button
                    variant="primary"
                    size="sm"
                    onClick={handleSave}
                    disabled={!isDirty}
                >
                    <Save className="mr-2 h-4 w-4" />
                    Save Settings
                </Button>
            </div>

            {/* SSL Certificate Settings */}
            <Card className="mb-6">
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <ShieldCheck className="h-5 w-5 text-primary" />
                        <CardTitle>SSL Certificate Settings</CardTitle>
                    </div>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div>
                        <label className="mb-2 block text-sm font-medium text-foreground">
                            SSL Provider
                        </label>
                        <select
                            value={settings.ssl_provider}
                            onChange={(e) => handleChange('ssl_provider', e.target.value)}
                            className="w-full rounded-lg border border-border bg-background px-4 py-2 text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                        >
                            <option value="letsencrypt">Let's Encrypt (Free)</option>
                            <option value="custom">Custom Certificate</option>
                        </select>
                    </div>

                    {settings.ssl_provider === 'letsencrypt' && (
                        <div>
                            <label className="mb-2 block text-sm font-medium text-foreground">
                                Let's Encrypt Email
                            </label>
                            <input
                                type="email"
                                value={settings.letsencrypt_email || ''}
                                onChange={(e) => handleChange('letsencrypt_email', e.target.value)}
                                placeholder="admin@example.com"
                                className="w-full rounded-lg border border-border bg-background px-4 py-2 text-foreground placeholder:text-foreground-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                            />
                            <p className="mt-1 text-xs text-foreground-muted">
                                Email for certificate expiration notifications
                            </p>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Default Redirect Settings */}
            <Card className="mb-6">
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <Globe className="h-5 w-5 text-primary" />
                        <CardTitle>Default Redirect Rules</CardTitle>
                    </div>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div>
                        <label className="mb-2 block text-sm font-medium text-foreground">
                            Default Redirect URL
                        </label>
                        <input
                            type="url"
                            value={settings.default_redirect_url || ''}
                            onChange={(e) => handleChange('default_redirect_url', e.target.value)}
                            placeholder="https://example.com"
                            className="w-full rounded-lg border border-border bg-background px-4 py-2 text-foreground placeholder:text-foreground-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                        />
                        <p className="mt-1 text-xs text-foreground-muted">
                            Requests to unconfigured domains will be redirected here
                        </p>
                    </div>
                </CardContent>
            </Card>

            {/* Rate Limiting */}
            <Card className="mb-6">
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <Zap className="h-5 w-5 text-primary" />
                        <CardTitle>Rate Limiting</CardTitle>
                    </div>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex items-center gap-3">
                        <input
                            type="checkbox"
                            id="enable_rate_limiting"
                            checked={settings.enable_rate_limiting}
                            onChange={(e) => handleChange('enable_rate_limiting', e.target.checked)}
                            className="h-4 w-4 rounded border-border text-primary focus:ring-2 focus:ring-primary/20"
                        />
                        <label htmlFor="enable_rate_limiting" className="text-sm font-medium text-foreground">
                            Enable Rate Limiting
                        </label>
                    </div>

                    {settings.enable_rate_limiting && (
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Max Requests
                                </label>
                                <input
                                    type="number"
                                    value={settings.rate_limit_requests}
                                    onChange={(e) => handleChange('rate_limit_requests', parseInt(e.target.value))}
                                    min="1"
                                    className="w-full rounded-lg border border-border bg-background px-4 py-2 text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                />
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-foreground">
                                    Time Window (seconds)
                                </label>
                                <input
                                    type="number"
                                    value={settings.rate_limit_window}
                                    onChange={(e) => handleChange('rate_limit_window', parseInt(e.target.value))}
                                    min="1"
                                    className="w-full rounded-lg border border-border bg-background px-4 py-2 text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                />
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Custom Headers */}
            <Card>
                <CardHeader>
                    <CardTitle>Custom Headers</CardTitle>
                    <p className="text-sm text-foreground-muted">
                        Add custom HTTP headers to all responses
                    </p>
                </CardHeader>
                <CardContent className="space-y-4">
                    {/* Existing Headers */}
                    {Object.keys(settings.custom_headers).length > 0 && (
                        <div className="space-y-2">
                            {Object.entries(settings.custom_headers).map(([key, value]) => (
                                <div key={key} className="flex items-center gap-2 rounded-lg border border-border p-3">
                                    <div className="flex-1">
                                        <div className="font-mono text-sm font-medium text-foreground">{key}</div>
                                        <div className="font-mono text-sm text-foreground-muted">{value}</div>
                                    </div>
                                    <Button
                                        variant="danger"
                                        size="sm"
                                        onClick={() => handleRemoveHeader(key)}
                                    >
                                        Remove
                                    </Button>
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Add New Header */}
                    <div className="rounded-lg border border-border bg-background-subtle p-4">
                        <h4 className="mb-3 text-sm font-medium text-foreground">Add New Header</h4>
                        <div className="grid gap-3 md:grid-cols-2">
                            <input
                                type="text"
                                placeholder="Header Name"
                                value={newHeaderKey}
                                onChange={(e) => setNewHeaderKey(e.target.value)}
                                className="rounded-lg border border-border bg-background px-4 py-2 text-foreground placeholder:text-foreground-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                            />
                            <input
                                type="text"
                                placeholder="Header Value"
                                value={newHeaderValue}
                                onChange={(e) => setNewHeaderValue(e.target.value)}
                                className="rounded-lg border border-border bg-background px-4 py-2 text-foreground placeholder:text-foreground-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                            />
                        </div>
                        <Button
                            variant="secondary"
                            size="sm"
                            className="mt-3"
                            onClick={handleAddHeader}
                            disabled={!newHeaderKey.trim() || !newHeaderValue.trim()}
                        >
                            Add Header
                        </Button>
                    </div>

                    {/* Examples */}
                    <div className="rounded-lg bg-info/10 p-4">
                        <h4 className="mb-2 text-sm font-medium text-foreground">Common Headers</h4>
                        <div className="space-y-1 text-xs font-mono text-foreground-muted">
                            <div>X-Frame-Options: DENY</div>
                            <div>X-Content-Type-Options: nosniff</div>
                            <div>Strict-Transport-Security: max-age=31536000</div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
