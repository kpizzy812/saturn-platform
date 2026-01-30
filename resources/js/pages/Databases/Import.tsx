import { AppLayout } from '@/components/layout';
import { Button, Card } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
import * as Icons from 'lucide-react';
import { cn } from '@/lib/utils';
import type { StandaloneDatabase } from '@/types';

type ExportFormat = 'sql' | 'csv' | 'json';

interface Props {
    database: StandaloneDatabase;
}

// CLI import commands by database type
function getImportCommands(database: StandaloneDatabase): { label: string; command: string }[] {
    const containerName = database.uuid;
    switch (database.database_type) {
        case 'postgresql':
            return [
                { label: 'Import SQL dump', command: `docker exec -i ${containerName} psql -U ${database.postgres_user || 'postgres'} -d ${database.postgres_db || 'postgres'} < dump.sql` },
                { label: 'Import compressed dump', command: `gunzip -c dump.sql.gz | docker exec -i ${containerName} psql -U ${database.postgres_user || 'postgres'} -d ${database.postgres_db || 'postgres'}` },
                { label: 'Restore custom format', command: `docker exec -i ${containerName} pg_restore -U ${database.postgres_user || 'postgres'} -d ${database.postgres_db || 'postgres'} < dump.custom` },
            ];
        case 'mysql':
        case 'mariadb':
            return [
                { label: 'Import SQL dump', command: `docker exec -i ${containerName} mysql -u root -p${database.mysql_root_password ? '***' : ''} ${database.mysql_database || 'mysql'} < dump.sql` },
                { label: 'Import compressed dump', command: `gunzip -c dump.sql.gz | docker exec -i ${containerName} mysql -u root -p${database.mysql_root_password ? '***' : ''} ${database.mysql_database || 'mysql'}` },
            ];
        case 'mongodb':
            return [
                { label: 'Import BSON dump', command: `docker exec -i ${containerName} mongorestore --db ${database.mongo_initdb_database || 'admin'} /dump/` },
                { label: 'Import JSON', command: `docker exec -i ${containerName} mongoimport --db ${database.mongo_initdb_database || 'admin'} --collection <name> --file data.json` },
            ];
        case 'redis':
        case 'keydb':
        case 'dragonfly':
            return [
                { label: 'Restore RDB file', command: `docker cp dump.rdb ${containerName}:/data/dump.rdb && docker restart ${containerName}` },
            ];
        case 'clickhouse':
            return [
                { label: 'Import CSV', command: `docker exec -i ${containerName} clickhouse-client --query="INSERT INTO <table> FORMAT CSV" < data.csv` },
                { label: 'Import SQL dump', command: `docker exec -i ${containerName} clickhouse-client < dump.sql` },
            ];
        default:
            return [
                { label: 'Import SQL dump', command: `docker exec -i ${containerName} < dump.sql` },
            ];
    }
}

