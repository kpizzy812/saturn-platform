import { AppLayout } from '@/components/layout';
import { Button, Card, Badge } from '@/components/ui';
import { Link } from '@inertiajs/react';
import { useState, useEffect, useCallback, useRef } from 'react';
import * as Icons from 'lucide-react';
import { cn } from '@/lib/utils';
import type { StandaloneDatabase } from '@/types';
import { TableDataViewer } from '@/components/features/TableDataViewer';

interface TableColumn {
    name: string;
    type: string;
    nullable: boolean;
    defaultValue: string | null;
    isPrimaryKey: boolean;
    isForeignKey: boolean;
    foreignKeyReference?: {
        table: string;
        column: string;
    };
}

interface TableInfo {
    name: string;
    rowCount: number;
    sizeBytes: number;
    columns: TableColumn[];
}

interface Props {
    database: StandaloneDatabase;
}

const SIDEBAR_MIN_WIDTH = 200;
const SIDEBAR_MAX_WIDTH = 500;
const SIDEBAR_DEFAULT_WIDTH = 320;

export default function DatabaseTables({ database }: Props) {
    const [tables, setTables] = useState<TableInfo[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [selectedTable, setSelectedTable] = useState<string | null>(null);
    const [searchQuery, setSearchQuery] = useState('');
    const [activeTab, setActiveTab] = useState<'schema' | 'data'>('data');
    const abortControllerRef = useRef<AbortController | null>(null);

    // Sidebar resize state
    const [sidebarWidth, setSidebarWidth] = useState(() => {
        const saved = localStorage.getItem('tables-sidebar-width');
        return saved ? parseInt(saved, 10) : SIDEBAR_DEFAULT_WIDTH;
    });
    const [sidebarCollapsed, setSidebarCollapsed] = useState(() => {
        return localStorage.getItem('tables-sidebar-collapsed') === 'true';
    });
    const [isResizing, setIsResizing] = useState(false);
    const sidebarRef = useRef<HTMLDivElement>(null);

    // Parse size string to bytes for calculations
    const parseSizeToBytes = (size: string): number => {
        const match = size.match(/^([\d.]+)\s*(bytes?|KB|MB|GB|TB)?$/i);
        if (!match) return 0;
        const value = parseFloat(match[1]);
        const unit = (match[2] || 'bytes').toLowerCase();
        const multipliers: Record<string, number> = {
            bytes: 1, byte: 1, kb: 1024, mb: 1024 * 1024, gb: 1024 * 1024 * 1024, tb: 1024 * 1024 * 1024 * 1024,
        };
        return value * (multipliers[unit] || 1);
    };

    // Fetch tables list from API
    const fetchTables = useCallback(async () => {
        abortControllerRef.current?.abort();
        const controller = new AbortController();
        abortControllerRef.current = controller;

        setIsLoading(true);
        setError(null);

        try {
            const response = await fetch(`/_internal/databases/${database.uuid}/tables`, {
                signal: controller.signal,
            });
            const data = await response.json();

            if (controller.signal.aborted) return;

            if (data.available && data.tables) {
                // Transform API response to TableInfo format
                const transformedTables: TableInfo[] = data.tables.map((t: { name: string; rows: number; size: string }) => ({
                    name: t.name,
                    rowCount: t.rows,
                    sizeBytes: parseSizeToBytes(t.size),
                    columns: [], // Will be loaded when table is selected
                }));
                setTables(transformedTables);
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
    }, [database.uuid]);

    // Fetch columns for a specific table
    const fetchTableColumns = useCallback(async (tableName: string) => {
        try {
            const response = await fetch(`/_internal/databases/${database.uuid}/tables/${encodeURIComponent(tableName)}/columns`);
            const data = await response.json();

            if (data.success && data.columns) {
                setTables(prev => prev.map(t => {
                    if (t.name === tableName) {
                        return {
                            ...t,
                            columns: data.columns.map((col: { name: string; type: string; nullable: boolean; default: string | null; is_primary: boolean }) => ({
                                name: col.name,
                                type: col.type,
                                nullable: col.nullable,
                                defaultValue: col.default,
                                isPrimaryKey: col.is_primary,
                                isForeignKey: false, // Not returned by current API
                            })),
                        };
                    }
                    return t;
                }));
            }
        } catch (err) {
            console.error('Failed to fetch columns:', err);
        }
    }, [database.uuid]);

    // Load tables on mount
    useEffect(() => {
        fetchTables();
        return () => {
            abortControllerRef.current?.abort();
        };
    }, [fetchTables]);

    // Save sidebar width to localStorage
    useEffect(() => {
        localStorage.setItem('tables-sidebar-width', sidebarWidth.toString());
    }, [sidebarWidth]);

    // Save sidebar collapsed state to localStorage
    useEffect(() => {
        localStorage.setItem('tables-sidebar-collapsed', sidebarCollapsed.toString());
    }, [sidebarCollapsed]);

    // Handle sidebar resize
    useEffect(() => {
        const handleMouseMove = (e: MouseEvent) => {
            if (!isResizing || !sidebarRef.current) return;
            const containerRect = sidebarRef.current.parentElement?.getBoundingClientRect();
            if (!containerRect) return;
            const newWidth = e.clientX - containerRect.left;
            setSidebarWidth(Math.min(Math.max(newWidth, SIDEBAR_MIN_WIDTH), SIDEBAR_MAX_WIDTH));
        };

        const handleMouseUp = () => {
            setIsResizing(false);
        };

        if (isResizing) {
            document.addEventListener('mousemove', handleMouseMove);
            document.addEventListener('mouseup', handleMouseUp);
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
        }

        return () => {
            document.removeEventListener('mousemove', handleMouseMove);
            document.removeEventListener('mouseup', handleMouseUp);
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
        };
    }, [isResizing]);

    // Handle URL query parameters for deep linking
    useEffect(() => {
        if (isLoading || tables.length === 0) return;

        const params = new URLSearchParams(window.location.search);
        const tableParam = params.get('table');
        const tabParam = params.get('tab');

        if (tableParam) {
            const decodedTable = decodeURIComponent(tableParam);
            const tableExists = tables.some((t) => t.name === decodedTable);
            if (tableExists) {
                setSelectedTable(decodedTable);
                if (tabParam === 'data' || tabParam === 'schema') {
                    setActiveTab(tabParam);
                }
            }
        }
    }, [tables, isLoading]);

    // Fetch columns when table is selected
    useEffect(() => {
        if (selectedTable) {
            const table = tables.find(t => t.name === selectedTable);
            if (table && table.columns.length === 0) {
                fetchTableColumns(selectedTable);
            }
        }
    }, [selectedTable, tables, fetchTableColumns]);

    const filteredTables = tables.filter((table) =>
        table.name.toLowerCase().includes(searchQuery.toLowerCase())
    );

    const selectedTableInfo = selectedTable
        ? tables.find((t) => t.name === selectedTable)
        : null;

    const formatBytes = (bytes: number) => {
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    };

    return (
        <AppLayout
            title={`Tables - ${database.name}`}
            breadcrumbs={[
                { label: 'Databases', href: '/databases' },
                { label: database.name, href: `/databases/${database.uuid}` },
                { label: 'Tables' },
            ]}
        >
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Database Tables</h1>
                        <p className="text-foreground-muted">Browse tables and explore schemas</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button size="sm" variant="secondary" onClick={fetchTables} disabled={isLoading}>
                            <Icons.RefreshCw className={cn("mr-1 h-3 w-3", isLoading && "animate-spin")} />
                            Refresh
                        </Button>
                        <Link href={`/databases/${database.uuid}/query`}>
                            <Button>
                                <Icons.Search className="mr-2 h-4 w-4" />
                                Query Browser
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Error State */}
                {error && (
                    <div className="rounded-lg border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-500">
                        <div className="flex items-center gap-2">
                            <Icons.AlertCircle className="h-4 w-4" />
                            <span>{error}</span>
                        </div>
                    </div>
                )}

                <div className="flex gap-4">
                    {/* Tables List - Resizable Sidebar */}
                    <div
                        ref={sidebarRef}
                        className={cn(
                            "relative flex-shrink-0 transition-all duration-200",
                            sidebarCollapsed && "w-0 overflow-hidden"
                        )}
                        style={{ width: sidebarCollapsed ? 0 : sidebarWidth }}
                    >
                        <Card className="h-full p-4">
                            <div className="mb-4">
                                <div className="relative">
                                    <Icons.Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-subtle" />
                                    <input
                                        type="text"
                                        placeholder="Search tables..."
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        className="h-10 w-full rounded-lg border border-border bg-background pl-10 pr-4 text-sm text-foreground placeholder:text-foreground-subtle focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
                                    />
                                </div>
                            </div>

                            <div className="space-y-1">
                                {filteredTables.map((table) => (
                                    <button
                                        key={table.name}
                                        onClick={() => setSelectedTable(table.name)}
                                        className={cn(
                                            'w-full rounded-lg px-3 py-2.5 text-left transition-colors',
                                            selectedTable === table.name
                                                ? 'bg-primary text-white'
                                                : 'hover:bg-background-secondary'
                                        )}
                                    >
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <Icons.Table className="h-4 w-4" />
                                                <span className="font-mono text-sm font-medium">
                                                    {table.name}
                                                </span>
                                            </div>
                                            <Icons.ChevronRight
                                                className={cn(
                                                    'h-4 w-4',
                                                    selectedTable === table.name
                                                        ? 'opacity-100'
                                                        : 'opacity-0'
                                                )}
                                            />
                                        </div>
                                        <div className="mt-1 flex gap-3 text-xs opacity-80">
                                            <span>{table.rowCount.toLocaleString()} rows</span>
                                            <span>•</span>
                                            <span>{formatBytes(table.sizeBytes)}</span>
                                        </div>
                                    </button>
                                ))}
                            </div>

                            {isLoading && (
                                <div className="flex items-center justify-center py-8">
                                    <Icons.RefreshCw className="h-6 w-6 animate-spin text-foreground-muted" />
                                </div>
                            )}

                            {!isLoading && filteredTables.length === 0 && (
                                <div className="py-8 text-center">
                                    <Icons.Database className="mx-auto mb-2 h-8 w-8 text-foreground-subtle" />
                                    <p className="text-sm text-foreground-muted">No tables found</p>
                                </div>
                            )}

                            {/* Stats */}
                            <div className="mt-4 border-t border-border pt-4">
                                <div className="grid grid-cols-2 gap-4 text-center">
                                    <div>
                                        <div className="text-xl font-bold text-foreground">
                                            {tables.length}
                                        </div>
                                        <div className="text-xs text-foreground-muted">Tables</div>
                                    </div>
                                    <div>
                                        <div className="text-xl font-bold text-foreground">
                                            {formatBytes(
                                                tables.reduce((sum, t) => sum + t.sizeBytes, 0)
                                            )}
                                        </div>
                                        <div className="text-xs text-foreground-muted">Total Size</div>
                                    </div>
                                </div>
                            </div>
                        </Card>
                        {/* Resize Handle */}
                        <div
                            className="absolute right-0 top-0 h-full w-1 cursor-col-resize bg-transparent hover:bg-primary/50 active:bg-primary"
                            onMouseDown={() => setIsResizing(true)}
                        />
                    </div>

                    {/* Collapse/Expand Button */}
                    <button
                        onClick={() => setSidebarCollapsed(!sidebarCollapsed)}
                        className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg border border-border bg-background text-foreground-muted hover:bg-background-secondary hover:text-foreground"
                        title={sidebarCollapsed ? "Show sidebar" : "Hide sidebar"}
                    >
                        {sidebarCollapsed ? (
                            <Icons.PanelLeftOpen className="h-4 w-4" />
                        ) : (
                            <Icons.PanelLeftClose className="h-4 w-4" />
                        )}
                    </button>

                    {/* Table Details */}
                    <div className="min-w-0 flex-1">
                        {selectedTableInfo ? (
                            <Card className="p-6">
                                {/* Table Header */}
                                <div className="mb-6 flex items-start justify-between">
                                    <div>
                                        <h2 className="mb-2 flex items-center gap-2 text-xl font-bold text-foreground">
                                            <Icons.Table className="h-5 w-5" />
                                            {selectedTableInfo.name}
                                        </h2>
                                        <div className="flex gap-4 text-sm text-foreground-muted">
                                            <span>
                                                {selectedTableInfo.rowCount.toLocaleString()} rows
                                            </span>
                                            <span>•</span>
                                            <span>{formatBytes(selectedTableInfo.sizeBytes)}</span>
                                            <span>•</span>
                                            <span>{selectedTableInfo.columns.length} columns</span>
                                        </div>
                                    </div>
                                    <Button size="sm" variant="secondary">
                                        <Icons.Download className="mr-1.5 h-3.5 w-3.5" />
                                        Export
                                    </Button>
                                </div>

                                {/* Tabs */}
                                <div className="mb-6 flex gap-1 border-b border-border">
                                    <button
                                        onClick={() => setActiveTab('schema')}
                                        className={cn(
                                            'flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium transition-colors',
                                            activeTab === 'schema'
                                                ? 'border-primary text-primary'
                                                : 'border-transparent text-foreground-muted hover:text-foreground'
                                        )}
                                    >
                                        <Icons.Database className="h-4 w-4" />
                                        Schema
                                    </button>
                                    <button
                                        onClick={() => setActiveTab('data')}
                                        className={cn(
                                            'flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium transition-colors',
                                            activeTab === 'data'
                                                ? 'border-primary text-primary'
                                                : 'border-transparent text-foreground-muted hover:text-foreground'
                                        )}
                                    >
                                        <Icons.Table className="h-4 w-4" />
                                        Data
                                    </button>
                                </div>

                                {/* Tab Content */}
                                {activeTab === 'schema' ? (
                                    <>
                                        {/* Columns Table */}
                                        <div>
                                            <h3 className="mb-3 text-sm font-semibold text-foreground">
                                                Columns
                                            </h3>
                                    <div className="overflow-hidden rounded-lg border border-border">
                                        <table className="w-full">
                                            <thead className="bg-background-secondary">
                                                <tr>
                                                    <th className="border-b border-border px-4 py-2 text-left text-xs font-semibold text-foreground">
                                                        Name
                                                    </th>
                                                    <th className="border-b border-border px-4 py-2 text-left text-xs font-semibold text-foreground">
                                                        Type
                                                    </th>
                                                    <th className="border-b border-border px-4 py-2 text-left text-xs font-semibold text-foreground">
                                                        Nullable
                                                    </th>
                                                    <th className="border-b border-border px-4 py-2 text-left text-xs font-semibold text-foreground">
                                                        Default
                                                    </th>
                                                    <th className="border-b border-border px-4 py-2 text-left text-xs font-semibold text-foreground">
                                                        Constraints
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {selectedTableInfo.columns.map((col) => (
                                                    <tr
                                                        key={col.name}
                                                        className="border-b border-border/50 transition-colors hover:bg-background-secondary/50"
                                                    >
                                                        <td className="px-4 py-3">
                                                            <div className="flex items-center gap-2">
                                                                <span className="font-mono text-sm text-foreground">
                                                                    {col.name}
                                                                </span>
                                                                {col.isPrimaryKey && (
                                                                    <Icons.Key className="h-3.5 w-3.5 text-amber-500" />
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3 font-mono text-xs text-foreground-muted">
                                                            {col.type}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            {col.nullable ? (
                                                                <Badge variant="default" className="text-xs">
                                                                    Yes
                                                                </Badge>
                                                            ) : (
                                                                <Badge variant="danger" className="text-xs">
                                                                    No
                                                                </Badge>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 font-mono text-xs text-foreground-muted">
                                                            {col.defaultValue || (
                                                                <span className="text-foreground-subtle">
                                                                    NULL
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <div className="flex gap-1">
                                                                {col.isPrimaryKey && (
                                                                    <Badge
                                                                        variant="warning"
                                                                        className="text-xs"
                                                                    >
                                                                        PK
                                                                    </Badge>
                                                                )}
                                                                {col.isForeignKey && (
                                                                    <Badge
                                                                        variant="info"
                                                                        className="text-xs"
                                                                        title={`References ${col.foreignKeyReference?.table}.${col.foreignKeyReference?.column}`}
                                                                    >
                                                                        FK
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                        {/* Foreign Key Relationships */}
                                        {selectedTableInfo.columns.some((c) => c.isForeignKey) && (
                                            <div className="mt-6">
                                                <h3 className="mb-3 text-sm font-semibold text-foreground">
                                                    Foreign Key Relationships
                                                </h3>
                                                <div className="space-y-2">
                                                    {selectedTableInfo.columns
                                                        .filter((c) => c.isForeignKey)
                                                        .map((col) => (
                                                            <div
                                                                key={col.name}
                                                                className="rounded-lg border border-border/50 bg-background-secondary/50 p-3"
                                                            >
                                                                <div className="flex items-center gap-2 text-sm">
                                                                    <Icons.Link className="h-4 w-4 text-blue-500" />
                                                                    <span className="font-mono text-foreground">
                                                                        {col.name}
                                                                    </span>
                                                                    <Icons.ArrowRight className="h-3.5 w-3.5 text-foreground-subtle" />
                                                                    <span className="font-mono text-foreground-muted">
                                                                        {col.foreignKeyReference?.table}.
                                                                        {col.foreignKeyReference?.column}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        ))}
                                                </div>
                                            </div>
                                        )}
                                    </>
                                ) : (
                                    <TableDataViewer
                                        databaseUuid={database.uuid}
                                        tableName={selectedTableInfo.name}
                                    />
                                )}
                            </Card>
                        ) : (
                            <Card className="p-12 text-center">
                                <Icons.Table className="mx-auto mb-4 h-12 w-12 text-foreground-subtle" />
                                <h3 className="mb-2 text-lg font-semibold text-foreground">
                                    No Table Selected
                                </h3>
                                <p className="text-foreground-muted">
                                    Select a table from the list to view its schema and details.
                                </p>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
