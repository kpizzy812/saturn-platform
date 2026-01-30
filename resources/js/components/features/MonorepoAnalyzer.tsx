import React, { useState } from 'react';
import { Button } from '@/components/ui/Button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge } from '@/components/ui/Badge';
import axios from 'axios';

interface DetectedApp {
    name: string;
    path: string;
    framework: string;
    build_pack: string;
    default_port: number;
    type: 'backend' | 'frontend' | 'fullstack' | 'unknown';
}

interface DetectedDatabase {
    type: string;
    name: string;
    env_var_name: string;
    consumers: string[];
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

interface AnalysisResult {
    is_monorepo: boolean;
    monorepo_type: string | null;
    applications: DetectedApp[];
    databases: DetectedDatabase[];
    services: DetectedService[];
    env_variables: EnvVariable[];
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
    environmentUuid: string;
    destinationUuid: string;
    onComplete: (result: ProvisionResult) => void;
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
    environmentUuid,
    destinationUuid,
    onComplete,
}: Props) {
    const [analyzing, setAnalyzing] = useState(false);
    const [provisioning, setProvisioning] = useState(false);
    const [analysis, setAnalysis] = useState<AnalysisResult | null>(null);
    const [selectedApps, setSelectedApps] = useState<Record<string, boolean>>({});
    const [selectedDbs, setSelectedDbs] = useState<Record<string, boolean>>({});
    const [error, setError] = useState<string | null>(null);

    const analyzeRepository = async () => {
        setAnalyzing(true);
        setError(null);

        try {
            const response = await axios.post('/api/v1/git/analyze', {
                git_repository: gitRepository,
                git_branch: gitBranch,
                private_key_id: privateKeyId,
                source_id: sourceId,
            });

            if (!response.data.success) {
                throw new Error(response.data.error || 'Analysis failed');
            }

            const result = response.data.data as AnalysisResult;
            setAnalysis(result);

            // Pre-select all apps and databases
            const apps: Record<string, boolean> = {};
            result.applications.forEach(app => {
                apps[app.name] = true;
            });
            setSelectedApps(apps);

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
            const response = await axios.post('/api/v1/git/provision', {
                environment_uuid: environmentUuid,
                destination_uuid: destinationUuid,
                git_repository: gitRepository,
                git_branch: gitBranch,
                private_key_id: privateKeyId,
                source_id: sourceId,
                applications: analysis.applications.map(app => ({
                    name: app.name,
                    enabled: selectedApps[app.name] ?? false,
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
                        {analysis.applications.map(app => (
                            <div
                                key={app.name}
                                className="flex items-center justify-between p-3 border border-white/[0.06] rounded-lg"
                            >
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
                                        </div>
                                        <div className="text-sm text-foreground-muted">
                                            {app.path} • {app.framework} • Port {app.default_port}
                                        </div>
                                    </div>
                                </div>
                                <Badge variant="outline">{app.build_pack}</Badge>
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>

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
