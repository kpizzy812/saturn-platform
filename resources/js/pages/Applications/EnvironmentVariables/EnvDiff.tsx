import * as React from 'react';
import { Modal, Button, Select } from '@/components/ui';
import { ArrowLeftRight, Plus, Minus, RefreshCw } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { Application } from '@/types';

interface DiffEntry {
    key: string;
    status: 'added' | 'removed' | 'changed' | 'unchanged';
    source_value: string | null;
    target_value: string | null;
}

interface DiffResult {
    source: { uuid: string; name: string };
    target: { uuid: string; name: string };
    diff: DiffEntry[];
}

interface AvailableApp {
    uuid: string;
    name: string;
}

export interface EnvDiffProps {
    isOpen: boolean;
    onClose: () => void;
    application: Application;
}

export function EnvDiff({ isOpen, onClose, application }: EnvDiffProps) {
    const [compareUuid, setCompareUuid] = React.useState('');
    const [availableApps, setAvailableApps] = React.useState<AvailableApp[]>([]);
    const [isLoadingApps, setIsLoadingApps] = React.useState(false);
    const [diffResult, setDiffResult] = React.useState<DiffResult | null>(null);
    const [isLoading, setIsLoading] = React.useState(false);
    const [error, setError] = React.useState<string | null>(null);
    const [filter, setFilter] = React.useState<'all' | 'changes'>('changes');

    // Fetch available applications when modal opens
    React.useEffect(() => {
        if (!isOpen) return;
        setIsLoadingApps(true);
        fetch('/api/v1/applications', {
            headers: { Accept: 'application/json' },
            credentials: 'include',
        })
            .then((r) => r.json())
            .then((data: unknown) => {
                const list = Array.isArray(data) ? data : ((data as { data?: unknown[] }).data ?? []);
                const apps = (list as AvailableApp[]).filter((a) => a.uuid !== application.uuid);
                setAvailableApps(apps);
            })
            .catch(() => setAvailableApps([]))
            .finally(() => setIsLoadingApps(false));
    }, [isOpen, application.uuid]);

    const handleCompare = async () => {
        if (!compareUuid) return;
        setIsLoading(true);
        setError(null);
        setDiffResult(null);
        try {
            const response = await fetch(
                `/web-api/applications/${application.uuid}/envs/diff?compare_uuid=${compareUuid}`,
                { headers: { Accept: 'application/json' } },
            );
            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error((data as { message?: string }).message || 'Failed to load diff');
            }
            const data = (await response.json()) as DiffResult;
            setDiffResult(data);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to load diff');
        } finally {
            setIsLoading(false);
        }
    };

    const handleClose = () => {
        setCompareUuid('');
        setDiffResult(null);
        setError(null);
        setFilter('changes');
        onClose();
    };

    const filteredDiff = diffResult?.diff.filter((e) => filter === 'all' || e.status !== 'unchanged') ?? [];

    const stats = diffResult
        ? {
              added: diffResult.diff.filter((e) => e.status === 'added').length,
              removed: diffResult.diff.filter((e) => e.status === 'removed').length,
              changed: diffResult.diff.filter((e) => e.status === 'changed').length,
              unchanged: diffResult.diff.filter((e) => e.status === 'unchanged').length,
          }
        : null;

    return (
        <Modal isOpen={isOpen} onClose={handleClose} title="Compare Environments" size="2xl">
            <div className="space-y-4">
                {/* Comparison selector row */}
                <div className="flex items-center gap-3">
                    <div className="flex-1 min-w-0 rounded-lg border border-border bg-background/50 px-3 py-2.5">
                        <p className="text-xs text-foreground-muted">Source</p>
                        <p className="truncate font-medium text-foreground">{application.name}</p>
                    </div>
                    <ArrowLeftRight className="h-5 w-5 shrink-0 text-foreground-muted" />
                    <div className="flex-1 min-w-0">
                        <Select
                            value={compareUuid}
                            onChange={(e) => {
                                setCompareUuid(e.target.value);
                                setDiffResult(null);
                                setError(null);
                            }}
                            disabled={isLoadingApps}
                            options={[
                                {
                                    value: '',
                                    label: isLoadingApps ? 'Loading applications...' : 'Select application to compare...',
                                },
                                ...availableApps.map((app) => ({ value: app.uuid, label: app.name })),
                            ]}
                        />
                    </div>
                    <Button onClick={handleCompare} disabled={!compareUuid || isLoading} loading={isLoading}>
                        <RefreshCw className={cn('mr-2 h-4 w-4', isLoading && 'animate-spin')} />
                        Compare
                    </Button>
                </div>

                {error && (
                    <div className="rounded-lg border border-danger/50 bg-danger/5 p-3 text-sm text-danger">
                        {error}
                    </div>
                )}

                {/* Stats + filter toggle */}
                {stats && (
                    <div className="flex items-center gap-4">
                        <div className="flex gap-3 text-xs">
                            {stats.added > 0 && (
                                <span className="flex items-center gap-1 text-success">
                                    <Plus className="h-3 w-3" />
                                    {stats.added} added
                                </span>
                            )}
                            {stats.removed > 0 && (
                                <span className="flex items-center gap-1 text-danger">
                                    <Minus className="h-3 w-3" />
                                    {stats.removed} removed
                                </span>
                            )}
                            {stats.changed > 0 && (
                                <span className="text-warning">{stats.changed} changed</span>
                            )}
                            <span className="text-foreground-muted">{stats.unchanged} unchanged</span>
                        </div>
                        <div className="ml-auto flex overflow-hidden rounded-lg border border-border text-xs">
                            <button
                                type="button"
                                onClick={() => setFilter('changes')}
                                className={cn(
                                    'px-3 py-1 transition-colors',
                                    filter === 'changes'
                                        ? 'bg-primary/10 text-primary'
                                        : 'text-foreground-muted hover:bg-white/5',
                                )}
                            >
                                Changes only
                            </button>
                            <button
                                type="button"
                                onClick={() => setFilter('all')}
                                className={cn(
                                    'border-l border-border px-3 py-1 transition-colors',
                                    filter === 'all'
                                        ? 'bg-primary/10 text-primary'
                                        : 'text-foreground-muted hover:bg-white/5',
                                )}
                            >
                                All
                            </button>
                        </div>
                    </div>
                )}

                {/* Diff table */}
                {diffResult && (
                    <div className="max-h-[420px] overflow-y-auto rounded-lg border border-border">
                        {filteredDiff.length === 0 ? (
                            <div className="flex items-center justify-center py-10 text-sm text-foreground-muted">
                                {filter === 'changes'
                                    ? 'No differences found. Both environments have identical variables.'
                                    : 'No variables found in either environment.'}
                            </div>
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="sticky top-0 border-b border-border bg-background/95 backdrop-blur-sm">
                                    <tr>
                                        <th className="w-6 px-2 py-2" />
                                        <th className="px-3 py-2 text-left text-xs font-medium text-foreground-muted">
                                            Key
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-foreground-muted">
                                            {diffResult.source.name}
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-foreground-muted">
                                            {diffResult.target.name}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredDiff.map((entry) => (
                                        <DiffRow key={entry.key} entry={entry} />
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                )}
            </div>
        </Modal>
    );
}

function DiffRow({ entry }: { entry: DiffEntry }) {
    const rowClass = {
        added: 'bg-success/5',
        removed: 'bg-danger/5',
        changed: 'bg-warning/5',
        unchanged: '',
    }[entry.status];

    return (
        <tr className={cn('border-b border-border/40', rowClass)}>
            <td className="w-6 px-2 py-2 text-center">
                {entry.status === 'added' && <Plus className="h-3 w-3 text-success" />}
                {entry.status === 'removed' && <Minus className="h-3 w-3 text-danger" />}
                {entry.status === 'changed' && (
                    <span className="text-xs font-bold text-warning">~</span>
                )}
            </td>
            <td className="px-3 py-2 font-mono font-medium text-foreground">{entry.key}</td>
            <td
                className={cn(
                    'px-3 py-2 font-mono text-sm',
                    entry.status === 'removed' && 'text-danger',
                    entry.status === 'changed' && 'text-foreground-muted line-through',
                    entry.status === 'unchanged' && 'text-foreground-muted',
                )}
            >
                <MaskedValue value={entry.source_value} />
            </td>
            <td
                className={cn(
                    'px-3 py-2 font-mono text-sm',
                    entry.status === 'added' && 'text-success',
                    entry.status === 'changed' && 'text-warning',
                    entry.status === 'unchanged' && 'text-foreground-muted',
                )}
            >
                <MaskedValue value={entry.target_value} />
            </td>
        </tr>
    );
}

function MaskedValue({ value }: { value: string | null }) {
    if (value === null) return <span className="italic text-foreground-muted">—</span>;
    if (value === '') return <span className="italic text-foreground-muted">(empty)</span>;
    return <>{'•'.repeat(Math.min(value.length, 16))}</>;
}
