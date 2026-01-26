import { useState } from 'react';
import { Badge, Button } from '@/components/ui';
import { Globe, Users, ChevronDown, Plus, Copy, ExternalLink, Trash2, Link2, Terminal, Shield } from 'lucide-react';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem } from '@/components/ui/Dropdown';
import type { SelectedService } from '../../types';

interface AppSettingsTabProps {
    service: SelectedService;
}

export function AppSettingsTab({ service }: AppSettingsTabProps) {
    const [cronEnabled, setCronEnabled] = useState(false);
    const [cronExpression, setCronExpression] = useState('0 * * * *');
    const [healthCheckEnabled, setHealthCheckEnabled] = useState(true);
    const [healthEndpoint, setHealthEndpoint] = useState('/health');
    const [healthTimeout, setHealthTimeout] = useState(10);
    const [healthInterval, setHealthInterval] = useState(30);
    const [replicas, setReplicas] = useState(1);
    const [isSaving, setIsSaving] = useState(false);

    const handleReplicasChange = async (newReplicas: number) => {
        if (newReplicas < 1) return;
        setReplicas(newReplicas);
    };

    const handleSaveSettings = async () => {
        if (!service.uuid) {
            alert('Cannot save: service UUID not available');
            return;
        }

        setIsSaving(true);
        try {
            const response = await fetch(`/api/v1/applications/${service.uuid}`, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    health_check_enabled: healthCheckEnabled,
                    health_check_path: healthEndpoint,
                    health_check_timeout: healthTimeout,
                    health_check_interval: healthInterval,
                }),
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to save settings');
            }

            alert('Settings saved successfully');
        } catch (err) {
            console.error('Save settings error:', err);
            alert(err instanceof Error ? err.message : 'Failed to save settings');
        } finally {
            setIsSaving(false);
        }
    };

    return (
        <div className="space-y-6">
            {/* Source Section */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Source</h3>
                <div className="rounded-lg border border-border bg-background-secondary p-3">
                    <div className="flex items-center gap-3">
                        <div className="flex h-8 w-8 items-center justify-center rounded bg-[#24292e]">
                            <svg viewBox="0 0 24 24" className="h-5 w-5" fill="#fff">
                                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                            </svg>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-foreground">GitHub Repository</p>
                            <p className="text-xs text-foreground-muted">saturn/api-server • main</p>
                        </div>
                    </div>
                </div>
            </div>

            {/* Build Configuration */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Build</h3>
                <div className="space-y-2">
                    <div className="rounded-lg border border-border bg-background-secondary p-3">
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-foreground-muted">Builder</span>
                            <span className="text-sm text-foreground">Dockerfile</span>
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-background-secondary p-3">
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-foreground-muted">Root Directory</span>
                            <span className="text-sm text-foreground">/</span>
                        </div>
                    </div>
                </div>
            </div>

            {/* Networking Section - Railway Style */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Networking</h3>
                <div className="space-y-4">
                    {/* Public Networking */}
                    <div className="rounded-lg border border-border bg-background-secondary p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Globe className="h-4 w-4 text-primary" />
                                <span className="text-sm font-medium text-foreground">Public Networking</span>
                            </div>
                            <Badge variant="success" size="sm">Enabled</Badge>
                        </div>

                        {/* Railway-provided domain */}
                        <div className="mb-3">
                            <p className="mb-1 text-xs text-foreground-muted">Railway-provided domain</p>
                            <div className="flex items-center gap-2">
                                <code className="flex-1 rounded bg-background px-2 py-1 text-sm text-foreground">
                                    {service.name || 'api-server'}-production.up.railway.app
                                </code>
                                <button
                                    onClick={() => {
                                        navigator.clipboard.writeText(`${service.name || 'api-server'}-production.up.railway.app`);
                                        alert('Domain copied to clipboard');
                                    }}
                                    className="rounded p-1 text-foreground-muted hover:bg-background hover:text-foreground"
                                    title="Copy domain"
                                >
                                    <Copy className="h-4 w-4" />
                                </button>
                                <a href={`https://${service.name || 'api-server'}-production.up.railway.app`} target="_blank" rel="noopener noreferrer" className="rounded p-1 text-foreground-muted hover:bg-background hover:text-foreground">
                                    <ExternalLink className="h-4 w-4" />
                                </a>
                            </div>
                        </div>

                        {/* Custom domains */}
                        <div>
                            <p className="mb-2 text-xs text-foreground-muted">Custom domains</p>
                            {service.fqdn && (
                                <div className="mb-2 flex items-center gap-2">
                                    <div className="h-2 w-2 rounded-full bg-green-500" />
                                    <code className="flex-1 text-sm text-foreground">{service.fqdn}</code>
                                    <Badge variant="success" size="sm">SSL</Badge>
                                    <button
                                        onClick={() => {
                                            if (window.confirm(`Delete domain ${service.fqdn}?`)) {
                                                alert('Domain deletion coming soon. Use the Settings page for now.');
                                            }
                                        }}
                                        className="rounded p-1 text-foreground-muted hover:bg-background hover:text-red-500"
                                        title="Delete domain"
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            )}
                            <Button
                                variant="secondary"
                                size="sm"
                                className="mt-2"
                                onClick={() => alert('Add Domain modal coming soon. Use the Settings page for now.')}
                            >
                                <Plus className="mr-1 h-3.5 w-3.5" />
                                Add Custom Domain
                            </Button>
                        </div>
                    </div>

                    {/* Port Configuration */}
                    <div className="rounded-lg border border-border bg-background-secondary p-4">
                        <div className="mb-3 flex items-center gap-2">
                            <Link2 className="h-4 w-4 text-foreground-muted" />
                            <span className="text-sm font-medium text-foreground">Port Configuration</span>
                        </div>
                        <div className="space-y-2">
                            <div className="flex items-center justify-between rounded bg-background p-2">
                                <div className="flex items-center gap-2">
                                    <span className="text-sm text-foreground">PORT</span>
                                    <span className="text-sm text-foreground-muted">→</span>
                                    <code className="text-sm font-medium text-primary">3000</code>
                                </div>
                                <Badge variant="default" size="sm">HTTP</Badge>
                            </div>
                        </div>
                        <p className="mt-2 text-xs text-foreground-muted">
                            Railway automatically detects your app's port from the PORT environment variable.
                        </p>
                    </div>

                    {/* TCP Proxy */}
                    <div className="rounded-lg border border-border bg-background-secondary p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Terminal className="h-4 w-4 text-foreground-muted" />
                                <span className="text-sm font-medium text-foreground">TCP Proxy</span>
                            </div>
                            <button className="relative h-5 w-9 rounded-full bg-background-tertiary transition-colors">
                                <span className="absolute left-0.5 top-0.5 h-4 w-4 rounded-full bg-white transition-all" />
                            </button>
                        </div>
                        <p className="text-xs text-foreground-muted">
                            Enable TCP proxy for non-HTTP services (SSH, databases, etc.). This exposes a public port.
                        </p>
                        <div className="mt-3 rounded bg-background p-3 text-sm text-foreground-muted">
                            <p className="font-mono">Proxy URL: <span className="text-foreground">your-service.proxy.rlwy.net:12345</span></p>
                        </div>
                    </div>

                    {/* Private Networking */}
                    <div className="rounded-lg border border-border bg-background-secondary p-4">
                        <div className="mb-3 flex items-center gap-2">
                            <Shield className="h-4 w-4 text-emerald-500" />
                            <span className="text-sm font-medium text-foreground">Private Networking</span>
                        </div>
                        <p className="mb-3 text-xs text-foreground-muted">
                            Use internal DNS for service-to-service communication. No egress charges.
                        </p>
                        <div className="rounded bg-background p-3">
                            <p className="mb-1 text-xs text-foreground-muted">Internal DNS</p>
                            <code className="text-sm text-foreground">{service.name || 'api-server'}.railway.internal</code>
                        </div>
                        <div className="mt-2 rounded bg-background p-3">
                            <p className="mb-1 text-xs text-foreground-muted">Internal Port</p>
                            <code className="text-sm text-foreground">3000</code>
                        </div>
                    </div>
                </div>
            </div>

            {/* Regions & Scaling */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Regions & Scaling</h3>
                <div className="space-y-2">
                    <div className="rounded-lg border border-border bg-background-secondary p-3">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Globe className="h-4 w-4 text-foreground-muted" />
                                <span className="text-sm text-foreground-muted">Region</span>
                            </div>
                            <Dropdown>
                                <DropdownTrigger>
                                    <button className="flex items-center gap-1 text-sm text-foreground hover:text-primary">
                                        us-east4
                                        <ChevronDown className="h-3 w-3" />
                                    </button>
                                </DropdownTrigger>
                                <DropdownContent align="right">
                                    <DropdownItem>us-east4 (Virginia)</DropdownItem>
                                    <DropdownItem>us-west1 (Oregon)</DropdownItem>
                                    <DropdownItem>eu-west1 (Belgium)</DropdownItem>
                                    <DropdownItem>asia-southeast1 (Singapore)</DropdownItem>
                                </DropdownContent>
                            </Dropdown>
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-background-secondary p-3">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Users className="h-4 w-4 text-foreground-muted" />
                                <span className="text-sm text-foreground-muted">Replicas</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <button
                                    onClick={() => handleReplicasChange(replicas - 1)}
                                    disabled={replicas <= 1}
                                    className="rounded border border-border bg-background px-2 py-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    −
                                </button>
                                <span className="min-w-[24px] text-center text-sm font-medium text-foreground">{replicas}</span>
                                <button
                                    onClick={() => handleReplicasChange(replicas + 1)}
                                    className="rounded border border-border bg-background px-2 py-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                                >
                                    +
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Cron Schedule */}
            <div>
                <div className="mb-3 flex items-center justify-between">
                    <h3 className="text-sm font-medium text-foreground">Cron Schedule</h3>
                    <button
                        onClick={() => setCronEnabled(!cronEnabled)}
                        className={`relative h-5 w-9 rounded-full transition-colors ${cronEnabled ? 'bg-primary' : 'bg-background-tertiary'}`}
                    >
                        <span className={`absolute top-0.5 h-4 w-4 rounded-full bg-white transition-all ${cronEnabled ? 'left-[18px]' : 'left-0.5'}`} />
                    </button>
                </div>
                {cronEnabled && (
                    <div className="space-y-2">
                        <div className="rounded-lg border border-border bg-background-secondary p-3">
                            <div className="space-y-2">
                                <label className="text-xs text-foreground-muted">Schedule (cron expression)</label>
                                <input
                                    type="text"
                                    value={cronExpression}
                                    onChange={(e) => setCronExpression(e.target.value)}
                                    className="w-full rounded border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle focus:border-primary focus:outline-none"
                                    placeholder="0 * * * *"
                                />
                            </div>
                        </div>
                        <p className="text-xs text-foreground-muted">
                            This service will run on the specified cron schedule instead of continuously.
                        </p>
                    </div>
                )}
                {!cronEnabled && (
                    <p className="text-xs text-foreground-muted">
                        Enable to run this service on a schedule instead of continuously.
                    </p>
                )}
            </div>

            {/* Health Check */}
            <div>
                <div className="mb-3 flex items-center justify-between">
                    <h3 className="text-sm font-medium text-foreground">Health Check</h3>
                    <button
                        onClick={() => setHealthCheckEnabled(!healthCheckEnabled)}
                        className={`relative h-5 w-9 rounded-full transition-colors ${healthCheckEnabled ? 'bg-primary' : 'bg-background-tertiary'}`}
                    >
                        <span className={`absolute top-0.5 h-4 w-4 rounded-full bg-white transition-all ${healthCheckEnabled ? 'left-[18px]' : 'left-0.5'}`} />
                    </button>
                </div>
                {healthCheckEnabled && (
                    <div className="space-y-2">
                        <div className="rounded-lg border border-border bg-background-secondary p-3">
                            <div className="space-y-3">
                                <div className="space-y-1">
                                    <label className="text-xs text-foreground-muted">Endpoint</label>
                                    <input
                                        type="text"
                                        value={healthEndpoint}
                                        onChange={(e) => setHealthEndpoint(e.target.value)}
                                        className="w-full rounded border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle focus:border-primary focus:outline-none"
                                        placeholder="/health"
                                    />
                                </div>
                                <div className="grid grid-cols-2 gap-2">
                                    <div className="space-y-1">
                                        <label className="text-xs text-foreground-muted">Timeout (s)</label>
                                        <input
                                            type="number"
                                            value={healthTimeout}
                                            onChange={(e) => setHealthTimeout(parseInt(e.target.value) || 10)}
                                            className="w-full rounded border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle focus:border-primary focus:outline-none"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <label className="text-xs text-foreground-muted">Interval (s)</label>
                                        <input
                                            type="number"
                                            value={healthInterval}
                                            onChange={(e) => setHealthInterval(parseInt(e.target.value) || 30)}
                                            className="w-full rounded border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle focus:border-primary focus:outline-none"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p className="text-xs text-foreground-muted">
                            Health checks ensure your service is running correctly and will restart it if it fails.
                        </p>
                    </div>
                )}
            </div>

            {/* Save Settings Button */}
            <div className="border-t border-border pt-4">
                <Button
                    onClick={handleSaveSettings}
                    disabled={isSaving}
                    className="w-full"
                >
                    {isSaving ? 'Saving...' : 'Save Settings'}
                </Button>
            </div>

            {/* Danger Zone */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-red-500">Danger Zone</h3>
                <Button variant="danger" size="sm">
                    Delete Service
                </Button>
            </div>
        </div>
    );
}
