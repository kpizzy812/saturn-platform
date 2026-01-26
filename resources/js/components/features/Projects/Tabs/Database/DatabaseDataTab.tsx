import { Button } from '@/components/ui';
import { Plus, Table, Database } from 'lucide-react';
import type { SelectedService } from '../../types';

interface DatabaseDataTabProps {
    service: SelectedService;
}

export function DatabaseDataTab({ service: _service }: DatabaseDataTabProps) {
    const tables = [
        { name: 'users', rows: 1234, size: '2.4 MB' },
        { name: 'sessions', rows: 5678, size: '1.1 MB' },
        { name: 'applications', rows: 89, size: '156 KB' },
        { name: 'deployments', rows: 456, size: '892 KB' },
    ];

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-medium text-foreground">Tables</h3>
                <Button size="sm" variant="secondary">
                    <Plus className="mr-1 h-3 w-3" />
                    Create Table
                </Button>
            </div>

            <div className="space-y-2">
                {tables.map((table) => (
                    <div
                        key={table.name}
                        className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-3 transition-colors hover:bg-background-tertiary cursor-pointer"
                    >
                        <div className="flex items-center gap-3">
                            <Table className="h-4 w-4 text-foreground-muted" />
                            <div>
                                <p className="text-sm font-medium text-foreground">{table.name}</p>
                                <p className="text-xs text-foreground-muted">{table.rows.toLocaleString()} rows</p>
                            </div>
                        </div>
                        <span className="text-xs text-foreground-muted">{table.size}</span>
                    </div>
                ))}
            </div>

            <div className="rounded-lg border border-dashed border-border p-6 text-center">
                <Database className="mx-auto h-8 w-8 text-foreground-subtle" />
                <p className="mt-2 text-sm text-foreground-muted">
                    Click on a table to view and edit data
                </p>
            </div>
        </div>
    );
}