export default function DatabaseImport({ database }: Props) {
    const [activeTab, setActiveTab] = useState<'import' | 'export'>('import');
    const [exportFormat, setExportFormat] = useState<ExportFormat>('sql');
    const [isProcessing, setIsProcessing] = useState(false);
    const [exportOptions, setExportOptions] = useState({
        includeData: true,
        includeStructure: true,
        compress: false,
    });
    const [copiedIndex, setCopiedIndex] = useState<number | null>(null);
    const { addToast } = useToast();

    const importCommands = getImportCommands(database);

    const copyToClipboard = (text: string, index: number) => {
        navigator.clipboard.writeText(text);
        setCopiedIndex(index);
        setTimeout(() => setCopiedIndex(null), 2000);
    };

    const handleExport = async () => {
        setIsProcessing(true);
        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';

        try {
            const response = await fetch(`/databases/${database.uuid}/export`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
                body: JSON.stringify({
                    format: exportFormat,
                    includeData: exportOptions.includeData,
                    includeStructure: exportOptions.includeStructure,
                    compress: exportOptions.compress,
                }),
            });

            const data = await response.json().catch(() => ({}));

            if (response.ok && data.success) {
                addToast('success', data.message || 'Export initiated. Check the Backups tab for progress.');
            } else {
                addToast('error', data.error || 'Failed to initiate export.');
            }
        } catch {
            addToast('error', 'Failed to connect to the server.');
        } finally {
            setIsProcessing(false);
        }
    };

    return (
        <AppLayout
            title={`Import/Export - ${database.name}`}
            breadcrumbs={[
                { label: 'Databases', href: '/databases' },
                { label: database.name, href: `/databases/${database.uuid}` },
                { label: 'Import/Export' },
            ]}
        >
            <div className="mx-auto max-w-4xl space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Import & Export</h1>
                    <p className="text-foreground-muted">Import data or export your database</p>
                </div>

                {/* Tabs */}
                <div className="flex gap-2 border-b border-border">
                    <button
                        onClick={() => setActiveTab('import')}
                        className={cn(
                            'px-4 py-2 text-sm font-medium transition-colors',
                            activeTab === 'import'
                                ? 'border-b-2 border-primary text-primary'
                                : 'text-foreground-muted hover:text-foreground'
                        )}
                    >
                        <div className="flex items-center gap-2">
                            <Icons.Upload className="h-4 w-4" />
                            Import
                        </div>
                    </button>
                    <button
                        onClick={() => setActiveTab('export')}
                        className={cn(
                            'px-4 py-2 text-sm font-medium transition-colors',
                            activeTab === 'export'
                                ? 'border-b-2 border-primary text-primary'
                                : 'text-foreground-muted hover:text-foreground'
                        )}
                    >
                        <div className="flex items-center gap-2">
                            <Icons.Download className="h-4 w-4" />
                            Export
                        </div>
                    </button>
                </div>

                {/* Import Tab */}
                {activeTab === 'import' && (
                    <div className="space-y-6">
                        {/* CLI Import Instructions */}
                        <Card className="p-6">
                            <div className="mb-4 flex items-center gap-3">
                                <Icons.Terminal className="h-6 w-6 text-primary" />
                                <h2 className="text-lg font-semibold text-foreground">
                                    Import via CLI
                                </h2>
                            </div>
                            <p className="mb-6 text-sm text-foreground-muted">
                                Database import is performed via CLI commands on the server where the database container is running.
                                SSH into your server and use the commands below.
                            </p>

                            <div className="space-y-4">
                                {importCommands.map((cmd, index) => (
                                    <div key={index} className="rounded-lg border border-border bg-background-secondary p-4">
                                        <div className="mb-2 flex items-center justify-between">
                                            <span className="text-sm font-medium text-foreground">{cmd.label}</span>
                                            <button
                                                type="button"
                                                onClick={() => copyToClipboard(cmd.command, index)}
                                                className="flex items-center gap-1 text-xs text-foreground-muted hover:text-foreground"
                                            >
                                                {copiedIndex === index ? (
                                                    <>
                                                        <Icons.Check className="h-3.5 w-3.5 text-success" />
                                                        Copied
                                                    </>
                                                ) : (
                                                    <>
                                                        <Icons.Copy className="h-3.5 w-3.5" />
                                                        Copy
                                                    </>
                                                )}
                                            </button>
                                        </div>
                                        <pre className="overflow-x-auto rounded bg-background p-3 text-xs text-foreground-muted">
                                            <code>{cmd.command}</code>
                                        </pre>
                                    </div>
                                ))}
                            </div>
                        </Card>

                        {/* Tips */}
                        <Card className="border-info/30 bg-info/5 p-4">
                            <div className="flex gap-3">
                                <Icons.Info className="h-5 w-5 flex-shrink-0 text-info" />
                                <div>
                                    <h3 className="mb-1 font-semibold text-info">Tips</h3>
                                    <ul className="space-y-1 text-sm text-foreground-muted">
                                        <li>Create a backup before importing to prevent data loss</li>
                                        <li>For large imports, consider using <code className="rounded bg-background px-1 text-xs">screen</code> or <code className="rounded bg-background px-1 text-xs">tmux</code> to prevent timeout</li>
                                        <li>Replace placeholder values in the commands with your actual data</li>
                                    </ul>
                                </div>
                            </div>
                        </Card>

                        <Link href={`/databases/${database.uuid}`}>
                            <Button variant="secondary">
                                <Icons.ArrowLeft className="mr-2 h-4 w-4" />
                                Back to Database
                            </Button>
                        </Link>
                    </div>
                )}

                {/* Export Tab */}
                {activeTab === 'export' && (
                    <div className="space-y-6">
                        {/* Export Format */}
                        <Card className="p-6">
                            <h2 className="mb-4 text-lg font-semibold text-foreground">
                                Export Format
                            </h2>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <button
                                    type="button"
                                    onClick={() => setExportFormat('sql')}
                                    className={cn(
                                        'rounded-lg border-2 p-4 text-center transition-all',
                                        exportFormat === 'sql'
                                            ? 'border-primary bg-primary/10'
                                            : 'border-border hover:border-border/80'
                                    )}
                                >
                                    <Icons.Database className="mx-auto mb-2 h-6 w-6 text-primary" />
                                    <h3 className="mb-1 text-sm font-semibold text-foreground">SQL</h3>
                                    <p className="text-xs text-foreground-muted">
                                        Standard SQL dump
                                    </p>
                                </button>

                                <button
                                    type="button"
                                    onClick={() => setExportFormat('csv')}
                                    className={cn(
                                        'rounded-lg border-2 p-4 text-center transition-all',
                                        exportFormat === 'csv'
                                            ? 'border-primary bg-primary/10'
                                            : 'border-border hover:border-border/80'
                                    )}
                                >
                                    <Icons.FileText className="mx-auto mb-2 h-6 w-6 text-primary" />
                                    <h3 className="mb-1 text-sm font-semibold text-foreground">CSV</h3>
                                    <p className="text-xs text-foreground-muted">
                                        Comma-separated values
                                    </p>
                                </button>

                                <button
                                    type="button"
                                    onClick={() => setExportFormat('json')}
                                    className={cn(
                                        'rounded-lg border-2 p-4 text-center transition-all',
                                        exportFormat === 'json'
                                            ? 'border-primary bg-primary/10'
                                            : 'border-border hover:border-border/80'
                                    )}
                                >
                                    <Icons.Braces className="mx-auto mb-2 h-6 w-6 text-primary" />
                                    <h3 className="mb-1 text-sm font-semibold text-foreground">JSON</h3>
                                    <p className="text-xs text-foreground-muted">
                                        JavaScript Object Notation
                                    </p>
                                </button>
                            </div>
                        </Card>

                        {/* Export Options */}
                        {exportFormat === 'sql' && (
                            <Card className="p-6">
                                <h2 className="mb-4 text-lg font-semibold text-foreground">
                                    Export Options
                                </h2>
                                <div className="space-y-3">
                                    <label className="flex items-center gap-3">
                                        <input
                                            type="checkbox"
                                            checked={exportOptions.includeStructure}
                                            onChange={(e) =>
                                                setExportOptions({
                                                    ...exportOptions,
                                                    includeStructure: e.target.checked,
                                                })
                                            }
                                            className="h-4 w-4 rounded border-border text-primary focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background"
                                        />
                                        <div>
                                            <div className="text-sm font-medium text-foreground">
                                                Include Structure
                                            </div>
                                            <div className="text-xs text-foreground-muted">
                                                Export table schemas and indexes
                                            </div>
                                        </div>
                                    </label>

                                    <label className="flex items-center gap-3">
                                        <input
                                            type="checkbox"
                                            checked={exportOptions.includeData}
                                            onChange={(e) =>
                                                setExportOptions({
                                                    ...exportOptions,
                                                    includeData: e.target.checked,
                                                })
                                            }
                                            className="h-4 w-4 rounded border-border text-primary focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background"
                                        />
                                        <div>
                                            <div className="text-sm font-medium text-foreground">
                                                Include Data
                                            </div>
                                            <div className="text-xs text-foreground-muted">
                                                Export all table rows
                                            </div>
                                        </div>
                                    </label>

                                    <label className="flex items-center gap-3">
                                        <input
                                            type="checkbox"
                                            checked={exportOptions.compress}
                                            onChange={(e) =>
                                                setExportOptions({
                                                    ...exportOptions,
                                                    compress: e.target.checked,
                                                })
                                            }
                                            className="h-4 w-4 rounded border-border text-primary focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background"
                                        />
                                        <div>
                                            <div className="text-sm font-medium text-foreground">
                                                Compress (GZIP)
                                            </div>
                                            <div className="text-xs text-foreground-muted">
                                                Reduce file size with compression
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </Card>
                        )}

                        {/* Export Info */}
                        <Card className="p-6">
                            <h2 className="mb-4 text-lg font-semibold text-foreground">
                                Database Information
                            </h2>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <div className="mb-1 text-xs text-foreground-muted">
                                        Database Name
                                    </div>
                                    <div className="font-medium text-foreground">
                                        {database.name}
                                    </div>
                                </div>
                                <div>
                                    <div className="mb-1 text-xs text-foreground-muted">
                                        Database Type
                                    </div>
                                    <div className="font-medium text-foreground">
                                        {database.database_type?.toUpperCase() || 'Unknown'}
                                    </div>
                                </div>
                            </div>
                        </Card>

                        {/* Export Button */}
                        <Button
                            onClick={handleExport}
                            disabled={
                                isProcessing ||
                                (exportFormat === 'sql' &&
                                    !exportOptions.includeData &&
                                    !exportOptions.includeStructure)
                            }
                            className="w-full"
                        >
                            {isProcessing ? (
                                <>
                                    <Icons.Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Exporting...
                                </>
                            ) : (
                                <>
                                    <Icons.Download className="mr-2 h-4 w-4" />
                                    Export Database
                                </>
                            )}
                        </Button>
                    </div>
                )}

            </div>
        </AppLayout>
    );
}
