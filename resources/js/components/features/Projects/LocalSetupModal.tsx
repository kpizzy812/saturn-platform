import { useState, useEffect, useCallback } from 'react';
import { Modal } from '@/components/ui/Modal';
import { useToast } from '@/components/ui/Toast';
import { Terminal, Copy, Check, GitBranch, Key, Database, AlertTriangle, Loader2, Eye, EyeOff } from 'lucide-react';
import type { Environment, Application, StandaloneDatabase, EnvironmentVariable } from '@/types';
import { getDbLogo, getDbBgColor } from './DatabaseLogos';

interface LocalSetupModalProps {
    isOpen: boolean;
    onClose: () => void;
    environment: Environment | null;
}

// Copy-to-clipboard button with feedback
function CopyButton({ text, label }: { text: string; label?: string }) {
    const [copied, setCopied] = useState(false);
    const { addToast } = useToast();

    const handleCopy = () => {
        navigator.clipboard.writeText(text);
        setCopied(true);
        addToast('success', 'Copied', label ? `${label} copied to clipboard` : 'Copied to clipboard');
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <button
            onClick={handleCopy}
            className="rounded p-1.5 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
            title={label || 'Copy'}
        >
            {copied ? <Check className="h-3.5 w-3.5 text-green-500" /> : <Copy className="h-3.5 w-3.5" />}
        </button>
    );
}

// Code block with copy button
function CodeBlock({ code, label }: { code: string; label?: string }) {
    return (
        <div className="group relative rounded-lg border border-border bg-background p-3">
            <pre className="overflow-x-auto text-sm text-foreground">
                <code>{code}</code>
            </pre>
            <div className="absolute right-2 top-2">
                <CopyButton text={code} label={label} />
            </div>
        </div>
    );
}

// Git clone section for applications
function GitCloneSection({ applications }: { applications: Application[] }) {
    const appsWithRepo = applications.filter(app => app.git_repository);

    if (appsWithRepo.length === 0) {
        return (
            <div className="rounded-lg border border-border bg-background-secondary p-4 text-center text-sm text-foreground-muted">
                No applications with Git repositories found in this environment.
            </div>
        );
    }

    return (
        <div className="space-y-3">
            {appsWithRepo.map(app => {
                const repo = app.git_repository || '';
                const branch = app.git_branch || 'main';
                // Extract repo name from URL for cd command
                const repoName = repo.split('/').pop()?.replace('.git', '') || 'project';
                const cloneCmd = `git clone ${repo}\ncd ${repoName}\ngit checkout ${branch}`;

                return (
                    <div key={app.id} className="space-y-2">
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-medium text-foreground">{app.name}</span>
                            <span className="rounded bg-background-tertiary px-1.5 py-0.5 text-xs text-foreground-muted">{branch}</span>
                        </div>
                        <CodeBlock code={cloneCmd} label={`Clone command for ${app.name}`} />
                    </div>
                );
            })}
        </div>
    );
}

