import { useState, useEffect, useCallback, useRef } from 'react';
import { Button, Badge, Modal, Input } from '@/components/ui';
import * as Icons from 'lucide-react';
import { cn } from '@/lib/utils';
import { toCSV, downloadFile, stripBOM } from '@/lib/csv';
import { FilterBuilder, buildWhereClause, type FilterGroup } from './FilterBuilder';

// Get CSRF token from meta tag
const getCsrfToken = (): string => {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta?.getAttribute('content') || '';
};

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
    const [perPage, _setPerPage] = useState(50);
    const [totalRows, setTotalRows] = useState(0);
    const [lastPage, setLastPage] = useState(1);

    // Search and filters
    const [searchQuery, setSearchQuery] = useState('');
    const [orderBy, setOrderBy] = useState('');
    const [orderDir, setOrderDir] = useState<'asc' | 'desc'>('asc');
    const [filters, setFilters] = useState<FilterGroup>({ logic: 'AND', conditions: [] });

    // Context menu
    const [contextMenu, setContextMenu] = useState<ContextMenu | null>(null);

    // Modals
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [formData, setFormData] = useState<Record<string, string>>({});

    // Export
    const [showExportMenu, setShowExportMenu] = useState(false);
    const exportMenuRef = useRef<HTMLDivElement>(null);

    // Import
    const [showImportModal, setShowImportModal] = useState(false);
    const [importFile, setImportFile] = useState<File | null>(null);
    const [importData, setImportData] = useState<TableData[]>([]);
    const [importColumns, setImportColumns] = useState<string[]>([]);
    const [columnMapping, setColumnMapping] = useState<Record<string, string>>({});
    const [importStep, setImportStep] = useState<'upload' | 'preview'>('upload');
    const [isImporting, setIsImporting] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    // Inline column filters
    const [columnFilters, setColumnFilters] = useState<Record<string, string>>({});

    const fetchData = useCallback(async () => {
        setIsLoading(true);
        setError(null);

        try {
            // Combine advanced filters with inline column filters
            const advancedWhereClause = buildWhereClause(filters);

            // Build inline column filters clause
            const inlineFilterClauses = Object.entries(columnFilters)
                .filter(([, value]) => value.trim() !== '')
                .map(([col, value]) => {
                    const escapedValue = value.replace(/'/g, "''");
                    return `"${col}" ILIKE '%${escapedValue}%'`;
                });

            // Combine all filter clauses
            const allClauses = [
                advancedWhereClause,
                ...inlineFilterClauses
            ].filter(c => c.trim() !== '');

            const combinedFilters = allClauses.join(' AND ');

            const params = new URLSearchParams({
                page: currentPage.toString(),
                per_page: perPage.toString(),
                search: searchQuery,
                order_by: orderBy,
                order_dir: orderDir,
                filters: combinedFilters,
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
    }, [databaseUuid, tableName, currentPage, perPage, searchQuery, orderBy, orderDir, filters, columnFilters]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    // Focus input when editing starts (only on cell change, not value change)
    const editingCellKey = editingCell ? `${editingCell.rowIndex}-${editingCell.columnName}` : null;
    useEffect(() => {
        if (editingCell && editInputRef.current) {
            editInputRef.current.focus();
            editInputRef.current.select();
        }
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [editingCellKey]);

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

    // Close export menu on click outside
    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (exportMenuRef.current && !exportMenuRef.current.contains(e.target as Node)) {
                setShowExportMenu(false);
            }
        };
        if (showExportMenu) {
            document.addEventListener('mousedown', handleClickOutside);
            return () => document.removeEventListener('mousedown', handleClickOutside);
        }
    }, [showExportMenu]);

    const handleSort = (columnName: string) => {
        if (orderBy === columnName) {
            if (orderDir === 'asc') {
                setOrderDir('desc');
            } else {
                // Third click - reset sorting
                setOrderBy('');
                setOrderDir('asc');
            }
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

    const handleRowSelect = (rowIndex: number, e: React.MouseEvent | React.ChangeEvent) => {
        const newSelected = new Set(selectedRows);
        const nativeEvent = e.nativeEvent as KeyboardEvent;

        if (nativeEvent.shiftKey && lastSelectedRow !== null) {
            // Shift+Click - Select range
            const start = Math.min(lastSelectedRow, rowIndex);
            const end = Math.max(lastSelectedRow, rowIndex);
            for (let i = start; i <= end; i++) {
                newSelected.add(i);
            }
        } else if (nativeEvent.metaKey || nativeEvent.ctrlKey) {
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
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken(),
                        },
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
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken(),
                        },
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

    // Export functions
    const handleExportCSV = (exportData: TableData[]) => {
        const headers = columns.map(c => c.name);
        const csvContent = toCSV(headers, exportData);
        downloadFile(csvContent, `${tableName}_export.csv`, 'text/csv;charset=utf-8');
        setShowExportMenu(false);
    };

    const handleExportJSON = (exportData: TableData[], pretty: boolean = true) => {
        const jsonContent = pretty
            ? JSON.stringify(exportData, null, 2)
            : JSON.stringify(exportData);

        downloadFile(jsonContent, `${tableName}_export.json`, 'application/json');
        setShowExportMenu(false);
    };

    const handleExport = (format: 'csv' | 'json', selection: 'selected' | 'all') => {
        const exportData = selection === 'selected' && selectedRows.size > 0
            ? Array.from(selectedRows).map(idx => data[idx])
            : data;

        if (format === 'csv') {
            handleExportCSV(exportData);
        } else {
            handleExportJSON(exportData);
        }
    };

    // Import functions
    const parseCSV = (text: string): { headers: string[]; rows: TableData[] } => {
        // Remove BOM if present (for files exported from Excel or our own export)
        const cleanText = stripBOM(text);
        const lines = cleanText.split('\n').filter(line => line.trim());
        if (lines.length === 0) return { headers: [], rows: [] };

        // Parse CSV with proper quote handling
        const parseLine = (line: string): string[] => {
            const result: string[] = [];
            let current = '';
            let inQuotes = false;

            for (let i = 0; i < line.length; i++) {
                const char = line[i];
                if (char === '"') {
                    if (inQuotes && line[i + 1] === '"') {
                        current += '"';
                        i++;
                    } else {
                        inQuotes = !inQuotes;
                    }
                } else if (char === ',' && !inQuotes) {
                    result.push(current.trim());
                    current = '';
                } else {
                    current += char;
                }
            }
            result.push(current.trim());
            return result;
        };

        const headers = parseLine(lines[0]);
        const rows = lines.slice(1).map(line => {
            const values = parseLine(line);
            const row: TableData = {};
            headers.forEach((header, idx) => {
                row[header] = values[idx] || null;
            });
            return row;
        });

        return { headers, rows };
    };

    const parseJSON = (text: string): { headers: string[]; rows: TableData[] } => {
        const parsed = JSON.parse(text);
        const rows = Array.isArray(parsed) ? parsed : [parsed];
        if (rows.length === 0) return { headers: [], rows: [] };

        // Get all unique keys from all rows
        const headers = Array.from(new Set(rows.flatMap(row => Object.keys(row))));
        return { headers, rows };
    };

    const handleFileSelect = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        setImportFile(file);
        setError(null);

        try {
            const text = await file.text();
            const isJSON = file.name.endsWith('.json') || text.trim().startsWith('[') || text.trim().startsWith('{');

            const { headers, rows } = isJSON ? parseJSON(text) : parseCSV(text);

            if (headers.length === 0 || rows.length === 0) {
                setError('No data found in file');
                return;
            }

            setImportColumns(headers);
            setImportData(rows);

            // Auto-map columns by matching names
            const mapping: Record<string, string> = {};
            headers.forEach(header => {
                const matchingColumn = columns.find(
                    c => c.name.toLowerCase() === header.toLowerCase()
                );
                if (matchingColumn) {
                    mapping[header] = matchingColumn.name;
                }
            });
            setColumnMapping(mapping);
            setImportStep('preview');
        } catch (err) {
            setError('Failed to parse file. Please check the format.');
        }
    };

    const resetImport = () => {
        setShowImportModal(false);
        setImportFile(null);
        setImportData([]);
        setImportColumns([]);
        setColumnMapping({});
        setImportStep('upload');
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const handleImport = async () => {
        if (importData.length === 0) return;

        setIsImporting(true);
        setError(null);

        try {
            // Transform data according to column mapping
            const mappedData = importData.map(row => {
                const mappedRow: TableData = {};
                Object.entries(columnMapping).forEach(([sourceCol, targetCol]) => {
                    if (targetCol) {
                        mappedRow[targetCol] = row[sourceCol];
                    }
                });
                return mappedRow;
            }).filter(row => Object.keys(row).length > 0);

            if (mappedData.length === 0) {
                setError('No valid data to import. Please map at least one column.');
                setIsImporting(false);
                return;
            }

            // Import rows one by one
            let successCount = 0;
            let errorCount = 0;

            for (const rowData of mappedData) {
                try {
                    const response = await fetch(
                        `/_internal/databases/${databaseUuid}/tables/${encodeURIComponent(tableName)}/rows`,
                        {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': getCsrfToken(),
                            },
                            body: JSON.stringify({ data: rowData }),
                        }
                    );

                    const result = await response.json();
                    if (result.success) {
                        successCount++;
                    } else {
                        errorCount++;
                    }
                } catch {
                    errorCount++;
                }
            }

            if (errorCount > 0) {
                setError(`Imported ${successCount} rows. Failed to import ${errorCount} rows.`);
            }

            resetImport();
            fetchData();
        } catch (err) {
            setError('Failed to import data');
        } finally {
            setIsImporting(false);
        }
    };

    const handleCreate = async () => {
        try {
            const response = await fetch(
                `/_internal/databases/${databaseUuid}/tables/${encodeURIComponent(tableName)}/rows`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    },
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
                            placeholder="Search all columns..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                            className="h-10 w-full rounded-lg border border-border bg-background pl-10 pr-16 text-sm text-foreground placeholder:text-foreground-subtle focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
                        />
                        <kbd className="absolute right-3 top-1/2 -translate-y-1/2 rounded border border-border bg-background-secondary px-1.5 py-0.5 text-xs text-foreground-muted">
                            Enter
                        </kbd>
                    </div>
                    <FilterBuilder
                        columns={columns}
                        filters={filters}
                        onFiltersChange={setFilters}
                        onApply={() => {
                            setCurrentPage(1);
                            fetchData();
                        }}
                    />
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
                        <Button size="sm" variant="warning" onClick={savePendingChanges} className="whitespace-nowrap gap-2">
                            <Icons.Save className="h-3.5 w-3.5" />
                            Save ({pendingChanges.size})
                            <kbd className="ml-1 flex items-center gap-0.5 rounded border border-amber-600/50 bg-amber-600/20 px-1 py-0.5 text-xs">
                                <Icons.Command className="h-2.5 w-2.5" />S
                            </kbd>
                        </Button>
                    )}
                    <Button size="sm" variant="secondary" onClick={fetchData}>
                        <Icons.RefreshCw className="mr-1.5 h-3.5 w-3.5" />
                        Refresh
                    </Button>
                    {/* Export Dropdown */}
                    <div className="relative" ref={exportMenuRef}>
                        <Button
                            size="sm"
                            variant="secondary"
                            onClick={() => setShowExportMenu(!showExportMenu)}
                        >
                            <Icons.Download className="mr-1.5 h-3.5 w-3.5" />
                            Export
                            <Icons.ChevronDown className="ml-1.5 h-3 w-3" />
                        </Button>
                        {showExportMenu && (
                            <div className="absolute right-0 top-full z-50 mt-1 min-w-[180px] rounded-lg border border-border bg-background shadow-lg">
                                <div className="p-1">
                                    <div className="px-3 py-1.5 text-xs font-medium text-foreground-muted">
                                        Export All ({data.length} rows)
                                    </div>
                                    <button
                                        onClick={() => handleExport('csv', 'all')}
                                        className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-foreground hover:bg-background-secondary"
                                    >
                                        <Icons.FileSpreadsheet className="h-4 w-4" />
                                        Export as CSV
                                    </button>
                                    <button
                                        onClick={() => handleExport('json', 'all')}
                                        className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-foreground hover:bg-background-secondary"
                                    >
                                        <Icons.FileJson className="h-4 w-4" />
                                        Export as JSON
                                    </button>
                                    {selectedRows.size > 0 && (
                                        <>
                                            <div className="my-1 border-t border-border" />
                                            <div className="px-3 py-1.5 text-xs font-medium text-foreground-muted">
                                                Export Selected ({selectedRows.size} rows)
                                            </div>
                                            <button
                                                onClick={() => handleExport('csv', 'selected')}
                                                className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-foreground hover:bg-background-secondary"
                                            >
                                                <Icons.FileSpreadsheet className="h-4 w-4" />
                                                Selected as CSV
                                            </button>
                                            <button
                                                onClick={() => handleExport('json', 'selected')}
                                                className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-foreground hover:bg-background-secondary"
                                            >
                                                <Icons.FileJson className="h-4 w-4" />
                                                Selected as JSON
                                            </button>
                                        </>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                    {/* Import Button */}
                    <Button
                        size="sm"
                        variant="secondary"
                        onClick={() => setShowImportModal(true)}
                    >
                        <Icons.Upload className="mr-1.5 h-3.5 w-3.5" />
                        Import
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
                                        {orderBy === col.name ? (
                                            <Icons.ChevronDown
                                                className={cn(
                                                    'h-3.5 w-3.5 text-primary',
                                                    orderDir === 'asc' && 'rotate-180'
                                                )}
                                            />
                                        ) : (
                                            <Icons.ChevronsUpDown className="h-3 w-3 text-foreground-subtle" />
                                        )}
                                    </button>
                                    <div className="mt-0.5 text-xs font-normal text-foreground-muted">
                                        {col.type}
                                    </div>
                                </th>
                            ))}
                        </tr>
                        {/* Inline column filters */}
                        <tr className="bg-background">
                            <th className="border-b border-border px-4 py-1.5">
                                {Object.values(columnFilters).some(v => v.trim() !== '') && (
                                    <button
                                        onClick={() => setColumnFilters({})}
                                        className="text-foreground-muted hover:text-danger"
                                        title="Clear all filters"
                                    >
                                        <Icons.X className="h-3.5 w-3.5" />
                                    </button>
                                )}
                            </th>
                            {columns.map((col) => (
                                <th key={`filter-${col.name}`} className="border-b border-border px-2 py-1.5">
                                    <input
                                        type="text"
                                        value={columnFilters[col.name] || ''}
                                        onChange={(e) => setColumnFilters({
                                            ...columnFilters,
                                            [col.name]: e.target.value
                                        })}
                                        onKeyDown={(e) => e.key === 'Enter' && fetchData()}
                                        placeholder="Filter..."
                                        className="h-7 w-full min-w-[80px] rounded border border-border bg-background px-2 text-xs text-foreground placeholder:text-foreground-subtle focus:border-primary focus:outline-none"
                                    />
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
                                            onChange={(e) => handleRowSelect(rowIdx, e)}
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

            {/* Import Modal */}
            {showImportModal && (
                <Modal
                    isOpen={true}
                    onClose={resetImport}
                    title={`Import Data to ${tableName}`}
                    size="xl"
                >
                    <div className="space-y-4">
                        {/* Hidden file input */}
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".csv,.json"
                            onChange={handleFileSelect}
                            className="hidden"
                        />

                        {importStep === 'upload' && (
                            <div
                                onClick={() => fileInputRef.current?.click()}
                                className="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-border p-12 transition-colors hover:border-primary hover:bg-background-secondary/50"
                            >
                                <Icons.Upload className="mb-4 h-12 w-12 text-foreground-muted" />
                                <p className="text-lg font-medium text-foreground">
                                    Click to select a file
                                </p>
                                <p className="mt-1 text-sm text-foreground-muted">
                                    Supported formats: CSV, JSON
                                </p>
                            </div>
                        )}

                        {importStep === 'preview' && (
                            <>
                                {/* File info */}
                                <div className="flex items-center justify-between rounded-lg bg-background-secondary p-3">
                                    <div className="flex items-center gap-3">
                                        <Icons.File className="h-8 w-8 text-foreground-muted" />
                                        <div>
                                            <p className="font-medium text-foreground">
                                                {importFile?.name}
                                            </p>
                                            <p className="text-sm text-foreground-muted">
                                                {importData.length} rows found
                                            </p>
                                        </div>
                                    </div>
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={() => {
                                            setImportStep('upload');
                                            setImportData([]);
                                            setImportColumns([]);
                                            setColumnMapping({});
                                            if (fileInputRef.current) {
                                                fileInputRef.current.value = '';
                                            }
                                        }}
                                    >
                                        <Icons.X className="mr-1.5 h-3.5 w-3.5" />
                                        Change File
                                    </Button>
                                </div>

                                {/* Column mapping */}
                                <div>
                                    <h4 className="mb-3 font-medium text-foreground">
                                        Column Mapping
                                    </h4>
                                    <p className="mb-3 text-sm text-foreground-muted">
                                        Map source columns to database columns. Unmapped columns will be skipped.
                                    </p>
                                    <div className="max-h-[200px] overflow-y-auto rounded-lg border border-border">
                                        <table className="w-full">
                                            <thead className="bg-background-secondary">
                                                <tr>
                                                    <th className="border-b border-border px-4 py-2 text-left text-xs font-semibold text-foreground">
                                                        Source Column
                                                    </th>
                                                    <th className="border-b border-border px-4 py-2 text-center text-xs font-semibold text-foreground">
                                                        →
                                                    </th>
                                                    <th className="border-b border-border px-4 py-2 text-left text-xs font-semibold text-foreground">
                                                        Target Column
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {importColumns.map((sourceCol) => (
                                                    <tr key={sourceCol} className="border-b border-border/50">
                                                        <td className="px-4 py-2 font-mono text-sm text-foreground">
                                                            {sourceCol}
                                                        </td>
                                                        <td className="px-4 py-2 text-center text-foreground-muted">
                                                            <Icons.ArrowRight className="inline h-4 w-4" />
                                                        </td>
                                                        <td className="px-4 py-2">
                                                            <select
                                                                value={columnMapping[sourceCol] || ''}
                                                                onChange={(e) =>
                                                                    setColumnMapping({
                                                                        ...columnMapping,
                                                                        [sourceCol]: e.target.value,
                                                                    })
                                                                }
                                                                className="w-full rounded-md border border-border bg-background px-3 py-1.5 text-sm text-foreground focus:border-primary focus:outline-none"
                                                            >
                                                                <option value="">-- Skip --</option>
                                                                {columns.map((col) => (
                                                                    <option key={col.name} value={col.name}>
                                                                        {col.name} ({col.type})
                                                                    </option>
                                                                ))}
                                                            </select>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                {/* Data preview */}
                                <div>
                                    <h4 className="mb-3 font-medium text-foreground">
                                        Data Preview (first 5 rows)
                                    </h4>
                                    <div className="max-h-[200px] overflow-auto rounded-lg border border-border">
                                        <table className="w-full">
                                            <thead className="bg-background-secondary">
                                                <tr>
                                                    {importColumns.map((col) => (
                                                        <th
                                                            key={col}
                                                            className="border-b border-border px-3 py-2 text-left text-xs font-semibold text-foreground"
                                                        >
                                                            {col}
                                                            {columnMapping[col] && (
                                                                <span className="ml-1 text-primary">
                                                                    → {columnMapping[col]}
                                                                </span>
                                                            )}
                                                        </th>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {importData.slice(0, 5).map((row, idx) => (
                                                    <tr key={idx} className="border-b border-border/50">
                                                        {importColumns.map((col) => (
                                                            <td
                                                                key={col}
                                                                className="px-3 py-2 font-mono text-xs text-foreground"
                                                            >
                                                                {row[col] || (
                                                                    <span className="italic text-foreground-subtle">
                                                                        null
                                                                    </span>
                                                                )}
                                                            </td>
                                                        ))}
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </>
                        )}

                        {/* Actions */}
                        <div className="flex justify-end gap-2 border-t border-border pt-4">
                            <Button variant="secondary" onClick={resetImport}>
                                Cancel
                            </Button>
                            {importStep === 'preview' && (
                                <Button
                                    onClick={handleImport}
                                    disabled={
                                        isImporting ||
                                        importData.length === 0 ||
                                        Object.values(columnMapping).filter(Boolean).length === 0
                                    }
                                >
                                    {isImporting ? (
                                        <>
                                            <Icons.RefreshCw className="mr-1.5 h-3.5 w-3.5 animate-spin" />
                                            Importing...
                                        </>
                                    ) : (
                                        <>
                                            <Icons.Upload className="mr-1.5 h-3.5 w-3.5" />
                                            Import {importData.length} Rows
                                        </>
                                    )}
                                </Button>
                            )}
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
}
