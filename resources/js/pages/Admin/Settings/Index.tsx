import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Checkbox } from '@/components/ui/Checkbox';
import { Select } from '@/components/ui/Select';
import { TabsRoot, TabsList, TabsTrigger, TabsPanels, TabsContent } from '@/components/ui/Tabs';
import { useConfirm } from '@/components/ui';
import {
    Globe,
    Mail,
    Save,
    RotateCcw,
    Brain,
    Shield,
    Activity,
    Server,
    RefreshCw,
    Send,
    Eye,
    EyeOff,
    Rocket,
    Hammer,
    Container,
    Undo2,
    Bug,
    Terminal,
    Network,
    Gauge,
    Layers,
    Clock,
    ShieldCheck,
    Cloud,
    Trash2,
    Zap,
} from 'lucide-react';

interface InstanceSettingsData {
    id?: number;
    // General
    fqdn?: string;
    instance_name?: string;
    public_ipv4?: string;
    public_ipv6?: string;
    allowed_ip_ranges?: string | string[];
    // Security
    is_registration_enabled?: boolean;
    is_dns_validation_enabled?: boolean;
    // Updates
    is_auto_update_enabled?: boolean;
    auto_update_frequency?: string;
    update_check_frequency?: string;
    // AI Features
    is_ai_code_review_enabled?: boolean;
    is_ai_error_analysis_enabled?: boolean;
    is_ai_chat_enabled?: boolean;
    // SMTP
    smtp_enabled?: boolean;
    smtp_host?: string;
    smtp_port?: number;
    smtp_username?: string;
    smtp_password?: string;
    smtp_from_address?: string;
    smtp_from_name?: string;
    smtp_recipients?: string;
    smtp_timeout?: number;
    smtp_encryption?: string;
    // Resend
    resend_enabled?: boolean;
    resend_api_key?: string;
    // Sentinel
    sentinel_token?: string;
    // Resource Monitoring
    resource_monitoring_enabled?: boolean;
    resource_check_interval_minutes?: number;
    resource_warning_cpu_threshold?: number;
    resource_critical_cpu_threshold?: number;
    resource_warning_memory_threshold?: number;
    resource_critical_memory_threshold?: number;
    resource_warning_disk_threshold?: number;
    resource_critical_disk_threshold?: number;
    // Auto-Provisioning
    auto_provision_enabled?: boolean;
    auto_provision_api_key?: string;
    auto_provision_max_servers_per_day?: number;
    auto_provision_cooldown_minutes?: number;
    // AI Provider
    ai_default_provider?: string;
    ai_anthropic_api_key?: string;
    ai_openai_api_key?: string;
    ai_claude_model?: string;
    ai_openai_model?: string;
    ai_ollama_base_url?: string;
    ai_ollama_model?: string;
    ai_max_tokens?: number;
    ai_cache_enabled?: boolean;
    ai_cache_ttl?: number;
    // Global S3
    s3_enabled?: boolean;
    s3_endpoint?: string;
    s3_bucket?: string;
    s3_region?: string;
    s3_key?: string;
    s3_secret?: string;
    s3_path?: string;
    // Application global defaults
    app_default_auto_deploy?: boolean;
    app_default_force_https?: boolean;
    app_default_preview_deployments?: boolean;
    app_default_pr_deployments_public?: boolean;
    app_default_git_submodules?: boolean;
    app_default_git_lfs?: boolean;
    app_default_git_shallow_clone?: boolean;
    app_default_use_build_secrets?: boolean;
    app_default_inject_build_args?: boolean;
    app_default_include_commit_in_build?: boolean;
    app_default_docker_images_to_keep?: number;
    app_default_auto_rollback?: boolean;
    app_default_rollback_validation_sec?: number;
    app_default_rollback_max_restarts?: number;
    app_default_rollback_on_health_fail?: boolean;
    app_default_rollback_on_crash_loop?: boolean;
    app_default_debug?: boolean;
    app_default_build_pack?: string;
    app_default_build_timeout?: number;
    app_default_static_image?: string;
    app_default_requires_approval?: boolean;
    // Infrastructure: SSH
    ssh_mux_enabled?: boolean;
    ssh_mux_persist_time?: number;
    ssh_mux_max_age?: number;
    ssh_connection_timeout?: number;
    ssh_command_timeout?: number;
    ssh_max_retries?: number;
    ssh_retry_base_delay?: number;
    ssh_retry_max_delay?: number;
    // Infrastructure: Docker Registry
    docker_registry_url?: string;
    docker_registry_username?: string;
    docker_registry_password?: string;
    // Infrastructure: Default Proxy
    default_proxy_type?: string;
    // Rate Limiting & Queue
    api_rate_limit?: number;
    horizon_balance?: string;
    horizon_min_processes?: number;
    horizon_max_processes?: number;
    horizon_worker_memory?: number;
    horizon_worker_timeout?: number;
    horizon_max_jobs?: number;
    horizon_trim_recent_minutes?: number;
    horizon_trim_failed_minutes?: number;
    horizon_queue_wait_threshold?: number;

    // Cloudflare Protection
    cloudflare_api_token?: string;
    cloudflare_account_id?: string;
    cloudflare_zone_id?: string;
    cloudflare_tunnel_id?: string;
    cloudflare_tunnel_token?: string;
    is_cloudflare_protection_enabled?: boolean;
    cloudflare_last_synced_at?: string;

    created_at?: string;
    updated_at?: string;
}

interface Props {
    settings: InstanceSettingsData;
}

// Helper to normalize allowed_ip_ranges to string for display
function ipRangesToString(val: string | string[] | undefined): string {
    if (!val) return '';
    if (Array.isArray(val)) return val.join(', ');
    return val;
}

