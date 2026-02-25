import { useState } from 'react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Input, Textarea, Badge, Modal, ModalFooter, useConfirm } from '@/components/ui';
import {
    Plus,
    Trash2,
    Eye,
    EyeOff,
    Copy,
    Download,
    Upload,
    Search,
    Edit2,
    Check,
    X,
    Lock,
    ChevronDown,
    ChevronRight,
    Link as LinkIcon
} from 'lucide-react';
import { usePermissions } from '@/hooks/usePermissions';

interface EnvironmentVariable {
    id: string;
    key: string;
    value: string;
    group?: string;
    isInherited?: boolean;
    inheritedFrom?: string;
    reference?: string; // e.g., "${SERVICE.VAR}"
}

interface Props {
    environment: {
        id: number;
        uuid: string;
        name: string;
        project: {
            name: string;
        };
    };
    variables?: EnvironmentVariable[];
    inheritedVariables?: EnvironmentVariable[];
}

export default function EnvironmentVariables({ environment, variables: propVariables = [], inheritedVariables = [] }: Props) {
    const { can } = usePermissions();
    const canEditVars = can('applications.env_vars') || can('services.env_vars') || can('databases.env_vars');
    const canRevealVars = can('applications.env_vars_sensitive');
    const confirm = useConfirm();
    const [variables, setVariables] = useState<EnvironmentVariable[]>(propVariables);
    const [searchQuery, setSearchQuery] = useState('');
    const [showImportModal, setShowImportModal] = useState(false);
    const [importText, setImportText] = useState('');
    const [editingId, setEditingId] = useState<string | null>(null);
    const [editKey, setEditKey] = useState('');
    const [editValue, setEditValue] = useState('');
    const [newKey, setNewKey] = useState('');
    const [newValue, setNewValue] = useState('');
    const [newGroup, setNewGroup] = useState('');
    const [maskedValues, setMaskedValues] = useState<Set<string>>(new Set(variables.map(v => v.id)));
    const [expandedGroups, setExpandedGroups] = useState<Set<string>>(new Set(['Database', 'Cache', 'API', 'General', 'References']));
    const [showInherited, setShowInherited] = useState(true);
    const [copiedId, setCopiedId] = useState<string | null>(null);

    // Filter variables by search query
    const filteredVariables = variables.filter(v =>
        v.key.toLowerCase().includes(searchQuery.toLowerCase()) ||
        v.value.toLowerCase().includes(searchQuery.toLowerCase()) ||
        (v.group && v.group.toLowerCase().includes(searchQuery.toLowerCase()))
    );

    // Group variables
    const groupedVariables = filteredVariables.reduce((acc, variable) => {
        const group = variable.group || 'Uncategorized';
        if (!acc[group]) acc[group] = [];
        acc[group].push(variable);
        return acc;
    }, {} as Record<string, EnvironmentVariable[]>);

    const toggleMask = (id: string) => {
        const newMasked = new Set(maskedValues);
        if (newMasked.has(id)) {
            newMasked.delete(id);
        } else {
            newMasked.add(id);
        }
        setMaskedValues(newMasked);
    };

    const toggleGroup = (group: string) => {
        const newExpanded = new Set(expandedGroups);
        if (newExpanded.has(group)) {
            newExpanded.delete(group);
        } else {
            newExpanded.add(group);
        }
        setExpandedGroups(newExpanded);
    };

    const handleAddVariable = () => {
        if (!newKey.trim() || !newValue.trim()) return;

        const newVariable: EnvironmentVariable = {
            id: Date.now().toString(),
            key: newKey.trim(),
            value: newValue.trim(),
            group: newGroup.trim() || 'General',
        };

        setVariables([...variables, newVariable]);
        setMaskedValues(new Set([...maskedValues, newVariable.id]));
        setNewKey('');
        setNewValue('');
        setNewGroup('');
    };

    const handleDeleteVariable = async (id: string) => {
        const confirmed = await confirm({
            title: 'Delete Variable',
            description: 'Are you sure you want to delete this variable?',
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            setVariables(variables.filter(v => v.id !== id));
            const newMasked = new Set(maskedValues);
            newMasked.delete(id);
            setMaskedValues(newMasked);
        }
    };

    const startEditing = (variable: EnvironmentVariable) => {
        setEditingId(variable.id);
        setEditKey(variable.key);
        setEditValue(variable.value);
    };

    const saveEdit = () => {
        if (!editKey.trim() || !editValue.trim()) return;

        setVariables(variables.map(v =>
            v.id === editingId
                ? { ...v, key: editKey.trim(), value: editValue.trim() }
                : v
        ));
        setEditingId(null);
        setEditKey('');
        setEditValue('');
    };

    const cancelEdit = () => {
        setEditingId(null);
        setEditKey('');
        setEditValue('');
    };

    const copyToClipboard = (text: string, id: string) => {
        navigator.clipboard.writeText(text);
        setCopiedId(id);
        setTimeout(() => setCopiedId(null), 2000);
    };

    const handleExport = () => {
        const envContent = variables
            .map(v => `${v.key}=${v.value}`)
            .join('\n');

        const blob = new Blob([envContent], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${environment.name}.env`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    const handleImport = () => {
        const lines = importText.split('\n');
        const newVariables: EnvironmentVariable[] = [];

        lines.forEach(line => {
            const trimmed = line.trim();
            if (!trimmed || trimmed.startsWith('#')) return;

            const [key, ...valueParts] = trimmed.split('=');
            if (key && valueParts.length > 0) {
                const value = valueParts.join('=').replace(/^["']|["']$/g, '');
                newVariables.push({
                    id: Date.now().toString() + Math.random(),
                    key: key.trim(),
                    value: value.trim(),
                    group: 'Imported',
                });
            }
        });

        setVariables([...variables, ...newVariables]);
        setImportText('');
        setShowImportModal(false);
    };

    return (
        <AppLayout
            title={`${environment.name} - Environment Variables`}
            breadcrumbs={[
                { label: 'Projects', href: '/projects' },
                { label: environment.project.name, href: `/projects/${environment.uuid}` },
                { label: environment.name },
                { label: 'Variables' },
            ]}
        >
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Environment Variables</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Manage variables for {environment.name} environment
                    </p>
                </div>
                {canEditVars && (
                    <div className="flex gap-2">
                        <Button variant="secondary" size="sm" onClick={() => setShowImportModal(true)}>
                            <Upload className="mr-2 h-4 w-4" />
                            Import .env
                        </Button>
                        <Button variant="secondary" size="sm" onClick={handleExport}>
                            <Download className="mr-2 h-4 w-4" />
                            Export
                        </Button>
                    </div>
                )}
            </div>

            {/* Search */}
            <div className="mb-6">
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                    <input
                        type="text"
                        placeholder="Search variables..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="h-10 w-full rounded-md border border-border bg-background-secondary pl-10 pr-4 text-sm text-foreground placeholder:text-foreground-subtle focus:outline-none focus:ring-2 focus:ring-primary"
                    />
                </div>
            </div>

            {/* Add New Variable */}
            {canEditVars && (
                <Card className="mb-6">
                    <CardContent className="p-4">
                        <div className="flex items-end gap-3">
                            <div className="flex-1">
                                <Input
                                    label="Key"
                                    placeholder="VARIABLE_NAME"
                                    value={newKey}
                                    onChange={(e) => setNewKey(e.target.value)}
                                />
                            </div>
                            <div className="flex-1">
                                <Input
                                    label="Value"
                                    placeholder="variable_value"
                                    value={newValue}
                                    onChange={(e) => setNewValue(e.target.value)}
                                />
                            </div>
                            <div className="flex-1">
                                <Input
                                    label="Group (optional)"
                                    placeholder="Database, API, etc."
                                    value={newGroup}
                                    onChange={(e) => setNewGroup(e.target.value)}
                                />
                            </div>
                            <Button onClick={handleAddVariable} disabled={!newKey.trim() || !newValue.trim()}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Variables List */}
            <div className="space-y-6">
                {Object.entries(groupedVariables).map(([group, vars]) => (
                    <Card key={group}>
                        <CardContent className="p-0">
                            {/* Group Header */}
                            <button
                                onClick={() => toggleGroup(group)}
                                className="flex w-full items-center justify-between border-b border-border p-4 text-left transition-colors hover:bg-background-tertiary"
                            >
                                <div className="flex items-center gap-2">
                                    {expandedGroups.has(group) ? (
                                        <ChevronDown className="h-4 w-4 text-foreground-muted" />
                                    ) : (
                                        <ChevronRight className="h-4 w-4 text-foreground-muted" />
                                    )}
                                    <h3 className="font-medium text-foreground">{group}</h3>
                                    <Badge variant="secondary">{vars.length}</Badge>
                                </div>
                            </button>

                            {/* Group Variables */}
                            {expandedGroups.has(group) && (
                                <div className="divide-y divide-border">
                                    {vars.map((variable) => (
                                        <div key={variable.id} className="p-4">
                                            {editingId === variable.id ? (
                                                // Edit Mode
                                                <div className="space-y-3">
                                                    <div className="grid gap-3 md:grid-cols-2">
                                                        <Input
                                                            label="Key"
                                                            value={editKey}
                                                            onChange={(e) => setEditKey(e.target.value)}
                                                        />
                                                        <Input
                                                            label="Value"
                                                            value={editValue}
                                                            onChange={(e) => setEditValue(e.target.value)}
                                                        />
                                                    </div>
                                                    <div className="flex gap-2">
                                                        <Button size="sm" onClick={saveEdit}>
                                                            <Check className="mr-2 h-4 w-4" />
                                                            Save
                                                        </Button>
                                                        <Button size="sm" variant="secondary" onClick={cancelEdit}>
                                                            <X className="mr-2 h-4 w-4" />
                                                            Cancel
                                                        </Button>
                                                    </div>
                                                </div>
                                            ) : (
                                                // View Mode
                                                <div className="flex items-start justify-between gap-4">
                                                    <div className="min-w-0 flex-1 space-y-2">
                                                        <div className="flex items-center gap-2">
                                                            <code className="font-mono text-sm font-medium text-foreground">
                                                                {variable.key}
                                                            </code>
                                                            {variable.isInherited && (
                                                                <Badge variant="secondary" className="text-xs">
                                                                    Inherited from {variable.inheritedFrom}
                                                                </Badge>
                                                            )}
                                                            {variable.reference && (
                                                                <div className="flex items-center gap-1 text-xs text-foreground-muted">
                                                                    <LinkIcon className="h-3 w-3" />
                                                                    Reference
                                                                </div>
                                                            )}
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <code className="min-w-0 flex-1 truncate rounded bg-background-tertiary px-2 py-1 font-mono text-xs text-foreground-muted">
                                                                {maskedValues.has(variable.id)
                                                                    ? '•'.repeat(Math.min(variable.value.length, 32))
                                                                    : variable.value}
                                                            </code>
                                                            {copiedId === variable.id && (
                                                                <span className="text-xs text-green-500">Copied!</span>
                                                            )}
                                                        </div>
                                                    </div>
                                                    <div className="flex shrink-0 gap-1">
                                                        {canRevealVars && (
                                                            <button
                                                                onClick={() => toggleMask(variable.id)}
                                                                className="rounded p-1.5 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                                                                title={maskedValues.has(variable.id) ? 'Show value' : 'Hide value'}
                                                            >
                                                                {maskedValues.has(variable.id) ? (
                                                                    <Eye className="h-4 w-4" />
                                                                ) : (
                                                                    <EyeOff className="h-4 w-4" />
                                                                )}
                                                            </button>
                                                        )}
                                                        {canRevealVars && (
                                                            <button
                                                                onClick={() => copyToClipboard(variable.value, variable.id)}
                                                                className="rounded p-1.5 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                                                                title="Copy value"
                                                            >
                                                                <Copy className="h-4 w-4" />
                                                            </button>
                                                        )}
                                                        {!variable.isInherited && canEditVars && (
                                                            <>
                                                                <button
                                                                    onClick={() => startEditing(variable)}
                                                                    className="rounded p-1.5 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                                                                    title="Edit variable"
                                                                >
                                                                    <Edit2 className="h-4 w-4" />
                                                                </button>
                                                                <button
                                                                    onClick={() => handleDeleteVariable(variable.id)}
                                                                    className="rounded p-1.5 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-red-500"
                                                                    title="Delete variable"
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </button>
                                                            </>
                                                        )}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                ))}
            </div>

            {/* Inherited Variables Section */}
            {showInherited && inheritedVariables.length > 0 && (
                <Card className="mt-6">
                    <CardContent className="p-0">
                        <button
                            onClick={() => setShowInherited(!showInherited)}
                            className="flex w-full items-center justify-between border-b border-border p-4 text-left transition-colors hover:bg-background-tertiary"
                        >
                            <div className="flex items-center gap-2">
                                <Lock className="h-4 w-4 text-foreground-muted" />
                                <h3 className="font-medium text-foreground">Inherited from Project</h3>
                                <Badge variant="secondary">{inheritedVariables.length}</Badge>
                            </div>
                        </button>
                        <div className="divide-y divide-border">
                            {inheritedVariables.map((variable) => (
                                <div key={variable.id} className="flex items-center justify-between p-4">
                                    <div className="min-w-0 flex-1">
                                        <code className="font-mono text-sm font-medium text-foreground">
                                            {variable.key}
                                        </code>
                                        <div className="mt-1">
                                            <code className="text-xs text-foreground-muted">
                                                {variable.value}
                                            </code>
                                        </div>
                                    </div>
                                    <Badge variant="secondary" className="shrink-0">
                                        Read-only
                                    </Badge>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Import Modal */}
            <Modal
                isOpen={showImportModal}
                onClose={() => setShowImportModal(false)}
                title="Import from .env file"
                description="Paste the contents of your .env file below"
                size="lg"
            >
                <Textarea
                    label="Environment file contents"
                    placeholder="DATABASE_URL=postgresql://...&#10;API_KEY=sk_live_..."
                    value={importText}
                    onChange={(e) => setImportText(e.target.value)}
                    rows={12}
                    className="font-mono text-xs"
                />
                <ModalFooter>
                    <Button variant="secondary" onClick={() => setShowImportModal(false)}>
                        Cancel
                    </Button>
                    <Button onClick={handleImport} disabled={!importText.trim()}>
                        Import Variables
                    </Button>
                </ModalFooter>
            </Modal>
        </AppLayout>
    );
}
