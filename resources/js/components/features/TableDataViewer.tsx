import { useState, useEffect, useCallback } from 'react';
import { Button, Badge, Modal, Input } from '@/components/ui';
import * as Icons from 'lucide-react';
import { cn } from '@/lib/utils';

interface Column {
    name: string;
    type: string;
    nullable: boolean;
    default: string | null;
    is_primary: boolean;
}

interface TableData {
    [key: string]: string | null;
}

interface TableDataViewerProps {
    databaseUuid: string;
    tableName: string;
}

export function TableDataViewer({ databaseUuid, tableName }: TableDataViewerProps) {
    const [data, setData] = useState<TableData[]>([]);
    const [columns, setColumns] = useState<Column[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    // Pagination
    const [currentPage, setCurrentPage] = useState(1);
    const [perPage, setPerPage] = useState(50);
    const [totalRows, setTotalRows] = useState(0);
    const [lastPage, setLastPage] = useState(1);

    // Search and filters
    const [searchQuery, setSearchQuery] = useState('');
    const [orderBy, setOrderBy] = useState('');
    const [orderDir, setOrderDir] = useState<'asc' | 'desc'>('asc');

    // Modals
    const [editingRow, setEditingRow] = useState<TableData | null>(null);
    const [creatingRow, setCreatingRow] = useState(false);
    const [deletingRow, setDeletingRow] = useState<TableData | null>(null);
    const [formData, setFormData] = useState<Record<string, string>>({});

    const fetchData = useCallback(async () => {
        setIsLoading(true);
        setError(null);

        try {
            const params = new URLSearchParams({
                page: currentPage.toString(),
                per_page: perPage.toString(),
                search: searchQuery,
                order_by: orderBy,
                order_dir: orderDir,
            });

            const response = await fetch(
                `/_internal/databases/${databaseUuid}/tables/${encodeURIComponent(tableName)}/data?${params}`
            );
            const result = await response.json();

            if (result.success) {
                setData(result.data);
                setColumns(result.columns);
                setTotalRows(result.pagination.total);
                setLastPage(result.pagination.last_page);
            } else {
                setError(result.error || 'Failed to fetch table data');
            }
        } catch (err) {
            setError('Failed to connect to server');
        } finally {
            setIsLoading(false);
        }
    }, [databaseUuid, tableName, currentPage, perPage, searchQuery, orderBy, orderDir]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const handleSort = (columnName: string) => {
        if (orderBy === columnName) {
            setOrderDir(orderDir === 'asc' ? 'desc' : 'asc');
        } else {
            setOrderBy(columnName);
            setOrderDir('asc');
        }
        setCurrentPage(1);
    };

    const handleSearch = () => {
        setCurrentPage(1);
        fetchData();
    };

    const getPrimaryKeyValue = (row: TableData): Record<string, string> => {
        const primaryKeys = columns.filter((c) => c.is_primary);
        const pkValue: Record<string, string> = {};
        primaryKeys.forEach((pk) => {
            pkValue[pk.name] = row[pk.name] as string;
        });
        return pkValue;
    };

    const openEditModal = (row: TableData) => {
        setEditingRow(row);
        const formValues: Record<string, string> = {};
        columns.forEach((col) => {
            formValues[col.name] = row[col.name] as string || '';
        });
        setFormData(formValues);
    };

    const openCreateModal = () => {
        setCreatingRow(true);
        const formValues: Record<string, string> = {};
        columns.forEach((col) => {
            formValues[col.name] = col.default || '';
        });
        setFormData(formValues);
    };

    const closeModals = () => {
        setEditingRow(null);
        setCreatingRow(false);
        setDeletingRow(null);
        setFormData({});
    };

    const handleUpdate = async () => {
        if (!editingRow) return;

        try {
            const primaryKey = getPrimaryKeyValue(editingRow);
            const updates: Record<string, string> = {};

            // Only include changed fields
            columns.forEach((col) => {
                if (!col.is_primary && formData[col.name] !== editingRow[col.name]) {
                    updates[col.name] = formData[col.name];
                }
            });

            const response = await fetch(
                `/_internal/databases/${databaseUuid}/tables/${encodeURIComponent(tableName)}/rows`,
                {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ primary_key: primaryKey, updates }),
                }
            );

            const result = await response.json();
            if (result.success) {
                closeModals();
                fetchData();
            } else {
                setError(result.error || 'Failed to update row');
            }
        } catch (err) {
            setError('Failed to update row');
        }
    };

    const handleCreate = async () => {
        try {
            const response = await fetch(
                `/_internal/databases/${databaseUuid}/tables/${encodeURIComponent(tableName)}/rows`,
                {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ data: formData }),
                }
            );

            const result = await response.json();
            if (result.success) {
                closeModals();
                fetchData();
            } else {
                setError(result.error || 'Failed to create row');
            }
        } catch (err) {
            setError('Failed to create row');
        }
    };

    const handleDelete = async () => {
        if (!deletingRow) return;

        try {
            const primaryKey = getPrimaryKeyValue(deletingRow);
            const response = await fetch(
                `/_internal/databases/${databaseUuid}/tables/${encodeURIComponent(tableName)}/rows`,
                {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ primary_key: primaryKey }),
                }
            );

            const result = await response.json();
            if (result.success) {
                closeModals();
                fetchData();
            } else {
                setError(result.error || 'Failed to delete row');
            }
        } catch (err) {
            setError('Failed to delete row');
        }
    };

    if (isLoading && data.length === 0) {
        return (
            <div className="flex items-center justify-center py-12">
                <Icons.RefreshCw className="h-6 w-6 animate-spin text-foreground-muted" />
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Toolbar */}
            <div className="flex items-center justify-between gap-4">
                <div className="flex flex-1 items-center gap-2">
                    <div className="relative flex-1 max-w-md">
                        <Icons.Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-subtle" />
                        <input
                            type="text"
                            placeholder="Search across all columns..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                            className="h-10 w-full rounded-lg border border-border bg-background pl-10 pr-4 text-sm text-foreground placeholder:text-foreground-subtle focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
                        />
                    </div>
                    <Button size="sm" variant="secondary" onClick={handleSearch}>
                        Search
                    </Button>
                </div>
                <div className="flex items-center gap-2">
                    <Button size="sm" variant="secondary" onClick={fetchData}>
                        <Icons.RefreshCw className="mr-1.5 h-3.5 w-3.5" />
                        Refresh
                    </Button>
                    <Button size="sm" onClick={openCreateModal}>
                        <Icons.Plus className="mr-1.5 h-3.5 w-3.5" />
                        Add Row
                    </Button>
                </div>
            </div>

            {/* Error Display */}
            {error && (
                <div className="rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-500">
                    {error}
                    <button
                        onClick={() => setError(null)}
                        className="ml-2 underline hover:no-underline"
                    >
                        Dismiss
                    </button>
                </div>
            )}

            {/* Data Table */}
            <div className="overflow-x-auto rounded-lg border border-border">
                <table className="w-full">
                    <thead className="bg-background-secondary">
                        <tr>
                            {columns.map((col) => (
                                <th
                                    key={col.name}
                                    className="border-b border-border px-4 py-2 text-left text-xs font-semibold text-foreground"
                                >
                                    <button
                                        onClick={() => handleSort(col.name)}
                                        className="flex items-center gap-1.5 hover:text-primary"
                                    >
                                        <span>{col.name}</span>
                                        {col.is_primary && (
                                            <Icons.Key className="h-3 w-3 text-amber-500" />
                                        )}
                                        {orderBy === col.name && (
                                            <Icons.ChevronDown
                                                className={cn(
                                                    'h-3.5 w-3.5',
                                                    orderDir === 'asc' && 'rotate-180'
                                                )}
                                            />
                                        )}
                                    </button>
                                    <div className="mt-0.5 text-xs font-normal text-foreground-muted">
                                        {col.type}
                                    </div>
                                </th>
                            ))}
                            <th className="border-b border-border px-4 py-2 text-right text-xs font-semibold text-foreground">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.map((row, rowIdx) => (
                            <tr
                                key={rowIdx}
                                className="border-b border-border/50 transition-colors hover:bg-background-secondary/50"
                            >
                                {columns.map((col) => (
                                    <td
                                        key={col.name}
                                        className="px-4 py-3 font-mono text-sm text-foreground"
                                    >
                                        {row[col.name] === null ? (
                                            <span className="italic text-foreground-subtle">NULL</span>
                                        ) : (
                                            <span className="break-all">
                                                {String(row[col.name]).length > 100
                                                    ? String(row[col.name]).substring(0, 100) + '...'
                                                    : String(row[col.name])}
                                            </span>
                                        )}
                                    </td>
                                ))}
                                <td className="px-4 py-3 text-right">
                                    <div className="flex justify-end gap-1">
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => openEditModal(row)}
                                        >
                                            <Icons.Edit className="h-3.5 w-3.5" />
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => setDeletingRow(row)}
                                        >
                                            <Icons.Trash className="h-3.5 w-3.5 text-red-500" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Pagination */}
            <div className="flex items-center justify-between">
                <div className="text-sm text-foreground-muted">
                    Showing {(currentPage - 1) * perPage + 1} to{' '}
                    {Math.min(currentPage * perPage, totalRows)} of {totalRows} rows
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        size="sm"
                        variant="secondary"
                        disabled={currentPage === 1}
                        onClick={() => setCurrentPage(currentPage - 1)}
                    >
                        <Icons.ChevronLeft className="h-4 w-4" />
                    </Button>
                    <span className="text-sm text-foreground">
                        Page {currentPage} of {lastPage}
                    </span>
                    <Button
                        size="sm"
                        variant="secondary"
                        disabled={currentPage >= lastPage}
                        onClick={() => setCurrentPage(currentPage + 1)}
                    >
                        <Icons.ChevronRight className="h-4 w-4" />
                    </Button>
                </div>
            </div>

            {/* Edit Modal */}
            {editingRow && (
                <Modal
                    isOpen={true}
                    onClose={closeModals}
                    title={`Edit Row in ${tableName}`}
                    size="lg"
                >
                    <div className="space-y-4">
                        {columns.map((col) => (
                            <div key={col.name}>
                                <label className="mb-1 flex items-center gap-2 text-sm font-medium text-foreground">
                                    {col.name}
                                    {col.is_primary && (
                                        <Badge variant="warning" className="text-xs">
                                            PK
                                        </Badge>
                                    )}
                                    {!col.nullable && (
                                        <Badge variant="danger" className="text-xs">
                                            Required
                                        </Badge>
                                    )}
                                </label>
                                <Input
                                    value={formData[col.name] || ''}
                                    onChange={(e) =>
                                        setFormData({ ...formData, [col.name]: e.target.value })
                                    }
                                    disabled={col.is_primary}
                                    placeholder={col.type}
                                />
                                <p className="mt-1 text-xs text-foreground-muted">{col.type}</p>
                            </div>
                        ))}
                        <div className="flex justify-end gap-2 pt-4">
                            <Button variant="secondary" onClick={closeModals}>
                                Cancel
                            </Button>
                            <Button onClick={handleUpdate}>Save Changes</Button>
                        </div>
                    </div>
                </Modal>
            )}

            {/* Create Modal */}
            {creatingRow && (
                <Modal
                    isOpen={true}
                    onClose={closeModals}
                    title={`Add Row to ${tableName}`}
                    size="lg"
                >
                    <div className="space-y-4">
                        {columns.map((col) => (
                            <div key={col.name}>
                                <label className="mb-1 flex items-center gap-2 text-sm font-medium text-foreground">
                                    {col.name}
                                    {col.is_primary && (
                                        <Badge variant="warning" className="text-xs">
                                            PK
                                        </Badge>
                                    )}
                                    {!col.nullable && (
                                        <Badge variant="danger" className="text-xs">
                                            Required
                                        </Badge>
                                    )}
                                </label>
                                <Input
                                    value={formData[col.name] || ''}
                                    onChange={(e) =>
                                        setFormData({ ...formData, [col.name]: e.target.value })
                                    }
                                    placeholder={col.default || col.type}
                                />
                                <p className="mt-1 text-xs text-foreground-muted">
                                    {col.type}
                                    {col.default && ` • Default: ${col.default}`}
                                </p>
                            </div>
                        ))}
                        <div className="flex justify-end gap-2 pt-4">
                            <Button variant="secondary" onClick={closeModals}>
                                Cancel
                            </Button>
                            <Button onClick={handleCreate}>Create Row</Button>
                        </div>
                    </div>
                </Modal>
            )}

            {/* Delete Confirmation Modal */}
            {deletingRow && (
                <Modal isOpen={true} onClose={closeModals} title="Confirm Delete" size="sm">
                    <div className="space-y-4">
                        <p className="text-sm text-foreground-muted">
                            Are you sure you want to delete this row? This action cannot be undone.
                        </p>
                        <div className="rounded-lg border border-border bg-background-secondary p-3">
                            <pre className="text-xs">
                                {JSON.stringify(deletingRow, null, 2)}
                            </pre>
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button variant="secondary" onClick={closeModals}>
                                Cancel
                            </Button>
                            <Button variant="danger" onClick={handleDelete}>
                                Delete Row
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
}
