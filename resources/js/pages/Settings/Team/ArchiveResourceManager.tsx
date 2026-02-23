import { useState, useMemo, useCallback } from 'react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Select } from '@/components/ui/Select';
import { useConfirmation } from '@/components/ui/ConfirmationModal';
import { useToast } from '@/components/ui/Toast';
import { Link, router } from '@inertiajs/react';
import {
    ExternalLink,
    Trash2,
    ArrowRightLeft,
    Search,
    Package,
    ArrowUpDown,
    Server,
    ChevronDown,
} from 'lucide-react';

export interface FullResource {
    id: number;
    uuid: string;
    type: string;
    full_type: string;
    name: string;
    status: string | null;
    project_name: string | null;
    project_id: number | null;
    environment_name: string | null;
    environment_id: number | null;
    server_name: string | null;
    action_count: number;
    url: string | null;
    interaction: 'created' | 'modified';
}

export interface EnvironmentOption {
    id: number;
    name: string;
    project_name: string;
    project_id: number | null;
}

interface Props {
    archiveId: number;
    resources: FullResource[];
    environments: EnvironmentOption[];
}

const STATUS_BADGE_MAP: Record<string, 'success' | 'default' | 'warning' | 'info'> = {
    running: 'success',
    stopped: 'default',
    exited: 'default',
    restarting: 'warning',
    building: 'info',
};

type SortField = 'name' | 'type' | 'status' | 'project' | 'actions';
type SortDir = 'asc' | 'desc';

const PAGE_SIZE = 20;

