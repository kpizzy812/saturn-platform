import * as React from 'react';
import { cn } from '@/lib/utils';
import { Input } from './Input';
import { Select } from './Select';
import { Button } from './Button';
import { Spinner } from './Spinner';
import { ChevronUp, ChevronDown, ChevronsUpDown, ChevronLeft, ChevronRight, Search } from 'lucide-react';

// ── Types ──────────────────────────────────────────────────────

export interface DataTableColumn<T> {
    key: string;
    header: string;
    render: (item: T, index: number) => React.ReactNode;
    sortable?: boolean;
    className?: string;
    headerClassName?: string;
}

export type SortDirection = 'asc' | 'desc';

interface DataTableContextValue<T = unknown> {
    data: T[];
    columns: DataTableColumn<T>[];
    loading?: boolean;
    sortKey?: string;
    sortDirection?: SortDirection;
    onSort?: (key: string, direction: SortDirection) => void;
}

const DataTableContext = React.createContext<DataTableContextValue | null>(null);

function useDataTable<T = unknown>() {
    const ctx = React.useContext(DataTableContext) as DataTableContextValue<T> | null;
    if (!ctx) throw new Error('DataTable compound components must be used within <DataTable>');
    return ctx;
}

// ── Root ───────────────────────────────────────────────────────

interface DataTableProps<T> {
    data: T[];
    columns: DataTableColumn<T>[];
    loading?: boolean;
    sortKey?: string;
    sortDirection?: SortDirection;
    onSort?: (key: string, direction: SortDirection) => void;
    children: React.ReactNode;
    className?: string;
}

export function DataTable<T>({
    data,
    columns,
    loading,
    sortKey,
    sortDirection,
    onSort,
    children,
    className,
}: DataTableProps<T>) {
    const value: DataTableContextValue<T> = React.useMemo(
        () => ({ data, columns, loading, sortKey, sortDirection, onSort }),
        [data, columns, loading, sortKey, sortDirection, onSort],
    );

    return (
        <DataTableContext.Provider value={value as DataTableContextValue}>
            <div className={cn('space-y-4', className)}>
                {children}
            </div>
        </DataTableContext.Provider>
    );
}

// ── Filters Bar ────────────────────────────────────────────────

interface DataTableFiltersProps {
    children: React.ReactNode;
    className?: string;
}

export function DataTableFilters({ children, className }: DataTableFiltersProps) {
    return (
        <div className={cn(
            'flex flex-wrap items-center gap-3',
            className,
        )}>
            {children}
        </div>
    );
}

// ── Search ─────────────────────────────────────────────────────

interface DataTableSearchProps {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    debounceMs?: number;
    className?: string;
}

export function DataTableSearch({
    value,
    onChange,
    placeholder = 'Search...',
    debounceMs = 300,
    className,
}: DataTableSearchProps) {
    const [localValue, setLocalValue] = React.useState(value);
    const timerRef = React.useRef<ReturnType<typeof setTimeout>>();

    // Sync external value changes
    React.useEffect(() => {
        setLocalValue(value);
    }, [value]);

    const handleChange = React.useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
        const newValue = e.target.value;
        setLocalValue(newValue);

        if (timerRef.current) clearTimeout(timerRef.current);
        timerRef.current = setTimeout(() => {
            onChange(newValue);
        }, debounceMs);
    }, [onChange, debounceMs]);

    React.useEffect(() => {
        return () => {
            if (timerRef.current) clearTimeout(timerRef.current);
        };
    }, []);

    return (
        <div className={cn('w-full sm:max-w-xs', className)}>
            <Input
                value={localValue}
                onChange={handleChange}
                placeholder={placeholder}
                icon={<Search className="h-4 w-4" />}
                iconPosition="left"
            />
        </div>
    );
}

// ── Filter Select ──────────────────────────────────────────────

interface DataTableFilterProps {
    label?: string;
    value: string;
    onChange: (value: string) => void;
    options: { value: string; label: string }[];
    className?: string;
}

