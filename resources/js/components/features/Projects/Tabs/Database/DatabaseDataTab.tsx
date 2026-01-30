import { useState, useEffect, useCallback, useRef } from 'react';
import { RefreshCw, Table, Database, ExternalLink } from 'lucide-react';
import { Button } from '@/components/ui';
import { Link } from '@inertiajs/react';
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
    const abortControllerRef = useRef<AbortController | null>(null);

    const fetchTables = useCallback(async () => {
        // Cancel any in-flight request
        abortControllerRef.current?.abort();
        const controller = new AbortController();
        abortControllerRef.current = controller;

        setIsLoading(true);
        setError(null);
        try {
            const response = await fetch(`/_internal/databases/${service.uuid}/tables`, {
                signal: controller.signal,
            });
            const data = await response.json();
            if (controller.signal.aborted) return;
            if (data.available && data.tables) {
                setTables(data.tables);
                setDbType(data.type || '');
            } else {
                setError(data.error || 'Unable to fetch tables');
                setTables([]);
            }
        } catch (err) {
            if (err instanceof DOMException && err.name === 'AbortError') return;
            setError('Failed to connect to database');
            setTables([]);
        } finally {
            if (!controller.signal.aborted) {
                setIsLoading(false);
            }
        }
    }, [service.uuid]);

    useEffect(() => {
        fetchTables();
        return () => {
            abortControllerRef.current?.abort();
        };
    }, [fetchTables]);

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
                        <Link
                            key={table.name}
                            href={`/databases/${service.uuid}/tables?table=${encodeURIComponent(table.name)}&tab=data`}
                            className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-3 transition-all hover:border-primary hover:bg-background-secondary/80 hover:shadow-sm group cursor-pointer"
                        >
                            <div className="flex items-center gap-3">
                                <Table className="h-4 w-4 text-foreground-muted group-hover:text-primary transition-colors" />
                                <div>
                                    <p className="text-sm font-medium text-foreground group-hover:text-primary transition-colors">
                                        {table.name}
                                    </p>
                                    <p className="text-xs text-foreground-muted">
                                        {table.rows.toLocaleString()} {rowLabel} • {table.size}
                                    </p>
                                </div>
                            </div>
                            <ExternalLink className="h-3.5 w-3.5 text-foreground-subtle opacity-0 group-hover:opacity-100 transition-opacity" />
                        </Link>
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
