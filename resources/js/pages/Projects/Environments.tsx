import { useState } from 'react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, CardHeader, CardTitle, Button, Badge, Input, useConfirm } from '@/components/ui';
import {
    Plus, Copy, ArrowRight, GitBranch, CheckCircle,
    XCircle, RefreshCw, Download, Upload
} from 'lucide-react';

interface Props {
    project: {
        id: number;
        uuid: string;
        name: string;
    };
}

interface Environment {
    id: number;
    name: string;
    slug: string;
    description?: string;
    variables: EnvironmentVariable[];
}

interface EnvironmentVariable {
    key: string;
    value: string;
    isSecret: boolean;
    existsIn: string[]; // List of environment slugs where this variable exists
}

// Mock environments data
const mockEnvironments: Environment[] = [
    {
        id: 1,
        name: 'Production',
        slug: 'production',
        description: 'Production environment',
        variables: [
            { key: 'DATABASE_URL', value: 'postgresql://prod-db', isSecret: true, existsIn: ['production', 'staging'] },
            { key: 'NODE_ENV', value: 'production', isSecret: false, existsIn: ['production', 'staging', 'development'] },
            { key: 'API_URL', value: 'https://api.example.com', isSecret: false, existsIn: ['production', 'staging', 'development'] },
            { key: 'STRIPE_SECRET_KEY', value: 'sk_live_xxx', isSecret: true, existsIn: ['production'] },
            { key: 'LOG_LEVEL', value: 'error', isSecret: false, existsIn: ['production'] },
        ],
    },
    {
        id: 2,
        name: 'Staging',
        slug: 'staging',
        description: 'Staging environment for testing',
        variables: [
            { key: 'DATABASE_URL', value: 'postgresql://staging-db', isSecret: true, existsIn: ['production', 'staging'] },
            { key: 'NODE_ENV', value: 'staging', isSecret: false, existsIn: ['production', 'staging', 'development'] },
            { key: 'API_URL', value: 'https://staging-api.example.com', isSecret: false, existsIn: ['production', 'staging', 'development'] },
            { key: 'STRIPE_SECRET_KEY', value: 'sk_test_xxx', isSecret: true, existsIn: ['staging'] },
            { key: 'LOG_LEVEL', value: 'info', isSecret: false, existsIn: ['staging'] },
        ],
    },
    {
        id: 3,
        name: 'Development',
        slug: 'development',
        description: 'Local development environment',
        variables: [
            { key: 'DATABASE_URL', value: 'postgresql://localhost:5432/dev', isSecret: false, existsIn: ['development'] },
            { key: 'NODE_ENV', value: 'development', isSecret: false, existsIn: ['production', 'staging', 'development'] },
            { key: 'API_URL', value: 'http://localhost:3000', isSecret: false, existsIn: ['production', 'staging', 'development'] },
            { key: 'LOG_LEVEL', value: 'debug', isSecret: false, existsIn: ['development'] },
        ],
    },
];

