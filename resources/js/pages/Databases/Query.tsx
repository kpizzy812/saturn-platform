import { AppLayout } from '@/components/layout';
import { Button, Card, useToast } from '@/components/ui';
import { SqlEditor } from '@/components/ui/SqlEditor';
import { Link, router } from '@inertiajs/react';
import { useState, useCallback, useEffect } from 'react';
import * as Icons from 'lucide-react';
import { cn } from '@/lib/utils';
import { exportToCSV, exportToJSON } from '@/lib/csv';
import type { StandaloneDatabase } from '@/types';

interface QueryResult {
    columns: string[];
    rows: Record<string, string>[];
    executionTime: number;
    rowCount: number;
}

interface QueryHistory {
    id: string;
    query: string;
    timestamp: string;
    status: 'success' | 'error';
    rowCount?: number;
    error?: string;
}

interface SavedQuery {
    id: string;
    name: string;
    query: string;
    createdAt: string;
}

interface Props {
    database: StandaloneDatabase;
    databases: StandaloneDatabase[];
    queryHistory?: QueryHistory[];
    savedQueries?: SavedQuery[];
}

// Local storage keys
const HISTORY_KEY = 'saturn_query_history';
const SAVED_QUERIES_KEY = 'saturn_saved_queries';

export default function DatabaseQuery({ database, databases = [], queryHistory: _initialHistory = [], savedQueries: _initialSavedQueries = [] }: Props) {
    const { addToast } = useToast();
    const [currentQuery, setCurrentQuery] = useState('SELECT 1;');
    const [isExecuting, setIsExecuting] = useState(false);
    const [queryResult, setQueryResult] = useState<QueryResult | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [showHistory, setShowHistory] = useState(false);
    const [showSaved, setShowSaved] = useState(false);
    const [exportFormat, setExportFormat] = useState<'csv' | 'json'>('csv');
    const [localHistory, setLocalHistory] = useState<QueryHistory[]>([]);
    const [localSavedQueries, setLocalSavedQueries] = useState<SavedQuery[]>([]);
    const [showSaveModal, setShowSaveModal] = useState(false);
    const [saveQueryName, setSaveQueryName] = useState('');

    // Load history from localStorage on mount
    useEffect(() => {
        try {
            const storedHistory = localStorage.getItem(`${HISTORY_KEY}_${database.uuid}`);
            if (storedHistory) {
                setLocalHistory(JSON.parse(storedHistory));
            }
            const storedSaved = localStorage.getItem(`${SAVED_QUERIES_KEY}_${database.uuid}`);
            if (storedSaved) {
                setLocalSavedQueries(JSON.parse(storedSaved));
            }
        } catch {
            // Ignore parse errors
        }
    }, [database.uuid]);

    // Add query to local history
    const addToHistory = useCallback((query: string, status: 'success' | 'error', rowCount?: number, errorMsg?: string) => {
        const newEntry: QueryHistory = {
            id: Date.now().toString(),
            query,
            timestamp: new Date().toLocaleTimeString(),
            status,
            rowCount,
            error: errorMsg,
        };

        setLocalHistory(prev => {
            const updated = [newEntry, ...prev].slice(0, 50); // Keep last 50
            try {
                localStorage.setItem(`${HISTORY_KEY}_${database.uuid}`, JSON.stringify(updated));
            } catch {
                // Ignore storage errors
            }
            return updated;
        });
    }, [database.uuid]);

    // Save query to local storage
    const saveQuery = useCallback(() => {
        if (!currentQuery.trim() || !saveQueryName.trim()) {
            addToast('error', 'Please enter a name for the query');
            return;
        }

        const newSavedQuery: SavedQuery = {
            id: Date.now().toString(),
            name: saveQueryName.trim(),
            query: currentQuery,
            createdAt: new Date().toISOString(),
        };

        setLocalSavedQueries(prev => {
            const updated = [newSavedQuery, ...prev];
            try {
                localStorage.setItem(`${SAVED_QUERIES_KEY}_${database.uuid}`, JSON.stringify(updated));
            } catch {
                // Ignore storage errors
            }
            return updated;
        });

        addToast('success', `Query "${saveQueryName}" saved successfully`);
        setShowSaveModal(false);
        setSaveQueryName('');
    }, [currentQuery, saveQueryName, database.uuid, addToast]);

    // Delete saved query
    const deleteSavedQuery = useCallback((id: string) => {
        setLocalSavedQueries(prev => {
            const updated = prev.filter(q => q.id !== id);
            try {
                localStorage.setItem(`${SAVED_QUERIES_KEY}_${database.uuid}`, JSON.stringify(updated));
            } catch {
                // Ignore storage errors
            }
            return updated;
        });
        addToast('success', 'Query deleted');
    }, [database.uuid, addToast]);

    const executeQuery = async () => {
        if (!currentQuery.trim()) return;

        setIsExecuting(true);
        setError(null);
        setQueryResult(null);

        try {
            const response = await fetch(`/_internal/databases/${database.uuid}/query`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                },
                credentials: 'include',
                body: JSON.stringify({ query: currentQuery }),
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                const errorMessage = data.error || 'Query execution failed';
                setError(errorMessage);
                addToHistory(currentQuery, 'error', undefined, errorMessage);
                addToast('error', `Query failed: ${errorMessage}`);
            } else {
                setQueryResult({
                    columns: data.columns || [],
                    rows: data.rows || [],
                    executionTime: data.executionTime || 0,
                    rowCount: data.rowCount || 0,
                });
                addToHistory(currentQuery, 'success', data.rowCount);
                addToast('success', `Query executed: ${data.rowCount} rows returned in ${data.executionTime}s`);
            }
        } catch (err) {
            const errorMessage = err instanceof Error ? err.message : 'Network error';
            setError(errorMessage);
            addToHistory(currentQuery, 'error', undefined, errorMessage);
            addToast('error', `Query failed: ${errorMessage}`);
        } finally {
            setIsExecuting(false);
        }
    };

    // Handle database change
    const handleDatabaseChange = (uuid: string) => {
        if (uuid !== database.uuid) {
            router.visit(`/databases/${uuid}/query`);
        }
    };

    const exportResults = () => {
        if (!queryResult) return;

        const timestamp = Date.now();
        if (exportFormat === 'csv') {
            exportToCSV(queryResult.columns, queryResult.rows, `query-results-${timestamp}.csv`);
        } else {
            exportToJSON(queryResult.rows, `query-results-${timestamp}.json`);
        }
    };

    return (
        <AppLayout
            title={`Query - ${database.name}`}
            breadcrumbs={[
                { label: 'Databases', href: '/databases' },
                { label: database.name, href: `/databases/${database.uuid}` },
                { label: 'Query' },
            ]}
        >
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Query Browser</h1>
                        <p className="text-foreground-muted">Execute SQL queries and explore your data</p>
                    </div>
                    <div className="flex gap-2">
                        <select
                            className="h-10 rounded-lg border border-border bg-background-secondary px-3 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
                            value={database.uuid}
                            onChange={(e) => handleDatabaseChange(e.target.value)}
                        >
                            {databases.map((db) => (
                                <option key={db.uuid} value={db.uuid}>
                                    {db.name} ({db.database_type})
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-12">
                    {/* Main Query Area */}
                    <div className="space-y-4 lg:col-span-9">
                        {/* SQL Editor */}
                        <Card className="p-4">
                            <div className="mb-3 flex items-center justify-between">
                                <h2 className="text-sm font-semibold text-foreground">SQL Query</h2>
                                <div className="flex gap-2">
                                    <Button
                                        onClick={() => setCurrentQuery('')}
                                        variant="secondary"
                                        size="sm"
                                    >
                                        <Icons.Eraser className="mr-1.5 h-3.5 w-3.5" />
                                        Clear
                                    </Button>
                                    <Button
                                        onClick={executeQuery}
                                        disabled={isExecuting || !currentQuery.trim()}
                                        size="sm"
                                    >
                                        {isExecuting ? (
                                            <>
                                                <Icons.Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />
                                                Running...
                                            </>
                                        ) : (
                                            <>
                                                <Icons.Play className="mr-1.5 h-3.5 w-3.5" />
                                                Run Query
                                            </>
                                        )}
                                    </Button>
                                </div>
                            </div>
                            <SqlEditor
                                value={currentQuery}
                                onChange={setCurrentQuery}
                                onExecute={executeQuery}
                                rows={8}
                            />
                        </Card>

                        {/* Results */}
                        {error && (
                            <Card className="border-danger/50 bg-danger/10 p-4">
                                <div className="flex items-start gap-3">
                                    <Icons.AlertCircle className="h-5 w-5 flex-shrink-0 text-danger" />
                                    <div>
                                        <h3 className="mb-1 font-semibold text-danger">Query Error</h3>
                                        <p className="font-mono text-sm text-danger">{error}</p>
                                    </div>
                                </div>
                            </Card>
                        )}

                        {queryResult && (
                            <Card className="p-4">
                                <div className="mb-4 flex items-center justify-between">
                                    <div className="flex items-center gap-4">
                                        <h2 className="text-sm font-semibold text-foreground">Results</h2>
                                        <div className="flex gap-3 text-xs text-foreground-muted">
                                            <span>
                                                {queryResult.rowCount}{' '}
                                                {queryResult.rowCount === 1 ? 'row' : 'rows'}
                                            </span>
                                            <span>•</span>
                                            <span>{queryResult.executionTime}s</span>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <select
                                            value={exportFormat}
                                            onChange={(e) => setExportFormat(e.target.value as 'csv' | 'json')}
                                            className="h-8 rounded-lg border border-border bg-background px-2 text-xs text-foreground focus:border-primary focus:outline-none"
                                        >
                                            <option value="csv">CSV</option>
                                            <option value="json">JSON</option>
                                        </select>
                                        <Button onClick={exportResults} variant="secondary" size="sm">
                                            <Icons.Download className="mr-1.5 h-3.5 w-3.5" />
                                            Export
                                        </Button>
                                    </div>
                                </div>

                                {/* Results Table */}
                                <div className="overflow-x-auto rounded-lg border border-border">
                                    <table className="w-full">
                                        <thead className="bg-background-secondary">
                                            <tr>
                                                {queryResult.columns.map((col) => (
                                                    <th
                                                        key={col}
                                                        className="border-b border-border px-4 py-2 text-left text-xs font-semibold text-foreground"
                                                    >
                                                        {col}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {queryResult.rows.map((row, idx) => (
                                                <tr
                                                    key={idx}
                                                    className="border-b border-border/50 transition-colors hover:bg-background-secondary/50"
                                                >
                                                    {queryResult.columns.map((col) => (
                                                        <td
                                                            key={col}
                                                            className="px-4 py-2 font-mono text-xs text-foreground-muted"
                                                        >
                                                            {row[col]}
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </Card>
                        )}

                        {!queryResult && !error && !isExecuting && (
                            <Card className="p-12 text-center">
                                <Icons.Database className="mx-auto mb-4 h-12 w-12 text-foreground-subtle" />
                                <h3 className="mb-2 text-lg font-semibold text-foreground">Ready to Query</h3>
                                <p className="text-foreground-muted">
                                    Write your SQL query above and click "Run Query" to see results.
                                </p>
                            </Card>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-4 lg:col-span-3">
                        {/* Query History */}
                        <Card className="p-4">
                            <button
                                onClick={() => setShowHistory(!showHistory)}
                                className="mb-3 flex w-full items-center justify-between text-sm font-semibold text-foreground"
                            >
                                <div className="flex items-center gap-2">
                                    <Icons.History className="h-4 w-4" />
                                    Query History
                                </div>
                                <Icons.ChevronDown
                                    className={cn(
                                        'h-4 w-4 transition-transform',
                                        showHistory && 'rotate-180'
                                    )}
                                />
                            </button>
                            {showHistory && (
                                <div className="space-y-2">
                                    {localHistory.length === 0 ? (
                                        <p className="py-4 text-center text-xs text-foreground-muted">No query history yet</p>
                                    ) : (
                                        localHistory.map((item) => (
                                            <button
                                                key={item.id}
                                                onClick={() => setCurrentQuery(item.query)}
                                                className="w-full rounded-lg border border-border/50 bg-background-secondary/50 p-2 text-left transition-colors hover:bg-background-secondary"
                                                title={item.status === 'error' ? item.error : undefined}
                                            >
                                                <div className="mb-1 flex items-center justify-between">
                                                    <span
                                                        className={cn(
                                                            'text-xs font-medium',
                                                            item.status === 'success'
                                                                ? 'text-success'
                                                                : 'text-danger'
                                                        )}
                                                    >
                                                        {item.status === 'success'
                                                            ? `${item.rowCount} rows`
                                                            : 'Error'}
                                                    </span>
                                                    <span className="text-xs text-foreground-subtle">
                                                        {item.timestamp}
                                                    </span>
                                                </div>
                                                <p className="line-clamp-2 font-mono text-xs text-foreground-muted">
                                                    {item.query}
                                                </p>
                                            </button>
                                        ))
                                    )}
                                </div>
                            )}
                        </Card>

                        {/* Saved Queries */}
                        <Card className="p-4">
                            <button
                                onClick={() => setShowSaved(!showSaved)}
                                className="mb-3 flex w-full items-center justify-between text-sm font-semibold text-foreground"
                            >
                                <div className="flex items-center gap-2">
                                    <Icons.BookmarkPlus className="h-4 w-4" />
                                    Saved Queries
                                </div>
                                <Icons.ChevronDown
                                    className={cn(
                                        'h-4 w-4 transition-transform',
                                        showSaved && 'rotate-180'
                                    )}
                                />
                            </button>
                            {showSaved && (
                                <div className="space-y-2">
                                    {localSavedQueries.length === 0 ? (
                                        <p className="py-4 text-center text-xs text-foreground-muted">No saved queries yet</p>
                                    ) : (
                                        localSavedQueries.map((item) => (
                                            <div
                                                key={item.id}
                                                className="group relative rounded-lg border border-border/50 bg-background-secondary/50 p-2 transition-colors hover:bg-background-secondary"
                                            >
                                                <button
                                                    onClick={() => setCurrentQuery(item.query)}
                                                    className="w-full text-left"
                                                >
                                                    <div className="mb-1 flex items-center justify-between pr-6">
                                                        <span className="text-xs font-medium text-foreground">
                                                            {item.name}
                                                        </span>
                                                    </div>
                                                    <p className="line-clamp-2 font-mono text-xs text-foreground-muted">
                                                        {item.query}
                                                    </p>
                                                </button>
                                                <button
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        deleteSavedQuery(item.id);
                                                    }}
                                                    className="absolute right-2 top-2 rounded p-1 text-foreground-muted opacity-0 transition-opacity hover:bg-danger/20 hover:text-danger group-hover:opacity-100"
                                                    title="Delete saved query"
                                                >
                                                    <Icons.Trash2 className="h-3 w-3" />
                                                </button>
                                            </div>
                                        ))
                                    )}
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        className="w-full"
                                        onClick={() => {
                                            if (!currentQuery.trim()) {
                                                addToast('error', 'Write a query first');
                                                return;
                                            }
                                            setShowSaveModal(true);
                                        }}
                                    >
                                        <Icons.Plus className="mr-1.5 h-3.5 w-3.5" />
                                        Save Current Query
                                    </Button>
                                </div>
                            )}
                        </Card>

                        {/* Quick Links */}
                        <Card className="p-4">
                            <h3 className="mb-3 text-sm font-semibold text-foreground">Quick Links</h3>
                            <div className="space-y-2">
                                <Link
                                    href={`/databases/${database.uuid}/tables`}
                                    className="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-foreground-muted transition-colors hover:bg-background-secondary hover:text-foreground"
                                >
                                    <Icons.Table className="h-4 w-4" />
                                    Browse Tables
                                </Link>
                                <Link
                                    href={`/databases/${database.uuid}/import`}
                                    className="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-foreground-muted transition-colors hover:bg-background-secondary hover:text-foreground"
                                >
                                    <Icons.Upload className="h-4 w-4" />
                                    Import/Export
                                </Link>
                            </div>
                        </Card>
                    </div>
                </div>
            </div>

            {/* Save Query Modal */}
            {showSaveModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
                    <div className="w-full max-w-md rounded-lg border border-border bg-background p-6 shadow-xl">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold text-foreground">Save Query</h3>
                            <button
                                onClick={() => {
                                    setShowSaveModal(false);
                                    setSaveQueryName('');
                                }}
                                className="rounded p-1 text-foreground-muted hover:bg-background-secondary hover:text-foreground"
                            >
                                <Icons.X className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="space-y-4">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-foreground">
                                    Query Name
                                </label>
                                <input
                                    type="text"
                                    value={saveQueryName}
                                    onChange={(e) => setSaveQueryName(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && saveQuery()}
                                    placeholder="e.g., Get all users, Monthly report..."
                                    className="w-full rounded-lg border border-border bg-background-secondary px-3 py-2 text-sm text-foreground placeholder:text-foreground-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                    autoFocus
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-foreground-muted">
                                    Query Preview
                                </label>
                                <pre className="max-h-32 overflow-auto rounded-lg border border-border bg-background-secondary p-3 font-mono text-xs text-foreground-muted">
                                    {currentQuery}
                                </pre>
                            </div>
                            <div className="flex justify-end gap-2">
                                <Button
                                    variant="secondary"
                                    onClick={() => {
                                        setShowSaveModal(false);
                                        setSaveQueryName('');
                                    }}
                                >
                                    Cancel
                                </Button>
                                <Button onClick={saveQuery} disabled={!saveQueryName.trim()}>
                                    <Icons.Save className="mr-1.5 h-4 w-4" />
                                    Save Query
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
