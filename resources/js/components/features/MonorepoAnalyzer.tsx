import { useState, useEffect, useRef } from 'react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge } from '@/components/ui/Badge';
import { DeployGuide } from '@/components/features/DeployGuide';
import { BrandIcon } from '@/components/ui/BrandIcon';
import { getDbLogo, getDbBgColor } from '@/components/features/Projects/DatabaseLogos';
import {
    Github,
    GitBranch,
    ChevronDown,
    Package,
    AlertCircle,
    CheckCircle2,
    Clock,
    Plus,
    X,
    Heart,
    Layers,
    Server,
    Code2,
    Cog,
    RefreshCw,
    Rocket,
    Database,
    Globe,
    Cpu,
    FileCode,
} from 'lucide-react';
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
    dockerfile_location?: string | null;
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

// Framework → brand icon name (for frameworks that have SVGs in /svgs/)
const frameworkBrandIcons: Record<string, string> = {
    dockerfile: 'docker',
    'docker-compose': 'docker',
};

// Framework → lucide icon (fallback for frameworks without brand SVGs)
function getFrameworkIcon(framework: string, className = 'h-3.5 w-3.5') {
    const brandName = frameworkBrandIcons[framework];
    if (brandName) {
        return <BrandIcon name={brandName} className={className} />;
    }
    // Lucide fallback icons by category
    switch (framework) {
        case 'nextjs':
        case 'nuxt':
        case 'sveltekit':
        case 'remix':
        case 'astro':
        case 'vite-react':
        case 'vite-vue':
        case 'vite-svelte':
        case 'create-react-app':
            return <Globe className={className} />;
        case 'nestjs':
        case 'express':
        case 'fastify':
        case 'hono':
        case 'fastapi':
        case 'django':
        case 'flask':
        case 'rails':
        case 'laravel':
        case 'symfony':
        case 'phoenix':
        case 'spring-boot':
            return <Server className={className} />;
        case 'go':
        case 'go-fiber':
        case 'go-gin':
        case 'go-echo':
        case 'rust':
        case 'rust-axum':
        case 'rust-actix':
            return <Cpu className={className} />;
        case 'nixpacks':
            return <Layers className={className} />;
        case 'procfile':
            return <FileCode className={className} />;
        default:
            return <Code2 className={className} />;
    }
}

