import { useState } from 'react';
import { Button, Badge, Modal } from '@/components/ui';
import * as Icons from 'lucide-react';
import { cn } from '@/lib/utils';

export interface FilterCondition {
    id: string;
    column: string;
    operator: string;
    value: string;
    valueEnd?: string; // For BETWEEN operator
}

export interface FilterGroup {
    logic: 'AND' | 'OR';
    conditions: FilterCondition[];
}

interface Column {
    name: string;
    type: string;
    nullable: boolean;
}

interface FilterBuilderProps {
    columns: Column[];
    filters: FilterGroup;
    onFiltersChange: (filters: FilterGroup) => void;
    onApply: () => void;
}

// Operators based on column type
const OPERATORS = {
    text: [
        { value: '=', label: 'equals' },
        { value: '!=', label: 'not equals' },
        { value: 'LIKE', label: 'contains' },
        { value: 'NOT LIKE', label: 'not contains' },
        { value: 'STARTS', label: 'starts with' },
        { value: 'ENDS', label: 'ends with' },
        { value: 'IS NULL', label: 'is empty' },
        { value: 'IS NOT NULL', label: 'is not empty' },
    ],
    numeric: [
        { value: '=', label: 'equals' },
        { value: '!=', label: 'not equals' },
        { value: '>', label: 'greater than' },
        { value: '>=', label: 'greater or equal' },
        { value: '<', label: 'less than' },
        { value: '<=', label: 'less or equal' },
        { value: 'BETWEEN', label: 'between' },
        { value: 'IS NULL', label: 'is empty' },
        { value: 'IS NOT NULL', label: 'is not empty' },
    ],
    date: [
        { value: '=', label: 'equals' },
        { value: '!=', label: 'not equals' },
        { value: '>', label: 'after' },
        { value: '>=', label: 'on or after' },
        { value: '<', label: 'before' },
        { value: '<=', label: 'on or before' },
        { value: 'BETWEEN', label: 'between' },
        { value: 'IS NULL', label: 'is empty' },
        { value: 'IS NOT NULL', label: 'is not empty' },
    ],
    boolean: [
        { value: '=', label: 'equals' },
        { value: 'IS NULL', label: 'is empty' },
        { value: 'IS NOT NULL', label: 'is not empty' },
    ],
};

// Determine column category
function getColumnCategory(type: string): keyof typeof OPERATORS {
    const lowerType = type.toLowerCase();

    if (lowerType.includes('int') || lowerType.includes('decimal') ||
        lowerType.includes('float') || lowerType.includes('double') ||
        lowerType.includes('numeric') || lowerType.includes('real') ||
        lowerType.includes('serial') || lowerType.includes('money')) {
        return 'numeric';
    }

    if (lowerType.includes('date') || lowerType.includes('time') ||
        lowerType.includes('timestamp')) {
        return 'date';
    }

    if (lowerType.includes('bool')) {
        return 'boolean';
    }

    return 'text';
}

// Does operator need a value input?
function needsValue(operator: string): boolean {
    return !['IS NULL', 'IS NOT NULL'].includes(operator);
}

// Does operator need two values?
function needsTwoValues(operator: string): boolean {
    return operator === 'BETWEEN';
}

