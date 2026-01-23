import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Input, Select, Badge, Modal, ModalFooter, useConfirm } from '@/components/ui';
import { Route, ArrowRight, Plus, Trash2, Edit, ExternalLink, Check, X } from 'lucide-react';

interface RedirectRule {
    id: number;
    source: string;
    target: string;
    type: '301' | '302';
    enabled: boolean;
    hits: number;
    created_at: string;
}

interface Props {
    redirects?: RedirectRule[];
}

export default function DomainsRedirects({ redirects: propRedirects = [] }: Props) {
    const confirm = useConfirm();
    const [redirects, setRedirects] = useState<RedirectRule[]>(propRedirects);
    const [showAddModal, setShowAddModal] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [source, setSource] = useState('');
    const [target, setTarget] = useState('');
    const [redirectType, setRedirectType] = useState<'301' | '302'>('301');
    const [enabled, setEnabled] = useState(true);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const handleOpenAdd = () => {
        setEditingId(null);
        setSource('');
        setTarget('');
        setRedirectType('301');
        setEnabled(true);
        setErrors({});
        setShowAddModal(true);
    };

    const handleOpenEdit = (redirect: RedirectRule) => {
        setEditingId(redirect.id);
        setSource(redirect.source);
        setTarget(redirect.target);
        setRedirectType(redirect.type);
        setEnabled(redirect.enabled);
        setErrors({});
        setShowAddModal(true);
    };

    const handleSave = () => {
        // Validate
        const newErrors: Record<string, string> = {};

        if (!source.trim()) {
            newErrors.source = 'Source path is required';
        } else if (!source.startsWith('/')) {
            newErrors.source = 'Source path must start with /';
        }

        if (!target.trim()) {
            newErrors.target = 'Target URL is required';
        }

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        if (editingId) {
            // Update existing
            router.put(`/domains/redirects/${editingId}`, {
                source,
                target,
                type: redirectType,
                enabled,
            });
        } else {
            // Create new
            router.post('/domains/redirects', {
                source,
                target,
                type: redirectType,
                enabled,
            });
        }

        setShowAddModal(false);
    };

    const handleToggle = (id: number, currentEnabled: boolean) => {
        router.post(`/domains/redirects/${id}/toggle`, {
            enabled: !currentEnabled,
        });
    };

    const handleDelete = async (id: number) => {
        const confirmed = await confirm({
            title: 'Delete Redirect Rule',
            description: 'Are you sure you want to delete this redirect rule?',
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/domains/redirects/${id}`);
        }
    };

    const handleTest = (redirect: RedirectRule) => {
        // In a real app, this would open a test window or show test results
        const testUrl = `${window.location.origin}${redirect.source}`;
        window.open(testUrl, '_blank');
    };

    const formatDate = (dateString: string) => {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    };

    return (
        <AppLayout
            title="Redirect Rules"
            breadcrumbs={[
                { label: 'Domains', href: '/domains' },
                { label: 'Redirects' },
            ]}
        >
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">URL Redirect Rules</h1>
                    <p className="text-foreground-muted">Configure URL redirects and rewrites</p>
                </div>
                <Button onClick={handleOpenAdd}>
                    <Plus className="mr-2 h-4 w-4" />
                    Add Redirect
                </Button>
            </div>

            {/* Stats */}
            <div className="mb-6 grid gap-4 md:grid-cols-4">
                <Card>
                    <CardContent className="p-4">
                        <div className="text-2xl font-bold text-foreground">
                            {redirects.length}
                        </div>
                        <div className="text-sm text-foreground-muted">Total Rules</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="text-2xl font-bold text-foreground">
                            {redirects.filter(r => r.enabled).length}
                        </div>
                        <div className="text-sm text-foreground-muted">Active Rules</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="text-2xl font-bold text-foreground">
                            {redirects.reduce((sum, r) => sum + r.hits, 0).toLocaleString()}
                        </div>
                        <div className="text-sm text-foreground-muted">Total Hits</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="text-2xl font-bold text-foreground">
                            {redirects.filter(r => r.type === '301').length}
                        </div>
                        <div className="text-sm text-foreground-muted">Permanent (301)</div>
                    </CardContent>
                </Card>
            </div>

            {/* Redirect Rules List */}
            <Card>
                <CardHeader>
                    <CardTitle>Redirect Rules</CardTitle>
                </CardHeader>
                <CardContent>
                    {redirects.length === 0 ? (
                        <div className="py-12 text-center">
                            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                                <Route className="h-8 w-8 text-foreground-muted" />
                            </div>
                            <h3 className="mt-4 text-lg font-medium text-foreground">No redirect rules yet</h3>
                            <p className="mt-2 text-foreground-muted">
                                Create your first redirect rule to manage URL redirects.
                            </p>
                            <Button onClick={handleOpenAdd} className="mt-6">
                                <Plus className="mr-2 h-4 w-4" />
                                Add Redirect
                            </Button>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {redirects.map((redirect) => (
                                <div
                                    key={redirect.id}
                                    className="rounded-lg border border-border bg-background-secondary p-4 transition-colors hover:bg-background-tertiary"
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-3 mb-2">
                                                <div className="flex items-center gap-2 font-mono text-sm">
                                                    <code className="rounded bg-background-tertiary px-2 py-1 text-foreground">
                                                        {redirect.source}
                                                    </code>
                                                    <ArrowRight className="h-4 w-4 text-foreground-muted" />
                                                    <code className="rounded bg-background-tertiary px-2 py-1 text-foreground">
                                                        {redirect.target}
                                                    </code>
                                                </div>
                                                <Badge variant={redirect.type === '301' ? 'default' : 'info'}>
                                                    {redirect.type}
                                                </Badge>
                                                <Badge variant={redirect.enabled ? 'success' : 'default'}>
                                                    {redirect.enabled ? 'Enabled' : 'Disabled'}
                                                </Badge>
                                            </div>
                                            <div className="flex items-center gap-4 text-sm text-foreground-muted">
                                                <span>{redirect.hits.toLocaleString()} hits</span>
                                                <span>•</span>
                                                <span>Created {formatDate(redirect.created_at)}</span>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleTest(redirect)}
                                                title="Test redirect"
                                            >
                                                <ExternalLink className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleToggle(redirect.id, redirect.enabled)}
                                                title={redirect.enabled ? 'Disable' : 'Enable'}
                                            >
                                                {redirect.enabled ? (
                                                    <X className="h-4 w-4 text-warning" />
                                                ) : (
                                                    <Check className="h-4 w-4 text-primary" />
                                                )}
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleOpenEdit(redirect)}
                                            >
                                                <Edit className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleDelete(redirect.id)}
                                            >
                                                <Trash2 className="h-4 w-4 text-danger" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Info Card */}
            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Redirect Types</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="rounded-lg border border-border bg-background p-4">
                            <div className="mb-2 flex items-center gap-2">
                                <Badge variant="default">301</Badge>
                                <h4 className="font-medium text-foreground">Permanent Redirect</h4>
                            </div>
                            <p className="text-sm text-foreground-muted">
                                Tells browsers and search engines that the page has permanently moved to a new location.
                                Use for SEO-friendly permanent URL changes.
                            </p>
                        </div>
                        <div className="rounded-lg border border-border bg-background p-4">
                            <div className="mb-2 flex items-center gap-2">
                                <Badge variant="info">302</Badge>
                                <h4 className="font-medium text-foreground">Temporary Redirect</h4>
                            </div>
                            <p className="text-sm text-foreground-muted">
                                Indicates a temporary move. Browsers won't cache it permanently.
                                Use for A/B testing, maintenance pages, or temporary promotions.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Add/Edit Modal */}
            {showAddModal && (
                <Modal
                    isOpen={showAddModal}
                    onClose={() => setShowAddModal(false)}
                    title={editingId ? 'Edit Redirect Rule' : 'Add Redirect Rule'}
                >
                    <div className="space-y-4">
                        <Input
                            label="Source Path"
                            placeholder="/old-page"
                            value={source}
                            onChange={(e) => {
                                setSource(e.target.value);
                                if (errors.source) {
                                    setErrors({ ...errors, source: '' });
                                }
                            }}
                            error={errors.source}
                            hint="The path to redirect from (e.g., /old-page or /legacy/*)"
                        />

                        <Input
                            label="Target URL"
                            placeholder="https://example.com/new-page"
                            value={target}
                            onChange={(e) => {
                                setTarget(e.target.value);
                                if (errors.target) {
                                    setErrors({ ...errors, target: '' });
                                }
                            }}
                            error={errors.target}
                            hint="Where to redirect (can be relative /path or absolute URL)"
                        />

                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-foreground">
                                Redirect Type
                            </label>
                            <div className="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    onClick={() => setRedirectType('301')}
                                    className={`rounded-md border px-4 py-2 text-sm font-medium transition-colors ${
                                        redirectType === '301'
                                            ? 'border-primary bg-primary/10 text-primary'
                                            : 'border-border bg-background-secondary text-foreground hover:bg-background-tertiary'
                                    }`}
                                >
                                    301 Permanent
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setRedirectType('302')}
                                    className={`rounded-md border px-4 py-2 text-sm font-medium transition-colors ${
                                        redirectType === '302'
                                            ? 'border-primary bg-primary/10 text-primary'
                                            : 'border-border bg-background-secondary text-foreground hover:bg-background-tertiary'
                                    }`}
                                >
                                    302 Temporary
                                </button>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="enabled"
                                checked={enabled}
                                onChange={(e) => setEnabled(e.target.checked)}
                                className="h-4 w-4 rounded border-border bg-background-secondary text-primary focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background"
                            />
                            <label htmlFor="enabled" className="text-sm text-foreground">
                                Enable redirect immediately
                            </label>
                        </div>
                    </div>

                    <ModalFooter>
                        <Button variant="secondary" onClick={() => setShowAddModal(false)}>
                            Cancel
                        </Button>
                        <Button onClick={handleSave}>
                            {editingId ? 'Update' : 'Create'} Redirect
                        </Button>
                    </ModalFooter>
                </Modal>
            )}
        </AppLayout>
    );
}
