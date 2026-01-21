import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Badge, Input } from '@/components/ui';
import { ArrowLeft, Search, Database, Package, CheckCircle2, Circle } from 'lucide-react';
import type { StandaloneDatabase } from '@/types';

interface Props {
    database: StandaloneDatabase;
}

interface Extension {
    id: number;
    name: string;
    description: string;
    version: string;
    enabled: boolean;
    popular: boolean;
}

// Mock extensions data for PostgreSQL
const mockExtensions: Extension[] = [
    {
        id: 1,
        name: 'pg_stat_statements',
        description: 'Track execution statistics of all SQL statements executed',
        version: '1.10',
        enabled: true,
        popular: true,
    },
    {
        id: 2,
        name: 'pgcrypto',
        description: 'Cryptographic functions for PostgreSQL',
        version: '1.3',
        enabled: true,
        popular: true,
    },
    {
        id: 3,
        name: 'uuid-ossp',
        description: 'Generate universally unique identifiers (UUIDs)',
        version: '1.1',
        enabled: true,
        popular: true,
    },
    {
        id: 4,
        name: 'postgis',
        description: 'PostGIS geometry and geography spatial types and functions',
        version: '3.4.0',
        enabled: false,
        popular: true,
    },
    {
        id: 5,
        name: 'pgvector',
        description: 'Vector similarity search for machine learning and AI applications',
        version: '0.5.1',
        enabled: false,
        popular: true,
    },
    {
        id: 6,
        name: 'hstore',
        description: 'Data type for storing sets of key/value pairs',
        version: '1.8',
        enabled: true,
        popular: false,
    },
    {
        id: 7,
        name: 'pg_trgm',
        description: 'Text similarity measurement and index searching based on trigrams',
        version: '1.6',
        enabled: true,
        popular: false,
    },
    {
        id: 8,
        name: 'btree_gin',
        description: 'Support for indexing common datatypes in GIN',
        version: '1.3',
        enabled: false,
        popular: false,
    },
    {
        id: 9,
        name: 'btree_gist',
        description: 'Support for indexing common datatypes in GiST',
        version: '1.7',
        enabled: false,
        popular: false,
    },
    {
        id: 10,
        name: 'citext',
        description: 'Data type for case-insensitive character strings',
        version: '1.6',
        enabled: false,
        popular: false,
    },
    {
        id: 11,
        name: 'fuzzystrmatch',
        description: 'Determine similarities and distance between strings',
        version: '1.1',
        enabled: false,
        popular: false,
    },
    {
        id: 12,
        name: 'ltree',
        description: 'Data type for hierarchical tree-like structures',
        version: '1.2',
        enabled: false,
        popular: false,
    },
    {
        id: 13,
        name: 'pg_buffercache',
        description: 'Examine the shared buffer cache',
        version: '1.3',
        enabled: false,
        popular: false,
    },
    {
        id: 14,
        name: 'tablefunc',
        description: 'Functions that manipulate whole tables, including crosstab',
        version: '1.0',
        enabled: false,
        popular: false,
    },
    {
        id: 15,
        name: 'unaccent',
        description: 'Text search dictionary that removes accents',
        version: '1.1',
        enabled: false,
        popular: false,
    },
];

