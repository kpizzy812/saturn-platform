import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, Button, Modal } from '@/components/ui';
import { Plus, Eye, EyeOff, Copy, Edit2, Trash2, Check } from 'lucide-react';
import type { Service } from '@/types';

interface Props {
    service: Service;
}

interface Variable {
    id: number;
    key: string;
    value: string;
    isSecret: boolean;
}

// Mock variables data
const mockVariables: Variable[] = [
    { id: 1, key: 'DATABASE_URL', value: 'postgresql://user:pass@localhost:5432/db', isSecret: true },
    { id: 2, key: 'NODE_ENV', value: 'production', isSecret: false },
    { id: 3, key: 'PORT', value: '3000', isSecret: false },
    { id: 4, key: 'API_KEY', value: 'sk_live_1234567890abcdef', isSecret: true },
    { id: 5, key: 'REDIS_URL', value: 'redis://localhost:6379', isSecret: true },
    { id: 6, key: 'LOG_LEVEL', value: 'info', isSecret: false },
];

export function VariablesTab({ service }: Props) {
    const [variables, setVariables] = useState<Variable[]>(mockVariables);
    const [showValues, setShowValues] = useState<Record<number, boolean>>({});
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [editingVariable, setEditingVariable] = useState<Variable | null>(null);
    const [isBulkEdit, setIsBulkEdit] = useState(false);
    const [copiedId, setCopiedId] = useState<number | null>(null);

    const toggleShowValue = (id: number) => {
        setShowValues((prev) => ({ ...prev, [id]: !prev[id] }));
    };

    const handleCopy = async (value: string, id: number) => {
        await navigator.clipboard.writeText(value);
        setCopiedId(id);
        setTimeout(() => setCopiedId(null), 2000);
    };

    const handleEdit = (variable: Variable) => {
        setEditingVariable(variable);
        setIsEditModalOpen(true);
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this variable?')) {
            setVariables((prev) => prev.filter((v) => v.id !== id));
        }
    };

    const handleSaveEdit = (key: string, value: string, isSecret: boolean) => {
        if (editingVariable) {
            setVariables((prev) =>
                prev.map((v) =>
                    v.id === editingVariable.id ? { ...v, key, value, isSecret } : v
                )
            );
        }
        setIsEditModalOpen(false);
        setEditingVariable(null);
    };

    const handleAdd = (key: string, value: string, isSecret: boolean) => {
        const newVariable: Variable = {
            id: Math.max(...variables.map((v) => v.id), 0) + 1,
            key,
            value,
            isSecret,
        };
        setVariables((prev) => [...prev, newVariable]);
        setIsAddModalOpen(false);
    };

    return (
        <div className="space-y-4">
            {/* Header Actions */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="font-medium text-foreground">Environment Variables</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Manage environment variables for your service
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => setIsBulkEdit(!isBulkEdit)}
                            >
                                {isBulkEdit ? 'Exit Bulk Edit' : 'Bulk Edit'}
                            </Button>
                            <Button onClick={() => setIsAddModalOpen(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Variable
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Bulk Edit Mode */}
            {isBulkEdit ? (
                <Card>
                    <CardHeader>
                        <CardTitle>Bulk Edit Variables</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <textarea
                            className="h-96 w-full rounded-md border border-border bg-background p-4 font-mono text-sm text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            defaultValue={variables
                                .map((v) => `${v.key}=${v.value}`)
                                .join('\n')}
                            placeholder="KEY=value&#10;ANOTHER_KEY=another_value"
                        />
                        <div className="mt-4 flex justify-end gap-2">
                            <Button variant="secondary" onClick={() => setIsBulkEdit(false)}>
                                Cancel
                            </Button>
                            <Button onClick={() => setIsBulkEdit(false)}>
                                Save Changes
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            ) : (
                /* Variables List */
                <div className="space-y-2">
                    {variables.length === 0 ? (
                        <Card>
                            <CardContent className="flex flex-col items-center justify-center py-12">
                                <Plus className="h-12 w-12 text-foreground-subtle" />
                                <h3 className="mt-4 font-medium text-foreground">No variables</h3>
                                <p className="mt-1 text-sm text-foreground-muted">
                                    Add your first environment variable
                                </p>
                                <Button className="mt-4" onClick={() => setIsAddModalOpen(true)}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Variable
                                </Button>
                            </CardContent>
                        </Card>
                    ) : (
                        variables.map((variable) => (
                            <Card key={variable.id}>
                                <CardContent className="p-4">
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                <code className="text-sm font-medium text-foreground">
                                                    {variable.key}
                                                </code>
                                                {variable.isSecret && (
                                                    <span className="rounded bg-warning/20 px-2 py-0.5 text-xs font-medium text-warning">
                                                        Secret
                                                    </span>
                                                )}
                                            </div>
                                            <div className="mt-2 flex items-center gap-2">
                                                <code className="text-sm text-foreground-muted">
                                                    {showValues[variable.id] || !variable.isSecret
                                                        ? variable.value
                                                        : '••••••••••••••••'}
                                                </code>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            {variable.isSecret && (
                                                <button
                                                    onClick={() => toggleShowValue(variable.id)}
                                                    className="rounded p-2 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                                                    title={showValues[variable.id] ? 'Hide value' : 'Show value'}
                                                >
                                                    {showValues[variable.id] ? (
                                                        <EyeOff className="h-4 w-4" />
                                                    ) : (
                                                        <Eye className="h-4 w-4" />
                                                    )}
                                                </button>
                                            )}
                                            <button
                                                onClick={() => handleCopy(variable.value, variable.id)}
                                                className="rounded p-2 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                                                title="Copy value"
                                            >
                                                {copiedId === variable.id ? (
                                                    <Check className="h-4 w-4 text-primary" />
                                                ) : (
                                                    <Copy className="h-4 w-4" />
                                                )}
                                            </button>
                                            <button
                                                onClick={() => handleEdit(variable)}
                                                className="rounded p-2 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                                                title="Edit variable"
                                            >
                                                <Edit2 className="h-4 w-4" />
                                            </button>
                                            <button
                                                onClick={() => handleDelete(variable.id)}
                                                className="rounded p-2 text-danger transition-colors hover:bg-danger/10"
                                                title="Delete variable"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))
                    )}
                </div>
            )}

            {/* Add/Edit Modal */}
            <VariableModal
                isOpen={isAddModalOpen || isEditModalOpen}
                onClose={() => {
                    setIsAddModalOpen(false);
                    setIsEditModalOpen(false);
                    setEditingVariable(null);
                }}
                onSave={isEditModalOpen ? handleSaveEdit : handleAdd}
                variable={editingVariable}
                title={isEditModalOpen ? 'Edit Variable' : 'Add Variable'}
            />
        </div>
    );
}