export default function AdminSettingsIndex({ settings }: Props) {
    const confirm = useConfirm();
    const [formData, setFormData] = React.useState<InstanceSettingsData>(() => ({
        ...settings,
        allowed_ip_ranges: ipRangesToString(settings?.allowed_ip_ranges),
    }));
    const [isSaving, setIsSaving] = React.useState(false);
    const [isTesting, setIsTesting] = React.useState(false);
    const [showSmtpPassword, setShowSmtpPassword] = React.useState(false);
    const [showResendKey, setShowResendKey] = React.useState(false);
    const [showSentinelToken, setShowSentinelToken] = React.useState(false);
    const [showProvisionKey, setShowProvisionKey] = React.useState(false);
    const [showAnthropicKey, setShowAnthropicKey] = React.useState(false);
    const [showOpenaiKey, setShowOpenaiKey] = React.useState(false);
    const [showS3Key, setShowS3Key] = React.useState(false);
    const [showS3Secret, setShowS3Secret] = React.useState(false);
    const [showDockerUsername, setShowDockerUsername] = React.useState(false);
    const [showDockerPassword, setShowDockerPassword] = React.useState(false);
    const [showCloudflareToken, setShowCloudflareToken] = React.useState(false);
    const [isCloudflareAction, setIsCloudflareAction] = React.useState(false);

    const update = (fields: Partial<InstanceSettingsData>) => {
        setFormData((prev) => ({ ...prev, ...fields }));
    };

    const handleSave = () => {
        setIsSaving(true);
        router.post(
            '/admin/settings',
            { settings: formData } as any,
            {
                preserveScroll: true,
                onFinish: () => setIsSaving(false),
            }
        );
    };

    const handleTestEmail = () => {
        setIsTesting(true);
        router.post(
            '/admin/settings/test-email',
            {},
            {
                preserveScroll: true,
                onFinish: () => setIsTesting(false),
            }
        );
    };

    const handleReset = async () => {
        const confirmed = await confirm({
            title: 'Reset Settings',
            description: 'Reset all settings to values from server?',
            confirmText: 'Reset',
            variant: 'warning',
        });
        if (confirmed) {
            setFormData({
                ...settings,
                allowed_ip_ranges: ipRangesToString(settings?.allowed_ip_ranges),
            });
        }
    };

    const handleExport = () => {
        window.location.href = '/admin/settings/export';
    };

    const handleImport = () => {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.json';
        input.onchange = (e) => {
            const file = (e.target as HTMLInputElement).files?.[0];
            if (!file) return;
            const formPayload = new FormData();
            formPayload.append('file', file);
            router.post('/admin/settings/import', formPayload as any, {
                preserveScroll: true,
                forceFormData: true,
            });
        };
        input.click();
    };

    return (
        <AdminLayout
            title="Settings"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Settings' },
            ]}
        >
            <div className="mx-auto max-w-5xl">
                {/* Header */}
                <div className="mb-8 flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">System Settings</h1>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Configure global system settings and feature flags
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="secondary" onClick={handleExport}>
                            Export
                        </Button>
                        <Button variant="secondary" onClick={handleImport}>
                            Import
                        </Button>
                        <Button variant="secondary" onClick={handleReset}>
                            <RotateCcw className="h-4 w-4" />
                            Reset
                        </Button>
                        <Button onClick={handleSave} disabled={isSaving}>
                            <Save className="h-4 w-4" />
                            {isSaving ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </div>
                </div>

                <TabsRoot defaultIndex={0}>
                    <TabsList>
                        <TabsTrigger>
                            <span className="flex items-center gap-1.5">
                                <Globe className="h-4 w-4" />
                                General
                            </span>
                        </TabsTrigger>
                        <TabsTrigger>
                            <span className="flex items-center gap-1.5">
                                <Mail className="h-4 w-4" />
                                Email
                            </span>
                        </TabsTrigger>
                        <TabsTrigger>
                            <span className="flex items-center gap-1.5">
                                <Brain className="h-4 w-4" />
                                AI Features
                            </span>
                        </TabsTrigger>
                        <TabsTrigger>
                            <span className="flex items-center gap-1.5">
                                <Activity className="h-4 w-4" />
                                Monitoring
                            </span>
                        </TabsTrigger>
                        <TabsTrigger>
                            <span className="flex items-center gap-1.5">
                                <Server className="h-4 w-4" />
                                Infrastructure
                            </span>
                        </TabsTrigger>
                        <TabsTrigger>
                            <span className="flex items-center gap-1.5">
                                <Rocket className="h-4 w-4" />
                                App Defaults
                            </span>
                        </TabsTrigger>
                        <TabsTrigger>
                            <span className="flex items-center gap-1.5">
                                <Cloud className="h-4 w-4" />
                                IP Protection
                            </span>
                        </TabsTrigger>
                    </TabsList>

                    <TabsPanels>
                        {/* ============ TAB 1: GENERAL ============ */}
                        <TabsContent>
                            <div className="space-y-6">
                                {/* Instance Info */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <Globe className="h-5 w-5 text-primary" />
                                            <CardTitle>Instance Configuration</CardTitle>
                                        </div>
                                        <CardDescription>Basic instance identification and network settings</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Input
                                                value={formData.instance_name || ''}
                                                onChange={(e) => update({ instance_name: e.target.value })}
                                                placeholder="Saturn Platform"
                                                label="Instance Name"
                                            />
                                            <Input
                                                value={formData.fqdn || ''}
                                                onChange={(e) => update({ fqdn: e.target.value })}
                                                placeholder="https://your-domain.com"
                                                label="FQDN"
                                            />
                                        </div>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Input
                                                value={formData.public_ipv4 || ''}
                                                onChange={(e) => update({ public_ipv4: e.target.value })}
                                                placeholder="0.0.0.0"
                                                label="Public IPv4"
                                            />
                                            <Input
                                                value={formData.public_ipv6 || ''}
                                                onChange={(e) => update({ public_ipv6: e.target.value })}
                                                placeholder="::1"
                                                label="Public IPv6"
                                            />
                                        </div>
                                        <Input
                                            value={
                                                typeof formData.allowed_ip_ranges === 'string'
                                                    ? formData.allowed_ip_ranges
                                                    : ipRangesToString(formData.allowed_ip_ranges)
                                            }
                                            onChange={(e) => update({ allowed_ip_ranges: e.target.value })}
                                            placeholder="0.0.0.0/0, 192.168.1.0/24"
                                            label="Allowed IP Ranges"
                                            hint="Comma-separated list of CIDR ranges. Leave empty for unrestricted access."
                                        />
                                    </CardContent>
                                </Card>

                                {/* Security & Registration */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <Shield className="h-5 w-5 text-primary" />
                                            <CardTitle>Security & Registration</CardTitle>
                                        </div>
                                        <CardDescription>User registration and DNS validation controls</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">User Registration</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Allow new users to register accounts on this instance
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.is_registration_enabled || false}
                                                onCheckedChange={(checked) =>
                                                    update({ is_registration_enabled: checked === true })
                                                }
                                            />
                                        </div>
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">DNS Validation</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Validate DNS records when configuring custom domains
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.is_dns_validation_enabled || false}
                                                onCheckedChange={(checked) =>
                                                    update({ is_dns_validation_enabled: checked === true })
                                                }
                                            />
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Updates */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <RefreshCw className="h-5 w-5 text-primary" />
                                            <CardTitle>Update Settings</CardTitle>
                                        </div>
                                        <CardDescription>Auto-update and version check configuration</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">Auto Update</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Automatically install updates when available
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.is_auto_update_enabled || false}
                                                onCheckedChange={(checked) =>
                                                    update({ is_auto_update_enabled: checked === true })
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Input
                                                value={formData.auto_update_frequency || ''}
                                                onChange={(e) => update({ auto_update_frequency: e.target.value })}
                                                placeholder="0 0 * * *"
                                                label="Auto Update Frequency"
                                                hint="Cron expression for auto-update schedule"
                                            />
                                            <Input
                                                value={formData.update_check_frequency || ''}
                                                onChange={(e) => update({ update_check_frequency: e.target.value })}
                                                placeholder="0 */6 * * *"
                                                label="Update Check Frequency"
                                                hint="Cron expression for checking new versions"
                                            />
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </TabsContent>

                        {/* ============ TAB 2: EMAIL ============ */}
                        <TabsContent>
                            <div className="space-y-6">
                                {/* SMTP Configuration */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <Mail className="h-5 w-5 text-primary" />
                                                <CardTitle>SMTP Configuration</CardTitle>
                                            </div>
                                            <Badge variant={formData.smtp_enabled ? 'success' : 'default'}>
                                                {formData.smtp_enabled ? 'Enabled' : 'Disabled'}
                                            </Badge>
                                        </div>
                                        <CardDescription>Configure SMTP server for sending emails (notifications, invites, etc.)</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">Enable SMTP</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Use SMTP server for sending emails
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.smtp_enabled || false}
                                                onCheckedChange={(checked) =>
                                                    update({ smtp_enabled: checked === true })
                                                }
                                            />
                                        </div>

                                        {formData.smtp_enabled && (
                                            <>
                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <Input
                                                        value={formData.smtp_host || ''}
                                                        onChange={(e) => update({ smtp_host: e.target.value })}
                                                        placeholder="smtp.example.com"
                                                        label="SMTP Host"
                                                    />
                                                    <div className="grid grid-cols-2 gap-4">
                                                        <Input
                                                            type="number"
                                                            value={formData.smtp_port ?? 587}
                                                            onChange={(e) => update({ smtp_port: parseInt(e.target.value) || 587 })}
                                                            placeholder="587"
                                                            label="Port"
                                                        />
                                                        <Select
                                                            value={formData.smtp_encryption || 'tls'}
                                                            onChange={(e) => update({ smtp_encryption: e.target.value })}
                                                            label="Encryption"
                                                            options={[
                                                                { value: 'tls', label: 'TLS' },
                                                                { value: 'ssl', label: 'SSL' },
                                                                { value: 'none', label: 'None' },
                                                            ]}
                                                        />
                                                    </div>
                                                </div>
                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <Input
                                                        value={formData.smtp_username || ''}
                                                        onChange={(e) => update({ smtp_username: e.target.value })}
                                                        placeholder="user@example.com"
                                                        label="Username"
                                                    />
                                                    <div>
                                                        <label className="mb-1.5 block text-sm font-medium text-foreground">
                                                            Password
                                                        </label>
                                                        <div className="relative">
                                                            <Input
                                                                type={showSmtpPassword ? 'text' : 'password'}
                                                                value={formData.smtp_password || ''}
                                                                onChange={(e) => update({ smtp_password: e.target.value })}
                                                                placeholder="••••••••"
                                                                className="pr-10"
                                                            />
                                                            <button
                                                                type="button"
                                                                onClick={() => setShowSmtpPassword(!showSmtpPassword)}
                                                                className="absolute right-3 top-1/2 -translate-y-1/2 text-foreground-muted hover:text-foreground"
                                                            >
                                                                {showSmtpPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <Input
                                                        value={formData.smtp_from_address || ''}
                                                        onChange={(e) => update({ smtp_from_address: e.target.value })}
                                                        placeholder="noreply@example.com"
                                                        label="From Address"
                                                    />
                                                    <Input
                                                        value={formData.smtp_from_name || ''}
                                                        onChange={(e) => update({ smtp_from_name: e.target.value })}
                                                        placeholder="Saturn Platform"
                                                        label="From Name"
                                                    />
                                                </div>
                                                <Input
                                                    value={formData.smtp_recipients || ''}
                                                    onChange={(e) => update({ smtp_recipients: e.target.value })}
                                                    placeholder="admin@example.com, ops@example.com"
                                                    label="Default Recipients"
                                                    hint="Comma-separated email addresses for system notifications"
                                                />
                                                <Input
                                                    type="number"
                                                    value={formData.smtp_timeout ?? 30}
                                                    onChange={(e) => update({ smtp_timeout: parseInt(e.target.value) || 30 })}
                                                    placeholder="30"
                                                    label="Timeout (seconds)"
                                                    hint="Connection timeout in seconds"
                                                />

                                                <div className="flex justify-end border-t border-white/[0.06] pt-4">
                                                    <Button
                                                        variant="secondary"
                                                        onClick={handleTestEmail}
                                                        disabled={isTesting}
                                                    >
                                                        <Send className="h-4 w-4" />
                                                        {isTesting ? 'Sending...' : 'Send Test Email'}
                                                    </Button>
                                                </div>
                                            </>
                                        )}
                                    </CardContent>
                                </Card>

                                {/* Resend Configuration */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <Send className="h-5 w-5 text-primary" />
                                                <CardTitle>Resend</CardTitle>
                                            </div>
                                            <Badge variant={formData.resend_enabled ? 'success' : 'default'}>
                                                {formData.resend_enabled ? 'Enabled' : 'Disabled'}
                                            </Badge>
                                        </div>
                                        <CardDescription>Alternative email delivery via Resend API</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">Enable Resend</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Use Resend API instead of SMTP for email delivery
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.resend_enabled || false}
                                                onCheckedChange={(checked) =>
                                                    update({ resend_enabled: checked === true })
                                                }
                                            />
                                        </div>

                                        {formData.resend_enabled && (
                                            <div>
                                                <label className="mb-1.5 block text-sm font-medium text-foreground">
                                                    API Key
                                                </label>
                                                <div className="relative">
                                                    <Input
                                                        type={showResendKey ? 'text' : 'password'}
                                                        value={formData.resend_api_key || ''}
                                                        onChange={(e) => update({ resend_api_key: e.target.value })}
                                                        placeholder="re_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                                        className="pr-10"
                                                    />
                                                    <button
                                                        type="button"
                                                        onClick={() => setShowResendKey(!showResendKey)}
                                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-foreground-muted hover:text-foreground"
                                                    >
                                                        {showResendKey ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                                    </button>
                                                </div>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            </div>
                        </TabsContent>

                        {/* ============ TAB 3: AI FEATURES ============ */}
                        <TabsContent>
                            <div className="space-y-6">
                                {/* Feature Toggles */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <Brain className="h-5 w-5 text-primary" />
                                            <CardTitle>AI Feature Toggles</CardTitle>
                                        </div>
                                        <CardDescription>
                                            Enable or disable individual AI-powered features
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">AI Code Review</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Analyze code for security issues and bad practices during deployment
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.is_ai_code_review_enabled || false}
                                                onCheckedChange={(checked) =>
                                                    update({ is_ai_code_review_enabled: checked === true })
                                                }
                                            />
                                        </div>
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">AI Error Analysis</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Analyze deployment failures to identify root cause and solutions
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.is_ai_error_analysis_enabled ?? true}
                                                onCheckedChange={(checked) =>
                                                    update({ is_ai_error_analysis_enabled: checked === true })
                                                }
                                            />
                                        </div>
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">AI Chat Assistant</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Interactive AI assistant for managing resources via chat
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.is_ai_chat_enabled ?? true}
                                                onCheckedChange={(checked) =>
                                                    update({ is_ai_chat_enabled: checked === true })
                                                }
                                            />
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Provider Configuration */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <Brain className="h-5 w-5 text-primary" />
                                            <CardTitle>AI Provider Configuration</CardTitle>
                                        </div>
                                        <CardDescription>
                                            Configure AI providers and API keys. The system will fallback to other providers if the default is unavailable.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <Select
                                            value={formData.ai_default_provider || 'claude'}
                                            onChange={(e) => update({ ai_default_provider: e.target.value })}
                                            label="Default Provider"
                                            options={[
                                                { value: 'claude', label: 'Claude (Anthropic)' },
                                                { value: 'openai', label: 'OpenAI (GPT)' },
                                                { value: 'ollama', label: 'Ollama (Self-hosted)' },
                                            ]}
                                        />

                                        {/* Anthropic / Claude */}
                                        <div className="rounded-lg border border-white/[0.06] p-4">
                                            <p className="mb-3 font-medium text-foreground">Claude (Anthropic)</p>
                                            <div className="space-y-3">
                                                <div>
                                                    <label className="mb-1.5 block text-sm font-medium text-foreground">
                                                        Anthropic API Key
                                                    </label>
                                                    <div className="relative">
                                                        <Input
                                                            type={showAnthropicKey ? 'text' : 'password'}
                                                            value={formData.ai_anthropic_api_key || ''}
                                                            onChange={(e) => update({ ai_anthropic_api_key: e.target.value })}
                                                            placeholder="sk-ant-..."
                                                            className="pr-10"
                                                        />
                                                        <button
                                                            type="button"
                                                            onClick={() => setShowAnthropicKey(!showAnthropicKey)}
                                                            className="absolute right-3 top-1/2 -translate-y-1/2 text-foreground-muted hover:text-foreground"
                                                        >
                                                            {showAnthropicKey ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                                        </button>
                                                    </div>
                                                </div>
                                                <Input
                                                    value={formData.ai_claude_model || 'claude-sonnet-4-20250514'}
                                                    onChange={(e) => update({ ai_claude_model: e.target.value })}
                                                    placeholder="claude-sonnet-4-20250514"
                                                    label="Model"
                                                    hint="e.g., claude-sonnet-4-20250514, claude-haiku-4-5-20251001"
                                                />
                                            </div>
                                        </div>

                                        {/* OpenAI */}
                                        <div className="rounded-lg border border-white/[0.06] p-4">
                                            <p className="mb-3 font-medium text-foreground">OpenAI</p>
                                            <div className="space-y-3">
                                                <div>
                                                    <label className="mb-1.5 block text-sm font-medium text-foreground">
                                                        OpenAI API Key
                                                    </label>
                                                    <div className="relative">
                                                        <Input
                                                            type={showOpenaiKey ? 'text' : 'password'}
                                                            value={formData.ai_openai_api_key || ''}
                                                            onChange={(e) => update({ ai_openai_api_key: e.target.value })}
                                                            placeholder="sk-..."
                                                            className="pr-10"
                                                        />
                                                        <button
                                                            type="button"
                                                            onClick={() => setShowOpenaiKey(!showOpenaiKey)}
                                                            className="absolute right-3 top-1/2 -translate-y-1/2 text-foreground-muted hover:text-foreground"
                                                        >
                                                            {showOpenaiKey ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                                        </button>
                                                    </div>
                                                </div>
                                                <Input
                                                    value={formData.ai_openai_model || 'gpt-4o-mini'}
                                                    onChange={(e) => update({ ai_openai_model: e.target.value })}
                                                    placeholder="gpt-4o-mini"
                                                    label="Model"
                                                    hint="e.g., gpt-4o, gpt-4o-mini, gpt-4-turbo"
                                                />
                                            </div>
                                        </div>

                                        {/* Ollama */}
                                        <div className="rounded-lg border border-white/[0.06] p-4">
                                            <p className="mb-3 font-medium text-foreground">Ollama (Self-hosted)</p>
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                <Input
                                                    value={formData.ai_ollama_base_url || ''}
                                                    onChange={(e) => update({ ai_ollama_base_url: e.target.value })}
                                                    placeholder="http://localhost:11434"
                                                    label="Base URL"
                                                />
                                                <Input
                                                    value={formData.ai_ollama_model || 'llama3.1'}
                                                    onChange={(e) => update({ ai_ollama_model: e.target.value })}
                                                    placeholder="llama3.1"
                                                    label="Model"
                                                />
                                            </div>
                                        </div>

                                        {/* Shared settings */}
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Input
                                                type="number"
                                                value={formData.ai_max_tokens ?? 2048}
                                                onChange={(e) => update({ ai_max_tokens: parseInt(e.target.value) || 2048 })}
                                                label="Max Tokens"
                                                placeholder="2048"
                                                hint="Maximum response length"
                                            />
                                            <Input
                                                type="number"
                                                value={formData.ai_cache_ttl ?? 86400}
                                                onChange={(e) => update({ ai_cache_ttl: parseInt(e.target.value) || 86400 })}
                                                label="Cache TTL (seconds)"
                                                placeholder="86400"
                                                hint="How long to cache analysis results (86400 = 24h)"
                                            />
                                        </div>
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">Analysis Cache</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Cache AI analysis results to reduce API costs
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.ai_cache_enabled ?? true}
                                                onCheckedChange={(checked) =>
                                                    update({ ai_cache_enabled: checked === true })
                                                }
                                            />
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </TabsContent>

                        {/* ============ TAB 4: MONITORING ============ */}
                        <TabsContent>
                            <div className="space-y-6">
                                {/* Resource Monitoring */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <Activity className="h-5 w-5 text-primary" />
                                                <CardTitle>Resource Monitoring</CardTitle>
                                            </div>
                                            <Badge variant={formData.resource_monitoring_enabled ? 'success' : 'default'}>
                                                {formData.resource_monitoring_enabled ? 'Enabled' : 'Disabled'}
                                            </Badge>
                                        </div>
                                        <CardDescription>
                                            Configure thresholds for CPU, memory, and disk usage alerts
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">Enable Monitoring</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Periodically check server resource usage and send alerts
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.resource_monitoring_enabled || false}
                                                onCheckedChange={(checked) =>
                                                    update({ resource_monitoring_enabled: checked === true })
                                                }
                                            />
                                        </div>

                                        {formData.resource_monitoring_enabled && (
                                            <>
                                                <Input
                                                    type="number"
                                                    value={formData.resource_check_interval_minutes ?? 5}
                                                    onChange={(e) =>
                                                        update({ resource_check_interval_minutes: parseInt(e.target.value) || 5 })
                                                    }
                                                    label="Check Interval (minutes)"
                                                    hint="How often to check resource usage"
                                                    placeholder="5"
                                                />

                                                {/* CPU Thresholds */}
                                                <div className="rounded-lg border border-white/[0.06] p-4">
                                                    <p className="mb-3 font-medium text-foreground">CPU Thresholds (%)</p>
                                                    <div className="grid gap-4 sm:grid-cols-2">
                                                        <Input
                                                            type="number"
                                                            value={formData.resource_warning_cpu_threshold ?? 80}
                                                            onChange={(e) =>
                                                                update({ resource_warning_cpu_threshold: parseInt(e.target.value) || 80 })
                                                            }
                                                            label="Warning"
                                                            placeholder="80"
                                                            hint="Trigger warning notification"
                                                        />
                                                        <Input
                                                            type="number"
                                                            value={formData.resource_critical_cpu_threshold ?? 95}
                                                            onChange={(e) =>
                                                                update({ resource_critical_cpu_threshold: parseInt(e.target.value) || 95 })
                                                            }
                                                            label="Critical"
                                                            placeholder="95"
                                                            hint="Trigger critical alert"
                                                        />
                                                    </div>
                                                </div>

                                                {/* Memory Thresholds */}
                                                <div className="rounded-lg border border-white/[0.06] p-4">
                                                    <p className="mb-3 font-medium text-foreground">Memory Thresholds (%)</p>
                                                    <div className="grid gap-4 sm:grid-cols-2">
                                                        <Input
                                                            type="number"
                                                            value={formData.resource_warning_memory_threshold ?? 80}
                                                            onChange={(e) =>
                                                                update({ resource_warning_memory_threshold: parseInt(e.target.value) || 80 })
                                                            }
                                                            label="Warning"
                                                            placeholder="80"
                                                        />
                                                        <Input
                                                            type="number"
                                                            value={formData.resource_critical_memory_threshold ?? 95}
                                                            onChange={(e) =>
                                                                update({ resource_critical_memory_threshold: parseInt(e.target.value) || 95 })
                                                            }
                                                            label="Critical"
                                                            placeholder="95"
                                                        />
                                                    </div>
                                                </div>

                                                {/* Disk Thresholds */}
                                                <div className="rounded-lg border border-white/[0.06] p-4">
                                                    <p className="mb-3 font-medium text-foreground">Disk Thresholds (%)</p>
                                                    <div className="grid gap-4 sm:grid-cols-2">
                                                        <Input
                                                            type="number"
                                                            value={formData.resource_warning_disk_threshold ?? 80}
                                                            onChange={(e) =>
                                                                update({ resource_warning_disk_threshold: parseInt(e.target.value) || 80 })
                                                            }
                                                            label="Warning"
                                                            placeholder="80"
                                                        />
                                                        <Input
                                                            type="number"
                                                            value={formData.resource_critical_disk_threshold ?? 95}
                                                            onChange={(e) =>
                                                                update({ resource_critical_disk_threshold: parseInt(e.target.value) || 95 })
                                                            }
                                                            label="Critical"
                                                            placeholder="95"
                                                        />
                                                    </div>
                                                </div>
                                            </>
                                        )}
                                    </CardContent>
                                </Card>

                                {/* Sentinel */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <Shield className="h-5 w-5 text-primary" />
                                            <CardTitle>Sentinel</CardTitle>
                                        </div>
                                        <CardDescription>Global Sentinel monitoring agent token</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div>
                                            <label className="mb-1.5 block text-sm font-medium text-foreground">
                                                Sentinel Token
                                            </label>
                                            <div className="relative">
                                                <Input
                                                    type={showSentinelToken ? 'text' : 'password'}
                                                    value={formData.sentinel_token || ''}
                                                    onChange={(e) => update({ sentinel_token: e.target.value })}
                                                    placeholder="Enter sentinel token"
                                                    className="pr-10"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => setShowSentinelToken(!showSentinelToken)}
                                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-foreground-muted hover:text-foreground"
                                                >
                                                    {showSentinelToken ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                                </button>
                                            </div>
                                            <p className="mt-1.5 text-xs text-foreground-muted">
                                                Used to authenticate Sentinel agents on managed servers
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </TabsContent>

                        {/* ============ TAB 5: INFRASTRUCTURE ============ */}
                        <TabsContent>
                            <div className="space-y-6">
                                {/* Auto-Provisioning */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <Server className="h-5 w-5 text-primary" />
                                                <CardTitle>Auto-Provisioning</CardTitle>
                                            </div>
                                            <Badge variant={formData.auto_provision_enabled ? 'success' : 'default'}>
                                                {formData.auto_provision_enabled ? 'Enabled' : 'Disabled'}
                                            </Badge>
                                        </div>
                                        <CardDescription>
                                            Automatically provision new servers via cloud provider API
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">Enable Auto-Provisioning</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Allow automatic server creation when capacity is needed
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.auto_provision_enabled || false}
                                                onCheckedChange={(checked) =>
                                                    update({ auto_provision_enabled: checked === true })
                                                }
                                            />
                                        </div>

                                        {formData.auto_provision_enabled && (
                                            <>
                                                <div>
                                                    <label className="mb-1.5 block text-sm font-medium text-foreground">
                                                        Provider API Key
                                                    </label>
                                                    <div className="relative">
                                                        <Input
                                                            type={showProvisionKey ? 'text' : 'password'}
                                                            value={formData.auto_provision_api_key || ''}
                                                            onChange={(e) =>
                                                                update({ auto_provision_api_key: e.target.value })
                                                            }
                                                            placeholder="Enter provider API key"
                                                            className="pr-10"
                                                        />
                                                        <button
                                                            type="button"
                                                            onClick={() => setShowProvisionKey(!showProvisionKey)}
                                                            className="absolute right-3 top-1/2 -translate-y-1/2 text-foreground-muted hover:text-foreground"
                                                        >
                                                            {showProvisionKey ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                                        </button>
                                                    </div>
                                                </div>
                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <Input
                                                        type="number"
                                                        value={formData.auto_provision_max_servers_per_day ?? 5}
                                                        onChange={(e) =>
                                                            update({
                                                                auto_provision_max_servers_per_day:
                                                                    parseInt(e.target.value) || 5,
                                                            })
                                                        }
                                                        label="Max Servers Per Day"
                                                        hint="Safety limit for server creation"
                                                        placeholder="5"
                                                    />
                                                    <Input
                                                        type="number"
                                                        value={formData.auto_provision_cooldown_minutes ?? 10}
                                                        onChange={(e) =>
                                                            update({
                                                                auto_provision_cooldown_minutes:
                                                                    parseInt(e.target.value) || 10,
                                                            })
                                                        }
                                                        label="Cooldown (minutes)"
                                                        hint="Minimum wait time between provisioning"
                                                        placeholder="10"
                                                    />
                                                </div>
                                            </>
                                        )}
                                    </CardContent>
                                </Card>

                                {/* Global S3 Storage */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <Server className="h-5 w-5 text-primary" />
                                                <CardTitle>Global S3 Storage</CardTitle>
                                            </div>
                                            <Badge variant={formData.s3_enabled ? 'success' : 'default'}>
                                                {formData.s3_enabled ? 'Enabled' : 'Disabled'}
                                            </Badge>
                                        </div>
                                        <CardDescription>
                                            Platform-level S3-compatible storage for global backups and exports
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">Enable Global S3</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Use S3-compatible storage for platform backups
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.s3_enabled || false}
                                                onCheckedChange={(checked) =>
                                                    update({ s3_enabled: checked === true })
                                                }
                                            />
                                        </div>

                                        {formData.s3_enabled && (
                                            <>
                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <Input
                                                        value={formData.s3_endpoint || ''}
                                                        onChange={(e) => update({ s3_endpoint: e.target.value })}
                                                        placeholder="https://s3.amazonaws.com"
                                                        label="S3 Endpoint"
                                                        hint="AWS, MinIO, Cloudflare R2, etc."
                                                    />
                                                    <Input
                                                        value={formData.s3_region || ''}
                                                        onChange={(e) => update({ s3_region: e.target.value })}
                                                        placeholder="us-east-1"
                                                        label="Region"
                                                    />
                                                </div>
                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <Input
                                                        value={formData.s3_bucket || ''}
                                                        onChange={(e) => update({ s3_bucket: e.target.value })}
                                                        placeholder="saturn-backups"
                                                        label="Bucket Name"
                                                    />
                                                    <Input
                                                        value={formData.s3_path || ''}
                                                        onChange={(e) => update({ s3_path: e.target.value })}
                                                        placeholder="/backups"
                                                        label="Path Prefix"
                                                        hint="Optional path prefix for all files"
                                                    />
                                                </div>
                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <div>
                                                        <label className="mb-1.5 block text-sm font-medium text-foreground">
                                                            Access Key
                                                        </label>
                                                        <div className="relative">
                                                            <Input
                                                                type={showS3Key ? 'text' : 'password'}
                                                                value={formData.s3_key || ''}
                                                                onChange={(e) => update({ s3_key: e.target.value })}
                                                                placeholder="AKIAIOSFODNN7EXAMPLE"
                                                                className="pr-10"
                                                            />
                                                            <button
                                                                type="button"
                                                                onClick={() => setShowS3Key(!showS3Key)}
                                                                className="absolute right-3 top-1/2 -translate-y-1/2 text-foreground-muted hover:text-foreground"
                                                            >
                                                                {showS3Key ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <label className="mb-1.5 block text-sm font-medium text-foreground">
                                                            Secret Key
                                                        </label>
                                                        <div className="relative">
                                                            <Input
                                                                type={showS3Secret ? 'text' : 'password'}
                                                                value={formData.s3_secret || ''}
                                                                onChange={(e) => update({ s3_secret: e.target.value })}
                                                                placeholder="wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY"
                                                                className="pr-10"
                                                            />
                                                            <button
                                                                type="button"
                                                                onClick={() => setShowS3Secret(!showS3Secret)}
                                                                className="absolute right-3 top-1/2 -translate-y-1/2 text-foreground-muted hover:text-foreground"
                                                            >
                                                                {showS3Secret ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </>
                                        )}
                                    </CardContent>
                                </Card>

                                {/* SSH Configuration */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <Terminal className="h-5 w-5 text-primary" />
                                                <CardTitle>SSH Configuration</CardTitle>
                                            </div>
                                            <Badge variant={formData.ssh_mux_enabled ? 'success' : 'default'}>
                                                {formData.ssh_mux_enabled ? 'Mux Enabled' : 'Mux Disabled'}
                                            </Badge>
                                        </div>
                                        <CardDescription>
                                            SSH multiplexing and connection tuning for remote server communication
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">SSH Multiplexing</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Reuse SSH connections for faster command execution
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.ssh_mux_enabled ?? true}
                                                onCheckedChange={(checked) =>
                                                    update({ ssh_mux_enabled: checked === true })
                                                }
                                            />
                                        </div>

                                        {formData.ssh_mux_enabled && (
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <Input
                                                    type="number"
                                                    value={formData.ssh_mux_persist_time ?? 1800}
                                                    onChange={(e) =>
                                                        update({ ssh_mux_persist_time: parseInt(e.target.value) || 1800 })
                                                    }
                                                    label="Mux Persist Time (sec)"
                                                    hint="Keep connection open after last use (60-86400)"
                                                    placeholder="1800"
                                                />
                                                <Input
                                                    type="number"
                                                    value={formData.ssh_mux_max_age ?? 3600}
                                                    onChange={(e) =>
                                                        update({ ssh_mux_max_age: parseInt(e.target.value) || 3600 })
                                                    }
                                                    label="Mux Max Age (sec)"
                                                    hint="Maximum total age before cleanup (60-86400)"
                                                    placeholder="3600"
                                                />
                                            </div>
                                        )}

                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Input
                                                type="number"
                                                value={formData.ssh_connection_timeout ?? 30}
                                                onChange={(e) =>
                                                    update({ ssh_connection_timeout: parseInt(e.target.value) || 30 })
                                                }
                                                label="Connection Timeout (sec)"
                                                hint="Timeout for establishing SSH connection (5-300)"
                                                placeholder="30"
                                            />
                                            <Input
                                                type="number"
                                                value={formData.ssh_command_timeout ?? 3600}
                                                onChange={(e) =>
                                                    update({ ssh_command_timeout: parseInt(e.target.value) || 3600 })
                                                }
                                                label="Command Timeout (sec)"
                                                hint="Max execution time for remote commands (60-86400)"
                                                placeholder="3600"
                                            />
                                        </div>

                                        <div className="grid gap-4 sm:grid-cols-3">
                                            <Input
                                                type="number"
                                                value={formData.ssh_max_retries ?? 3}
                                                onChange={(e) =>
                                                    update({ ssh_max_retries: parseInt(e.target.value) || 3 })
                                                }
                                                label="Max Retries"
                                                hint="Retry attempts on failure (0-10)"
                                                placeholder="3"
                                            />
                                            <Input
                                                type="number"
                                                value={formData.ssh_retry_base_delay ?? 2}
                                                onChange={(e) =>
                                                    update({ ssh_retry_base_delay: parseInt(e.target.value) || 2 })
                                                }
                                                label="Retry Base Delay (sec)"
                                                hint="Initial delay between retries (1-60)"
                                                placeholder="2"
                                            />
                                            <Input
                                                type="number"
                                                value={formData.ssh_retry_max_delay ?? 30}
                                                onChange={(e) =>
                                                    update({ ssh_retry_max_delay: parseInt(e.target.value) || 30 })
                                                }
                                                label="Retry Max Delay (sec)"
                                                hint="Maximum delay between retries (1-300)"
                                                placeholder="30"
                                            />
                                        </div>

                                        <div className="rounded-lg border border-blue-500/20 bg-blue-500/5 p-3">
                                            <p className="text-sm text-foreground-muted">
                                                Changes take effect after queue worker restart.
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Docker Registry */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <Container className="h-5 w-5 text-primary" />
                                            <CardTitle>Docker Registry</CardTitle>
                                        </div>
                                        <CardDescription>
                                            Default Docker registry for pulling helper and realtime images
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <Input
                                            value={formData.docker_registry_url || ''}
                                            onChange={(e) => update({ docker_registry_url: e.target.value })}
                                            placeholder="ghcr.io"
                                            label="Registry URL"
                                            hint="Docker registry URL for helper image pulls"
                                        />
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <label className="mb-1.5 block text-sm font-medium text-foreground">
                                                    Username
                                                </label>
                                                <div className="relative">
                                                    <Input
                                                        type={showDockerUsername ? 'text' : 'password'}
                                                        value={formData.docker_registry_username || ''}
                                                        onChange={(e) => update({ docker_registry_username: e.target.value })}
                                                        placeholder="Optional"
                                                        className="pr-10"
                                                    />
                                                    <button
                                                        type="button"
                                                        onClick={() => setShowDockerUsername(!showDockerUsername)}
                                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-foreground-muted hover:text-foreground"
                                                    >
                                                        {showDockerUsername ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                                    </button>
                                                </div>
                                            </div>
                                            <div>
                                                <label className="mb-1.5 block text-sm font-medium text-foreground">
                                                    Password
                                                </label>
                                                <div className="relative">
                                                    <Input
                                                        type={showDockerPassword ? 'text' : 'password'}
                                                        value={formData.docker_registry_password || ''}
                                                        onChange={(e) => update({ docker_registry_password: e.target.value })}
                                                        placeholder="Optional"
                                                        className="pr-10"
                                                    />
                                                    <button
                                                        type="button"
                                                        onClick={() => setShowDockerPassword(!showDockerPassword)}
                                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-foreground-muted hover:text-foreground"
                                                    >
                                                        {showDockerPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="rounded-lg border border-yellow-500/20 bg-yellow-500/5 p-3">
                                            <p className="text-sm text-foreground-muted">
                                                Changing the registry URL affects helper image pulls for all servers.
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Default Proxy Type */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <Network className="h-5 w-5 text-primary" />
                                            <CardTitle>Default Proxy Type</CardTitle>
                                        </div>
                                        <CardDescription>
                                            Default reverse proxy for newly created servers
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <Select
                                            value={formData.default_proxy_type || 'TRAEFIK'}
                                            onChange={(e) => update({ default_proxy_type: e.target.value })}
                                            label="Proxy Type"
                                            options={[
                                                { value: 'TRAEFIK', label: 'Traefik' },
                                                { value: 'CADDY', label: 'Caddy' },
                                                { value: 'NONE', label: 'None' },
                                            ]}
                                        />
                                        <p className="text-sm text-foreground-muted">
                                            {formData.default_proxy_type === 'TRAEFIK' && 'Traefik — full-featured reverse proxy with automatic SSL, dashboard, and middleware support.'}
                                            {formData.default_proxy_type === 'CADDY' && 'Caddy — simple reverse proxy with automatic HTTPS and minimal configuration.'}
                                            {formData.default_proxy_type === 'NONE' && 'No proxy — servers will not have a reverse proxy configured by default.'}
                                            {!formData.default_proxy_type && 'Traefik — full-featured reverse proxy with automatic SSL, dashboard, and middleware support.'}
                                        </p>
                                    </CardContent>
                                </Card>

                                {/* API Rate Limiting */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <Gauge className="h-5 w-5 text-primary" />
                                            <CardTitle>API Rate Limiting</CardTitle>
                                        </div>
                                        <CardDescription>
                                            Control API request limits per user or IP address
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <Input
                                            type="number"
                                            value={formData.api_rate_limit ?? 200}
                                            onChange={(e) =>
                                                update({ api_rate_limit: parseInt(e.target.value) || 200 })
                                            }
                                            label="Requests Per Minute"
                                            hint="10-10000 requests per minute per user/IP"
                                            placeholder="200"
                                        />
                                        <div className="rounded-lg border border-blue-500/20 bg-blue-500/5 p-3">
                                            <p className="text-sm text-foreground-muted">
                                                Rate limit applies per authenticated user or IP. Changes apply immediately.
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Queue & Horizon Workers */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <Layers className="h-5 w-5 text-primary" />
                                            <CardTitle>Queue & Horizon Workers</CardTitle>
                                        </div>
                                        <CardDescription>
                                            Configure background job processing, worker scaling, and retention
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-6">
                                        {/* Workers section */}
                                        <div className="space-y-4">
                                            <h4 className="text-sm font-medium text-foreground">Workers</h4>
                                            <Select
                                                value={formData.horizon_balance || 'false'}
                                                onChange={(e) => update({ horizon_balance: e.target.value })}
                                                label="Balance Strategy"
                                                options={[
                                                    { value: 'false', label: 'None (fixed processes)' },
                                                    { value: 'simple', label: 'Simple (equal distribution)' },
                                                    { value: 'auto', label: 'Auto (scale by workload)' },
                                                ]}
                                            />
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <Input
                                                    type="number"
                                                    value={formData.horizon_min_processes ?? 1}
                                                    onChange={(e) =>
                                                        update({ horizon_min_processes: parseInt(e.target.value) || 1 })
                                                    }
                                                    label="Min Processes"
                                                    hint="Minimum worker processes (1-20)"
                                                    placeholder="1"
                                                />
                                                <Input
                                                    type="number"
                                                    value={formData.horizon_max_processes ?? 4}
                                                    onChange={(e) =>
                                                        update({ horizon_max_processes: parseInt(e.target.value) || 4 })
                                                    }
                                                    label="Max Processes"
                                                    hint="Maximum worker processes (1-50)"
                                                    placeholder="4"
                                                />
                                            </div>
                                            <div className="grid gap-4 sm:grid-cols-3">
                                                <Input
                                                    type="number"
                                                    value={formData.horizon_worker_memory ?? 128}
                                                    onChange={(e) =>
                                                        update({ horizon_worker_memory: parseInt(e.target.value) || 128 })
                                                    }
                                                    label="Memory (MB)"
                                                    hint="Worker memory limit (64-2048)"
                                                    placeholder="128"
                                                />
                                                <Input
                                                    type="number"
                                                    value={formData.horizon_worker_timeout ?? 3600}
                                                    onChange={(e) =>
                                                        update({ horizon_worker_timeout: parseInt(e.target.value) || 3600 })
                                                    }
                                                    label="Timeout (sec)"
                                                    hint="Max job execution time (60-86400)"
                                                    placeholder="3600"
                                                />
                                                <Input
                                                    type="number"
                                                    value={formData.horizon_max_jobs ?? 400}
                                                    onChange={(e) =>
                                                        update({ horizon_max_jobs: parseInt(e.target.value) || 400 })
                                                    }
                                                    label="Max Jobs"
                                                    hint="Jobs before worker restart (10-10000)"
                                                    placeholder="400"
                                                />
                                            </div>
                                        </div>

                                        {/* Retention section */}
                                        <div className="space-y-4">
                                            <h4 className="text-sm font-medium text-foreground">Retention</h4>
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <Input
                                                    type="number"
                                                    value={formData.horizon_trim_recent_minutes ?? 60}
                                                    onChange={(e) =>
                                                        update({ horizon_trim_recent_minutes: parseInt(e.target.value) || 60 })
                                                    }
                                                    label="Recent Jobs (min)"
                                                    hint="Keep completed jobs for (10-10080 min)"
                                                    placeholder="60"
                                                />
                                                <Input
                                                    type="number"
                                                    value={formData.horizon_trim_failed_minutes ?? 10080}
                                                    onChange={(e) =>
                                                        update({ horizon_trim_failed_minutes: parseInt(e.target.value) || 10080 })
                                                    }
                                                    label="Failed Jobs (min)"
                                                    hint="Keep failed jobs for (60-43200 min)"
                                                    placeholder="10080"
                                                />
                                            </div>
                                        </div>

                                        {/* Monitoring section */}
                                        <div className="space-y-4">
                                            <h4 className="text-sm font-medium text-foreground">Monitoring</h4>
                                            <Input
                                                type="number"
                                                value={formData.horizon_queue_wait_threshold ?? 60}
                                                onChange={(e) =>
                                                    update({ horizon_queue_wait_threshold: parseInt(e.target.value) || 60 })
                                                }
                                                label="Queue Wait Threshold (sec)"
                                                hint="Alert when jobs wait longer than this (10-600 sec)"
                                                placeholder="60"
                                            />
                                        </div>

                                        <div className="rounded-lg border border-yellow-500/20 bg-yellow-500/5 p-3">
                                            <p className="text-sm text-foreground-muted">
                                                Worker and retention changes require Horizon restart to take effect.
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </TabsContent>

                        {/* ============ TAB 6: APP DEFAULTS ============ */}
                        <TabsContent>
                            <div className="space-y-6">
                                <div className="rounded-lg border border-blue-500/20 bg-blue-500/5 p-4">
                                    <p className="text-sm text-foreground-muted">
                                        These defaults are applied when creating new applications. Existing applications are not affected.
                                    </p>
                                </div>

                                {/* Deployment Defaults */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <Rocket className="h-5 w-5 text-primary" />
                                            <CardTitle>Deployment Defaults</CardTitle>
                                        </div>
                                        <CardDescription>Default deployment behavior for new applications</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">Auto Deploy</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Automatically deploy on git push
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.app_default_auto_deploy ?? true}
                                                onCheckedChange={(checked) =>
                                                    update({ app_default_auto_deploy: checked === true })
                                                }
                                            />
                                        </div>
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">Force HTTPS</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Redirect HTTP to HTTPS
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.app_default_force_https ?? true}
                                                onCheckedChange={(checked) =>
                                                    update({ app_default_force_https: checked === true })
                                                }
                                            />
                                        </div>
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">Preview Deployments</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Enable preview deployments for pull requests
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.app_default_preview_deployments ?? false}
                                                onCheckedChange={(checked) =>
                                                    update({ app_default_preview_deployments: checked === true })
                                                }
                                            />
                                        </div>
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">PR Deployments Public</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Make pull request deployments publicly accessible
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.app_default_pr_deployments_public ?? false}
                                                onCheckedChange={(checked) =>
                                                    update({ app_default_pr_deployments_public: checked === true })
                                                }
                                            />
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Build Pack & Timeout */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <Clock className="h-5 w-5 text-primary" />
                                            <CardTitle>Build Pack & Timeout</CardTitle>
                                        </div>
                                        <CardDescription>Default build pack and timeout for new applications</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <Select
                                            value={formData.app_default_build_pack ?? 'nixpacks'}
                                            onChange={(e) => update({ app_default_build_pack: e.target.value })}
                                            label="Default Build Pack"
                                            hint={
                                                ({
                                                    nixpacks: 'Auto-detect language and build with Nixpacks (recommended)',
                                                    static: 'Serve static files with a web server',
                                                    dockerfile: 'Build from a Dockerfile in the repository',
                                                    dockercompose: 'Use Docker Compose for multi-container apps',
                                                } as Record<string, string>)[formData.app_default_build_pack ?? 'nixpacks'] ?? 'Select a build pack'
                                            }
                                            options={[
                                                { value: 'nixpacks', label: 'Nixpacks' },
                                                { value: 'static', label: 'Static' },
                                                { value: 'dockerfile', label: 'Dockerfile' },
                                                { value: 'dockercompose', label: 'Docker Compose' },
                                            ]}
                                        />
                                        <Input
                                            type="number"
                                            value={formData.app_default_build_timeout ?? 3600}
                                            onChange={(e) =>
                                                update({ app_default_build_timeout: parseInt(e.target.value) || 3600 })
                                            }
                                            label="Build Timeout (seconds)"
                                            hint="Max time for build/deploy process (60-86400)"
                                            placeholder="3600"
                                        />
                                        {formData.app_default_build_pack === 'static' && (
                                            <Input
                                                value={formData.app_default_static_image ?? 'nginx:alpine'}
                                                onChange={(e) => update({ app_default_static_image: e.target.value })}
                                                label="Static Image"
                                                hint="Docker image to serve static files"
                                                placeholder="nginx:alpine"
                                            />
                                        )}
                                    </CardContent>
                                </Card>

                                {/* Build Defaults */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <Hammer className="h-5 w-5 text-primary" />
                                            <CardTitle>Build Defaults</CardTitle>
                                        </div>
                                        <CardDescription>Default build configuration for new applications</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">Git Submodules</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Enable git submodules during clone
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.app_default_git_submodules ?? true}
                                                onCheckedChange={(checked) =>
                                                    update({ app_default_git_submodules: checked === true })
                                                }
                                            />
                                        </div>
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">Git LFS</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Enable Git Large File Storage
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.app_default_git_lfs ?? true}
                                                onCheckedChange={(checked) =>
                                                    update({ app_default_git_lfs: checked === true })
                                                }
                                            />
                                        </div>
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">Shallow Clone</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Use shallow clone for faster builds
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.app_default_git_shallow_clone ?? true}
                                                onCheckedChange={(checked) =>
                                                    update({ app_default_git_shallow_clone: checked === true })
                                                }
                                            />
                                        </div>
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">Use Build Secrets</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Expose secrets during build phase
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.app_default_use_build_secrets ?? false}
                                                onCheckedChange={(checked) =>
                                                    update({ app_default_use_build_secrets: checked === true })
                                                }
                                            />
                                        </div>
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">Inject Build Args</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Inject build arguments to Dockerfile
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.app_default_inject_build_args ?? true}
                                                onCheckedChange={(checked) =>
                                                    update({ app_default_inject_build_args: checked === true })
                                                }
                                            />
                                        </div>
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">Include Commit SHA</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Include source commit SHA in build metadata
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.app_default_include_commit_in_build ?? false}
                                                onCheckedChange={(checked) =>
                                                    update({ app_default_include_commit_in_build: checked === true })
                                                }
                                            />
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Docker & Retention */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <Container className="h-5 w-5 text-primary" />
                                            <CardTitle>Docker & Debug</CardTitle>
                                        </div>
                                        <CardDescription>Docker image retention and debug settings</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <Input
                                            type="number"
                                            value={formData.app_default_docker_images_to_keep ?? 2}
                                            onChange={(e) =>
                                                update({ app_default_docker_images_to_keep: parseInt(e.target.value) || 2 })
                                            }
                                            label="Docker Images to Keep"
                                            hint="Number of Docker images to retain per application (1-50)"
                                            placeholder="2"
                                        />
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <Bug className="h-4 w-4 text-yellow-500" />
                                                    <p className="font-medium text-foreground">Debug Mode</p>
                                                </div>
                                                <p className="text-sm text-foreground-muted">
                                                    Enable debug mode for new applications
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.app_default_debug ?? false}
                                                onCheckedChange={(checked) =>
                                                    update({ app_default_debug: checked === true })
                                                }
                                            />
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Auto-Rollback Defaults */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <Undo2 className="h-5 w-5 text-primary" />
                                            <CardTitle>Auto-Rollback Defaults</CardTitle>
                                        </div>
                                        <CardDescription>Default auto-rollback settings for new applications</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">Enable Auto-Rollback</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Automatically rollback failed deployments
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.app_default_auto_rollback ?? false}
                                                onCheckedChange={(checked) =>
                                                    update({ app_default_auto_rollback: checked === true })
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Input
                                                type="number"
                                                value={formData.app_default_rollback_validation_sec ?? 300}
                                                onChange={(e) =>
                                                    update({ app_default_rollback_validation_sec: parseInt(e.target.value) || 300 })
                                                }
                                                label="Validation Period (seconds)"
                                                hint="Time to validate deployment health before confirming (10-3600)"
                                                placeholder="300"
                                            />
                                            <Input
                                                type="number"
                                                value={formData.app_default_rollback_max_restarts ?? 3}
                                                onChange={(e) =>
                                                    update({ app_default_rollback_max_restarts: parseInt(e.target.value) || 3 })
                                                }
                                                label="Max Restarts"
                                                hint="Maximum container restarts before triggering rollback (1-20)"
                                                placeholder="3"
                                            />
                                        </div>
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">Rollback on Health Check Failure</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Trigger rollback when healthcheck fails
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.app_default_rollback_on_health_fail ?? true}
                                                onCheckedChange={(checked) =>
                                                    update({ app_default_rollback_on_health_fail: checked === true })
                                                }
                                            />
                                        </div>
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">Rollback on Crash Loop</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Trigger rollback when container enters crash loop
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.app_default_rollback_on_crash_loop ?? true}
                                                onCheckedChange={(checked) =>
                                                    update({ app_default_rollback_on_crash_loop: checked === true })
                                                }
                                            />
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Deployment Policy */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <ShieldCheck className="h-5 w-5 text-primary" />
                                            <CardTitle>Deployment Policy</CardTitle>
                                        </div>
                                        <CardDescription>Deployment approval enforcement for new environments</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="flex items-center justify-between rounded-lg border border-white/[0.06] p-4">
                                            <div>
                                                <p className="font-medium text-foreground">Require Deployment Approval</p>
                                                <p className="text-sm text-foreground-muted">
                                                    Require manual approval before deploying to production environments
                                                </p>
                                            </div>
                                            <Checkbox
                                                checked={formData.app_default_requires_approval ?? false}
                                                onCheckedChange={(checked) =>
                                                    update({ app_default_requires_approval: checked === true })
                                                }
                                            />
                                        </div>
                                        <div className="rounded-lg border border-blue-500/20 bg-blue-500/5 p-3">
                                            <p className="text-sm text-blue-400">
                                                When enabled, all new environments will require deployment approval by default.
                                                This can be overridden per environment.
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </TabsContent>
                        {/* ============ TAB 7: IP PROTECTION ============ */}
                        <TabsContent>
                            <div className="space-y-6">
                                {/* Status Card */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <ShieldCheck className="h-5 w-5 text-primary" />
                                            <CardTitle>Cloudflare Tunnel Protection</CardTitle>
                                        </div>
                                        <CardDescription>
                                            Hide your master server IP behind Cloudflare Tunnel for DDoS protection. Routes are auto-synced on every deployment.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="flex items-center gap-3">
                                            <span className="text-sm font-medium text-foreground">Status:</span>
                                            {formData.is_cloudflare_protection_enabled && formData.cloudflare_tunnel_id ? (
                                                <Badge variant="success">Active</Badge>
                                            ) : formData.cloudflare_api_token && formData.cloudflare_api_token !== '' ? (
                                                <Badge variant="warning">Configured (Tunnel not initialized)</Badge>
                                            ) : (
                                                <Badge variant="secondary">Not Configured</Badge>
                                            )}
                                        </div>
                                        {formData.cloudflare_tunnel_id && (
                                            <div className="mt-3 space-y-1 text-sm text-foreground-muted">
                                                <p>Tunnel ID: <code className="rounded bg-white/5 px-1.5 py-0.5 font-mono text-xs">{formData.cloudflare_tunnel_id}</code></p>
                                                {formData.cloudflare_last_synced_at && (
                                                    <p>Last synced: {new Date(formData.cloudflare_last_synced_at).toLocaleString()}</p>
                                                )}
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>

                                {/* Credentials Form */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <Cloud className="h-5 w-5 text-primary" />
                                            <CardTitle>Cloudflare Credentials</CardTitle>
                                        </div>
                                        <CardDescription>
                                            Enter your Cloudflare API Token, Account ID, and Zone ID. The API token needs permissions: Cloudflare Tunnel (Edit), DNS (Edit), Account Settings (Read).
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="relative">
                                            <Input
                                                type={showCloudflareToken ? 'text' : 'password'}
                                                value={formData.cloudflare_api_token || ''}
                                                onChange={(e) => update({ cloudflare_api_token: e.target.value })}
                                                placeholder="Enter Cloudflare API Token"
                                                label="API Token"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => setShowCloudflareToken(!showCloudflareToken)}
                                                className="absolute right-3 top-9 text-foreground-muted hover:text-foreground"
                                            >
                                                {showCloudflareToken ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                            </button>
                                        </div>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Input
                                                value={formData.cloudflare_account_id || ''}
                                                onChange={(e) => update({ cloudflare_account_id: e.target.value })}
                                                placeholder="e.g. a1b2c3d4e5f6..."
                                                label="Account ID"
                                                hint="Found in Cloudflare Dashboard → Overview → API section"
                                            />
                                            <Input
                                                value={formData.cloudflare_zone_id || ''}
                                                onChange={(e) => update({ cloudflare_zone_id: e.target.value })}
                                                placeholder="e.g. f6e5d4c3b2a1..."
                                                label="Zone ID"
                                                hint="Found in Cloudflare Dashboard → Your domain → Overview → API section"
                                            />
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Actions */}
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <Zap className="h-5 w-5 text-primary" />
                                            <CardTitle>Tunnel Actions</CardTitle>
                                        </div>
                                        <CardDescription>
                                            Initialize, sync, or destroy the Cloudflare Tunnel. Save credentials first before initializing.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="flex flex-wrap gap-3">
                                            {!formData.cloudflare_tunnel_id ? (
                                                <Button
                                                    onClick={() => {
                                                        setIsCloudflareAction(true);
                                                        router.post('/admin/settings/cloudflare/initialize', {}, {
                                                            preserveScroll: true,
                                                            onFinish: () => setIsCloudflareAction(false),
                                                        });
                                                    }}
                                                    disabled={isCloudflareAction || !formData.cloudflare_api_token || formData.cloudflare_api_token === '••••••••' && !formData.cloudflare_account_id}
                                                >
                                                    <Cloud className="h-4 w-4" />
                                                    {isCloudflareAction ? 'Initializing...' : 'Initialize Tunnel'}
                                                </Button>
                                            ) : (
                                                <>
                                                    <Button
                                                        variant="secondary"
                                                        onClick={() => {
                                                            setIsCloudflareAction(true);
                                                            router.post('/admin/settings/cloudflare/sync', {}, {
                                                                preserveScroll: true,
                                                                onFinish: () => setIsCloudflareAction(false),
                                                            });
                                                        }}
                                                        disabled={isCloudflareAction}
                                                    >
                                                        <RefreshCw className="h-4 w-4" />
                                                        {isCloudflareAction ? 'Syncing...' : 'Force Sync Routes'}
                                                    </Button>
                                                    <Button
                                                        variant="destructive"
                                                        onClick={async () => {
                                                            const confirmed = await confirm({
                                                                title: 'Destroy Cloudflare Tunnel',
                                                                description: 'This will remove the Cloudflare Tunnel and cloudflared container. Your server IP will no longer be protected. Are you sure?',
                                                                confirmText: 'Destroy',
                                                                variant: 'danger',
                                                            });
                                                            if (confirmed) {
                                                                setIsCloudflareAction(true);
                                                                router.post('/admin/settings/cloudflare/destroy', {}, {
                                                                    preserveScroll: true,
                                                                    onFinish: () => setIsCloudflareAction(false),
                                                                });
                                                            }
                                                        }}
                                                        disabled={isCloudflareAction}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                        Destroy Tunnel
                                                    </Button>
                                                </>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </TabsContent>
                    </TabsPanels>
                </TabsRoot>
            </div>
        </AdminLayout>
    );
}
