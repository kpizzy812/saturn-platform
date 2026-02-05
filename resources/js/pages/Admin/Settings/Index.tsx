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
                                <Card variant="glass">
                                    <CardHeader>
                                        <div className="flex items-center gap-2">
                                            <Brain className="h-5 w-5 text-primary" />
                                            <CardTitle>AI Features</CardTitle>
                                        </div>
                                        <CardDescription>
                                            AI-powered analysis requires ANTHROPIC_API_KEY or OPENAI_API_KEY in environment
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
                            </div>
                        </TabsContent>
                    </TabsPanels>
                </TabsRoot>
            </div>
        </AdminLayout>
    );
}