interface VariableModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSave: (key: string, value: string, isSecret: boolean) => void;
    variable?: Variable | null;
    title: string;
}

function VariableModal({ isOpen, onClose, onSave, variable, title }: VariableModalProps) {
    const [key, setKey] = useState(variable?.key || '');
    const [value, setValue] = useState(variable?.value || '');
    const [isSecret, setIsSecret] = useState(variable?.isSecret || false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (key && value) {
            onSave(key, value, isSecret);
            setKey('');
            setValue('');
            setIsSecret(false);
        }
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div className="w-full max-w-md rounded-lg border border-border bg-background p-6 shadow-xl">
                <h2 className="text-xl font-semibold text-foreground">{title}</h2>
                <form onSubmit={handleSubmit} className="mt-4 space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-foreground">
                            Key
                        </label>
                        <input
                            type="text"
                            value={key}
                            onChange={(e) => setKey(e.target.value)}
                            className="mt-1 w-full rounded-md border border-border bg-background px-3 py-2 text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            placeholder="DATABASE_URL"
                            required
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-foreground">
                            Value
                        </label>
                        <textarea
                            value={value}
                            onChange={(e) => setValue(e.target.value)}
                            className="mt-1 w-full rounded-md border border-border bg-background px-3 py-2 text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            placeholder="postgresql://..."
                            rows={3}
                            required
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="is-secret"
                            checked={isSecret}
                            onChange={(e) => setIsSecret(e.target.checked)}
                            className="h-4 w-4 rounded border-border bg-background text-primary focus:ring-2 focus:ring-primary focus:ring-offset-2"
                        />
                        <label htmlFor="is-secret" className="text-sm text-foreground-muted">
                            Mark as secret (hide value by default)
                        </label>
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit">
                            {variable ? 'Save Changes' : 'Add Variable'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}