// Environment variables section
function EnvVarsSection({ applications }: { applications: Application[] }) {
    const [envVars, setEnvVars] = useState<Record<string, EnvironmentVariable[]>>({});
    const [loading, setLoading] = useState(false);
    const [showValues, setShowValues] = useState(false);
    const [fetched, setFetched] = useState(false);
    const { addToast } = useToast();

    const fetchEnvVars = useCallback(async () => {
        if (applications.length === 0 || fetched) return;
        setLoading(true);
        try {
            const results: Record<string, EnvironmentVariable[]> = {};
            await Promise.all(
                applications.map(async (app) => {
                    try {
                        const response = await fetch(`/applications/${app.uuid}/envs/json`, {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'include',
                        });
                        if (response.ok) {
                            const data = await response.json();
                            results[app.uuid] = (data as EnvironmentVariable[]).filter(env => !env.is_preview);
                        }
                    } catch {
                        // Skip failed fetches for individual apps
                    }
                })
            );
            setEnvVars(results);
            setFetched(true);
        } catch {
            addToast('error', 'Error', 'Failed to load environment variables');
        } finally {
            setLoading(false);
        }
    }, [applications, fetched, addToast]);

    useEffect(() => {
        fetchEnvVars();
    }, [fetchEnvVars]);

    if (applications.length === 0) {
        return (
            <div className="rounded-lg border border-border bg-background-secondary p-4 text-center text-sm text-foreground-muted">
                No applications found in this environment.
            </div>
        );
    }

    if (loading) {
        return (
            <div className="flex items-center justify-center gap-2 py-8 text-foreground-muted">
                <Loader2 className="h-4 w-4 animate-spin" />
                <span className="text-sm">Loading environment variables...</span>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-end">
                <button
                    onClick={() => setShowValues(!showValues)}
                    className="flex items-center gap-1.5 rounded-md px-2 py-1 text-xs text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                >
                    {showValues ? <EyeOff className="h-3 w-3" /> : <Eye className="h-3 w-3" />}
                    {showValues ? 'Hide values' : 'Show values'}
                </button>
            </div>
            {applications.map(app => {
                const vars = envVars[app.uuid] || [];
                if (vars.length === 0) {
                    return (
                        <div key={app.id} className="space-y-2">
                            <span className="text-sm font-medium text-foreground">{app.name}</span>
                            <div className="rounded-lg border border-border bg-background p-3 text-center text-xs text-foreground-muted">
                                No environment variables configured.
                            </div>
                        </div>
                    );
                }

                const envContent = vars
                    .map(v => `${v.key}=${showValues ? (v.value || v.real_value || '') : '********'}`)
                    .join('\n');

                const envContentRaw = vars
                    .map(v => `${v.key}=${v.value || v.real_value || ''}`)
                    .join('\n');

                return (
                    <div key={app.id} className="space-y-2">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium text-foreground">{app.name}</span>
                            <CopyButton text={envContentRaw} label={`.env for ${app.name}`} />
                        </div>
                        <CodeBlock code={envContent} label={`.env for ${app.name}`} />
                    </div>
                );
            })}
        </div>
    );
}

// Database connections section
function DatabaseSection({ databases }: { databases: StandaloneDatabase[] }) {
    const [showPasswords, setShowPasswords] = useState(false);

    if (databases.length === 0) {
        return (
            <div className="rounded-lg border border-border bg-background-secondary p-4 text-center text-sm text-foreground-muted">
                No databases found in this environment.
            </div>
        );
    }

    const getConnectionDetails = (db: StandaloneDatabase) => {
        const type = db.database_type?.toLowerCase() || '';
        const details: { label: string; value: string }[] = [];

        if (type.includes('postgresql') || type.includes('postgres')) {
            details.push(
                { label: 'Host', value: db.connection?.external_host || db.connection?.internal_host || db.uuid },
                { label: 'Port', value: db.connection?.public_port?.toString() || db.connection?.port || '5432' },
                { label: 'User', value: db.postgres_user || 'postgres' },
                { label: 'Password', value: db.postgres_password || '' },
                { label: 'Database', value: db.postgres_db || 'postgres' },
            );
        } else if (type.includes('mysql') || type.includes('mariadb')) {
            details.push(
                { label: 'Host', value: db.connection?.external_host || db.connection?.internal_host || db.uuid },
                { label: 'Port', value: db.connection?.public_port?.toString() || db.connection?.port || '3306' },
                { label: 'User', value: db.mysql_user || 'root' },
                { label: 'Password', value: db.mysql_password || db.mysql_root_password || '' },
                { label: 'Database', value: db.mysql_database || '' },
            );
        } else if (type.includes('mongodb') || type.includes('mongo')) {
            details.push(
                { label: 'Host', value: db.connection?.external_host || db.connection?.internal_host || db.uuid },
                { label: 'Port', value: db.connection?.public_port?.toString() || db.connection?.port || '27017' },
                { label: 'User', value: db.mongo_initdb_root_username || '' },
                { label: 'Password', value: db.mongo_initdb_root_password || '' },
                { label: 'Database', value: db.mongo_initdb_database || '' },
            );
        } else if (type.includes('redis') || type.includes('keydb') || type.includes('dragonfly')) {
            details.push(
                { label: 'Host', value: db.connection?.external_host || db.connection?.internal_host || db.uuid },
                { label: 'Port', value: db.connection?.public_port?.toString() || db.connection?.port || '6379' },
                { label: 'Password', value: db.redis_password || db.keydb_password || db.dragonfly_password || '' },
            );
        } else if (type.includes('clickhouse')) {
            details.push(
                { label: 'Host', value: db.connection?.external_host || db.connection?.internal_host || db.uuid },
                { label: 'Port', value: db.connection?.public_port?.toString() || db.connection?.port || '8123' },
                { label: 'User', value: db.clickhouse_admin_user || 'default' },
                { label: 'Password', value: db.clickhouse_admin_password || '' },
            );
        }

        return details;
    };

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-end">
                <button
                    onClick={() => setShowPasswords(!showPasswords)}
                    className="flex items-center gap-1.5 rounded-md px-2 py-1 text-xs text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                >
                    {showPasswords ? <EyeOff className="h-3 w-3" /> : <Eye className="h-3 w-3" />}
                    {showPasswords ? 'Hide passwords' : 'Show passwords'}
                </button>
            </div>
            {databases.map(db => {
                const connectionUrl = db.external_db_url || db.internal_db_url || '';
                const details = getConnectionDetails(db);
                const isPublic = db.is_public;

                return (
                    <div key={db.id} className="space-y-3 rounded-lg border border-border bg-background p-4">
                        <div className="flex items-center gap-3">
                            <div className={`flex h-7 w-7 items-center justify-center rounded-md text-white ${getDbBgColor(db.database_type)}`}>
                                {getDbLogo(db.database_type)}
                            </div>
                            <div>
                                <span className="text-sm font-medium text-foreground">{db.name}</span>
                                <span className="ml-2 text-xs capitalize text-foreground-muted">{db.database_type}</span>
                            </div>
                            {!isPublic && (
                                <span className="ml-auto flex items-center gap-1 rounded bg-yellow-500/10 px-2 py-0.5 text-xs text-yellow-500">
                                    <AlertTriangle className="h-3 w-3" />
                                    Not public
                                </span>
                            )}
                        </div>

                        {/* Connection URL */}
                        {connectionUrl && (
                            <div className="space-y-1">
                                <span className="text-xs font-medium text-foreground-muted">Connection URL</span>
                                <CodeBlock
                                    code={showPasswords ? connectionUrl : connectionUrl.replace(/:[^:@]+@/, ':********@')}
                                    label={`Connection URL for ${db.name}`}
                                />
                            </div>
                        )}

                        {/* Individual fields */}
                        <div className="grid grid-cols-2 gap-2">
                            {details.map(({ label, value }) => {
                                const isPassword = label.toLowerCase() === 'password';
                                const displayValue = isPassword && !showPasswords ? '********' : value;
                                return (
                                    <div key={label} className="space-y-1">
                                        <span className="text-xs text-foreground-muted">{label}</span>
                                        <div className="flex items-center gap-1 rounded border border-border bg-background-secondary px-2 py-1.5">
                                            <code className="flex-1 truncate text-xs text-foreground">{displayValue || '-'}</code>
                                            {value && <CopyButton text={value} label={`${label} for ${db.name}`} />}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        {!isPublic && (
                            <p className="text-xs text-foreground-muted">
                                This database is not publicly accessible. Use an SSH tunnel to connect locally:
                            </p>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

export function LocalSetupModal({ isOpen, onClose, environment }: LocalSetupModalProps) {
    const applications = environment?.applications || [];
    const databases = environment?.databases || [];

    return (
        <Modal isOpen={isOpen} onClose={onClose} size="full">
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Terminal className="h-5 w-5" />
                    </div>
                    <div>
                        <h2 className="text-lg font-semibold text-foreground">Set up your project locally</h2>
                        <p className="text-sm text-foreground-muted">
                            Clone repositories, configure environment variables, and connect to databases.
                        </p>
                    </div>
                </div>

                {/* Git Clone Section */}
                <section className="space-y-3">
                    <div className="flex items-center gap-2">
                        <GitBranch className="h-4 w-4 text-foreground-muted" />
                        <h3 className="text-sm font-semibold text-foreground">Clone Repositories</h3>
                    </div>
                    <GitCloneSection applications={applications} />
                </section>

                {/* Environment Variables Section */}
                <section className="space-y-3">
                    <div className="flex items-center gap-2">
                        <Key className="h-4 w-4 text-foreground-muted" />
                        <h3 className="text-sm font-semibold text-foreground">Environment Variables</h3>
                    </div>
                    <EnvVarsSection applications={applications} />
                </section>

                {/* Database Connections Section */}
                <section className="space-y-3">
                    <div className="flex items-center gap-2">
                        <Database className="h-4 w-4 text-foreground-muted" />
                        <h3 className="text-sm font-semibold text-foreground">Database Connections</h3>
                    </div>
                    <DatabaseSection databases={databases} />
                </section>
            </div>
        </Modal>
    );
}
