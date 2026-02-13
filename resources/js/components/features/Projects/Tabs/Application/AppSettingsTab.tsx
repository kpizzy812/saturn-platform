import { useState, useEffect, useCallback } from 'react';
import { Badge, Button, useConfirm } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { Globe, Copy, ExternalLink, Trash2, Link2, Shield, RefreshCw, Loader2, Zap, Webhook, AlertCircle, Check, Eye, EyeOff, ChevronDown } from 'lucide-react';
import type { SelectedService } from '../../types';

interface SourceInfo {
    id: number;
    name: string;
    type: 'github_app' | 'public';
}

interface GithubAppOption {
    id: number;
    name: string;
    organization: string | null;
}

interface ApplicationData {
    uuid: string;
    name: string;
    description: string | null;
    fqdn: string | null;
    git_repository: string | null;
    git_branch: string | null;
    git_commit_sha: string | null;
    build_pack: string | null;
    base_directory: string | null;
    publish_directory: string | null;
    install_command: string | null;
    build_command: string | null;
    start_command: string | null;
    ports_exposes: string | null;
    ports_mappings: string | null;
    health_check_enabled: boolean;
    health_check_path: string | null;
    health_check_port: string | null;
    health_check_method: string | null;
    health_check_return_code: number | null;
    health_check_scheme: string | null;
    health_check_interval: number | null;
    health_check_timeout: number | null;
    health_check_retries: number | null;
    health_check_start_period: number | null;
    limits_memory: string | null;
    limits_memory_swap: string | null;
    limits_cpus: string | null;
    limits_cpu_shares: number | null;
    redirect: string | null;
    custom_docker_run_options: string | null;
    docker_registry_image_name: string | null;
    docker_registry_image_tag: string | null;
    watch_paths: string | null;
    pre_deployment_command: string | null;
    post_deployment_command: string | null;
    destination?: {
        server?: {
            name: string;
            ip: string;
        };
    };
    // Auto-deploy fields
    is_auto_deploy_enabled?: boolean;
    auto_deploy_status?: 'automatic' | 'manual_webhook' | 'not_configured';
    has_webhook_secret?: boolean;
    manual_webhook_secret_github?: string | null;
    webhook_url?: string | null;
    repository_project_id?: number | null;
    source_info?: SourceInfo | null;
}

interface AppSettingsTabProps {
    service: SelectedService;
    onChangeStaged?: () => void;
}

