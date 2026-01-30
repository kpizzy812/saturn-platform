# Frontend UI

**Файл:** `resources/js/components/features/MonorepoAnalyzer.tsx`

## Компонент MonorepoAnalyzer

```tsx
import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Loader2, Database, Server, Package, AlertCircle, Globe, Cpu } from 'lucide-react';
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

interface AnalysisResult {
    is_monorepo: boolean;
    monorepo_type: string | null;
    applications: DetectedApp[];
    databases: DetectedDatabase[];
    services: DetectedService[];
    env_variables: Array<{
        key: string;
        is_required: boolean;
        category: string;
        for_app: string;
    }>;
}

interface Props {
    gitRepository: string;
    gitBranch?: string;
    privateKeyId?: number;
    sourceId?: number;
    environmentUuid: string;
    destinationUuid: string;
    onComplete: (result: any) => void;
}

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
        } catch (err: any) {
            const message = err.response?.data?.error || err.message || 'Failed to analyze repository';
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
        } catch (err: any) {
            const message = err.response?.data?.error || err.message || 'Failed to provision infrastructure';
            setError(message);
        } finally {
            setProvisioning(false);
        }
    };

    const getFrameworkIcon = (framework: string) => {
        const icons: Record<string, string> = {
            nestjs: '🦁',
            nextjs: '▲',
            nuxt: '💚',
            express: '⚡',
            fastapi: '🚀',
            django: '🎸',
            rails: '💎',
            go: '🐹',
            rust: '🦀',
        };
        return icons[framework] || '📦';
    };

    const getDatabaseIcon = (type: string) => {
        const icons: Record<string, string> = {
            postgresql: '🐘',
            mysql: '🐬',
            mongodb: '🍃',
            redis: '🔴',
            clickhouse: '🏠',
        };
        return icons[type] || '💾';
    };

    const getAppTypeBadge = (type: string) => {
        switch (type) {
            case 'backend':
                return <Badge variant="secondary" className="ml-2"><Cpu className="h-3 w-3 mr-1" />Backend</Badge>;
            case 'frontend':
                return <Badge variant="outline" className="ml-2"><Globe className="h-3 w-3 mr-1" />Frontend</Badge>;
            case 'fullstack':
                return <Badge className="ml-2">Full-stack</Badge>;
            default:
                return null;
        }
    };

    // Initial state - show analyze button
    if (!analysis) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Repository Analysis</CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-muted-foreground mb-4">
                        Saturn will analyze your repository to detect applications,
                        required databases, and configuration.
                    </p>

                    {error && (
                        <div className="flex items-center gap-2 text-destructive mb-4">
                            <AlertCircle className="h-4 w-4" />
                            <span>{error}</span>
                        </div>
                    )}

                    <Button onClick={analyzeRepository} disabled={analyzing}>
                        {analyzing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
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
                            <Package className="h-5 w-5" />
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
                        <Server className="h-5 w-5" />
                        Applications ({analysis.applications.length})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="space-y-3">
                        {analysis.applications.map(app => (
                            <div
                                key={app.name}
                                className="flex items-center justify-between p-3 border rounded-lg"
                            >
                                <div className="flex items-center gap-3">
                                    <Checkbox
                                        checked={selectedApps[app.name] ?? false}
                                        onCheckedChange={(checked) =>
                                            setSelectedApps(prev => ({
                                                ...prev,
                                                [app.name]: !!checked,
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
                                        <div className="text-sm text-muted-foreground">
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
                            <Database className="h-5 w-5" />
                            Required Databases ({analysis.databases.length})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {analysis.databases.map(db => (
                                <div
                                    key={db.type}
                                    className="flex items-center justify-between p-3 border rounded-lg"
                                >
                                    <div className="flex items-center gap-3">
                                        <Checkbox
                                            checked={selectedDbs[db.type] ?? false}
                                            onCheckedChange={(checked) =>
                                                setSelectedDbs(prev => ({
                                                    ...prev,
                                                    [db.type]: !!checked,
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
                                            <div className="text-sm text-muted-foreground">
                                                Used by: {db.consumers.join(', ')}
                                            </div>
                                        </div>
                                    </div>
                                    <Badge variant="outline">{db.env_var_name}</Badge>
                                </div>
                            ))}
                        </div>
                        <p className="text-sm text-muted-foreground mt-3">
                            Connection URLs will be automatically injected into your applications.
                        </p>
                    </CardContent>
                </Card>
            )}

            {/* External Services Warning */}
            {analysis.services.length > 0 && (
                <Card className="border-warning">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-warning">
                            <AlertCircle className="h-5 w-5" />
                            External Services Detected
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-muted-foreground mb-3">
                            These services need to be configured manually:
                        </p>
                        <div className="space-y-2">
                            {analysis.services.map(service => (
                                <div key={service.type} className="p-2 bg-muted rounded">
                                    <div className="font-medium capitalize">{service.type}</div>
                                    <div className="text-sm text-muted-foreground">
                                        {service.description}
                                    </div>
                                    <div className="text-xs mt-1">
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
                    disabled={provisioning || Object.values(selectedApps).every(v => !v)}
                >
                    {provisioning && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                    {provisioning ? 'Provisioning...' : 'Deploy Selected'}
                </Button>
            </div>

            {error && (
                <div className="flex items-center gap-2 text-destructive">
                    <AlertCircle className="h-4 w-4" />
                    <span>{error}</span>
                </div>
            )}
        </div>
    );
}
```

---

## Использование компонента

```tsx
// В Create Application flow
import { MonorepoAnalyzer } from '@/components/features/MonorepoAnalyzer';

function CreateApplicationPage() {
    const [step, setStep] = useState<'git' | 'analyze' | 'complete'>('git');
    const [gitUrl, setGitUrl] = useState('');

    const handleComplete = (result) => {
        // Redirect to project canvas or show success
        router.visit(`/projects/${projectUuid}/environments/${environmentUuid}`);
    };

    if (step === 'analyze') {
        return (
            <MonorepoAnalyzer
                gitRepository={gitUrl}
                gitBranch="main"
                environmentUuid={environmentUuid}
                destinationUuid={destinationUuid}
                onComplete={handleComplete}
            />
        );
    }

    // ... git input form
}
```
