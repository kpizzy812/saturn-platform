import { Head, Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Badge, Input } from '@/components/ui';
import { Plus, Search, Lock, Unlock, Building2, FolderKanban, Layers } from 'lucide-react';
import { useState } from 'react';

interface SharedVariable {
    id: number;
    uuid: string;
    key: string;
    value: string;
    is_secret: boolean;
    scope: 'team' | 'project' | 'environment';
    scope_name: string;
    inherited_from?: string;
    created_at: string;
}

interface Props {
    variables: SharedVariable[];
    team: { id: number; name: string };
}

export default function SharedVariablesIndex({ variables = [], team }: Props) {
    const [search, setSearch] = useState('');
    const [activeTab, setActiveTab] = useState<'all' | 'team' | 'project' | 'environment'>('all');

    const filteredVariables = variables.filter(v => {
        const matchesSearch = v.key.toLowerCase().includes(search.toLowerCase());
        const matchesTab = activeTab === 'all' || v.scope === activeTab;
        return matchesSearch && matchesTab;
    });

    const getScopeIcon = (scope: string) => {
        switch (scope) {
            case 'team': return <Building2 className="h-4 w-4" />;
            case 'project': return <FolderKanban className="h-4 w-4" />;
            case 'environment': return <Layers className="h-4 w-4" />;
            default: return null;
        }
    };

    const getScopeBadgeVariant = (scope: string) => {
        switch (scope) {
            case 'team': return 'info';
            case 'project': return 'warning';
            case 'environment': return 'success';
            default: return 'default';
        }
    };

    const tabs = [
        { key: 'all', label: 'All Variables', count: variables.length },
        { key: 'team', label: 'Team', count: variables.filter(v => v.scope === 'team').length },
        { key: 'project', label: 'Project', count: variables.filter(v => v.scope === 'project').length },
        { key: 'environment', label: 'Environment', count: variables.filter(v => v.scope === 'environment').length },
    ];

    return (
        <AppLayout
            title="Shared Variables"
            breadcrumbs={[
                { label: 'Dashboard', href: '/new' },
                { label: 'Shared Variables' },
            ]}
        >
            <Head title="Shared Variables" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Shared Variables</h1>
                        <p className="text-foreground-muted mt-1">
                            Manage variables shared across team, projects, and environments
                        </p>
                    </div>
                    <Link href="/shared-variables/create">
                        <Button>
                            <Plus className="h-4 w-4 mr-2" />
                            Add Variable
                        </Button>
                    </Link>
                </div>

                {/* Tabs */}
                <div className="flex gap-2 border-b border-border pb-2">
                    {tabs.map(tab => (
                        <button
                            key={tab.key}
                            onClick={() => setActiveTab(tab.key as any)}
                            className={`px-4 py-2 rounded-t-lg text-sm font-medium transition-colors ${
                                activeTab === tab.key
                                    ? 'bg-background-secondary text-foreground border-b-2 border-primary'
                                    : 'text-foreground-muted hover:text-foreground'
                            }`}
                        >
                            {tab.label}
                            <span className="ml-2 text-xs bg-background-tertiary px-2 py-0.5 rounded-full">
                                {tab.count}
                            </span>
                        </button>
                    ))}
                </div>

                {/* Search */}
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-foreground-muted" />
                    <Input
                        placeholder="Search variables..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="pl-10"
                    />
                </div>

                {/* Variables List */}
                <Card>
                    <CardContent className="p-0">
                        {filteredVariables.length === 0 ? (
                            <div className="p-8 text-center text-foreground-muted">
                                <Layers className="h-12 w-12 mx-auto mb-4 opacity-50" />
                                <p>No variables found</p>
                                <Link href="/shared-variables/create" className="text-primary hover:underline mt-2 inline-block">
                                    Create your first variable
                                </Link>
                            </div>
                        ) : (
                            <div className="divide-y divide-border">
                                {filteredVariables.map(variable => (
                                    <div key={variable.id} className="p-4 hover:bg-background-secondary transition-colors">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                {variable.is_secret ? (
                                                    <Lock className="h-4 w-4 text-warning" />
                                                ) : (
                                                    <Unlock className="h-4 w-4 text-foreground-muted" />
                                                )}
                                                <div>
                                                    <div className="flex items-center gap-2">
                                                        <code className="font-mono font-medium">{variable.key}</code>
                                                        <Badge variant={getScopeBadgeVariant(variable.scope) as any}>
                                                            {getScopeIcon(variable.scope)}
                                                            <span className="ml-1 capitalize">{variable.scope}</span>
                                                        </Badge>
                                                    </div>
                                                    <p className="text-sm text-foreground-muted mt-1">
                                                        {variable.is_secret ? '••••••••' : variable.value}
                                                        {variable.inherited_from && (
                                                            <span className="ml-2 text-xs">
                                                                (inherited from {variable.inherited_from})
                                                            </span>
                                                        )}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <span className="text-xs text-foreground-muted">{variable.scope_name}</span>
                                                <Link href={`/shared-variables/${variable.uuid}`}>
                                                    <Button variant="ghost" size="sm">Edit</Button>
                                                </Link>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
