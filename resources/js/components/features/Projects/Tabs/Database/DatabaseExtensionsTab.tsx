import { useState } from 'react';
import { Button } from '@/components/ui';
import { Search } from 'lucide-react';
import type { SelectedService } from '../../types';

interface DatabaseExtensionsTabProps {
    service: SelectedService;
}

export function DatabaseExtensionsTab({ service: _service }: DatabaseExtensionsTabProps) {
    const [searchQuery, setSearchQuery] = useState('');

    const extensions = [
        { name: 'pgvector', version: '0.5.1', description: 'Vector similarity search', enabled: true, popular: true },
        { name: 'postgis', version: '3.4.0', description: 'Spatial database extension', enabled: false, popular: true },
        { name: 'pg_trgm', version: '1.6', description: 'Text similarity with trigrams', enabled: true, popular: true },
        { name: 'uuid-ossp', version: '1.1', description: 'Generate universally unique identifiers', enabled: true, popular: false },
        { name: 'hstore', version: '1.8', description: 'Key-value store in PostgreSQL', enabled: false, popular: false },
        { name: 'pg_stat_statements', version: '1.10', description: 'Track query execution statistics', enabled: true, popular: true },
        { name: 'timescaledb', version: '2.13.0', description: 'Time-series database extension', enabled: false, popular: true },
        { name: 'citext', version: '1.6', description: 'Case-insensitive character string type', enabled: false, popular: false },
    ];

    const filteredExtensions = extensions.filter(ext =>
        ext.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        ext.description.toLowerCase().includes(searchQuery.toLowerCase())
    );

    const enabledExtensions = filteredExtensions.filter(ext => ext.enabled);
    const availableExtensions = filteredExtensions.filter(ext => !ext.enabled);

    return (
        <div className="space-y-6">
            {/* Search */}
            <div className="relative">
                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                <input
                    type="text"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    placeholder="Search extensions..."
                    className="w-full rounded-lg border border-border bg-background-secondary py-2 pl-10 pr-4 text-sm text-foreground placeholder:text-foreground-muted focus:border-primary focus:outline-none"
                />
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
                                        {ext.popular && (
                                            <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">
                                                Popular
                                            </span>
                                        )}
                                    </div>
                                    <p className="mt-0.5 text-xs text-foreground-muted">{ext.description}</p>
                                </div>
                                <Button size="sm" variant="secondary" className="text-red-500 hover:text-red-400">
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
                                        {ext.popular && (
                                            <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">
                                                Popular
                                            </span>
                                        )}
                                    </div>
                                    <p className="mt-0.5 text-xs text-foreground-muted">{ext.description}</p>
                                </div>
                                <Button size="sm">
                                    Enable
                                </Button>
                            </div>
                        ))}
                    </div>
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
