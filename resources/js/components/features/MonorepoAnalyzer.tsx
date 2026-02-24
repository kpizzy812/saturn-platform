import { useState, useEffect, useRef } from 'react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge } from '@/components/ui/Badge';
import { DeployGuide } from '@/components/features/DeployGuide';
import axios from 'axios';

// ── Types ───────────────────────────────────────────────────────────

interface DetectedHealthCheck {
    path: string;
    method: string;
    interval: number;
    timeout: number;
}

type ApplicationMode = 'web' | 'worker' | 'both';

interface DetectedApp {
    name: string;
    path: string;
    framework: string;
    build_pack: string;
    default_port: number;
    type: 'backend' | 'frontend' | 'fullstack' | 'unknown';
    build_command?: string;
    install_command?: string;
    start_command?: string;
    health_check?: DetectedHealthCheck;
    node_version?: string;
    python_version?: string;
    dockerfile_info?: DockerfileInfo;
    application_mode?: ApplicationMode;
}

interface DetectedDatabase {
    type: string;
    name: string;
    env_var_name: string;
    consumers: string[];
    detected_via?: string;
    port?: number;
}

interface DetectedService {
    type: string;
    description: string;
    required_env_vars: string[];
}

interface EnvVariable {
    key: string;
    default_value?: string | null;
    is_required: boolean;
    category: string;
    for_app: string;
    comment?: string | null;
}

interface AppDependency {
    app_name: string;
    depends_on: string[];
    internal_urls: Record<string, string>;
    deploy_order: number;
}

interface CIConfig {
    install_command?: string;
    build_command?: string;
    test_command?: string;
    start_command?: string;
    node_version?: string;
    detected_from?: string;
}

interface DockerComposeService {
    name: string;
    image: string;
    ports: string[];
    is_database: boolean;
}

interface DockerfileInfo {
    base_image?: string;
    env_variables?: Record<string, string | null>;
    exposed_ports?: number[];
    build_args?: Record<string, string | null>;
    workdir?: string;
    healthcheck?: string;
    entrypoint?: string;
    cmd?: string;
    labels?: Record<string, string>;
    node_version?: string;
    python_version?: string;
    go_version?: string;
}

interface AnalysisResult {
    is_monorepo: boolean;
    monorepo_type: string | null;
    repository_name?: string;
    git_branch?: string;
    applications: DetectedApp[];
    databases: DetectedDatabase[];
    services: DetectedService[];
    env_variables: EnvVariable[];
    app_dependencies: AppDependency[];
    docker_compose_services: DockerComposeService[];
    ci_config?: CIConfig;
}

interface ProvisionResult {
    applications: Array<{ uuid: string; name: string }>;
    databases: Array<{ uuid: string; type: string }>;
    monorepo_group_id: string | null;
}

interface Props {
    gitRepository: string;
    gitBranch?: string;
    privateKeyId?: number;
    sourceId?: number;
    githubAppId?: number;
    environmentUuid: string;
    destinationUuid: string;
    onComplete: (result: ProvisionResult) => void;
    autoStart?: boolean;
}

interface AppConfig {
    base_directory: string;
    application_type: ApplicationMode;
    env_vars: Array<{ key: string; value: string; comment?: string }>;
}

// ── Constants ───────────────────────────────────────────────────────

const frameworkLabels: Record<string, string> = {
    nestjs: 'NestJS',
    nextjs: 'Next.js',
    nuxt: 'Nuxt',
    express: 'Express',
    fastapi: 'FastAPI',
    django: 'Django',
    flask: 'Flask',
    rails: 'Rails',
    'go-fiber': 'Go Fiber',
    'go-gin': 'Go Gin',
    'go-echo': 'Go Echo',
    go: 'Go',
    rust: 'Rust',
    'rust-axum': 'Rust Axum',
    'rust-actix': 'Rust Actix',
    laravel: 'Laravel',
    symfony: 'Symfony',
    phoenix: 'Phoenix',
    'spring-boot': 'Spring Boot',
    'vite-react': 'Vite + React',
    'vite-vue': 'Vite + Vue',
    'vite-svelte': 'Vite + Svelte',
    'create-react-app': 'Create React App',
    astro: 'Astro',
    remix: 'Remix',
    sveltekit: 'SvelteKit',
    hono: 'Hono',
    fastify: 'Fastify',
    dockerfile: 'Dockerfile',
    'docker-compose': 'Docker Compose',
    nixpacks: 'Nixpacks',
    procfile: 'Procfile',
};

