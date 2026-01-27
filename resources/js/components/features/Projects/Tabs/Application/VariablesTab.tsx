import { useState, useEffect } from 'react';
import { Button } from '@/components/ui';
import { Plus, RefreshCw, Eye, EyeOff, Copy, Trash2, Pencil, Check, X } from 'lucide-react';
import { useToast } from '@/components/ui/Toast';
import type { SelectedService } from '../../types';

interface EnvVariable {
    id: number;
    uuid: string;
    key: string;
    value: string;
    real_value?: string;
    is_preview?: boolean;
    is_shown_once?: boolean;
}

interface VariablesTabProps {
    service: SelectedService;
}

interface EditState {
    uuid: string;
    key: string;
    value: string;
}

export function VariablesTab({ service }: VariablesTabProps) {
    const { toast } = useToast();
    const [variables, setVariables] = useState<EnvVariable[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [showAddModal, setShowAddModal] = useState(false);
    const [newKey, setNewKey] = useState('');
    const [newValue, setNewValue] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [revealedIds, setRevealedIds] = useState<Set<number>>(new Set());
    const [deletingIds, setDeletingIds] = useState<Set<string>>(new Set());
    const [editing, setEditing] = useState<EditState | null>(null);
    const [isSaving, setIsSaving] = useState(false);

    // Fetch environment variables
    useEffect(() => {
        const fetchEnvs = async () => {
            try {
                setIsLoading(true);
                const response = await fetch(`/api/v1/applications/${service.uuid}/envs`, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'include',
                });
                if (response.ok) {
                    const data = await response.json();
                    setVariables(data.filter((env: EnvVariable) => !env.is_preview));
                }
            } catch {
                toast({ title: 'Failed to load variables', variant: 'error' });
            } finally {
                setIsLoading(false);
            }
        };
        fetchEnvs();
    }, [service.uuid, toast]);

    const handleAddVariable = async () => {
        if (!newKey.trim()) {
            toast({ title: 'Key is required', variant: 'error' });
            return;
        }
        try {
            setIsSubmitting(true);
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(`/api/v1/applications/${service.uuid}/envs`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
                body: JSON.stringify({ key: newKey, value: newValue }),
            });
            if (response.ok) {
                const created = await response.json();
                setVariables(prev => [...prev, created]);
                setShowAddModal(false);
                setNewKey('');
                setNewValue('');
                toast({ title: 'Variable created' });
            } else {
                const error = await response.json();
                toast({ title: error.message || 'Failed to create variable', variant: 'error' });
            }
        } catch {
            toast({ title: 'Failed to create variable', variant: 'error' });
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleSaveEdit = async () => {
        if (!editing) return;
        try {
            setIsSaving(true);
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(`/api/v1/applications/${service.uuid}/envs`, {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
                body: JSON.stringify({ key: editing.key, value: editing.value }),
            });
            if (response.ok) {
                setVariables(prev => prev.map(v =>
                    v.uuid === editing.uuid
                        ? { ...v, key: editing.key, value: editing.value, real_value: editing.value }
                        : v
                ));
                setEditing(null);
                toast({ title: `Updated ${editing.key}` });
            } else {
                const error = await response.json();
                toast({ title: error.message || 'Failed to update variable', variant: 'error' });
            }
        } catch {
            toast({ title: 'Failed to update variable', variant: 'error' });
        } finally {
            setIsSaving(false);
        }
    };

    const handleCopyVariable = async (key: string, value: string) => {
        try {
            await navigator.clipboard.writeText(`${key}=${value}`);
            toast({ title: `Copied ${key} to clipboard` });
        } catch {
            toast({ title: 'Failed to copy', variant: 'error' });
        }
    };

    const toggleReveal = (id: number) => {
        setRevealedIds(prev => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    };

    const handleDeleteVariable = async (envUuid: string, key: string) => {
        if (!confirm(`Delete variable "${key}"?`)) return;
        try {
            setDeletingIds(prev => new Set(prev).add(envUuid));
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(`/api/v1/applications/${service.uuid}/envs/${envUuid}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
            });
            if (response.ok) {
                setVariables(prev => prev.filter(v => v.uuid !== envUuid));
                toast({ title: `Deleted ${key}` });
            } else {
                const error = await response.json();
                toast({ title: error.message || 'Failed to delete variable', variant: 'error' });
            }
        } catch {
            toast({ title: 'Failed to delete variable', variant: 'error' });
        } finally {
            setDeletingIds(prev => {
                const next = new Set(prev);
                next.delete(envUuid);
                return next;
            });
        }
    };

    const startEditing = (v: EnvVariable) => {
        setEditing({ uuid: v.uuid, key: v.key, value: v.real_value || v.value });
        // Auto-reveal the value being edited
        setRevealedIds(prev => new Set(prev).add(v.id));
    };

    const maskValue = (value: string) => '•'.repeat(Math.min(value.length, 12));

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-8">
                <RefreshCw className="h-5 w-5 animate-spin text-foreground-muted" />
            </div>
        );
    }

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-medium text-foreground">Environment Variables</h3>
                <Button size="sm" variant="secondary" onClick={() => setShowAddModal(true)}>
                    <Plus className="mr-1 h-3 w-3" />
                    Add
                </Button>
            </div>

            {/* Add Variable Modal */}
            {showAddModal && (
                <div className="rounded-lg border border-border bg-background p-4 space-y-3">
                    <h4 className="text-sm font-medium">Add Environment Variable</h4>
                    <div className="space-y-2">
                        <input
                            type="text"
                            placeholder="KEY_NAME"
                            value={newKey}
                            onChange={(e) => setNewKey(e.target.value.toUpperCase().replace(/[^A-Z0-9_]/g, ''))}
                            className="w-full rounded-md border border-border bg-background-secondary px-3 py-2 text-sm font-mono"
                        />
                        <textarea
                            placeholder="Value"
                            value={newValue}
                            onChange={(e) => setNewValue(e.target.value)}
                            rows={2}
                            className="w-full rounded-md border border-border bg-background-secondary px-3 py-2 text-sm font-mono"
                        />
                    </div>
                    <div className="flex gap-2">
                        <Button size="sm" onClick={handleAddVariable} disabled={isSubmitting}>
                            {isSubmitting ? 'Creating...' : 'Create'}
                        </Button>
                        <Button size="sm" variant="secondary" onClick={() => setShowAddModal(false)}>
                            Cancel
                        </Button>
                    </div>
                </div>
            )}

            <div className="space-y-2">
                {variables.length === 0 ? (
                    <p className="text-sm text-foreground-muted py-4 text-center">No environment variables configured</p>
                ) : (
                    variables.map((v) => {
                        const isEditing = editing?.uuid === v.uuid;

                        return (
                            <div key={v.id} className="rounded-lg border border-border bg-background-secondary p-3">
                                {isEditing ? (
                                    <div className="space-y-2">
                                        <input
                                            type="text"
                                            value={editing.key}
                                            onChange={(e) => setEditing({ ...editing, key: e.target.value.toUpperCase().replace(/[^A-Z0-9_]/g, '') })}
                                            className="w-full rounded-md border border-border bg-background px-3 py-1.5 text-sm font-mono font-medium"
                                        />
                                        <textarea
                                            value={editing.value}
                                            onChange={(e) => setEditing({ ...editing, value: e.target.value })}
                                            rows={2}
                                            className="w-full rounded-md border border-border bg-background px-3 py-1.5 text-sm font-mono"
                                        />
                                        <div className="flex gap-1">
                                            <button
                                                onClick={handleSaveEdit}
                                                disabled={isSaving}
                                                className="rounded p-1 text-green-500 hover:bg-green-500/10 disabled:opacity-50"
                                                title="Save"
                                            >
                                                <Check className="h-4 w-4" />
                                            </button>
                                            <button
                                                onClick={() => setEditing(null)}
                                                disabled={isSaving}
                                                className="rounded p-1 text-foreground-muted hover:bg-background hover:text-foreground disabled:opacity-50"
                                                title="Cancel"
                                            >
                                                <X className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex items-center justify-between">
                                        <div className="flex-1 min-w-0">
                                            <code className="text-sm font-medium text-foreground">{v.key}</code>
                                            <p className="text-sm text-foreground-muted font-mono truncate">
                                                {revealedIds.has(v.id) ? (v.real_value || v.value) : maskValue(v.real_value || v.value)}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <button
                                                onClick={() => toggleReveal(v.id)}
                                                className="rounded p-1 text-foreground-muted hover:bg-background hover:text-foreground"
                                                title={revealedIds.has(v.id) ? 'Hide value' : 'Show value'}
                                            >
                                                {revealedIds.has(v.id) ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                            </button>
                                            <button
                                                onClick={() => startEditing(v)}
                                                className="rounded p-1 text-foreground-muted hover:bg-background hover:text-foreground"
                                                title="Edit variable"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            <button
                                                onClick={() => handleCopyVariable(v.key, v.real_value || v.value)}
                                                className="rounded p-1 text-foreground-muted hover:bg-background hover:text-foreground"
                                                title="Copy to clipboard"
                                            >
                                                <Copy className="h-4 w-4" />
                                            </button>
                                            <button
                                                onClick={() => handleDeleteVariable(v.uuid, v.key)}
                                                disabled={deletingIds.has(v.uuid)}
                                                className="rounded p-1 text-foreground-muted hover:bg-red-500/10 hover:text-red-500 disabled:opacity-50"
                                                title="Delete variable"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        );
                    })
                )}
            </div>
        </div>
    );
}
