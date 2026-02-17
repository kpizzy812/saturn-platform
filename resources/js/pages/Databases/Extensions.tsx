import { useState, useEffect, useCallback } from 'react';
import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Badge, Input } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { ArrowLeft, Search, Database, Package, CheckCircle2, Circle, Loader2 } from 'lucide-react';
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

// Well-known popular extensions for highlighting
const POPULAR_EXTENSIONS = new Set([
    'pg_stat_statements', 'pgcrypto', 'uuid-ossp', 'postgis', 'pgvector',
    'hstore', 'pg_trgm', 'citext', 'ltree', 'unaccent',
]);

export default function DatabaseExtensions({ database }: Props) {
    const [extensions, setExtensions] = useState<Extension[]>([]);
    const [searchQuery, setSearchQuery] = useState('');
    const [isLoading, setIsLoading] = useState(true);
    const { addToast } = useToast();

    const fetchExtensions = useCallback(async () => {
        setIsLoading(true);
        try {
            const response = await fetch(`/_internal/databases/${database.uuid}/extensions`);
            const data = await response.json();
            if (data.available && data.extensions) {
                setExtensions(data.extensions.map((ext: { name: string; version: string; enabled: boolean; description: string }, index: number) => ({
                    id: index + 1,
                    name: ext.name,
                    version: ext.version || 'N/A',
                    enabled: ext.enabled,
                    description: ext.description || '',
                    popular: POPULAR_EXTENSIONS.has(ext.name),
                })));
            }
        } catch {
            addToast('error', 'Failed to load extensions');
        } finally {
            setIsLoading(false);
        }
    }, [database.uuid, addToast]);

    useEffect(() => {
        fetchExtensions();
    }, [fetchExtensions]);

    const filteredExtensions = extensions.filter((ext) =>
        ext.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        ext.description.toLowerCase().includes(searchQuery.toLowerCase())
    );

    const popularExtensions = filteredExtensions.filter((ext) => ext.popular);
    const otherExtensions = filteredExtensions.filter((ext) => !ext.popular);

    const [_togglingId, setTogglingId] = useState<number | null>(null);

    const handleToggle = async (extensionId: number) => {
        const extension = extensions.find((ext) => ext.id === extensionId);
        if (!extension) return;

        setTogglingId(extensionId);
        const enable = !extension.enabled;

        try {
            const response = await fetch(`/_internal/databases/${database.uuid}/extensions/toggle`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
                },
                body: JSON.stringify({ extension: extension.name, enable }),
            });
            const data = await response.json();
            if (data.success) {
                setExtensions((prev) =>
                    prev.map((ext) =>
                        ext.id === extensionId ? { ...ext, enabled: enable } : ext
                    )
                );
                addToast('success', `${extension.name} ${enable ? 'enabled' : 'disabled'}`);
            } else {
                addToast('error', data.error || `Failed to ${enable ? 'enable' : 'disable'} ${extension.name}`);
            }
        } catch {
            addToast('error', `Failed to ${enable ? 'enable' : 'disable'} ${extension.name}`);
        } finally {
            setTogglingId(null);
        }
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

            {isLoading && (
                <div className="flex items-center justify-center py-12">
                    <Loader2 className="h-6 w-6 animate-spin text-foreground-muted" />
                    <span className="ml-2 text-sm text-foreground-muted">Loading extensions...</span>
                </div>
            )}

            {/* Popular Extensions */}
            {!isLoading && popularExtensions.length > 0 && (
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
            {!isLoading && otherExtensions.length > 0 && (
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

            {!isLoading && filteredExtensions.length === 0 && (
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