export function FilterBuilder({ columns, filters, onFiltersChange, onApply }: FilterBuilderProps) {
    const [isOpen, setIsOpen] = useState(false);

    const generateId = () => Math.random().toString(36).substring(7);

    const addCondition = () => {
        const firstColumn = columns[0];
        const newCondition: FilterCondition = {
            id: generateId(),
            column: firstColumn?.name || '',
            operator: '=',
            value: '',
        };

        onFiltersChange({
            ...filters,
            conditions: [...filters.conditions, newCondition],
        });
    };

    const updateCondition = (id: string, updates: Partial<FilterCondition>) => {
        onFiltersChange({
            ...filters,
            conditions: filters.conditions.map(c =>
                c.id === id ? { ...c, ...updates } : c
            ),
        });
    };

    const removeCondition = (id: string) => {
        onFiltersChange({
            ...filters,
            conditions: filters.conditions.filter(c => c.id !== id),
        });
    };

    const toggleLogic = () => {
        onFiltersChange({
            ...filters,
            logic: filters.logic === 'AND' ? 'OR' : 'AND',
        });
    };

    const clearAll = () => {
        onFiltersChange({
            logic: 'AND',
            conditions: [],
        });
    };

    const handleApply = () => {
        onApply();
        setIsOpen(false);
    };

    const activeFiltersCount = filters.conditions.length;

    return (
        <>
            {/* Filter Button */}
            <Button
                size="sm"
                variant={activeFiltersCount > 0 ? 'default' : 'secondary'}
                onClick={() => setIsOpen(true)}
            >
                <Icons.Filter className="mr-1.5 h-3.5 w-3.5" />
                Filter
                {activeFiltersCount > 0 && (
                    <Badge variant="secondary" className="ml-2 h-5 min-w-[20px] px-1.5">
                        {activeFiltersCount}
                    </Badge>
                )}
            </Button>

            {/* Active Filters Preview */}
            {activeFiltersCount > 0 && (
                <div className="flex flex-wrap items-center gap-2">
                    {filters.conditions.slice(0, 3).map((condition, _idx) => (
                        <Badge
                            key={condition.id}
                            variant="secondary"
                            className="flex items-center gap-1.5 pl-2"
                        >
                            <span className="font-mono text-xs">{condition.column}</span>
                            <span className="text-foreground-muted">{condition.operator}</span>
                            {needsValue(condition.operator) && (
                                <span className="font-medium">{condition.value || '""'}</span>
                            )}
                            <button
                                onClick={() => removeCondition(condition.id)}
                                className="ml-1 rounded-full p-0.5 hover:bg-background-tertiary"
                            >
                                <Icons.X className="h-3 w-3" />
                            </button>
                        </Badge>
                    ))}
                    {filters.conditions.length > 3 && (
                        <Badge variant="secondary">
                            +{filters.conditions.length - 3} more
                        </Badge>
                    )}
                    <button
                        onClick={clearAll}
                        className="text-xs text-foreground-muted hover:text-foreground"
                    >
                        Clear all
                    </button>
                </div>
            )}

            {/* Filter Modal */}
            <Modal isOpen={isOpen} onClose={() => setIsOpen(false)} title="Filter Data" size="lg">
                <div className="space-y-4">
                    {/* Logic Toggle */}
                    {filters.conditions.length > 1 && (
                        <div className="flex items-center gap-2 text-sm">
                            <span className="text-foreground-muted">Match</span>
                            <button
                                onClick={toggleLogic}
                                className={cn(
                                    'rounded-md px-3 py-1 font-medium transition-colors',
                                    filters.logic === 'AND'
                                        ? 'bg-primary text-white'
                                        : 'bg-background-tertiary text-foreground'
                                )}
                            >
                                ALL
                            </button>
                            <button
                                onClick={toggleLogic}
                                className={cn(
                                    'rounded-md px-3 py-1 font-medium transition-colors',
                                    filters.logic === 'OR'
                                        ? 'bg-primary text-white'
                                        : 'bg-background-tertiary text-foreground'
                                )}
                            >
                                ANY
                            </button>
                            <span className="text-foreground-muted">of the following conditions</span>
                        </div>
                    )}

                    {/* Conditions List */}
                    <div className="space-y-3">
                        {filters.conditions.length === 0 ? (
                            <div className="rounded-lg border border-dashed border-border p-6 text-center">
                                <Icons.Filter className="mx-auto mb-2 h-8 w-8 text-foreground-subtle" />
                                <p className="text-sm text-foreground-muted">No filters applied</p>
                                <p className="text-xs text-foreground-subtle">
                                    Click &quot;Add Condition&quot; to filter your data
                                </p>
                            </div>
                        ) : (
                            filters.conditions.map((condition, idx) => {
                                const column = columns.find(c => c.name === condition.column);
                                const category = column ? getColumnCategory(column.type) : 'text';
                                const operators = OPERATORS[category];

                                return (
                                    <div
                                        key={condition.id}
                                        className="flex items-start gap-2 rounded-lg border border-border bg-background-secondary p-3"
                                    >
                                        {/* Row indicator */}
                                        {idx > 0 && (
                                            <div className="flex h-9 w-12 items-center justify-center">
                                                <span className="text-xs font-medium text-foreground-muted">
                                                    {filters.logic}
                                                </span>
                                            </div>
                                        )}

                                        {/* Column Select */}
                                        <select
                                            value={condition.column}
                                            onChange={(e) => updateCondition(condition.id, {
                                                column: e.target.value,
                                                operator: '=',
                                                value: '',
                                            })}
                                            className="h-9 rounded-md border border-border bg-background px-3 text-sm text-foreground focus:border-primary focus:outline-none"
                                        >
                                            {columns.map(col => (
                                                <option key={col.name} value={col.name}>
                                                    {col.name}
                                                </option>
                                            ))}
                                        </select>

                                        {/* Operator Select */}
                                        <select
                                            value={condition.operator}
                                            onChange={(e) => updateCondition(condition.id, {
                                                operator: e.target.value,
                                                valueEnd: undefined,
                                            })}
                                            className="h-9 rounded-md border border-border bg-background px-3 text-sm text-foreground focus:border-primary focus:outline-none"
                                        >
                                            {operators.map(op => (
                                                <option key={op.value} value={op.value}>
                                                    {op.label}
                                                </option>
                                            ))}
                                        </select>

                                        {/* Value Input */}
                                        {needsValue(condition.operator) && (
                                            <div className="flex flex-1 items-center gap-2">
                                                {category === 'boolean' ? (
                                                    <select
                                                        value={condition.value}
                                                        onChange={(e) => updateCondition(condition.id, { value: e.target.value })}
                                                        className="h-9 flex-1 rounded-md border border-border bg-background px-3 text-sm text-foreground focus:border-primary focus:outline-none"
                                                    >
                                                        <option value="">Select...</option>
                                                        <option value="true">true</option>
                                                        <option value="false">false</option>
                                                    </select>
                                                ) : category === 'date' ? (
                                                    <>
                                                        <input
                                                            type="date"
                                                            value={condition.value}
                                                            onChange={(e) => updateCondition(condition.id, { value: e.target.value })}
                                                            className="h-9 flex-1 rounded-md border border-border bg-background px-3 text-sm text-foreground focus:border-primary focus:outline-none"
                                                        />
                                                        {needsTwoValues(condition.operator) && (
                                                            <>
                                                                <span className="text-foreground-muted">and</span>
                                                                <input
                                                                    type="date"
                                                                    value={condition.valueEnd || ''}
                                                                    onChange={(e) => updateCondition(condition.id, { valueEnd: e.target.value })}
                                                                    className="h-9 flex-1 rounded-md border border-border bg-background px-3 text-sm text-foreground focus:border-primary focus:outline-none"
                                                                />
                                                            </>
                                                        )}
                                                    </>
                                                ) : (
                                                    <>
                                                        <input
                                                            type={category === 'numeric' ? 'number' : 'text'}
                                                            value={condition.value}
                                                            onChange={(e) => updateCondition(condition.id, { value: e.target.value })}
                                                            placeholder="Enter value..."
                                                            className="h-9 flex-1 rounded-md border border-border bg-background px-3 text-sm text-foreground focus:border-primary focus:outline-none"
                                                        />
                                                        {needsTwoValues(condition.operator) && (
                                                            <>
                                                                <span className="text-foreground-muted">and</span>
                                                                <input
                                                                    type={category === 'numeric' ? 'number' : 'text'}
                                                                    value={condition.valueEnd || ''}
                                                                    onChange={(e) => updateCondition(condition.id, { valueEnd: e.target.value })}
                                                                    placeholder="Enter value..."
                                                                    className="h-9 flex-1 rounded-md border border-border bg-background px-3 text-sm text-foreground focus:border-primary focus:outline-none"
                                                                />
                                                            </>
                                                        )}
                                                    </>
                                                )}
                                            </div>
                                        )}

                                        {/* Remove Button */}
                                        <button
                                            onClick={() => removeCondition(condition.id)}
                                            className="flex h-9 w-9 items-center justify-center rounded-md text-foreground-muted hover:bg-background-tertiary hover:text-danger"
                                        >
                                            <Icons.Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                );
                            })
                        )}
                    </div>

                    {/* Add Condition Button */}
                    <button
                        onClick={addCondition}
                        className="flex w-full items-center justify-center gap-2 rounded-lg border border-dashed border-border py-3 text-sm text-foreground-muted hover:border-primary hover:text-primary"
                    >
                        <Icons.Plus className="h-4 w-4" />
                        Add Condition
                    </button>

                    {/* Actions */}
                    <div className="flex items-center justify-between border-t border-border pt-4">
                        <Button variant="secondary" onClick={clearAll} disabled={filters.conditions.length === 0}>
                            <Icons.X className="mr-1.5 h-3.5 w-3.5" />
                            Clear All
                        </Button>
                        <div className="flex gap-2">
                            <Button variant="secondary" onClick={() => setIsOpen(false)}>
                                Cancel
                            </Button>
                            <Button onClick={handleApply}>
                                <Icons.Check className="mr-1.5 h-3.5 w-3.5" />
                                Apply Filters
                            </Button>
                        </div>
                    </div>
                </div>
            </Modal>
        </>
    );
}

// Helper function to build SQL WHERE clause from filters
export function buildWhereClause(filters: FilterGroup): string {
    if (filters.conditions.length === 0) return '';

    const clauses = filters.conditions.map(c => {
        const column = `"${c.column}"`;

        switch (c.operator) {
            case 'IS NULL':
                return `${column} IS NULL`;
            case 'IS NOT NULL':
                return `${column} IS NOT NULL`;
            case 'LIKE':
                return `${column} ILIKE '%${escapeSql(c.value)}%'`;
            case 'NOT LIKE':
                return `${column} NOT ILIKE '%${escapeSql(c.value)}%'`;
            case 'STARTS':
                return `${column} ILIKE '${escapeSql(c.value)}%'`;
            case 'ENDS':
                return `${column} ILIKE '%${escapeSql(c.value)}'`;
            case 'BETWEEN':
                return `${column} BETWEEN '${escapeSql(c.value)}' AND '${escapeSql(c.valueEnd || '')}'`;
            default:
                return `${column} ${c.operator} '${escapeSql(c.value)}'`;
        }
    });

    return clauses.join(` ${filters.logic} `);
}

function escapeSql(value: string): string {
    return value.replace(/'/g, "''");
}
