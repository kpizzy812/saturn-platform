import { useState, useEffect, useCallback } from 'react';
import { Button } from '@/components/ui';
import { Search, RefreshCw, Layers } from 'lucide-react';
import { useToast } from '@/components/ui/Toast';
import type { SelectedService } from '../../types';

interface Extension {
    name: string;
    version: string;
    description: string;
    enabled: boolean;
}

interface DatabaseExtensionsTabProps {
    service: SelectedService;
}

export function DatabaseExtensionsTab({ service }: DatabaseExtensionsTabProps) {
    const { addToast } = useToast();
    const [searchQuery, setSearchQuery] = useState('');
    const [extensions, setExtensions] = useState<Extension[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [togglingExtension, setTogglingExtension] = useState<string | null>(null);

    const fetchExtensions = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const response = await fetch(`/_internal/databases/${service.uuid}/extensions`);
            const data = await response.json();
            if (data.available && data.extensions) {
                setExtensions(data.extensions);
            } else {
                setError(data.error || 'Unable to fetch extensions');
                setExtensions([]);
            }
        } catch {
            setError('Failed to fetch extensions');
            setExtensions([]);
        } finally {
            setIsLoading(false);
        }
    }, [service.uuid]);

    useEffect(() => {
        fetchExtensions();
    }, [fetchExtensions]);

    const handleToggleExtension = async (ext: Extension) => {
        setTogglingExtension(ext.name);
        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(`/_internal/databases/${service.uuid}/extensions/toggle`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    extension: ext.name,
                    enable: !ext.enabled,
                }),
            });
            if (!response.ok) {
                addToast('error', 'Failed', `Server returned ${response.status}`);
                return;
            }
            const data = await response.json();
            if (data.success) {
                addToast('success', ext.enabled ? 'Extension disabled' : 'Extension enabled', data.message);
                await fetchExtensions();
            } else {
                addToast('error', 'Failed', data.error || 'Failed to toggle extension');
            }
        } catch {
            addToast('error', 'Error', 'Failed to toggle extension');
        } finally {
            setTogglingExtension(null);
        }
    };

    const filteredExtensions = extensions.filter(ext =>
        ext.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        ext.description.toLowerCase().includes(searchQuery.toLowerCase())
    );

    const enabledExtensions = filteredExtensions.filter(ext => ext.enabled);
    const availableExtensions = filteredExtensions.filter(ext => !ext.enabled);

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-12">
                <RefreshCw className="h-6 w-6 animate-spin text-foreground-muted" />
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex flex-col items-center justify-center py-12 text-center">
                <Layers className="mb-4 h-12 w-12 text-foreground-muted opacity-50" />
                <p className="text-foreground-muted">Extensions unavailable</p>
                <p className="mt-1 text-sm text-foreground-subtle">{error}</p>
                <Button size="sm" variant="secondary" className="mt-4" onClick={fetchExtensions}>
                    <RefreshCw className="mr-2 h-3 w-3" />
                    Retry
                </Button>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Search & Refresh */}
            <div className="flex items-center gap-2">
                <div className="relative flex-1">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                    <input
                        type="text"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        placeholder="Search extensions..."
                        className="w-full rounded-lg border border-border bg-background-secondary py-2 pl-10 pr-4 text-sm text-foreground placeholder:text-foreground-muted focus:border-primary focus:outline-none"
                    />
                </div>
                <Button size="sm" variant="secondary" onClick={fetchExtensions}>
                    <RefreshCw className="h-3 w-3" />
                </Button>
            </div>

            {/* Enabled Extensions */}
            {enabledExtensions.length > 0 && (
                <div>
                    <h3 className="mb-3 flex items-center gap-2 text-sm font-medium text-foreground">
                        <span className="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500/20">
                            <span className="h-2 w-2 rounded-full bg-emerald-500" />
                        </span>
                        Enabled ({enabledExtensions.length})
                    </h3>
                    <div className="space-y-2">
                        {enabledExtensions.map((ext) => (
                            <div
                                key={ext.name}
                                className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-3"
                            >
                                <div className="flex-1">
                                    <div className="flex items-center gap-2">
                                        <p className="text-sm font-medium text-foreground">{ext.name}</p>
                                        <span className="text-xs text-foreground-muted">v{ext.version}</span>
                                    </div>
                                    {ext.description && (
                                        <p className="mt-0.5 text-xs text-foreground-muted">{ext.description}</p>
                                    )}
                                </div>
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    className="text-red-500 hover:text-red-400"
                                    onClick={() => handleToggleExtension(ext)}
                                    disabled={togglingExtension === ext.name}
                                >
                                    {togglingExtension === ext.name ? (
                                        <RefreshCw className="mr-1 h-3 w-3 animate-spin" />
                                    ) : null}
                                    Disable
                                </Button>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Available Extensions */}
            {availableExtensions.length > 0 && (
                <div>
                    <h3 className="mb-3 text-sm font-medium text-foreground">
                        Available ({availableExtensions.length})
                    </h3>
                    <div className="space-y-2">
                        {availableExtensions.map((ext) => (
                            <div
                                key={ext.name}
                                className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-3"
                            >
                                <div className="flex-1">
                                    <div className="flex items-center gap-2">
                                        <p className="text-sm font-medium text-foreground">{ext.name}</p>
                                        <span className="text-xs text-foreground-muted">v{ext.version}</span>
                                    </div>
                                    {ext.description && (
                                        <p className="mt-0.5 text-xs text-foreground-muted">{ext.description}</p>
                                    )}
                                </div>
                                <Button
                                    size="sm"
                                    onClick={() => handleToggleExtension(ext)}
                                    disabled={togglingExtension === ext.name}
                                >
                                    {togglingExtension === ext.name ? (
                                        <RefreshCw className="mr-1 h-3 w-3 animate-spin" />
                                    ) : null}
                                    Enable
                                </Button>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {extensions.length === 0 && !error && (
                <div className="rounded-lg border border-dashed border-border p-6 text-center">
                    <Layers className="mx-auto h-8 w-8 text-foreground-subtle" />
                    <p className="mt-2 text-sm text-foreground-muted">No extensions found</p>
                </div>
            )}

            {/* Info */}
            <div className="rounded-lg border border-yellow-500/20 bg-yellow-500/5 p-4">
                <p className="text-sm text-yellow-500">
                    <strong>Note:</strong> Enabling or disabling extensions may require a database restart.
                    Some extensions may affect performance.
                </p>
            </div>
        </div>
    );
}