export function DataTableFilter({
    label,
    value,
    onChange,
    options,
    className,
}: DataTableFilterProps) {
    return (
        <div className={cn('w-full sm:w-auto sm:min-w-[160px]', className)}>
            <Select
                label={label}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                options={options}
            />
        </div>
    );
}

// ── Content (Table) ────────────────────────────────────────────

interface DataTableContentProps {
    emptyIcon?: React.ReactNode;
    emptyTitle?: string;
    emptyDescription?: string;
    className?: string;
    skeletonRows?: number;
}

export function DataTableContent({
    emptyIcon,
    emptyTitle = 'No results found',
    emptyDescription = 'Try adjusting your search or filters.',
    className,
    skeletonRows = 5,
}: DataTableContentProps) {
    const { data, columns, loading, sortKey, sortDirection, onSort } = useDataTable();

    if (loading) {
        return (
            <div className={cn(
                'w-full overflow-hidden rounded-xl border border-border/50',
                'bg-gradient-to-br from-background-secondary to-background-secondary/50',
                className,
            )}>
                {/* Skeleton header */}
                <div className="border-b border-border/50 bg-background-secondary/80 px-4 py-3">
                    <div className="flex gap-4">
                        {columns.map((col) => (
                            <div key={col.key} className={cn('flex-1', col.headerClassName)}>
                                <div className="h-4 w-20 animate-pulse rounded bg-background-tertiary/50" />
                            </div>
                        ))}
                    </div>
                </div>
                {/* Skeleton rows */}
                {Array.from({ length: skeletonRows }).map((_, i) => (
                    <div key={i} className="border-b border-border/30 px-4 py-3 last:border-0">
                        <div className="flex gap-4">
                            {columns.map((col) => (
                                <div key={col.key} className={cn('flex-1', col.className)}>
                                    <div className="h-4 w-full max-w-[200px] animate-pulse rounded bg-background-tertiary/40" />
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        );
    }

    if (data.length === 0) {
        return (
            <div className={cn(
                'flex flex-col items-center justify-center rounded-xl border border-border/50',
                'bg-gradient-to-br from-background-secondary/30 to-transparent py-16',
                className,
            )}>
                {emptyIcon && (
                    <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary/50">
                        {emptyIcon}
                    </div>
                )}
                <h3 className="mt-4 text-lg font-medium text-foreground">{emptyTitle}</h3>
                <p className="mt-1 text-sm text-foreground-muted">{emptyDescription}</p>
            </div>
        );
    }

    const handleSort = (key: string) => {
        if (!onSort) return;
        const newDirection: SortDirection =
            sortKey === key && sortDirection === 'asc' ? 'desc' : 'asc';
        onSort(key, newDirection);
    };

    return (
        <div className={cn(
            'w-full overflow-x-auto rounded-xl border border-border/50',
            'bg-gradient-to-br from-background-secondary to-background-secondary/50',
            className,
        )}>
            <table className="w-full">
                <thead>
                    <tr className="border-b border-border/50 bg-background-secondary/80">
                        {columns.map((col) => (
                            <th
                                key={col.key}
                                className={cn(
                                    'px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-foreground-muted',
                                    col.sortable && 'cursor-pointer select-none hover:text-foreground',
                                    col.headerClassName,
                                )}
                                onClick={col.sortable ? () => handleSort(col.key) : undefined}
                            >
                                <span className="inline-flex items-center gap-1.5">
                                    {col.header}
                                    {col.sortable && (
                                        <SortIcon
                                            active={sortKey === col.key}
                                            direction={sortKey === col.key ? sortDirection : undefined}
                                        />
                                    )}
                                </span>
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {data.map((item, index) => (
                        <tr
                            key={index}
                            className="border-b border-border/30 transition-colors duration-150 last:border-0 hover:bg-white/[0.02]"
                        >
                            {columns.map((col) => (
                                <td
                                    key={col.key}
                                    className={cn(
                                        'px-4 py-3 text-sm text-foreground',
                                        col.className,
                                    )}
                                >
                                    {col.render(item as never, index)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

// ── Sort Icon ──────────────────────────────────────────────────

function SortIcon({ active, direction }: { active: boolean; direction?: SortDirection }) {
    if (!active) {
        return <ChevronsUpDown className="h-3.5 w-3.5 text-foreground-subtle" />;
    }
    return direction === 'asc'
        ? <ChevronUp className="h-3.5 w-3.5 text-primary" />
        : <ChevronDown className="h-3.5 w-3.5 text-primary" />;
}

// ── Pagination ─────────────────────────────────────────────────

interface DataTablePaginationProps {
    currentPage: number;
    totalPages: number;
    onPageChange: (page: number) => void;
    totalItems?: number;
    pageSize?: number;
    className?: string;
}

export function DataTablePagination({
    currentPage,
    totalPages,
    onPageChange,
    totalItems,
    pageSize,
    className,
}: DataTablePaginationProps) {
    if (totalPages <= 1) return null;

    const startItem = pageSize ? (currentPage - 1) * pageSize + 1 : undefined;
    const endItem = pageSize && totalItems ? Math.min(currentPage * pageSize, totalItems) : undefined;

    return (
        <div className={cn(
            'flex flex-wrap items-center justify-between gap-4',
            className,
        )}>
            <div className="text-sm text-foreground-muted">
                {totalItems != null && startItem != null && endItem != null ? (
                    <span>{startItem}–{endItem} of {totalItems}</span>
                ) : (
                    <span>Page {currentPage} of {totalPages}</span>
                )}
            </div>
            <div className="flex items-center gap-2">
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onPageChange(currentPage - 1)}
                    disabled={currentPage <= 1}
                >
                    <ChevronLeft className="h-4 w-4" />
                    <span className="hidden sm:inline">Previous</span>
                </Button>

                {/* Page number buttons */}
                <PageNumbers
                    currentPage={currentPage}
                    totalPages={totalPages}
                    onPageChange={onPageChange}
                />

                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onPageChange(currentPage + 1)}
                    disabled={currentPage >= totalPages}
                >
                    <span className="hidden sm:inline">Next</span>
                    <ChevronRight className="h-4 w-4" />
                </Button>
            </div>
        </div>
    );
}

// ── Page Numbers ───────────────────────────────────────────────

function PageNumbers({
    currentPage,
    totalPages,
    onPageChange,
}: {
    currentPage: number;
    totalPages: number;
    onPageChange: (page: number) => void;
}) {
    const pages = React.useMemo(() => {
        const items: (number | 'ellipsis')[] = [];
        const maxVisible = 5;

        if (totalPages <= maxVisible) {
            for (let i = 1; i <= totalPages; i++) items.push(i);
        } else {
            items.push(1);

            if (currentPage > 3) items.push('ellipsis');

            const start = Math.max(2, currentPage - 1);
            const end = Math.min(totalPages - 1, currentPage + 1);
            for (let i = start; i <= end; i++) items.push(i);

            if (currentPage < totalPages - 2) items.push('ellipsis');

            items.push(totalPages);
        }

        return items;
    }, [currentPage, totalPages]);

    return (
        <div className="hidden items-center gap-1 sm:flex">
            {pages.map((page, i) =>
                page === 'ellipsis' ? (
                    <span key={`e-${i}`} className="px-1 text-foreground-subtle">...</span>
                ) : (
                    <button
                        key={page}
                        onClick={() => onPageChange(page)}
                        className={cn(
                            'inline-flex h-8 w-8 items-center justify-center rounded-md text-sm font-medium transition-colors',
                            page === currentPage
                                ? 'bg-primary text-white'
                                : 'text-foreground-muted hover:bg-white/[0.06] hover:text-foreground',
                        )}
                    >
                        {page}
                    </button>
                ),
            )}
        </div>
    );
}