export function AppSettingsTab({ service, onChangeStaged }: AppSettingsTabProps) {
    const { addToast } = useToast();
    const confirm = useConfirm();
    const [app, setApp] = useState<ApplicationData | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);

    // Editable fields
    const [healthCheckEnabled, setHealthCheckEnabled] = useState(false);
    const [healthCheckPath, setHealthCheckPath] = useState('/');
    const [healthCheckInterval, setHealthCheckInterval] = useState(30);
    const [healthCheckTimeout, setHealthCheckTimeout] = useState(10);
    const [healthCheckRetries, setHealthCheckRetries] = useState(3);
    const [portsExposes, setPortsExposes] = useState('');
    const [portsMappings, setPortsMappings] = useState('');
    const [baseDirectory, setBaseDirectory] = useState('/');
    const [publishDirectory, setPublishDirectory] = useState('');
    const [installCommand, setInstallCommand] = useState('');
    const [buildCommand, setBuildCommand] = useState('');
    const [startCommand, setStartCommand] = useState('');
    const [watchPaths, setWatchPaths] = useState('');

    // Auto-deploy state
    const [autoDeployEnabled, setAutoDeployEnabled] = useState(false);
    const [showWebhookSecret, setShowWebhookSecret] = useState(false);
    const [githubApps, setGithubApps] = useState<GithubAppOption[]>([]);
    const [isLinkingApp, setIsLinkingApp] = useState(false);
    const [showGithubAppSelector, setShowGithubAppSelector] = useState(false);

    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';

    const fetchApplication = useCallback(async () => {
        try {
            setIsLoading(true);
            const response = await fetch(`/web-api/applications/${service.uuid}`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'include',
            });
            if (response.ok) {
                const data: ApplicationData = await response.json();
                setApp(data);
                // Populate editable fields
                setHealthCheckEnabled(data.health_check_enabled ?? false);
                setHealthCheckPath(data.health_check_path || '/');
                setHealthCheckInterval(data.health_check_interval ?? 30);
                setHealthCheckTimeout(data.health_check_timeout ?? 10);
                setHealthCheckRetries(data.health_check_retries ?? 3);
                setPortsExposes(data.ports_exposes || '');
                setPortsMappings(data.ports_mappings || '');
                setBaseDirectory(data.base_directory || '/');
                setPublishDirectory(data.publish_directory || '');
                setInstallCommand(data.install_command || '');
                setBuildCommand(data.build_command || '');
                setStartCommand(data.start_command || '');
                setWatchPaths(data.watch_paths || '');
                setAutoDeployEnabled(data.is_auto_deploy_enabled ?? false);
            } else {
                addToast('error', 'Failed to load application settings');
            }
        } catch {
            addToast('error', 'Failed to load application settings');
        } finally {
            setIsLoading(false);
        }
    }, [service.uuid, addToast]);

    const fetchGithubApps = useCallback(async () => {
        try {
            const response = await fetch('/web-api/github-apps/active', {
                headers: { 'Accept': 'application/json' },
                credentials: 'include',
            });
            if (response.ok) {
                const data = await response.json();
                setGithubApps(data.github_apps || []);
            }
        } catch {
            // Non-critical
        }
    }, []);

    useEffect(() => {
        fetchApplication();
        fetchGithubApps();
    }, [fetchApplication, fetchGithubApps]);

    const handleToggleAutoDeploy = async (enabled: boolean) => {
        setAutoDeployEnabled(enabled);
        try {
            const response = await fetch(`/web-api/applications/${service.uuid}`, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
                body: JSON.stringify({ is_auto_deploy_enabled: enabled }),
            });
            if (!response.ok) {
                setAutoDeployEnabled(!enabled);
                addToast('error', 'Failed to update auto-deploy setting');
            } else {
                addToast('success', enabled ? 'Auto-deploy enabled' : 'Auto-deploy disabled');
                await fetchApplication();
            }
        } catch {
            setAutoDeployEnabled(!enabled);
            addToast('error', 'Failed to update auto-deploy setting');
        }
    };

    const handleLinkGithubApp = async (githubAppId: number) => {
        setIsLinkingApp(true);
        try {
            const response = await fetch(`/web-api/applications/${service.uuid}`, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
                body: JSON.stringify({ github_app_id: githubAppId }),
            });
            if (response.ok) {
                addToast('success', 'GitHub App connected successfully');
                setShowGithubAppSelector(false);
                await fetchApplication();
            } else {
                const error = await response.json();
                addToast('error', error.message || 'Failed to connect GitHub App');
            }
        } catch {
            addToast('error', 'Failed to connect GitHub App');
        } finally {
            setIsLinkingApp(false);
        }
    };

    const handleSaveSettings = async () => {
        setIsSaving(true);
        try {
            const response = await fetch(`/web-api/applications/${service.uuid}`, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
                body: JSON.stringify({
                    health_check_enabled: healthCheckEnabled,
                    health_check_path: healthCheckPath,
                    health_check_interval: healthCheckInterval,
                    health_check_timeout: healthCheckTimeout,
                    health_check_retries: healthCheckRetries,
                    ports_exposes: portsExposes,
                    ports_mappings: portsMappings || undefined,
                    base_directory: baseDirectory,
                    publish_directory: publishDirectory || undefined,
                    install_command: installCommand || undefined,
                    build_command: buildCommand || undefined,
                    start_command: startCommand || undefined,
                    watch_paths: watchPaths || undefined,
                }),
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to save settings');
            }

            addToast('success', 'Settings saved successfully');
            onChangeStaged?.();
            await fetchApplication();
        } catch (err) {
            addToast('error', err instanceof Error ? err.message : 'Failed to save settings');
        } finally {
            setIsSaving(false);
        }
    };

    const handleDelete = async () => {
        const confirmed = await confirm({
            title: 'Delete Application',
            description: `Are you sure you want to delete "${service.name}"? This action cannot be undone and will remove all associated data.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (!confirmed) return;

        try {
            const response = await fetch(`/web-api/applications/${service.uuid}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
            });
            if (!response.ok) throw new Error('Failed to delete application');
            addToast('success', 'Application deleted');
            window.location.reload();
        } catch {
            addToast('error', 'Failed to delete application');
        }
    };

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-12">
                <Loader2 className="h-6 w-6 animate-spin text-foreground-muted" />
            </div>
        );
    }

    if (!app) {
        return (
            <div className="py-8 text-center text-sm text-foreground-muted">
                Failed to load application data.
                <button onClick={fetchApplication} className="ml-2 text-primary hover:underline">
                    Retry
                </button>
            </div>
        );
    }

    const domains = app.fqdn?.split(',').map(d => d.trim()).filter(Boolean) || [];
    const ports = portsExposes?.split(',').map(p => p.trim()).filter(Boolean) || [];

    const buildPackLabel = (bp: string | null) => {
        switch (bp) {
            case 'nixpacks': return 'Nixpacks';
            case 'static': return 'Static';
            case 'dockerfile': return 'Dockerfile';
            case 'dockercompose': return 'Docker Compose';
            case 'dockerimage': return 'Docker Image';
            default: return bp || 'Unknown';
        }
    };

    const sourceLabel = () => {
        if (app.git_repository) {
            const repo = app.git_repository.replace('https://github.com/', '').replace('.git', '');
            return { type: 'git', repo, branch: app.git_branch || 'main' };
        }
        if (app.docker_registry_image_name) {
            return { type: 'docker', repo: `${app.docker_registry_image_name}:${app.docker_registry_image_tag || 'latest'}`, branch: '' };
        }
        return { type: 'unknown', repo: 'Not configured', branch: '' };
    };

    const source = sourceLabel();
    const deployStatus = app.auto_deploy_status || 'not_configured';

    return (
        <div className="space-y-6">
            {/* Header with refresh */}
            <div className="flex items-center justify-end">
                <button
                    onClick={fetchApplication}
                    className="rounded p-1 text-foreground-muted hover:bg-background-secondary hover:text-foreground"
                    title="Refresh settings"
                >
                    <RefreshCw className="h-4 w-4" />
                </button>
            </div>

            {/* Source Section */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Source</h3>
                <div className="rounded-lg border border-border bg-background-secondary p-3">
                    <div className="flex items-center gap-3">
                        {source.type === 'git' ? (
                            <div className="flex h-8 w-8 items-center justify-center rounded bg-[#24292e]">
                                <svg viewBox="0 0 24 24" className="h-5 w-5" fill="#fff">
                                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                </svg>
                            </div>
                        ) : (
                            <div className="flex h-8 w-8 items-center justify-center rounded bg-blue-600">
                                <svg viewBox="0 0 24 24" className="h-5 w-5" fill="#fff">
                                    <path d="M13.983 11.078h2.119a.186.186 0 00.186-.185V9.006a.186.186 0 00-.186-.186h-2.119a.186.186 0 00-.185.185v1.888c0 .102.083.185.185.185m-2.954-5.43h2.118a.186.186 0 00.186-.186V3.574a.186.186 0 00-.186-.185h-2.118a.186.186 0 00-.185.185v1.888c0 .102.082.185.185.186m0 2.716h2.118a.187.187 0 00.186-.186V6.29a.186.186 0 00-.186-.185h-2.118a.186.186 0 00-.185.185v1.887c0 .102.082.186.185.186m-2.93 0h2.12a.186.186 0 00.184-.186V6.29a.185.185 0 00-.185-.185H8.1a.186.186 0 00-.185.185v1.887c0 .102.083.186.185.186m-2.964 0h2.119a.186.186 0 00.185-.186V6.29a.186.186 0 00-.185-.185H5.136a.186.186 0 00-.186.185v1.887c0 .102.084.186.186.186m5.893 2.715h2.118a.186.186 0 00.186-.185V9.006a.186.186 0 00-.186-.186h-2.118a.186.186 0 00-.185.185v1.888c0 .102.082.185.185.185m-2.93 0h2.12a.185.185 0 00.184-.185V9.006a.185.185 0 00-.184-.186h-2.12a.185.185 0 00-.184.185v1.888c0 .102.083.185.185.185m-2.964 0h2.119a.186.186 0 00.185-.185V9.006a.186.186 0 00-.185-.186H5.136a.186.186 0 00-.186.185v1.888c0 .102.084.185.186.185m-2.92 0h2.12a.185.185 0 00.184-.185V9.006a.185.185 0 00-.184-.186h-2.12a.186.186 0 00-.186.186v1.887c0 .102.084.185.186.185m10.773 2.716h2.118a.186.186 0 00.186-.186v-1.887a.186.186 0 00-.186-.186h-2.118a.186.186 0 00-.185.186v1.887c0 .102.082.186.185.186m-2.93 0h2.12a.185.185 0 00.184-.186v-1.887a.185.185 0 00-.184-.186h-2.12a.185.185 0 00-.184.186v1.887c0 .102.083.186.185.186m-2.964 0h2.119a.185.185 0 00.185-.186v-1.887a.185.185 0 00-.185-.186H5.136a.186.186 0 00-.186.186v1.887c0 .102.084.186.186.186m-2.92 0h2.12a.186.186 0 00.184-.186v-1.887a.186.186 0 00-.184-.186h-2.12a.185.185 0 00-.186.186v1.887c0 .102.084.186.186.186m14.658 2.714h2.118a.186.186 0 00.186-.185v-1.888a.186.186 0 00-.186-.185h-2.118a.186.186 0 00-.185.185v1.888c0 .102.082.185.185.185"/>
                                </svg>
                            </div>
                        )}
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-medium text-foreground">
                                {source.type === 'git' ? 'GitHub Repository' : 'Docker Image'}
                            </p>
                            <p className="truncate text-xs text-foreground-muted">
                                {source.repo}{source.branch ? ` \u2022 ${source.branch}` : ''}
                            </p>
                        </div>
                    </div>
                    {app.git_commit_sha && (
                        <div className="mt-2 border-t border-border pt-2">
                            <p className="text-xs text-foreground-muted">
                                Last commit: <code className="rounded bg-background px-1 py-0.5 text-xs">{app.git_commit_sha.substring(0, 7)}</code>
                            </p>
                        </div>
                    )}
                </div>
            </div>

            {/* Auto Deploy Section */}
            {source.type === 'git' && (
                <AutoDeploySection
                    app={app}
                    autoDeployEnabled={autoDeployEnabled}
                    onToggleAutoDeploy={handleToggleAutoDeploy}
                    deployStatus={deployStatus}
                    showWebhookSecret={showWebhookSecret}
                    onToggleShowSecret={() => setShowWebhookSecret(!showWebhookSecret)}
                    githubApps={githubApps}
                    showGithubAppSelector={showGithubAppSelector}
                    onToggleSelector={() => setShowGithubAppSelector(!showGithubAppSelector)}
                    onLinkGithubApp={handleLinkGithubApp}
                    isLinkingApp={isLinkingApp}
                    addToast={addToast}
                />
            )}

            {/* Build Configuration */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Build</h3>
                <div className="space-y-2">
                    <div className="rounded-lg border border-border bg-background-secondary p-3">
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-foreground-muted">Builder</span>
                            <span className="text-sm text-foreground">{buildPackLabel(app.build_pack)}</span>
                        </div>
                    </div>
                    <SettingsInput
                        label="Root Directory"
                        value={baseDirectory}
                        onChange={setBaseDirectory}
                        placeholder="/"
                    />
                    {app.build_pack === 'static' && (
                        <SettingsInput
                            label="Publish Directory"
                            value={publishDirectory}
                            onChange={setPublishDirectory}
                            placeholder="dist"
                        />
                    )}
                    {app.build_pack === 'nixpacks' && (
                        <>
                            <SettingsInput
                                label="Install Command"
                                value={installCommand}
                                onChange={setInstallCommand}
                                placeholder="npm install"
                            />
                            <SettingsInput
                                label="Build Command"
                                value={buildCommand}
                                onChange={setBuildCommand}
                                placeholder="npm run build"
                            />
                            <SettingsInput
                                label="Start Command"
                                value={startCommand}
                                onChange={setStartCommand}
                                placeholder="npm start"
                            />
                        </>
                    )}
                    <SettingsInput
                        label="Watch Paths"
                        value={watchPaths}
                        onChange={setWatchPaths}
                        placeholder="src/, package.json"
                        hint="Comma-separated paths. Deployments trigger only when these paths change."
                    />
                </div>
            </div>

            {/* Networking Section */}
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
                            <Badge variant={domains.length > 0 ? 'success' : 'default'} size="sm">
                                {domains.length > 0 ? 'Enabled' : 'Disabled'}
                            </Badge>
                        </div>

                        {domains.length > 0 ? (
                            <div className="space-y-2">
                                {domains.map((domain, i) => (
                                    <div key={i} className="flex items-center gap-2">
                                        <div className="h-2 w-2 rounded-full bg-green-500" />
                                        <code className="flex-1 truncate rounded bg-background px-2 py-1 text-sm text-foreground">
                                            {domain}
                                        </code>
                                        {domain.startsWith('https://') && (
                                            <Badge variant="success" size="sm">SSL</Badge>
                                        )}
                                        <button
                                            onClick={() => {
                                                navigator.clipboard.writeText(domain);
                                                addToast('success', 'Domain copied');
                                            }}
                                            className="rounded p-1 text-foreground-muted hover:bg-background hover:text-foreground"
                                        >
                                            <Copy className="h-3.5 w-3.5" />
                                        </button>
                                        <a
                                            href={domain.startsWith('http') ? domain : `https://${domain}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="rounded p-1 text-foreground-muted hover:bg-background hover:text-foreground"
                                        >
                                            <ExternalLink className="h-3.5 w-3.5" />
                                        </a>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-xs text-foreground-muted">No domains configured.</p>
                        )}
                    </div>

                    {/* Port Configuration */}
                    <div className="rounded-lg border border-border bg-background-secondary p-4">
                        <div className="mb-3 flex items-center gap-2">
                            <Link2 className="h-4 w-4 text-foreground-muted" />
                            <span className="text-sm font-medium text-foreground">Port Configuration</span>
                        </div>
                        <div className="space-y-2">
                            <SettingsInput
                                label="Exposed Ports"
                                value={portsExposes}
                                onChange={setPortsExposes}
                                placeholder="3000"
                                hint="Comma-separated list of ports your app listens on"
                            />
                            <SettingsInput
                                label="Port Mappings"
                                value={portsMappings}
                                onChange={setPortsMappings}
                                placeholder="8080:3000"
                                hint="host:container format, comma-separated"
                            />
                        </div>
                        {ports.length > 0 && (
                            <div className="mt-3 space-y-1">
                                {ports.map((port, i) => (
                                    <div key={i} className="flex items-center justify-between rounded bg-background px-2 py-1.5">
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm text-foreground">PORT</span>
                                            <span className="text-sm text-foreground-muted">&rarr;</span>
                                            <code className="text-sm font-medium text-primary">{port}</code>
                                        </div>
                                        <Badge variant="default" size="sm">HTTP</Badge>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Internal Networking */}
                    <div className="rounded-lg border border-border bg-background-secondary p-4">
                        <div className="mb-3 flex items-center gap-2">
                            <Shield className="h-4 w-4 text-emerald-500" />
                            <span className="text-sm font-medium text-foreground">Internal Networking</span>
                        </div>
                        <p className="mb-3 text-xs text-foreground-muted">
                            Use the container name for service-to-service communication within the Docker network.
                        </p>
                        <div className="rounded bg-background p-3">
                            <p className="mb-1 text-xs text-foreground-muted">Container name</p>
                            <code className="text-sm text-foreground">{app.uuid}</code>
                        </div>
                        {app.destination?.server && (
                            <div className="mt-2 rounded bg-background p-3">
                                <p className="mb-1 text-xs text-foreground-muted">Server</p>
                                <code className="text-sm text-foreground">
                                    {app.destination.server.name} ({app.destination.server.ip})
                                </code>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Resource Limits */}
            {(app.limits_memory || app.limits_cpus) && (
                <div>
                    <h3 className="mb-3 text-sm font-medium text-foreground">Resource Limits</h3>
                    <div className="space-y-2">
                        {app.limits_memory && (
                            <div className="rounded-lg border border-border bg-background-secondary p-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-foreground-muted">Memory</span>
                                    <span className="text-sm text-foreground">{app.limits_memory}</span>
                                </div>
                            </div>
                        )}
                        {app.limits_cpus && (
                            <div className="rounded-lg border border-border bg-background-secondary p-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-foreground-muted">CPUs</span>
                                    <span className="text-sm text-foreground">{app.limits_cpus}</span>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}

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
                                <SettingsInput
                                    label="Endpoint"
                                    value={healthCheckPath}
                                    onChange={setHealthCheckPath}
                                    placeholder="/health"
                                />
                                <div className="grid grid-cols-3 gap-2">
                                    <div className="space-y-1">
                                        <label className="text-xs text-foreground-muted">Interval (s)</label>
                                        <input
                                            type="number"
                                            value={healthCheckInterval}
                                            onChange={(e) => setHealthCheckInterval(parseInt(e.target.value) || 30)}
                                            className="w-full rounded border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <label className="text-xs text-foreground-muted">Timeout (s)</label>
                                        <input
                                            type="number"
                                            value={healthCheckTimeout}
                                            onChange={(e) => setHealthCheckTimeout(parseInt(e.target.value) || 10)}
                                            className="w-full rounded border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <label className="text-xs text-foreground-muted">Retries</label>
                                        <input
                                            type="number"
                                            value={healthCheckRetries}
                                            onChange={(e) => setHealthCheckRetries(parseInt(e.target.value) || 3)}
                                            className="w-full rounded border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
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
                <Button variant="danger" size="sm" onClick={handleDelete}>
                    <Trash2 className="mr-1.5 h-3.5 w-3.5" />
                    Delete Application
                </Button>
            </div>
        </div>
    );
}

function AutoDeploySection({
    app,
    autoDeployEnabled,
    onToggleAutoDeploy,
    deployStatus,
    showWebhookSecret,
    onToggleShowSecret,
    githubApps,
    showGithubAppSelector,
    onToggleSelector,
    onLinkGithubApp,
    isLinkingApp,
    addToast,
}: {
    app: ApplicationData;
    autoDeployEnabled: boolean;
    onToggleAutoDeploy: (enabled: boolean) => void;
    deployStatus: string;
    showWebhookSecret: boolean;
    onToggleShowSecret: () => void;
    githubApps: GithubAppOption[];
    showGithubAppSelector: boolean;
    onToggleSelector: () => void;
    onLinkGithubApp: (id: number) => void;
    isLinkingApp: boolean;
    addToast: (type: 'success' | 'error', message: string) => void;
}) {
    const statusConfig = {
        automatic: {
            icon: <Zap className="h-4 w-4 text-green-500" />,
            label: 'Automatic',
            sublabel: `via ${app.source_info?.name || 'GitHub App'}`,
            color: 'text-green-500',
            borderColor: 'border-green-500/20',
            bgColor: 'bg-green-500/5',
        },
        manual_webhook: {
            icon: <Webhook className="h-4 w-4 text-yellow-500" />,
            label: 'Manual Webhook',
            sublabel: 'Webhook secret configured',
            color: 'text-yellow-500',
            borderColor: 'border-yellow-500/20',
            bgColor: 'bg-yellow-500/5',
        },
        not_configured: {
            icon: <AlertCircle className="h-4 w-4 text-red-400" />,
            label: 'Not Configured',
            sublabel: 'No webhook integration set up',
            color: 'text-red-400',
            borderColor: 'border-red-500/20',
            bgColor: 'bg-red-500/5',
        },
    };

    const status = statusConfig[deployStatus as keyof typeof statusConfig] || statusConfig.not_configured;

    return (
        <div>
            <div className="mb-3 flex items-center justify-between">
                <h3 className="text-sm font-medium text-foreground">Auto Deploy</h3>
                <button
                    onClick={() => onToggleAutoDeploy(!autoDeployEnabled)}
                    className={`relative h-5 w-9 rounded-full transition-colors ${autoDeployEnabled ? 'bg-primary' : 'bg-background-tertiary'}`}
                >
                    <span className={`absolute top-0.5 h-4 w-4 rounded-full bg-white transition-all ${autoDeployEnabled ? 'left-[18px]' : 'left-0.5'}`} />
                </button>
            </div>

            <div className={`rounded-lg border ${status.borderColor} ${status.bgColor} p-4`}>
                {/* Status indicator */}
                <div className="mb-3 flex items-center gap-2">
                    {status.icon}
                    <div>
                        <span className={`text-sm font-medium ${status.color}`}>{status.label}</span>
                        <span className="ml-2 text-xs text-foreground-muted">{status.sublabel}</span>
                    </div>
                </div>

                {/* Automatic mode details */}
                {deployStatus === 'automatic' && (
                    <div className="space-y-2">
                        <div className="flex items-center gap-2 rounded bg-background/50 px-3 py-2">
                            <Check className="h-3.5 w-3.5 text-green-500" />
                            <span className="text-xs text-foreground-muted">
                                Connected to <span className="font-medium text-foreground">{app.source_info?.name}</span>
                            </span>
                        </div>
                        {app.repository_project_id && (
                            <div className="flex items-center gap-2 rounded bg-background/50 px-3 py-2">
                                <Check className="h-3.5 w-3.5 text-green-500" />
                                <span className="text-xs text-foreground-muted">
                                    Repository ID: <code className="rounded bg-background px-1 text-xs">{app.repository_project_id}</code>
                                </span>
                            </div>
                        )}
                    </div>
                )}

                {/* Manual webhook details */}
                {deployStatus === 'manual_webhook' && app.webhook_url && (
                    <div className="space-y-2">
                        <div className="rounded bg-background/50 p-3">
                            <p className="mb-1 text-xs text-foreground-muted">Webhook URL</p>
                            <div className="flex items-center gap-2">
                                <code className="flex-1 truncate text-xs text-foreground">{app.webhook_url}</code>
                                <button
                                    onClick={() => {
                                        navigator.clipboard.writeText(app.webhook_url || '');
                                        addToast('success', 'Webhook URL copied');
                                    }}
                                    className="rounded p-1 text-foreground-muted hover:text-foreground"
                                >
                                    <Copy className="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                        {app.manual_webhook_secret_github && (
                            <div className="rounded bg-background/50 p-3">
                                <p className="mb-1 text-xs text-foreground-muted">Webhook Secret</p>
                                <div className="flex items-center gap-2">
                                    <code className="flex-1 truncate text-xs text-foreground">
                                        {showWebhookSecret ? app.manual_webhook_secret_github : '\u2022'.repeat(32)}
                                    </code>
                                    <button
                                        onClick={onToggleShowSecret}
                                        className="rounded p-1 text-foreground-muted hover:text-foreground"
                                    >
                                        {showWebhookSecret ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                                    </button>
                                    <button
                                        onClick={() => {
                                            navigator.clipboard.writeText(app.manual_webhook_secret_github || '');
                                            addToast('success', 'Secret copied');
                                        }}
                                        className="rounded p-1 text-foreground-muted hover:text-foreground"
                                    >
                                        <Copy className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                        )}

                        {/* Show GitHub App connector for manual webhook mode */}
                        {githubApps.length > 0 && (
                            <div className="border-t border-border/50 pt-2">
                                <p className="mb-2 text-xs text-foreground-muted">
                                    Upgrade to automatic deploy by connecting a GitHub App:
                                </p>
                                <GithubAppSelector
                                    githubApps={githubApps}
                                    showSelector={showGithubAppSelector}
                                    onToggle={onToggleSelector}
                                    onSelect={onLinkGithubApp}
                                    isLinking={isLinkingApp}
                                />
                            </div>
                        )}
                    </div>
                )}

                {/* Not configured */}
                {deployStatus === 'not_configured' && (
                    <div className="space-y-3">
                        {githubApps.length > 0 ? (
                            <div>
                                <p className="mb-2 text-xs text-foreground-muted">
                                    Connect a GitHub App for automatic deployments on push:
                                </p>
                                <GithubAppSelector
                                    githubApps={githubApps}
                                    showSelector={showGithubAppSelector}
                                    onToggle={onToggleSelector}
                                    onSelect={onLinkGithubApp}
                                    isLinking={isLinkingApp}
                                />
                            </div>
                        ) : (
                            <div className="rounded bg-background/50 p-3">
                                <p className="text-xs text-foreground-muted">
                                    To enable auto-deploy, configure a GitHub App in{' '}
                                    <a href="/sources" className="text-primary hover:underline">Sources</a>
                                    {' '}or set up a manual webhook.
                                </p>
                            </div>
                        )}

                        {/* Always show webhook info if secret exists */}
                        {app.has_webhook_secret && app.webhook_url && (
                            <div className="rounded bg-background/50 p-3">
                                <p className="mb-1 text-xs text-foreground-muted">Manual Webhook URL</p>
                                <div className="flex items-center gap-2">
                                    <code className="flex-1 truncate text-xs text-foreground">{app.webhook_url}</code>
                                    <button
                                        onClick={() => {
                                            navigator.clipboard.writeText(app.webhook_url || '');
                                            addToast('success', 'Webhook URL copied');
                                        }}
                                        className="rounded p-1 text-foreground-muted hover:text-foreground"
                                    >
                                        <Copy className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

function GithubAppSelector({
    githubApps,
    showSelector,
    onToggle,
    onSelect,
    isLinking,
}: {
    githubApps: GithubAppOption[];
    showSelector: boolean;
    onToggle: () => void;
    onSelect: (id: number) => void;
    isLinking: boolean;
}) {
    return (
        <div>
            <button
                onClick={onToggle}
                disabled={isLinking}
                className="flex w-full items-center justify-between rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground hover:bg-background-secondary"
            >
                <span>{isLinking ? 'Connecting...' : 'Connect GitHub App'}</span>
                {isLinking ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                    <ChevronDown className={`h-4 w-4 transition-transform ${showSelector ? 'rotate-180' : ''}`} />
                )}
            </button>
            {showSelector && !isLinking && (
                <div className="mt-1 rounded-lg border border-border bg-background shadow-lg">
                    {githubApps.map((ghApp) => (
                        <button
                            key={ghApp.id}
                            onClick={() => onSelect(ghApp.id)}
                            className="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-background-secondary first:rounded-t-lg last:rounded-b-lg"
                        >
                            <div className="flex h-7 w-7 items-center justify-center rounded bg-[#24292e]">
                                <svg viewBox="0 0 24 24" className="h-4 w-4" fill="#fff">
                                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                </svg>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-foreground">{ghApp.name}</p>
                                {ghApp.organization && (
                                    <p className="text-xs text-foreground-muted">{ghApp.organization}</p>
                                )}
                            </div>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

function SettingsInput({ label, value, onChange, placeholder, hint }: {
    label: string;
    value: string;
    onChange: (v: string) => void;
    placeholder?: string;
    hint?: string;
}) {
    return (
        <div className="rounded-lg border border-border bg-background-secondary p-3">
            <div className="space-y-1">
                <label className="text-xs text-foreground-muted">{label}</label>
                <input
                    type="text"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    className="w-full rounded border border-border bg-background px-3 py-2 text-sm text-foreground placeholder:text-foreground-subtle focus:border-primary focus:outline-none"
                    placeholder={placeholder}
                />
                {hint && <p className="text-xs text-foreground-muted">{hint}</p>}
            </div>
        </div>
    );
}
