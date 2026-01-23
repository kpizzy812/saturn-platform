import { useState } from 'react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Input, Badge, Modal, ModalFooter, Textarea, useConfirm } from '@/components/ui';
import {
    Plus,
    Trash2,
    Eye,
    EyeOff,
    Copy,
    Lock,
    Shield,
    Clock,
    RotateCw,
    AlertTriangle,
    History,
    ExternalLink,
    ChevronDown,
    ChevronRight
} from 'lucide-react';

interface Secret {
    id: string;
    key: string;
    value: string;
    lastRotated?: string;
    rotationSchedule?: 'never' | 'weekly' | 'monthly' | 'quarterly';
    externalReference?: {
        provider: 'aws-secrets-manager' | 'vault' | 'azure-key-vault';
        path: string;
    };
    createdAt: string;
    createdBy: string;
}

interface SecretAccessLog {
    id: string;
    secretKey: string;
    action: 'viewed' | 'created' | 'updated' | 'rotated' | 'deleted';
    user: string;
    timestamp: string;
    ipAddress: string;
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
    secrets?: Secret[];
    auditLogs?: SecretAccessLog[];
}

export default function EnvironmentSecrets({ environment, secrets: propSecrets = [], auditLogs = [] }: Props) {
    const confirm = useConfirm();
    const [secrets, setSecrets] = useState<Secret[]>(propSecrets);
    const [maskedValues, setMaskedValues] = useState<Set<string>>(new Set(secrets.map(s => s.id)));
    const [showAddModal, setShowAddModal] = useState(false);
    const [showRotateModal, setShowRotateModal] = useState(false);
    const [showAuditModal, setShowAuditModal] = useState(false);
    const [showExternalRefModal, setShowExternalRefModal] = useState(false);
    const [selectedSecret, setSelectedSecret] = useState<Secret | null>(null);
    const [newKey, setNewKey] = useState('');
    const [newValue, setNewValue] = useState('');
    const [newRotationSchedule, setNewRotationSchedule] = useState<Secret['rotationSchedule']>('never');
    const [copiedId, setCopiedId] = useState<string | null>(null);
    const [expandedAudit, setExpandedAudit] = useState(false);

    const toggleMask = (id: string, key: string) => {
        // Log the view action
        console.log(`Secret ${key} viewed`);

        const newMasked = new Set(maskedValues);
        if (newMasked.has(id)) {
            newMasked.delete(id);
        } else {
            newMasked.add(id);
        }
        setMaskedValues(newMasked);
    };

    const handleAddSecret = () => {
        if (!newKey.trim() || !newValue.trim()) return;

        const newSecret: Secret = {
            id: Date.now().toString(),
            key: newKey.trim(),
            value: newValue.trim(),
            rotationSchedule: newRotationSchedule,
            createdAt: new Date().toISOString(),
            createdBy: 'current-user@example.com',
            lastRotated: new Date().toISOString(),
        };

        setSecrets([...secrets, newSecret]);
        setMaskedValues(new Set([...maskedValues, newSecret.id]));
        setNewKey('');
        setNewValue('');
        setNewRotationSchedule('never');
        setShowAddModal(false);
    };

    const handleDeleteSecret = async (id: string, key: string) => {
        const confirmed = await confirm({
            title: 'Delete Secret',
            description: `Are you sure you want to delete the secret "${key}"? This action cannot be undone.`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            setSecrets(secrets.filter(s => s.id !== id));
            const newMasked = new Set(maskedValues);
            newMasked.delete(id);
            setMaskedValues(newMasked);
        }
    };

    const handleRotateSecret = () => {
        if (!selectedSecret) return;

        setSecrets(secrets.map(s =>
            s.id === selectedSecret.id
                ? { ...s, lastRotated: new Date().toISOString() }
                : s
        ));
        setShowRotateModal(false);
        setSelectedSecret(null);
    };

    const copyToClipboard = (text: string, id: string) => {
        navigator.clipboard.writeText(text);
        setCopiedId(id);
        setTimeout(() => setCopiedId(null), 2000);
    };

    const getRotationBadgeColor = (schedule?: Secret['rotationSchedule']) => {
        switch (schedule) {
            case 'weekly': return 'bg-green-500/10 text-green-500 border-green-500/20';
            case 'monthly': return 'bg-blue-500/10 text-blue-500 border-blue-500/20';
            case 'quarterly': return 'bg-purple-500/10 text-purple-500 border-purple-500/20';
            default: return 'bg-foreground-subtle/10 text-foreground-subtle border-border';
        }
    };

    const getActionBadgeColor = (action: SecretAccessLog['action']) => {
        switch (action) {
            case 'created': return 'bg-green-500/10 text-green-500';
            case 'viewed': return 'bg-blue-500/10 text-blue-500';
            case 'updated': return 'bg-yellow-500/10 text-yellow-500';
            case 'rotated': return 'bg-purple-500/10 text-purple-500';
            case 'deleted': return 'bg-red-500/10 text-red-500';
        }
    };

    return (
        <AppLayout
            title={`${environment.name} - Secrets`}
            breadcrumbs={[
                { label: 'Projects', href: '/projects' },
                { label: environment.project.name, href: `/projects/${environment.uuid}` },
                { label: environment.name },
                { label: 'Secrets' },
            ]}
        >
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold text-foreground">Secrets Management</h1>
                        <Shield className="h-6 w-6 text-primary" />
                    </div>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Securely manage sensitive credentials for {environment.name}
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button variant="secondary" size="sm" onClick={() => setShowAuditModal(true)}>
                        <History className="mr-2 h-4 w-4" />
                        Audit Log
                    </Button>
                    <Button size="sm" onClick={() => setShowAddModal(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Secret
                    </Button>
                </div>
            </div>

            {/* Security Notice */}
            <Card className="mb-6 border-yellow-500/20 bg-yellow-500/5">
                <CardContent className="p-4">
                    <div className="flex gap-3">
                        <AlertTriangle className="h-5 w-5 shrink-0 text-yellow-500" />
                        <div className="text-sm">
                            <p className="font-medium text-foreground">Security Best Practices</p>
                            <p className="mt-1 text-foreground-muted">
                                Secrets are always encrypted at rest. Viewing a secret is logged in the audit trail.
                                Consider using external secret managers for production environments.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Secrets List */}
            <div className="space-y-3">
                {secrets.map((secret) => (
                    <Card key={secret.id}>
                        <CardContent className="p-4">
                            <div className="flex items-start justify-between gap-4">
                                <div className="min-w-0 flex-1 space-y-3">
                                    {/* Secret Key & Badges */}
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Lock className="h-4 w-4 shrink-0 text-foreground-muted" />
                                        <code className="font-mono text-sm font-medium text-foreground">
                                            {secret.key}
                                        </code>
                                        {secret.rotationSchedule && secret.rotationSchedule !== 'never' && (
                                            <Badge
                                                variant="secondary"
                                                className={`border text-xs ${getRotationBadgeColor(secret.rotationSchedule)}`}
                                            >
                                                <RotateCw className="mr-1 h-3 w-3" />
                                                {secret.rotationSchedule}
                                            </Badge>
                                        )}
                                        {secret.externalReference && (
                                            <Badge variant="secondary" className="border border-primary/20 bg-primary/10 text-xs text-primary">
                                                <ExternalLink className="mr-1 h-3 w-3" />
                                                {secret.externalReference.provider}
                                            </Badge>
                                        )}
                                    </div>

                                    {/* Secret Value */}
                                    <div className="flex items-center gap-2">
                                        <code className="min-w-0 flex-1 truncate rounded bg-background-tertiary px-3 py-2 font-mono text-xs text-foreground-muted">
                                            {maskedValues.has(secret.id)
                                                ? '•'.repeat(32)
                                                : secret.value}
                                        </code>
                                        {copiedId === secret.id && (
                                            <span className="shrink-0 text-xs text-green-500">Copied!</span>
                                        )}
                                    </div>

                                    {/* Metadata */}
                                    <div className="flex flex-wrap gap-4 text-xs text-foreground-muted">
                                        {secret.lastRotated && (
                                            <div className="flex items-center gap-1">
                                                <Clock className="h-3 w-3" />
                                                Last rotated: {new Date(secret.lastRotated).toLocaleDateString()}
                                            </div>
                                        )}
                                        <div>Created by: {secret.createdBy}</div>
                                        {secret.externalReference && (
                                            <div className="flex items-center gap-1">
                                                <ExternalLink className="h-3 w-3" />
                                                {secret.externalReference.path}
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {/* Actions */}
                                <div className="flex shrink-0 gap-1">
                                    <button
                                        onClick={() => toggleMask(secret.id, secret.key)}
                                        className="rounded p-1.5 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                                        title={maskedValues.has(secret.id) ? 'Show secret' : 'Hide secret'}
                                    >
                                        {maskedValues.has(secret.id) ? (
                                            <Eye className="h-4 w-4" />
                                        ) : (
                                            <EyeOff className="h-4 w-4" />
                                        )}
                                    </button>
                                    <button
                                        onClick={() => copyToClipboard(secret.value, secret.id)}
                                        className="rounded p-1.5 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                                        title="Copy secret"
                                    >
                                        <Copy className="h-4 w-4" />
                                    </button>
                                    <button
                                        onClick={() => {
                                            setSelectedSecret(secret);
                                            setShowRotateModal(true);
                                        }}
                                        className="rounded p-1.5 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-foreground"
                                        title="Rotate secret"
                                    >
                                        <RotateCw className="h-4 w-4" />
                                    </button>
                                    <button
                                        onClick={() => handleDeleteSecret(secret.id, secret.key)}
                                        className="rounded p-1.5 text-foreground-muted transition-colors hover:bg-background-tertiary hover:text-red-500"
                                        title="Delete secret"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ))}

                {secrets.length === 0 && (
                    <Card>
                        <CardContent className="p-12 text-center">
                            <Shield className="mx-auto h-12 w-12 text-foreground-subtle" />
                            <h3 className="mt-4 font-medium text-foreground">No secrets yet</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Add your first secret to get started.
                            </p>
                            <Button className="mt-6" onClick={() => setShowAddModal(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Secret
                            </Button>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Recent Audit Activity */}
            <Card className="mt-6">
                <CardContent className="p-0">
                    <button
                        onClick={() => setExpandedAudit(!expandedAudit)}
                        className="flex w-full items-center justify-between border-b border-border p-4 text-left transition-colors hover:bg-background-tertiary"
                    >
                        <div className="flex items-center gap-2">
                            {expandedAudit ? (
                                <ChevronDown className="h-4 w-4 text-foreground-muted" />
                            ) : (
                                <ChevronRight className="h-4 w-4 text-foreground-muted" />
                            )}
                            <History className="h-4 w-4 text-foreground-muted" />
                            <h3 className="font-medium text-foreground">Recent Activity</h3>
                            <Badge variant="secondary">{auditLogs.length}</Badge>
                        </div>
                    </button>
                    {expandedAudit && (
                        <div className="divide-y divide-border">
                            {auditLogs.slice(0, 5).map((log) => (
                                <div key={log.id} className="flex items-center justify-between p-4">
                                    <div className="flex items-center gap-3">
                                        <Badge className={getActionBadgeColor(log.action)}>
                                            {log.action}
                                        </Badge>
                                        <div>
                                            <code className="font-mono text-sm text-foreground">
                                                {log.secretKey}
                                            </code>
                                            <p className="text-xs text-foreground-muted">
                                                by {log.user} from {log.ipAddress}
                                            </p>
                                        </div>
                                    </div>
                                    <span className="text-xs text-foreground-muted">
                                        {new Date(log.timestamp).toLocaleString()}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Add Secret Modal */}
            <Modal
                isOpen={showAddModal}
                onClose={() => setShowAddModal(false)}
                title="Add New Secret"
                description="Secrets are encrypted and access is logged"
                size="lg"
            >
                <div className="space-y-4">
                    <Input
                        label="Secret Key"
                        placeholder="API_SECRET_KEY"
                        value={newKey}
                        onChange={(e) => setNewKey(e.target.value)}
                    />
                    <Textarea
                        label="Secret Value"
                        placeholder="Enter the secret value..."
                        value={newValue}
                        onChange={(e) => setNewValue(e.target.value)}
                        rows={4}
                    />
                    <div>
                        <label className="mb-2 block text-sm font-medium text-foreground">
                            Rotation Schedule
                        </label>
                        <div className="grid grid-cols-4 gap-2">
                            {(['never', 'weekly', 'monthly', 'quarterly'] as const).map((schedule) => (
                                <button
                                    key={schedule}
                                    onClick={() => setNewRotationSchedule(schedule)}
                                    className={`rounded-md border px-3 py-2 text-sm transition-colors ${
                                        newRotationSchedule === schedule
                                            ? 'border-primary bg-primary/10 text-primary'
                                            : 'border-border bg-background-secondary text-foreground hover:bg-background-tertiary'
                                    }`}
                                >
                                    {schedule.charAt(0).toUpperCase() + schedule.slice(1)}
                                </button>
                            ))}
                        </div>
                    </div>
                </div>
                <ModalFooter>
                    <Button variant="secondary" onClick={() => setShowAddModal(false)}>
                        Cancel
                    </Button>
                    <Button onClick={handleAddSecret} disabled={!newKey.trim() || !newValue.trim()}>
                        <Lock className="mr-2 h-4 w-4" />
                        Add Secret
                    </Button>
                </ModalFooter>
            </Modal>

            {/* Rotate Secret Modal */}
            <Modal
                isOpen={showRotateModal}
                onClose={() => setShowRotateModal(false)}
                title="Rotate Secret"
                description={`Update the value for ${selectedSecret?.key}`}
            >
                <div className="space-y-4">
                    <div className="rounded-lg border border-yellow-500/20 bg-yellow-500/5 p-3">
                        <div className="flex gap-2">
                            <AlertTriangle className="h-5 w-5 shrink-0 text-yellow-500" />
                            <p className="text-sm text-foreground-muted">
                                Rotating this secret will update its value. Make sure to update all applications using this secret.
                            </p>
                        </div>
                    </div>
                    <Textarea
                        label="New Secret Value"
                        placeholder="Enter new secret value..."
                        rows={4}
                    />
                </div>
                <ModalFooter>
                    <Button variant="secondary" onClick={() => setShowRotateModal(false)}>
                        Cancel
                    </Button>
                    <Button onClick={handleRotateSecret}>
                        <RotateCw className="mr-2 h-4 w-4" />
                        Rotate Secret
                    </Button>
                </ModalFooter>
            </Modal>

            {/* Audit Log Modal */}
            <Modal
                isOpen={showAuditModal}
                onClose={() => setShowAuditModal(false)}
                title="Secret Access Audit Log"
                description="Complete history of secret access and modifications"
                size="full"
            >
                <div className="max-h-96 space-y-2 overflow-y-auto">
                    {auditLogs.map((log) => (
                        <div
                            key={log.id}
                            className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-3"
                        >
                            <div className="flex items-center gap-3">
                                <Badge className={getActionBadgeColor(log.action)}>
                                    {log.action}
                                </Badge>
                                <div>
                                    <code className="font-mono text-sm text-foreground">
                                        {log.secretKey}
                                    </code>
                                    <p className="text-xs text-foreground-muted">
                                        by {log.user} from {log.ipAddress}
                                    </p>
                                </div>
                            </div>
                            <span className="text-xs text-foreground-muted">
                                {new Date(log.timestamp).toLocaleString()}
                            </span>
                        </div>
                    ))}
                </div>
                <ModalFooter>
                    <Button onClick={() => setShowAuditModal(false)}>Close</Button>
                </ModalFooter>
            </Modal>
        </AppLayout>
    );
}
