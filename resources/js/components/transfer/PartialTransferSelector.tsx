import { Loader2, Database, Table, Key, Folder } from 'lucide-react';
import type { DatabaseStructure, DatabaseType } from '@/types';

interface PartialTransferSelectorProps {
    databaseType: DatabaseType;
    structure: DatabaseStructure | null;
    isLoading: boolean;
    selectedItems: string[];
    onSelectionChange: (items: string[]) => void;
}

export function PartialTransferSelector({
    databaseType,
    structure,
    isLoading,
    selectedItems,
    onSelectionChange,
}: PartialTransferSelectorProps) {
    const isRedisLike = ['redis', 'keydb', 'dragonfly'].includes(databaseType);
    const isMongoDB = databaseType === 'mongodb';

    const getItemIcon = () => {
        if (isRedisLike) return Key;
        if (isMongoDB) return Folder;
        return Table;
    };

    const getItemLabel = () => {
        if (isRedisLike) return 'Key Patterns';
        if (isMongoDB) return 'Collections';
        return 'Tables';
    };

    const ItemIcon = getItemIcon();

    const toggleItem = (name: string) => {
        if (selectedItems.includes(name)) {
            onSelectionChange(selectedItems.filter((i) => i !== name));
        } else {
            onSelectionChange([...selectedItems, name]);
        }
    };

    const toggleAll = () => {
        if (!structure) return;
        if (selectedItems.length === structure.items.length) {
            onSelectionChange([]);
        } else {
            onSelectionChange(structure.items.map((i) => i.name));
        }
    };

    if (isLoading) {
        return (
            <div className="flex flex-col items-center justify-center py-8">
                <Loader2 className="h-6 w-6 animate-spin text-foreground-muted" />
                <p className="mt-2 text-sm text-foreground-muted">Loading database structure...</p>
            </div>
        );
    }

    if (!structure) {
        return (
            <div className="flex flex-col items-center justify-center py-8">
                <Database className="h-8 w-8 text-foreground-muted" />
                <p className="mt-2 text-sm text-foreground-muted">
                    Unable to load database structure
                </p>
            </div>
        );
    }

    if (structure.items.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center py-8">
                <Database className="h-8 w-8 text-foreground-muted" />
                <p className="mt-2 text-sm text-foreground-muted">
                    No {getItemLabel().toLowerCase()} found in this database
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <p className="text-sm text-foreground-muted">
                    Select {getItemLabel().toLowerCase()} to transfer
                </p>
                <button
                    onClick={toggleAll}
                    className="text-sm text-primary hover:text-primary/80"
                >
                    {selectedItems.length === structure.items.length ? 'Deselect All' : 'Select All'}
                </button>
            </div>

            <div className="max-h-64 overflow-y-auto rounded-lg border border-border">
                {structure.items.map((item) => {
                    const isSelected = selectedItems.includes(item.name);
                    return (
                        <button
                            key={item.name}
                            onClick={() => toggleItem(item.name)}
                            className={`flex w-full items-center gap-3 border-b border-border px-4 py-3 text-left last:border-b-0 transition-colors ${
                                isSelected ? 'bg-primary/5' : 'hover:bg-background-tertiary'
                            }`}
                        >
                            <div
                                className={`flex h-5 w-5 items-center justify-center rounded border ${
                                    isSelected
                                        ? 'border-primary bg-primary text-white'
                                        : 'border-foreground-muted'
                                }`}
                            >
                                {isSelected && (
                                    <svg
                                        className="h-3 w-3"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        strokeWidth={3}
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            d="M5 13l4 4L19 7"
                                        />
                                    </svg>
                                )}
                            </div>
                            <ItemIcon className="h-4 w-4 text-foreground-muted" />
                            <div className="flex-1">
                                <p className="font-medium text-foreground">{item.name}</p>
                                {item.row_count !== undefined && (
                                    <p className="text-xs text-foreground-muted">
                                        {item.row_count.toLocaleString()} rows
                                    </p>
                                )}
                            </div>
                            {item.size && (
                                <span className="text-sm text-foreground-muted">{item.size}</span>
                            )}
                        </button>
                    );
                })}
            </div>

            <div className="flex items-center justify-between text-sm text-foreground-muted">
                <span>
                    {selectedItems.length} of {structure.items.length} selected
                </span>
                {structure.total_size && <span>Total: {structure.total_size}</span>}
            </div>
        </div>
    );
}
