import { useState, useEffect } from 'react';
import { RefreshCw, Table, Database } from 'lucide-react';
import { Button } from '@/components/ui';
import type { SelectedService } from '../../types';

interface DatabaseDataTabProps {
    service: SelectedService;
}

interface TableInfo {
    name: string;
    rows: number;
    size: string;
}

export function DatabaseDataTab({ service }: DatabaseDataTabProps) {
    const [tables, setTables] = useState<TableInfo[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [dbType, setDbType] = useState<string>('');

    const fetchTables = async () => {
        setIsLoading(true);
        setError(null);
        try {
            const response = await fetch(`/_internal/databases/${service.uuid}/tables`);
            const data = await response.json();
            if (data.available && data.tables) {
                setTables(data.tables);
                setDbType(data.type || '');
            } else {
                setError(data.error || 'Unable to fetch tables');
                setTables([]);
            }
        } catch {
            setError('Failed to connect to database');
            setTables([]);
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        fetchTables();
    }, [service.uuid]);

    const entityLabel = dbType === 'mongodb' ? 'Collections' : 'Tables';
    const rowLabel = dbType === 'mongodb' ? 'documents' : 'rows';

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-12">
                <RefreshCw className="h-6 w-6 animate-spin text-foreground-muted" />
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-medium text-foreground">{entityLabel}</h3>
                <Button size="sm" variant="secondary" onClick={fetchTables}>
                    <RefreshCw className="mr-1 h-3 w-3" />
                    Refresh
                </Button>
            </div>

            {error && (
                <div className="rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-3 text-sm text-yellow-500">
                    {error}
                </div>
            )}

            {tables.length > 0 ? (
                <div className="space-y-2">
                    {tables.map((table) => (
                        <div
                            key={table.name}
                            className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-3"
                        >
                            <div className="flex items-center gap-3">
                                <Table className="h-4 w-4 text-foreground-muted" />
                                <div>
                                    <p className="text-sm font-medium text-foreground">{table.name}</p>
                                    <p className="text-xs text-foreground-muted">{table.rows.toLocaleString()} {rowLabel}</p>
                                </div>
                            </div>
                            <span className="text-xs text-foreground-muted">{table.size}</span>
                        </div>
                    ))}
                </div>
            ) : !error ? (
                <div className="rounded-lg border border-dashed border-border p-6 text-center">
                    <Database className="mx-auto h-8 w-8 text-foreground-subtle" />
                    <p className="mt-2 text-sm text-foreground-muted">
                        No {entityLabel.toLowerCase()} found
                    </p>
                    <p className="mt-1 text-xs text-foreground-subtle">
                        {dbType === 'redis' || dbType === 'keydb' || dbType === 'dragonfly'
                            ? 'Key-value stores do not have a table structure'
                            : `Create your first ${entityLabel.toLowerCase().slice(0, -1)} to get started`}
                    </p>
                </div>
            ) : null}
        </div>
    );
}
