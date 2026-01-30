import { AppLayout } from '@/components/layout';
import { Button, Card, Badge } from '@/components/ui';
import { Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
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
    tables?: TableInfo[];
}

export default function DatabaseTables({ database, tables = [] }: Props) {
    const [selectedTable, setSelectedTable] = useState<string | null>(null);
    const [searchQuery, setSearchQuery] = useState('');
    const [activeTab, setActiveTab] = useState<'schema' | 'data'>('schema');

    // Handle URL query parameters for deep linking
    useEffect(() => {
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
    }, [tables]);

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
                    <Link href={`/databases/${database.uuid}/query`}>
                        <Button>
                            <Icons.Search className="mr-2 h-4 w-4" />
                            Query Browser
                        </Button>
                    </Link>
                </div>

                <div className="grid gap-6 lg:grid-cols-12">
                    {/* Tables List */}
                    <div className="lg:col-span-4">
                        <Card className="p-4">
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

                            {filteredTables.length === 0 && (
                                <div className="py-8 text-center">
                                    <Icons.Search className="mx-auto mb-2 h-8 w-8 text-foreground-subtle" />
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
                    </div>

                    {/* Table Details */}
                    <div className="lg:col-span-8">
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