export default function ArchiveResourceManager({ archiveId, resources, environments }: Props) {
    const { addToast } = useToast();

    // Filters
    const [typeFilter, setTypeFilter] = useState('all');
    const [statusFilter, setStatusFilter] = useState('all');
    const [projectFilter, setProjectFilter] = useState('all');
    const [searchQuery, setSearchQuery] = useState('');

    // Sorting
    const [sortField, setSortField] = useState<SortField>('actions');
    const [sortDir, setSortDir] = useState<SortDir>('desc');

    // Pagination
    const [visibleCount, setVisibleCount] = useState(PAGE_SIZE);

    // Selection
    const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());

    // Bulk actions
    const [targetEnvironmentId, setTargetEnvironmentId] = useState('');
    const [isMoving, setIsMoving] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    // Unique filter options
    const typeOptions = useMemo(() => {
        const types = [...new Set(resources.map((r) => r.type))].sort();
        return types;
    }, [resources]);

    const statusOptions = useMemo(() => {
        const statuses = [...new Set(resources.map((r) => r.status).filter(Boolean))] as string[];
        return statuses.sort();
    }, [resources]);

    const projectOptions = useMemo(() => {
        const projects = new Map<number, string>();
        resources.forEach((r) => {
            if (r.project_id && r.project_name) {
                projects.set(r.project_id, r.project_name);
            }
        });
        return Array.from(projects.entries()).sort((a, b) => a[1].localeCompare(b[1]));
    }, [resources]);

    // Sorted + filtered resources
    const filteredResources = useMemo(() => {
        const filtered = resources.filter((r) => {
            if (typeFilter !== 'all' && r.type !== typeFilter) return false;
            if (statusFilter !== 'all' && r.status !== statusFilter) return false;
            if (projectFilter !== 'all' && String(r.project_id) !== projectFilter) return false;
            if (searchQuery && !r.name.toLowerCase().includes(searchQuery.toLowerCase())) return false;
            return true;
        });

        // Sort
        filtered.sort((a, b) => {
            let cmp = 0;
            switch (sortField) {
                case 'name':
                    cmp = a.name.localeCompare(b.name);
                    break;
                case 'type':
                    cmp = a.type.localeCompare(b.type);
                    break;
                case 'status':
                    cmp = (a.status ?? '').localeCompare(b.status ?? '');
                    break;
                case 'project':
                    cmp = (a.project_name ?? '').localeCompare(b.project_name ?? '');
                    break;
                case 'actions':
                    cmp = a.action_count - b.action_count;
                    break;
            }
            return sortDir === 'asc' ? cmp : -cmp;
        });

        return filtered;
    }, [resources, typeFilter, statusFilter, projectFilter, searchQuery, sortField, sortDir]);

    // Paginated slice
    const visibleResources = filteredResources.slice(0, visibleCount);
    const hasMore = visibleCount < filteredResources.length;

    // Selection helpers
    const getResourceKey = (r: FullResource) => `${r.full_type}:${r.id}`;

    const toggleSelection = (key: string) => {
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }
            return next;
        });
    };

    const toggleSelectAll = () => {
        const filteredKeys = filteredResources.map(getResourceKey);
        const allSelected = filteredKeys.every((k) => selectedIds.has(k));
        if (allSelected) {
            setSelectedIds((prev) => {
                const next = new Set(prev);
                filteredKeys.forEach((k) => next.delete(k));
                return next;
            });
        } else {
            setSelectedIds((prev) => {
                const next = new Set(prev);
                filteredKeys.forEach((k) => next.add(k));
                return next;
            });
        }
    };

    const clearSelection = () => setSelectedIds(new Set());

    const selectedResources = resources.filter((r) => selectedIds.has(getResourceKey(r)));

    // Sort toggle
    const handleSort = useCallback((field: SortField) => {
        setSortField((prev) => {
            if (prev === field) {
                setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
                return prev;
            }
            setSortDir(field === 'actions' ? 'desc' : 'asc');
            return field;
        });
        setVisibleCount(PAGE_SIZE);
    }, []);

    // Environment options grouped by project
    const environmentOptions = useMemo(() => {
        const grouped = new Map<string, EnvironmentOption[]>();
        environments.forEach((env) => {
            const key = env.project_name;
            if (!grouped.has(key)) grouped.set(key, []);
            grouped.get(key)!.push(env);
        });
        return Array.from(grouped.entries()).sort((a, b) => a[0].localeCompare(b[0]));
    }, [environments]);

    const getCsrfToken = () =>
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';

    // Move action
    const executeMove = async () => {
        if (!targetEnvironmentId || selectedResources.length === 0) return;
        setIsMoving(true);
        try {
            const response = await fetch(`/settings/team/archives/${archiveId}/resources/move`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'include',
                body: JSON.stringify({
                    resources: selectedResources.map((r) => ({
                        resource_type: r.full_type,
                        resource_id: r.id,
                    })),
                    target_environment_id: Number(targetEnvironmentId),
                }),
            });

            if (response.ok) {
                const data = await response.json();
                addToast('success', `Moved ${data.moved} resource(s)`);
                clearSelection();
                setTargetEnvironmentId('');
                router.reload();
            } else {
                addToast('error', 'Failed to move resources');
            }
        } catch {
            addToast('error', 'Failed to move resources');
        } finally {
            setIsMoving(false);
        }
    };

    // Delete action
    const executeDelete = async () => {
        if (selectedResources.length === 0) return;
        setIsDeleting(true);
        try {
            const response = await fetch(`/settings/team/archives/${archiveId}/resources/delete`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'include',
                body: JSON.stringify({
                    resources: selectedResources.map((r) => ({
                        resource_type: r.full_type,
                        resource_id: r.id,
                    })),
                }),
            });

            if (response.ok) {
                const data = await response.json();
                addToast('success', `Queued deletion of ${data.deleted} resource(s)`);
                clearSelection();
                router.reload();
            } else {
                addToast('error', 'Failed to delete resources');
            }
        } catch {
            addToast('error', 'Failed to delete resources');
        } finally {
            setIsDeleting(false);
        }
    };

    const { open: openMoveConfirm, ConfirmationDialog: MoveDialog } = useConfirmation({
        title: 'Move Resources',
        description: `Move ${selectedResources.length} resource(s) to a different environment? This changes their organizational location but does not affect running containers.`,
        confirmText: 'Move',
        cancelText: 'Cancel',
        variant: 'default',
        onConfirm: executeMove,
    });

    const deleteDescription = useMemo(() => {
        if (selectedResources.length === 0) return 'No resources selected.';
        const list = selectedResources
            .slice(0, 10)
            .map((r) => `  ${r.type}: ${r.name}`)
            .join('\n');
        const suffix = selectedResources.length > 10
            ? `\n  ...and ${selectedResources.length - 10} more`
            : '';
        return `Permanently delete ${selectedResources.length} resource(s)?\n\n${list}${suffix}\n\nThis will stop containers and remove all data. This action cannot be undone.`;
    }, [selectedResources]);

    const { open: openDeleteConfirm, ConfirmationDialog: DeleteDialog } = useConfirmation({
        title: 'Delete Resources',
        description: deleteDescription,
        confirmText: 'Delete',
        cancelText: 'Cancel',
        variant: 'danger',
        onConfirm: executeDelete,
    });

    if (resources.length === 0) {
        return null;
    }

    const allFilteredSelected =
        filteredResources.length > 0 && filteredResources.every((r) => selectedIds.has(getResourceKey(r)));

    const SortButton = ({ field, label }: { field: SortField; label: string }) => (
        <button
            onClick={() => handleSort(field)}
            className={`flex items-center gap-1 text-xs font-medium transition-colors ${
                sortField === field ? 'text-primary' : 'text-foreground-muted hover:text-foreground'
            }`}
        >
            {label}
            {sortField === field && (
                <ArrowUpDown className="h-3 w-3" />
            )}
        </button>
    );

    return (
        <>
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <Package className="h-5 w-5 text-foreground-muted" />
                            <div>
                                <CardTitle>Resource Manager</CardTitle>
                                <CardDescription>
                                    {resources.length} resource{resources.length !== 1 ? 's' : ''} associated with this member
                                </CardDescription>
                            </div>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    {/* Filter bar */}
                    <div className="mb-4 flex flex-wrap items-center gap-3">
                        <Select
                            className="w-36"
                            value={typeFilter}
                            onChange={(e) => { setTypeFilter(e.target.value); setVisibleCount(PAGE_SIZE); }}
                            options={[
                                { value: 'all', label: 'All types' },
                                ...typeOptions.map((t) => ({ value: t, label: t })),
                            ]}
                        />
                        <Select
                            className="w-36"
                            value={statusFilter}
                            onChange={(e) => { setStatusFilter(e.target.value); setVisibleCount(PAGE_SIZE); }}
                            options={[
                                { value: 'all', label: 'All statuses' },
                                ...statusOptions.map((s) => ({ value: s, label: s })),
                            ]}
                        />
                        {projectOptions.length > 1 && (
                            <Select
                                className="w-44"
                                value={projectFilter}
                                onChange={(e) => { setProjectFilter(e.target.value); setVisibleCount(PAGE_SIZE); }}
                                options={[
                                    { value: 'all', label: 'All projects' },
                                    ...projectOptions.map(([id, name]) => ({
                                        value: String(id),
                                        label: name,
                                    })),
                                ]}
                            />
                        )}
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                            <input
                                type="text"
                                placeholder="Search by name..."
                                value={searchQuery}
                                onChange={(e) => { setSearchQuery(e.target.value); setVisibleCount(PAGE_SIZE); }}
                                className="h-10 w-full rounded-md border border-border bg-background-secondary pl-9 pr-3 text-sm text-foreground placeholder:text-foreground-muted focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background"
                            />
                        </div>
                    </div>

                    {/* Bulk actions bar */}
                    {selectedIds.size > 0 && (
                        <div className="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-primary/30 bg-primary/5 p-3">
                            <span className="text-sm font-medium text-foreground">
                                {selectedIds.size} selected
                            </span>
                            <div className="flex items-center gap-2">
                                <Select
                                    className="w-56"
                                    value={targetEnvironmentId}
                                    onChange={(e) => setTargetEnvironmentId(e.target.value)}
                                >
                                    <option value="">Move to environment...</option>
                                    {environmentOptions.map(([projectName, envs]) => (
                                        <optgroup key={projectName} label={projectName}>
                                            {envs.map((env) => (
                                                <option key={env.id} value={String(env.id)}>
                                                    {env.name}
                                                </option>
                                            ))}
                                        </optgroup>
                                    ))}
                                </Select>
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    onClick={openMoveConfirm}
                                    loading={isMoving}
                                    disabled={!targetEnvironmentId}
                                >
                                    <ArrowRightLeft className="mr-1.5 h-3.5 w-3.5" />
                                    Move
                                </Button>
                            </div>
                            <Button
                                size="sm"
                                variant="danger"
                                onClick={openDeleteConfirm}
                                loading={isDeleting}
                            >
                                <Trash2 className="mr-1.5 h-3.5 w-3.5" />
                                Delete
                            </Button>
                            <Button size="sm" variant="ghost" onClick={clearSelection}>
                                Clear
                            </Button>
                        </div>
                    )}

                    {/* Sort bar + select all */}
                    {filteredResources.length > 0 && (
                        <div className="mb-2 flex items-center gap-3 px-3 py-1.5">
                            <input
                                type="checkbox"
                                checked={allFilteredSelected}
                                onChange={toggleSelectAll}
                                className="h-4 w-4 rounded border-border bg-background-secondary accent-primary"
                            />
                            <span className="text-xs font-medium text-foreground-muted">
                                {allFilteredSelected ? 'Deselect all' : 'Select all'} ({filteredResources.length})
                            </span>
                            <div className="ml-auto flex items-center gap-4">
                                <SortButton field="name" label="Name" />
                                <SortButton field="type" label="Type" />
                                <SortButton field="status" label="Status" />
                                <SortButton field="project" label="Project" />
                                <SortButton field="actions" label="Actions" />
                            </div>
                        </div>
                    )}

                    {/* Resource list */}
                    <div className="space-y-2">
                        {filteredResources.length === 0 ? (
                            <p className="py-4 text-center text-sm italic text-foreground-muted">
                                No resources match the current filters
                            </p>
                        ) : (
                            <>
                                {visibleResources.map((resource) => {
                                    const key = getResourceKey(resource);
                                    const isSelected = selectedIds.has(key);
                                    const statusVariant = STATUS_BADGE_MAP[resource.status ?? ''] ?? 'default';

                                    return (
                                        <div
                                            key={key}
                                            className={`flex items-center gap-3 rounded-lg border p-3 transition-colors ${
                                                isSelected
                                                    ? 'border-primary/40 bg-primary/5'
                                                    : 'border-border bg-background'
                                            }`}
                                        >
                                            <input
                                                type="checkbox"
                                                checked={isSelected}
                                                onChange={() => toggleSelection(key)}
                                                className="h-4 w-4 shrink-0 rounded border-border bg-background-secondary accent-primary"
                                            />
                                            <Badge variant="default" size="sm">
                                                {resource.type}
                                            </Badge>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-2">
                                                    {resource.url ? (
                                                        <Link
                                                            href={resource.url}
                                                            className="truncate text-sm font-medium text-foreground hover:text-primary hover:underline"
                                                        >
                                                            {resource.name}
                                                        </Link>
                                                    ) : (
                                                        <span className="truncate text-sm font-medium text-foreground">
                                                            {resource.name}
                                                        </span>
                                                    )}
                                                    <Badge
                                                        variant={resource.interaction === 'created' ? 'info' : 'default'}
                                                        size="sm"
                                                    >
                                                        {resource.interaction}
                                                    </Badge>
                                                </div>
                                                <div className="flex items-center gap-2 text-xs text-foreground-muted">
                                                    <span>{resource.project_name} / {resource.environment_name}</span>
                                                    {resource.server_name && (
                                                        <>
                                                            <span className="text-border">|</span>
                                                            <span className="flex items-center gap-1">
                                                                <Server className="h-3 w-3" />
                                                                {resource.server_name}
                                                            </span>
                                                        </>
                                                    )}
                                                </div>
                                            </div>
                                            {resource.status && (
                                                <Badge variant={statusVariant} size="sm" dot>
                                                    {resource.status}
                                                </Badge>
                                            )}
                                            <span className="shrink-0 text-xs text-foreground-muted">
                                                {resource.action_count} actions
                                            </span>
                                            {resource.url && (
                                                <Link
                                                    href={resource.url}
                                                    className="shrink-0 text-foreground-muted hover:text-foreground"
                                                >
                                                    <ExternalLink className="h-4 w-4" />
                                                </Link>
                                            )}
                                        </div>
                                    );
                                })}

                                {/* Load more */}
                                {hasMore && (
                                    <div className="flex justify-center pt-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setVisibleCount((prev) => prev + PAGE_SIZE)}
                                        >
                                            <ChevronDown className="mr-1.5 h-3.5 w-3.5" />
                                            Show more ({filteredResources.length - visibleCount} remaining)
                                        </Button>
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                </CardContent>
            </Card>

            <MoveDialog />
            <DeleteDialog />
        </>
    );
}
