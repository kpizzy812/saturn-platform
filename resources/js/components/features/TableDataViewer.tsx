import { useState, useEffect, useCallback, useRef } from 'react';
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

interface EditingCell {
    rowIndex: number;
    columnName: string;
    value: string;
}

interface ContextMenu {
    x: number;
    y: number;
    rowIndex: number;
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

    // Selection state
    const [selectedRows, setSelectedRows] = useState<Set<number>>(new Set());
    const [lastSelectedRow, setLastSelectedRow] = useState<number | null>(null);

    // Inline editing state
    const [editingCell, setEditingCell] = useState<EditingCell | null>(null);
    const [pendingChanges, setPendingChanges] = useState<Map<number, Partial<TableData>>>(new Map());
    const editInputRef = useRef<HTMLInputElement>(null);

    // Pagination
    const [currentPage, setCurrentPage] = useState(1);
    const [perPage, setPerPage] = useState(50);
    const [totalRows, setTotalRows] = useState(0);
    const [lastPage, setLastPage] = useState(1);

    // Search and filters
    const [searchQuery, setSearchQuery] = useState('');
    const [orderBy, setOrderBy] = useState('');
    const [orderDir, setOrderDir] = useState<'asc' | 'desc'>('asc');

    // Context menu
    const [contextMenu, setContextMenu] = useState<ContextMenu | null>(null);

    // Modals
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
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
                setSelectedRows(new Set());
                setPendingChanges(new Map());
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

    // Focus input when editing starts
    useEffect(() => {
        if (editingCell && editInputRef.current) {
            editInputRef.current.focus();
            editInputRef.current.select();
        }
    }, [editingCell]);

    // Global keyboard shortcuts
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            // Cmd+S / Ctrl+S - Save all pending changes
            if ((e.metaKey || e.ctrlKey) && e.key === 's') {
                e.preventDefault();
                if (pendingChanges.size > 0) {
                    savePendingChanges();
                }
                return;
            }

            // Backspace / Delete - Delete selected rows
            if ((e.key === 'Backspace' || e.key === 'Delete') && !editingCell && selectedRows.size > 0) {
                e.preventDefault();
                setShowDeleteConfirm(true);
                return;
            }

            // Escape - Cancel editing
            if (e.key === 'Escape' && editingCell) {
                setEditingCell(null);
                return;
            }