export default function ProjectEnvironments({ project: propProject }: Props) {
    const project = propProject || {
        id: 1,
        uuid: 'project-uuid-123',
        name: 'My Project',
    };

    const [environments, setEnvironments] = useState<Environment[]>(mockEnvironments);
    const [selectedEnvs, setSelectedEnvs] = useState<[string, string]>(['production', 'staging']);
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isSyncModalOpen, setIsSyncModalOpen] = useState(false);

    // Get all unique variables across selected environments
    const getAllVariables = () => {
        const envs = environments.filter((e) => selectedEnvs.includes(e.slug));
        const allKeys = new Set<string>();
        envs.forEach((env) => env.variables.forEach((v) => allKeys.add(v.key)));

        return Array.from(allKeys).map((key) => {
            const values: Record<string, string | undefined> = {};
            const isSecret = envs.some((env) =>
                env.variables.find((v) => v.key === key && v.isSecret)
            );

            envs.forEach((env) => {
                const variable = env.variables.find((v) => v.key === key);
                values[env.slug] = variable?.value;
            });

            return { key, values, isSecret };
        });
    };

    const variables = getAllVariables();

    return (
        <AppLayout
            title={`${project.name} - Environments`}
            breadcrumbs={[
                { label: 'Dashboard', href: '/dashboard' },
                { label: 'Projects', href: '/projects' },
                { label: project.name, href: `/projects/${project.uuid}` },
                { label: 'Environments' },
            ]}
        >
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Environment Comparison</h1>
                    <p className="mt-1 text-foreground-muted">
                        Compare and sync variables across environments
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="secondary" onClick={() => setIsSyncModalOpen(true)}>
                        <RefreshCw className="mr-2 h-4 w-4" />
                        Sync Variables
                    </Button>
                    <Button onClick={() => setIsCreateModalOpen(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Create Environment
                    </Button>
                </div>
            </div>

            {/* Environment Selector */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex items-center gap-4">
                        <span className="text-sm font-medium text-foreground">Compare:</span>
                        <select
                            value={selectedEnvs[0]}
                            onChange={(e) => setSelectedEnvs([e.target.value, selectedEnvs[1]])}
                            className="rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        >
                            {environments.map((env) => (
                                <option key={env.slug} value={env.slug}>
                                    {env.name}
                                </option>
                            ))}
                        </select>
                        <ArrowRight className="h-4 w-4 text-foreground-muted" />
                        <select
                            value={selectedEnvs[1]}
                            onChange={(e) => setSelectedEnvs([selectedEnvs[0], e.target.value])}
                            className="rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        >
                            {environments.map((env) => (
                                <option key={env.slug} value={env.slug}>
                                    {env.name}
                                </option>
                            ))}
                        </select>
                    </div>
                </CardContent>
            </Card>

            {/* Environments List */}
            <div className="mb-6 grid gap-4 md:grid-cols-3">
                {environments.map((env) => (
                    <Card key={env.id}>
                        <CardContent className="p-4">
                            <div className="flex items-start justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                        <GitBranch className="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <h3 className="font-medium text-foreground">{env.name}</h3>
                                        <p className="text-xs text-foreground-muted">
                                            {env.variables.length} variables
                                        </p>
                                    </div>
                                </div>
                                <Badge variant="default">{env.slug}</Badge>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {/* Variables Comparison Table */}
            <Card>
                <CardHeader>
                    <CardTitle>Environment Variables Comparison</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="border-b border-border">
                                    <th className="pb-3 pr-4 text-left text-sm font-medium text-foreground">
                                        Variable
                                    </th>
                                    {selectedEnvs.map((slug) => {
                                        const env = environments.find((e) => e.slug === slug);
                                        return (
                                            <th
                                                key={slug}
                                                className="pb-3 px-4 text-left text-sm font-medium text-foreground"
                                            >
                                                {env?.name}
                                            </th>
                                        );
                                    })}
                                    <th className="pb-3 pl-4 text-left text-sm font-medium text-foreground">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {variables.map((variable, index) => {
                                    const isDifferent =
                                        variable.values[selectedEnvs[0]] !==
                                        variable.values[selectedEnvs[1]];

                                    return (
                                        <tr
                                            key={variable.key}
                                            className={`border-b border-border ${
                                                index % 2 === 0 ? 'bg-background' : 'bg-background-secondary'
                                            }`}
                                        >
                                            <td className="py-3 pr-4">
                                                <div className="flex items-center gap-2">
                                                    <code className="text-sm font-medium text-foreground">
                                                        {variable.key}
                                                    </code>
                                                    {variable.isSecret && (
                                                        <Badge variant="warning" className="text-xs">
                                                            Secret
                                                        </Badge>
                                                    )}
                                                </div>
                                            </td>
                                            {selectedEnvs.map((slug) => (
                                                <td key={slug} className="py-3 px-4">
                                                    {variable.values[slug] ? (
                                                        <div className="flex items-center gap-2">
                                                            <code className="text-sm text-foreground-muted">
                                                                {variable.isSecret
                                                                    ? '••••••••••••'
                                                                    : variable.values[slug]}
                                                            </code>
                                                            {isDifferent && (
                                                                <AlertBadge
                                                                    slug={slug}
                                                                    value={variable.values[slug]}
                                                                />
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <span className="text-sm text-foreground-subtle">
                                                            Not set
                                                        </span>
                                                    )}
                                                </td>
                                            ))}
                                            <td className="py-3 pl-4">
                                                <div className="flex items-center gap-1">
                                                    {isDifferent && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-7 px-2"
                                                        >
                                                            <RefreshCw className="h-3 w-3" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            {/* Create Environment Modal */}
            {isCreateModalOpen && (
                <CreateEnvironmentModal
                    onClose={() => setIsCreateModalOpen(false)}
                    onCreate={(env) => {
                        setEnvironments((prev) => [...prev, env]);
                        setIsCreateModalOpen(false);
                    }}
                    existingSlugs={environments.map((e) => e.slug)}
                />
            )}

            {/* Sync Variables Modal */}
            {isSyncModalOpen && (
                <SyncVariablesModal
                    environments={environments}
                    onClose={() => setIsSyncModalOpen(false)}
                    onSync={() => {
                        setIsSyncModalOpen(false);
                    }}
                />
            )}
        </AppLayout>
    );
}

function AlertBadge({ slug, value }: { slug: string; value?: string }) {
    return value ? (
        <XCircle className="h-3 w-3 text-warning" title="Different value" />
    ) : (
        <XCircle className="h-3 w-3 text-danger" title="Not set" />
    );
}

interface CreateEnvironmentModalProps {
    onClose: () => void;
    onCreate: (env: Environment) => void;
    existingSlugs: string[];
}

function CreateEnvironmentModal({ onClose, onCreate, existingSlugs }: CreateEnvironmentModalProps) {
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [cloneFrom, setCloneFrom] = useState<string>('');

    const slug = name.toLowerCase().replace(/\s+/g, '-');

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!name || existingSlugs.includes(slug)) return;

        const env: Environment = {
            id: Date.now(),
            name,
            slug,
            description,
            variables: [],
        };

        onCreate(env);
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div className="w-full max-w-md rounded-lg border border-border bg-background p-6 shadow-xl">
                <h2 className="text-xl font-semibold text-foreground">Create Environment</h2>
                <form onSubmit={handleSubmit} className="mt-4 space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-foreground mb-2">
                            Environment Name
                        </label>
                        <Input
                            type="text"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="Production"
                            required
                        />
                        {name && (
                            <p className="mt-1 text-xs text-foreground-muted">
                                Slug: {slug}
                                {existingSlugs.includes(slug) && (
                                    <span className="text-danger"> (already exists)</span>
                                )}
                            </p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-foreground mb-2">
                            Description (optional)
                        </label>
                        <Input
                            type="text"
                            value={description}
                            onChange={(e) => setDescription(e.target.value)}
                            placeholder="Production environment"
                        />
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={!name || existingSlugs.includes(slug)}>
                            Create Environment
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

interface SyncVariablesModalProps {
    environments: Environment[];
    onClose: () => void;
    onSync: () => void;
}

function SyncVariablesModal({ environments, onClose, onSync }: SyncVariablesModalProps) {
    const confirm = useConfirm();
    const [sourceEnv, setSourceEnv] = useState(environments[0]?.slug || '');
    const [targetEnv, setTargetEnv] = useState(environments[1]?.slug || '');

    const handleSync = async () => {
        const confirmed = await confirm({
            title: 'Sync Variables',
            description: `This will copy all variables from ${sourceEnv} to ${targetEnv}, overwriting any existing values.`,
            confirmText: 'Sync',
            variant: 'warning',
        });
        if (confirmed) {
            onSync();
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div className="w-full max-w-md rounded-lg border border-border bg-background p-6 shadow-xl">
                <h2 className="text-xl font-semibold text-foreground">Sync Variables</h2>
                <div className="mt-4 space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-foreground mb-2">
                            Source Environment
                        </label>
                        <select
                            value={sourceEnv}
                            onChange={(e) => setSourceEnv(e.target.value)}
                            className="w-full rounded-md border border-border bg-background px-3 py-2 text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        >
                            {environments.map((env) => (
                                <option key={env.slug} value={env.slug}>
                                    {env.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-foreground mb-2">
                            Target Environment
                        </label>
                        <select
                            value={targetEnv}
                            onChange={(e) => setTargetEnv(e.target.value)}
                            className="w-full rounded-md border border-border bg-background px-3 py-2 text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        >
                            {environments.map((env) => (
                                <option key={env.slug} value={env.slug}>
                                    {env.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="rounded-lg border border-warning/20 bg-warning/10 p-3">
                        <p className="text-xs text-foreground-muted">
                            This will copy all variables from <strong>{sourceEnv}</strong> to{' '}
                            <strong>{targetEnv}</strong>, overwriting any existing values.
                        </p>
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button onClick={handleSync} disabled={sourceEnv === targetEnv}>
                            Sync Variables
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}