const databaseIcons: Record<string, string> = {
    postgresql: '🐘',
    mysql: '🐬',
    mongodb: '🍃',
    redis: '🔴',
    clickhouse: '🏠',
};

// ── Helpers ─────────────────────────────────────────────────────────

function extractRepoName(gitRepository: string): string {
    return gitRepository.split('/').pop()?.replace('.git', '') || gitRepository;
}

function isComposeIncludedDb(db: DetectedDatabase): boolean {
    return !!db.detected_via?.startsWith('docker-compose:');
}

// ── Component ───────────────────────────────────────────────────────

export function MonorepoAnalyzer({
    gitRepository,
    gitBranch,
    privateKeyId,
    sourceId,
    githubAppId,
    environmentUuid,
    destinationUuid,
    onComplete,
    autoStart = false,
}: Props) {
    const [analyzing, setAnalyzing] = useState(false);
    const [provisioning, setProvisioning] = useState(false);
    const [analysis, setAnalysis] = useState<AnalysisResult | null>(null);
    const [selectedApps, setSelectedApps] = useState<Record<string, boolean>>({});
    const [selectedDbs, setSelectedDbs] = useState<Record<string, boolean>>({});
    const [appConfigs, setAppConfigs] = useState<Record<string, AppConfig>>({});
    const [error, setError] = useState<string | null>(null);
    const [expandedApps, setExpandedApps] = useState<Record<string, boolean>>({});
    const autoStarted = useRef(false);

    const repoName = analysis?.repository_name || extractRepoName(gitRepository);
    const branch = analysis?.git_branch || gitBranch || 'main';

    useEffect(() => {
        if (autoStart && !autoStarted.current && !analyzing && !analysis) {
            autoStarted.current = true;
            analyzeRepository();
        }
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [autoStart]);

    const analyzeRepository = async () => {
        setAnalyzing(true);
        setError(null);

        try {
            const response = await axios.post('/git/analyze', {
                git_repository: gitRepository,
                git_branch: gitBranch,
                private_key_id: privateKeyId,
                source_id: sourceId,
                github_app_id: githubAppId,
            });

            if (!response.data.success) {
                throw new Error(response.data.error || 'Analysis failed');
            }

            const result = response.data.data as AnalysisResult;
            setAnalysis(result);

            // Pre-select all apps and non-compose databases
            const apps: Record<string, boolean> = {};
            const configs: Record<string, AppConfig> = {};
            const expanded: Record<string, boolean> = {};
            result.applications.forEach(app => {
                apps[app.name] = true;
                expanded[app.name] = true; // Expand first app by default
                const appEnvVars = result.env_variables
                    .filter(v => v.for_app === app.name && v.category !== 'database' && v.category !== 'cache')
                    .map(v => ({
                        key: v.key,
                        value: (v.is_required ? '' : v.default_value) || '',
                        comment: v.comment || undefined,
                    }));
                configs[app.name] = {
                    base_directory: app.path === '.' ? '/' : '/' + app.path.replace(/^\//, ''),
                    application_type: app.application_mode || 'web',
                    env_vars: appEnvVars,
                };
            });
            setSelectedApps(apps);
            setAppConfigs(configs);
            setExpandedApps(expanded);

            // Pre-select databases: if no app uses docker-compose build pack,
            // compose-included DBs should also be pre-selected (user needs managed DBs)
            const hasAnyDockerComposeApp = result.applications.some(
                a => a.build_pack === 'docker-compose'
            );
            const dbs: Record<string, boolean> = {};
            result.databases.forEach(db => {
                dbs[db.type] = hasAnyDockerComposeApp ? !isComposeIncludedDb(db) : true;
            });
            setSelectedDbs(dbs);
        } catch (err: unknown) {
            const axiosError = err as { response?: { data?: { error?: string } }; message?: string };
            const message = axiosError.response?.data?.error || axiosError.message || 'Failed to analyze repository';
            setError(message);
        } finally {
            setAnalyzing(false);
        }
    };

    const provisionInfrastructure = async () => {
        if (!analysis) return;

        setProvisioning(true);
        setError(null);

        const payload = {
            environment_uuid: environmentUuid,
            destination_uuid: destinationUuid,
            git_repository: gitRepository,
            git_branch: gitBranch,
            private_key_id: privateKeyId,
            source_id: sourceId,
            github_app_id: githubAppId,
            applications: analysis.applications.map(app => ({
                name: app.name,
                enabled: selectedApps[app.name] ?? false,
                base_directory: appConfigs[app.name]?.base_directory ?? app.path,
                application_type: appConfigs[app.name]?.application_type ?? app.application_mode ?? 'web',
                env_vars: (appConfigs[app.name]?.env_vars ?? []).filter(v => v.key && v.value),
            })),
            databases: analysis.databases.map(db => ({
                type: db.type,
                enabled: selectedDbs[db.type] ?? false,
            })),
        };

        try {
            const response = await axios.post('/git/provision', payload);

            if (!response.data.success) {
                throw new Error(response.data.error || 'Provisioning failed');
            }

            onComplete(response.data.data);
        } catch (err: unknown) {
            const axiosError = err as { response?: { data?: { error?: string; errors?: Record<string, string[]>; message?: string } }; message?: string };
            const respData = axiosError.response?.data;
            // Extract Laravel validation errors
            let message = respData?.error || respData?.message || axiosError.message || 'Failed to provision infrastructure';
            if (respData?.errors) {
                const validationErrors = Object.entries(respData.errors)
                    .map(([field, msgs]) => `${field}: ${(msgs as string[]).join(', ')}`)
                    .join('\n');
                message = `Validation failed:\n${validationErrors}`;
            }
            console.error('[Saturn Provision]', { status: axiosError.response?.data, payload });
            setError(message);
        } finally {
            setProvisioning(false);
        }
    };

    const selectedAppCount = Object.values(selectedApps).filter(Boolean).length;
    const selectedDbCount = Object.values(selectedDbs).filter(Boolean).length;
    const hasSelectedApps = selectedAppCount > 0;

    // If any app uses docker-compose build pack, compose DBs are managed by compose itself.
    // Otherwise, ALL databases should be shown as standalone (user needs managed instances).
    const hasDockerComposeApp = analysis?.applications.some(
        app => app.build_pack === 'docker-compose' && selectedApps[app.name]
    ) ?? false;
    const composeIncludedDbs = hasDockerComposeApp
        ? analysis?.databases.filter(isComposeIncludedDb) ?? []
        : [];
    const standaloneDbs = hasDockerComposeApp
        ? analysis?.databases.filter(db => !isComposeIncludedDb(db)) ?? []
        : analysis?.databases ?? [];

    // Non-database compose services that aren't the main app (e.g. hummingbot).
    // Filter out services that use `build:` (these are the app itself, already detected via Dockerfile).
    const extraComposeServices = (analysis?.docker_compose_services ?? []).filter(
        s => !s.is_database
            && !s.image.startsWith('build:')
            && !analysis?.applications.some(a => a.name === s.name)
    );

    // ── Analyze screen ──────────────────────────────────────────────

    if (!analysis) {
        return (
            <div className="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Repository Analysis</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-foreground-muted mb-4">
                            Saturn will analyze your repository to detect applications,
                            required databases, and configuration.
                        </p>

                        {error && (
                            <div className="flex items-center gap-2 text-danger mb-4">
                                <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="12" y1="8" x2="12" y2="12" />
                                    <line x1="12" y1="16" x2="12.01" y2="16" />
                                </svg>
                                <span>{error}</span>
                            </div>
                        )}

                        <Button onClick={analyzeRepository} loading={analyzing}>
                            {analyzing ? 'Analyzing...' : 'Analyze Repository'}
                        </Button>
                    </CardContent>
                </Card>
                {!analyzing && (
                    <DeployGuide variant="full" defaultOpen="repo-checklist" />
                )}
            </div>
        );
    }

    // ── Results screen ──────────────────────────────────────────────

    return (
        <div className="space-y-4">
            {/* Global Error Banner */}
            {error && (
                <div className="p-3 bg-danger/10 border border-danger/30 rounded-lg text-danger text-sm whitespace-pre-wrap">
                    {error}
                </div>
            )}

            {/* Repository Header */}
            <Card>
                <CardContent className="py-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="text-lg font-semibold">{repoName}</h2>
                            <div className="flex items-center gap-2 mt-1 text-sm text-foreground-muted">
                                <span>Branch: {branch}</span>
                                {analysis.is_monorepo && (
                                    <>
                                        <span>•</span>
                                        <Badge variant="secondary" size="sm">Monorepo ({analysis.monorepo_type})</Badge>
                                    </>
                                )}
                            </div>
                        </div>
                        <Button variant="outline" size="sm" onClick={() => setAnalysis(null)}>
                            Re-analyze
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Applications */}
            {analysis.applications.map(app => {
                const appDep = analysis.app_dependencies?.find(d => d.app_name === app.name);
                const config = appConfigs[app.name];
                const isWorker = config?.application_type === 'worker';
                const isExpanded = expandedApps[app.name] ?? false;
                const isDockerCompose = app.build_pack === 'docker-compose';

                return (
                    <Card key={app.name}>
                        <CardContent className="py-4">
                            {/* App Header */}
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <Checkbox
                                        checked={selectedApps[app.name] ?? false}
                                        onCheckedChange={(checked) =>
                                            setSelectedApps(prev => ({ ...prev, [app.name]: checked }))
                                        }
                                    />
                                    <div>
                                        <div className="font-medium flex items-center gap-2 flex-wrap">
                                            {app.name}
                                            <Badge variant="outline" size="sm">
                                                {frameworkLabels[app.framework] || app.framework}
                                            </Badge>
                                            {config?.application_type === 'worker' && (
                                                <Badge variant="warning" size="sm">Worker</Badge>
                                            )}
                                            {config?.application_type === 'both' && (
                                                <Badge variant="primary" size="sm">Web + Worker</Badge>
                                            )}
                                            {app.type !== 'unknown' && (
                                                <Badge variant="secondary" size="sm" className="capitalize">{app.type}</Badge>
                                            )}
                                            {appDep && appDep.deploy_order !== undefined && (
                                                <Badge variant="outline" size="sm">
                                                    Deploy #{appDep.deploy_order + 1}
                                                </Badge>
                                            )}
                                        </div>
                                        <div className="text-sm text-foreground-muted mt-0.5">
                                            {isWorker
                                                ? 'No HTTP port — runs as background process'
                                                : `Port ${app.default_port || 80}`
                                            }
                                            {app.path !== '.' && <> • {app.path}</>}
                                        </div>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setExpandedApps(prev => ({ ...prev, [app.name]: !isExpanded }))}
                                    className="text-foreground-muted hover:text-foreground p-1"
                                >
                                    <svg className={`h-5 w-5 transition-transform ${isExpanded ? 'rotate-180' : ''}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                        <polyline points="6 9 12 15 18 9" />
                                    </svg>
                                </button>
                            </div>

                            {/* Expanded Details */}
                            {isExpanded && (
                                <div className="mt-4 space-y-4 border-t border-white/[0.06] pt-4">
                                    {/* Docker Compose Services (inline) */}
                                    {isDockerCompose && analysis.docker_compose_services.length > 0 && (
                                        <div>
                                            <div className="text-sm font-medium text-foreground-muted mb-2">Compose Services</div>
                                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                {analysis.docker_compose_services.map(service => (
                                                    <div
                                                        key={service.name}
                                                        className="flex items-center gap-2 p-2 bg-background-secondary rounded text-sm"
                                                    >
                                                        <span>{service.is_database ? '💾' : '📦'}</span>
                                                        <div className="min-w-0">
                                                            <span className="font-medium">{service.name}</span>
                                                            <span className="text-foreground-muted ml-2 truncate text-xs">{service.image}</span>
                                                            {service.ports.length > 0 && (
                                                                <span className="text-foreground-muted ml-2 text-xs">({service.ports.join(', ')})</span>
                                                            )}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                            {composeIncludedDbs.length > 0 && (
                                                <p className="text-xs text-foreground-muted mt-2">
                                                    {composeIncludedDbs.map(db => db.name).join(', ')} included in compose — no separate database needed.
                                                </p>
                                            )}
                                        </div>
                                    )}

                                    {/* Dockerfile Info */}
                                    {!isDockerCompose && app.dockerfile_info && (
                                        <div className="space-y-1">
                                            <div className="text-sm font-medium text-foreground-muted">Dockerfile</div>
                                            <div className="grid grid-cols-2 gap-x-4 gap-y-1 text-sm pl-2">
                                                {app.dockerfile_info.base_image && (
                                                    <div><span className="text-foreground-muted">Base:</span> <code className="text-xs bg-background-secondary px-1 rounded">{app.dockerfile_info.base_image}</code></div>
                                                )}
                                                {app.dockerfile_info.cmd && (
                                                    <div><span className="text-foreground-muted">CMD:</span> <code className="text-xs bg-background-secondary px-1 rounded">{app.dockerfile_info.cmd}</code></div>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {/* Health Check */}
                                    {app.health_check && !isWorker && (
                                        <div className="flex items-center gap-2 text-sm">
                                            <svg className="h-4 w-4 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                                <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
                                            </svg>
                                            <span className="text-foreground-muted">Health:</span>
                                            <code className="text-xs bg-background-secondary px-2 py-0.5 rounded">
                                                {app.health_check.method} {app.health_check.path}
                                            </code>
                                        </div>
                                    )}

                                    {/* Dependencies */}
                                    {appDep && appDep.depends_on.length > 0 && (
                                        <div className="flex items-center gap-2 text-sm">
                                            <span className="text-foreground-muted">Depends on:</span>
                                            {appDep.depends_on.map(dep => (
                                                <Badge key={dep} variant="secondary" size="sm">{dep}</Badge>
                                            ))}
                                        </div>
                                    )}

                                    {/* Configuration */}
                                    {selectedApps[app.name] && config && (
                                        <div className="space-y-3 border-t border-white/[0.06] pt-3">
                                            <div className="text-sm font-medium text-foreground-muted">Configuration</div>

                                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <label className="text-xs text-foreground-muted block mb-1">Application Type</label>
                                                    <select
                                                        value={config.application_type}
                                                        onChange={(e) => setAppConfigs(prev => ({
                                                            ...prev,
                                                            [app.name]: { ...prev[app.name], application_type: e.target.value as ApplicationMode },
                                                        }))}
                                                        className="w-full h-8 text-sm bg-background-secondary border border-white/[0.06] rounded px-2"
                                                    >
                                                        <option value="web">Web (HTTP)</option>
                                                        <option value="worker">Worker (no HTTP)</option>
                                                        <option value="both">Web + Worker</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label className="text-xs text-foreground-muted block mb-1">Base Directory</label>
                                                    <Input
                                                        value={config.base_directory}
                                                        onChange={(e) => setAppConfigs(prev => ({
                                                            ...prev,
                                                            [app.name]: { ...prev[app.name], base_directory: e.target.value },
                                                        }))}
                                                        placeholder="/"
                                                        className="h-8 text-sm"
                                                    />
                                                </div>
                                            </div>

                                            {/* Environment Variables */}
                                            {config.env_vars.length === 0 && (
                                                <p className="text-xs text-foreground-muted">
                                                    No .env.example found. Add environment variables your app needs below.
                                                </p>
                                            )}
                                            {config.env_vars.length > 0 && (
                                                <div className="space-y-1.5">
                                                    <label className="text-xs text-foreground-muted">Environment Variables</label>
                                                    {config.env_vars.map((envVar, idx) => (
                                                        <div key={idx} className="p-2 bg-background-secondary rounded-lg space-y-1">
                                                            <div className="flex items-center justify-between gap-2">
                                                                <code className="text-xs font-mono text-primary break-all">{envVar.key}</code>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => {
                                                                        const newVars = config.env_vars.filter((_, i) => i !== idx);
                                                                        setAppConfigs(prev => ({
                                                                            ...prev,
                                                                            [app.name]: { ...prev[app.name], env_vars: newVars },
                                                                        }));
                                                                    }}
                                                                    className="text-foreground-muted hover:text-danger text-sm px-1 flex-shrink-0"
                                                                >
                                                                    &times;
                                                                </button>
                                                            </div>
                                                            {envVar.comment && (
                                                                <p className="text-[11px] text-foreground-muted leading-tight">{envVar.comment}</p>
                                                            )}
                                                            <Input
                                                                value={envVar.value}
                                                                onChange={(e) => {
                                                                    const newVars = [...config.env_vars];
                                                                    newVars[idx] = { ...newVars[idx], value: e.target.value };
                                                                    setAppConfigs(prev => ({
                                                                        ...prev,
                                                                        [app.name]: { ...prev[app.name], env_vars: newVars },
                                                                    }));
                                                                }}
                                                                placeholder={envVar.value ? undefined : 'Enter value...'}
                                                                className="h-7 text-xs font-mono w-full"
                                                            />
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setAppConfigs(prev => ({
                                                        ...prev,
                                                        [app.name]: {
                                                            ...prev[app.name],
                                                            env_vars: [...prev[app.name].env_vars, { key: '', value: '' }],
                                                        },
                                                    }));
                                                }}
                                                className="text-xs text-primary hover:underline"
                                            >
                                                + Add env variable
                                            </button>
                                        </div>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                );
            })}

            {/* Standalone Databases (not from compose) */}
            {standaloneDbs.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Required Databases</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            {standaloneDbs.map(db => (
                                <div
                                    key={db.type}
                                    className="flex items-center justify-between p-3 border border-white/[0.06] rounded-lg"
                                >
                                    <div className="flex items-center gap-3">
                                        <Checkbox
                                            checked={selectedDbs[db.type] ?? false}
                                            onCheckedChange={(checked) =>
                                                setSelectedDbs(prev => ({ ...prev, [db.type]: checked }))
                                            }
                                        />
                                        <span className="text-lg">{databaseIcons[db.type] || '💾'}</span>
                                        <div>
                                            <div className="font-medium capitalize">{db.type}</div>
                                            {db.consumers.length > 0 && (
                                                <div className="text-sm text-foreground-muted">
                                                    Used by: {db.consumers.join(', ')}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                    <Badge variant="outline" size="sm">{db.env_var_name}</Badge>
                                </div>
                            ))}
                        </div>
                        <p className="text-xs text-foreground-muted mt-2">
                            Saturn will create managed database instances. Connection URLs auto-injected.
                        </p>
                    </CardContent>
                </Card>
            )}

            {/* External Services Warning */}
            {analysis.services.length > 0 && (
                <Card className="border-warning/30">
                    <CardHeader>
                        <CardTitle className="text-base flex items-center gap-2 text-warning">
                            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" y1="8" x2="12" y2="12" />
                                <line x1="12" y1="16" x2="12.01" y2="16" />
                            </svg>
                            External Services
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            {analysis.services.map(service => (
                                <div key={service.type} className="p-2 bg-background-secondary rounded text-sm">
                                    <span className="font-medium capitalize">{service.type}</span>
                                    <span className="text-foreground-muted ml-2">— {service.description}</span>
                                    <div className="text-xs mt-1 text-foreground-muted">
                                        Env vars needed: {service.required_env_vars.join(', ')}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Additional Compose Services (not DB, not main app — e.g. hummingbot) */}
            {!hasDockerComposeApp && extraComposeServices.length > 0 && (
                <Card className="border-warning/30">
                    <CardHeader>
                        <CardTitle className="text-base flex items-center gap-2 text-warning">
                            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" y1="8" x2="12" y2="12" />
                                <line x1="12" y1="16" x2="12.01" y2="16" />
                            </svg>
                            Additional Compose Services
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            {extraComposeServices.map(service => (
                                <div
                                    key={service.name}
                                    className="flex items-center gap-2 p-2 bg-background-secondary rounded text-sm"
                                >
                                    <span>📦</span>
                                    <div className="min-w-0">
                                        <span className="font-medium">{service.name}</span>
                                        <span className="text-foreground-muted ml-2 text-xs">{service.image}</span>
                                        {service.ports.length > 0 && (
                                            <span className="text-foreground-muted ml-2 text-xs">({service.ports.join(', ')})</span>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                        <p className="text-xs text-foreground-muted mt-2">
                            These services were found in docker-compose.yml but won't be deployed automatically.
                            Deploy them separately or use external providers.
                        </p>
                    </CardContent>
                </Card>
            )}

            {/* Deployment Summary + Actions */}
            <Card className="border-primary/30">
                <CardContent className="py-4">
                    <div className="text-sm font-medium mb-3">Deployment Summary</div>
                    <div className="space-y-1.5 text-sm mb-4">
                        {analysis.applications.filter(a => selectedApps[a.name]).map(app => {
                            const cfg = appConfigs[app.name];
                            const mode = cfg?.application_type || 'web';
                            return (
                                <div key={app.name} className="flex items-center gap-2">
                                    <svg className="h-4 w-4 text-success flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg>
                                    <span>
                                        <span className="font-medium">{app.name}</span>
                                        <span className="text-foreground-muted">
                                            {' '}— {frameworkLabels[app.framework] || app.framework}
                                            {mode === 'worker' && ', no domain'}
                                            {mode === 'web' && `, port ${app.default_port || 80}`}
                                            {mode === 'both' && `, port ${app.default_port || 80} + worker`}
                                        </span>
                                    </span>
                                </div>
                            );
                        })}
                        {standaloneDbs.filter(db => selectedDbs[db.type]).map(db => (
                            <div key={db.type} className="flex items-center gap-2">
                                <svg className="h-4 w-4 text-success flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                                <span>
                                    <span className="font-medium capitalize">{db.type}</span>
                                    <span className="text-foreground-muted"> — managed database</span>
                                </span>
                            </div>
                        ))}
                        {analysis.applications.some(a => selectedApps[a.name] && (appConfigs[a.name]?.application_type ?? a.application_mode) === 'worker') && (
                            <div className="flex items-center gap-2 text-foreground-muted">
                                <svg className="h-4 w-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="12" y1="8" x2="12" y2="12" />
                                    <line x1="12" y1="16" x2="12.01" y2="16" />
                                </svg>
                                <span>Worker apps run without domain or health check (container stability only)</span>
                            </div>
                        )}
                    </div>

                    <div className="flex gap-3">
                        <Button
                            onClick={provisionInfrastructure}
                            loading={provisioning}
                            disabled={!hasSelectedApps}
                        >
                            {provisioning
                                ? 'Deploying...'
                                : `Deploy${selectedAppCount > 0 ? ` ${selectedAppCount} app${selectedAppCount > 1 ? 's' : ''}` : ''}${selectedDbCount > 0 ? ` + ${selectedDbCount} db${selectedDbCount > 1 ? 's' : ''}` : ''}`
                            }
                        </Button>
                    </div>

                    {error && (
                        <div className="flex items-center gap-2 text-danger mt-3">
                            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" y1="8" x2="12" y2="12" />
                                <line x1="12" y1="16" x2="12.01" y2="16" />
                            </svg>
                            <span>{error}</span>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