export default function DatabaseExtensions({ database }: Props) {
    const [extensions, setExtensions] = useState<Extension[]>(mockExtensions);
    const [searchQuery, setSearchQuery] = useState('');

    const filteredExtensions = extensions.filter((ext) =>
        ext.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        ext.description.toLowerCase().includes(searchQuery.toLowerCase())
    );

    const popularExtensions = filteredExtensions.filter((ext) => ext.popular);
    const otherExtensions = filteredExtensions.filter((ext) => !ext.popular);

    const handleToggle = (extensionId: number) => {
        const extension = extensions.find((ext) => ext.id === extensionId);
        if (!extension) return;

        const action = extension.enabled ? 'disable' : 'enable';
        router.post(`/databases/${database.uuid}/extensions/${extensionId}/${action}`, {}, {
            onSuccess: () => {
                setExtensions((prev) =>
                    prev.map((ext) =>
                        ext.id === extensionId ? { ...ext, enabled: !ext.enabled } : ext
                    )
                );
            },
        });
    };

    const enabledCount = extensions.filter((ext) => ext.enabled).length;

    return (
        <AppLayout
            title={`${database.name} - Extensions`}
            breadcrumbs={[
                { label: 'Databases', href: '/databases' },
                { label: database.name, href: `/databases/${database.uuid}` },
                { label: 'Extensions' }
            ]}
        >
            {/* Back Button */}
            <Link
                href={`/databases/${database.uuid}`}
                className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
            >
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back to {database.name}
            </Link>

            {/* Header */}
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-foreground">PostgreSQL Extensions</h1>
                <p className="text-foreground-muted">
                    Extend PostgreSQL with additional functionality - {enabledCount} of {extensions.length} enabled
                </p>
            </div>

            {/* Search Bar */}
            <div className="mb-6">
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                    <Input
                        type="text"
                        placeholder="Search extensions..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="pl-10"
                    />
                </div>
            </div>

            {/* Popular Extensions */}
            {popularExtensions.length > 0 && (
                <div className="mb-8">
                    <div className="mb-4 flex items-center gap-2">
                        <Package className="h-5 w-5 text-foreground-muted" />
                        <h2 className="text-lg font-medium text-foreground">Popular Extensions</h2>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        {popularExtensions.map((extension) => (
                            <ExtensionCard
                                key={extension.id}
                                extension={extension}
                                onToggle={() => handleToggle(extension.id)}
                            />
                        ))}
                    </div>
                </div>
            )}

            {/* All Extensions */}
            {otherExtensions.length > 0 && (
                <div>
                    <div className="mb-4 flex items-center gap-2">
                        <Database className="h-5 w-5 text-foreground-muted" />
                        <h2 className="text-lg font-medium text-foreground">All Extensions</h2>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        {otherExtensions.map((extension) => (
                            <ExtensionCard
                                key={extension.id}
                                extension={extension}
                                onToggle={() => handleToggle(extension.id)}
                            />
                        ))}
                    </div>
                </div>
            )}

            {filteredExtensions.length === 0 && (
                <Card>
                    <CardContent className="p-12 text-center">
                        <Search className="mx-auto h-12 w-12 text-foreground-subtle" />
                        <h3 className="mt-4 font-medium text-foreground">No extensions found</h3>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Try adjusting your search query
                        </p>
                    </CardContent>
                </Card>
            )}
        </AppLayout>
    );
}

function ExtensionCard({
    extension,
    onToggle,
}: {
    extension: Extension;
    onToggle: () => void;
}) {
    return (
        <Card>
            <CardContent className="p-4">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex-1">
                        <div className="flex items-center gap-2">
                            <h3 className="font-medium text-foreground">{extension.name}</h3>
                            <Badge variant="default">{extension.version}</Badge>
                            {extension.enabled ? (
                                <Badge variant="success">Enabled</Badge>
                            ) : (
                                <Badge variant="default">Disabled</Badge>
                            )}
                        </div>
                        <p className="mt-2 text-sm text-foreground-muted">{extension.description}</p>
                    </div>

                    <button
                        onClick={onToggle}
                        className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg transition-colors ${
                            extension.enabled
                                ? 'bg-green-500/10 text-green-500 hover:bg-green-500/20'
                                : 'bg-background-tertiary text-foreground-muted hover:bg-background-secondary'
                        }`}
                        title={extension.enabled ? 'Disable extension' : 'Enable extension'}
                    >
                        {extension.enabled ? (
                            <CheckCircle2 className="h-5 w-5" />
                        ) : (
                            <Circle className="h-5 w-5" />
                        )}
                    </button>
                </div>
            </CardContent>
        </Card>
    );
}
