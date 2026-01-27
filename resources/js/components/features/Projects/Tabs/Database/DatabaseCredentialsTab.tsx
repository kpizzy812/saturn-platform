import { useState } from 'react';
import { Button } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { RefreshCw, Eye, EyeOff, Copy } from 'lucide-react';
import { useDatabase } from '@/hooks/useDatabases';
import type { SelectedService } from '../../types';

interface DatabaseCredentialsTabProps {
    service: SelectedService;
}

export function DatabaseCredentialsTab({ service }: DatabaseCredentialsTabProps) {
    const { addToast } = useToast();
    const [showPassword, setShowPassword] = useState(false);
    const { database, isLoading, error } = useDatabase({ uuid: service.uuid });

    // Get credentials based on database type
    const getCredentials = () => {
        const dbType = service.dbType?.toLowerCase() || '';

        if (dbType.includes('postgresql') || dbType.includes('postgres')) {
            return {
                username: database?.postgres_user || 'postgres',
                password: database?.postgres_password,
                database: database?.postgres_db || 'postgres',
            };
        }
        if (dbType.includes('mysql') || dbType.includes('mariadb')) {
            return {
                username: database?.mysql_user || 'root',
                password: database?.mysql_password || database?.mysql_root_password,
                database: database?.mysql_database || 'mysql',
            };
        }
        if (dbType.includes('mongodb') || dbType.includes('mongo')) {
            return {
                username: database?.mongo_initdb_root_username || 'root',
                password: database?.mongo_initdb_root_password,
                database: database?.mongo_initdb_database || 'admin',
            };
        }
        if (dbType.includes('redis')) {
            return {
                username: null,
                password: database?.redis_password,
                database: null,
            };
        }
        if (dbType.includes('keydb')) {
            return {
                username: null,
                password: database?.keydb_password,
                database: null,
            };
        }
        if (dbType.includes('dragonfly')) {
            return {
                username: null,
                password: database?.dragonfly_password,
                database: null,
            };
        }
        if (dbType.includes('clickhouse')) {
            return {
                username: database?.clickhouse_admin_user || 'default',
                password: database?.clickhouse_admin_password,
                database: 'default',
            };
        }
        return { username: null, password: null, database: null };
    };

    const credentials = getCredentials();

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
                    Unable to load credentials. Check API permissions (requires read:sensitive).
                </div>
            )}

            {/* Current Credentials */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Current Credentials</h3>
                <div className="space-y-2">
                    {credentials.username !== null && (
                        <div className="rounded-lg border border-border bg-background-secondary p-3">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-xs text-foreground-muted">Username</p>
                                    <code className="text-sm text-foreground">{credentials.username}</code>
                                </div>
                                <button
                                    onClick={() => {
                                        if (credentials.username) {
                                            navigator.clipboard.writeText(credentials.username);
                                            addToast('success', 'Username copied');
                                        }
                                    }}
                                    className="rounded p-1.5 text-foreground-muted hover:bg-background hover:text-foreground"
                                    title="Copy username"
                                >
                                    <Copy className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    )}
                    <div className="rounded-lg border border-border bg-background-secondary p-3">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs text-foreground-muted">Password</p>
                                <code className="text-sm text-foreground">
                                    {credentials.password
                                        ? (showPassword ? credentials.password : '••••••••••••••••')
                                        : '(not available - requires read:sensitive permission)'}
                                </code>
                            </div>
                            <div className="flex gap-1">
                                <button
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="rounded p-1.5 text-foreground-muted hover:bg-background hover:text-foreground disabled:opacity-50"
                                    title={showPassword ? 'Hide password' : 'Show password'}
                                    disabled={!credentials.password}
                                >
                                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                </button>
                                <button
                                    onClick={() => {
                                        if (credentials.password) {
                                            navigator.clipboard.writeText(credentials.password);
                                            addToast('success', 'Password copied');
                                        }
                                    }}
                                    className="rounded p-1.5 text-foreground-muted hover:bg-background hover:text-foreground disabled:opacity-50"
                                    title="Copy password"
                                    disabled={!credentials.password}
                                >
                                    <Copy className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                    {credentials.database !== null && (
                        <div className="rounded-lg border border-border bg-background-secondary p-3">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-xs text-foreground-muted">Database</p>
                                    <code className="text-sm text-foreground">{credentials.database}</code>
                                </div>
                                <button
                                    onClick={() => {
                                        if (credentials.database) {
                                            navigator.clipboard.writeText(credentials.database);
                                            addToast('success', 'Database name copied');
                                        }
                                    }}
                                    className="rounded p-1.5 text-foreground-muted hover:bg-background hover:text-foreground"
                                    title="Copy database name"
                                >
                                    <Copy className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Regenerate Password */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Regenerate Password</h3>
                <p className="mb-3 text-xs text-foreground-muted">
                    Generate a new password. This will automatically update the DATABASE_URL variable.
                    You'll need to redeploy services that depend on this database.
                </p>
                <Button variant="secondary" size="sm">
                    <RefreshCw className="mr-2 h-3 w-3" />
                    Regenerate Password
                </Button>
            </div>

            {/* Warning */}
            <div className="rounded-lg border border-yellow-500/20 bg-yellow-500/5 p-4">
                <p className="text-sm text-yellow-500">
                    <strong>Note:</strong> After regenerating the password, any services using this
                    database will need to be redeployed to use the new credentials.
                </p>
            </div>
        </div>
    );
}
