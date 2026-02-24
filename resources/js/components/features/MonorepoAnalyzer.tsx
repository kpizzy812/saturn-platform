import { useState, useEffect, useRef } from 'react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge } from '@/components/ui/Badge';
import { DeployGuide } from '@/components/features/DeployGuide';
import axios from 'axios';

interface DetectedHealthCheck {
    path: string;
    method: string;
    interval: number;
    timeout: number;
}

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
    is_required: boolean;
    category: string;
    for_app: string;
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

const frameworkIcons: Record<string, string> = {
    nestjs: '🦁',
    nextjs: '▲',
    nuxt: '💚',
    express: '⚡',
    fastapi: '🚀',
    django: '🎸',
    rails: '💎',
    'go-fiber': '🐹',
    'go-gin': '🐹',
    'go-echo': '🐹',
    rust: '🦀',
    laravel: '🔴',
    symfony: '⚫',
    phoenix: '🔥',
    'spring-boot': '☕',
    'vite-react': '⚛️',
    'vite-vue': '💚',
    astro: '🚀',
    remix: '💿',
    sveltekit: '🔶',
};

const databaseIcons: Record<string, string> = {
    postgresql: '🐘',
    mysql: '🐬',
    mongodb: '🍃',
    redis: '🔴',
    clickhouse: '🏠',
};

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
    const [appConfigs, setAppConfigs] = useState<Record<string, { base_directory: string; env_vars: Array<{ key: string; value: string }> }>>({});
    const [error, setError] = useState<string | null>(null);
    const autoStarted = useRef(false);

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

            // Pre-select all apps and databases
            const apps: Record<string, boolean> = {};
            const configs: Record<string, { base_directory: string; env_vars: Array<{ key: string; value: string }> }> = {};
            result.applications.forEach(app => {
                apps[app.name] = true;
                // Build env vars from detected env_variables for this app
                const appEnvVars = result.env_variables
                    .filter(v => v.for_app === app.name && v.category !== 'database' && v.category !== 'cache')
                    .map(v => ({ key: v.key, value: '' }));
                configs[app.name] = {
                    base_directory: app.path === '.' ? '/' : '/' + app.path.replace(/^\//, ''),
                    env_vars: appEnvVars,
                };
            });
            setSelectedApps(apps);
            setAppConfigs(configs);

            const dbs: Record<string, boolean> = {};
            result.databases.forEach(db => {
                dbs[db.type] = true;
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

        try {
            const response = await axios.post('/git/provision', {
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
                    env_vars: (appConfigs[app.name]?.env_vars ?? []).filter(v => v.key && v.value),
                })),
                databases: analysis.databases.map(db => ({
                    type: db.type,
                    enabled: selectedDbs[db.type] ?? false,
                })),
            });

            if (!response.data.success) {
                throw new Error(response.data.error || 'Provisioning failed');
            }

            onComplete(response.data.data);
        } catch (err: unknown) {
            const axiosError = err as { response?: { data?: { error?: string } }; message?: string };
            const message = axiosError.response?.data?.error || axiosError.message || 'Failed to provision infrastructure';
            setError(message);
        } finally {
            setProvisioning(false);
        }
    };

    const getFrameworkIcon = (framework: string) => {
        return frameworkIcons[framework] || '📦';
    };

    const getDatabaseIcon = (type: string) => {
        return databaseIcons[type] || '💾';
    };

    const getAppTypeBadge = (type: string) => {
        switch (type) {
            case 'backend':
                return <Badge variant="secondary" size="sm" className="ml-2">Backend</Badge>;
            case 'frontend':
                return <Badge variant="outline" size="sm" className="ml-2">Frontend</Badge>;
            case 'fullstack':
                return <Badge variant="primary" size="sm" className="ml-2">Full-stack</Badge>;
            default:
                return null;
        }
    };

    const hasSelectedApps = Object.values(selectedApps).some(v => v);

    // Initial state - show analyze button
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

    // Analysis complete - show results
    return (
        <div className="space-y-6">
            {/* Monorepo Info */}
            {analysis.is_monorepo && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M16.5 9.4l-9-5.19M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z" />
                            </svg>
                            Monorepo Detected
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Badge variant="secondary">{analysis.monorepo_type}</Badge>
                    </CardContent>
                </Card>
            )}

            {/* CI/CD Configuration */}
            {analysis.ci_config && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <circle cx="12" cy="12" r="3" />
                                <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z" />
                            </svg>
                            CI/CD Configuration Detected
                            {analysis.ci_config.detected_from && (
                                <Badge variant="secondary" size="sm">{analysis.ci_config.detected_from}</Badge>
                            )}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 gap-3 text-sm">
                            {analysis.ci_config.install_command && (
                                <div>
                                    <div className="text-foreground-muted">Install</div>
                                    <code className="text-xs bg-background-secondary px-2 py-1 rounded">
                                        {analysis.ci_config.install_command}
                                    </code>
                                </div>
                            )}
                            {analysis.ci_config.build_command && (
                                <div>
                                    <div className="text-foreground-muted">Build</div>
                                    <code className="text-xs bg-background-secondary px-2 py-1 rounded">
                                        {analysis.ci_config.build_command}
                                    </code>
                                </div>
                            )}
                            {analysis.ci_config.test_command && (
                                <div>
                                    <div className="text-foreground-muted">Test</div>
                                    <code className="text-xs bg-background-secondary px-2 py-1 rounded">
                                        {analysis.ci_config.test_command}
                                    </code>
                                </div>
                            )}
                            {analysis.ci_config.node_version && (
                                <div>
                                    <div className="text-foreground-muted">Node.js</div>
                                    <Badge variant="outline" size="sm">v{analysis.ci_config.node_version}</Badge>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Applications */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <rect x="2" y="2" width="20" height="8" rx="2" />
                            <rect x="2" y="14" width="20" height="8" rx="2" />
                        </svg>
                        Applications ({analysis.applications.length})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="space-y-3">
                        {analysis.applications.map(app => {
                            const appDep = analysis.app_dependencies?.find(d => d.app_name === app.name);
                            return (
                                <div
                                    key={app.name}
                                    className="p-3 border border-white/[0.06] rounded-lg"
                                >
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-3">
                                            <Checkbox
                                                checked={selectedApps[app.name] ?? false}
                                                onCheckedChange={(checked) =>
                                                    setSelectedApps(prev => ({
                                                        ...prev,
                                                        [app.name]: checked,
                                                    }))
                                                }
                                            />
                                            <span className="text-2xl">
                                                {getFrameworkIcon(app.framework)}
                                            </span>
                                            <div>
                                                <div className="font-medium flex items-center">
                                                    {app.name}
                                                    {getAppTypeBadge(app.type)}
                                                    {appDep && appDep.deploy_order !== undefined && (
                                                        <Badge variant="outline" size="sm" className="ml-2">
                                                            Deploy #{appDep.deploy_order + 1}
                                                        </Badge>
                                                    )}
                                                </div>
                                                <div className="text-sm text-foreground-muted">
                                                    {app.path} • {app.framework} • Port {app.default_port}
                                                </div>
                                            </div>
                                        </div>
                                        <Badge variant="outline">{app.build_pack}</Badge>
                                    </div>

                                    {/* App Details */}
                                    <div className="mt-3 pl-10 space-y-2">
                                        {/* Health Check */}
                                        {app.health_check && (
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

                                        {/* Build Command */}
                                        {app.build_command && (
                                            <div className="flex items-center gap-2 text-sm">
                                                <svg className="h-4 w-4 text-foreground-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                                    <polyline points="4 17 10 11 4 5" />
                                                    <line x1="12" y1="19" x2="20" y2="19" />
                                                </svg>
                                                <span className="text-foreground-muted">Build:</span>
                                                <code className="text-xs bg-background-secondary px-2 py-0.5 rounded">
                                                    {app.build_command}
                                                </code>
                                            </div>
                                        )}

                                        {/* Dependencies */}
                                        {appDep && appDep.depends_on.length > 0 && (
                                            <div className="flex items-center gap-2 text-sm">
                                                <svg className="h-4 w-4 text-foreground-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                                    <circle cx="12" cy="12" r="10" />
                                                    <polyline points="12 6 12 12 16 14" />
                                                </svg>
                                                <span className="text-foreground-muted">Depends on:</span>
                                                {appDep.depends_on.map(dep => (
                                                    <Badge key={dep} variant="secondary" size="sm">{dep}</Badge>
                                                ))}
                                            </div>
                                        )}

                                        {/* Runtime version */}
                                        {(app.node_version || app.python_version) && (
                                            <div className="flex items-center gap-2 text-sm">
                                                <svg className="h-4 w-4 text-foreground-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                                                    <line x1="9" y1="9" x2="15" y2="15" />
                                                    <line x1="15" y1="9" x2="9" y2="15" />
                                                </svg>
                                                <span className="text-foreground-muted">Runtime:</span>
                                                {app.node_version && <Badge variant="outline" size="sm">Node {app.node_version}</Badge>}
                                                {app.python_version && <Badge variant="outline" size="sm">Python {app.python_version}</Badge>}
                                            </div>
                                        )}

                                        {/* Dockerfile Info */}
                                        {app.dockerfile_info && (
                                            <div className="space-y-1 mt-2 pt-2 border-t border-white/[0.06]">
                                                <div className="flex items-center gap-2 text-sm">
                                                    <svg className="h-4 w-4 text-blue-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                                        <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z" />
                                                    </svg>
                                                    <span className="text-foreground-muted">Dockerfile</span>
                                                </div>
                                                {app.dockerfile_info.base_image && (
                                                    <div className="flex items-center gap-2 text-sm pl-6">
                                                        <span className="text-foreground-muted">Base:</span>
                                                        <code className="text-xs bg-background-secondary px-2 py-0.5 rounded">
                                                            {app.dockerfile_info.base_image}
                                                        </code>
                                                    </div>
                                                )}
                                                {app.dockerfile_info.workdir && (
                                                    <div className="flex items-center gap-2 text-sm pl-6">
                                                        <span className="text-foreground-muted">Workdir:</span>
                                                        <code className="text-xs bg-background-secondary px-2 py-0.5 rounded">
                                                            {app.dockerfile_info.workdir}
                                                        </code>
                                                    </div>
                                                )}
                                                {app.dockerfile_info.cmd && (
                                                    <div className="flex items-center gap-2 text-sm pl-6">
                                                        <span className="text-foreground-muted">CMD:</span>
                                                        <code className="text-xs bg-background-secondary px-2 py-0.5 rounded">
                                                            {app.dockerfile_info.cmd}
                                                        </code>
                                                    </div>
                                                )}
                                                {app.dockerfile_info.exposed_ports && app.dockerfile_info.exposed_ports.length > 0 && (
                                                    <div className="flex items-center gap-2 text-sm pl-6">
                                                        <span className="text-foreground-muted">Ports:</span>
                                                        {app.dockerfile_info.exposed_ports.map(port => (
                                                            <Badge key={port} variant="secondary" size="sm">{port}</Badge>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        )}

                                        {/* Editable Config */}
                                        {selectedApps[app.name] && appConfigs[app.name] && (
                                            <div className="mt-3 pt-3 border-t border-white/[0.06] space-y-3">
                                                {/* Base Directory */}
                                                <div className="flex items-center gap-2">
                                                    <label className="text-sm text-foreground-muted whitespace-nowrap w-24">Base Dir</label>
                                                    <Input
                                                        value={appConfigs[app.name].base_directory}
                                                        onChange={(e) => setAppConfigs(prev => ({
                                                            ...prev,
                                                            [app.name]: { ...prev[app.name], base_directory: e.target.value },
                                                        }))}
                                                        placeholder="/"
                                                        className="h-8 text-sm"
                                                    />
                                                </div>

                                                {/* Environment Variables */}
                                                {appConfigs[app.name].env_vars.length > 0 && (
                                                    <div className="space-y-2">
                                                        <label className="text-sm text-foreground-muted">Environment Variables</label>
                                                        {appConfigs[app.name].env_vars.map((envVar, idx) => (
                                                            <div key={idx} className="flex items-center gap-2">
                                                                <Input
                                                                    value={envVar.key}
                                                                    onChange={(e) => {
                                                                        const newVars = [...appConfigs[app.name].env_vars];
                                                                        newVars[idx] = { ...newVars[idx], key: e.target.value };
                                                                        setAppConfigs(prev => ({
                                                                            ...prev,
                                                                            [app.name]: { ...prev[app.name], env_vars: newVars },
                                                                        }));
                                                                    }}
                                                                    placeholder="KEY"
                                                                    className="h-8 text-sm font-mono w-2/5"
                                                                />
                                                                <span className="text-foreground-muted">=</span>
                                                                <Input
                                                                    value={envVar.value}
                                                                    onChange={(e) => {
                                                                        const newVars = [...appConfigs[app.name].env_vars];
                                                                        newVars[idx] = { ...newVars[idx], value: e.target.value };
                                                                        setAppConfigs(prev => ({
                                                                            ...prev,
                                                                            [app.name]: { ...prev[app.name], env_vars: newVars },
                                                                        }));
                                                                    }}
                                                                    placeholder="value"
                                                                    className="h-8 text-sm font-mono flex-1"
                                                                />
                                                                <button
                                                                    type="button"
                                                                    onClick={() => {
                                                                        const newVars = appConfigs[app.name].env_vars.filter((_, i) => i !== idx);
                                                                        setAppConfigs(prev => ({
                                                                            ...prev,
                                                                            [app.name]: { ...prev[app.name], env_vars: newVars },
                                                                        }));
                                                                    }}
                                                                    className="text-foreground-muted hover:text-danger text-sm px-1"
                                                                >
                                                                    &times;
                                                                </button>
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
                                </div>
                            );
                        })}
                    </div>
                </CardContent>
            </Card>

            {/* Docker Compose Services */}
            {analysis.docker_compose_services && analysis.docker_compose_services.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z" />
                            </svg>
                            Docker Compose Services ({analysis.docker_compose_services.length})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-2">
                            {analysis.docker_compose_services.map(service => (
                                <div
                                    key={service.name}
                                    className="p-2 border border-white/[0.06] rounded text-sm"
                                >
                                    <div className="font-medium flex items-center gap-2">
                                        {service.is_database ? '💾' : '📦'} {service.name}
                                    </div>
                                    <div className="text-xs text-foreground-muted truncate">
                                        {service.image}
                                    </div>
                                    {service.ports.length > 0 && (
                                        <div className="text-xs text-foreground-muted">
                                            Ports: {service.ports.join(', ')}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                        <p className="text-sm text-foreground-muted mt-3">
                            Database services from docker-compose.yml will be created automatically.
                        </p>
                    </CardContent>
                </Card>
            )}

            {/* Databases */}
            {analysis.databases.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <ellipse cx="12" cy="5" rx="9" ry="3" />
                                <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3" />
                                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5" />
                            </svg>
                            Required Databases ({analysis.databases.length})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {analysis.databases.map(db => (
                                <div
                                    key={db.type}
                                    className="flex items-center justify-between p-3 border border-white/[0.06] rounded-lg"
                                >
                                    <div className="flex items-center gap-3">
                                        <Checkbox
                                            checked={selectedDbs[db.type] ?? false}
                                            onCheckedChange={(checked) =>
                                                setSelectedDbs(prev => ({
                                                    ...prev,
                                                    [db.type]: checked,
                                                }))
                                            }
                                        />
                                        <span className="text-2xl">
                                            {getDatabaseIcon(db.type)}
                                        </span>
                                        <div>
                                            <div className="font-medium capitalize">
                                                {db.type}
                                            </div>
                                            <div className="text-sm text-foreground-muted">
                                                Used by: {db.consumers.join(', ')}
                                            </div>
                                        </div>
                                    </div>
                                    <Badge variant="outline">{db.env_var_name}</Badge>
                                </div>
                            ))}
                        </div>
                        <p className="text-sm text-foreground-muted mt-3">
                            Connection URLs will be automatically injected into your applications.
                        </p>
                    </CardContent>
                </Card>
            )}

            {/* External Services Warning */}
            {analysis.services.length > 0 && (
                <Card className="border-warning/30">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-warning">
                            <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" y1="8" x2="12" y2="12" />
                                <line x1="12" y1="16" x2="12.01" y2="16" />
                            </svg>
                            External Services Detected
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-foreground-muted mb-3">
                            These services need to be configured manually:
                        </p>
                        <div className="space-y-2">
                            {analysis.services.map(service => (
                                <div key={service.type} className="p-2 bg-background-secondary rounded">
                                    <div className="font-medium capitalize">{service.type}</div>
                                    <div className="text-sm text-foreground-muted">
                                        {service.description}
                                    </div>
                                    <div className="text-xs mt-1 text-foreground-muted">
                                        Required: {service.required_env_vars.join(', ')}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Actions */}
            <div className="flex gap-3">
                <Button variant="outline" onClick={() => setAnalysis(null)}>
                    Re-analyze
                </Button>
                <Button
                    onClick={provisionInfrastructure}
                    loading={provisioning}
                    disabled={!hasSelectedApps}
                >
                    {provisioning ? 'Provisioning...' : 'Deploy Selected'}
                </Button>
            </div>

            {error && (
                <div className="flex items-center gap-2 text-danger">
                    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                    <span>{error}</span>
                </div>
            )}
        </div>
    );
}