            // Enter - Save cell edit
            if (e.key === 'Enter' && editingCell) {
                saveCellEdit();
                return;
            }
        };

        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [editingCell, selectedRows, pendingChanges]);

    // Close context menu on click outside
    useEffect(() => {
        const handleClick = () => setContextMenu(null);
        if (contextMenu) {
            document.addEventListener('click', handleClick);
            return () => document.removeEventListener('click', handleClick);
        }
    }, [contextMenu]);

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

    const handleRowSelect = (rowIndex: number, e: React.MouseEvent) => {
        const newSelected = new Set(selectedRows);

        if (e.shiftKey && lastSelectedRow !== null) {
            // Shift+Click - Select range
            const start = Math.min(lastSelectedRow, rowIndex);
            const end = Math.max(lastSelectedRow, rowIndex);
            for (let i = start; i <= end; i++) {
                newSelected.add(i);
            }
        } else if (e.metaKey || e.ctrlKey) {
            // Cmd/Ctrl+Click - Toggle selection
            if (newSelected.has(rowIndex)) {
                newSelected.delete(rowIndex);
            } else {
                newSelected.add(rowIndex);
            }
        } else {
            // Regular click - Select only this row
            newSelected.clear();
            newSelected.add(rowIndex);
        }

        setSelectedRows(newSelected);
        setLastSelectedRow(rowIndex);
    };

    const handleSelectAll = () => {
        if (selectedRows.size === data.length) {
            setSelectedRows(new Set());
        } else {
            setSelectedRows(new Set(data.map((_, idx) => idx)));
        }
    };

    const handleCellDoubleClick = (rowIndex: number, columnName: string, currentValue: string | null) => {
        const column = columns.find((c) => c.name === columnName);
        if (column?.is_primary) return; // Cannot edit primary keys

        setEditingCell({
            rowIndex,
            columnName,
            value: currentValue || '',
        });
    };

    const saveCellEdit = () => {
        if (!editingCell) return;

        const { rowIndex, columnName, value } = editingCell;
        const row = data[rowIndex];

        // Update pending changes
        const rowChanges = pendingChanges.get(rowIndex) || {};
        rowChanges[columnName] = value;
        const newPendingChanges = new Map(pendingChanges);
        newPendingChanges.set(rowIndex, rowChanges);
        setPendingChanges(newPendingChanges);

        // Update local data immediately for UI feedback
        const newData = [...data];
        newData[rowIndex] = { ...row, [columnName]: value };
        setData(newData);

        setEditingCell(null);
    };

    const savePendingChanges = async () => {
        if (pendingChanges.size === 0) return;

        try {
            // Save each changed row
            for (const [rowIndex, changes] of pendingChanges.entries()) {
                const row = data[rowIndex];
                const primaryKey = getPrimaryKeyValue(row);

                const response = await fetch(
                    `/_internal/databases/${databaseUuid}/tables/${encodeURIComponent(tableName)}/rows`,
                    {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ primary_key: primaryKey, updates: changes }),
                    }
                );

                const result = await response.json();
                if (!result.success) {
                    setError(`Failed to save row ${rowIndex + 1}: ${result.error}`);
                    return;
                }
            }

            setPendingChanges(new Map());
            fetchData(); // Refresh data
        } catch (err) {
            setError('Failed to save changes');
        }
    };

    const getPrimaryKeyValue = (row: TableData): Record<string, string> => {
        const primaryKeys = columns.filter((c) => c.is_primary);
        const pkValue: Record<string, string> = {};
        primaryKeys.forEach((pk) => {
            pkValue[pk.name] = row[pk.name] as string;
        });
        return pkValue;
    };

    const handleContextMenu = (e: React.MouseEvent, rowIndex: number) => {
        e.preventDefault();
        setContextMenu({ x: e.clientX, y: e.clientY, rowIndex });

        // Select the row if not already selected
        if (!selectedRows.has(rowIndex)) {
            setSelectedRows(new Set([rowIndex]));
        }
    };

    const duplicateRow = (rowIndex: number) => {
        const row = data[rowIndex];
        const duplicateData: Record<string, string> = {};

        columns.forEach((col) => {
            if (!col.is_primary) {
                duplicateData[col.name] = row[col.name] as string || '';
            }
        });

        setFormData(duplicateData);
        setShowCreateModal(true);
        setContextMenu(null);
    };

    const handleDeleteSelected = async () => {
        if (selectedRows.size === 0) return;

        try {
            const selectedIndices = Array.from(selectedRows).sort((a, b) => b - a); // Delete from end to preserve indices

            for (const rowIndex of selectedIndices) {
                const row = data[rowIndex];
                const primaryKey = getPrimaryKeyValue(row);

                const response = await fetch(
                    `/_internal/databases/${databaseUuid}/tables/${encodeURIComponent(tableName)}/rows`,
                    {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ primary_key: primaryKey }),
                    }
                );

                const result = await response.json();
                if (!result.success) {
                    setError(`Failed to delete row: ${result.error}`);
                    return;
                }
            }

            setShowDeleteConfirm(false);
            setSelectedRows(new Set());
            fetchData();
        } catch (err) {
            setError('Failed to delete rows');
        }
    };

    const openCreateModal = () => {
        const formValues: Record<string, string> = {};
        columns.forEach((col) => {
            formValues[col.name] = col.default || '';
        });
        setFormData(formValues);
        setShowCreateModal(true);
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
                setShowCreateModal(false);
                fetchData();
            } else {
                setError(result.error || 'Failed to create row');
            }
        } catch (err) {
            setError('Failed to create row');
        }
    };

    if (isLoading && data.length === 0) {
        return (
            <div className="flex items-center justify-center py-12">
                <Icons.RefreshCw className="h-6 w-6 animate-spin text-foreground-muted" />
            </div>
        );
    }

    const hasChanges = pendingChanges.size > 0;

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
                    {selectedRows.size > 0 && (
                        <Button
                            size="sm"
                            variant="danger"
                            onClick={() => setShowDeleteConfirm(true)}
                        >
                            <Icons.Delete className="mr-1.5 h-3.5 w-3.5" />
                            Delete ({selectedRows.size})
                        </Button>
                    )}
                    {hasChanges && (
                        <Button size="sm" variant="warning" onClick={savePendingChanges}>
                            <Icons.Save className="mr-1.5 h-3.5 w-3.5" />
                            Save Changes ({pendingChanges.size})
                        </Button>
                    )}
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

            {/* Info Banner */}
            <div className="rounded-lg border border-blue-500/30 bg-blue-500/10 p-3 text-sm text-blue-500">
                <div className="flex items-start gap-2">
                    <Icons.Info className="h-4 w-4 mt-0.5" />
                    <div>
                        <strong>Keyboard Shortcuts:</strong> Double-click to edit • Enter to save •
                        Escape to cancel • Cmd+S to save all • Backspace/Delete to delete selected •
                        Right-click for more options
                    </div>
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
                            <th className="border-b border-border px-4 py-2 w-12">
                                <input
                                    type="checkbox"
                                    checked={selectedRows.size === data.length && data.length > 0}
                                    onChange={handleSelectAll}
                                    className="h-4 w-4 rounded border-border"
                                />
                            </th>
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
                        </tr>
                    </thead>
                    <tbody>
                        {data.map((row, rowIdx) => {
                            const isSelected = selectedRows.has(rowIdx);
                            const hasRowChanges = pendingChanges.has(rowIdx);

                            return (
                                <tr
                                    key={rowIdx}
                                    className={cn(
                                        'border-b border-border/50 transition-colors',
                                        isSelected && 'bg-primary/10',
                                        hasRowChanges && 'bg-yellow-500/10',
                                        !isSelected && !hasRowChanges && 'hover:bg-background-secondary/50'
                                    )}
                                    onContextMenu={(e) => handleContextMenu(e, rowIdx)}
                                >
                                    <td className="px-4 py-2">
                                        <input
                                            type="checkbox"
                                            checked={isSelected}
                                            onChange={(e) => handleRowSelect(rowIdx, e as any)}
                                            className="h-4 w-4 rounded border-border"
                                        />
                                    </td>
                                    {columns.map((col) => {
                                        const isEditing =
                                            editingCell?.rowIndex === rowIdx &&
                                            editingCell?.columnName === col.name;
                                        const cellValue = row[col.name];
                                        const hasChange = pendingChanges.get(rowIdx)?.[col.name] !== undefined;

                                        return (
                                            <td
                                                key={col.name}
                                                className={cn(
                                                    'px-4 py-2 font-mono text-sm',
                                                    col.is_primary
                                                        ? 'text-foreground-muted cursor-not-allowed'
                                                        : 'text-foreground cursor-text',
                                                    hasChange && 'bg-yellow-500/20'
                                                )}
                                                onDoubleClick={() =>
                                                    !col.is_primary &&
                                                    handleCellDoubleClick(rowIdx, col.name, cellValue)
                                                }
                                            >
                                                {isEditing ? (
                                                    <input
                                                        ref={editInputRef}
                                                        type="text"
                                                        value={editingCell.value}
                                                        onChange={(e) =>
                                                            setEditingCell({
                                                                ...editingCell,
                                                                value: e.target.value,
                                                            })
                                                        }
                                                        onBlur={saveCellEdit}
                                                        className="w-full border border-primary bg-background px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                                    />
                                                ) : cellValue === null ? (
                                                    <span className="italic text-foreground-subtle">
                                                        NULL
                                                    </span>
                                                ) : (
                                                    <span className="break-all">
                                                        {String(cellValue).length > 100
                                                            ? String(cellValue).substring(0, 100) + '...'
                                                            : String(cellValue)}
                                                    </span>
                                                )}
                                            </td>
                                        );
                                    })}
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            {/* Pagination */}
            <div className="flex items-center justify-between">
                <div className="text-sm text-foreground-muted">
                    Showing {(currentPage - 1) * perPage + 1} to{' '}
                    {Math.min(currentPage * perPage, totalRows)} of {totalRows} rows
                    {selectedRows.size > 0 && ` • ${selectedRows.size} selected`}
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

            {/* Context Menu */}
            {contextMenu && (
                <div
                    className="fixed z-50 min-w-[160px] rounded-lg border border-border bg-background shadow-lg"
                    style={{ top: contextMenu.y, left: contextMenu.x }}
                >
                    <button
                        onClick={() => {
                            const row = data[contextMenu.rowIndex];
                            columns.forEach((col) => {
                                if (!col.is_primary) {
                                    handleCellDoubleClick(
                                        contextMenu.rowIndex,
                                        col.name,
                                        row[col.name]
                                    );
                                }
                            });
                            setContextMenu(null);
                        }}
                        className="flex w-full items-center gap-2 px-4 py-2 text-sm text-foreground hover:bg-background-secondary"
                    >
                        <Icons.Edit className="h-4 w-4" />
                        Edit Row
                    </button>
                    <button
                        onClick={() => duplicateRow(contextMenu.rowIndex)}
                        className="flex w-full items-center gap-2 px-4 py-2 text-sm text-foreground hover:bg-background-secondary"
                    >
                        <Icons.Copy className="h-4 w-4" />
                        Duplicate Row
                    </button>
                    <div className="border-t border-border" />
                    <button
                        onClick={() => {
                            setShowDeleteConfirm(true);
                            setContextMenu(null);
                        }}
                        className="flex w-full items-center gap-2 px-4 py-2 text-sm text-red-500 hover:bg-red-500/10"
                    >
                        <Icons.Trash className="h-4 w-4" />
                        Delete Row{selectedRows.size > 1 ? 's' : ''}
                    </button>
                </div>
            )}

            {/* Create Modal */}
            {showCreateModal && (
                <Modal
                    isOpen={true}
                    onClose={() => setShowCreateModal(false)}
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
                            <Button variant="secondary" onClick={() => setShowCreateModal(false)}>
                                Cancel
                            </Button>
                            <Button onClick={handleCreate}>Create Row</Button>
                        </div>
                    </div>
                </Modal>
            )}

            {/* Delete Confirmation Modal */}
            {showDeleteConfirm && (
                <Modal
                    isOpen={true}
                    onClose={() => setShowDeleteConfirm(false)}
                    title="Confirm Delete"
                    size="sm"
                >
                    <div className="space-y-4">
                        <p className="text-sm text-foreground-muted">
                            Are you sure you want to delete {selectedRows.size} row
                            {selectedRows.size > 1 ? 's' : ''}? This action cannot be undone.
                        </p>
                        <div className="flex justify-end gap-2">
                            <Button variant="secondary" onClick={() => setShowDeleteConfirm(false)}>
                                Cancel
                            </Button>
                            <Button variant="danger" onClick={handleDeleteSelected}>
                                Delete {selectedRows.size} Row{selectedRows.size > 1 ? 's' : ''}
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
}