// Database type → color config for styled cards
const databaseColorConfig: Record<string, { border: string; bg: string; glow: string }> = {
    postgresql: { border: 'border-blue-500/30', bg: 'bg-blue-500/5', glow: 'hover:shadow-[0_0_20px_rgba(59,130,246,0.1)]' },
    mysql: { border: 'border-orange-500/30', bg: 'bg-orange-500/5', glow: 'hover:shadow-[0_0_20px_rgba(249,115,22,0.1)]' },
    mariadb: { border: 'border-amber-500/30', bg: 'bg-amber-500/5', glow: 'hover:shadow-[0_0_20px_rgba(245,158,11,0.1)]' },
    mongodb: { border: 'border-green-500/30', bg: 'bg-green-500/5', glow: 'hover:shadow-[0_0_20px_rgba(34,197,94,0.1)]' },
    redis: { border: 'border-red-500/30', bg: 'bg-red-500/5', glow: 'hover:shadow-[0_0_20px_rgba(239,68,68,0.1)]' },
    keydb: { border: 'border-rose-500/30', bg: 'bg-rose-500/5', glow: 'hover:shadow-[0_0_20px_rgba(244,63,94,0.1)]' },
    dragonfly: { border: 'border-purple-500/30', bg: 'bg-purple-500/5', glow: 'hover:shadow-[0_0_20px_rgba(168,85,247,0.1)]' },
    clickhouse: { border: 'border-yellow-500/30', bg: 'bg-yellow-500/5', glow: 'hover:shadow-[0_0_20px_rgba(234,179,8,0.1)]' },
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
    const [dbEnvVarNames, setDbEnvVarNames] = useState<Record<string, string>>({}); // db type → custom env var name
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

            // Initialize env var names from analysis
            const envNames: Record<string, string> = {};
            result.databases.forEach(db => {
                envNames[db.type] = db.env_var_name;
            });
            setDbEnvVarNames(envNames);
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
                inject_as: dbEnvVarNames[db.type] !== db.env_var_name ? dbEnvVarNames[db.type] : undefined,
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

    // Non-database compose services that aren't already detected as apps (e.g. hummingbot).
    // DB services are already shown in "Required Databases" section.
    const extraComposeServices = (analysis?.docker_compose_services ?? []).filter(
        s => !s.is_database && !analysis?.applications.some(a => a.name === s.name)
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
                                <AlertCircle className="h-4 w-4" />
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
            <Card variant="glass" className="group overflow-hidden relative">
                <div className="absolute inset-0 bg-gradient-to-r from-primary/5 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500" />
                <CardContent className="py-5 relative">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3.5">
                            <div className="relative flex-shrink-0">
                                <div className="h-10 w-10 rounded-full bg-foreground flex items-center justify-center ring-2 ring-white/10 group-hover:ring-primary/30 transition-all duration-300">
                                    <Github className="h-5.5 w-5.5 text-background" />
                                </div>
                                <div className="absolute -bottom-0.5 -right-0.5 h-3.5 w-3.5 rounded-full bg-success border-2 border-background-secondary" />
                            </div>
                            <div>
                                <h2 className="text-lg font-semibold group-hover:text-primary transition-colors duration-300">{repoName}</h2>
                                <div className="flex items-center gap-2 mt-1 text-sm text-foreground-muted">
                                    <span className="inline-flex items-center gap-1.5">
                                        <GitBranch className="h-3.5 w-3.5" />
                                        {branch}
                                    </span>
                                    {analysis.is_monorepo && (
                                        <>
                                            <span className="text-white/20">|</span>
                                            <Badge variant="primary" size="sm" icon={<Layers className="h-3 w-3" />}>
                                                Monorepo ({analysis.monorepo_type})
                                            </Badge>
                                        </>
                                    )}
                                </div>
                            </div>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setAnalysis(null)}
                            className="group/btn"
                        >
                            <RefreshCw className="h-3.5 w-3.5 mr-1.5 group-hover/btn:rotate-180 transition-transform duration-500" />
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
                const isSelected = selectedApps[app.name] ?? false;

                return (
                    <Card key={app.name} variant="glass" className={`group/app transition-all duration-300 ${isSelected ? 'border-primary/20 shadow-[0_0_20px_rgba(var(--color-primary-rgb,99,102,241),0.08)]' : 'hover:border-white/[0.12]'}`}>
                        <CardContent className="py-4">
                            {/* App Header */}
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <Checkbox
                                        checked={isSelected}
                                        onCheckedChange={(checked) =>
                                            setSelectedApps(prev => ({ ...prev, [app.name]: checked }))
                                        }
                                    />
                                    <div className="flex items-center gap-2.5">
                                        <div className="h-8 w-8 rounded-lg bg-white/[0.06] flex items-center justify-center group-hover/app:bg-white/[0.1] transition-colors duration-200">
                                            {getFrameworkIcon(app.framework, 'h-4 w-4')}
                                        </div>
                                        <div>
                                            <div className="font-medium flex items-center gap-2 flex-wrap">
                                                {app.name}
                                                <Badge variant="outline" size="sm" icon={getFrameworkIcon(app.framework, 'h-3 w-3')}>
                                                    {frameworkLabels[app.framework] || app.framework}
                                                </Badge>
                                                {config?.application_type === 'worker' && (
                                                    <Badge variant="warning" size="sm" icon={<Cog className="h-3 w-3" />}>Worker</Badge>
                                                )}
                                                {config?.application_type === 'both' && (
                                                    <Badge variant="primary" size="sm" icon={<Layers className="h-3 w-3" />}>Web + Worker</Badge>
                                                )}
                                                {app.type !== 'unknown' && (
                                                    <Badge variant="secondary" size="sm" className="capitalize">{app.type}</Badge>
                                                )}
                                                {appDep && appDep.deploy_order !== undefined && (
                                                    <Badge variant="outline" size="sm" icon={<Rocket className="h-3 w-3" />}>
                                                        Deploy #{appDep.deploy_order + 1}
                                                    </Badge>
                                                )}
                                            </div>
                                            <div className="text-sm text-foreground-muted mt-0.5 flex items-center gap-1.5">
                                                {isWorker
                                                    ? <><Cog className="h-3 w-3 animate-[spin_3s_linear_infinite]" /> No HTTP port — runs as background process</>
                                                    : <><Globe className="h-3 w-3" /> Port {app.default_port || 80}</>
                                                }
                                                {app.path !== '.' && <> <span className="text-white/20">|</span> {app.path}</>}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setExpandedApps(prev => ({ ...prev, [app.name]: !isExpanded }))}
                                    className="text-foreground-muted hover:text-foreground p-1.5 rounded-lg hover:bg-white/[0.06] transition-all duration-200"
                                >
                                    <ChevronDown className={`h-5 w-5 transition-transform duration-300 ${isExpanded ? 'rotate-180' : ''}`} />
                                </button>
                            </div>

                            {/* Expanded Details */}
                            {isExpanded && (
                                <div className="mt-4 space-y-4 border-t border-white/[0.06] pt-4 animate-[fadeIn_0.2s_ease-out]">
                                    {/* Docker Compose Services (inline) */}
                                    {isDockerCompose && analysis.docker_compose_services.length > 0 && (
                                        <div>
                                            <div className="text-sm font-medium text-foreground-muted mb-2 flex items-center gap-1.5">
                                                <Layers className="h-3.5 w-3.5" />
                                                Compose Services
                                            </div>
                                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                {analysis.docker_compose_services.map(service => (
                                                    <div
                                                        key={service.name}
                                                        className="flex items-center gap-2.5 p-2.5 bg-white/[0.04] backdrop-blur-sm rounded-lg text-sm hover:bg-white/[0.08] transition-colors duration-200 group/svc"
                                                    >
                                                        <div className="h-7 w-7 rounded-md bg-white/[0.06] flex items-center justify-center flex-shrink-0 group-hover/svc:bg-white/[0.1] transition-colors duration-200">
                                                            {service.is_database ? <Database className="h-3.5 w-3.5 text-blue-400" /> : <Package className="h-3.5 w-3.5 text-foreground-muted" />}
                                                        </div>
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
                                        <div className="space-y-1.5">
                                            <div className="text-sm font-medium text-foreground-muted flex items-center gap-1.5">
                                                <BrandIcon name="docker" className="h-3.5 w-3.5" />
                                                Dockerfile
                                            </div>
                                            <div className="grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm pl-2">
                                                {app.dockerfile_info.base_image && (
                                                    <div className="flex items-center gap-1.5">
                                                        <span className="text-foreground-muted">Base:</span>
                                                        <code className="text-xs bg-white/[0.06] px-1.5 py-0.5 rounded-md font-mono">{app.dockerfile_info.base_image}</code>
                                                    </div>
                                                )}
                                                {app.dockerfile_info.cmd && (
                                                    <div className="flex items-center gap-1.5">
                                                        <span className="text-foreground-muted">CMD:</span>
                                                        <code className="text-xs bg-white/[0.06] px-1.5 py-0.5 rounded-md font-mono">{app.dockerfile_info.cmd}</code>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {/* Health Check */}
                                    {app.health_check && !isWorker && (
                                        <div className="flex items-center gap-2 text-sm">
                                            <Heart className="h-4 w-4 text-success animate-pulse" />
                                            <span className="text-foreground-muted">Health:</span>
                                            <code className="text-xs bg-white/[0.06] px-2 py-0.5 rounded-md font-mono">
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
                                                        className="w-full h-8 text-sm bg-white/[0.04] backdrop-blur-sm border border-white/[0.08] rounded-lg px-2 hover:border-white/[0.12] transition-colors duration-200"
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
                                                        <div key={idx} className="p-2.5 bg-white/[0.04] backdrop-blur-sm rounded-lg space-y-1 border border-white/[0.04] hover:border-white/[0.08] transition-colors duration-200">
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
                                                                    className="text-foreground-muted hover:text-danger hover:bg-danger/10 rounded p-0.5 transition-colors duration-200 flex-shrink-0"
                                                                >
                                                                    <X className="h-3.5 w-3.5" />
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
                                                className="inline-flex items-center gap-1 text-xs text-primary hover:text-primary/80 hover:bg-primary/10 rounded-md px-2 py-1 transition-colors duration-200"
                                            >
                                                <Plus className="h-3 w-3" />
                                                Add env variable
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
                <Card variant="glass">
                    <CardHeader>
                        <CardTitle className="text-base flex items-center gap-2">
                            <Database className="h-4.5 w-4.5 text-primary" />
                            Required Databases
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            {standaloneDbs.map(db => {
                                const colors = databaseColorConfig[db.type] || { border: 'border-white/[0.06]', bg: 'bg-white/[0.02]', glow: '' };
                                const isDbSelected = selectedDbs[db.type] ?? false;
                                return (
                                    <div
                                        key={db.type}
                                        className={`flex items-center justify-between p-3 rounded-lg border transition-all duration-300 group/db
                                            ${isDbSelected ? `${colors.border} ${colors.bg} ${colors.glow}` : 'border-white/[0.06] hover:border-white/[0.12]'}`}
                                    >
                                        <div className="flex items-center gap-3">
                                            <Checkbox
                                                checked={isDbSelected}
                                                onCheckedChange={(checked) =>
                                                    setSelectedDbs(prev => ({ ...prev, [db.type]: checked }))
                                                }
                                            />
                                            <div className={`h-9 w-9 rounded-lg flex items-center justify-center transition-all duration-300 ${getDbBgColor(db.type)} group-hover/db:scale-110`}>
                                                {getDbLogo(db.type)}
                                            </div>
                                            <div>
                                                <div className="font-medium capitalize">{db.type}</div>
                                                {db.consumers.length > 0 && (
                                                    <div className="text-sm text-foreground-muted">
                                                        Used by: {db.consumers.join(', ')}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-1.5">
                                            <Code2 className="h-3 w-3 text-foreground-muted flex-shrink-0" />
                                            <input
                                                type="text"
                                                value={dbEnvVarNames[db.type] ?? db.env_var_name}
                                                onChange={(e) => setDbEnvVarNames(prev => ({ ...prev, [db.type]: e.target.value.toUpperCase().replace(/[^A-Z0-9_]/g, '') }))}
                                                className="h-6 px-1.5 text-xs font-mono bg-transparent border border-white/[0.06] rounded w-32 text-right hover:border-white/[0.15] focus:border-primary focus:outline-none"
                                                title="Environment variable name for this database"
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                        <p className="text-xs text-foreground-muted mt-3 flex items-center gap-1.5">
                            <CheckCircle2 className="h-3.5 w-3.5 text-success" />
                            Saturn will create managed database instances. Connection URLs auto-injected.
                        </p>
                    </CardContent>
                </Card>
            )}

            {/* External Services Warning */}
            {analysis.services.length > 0 && (
                <Card variant="glass" className="border-warning/30">
                    <CardHeader>
                        <CardTitle className="text-base flex items-center gap-2 text-warning">
                            <AlertCircle className="h-4 w-4" />
                            External Services
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            {analysis.services.map(service => (
                                <div key={service.type} className="p-3 bg-white/[0.04] backdrop-blur-sm rounded-lg text-sm hover:bg-white/[0.08] transition-colors duration-200 group/ext">
                                    <div className="flex items-center gap-2">
                                        <Globe className="h-4 w-4 text-warning flex-shrink-0" />
                                        <span className="font-medium capitalize">{service.type}</span>
                                        <span className="text-foreground-muted">— {service.description}</span>
                                    </div>
                                    <div className="text-xs mt-1.5 text-foreground-muted flex items-center gap-1.5 pl-6">
                                        <Code2 className="h-3 w-3" />
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
                <Card variant="glass" className="border-warning/30">
                    <CardHeader>
                        <CardTitle className="text-base flex items-center gap-2 text-warning">
                            <AlertCircle className="h-4 w-4" />
                            Additional Compose Services
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            {extraComposeServices.map(service => (
                                <div
                                    key={service.name}
                                    className="flex items-center gap-2.5 p-3 bg-white/[0.04] backdrop-blur-sm rounded-lg text-sm hover:bg-white/[0.08] transition-colors duration-200 group/csvc"
                                >
                                    <div className="h-8 w-8 rounded-lg bg-white/[0.06] flex items-center justify-center flex-shrink-0 group-hover/csvc:bg-white/[0.1] transition-colors duration-200">
                                        <Package className="h-4 w-4 text-warning" />
                                    </div>
                                    <div className="min-w-0">
                                        <span className="font-medium">{service.name}</span>
                                        <span className="text-foreground-muted ml-2 text-xs">
                                            {service.image.startsWith('build:') ? `Dockerfile: ${service.image.replace('build:', '')}` : service.image}
                                        </span>
                                        {service.ports.length > 0 && (
                                            <span className="text-foreground-muted ml-2 text-xs">({service.ports.join(', ')})</span>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                        <p className="text-xs text-foreground-muted mt-3 flex items-center gap-1.5">
                            <Clock className="h-3.5 w-3.5" />
                            These services were found in docker-compose.yml but won't be deployed automatically.
                            Deploy them separately or use external providers.
                        </p>
                    </CardContent>
                </Card>
            )}

            {/* Deployment Summary + Actions */}
            <Card variant="glass" className="border-primary/30 overflow-hidden relative group/summary">
                <div className="absolute inset-0 bg-gradient-to-br from-primary/5 via-transparent to-transparent opacity-0 group-hover/summary:opacity-100 transition-opacity duration-500" />
                <CardContent className="py-5 relative">
                    <div className="text-sm font-medium mb-3 flex items-center gap-2">
                        <Rocket className="h-4 w-4 text-primary" />
                        Deployment Summary
                    </div>
                    <div className="space-y-2 text-sm mb-5">
                        {analysis.applications.filter(a => selectedApps[a.name]).map(app => {
                            const cfg = appConfigs[app.name];
                            const mode = cfg?.application_type || 'web';
                            return (
                                <div key={app.name} className="flex items-center gap-2.5 p-2 rounded-lg hover:bg-white/[0.03] transition-colors duration-200">
                                    <CheckCircle2 className="h-4 w-4 text-success flex-shrink-0" />
                                    <div className="h-6 w-6 rounded flex items-center justify-center bg-white/[0.06]">
                                        {getFrameworkIcon(app.framework, 'h-3.5 w-3.5')}
                                    </div>
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
                            <div key={db.type} className="flex items-center gap-2.5 p-2 rounded-lg hover:bg-white/[0.03] transition-colors duration-200">
                                <CheckCircle2 className="h-4 w-4 text-success flex-shrink-0" />
                                <div className={`h-6 w-6 rounded flex items-center justify-center ${getDbBgColor(db.type)}`}>
                                    {getDbLogo(db.type)}
                                </div>
                                <span>
                                    <span className="font-medium capitalize">{db.type}</span>
                                    <span className="text-foreground-muted"> — managed database</span>
                                </span>
                            </div>
                        ))}
                        {analysis.applications.some(a => selectedApps[a.name] && (appConfigs[a.name]?.application_type ?? a.application_mode) === 'worker') && (
                            <div className="flex items-center gap-2.5 p-2 text-foreground-muted rounded-lg">
                                <Clock className="h-4 w-4 flex-shrink-0" />
                                <span>Worker apps run without domain or health check (container stability only)</span>
                            </div>
                        )}
                    </div>

                    <div className="flex gap-3">
                        <Button
                            onClick={provisionInfrastructure}
                            loading={provisioning}
                            disabled={!hasSelectedApps}
                            className="group/deploy"
                        >
                            <Rocket className="h-4 w-4 mr-1.5 group-hover/deploy:-translate-y-0.5 group-hover/deploy:translate-x-0.5 transition-transform duration-300" />
                            {provisioning
                                ? 'Deploying...'
                                : `Deploy${selectedAppCount > 0 ? ` ${selectedAppCount} app${selectedAppCount > 1 ? 's' : ''}` : ''}${selectedDbCount > 0 ? ` + ${selectedDbCount} db${selectedDbCount > 1 ? 's' : ''}` : ''}`
                            }
                        </Button>
                    </div>

                    {error && (
                        <div className="flex items-center gap-2 text-danger mt-3">
                            <AlertCircle className="h-4 w-4" />
                            <span>{error}</span>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
