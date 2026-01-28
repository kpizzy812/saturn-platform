import { useState } from 'react';
import { Copy, Shield, Globe, RefreshCw, ExternalLink } from 'lucide-react';
import { useToast } from '@/components/ui/Toast';
import { useDatabase } from '@/hooks/useDatabases';
import type { SelectedService } from '../../types';

interface DatabaseConnectTabProps {
    service: SelectedService;
}

export function DatabaseConnectTab({ service }: DatabaseConnectTabProps) {
    const { addToast } = useToast();
    // Fetch real database data
    const { database, isLoading, error } = useDatabase({ uuid: service.uuid });

    // Get connection strings from API data
    const internalUrl = database?.internal_db_url;
    const externalUrl = database?.external_db_url;

    // Generate environment variables based on database type
    const getEnvVariables = () => {
        const dbType = service.dbType?.toLowerCase();
        switch (dbType) {
            case 'postgresql':
                return [
                    { key: 'DATABASE_URL', value: internalUrl ? '(set)' : '(not configured)' },
                    { key: 'PGHOST', value: database?.uuid || service.uuid },
                    { key: 'PGPORT', value: '5432' },
                    { key: 'PGUSER', value: database?.postgres_user || 'postgres' },
                    { key: 'PGDATABASE', value: database?.postgres_db || 'postgres' },
                ];
            case 'mysql':
            case 'mariadb':
                return [
                    { key: 'DATABASE_URL', value: internalUrl ? '(set)' : '(not configured)' },
                    { key: 'MYSQL_HOST', value: database?.uuid || service.uuid },
                    { key: 'MYSQL_PORT', value: '3306' },
                    { key: 'MYSQL_USER', value: database?.mysql_user || 'root' },
                    { key: 'MYSQL_DATABASE', value: database?.mysql_database || 'mysql' },
                ];
            case 'mongodb':
                return [
                    { key: 'MONGO_URL', value: internalUrl ? '(set)' : '(not configured)' },
                    { key: 'MONGO_HOST', value: database?.uuid || service.uuid },
                    { key: 'MONGO_PORT', value: '27017' },
                ];
            case 'redis':
            case 'keydb':
            case 'dragonfly':
                return [
                    { key: 'REDIS_URL', value: internalUrl ? '(set)' : '(not configured)' },
                    { key: 'REDIS_HOST', value: database?.uuid || service.uuid },
                    { key: 'REDIS_PORT', value: '6379' },
                ];
            default:
                return [
                    { key: 'DATABASE_URL', value: internalUrl ? '(set)' : '(not configured)' },
                ];
        }
    };

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-12">
                <RefreshCw className="h-6 w-6 animate-spin text-foreground-muted" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {error && (
                <div className="rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-3 text-sm text-yellow-500">
                    Unable to load connection details. Check API permissions.
                </div>
            )}

            {/* Private Network */}
            <div>
                <div className="mb-3 flex items-center gap-2">
                    <Shield className="h-4 w-4 text-emerald-500" />
                    <h3 className="text-sm font-medium text-foreground">Private Network</h3>
                </div>
                <p className="mb-3 text-xs text-foreground-muted">
                    Use this connection string for services within the same project.
                </p>
                <div className="rounded-lg border border-border bg-background-secondary p-3">
                    <div className="flex items-center justify-between gap-2">
                        <code className="flex-1 truncate text-sm text-foreground">
                            {internalUrl || 'Not configured - enable public port or check settings'}
                        </code>
                        <button
                            onClick={() => {
                                if (internalUrl) {
                                    navigator.clipboard.writeText(internalUrl);
                                    addToast('success', 'Copied to clipboard');
                                }
                            }}
                            className="rounded p-1.5 text-foreground-muted hover:bg-background hover:text-foreground disabled:opacity-50"
                            title="Copy connection string"
                            disabled={!internalUrl}
                        >
                            <Copy className="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

            {/* Public Network */}
            <div>
                <div className="mb-3 flex items-center gap-2">
                    <Globe className="h-4 w-4 text-foreground-muted" />
                    <h3 className="text-sm font-medium text-foreground">Public Network</h3>
                </div>
                <p className="mb-3 text-xs text-foreground-muted">
                    Use this for external access. Network egress charges apply.
                </p>
                <div className="rounded-lg border border-border bg-background-secondary p-3">
                    <div className="flex items-center justify-between gap-2">
                        <code className="flex-1 truncate text-sm text-foreground">
                            {externalUrl || `Public access not configured (port: ${database?.public_port || 'N/A'})`}
                        </code>
                        <button
                            onClick={() => {
                                if (externalUrl) {
                                    navigator.clipboard.writeText(externalUrl);
                                    addToast('success', 'Copied to clipboard');
                                }
                            }}
                            className="rounded p-1.5 text-foreground-muted hover:bg-background hover:text-foreground disabled:opacity-50"
                            title="Copy external URL"
                            disabled={!externalUrl}
                        >
                            <Copy className="h-4 w-4" />
                        </button>
                    </div>
                </div>
                {!externalUrl && database?.uuid && (
                    <div className="mt-2 flex items-center gap-2">
                        <p className="text-xs text-foreground-muted">
                            Enable public access to get an external connection URL.
                        </p>
                        <a
                            href={`/databases/${database.uuid}`}
                            className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                        >
                            Open Database Settings
                            <ExternalLink className="h-3 w-3" />
                        </a>
                    </div>
                )}
            </div>

            {/* Connection Variables */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Variables</h3>
                <div className="space-y-2">
                    {getEnvVariables().map((v) => (
                        <div key={v.key} className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-2">
                            <code className="text-xs text-foreground-muted">{v.key}</code>
                            <code className="text-xs text-foreground">{v.value}</code>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
