import { AppLayout } from '@/components/layout';
import { Button, Card } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { Link } from '@inertiajs/react';
import { useState, useRef, FormEvent } from 'react';
import * as Icons from 'lucide-react';
import { cn } from '@/lib/utils';
import type { StandaloneDatabase } from '@/types';

type ImportMethod = 'file' | 'url';
type ExportFormat = 'sql' | 'csv' | 'json';

interface Props {
    database: StandaloneDatabase;
}

export default function DatabaseImport({ database }: Props) {
    const [activeTab, setActiveTab] = useState<'import' | 'export'>('import');
    const [importMethod, setImportMethod] = useState<ImportMethod>('file');
    const [exportFormat, setExportFormat] = useState<ExportFormat>('sql');
    const [importUrl, setImportUrl] = useState('');
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [isProcessing, setIsProcessing] = useState(false);
    const [progress, setProgress] = useState(0);
    const [exportOptions, setExportOptions] = useState({
        includeData: true,
        includeStructure: true,
        compress: false,
    });
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { addToast } = useToast();

    const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setSelectedFile(file);
        }
    };

    const handleImport = async (e: FormEvent) => {
        e.preventDefault();
        setIsProcessing(true);
        setProgress(0);

        // Simulate progress
        const interval = setInterval(() => {
            setProgress((prev) => {
                if (prev >= 100) {
                    clearInterval(interval);
                    setTimeout(() => {
                        setIsProcessing(false);
                        setProgress(0);
                        setSelectedFile(null);
                        setImportUrl('');
                    }, 500);
                    return 100;
                }
                return prev + 10;
            });
        }, 300);
    };

    const handleExport = async () => {
        setIsProcessing(true);
        setProgress(0);

        // Simulate progress
        const interval = setInterval(() => {
            setProgress((prev) => {
                if (prev >= 100) {
                    clearInterval(interval);
                    setTimeout(() => {
                        setIsProcessing(false);
                        setProgress(0);
                        // Trigger download
                        addToast('success', 'Export complete! Download started.');
                    }, 500);
                    return 100;
                }
                return prev + 10;
            });
        }, 300);
    };

    const formatFileSize = (bytes: number) => {
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
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
                    <form onSubmit={handleImport} className="space-y-6">
                        {/* Import Method */}
                        <Card className="p-6">
                            <h2 className="mb-4 text-lg font-semibold text-foreground">
                                Import Method
                            </h2>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <button
                                    type="button"
                                    onClick={() => setImportMethod('file')}
                                    className={cn(
                                        'rounded-lg border-2 p-4 text-left transition-all',
                                        importMethod === 'file'
                                            ? 'border-primary bg-primary/10'
                                            : 'border-border hover:border-border/80'
                                    )}
                                >
                                    <Icons.FileUp className="mb-2 h-6 w-6 text-primary" />
                                    <h3 className="mb-1 font-semibold text-foreground">
                                        Upload File
                                    </h3>
                                    <p className="text-sm text-foreground-muted">
                                        Upload a SQL dump file from your computer
                                    </p>
                                </button>

                                <button
                                    type="button"
                                    onClick={() => setImportMethod('url')}
                                    className={cn(
                                        'rounded-lg border-2 p-4 text-left transition-all',
                                        importMethod === 'url'
                                            ? 'border-primary bg-primary/10'
                                            : 'border-border hover:border-border/80'
                                    )}
                                >
                                    <Icons.Link className="mb-2 h-6 w-6 text-primary" />
                                    <h3 className="mb-1 font-semibold text-foreground">
                                        Import from URL
                                    </h3>
                                    <p className="text-sm text-foreground-muted">
                                        Import from a publicly accessible URL
                                    </p>
                                </button>
                            </div>
                        </Card>

                        {/* File Upload */}
                        {importMethod === 'file' && (
                            <Card className="p-6">
                                <h2 className="mb-4 text-lg font-semibold text-foreground">
                                    Select File
                                </h2>
                                <div
                                    className={cn(
                                        'cursor-pointer rounded-lg border-2 border-dashed p-8 text-center transition-colors',
                                        selectedFile
                                            ? 'border-primary bg-primary/5'
                                            : 'border-border hover:border-border/80'
                                    )}
                                    onClick={() => fileInputRef.current?.click()}
                                >
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        accept=".sql,.gz,.zip"
                                        onChange={handleFileSelect}
                                        className="hidden"
                                    />
                                    {selectedFile ? (
                                        <div>
                                            <Icons.FileCheck className="mx-auto mb-3 h-12 w-12 text-primary" />
                                            <h3 className="mb-1 font-semibold text-foreground">
                                                {selectedFile.name}
                                            </h3>
                                            <p className="text-sm text-foreground-muted">
                                                {formatFileSize(selectedFile.size)}
                                            </p>
                                            <Button
                                                type="button"
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    setSelectedFile(null);
                                                }}
                                                variant="secondary"
                                                size="sm"
                                                className="mt-3"
                                            >
                                                Change File
                                            </Button>
                                        </div>
                                    ) : (
                                        <div>
                                            <Icons.Upload className="mx-auto mb-3 h-12 w-12 text-foreground-subtle" />
                                            <h3 className="mb-1 font-semibold text-foreground">
                                                Click to upload or drag and drop
                                            </h3>
                                            <p className="text-sm text-foreground-muted">
                                                SQL, GZ, or ZIP files (max 100MB)
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </Card>
                        )}

                        {/* URL Import */}
                        {importMethod === 'url' && (
                            <Card className="p-6">
                                <h2 className="mb-4 text-lg font-semibold text-foreground">
                                    Import URL
                                </h2>
                                <div>
                                    <label className="mb-2 block text-sm font-medium text-foreground">
                                        File URL
                                    </label>
                                    <input
                                        type="url"
                                        value={importUrl}
                                        onChange={(e) => setImportUrl(e.target.value)}
                                        placeholder="https://example.com/database-dump.sql"
                                        className="h-10 w-full rounded-lg border border-border bg-background px-3 text-sm text-foreground placeholder:text-foreground-subtle focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary"
                                        required
                                    />
                                    <p className="mt-2 text-xs text-foreground-subtle">
                                        The URL must be publicly accessible and point to a valid SQL dump file
                                    </p>
                                </div>
                            </Card>
                        )}

                        {/* Warning */}
                        <Card className="border-amber-500/50 bg-amber-500/10 p-4">
                            <div className="flex gap-3">
                                <Icons.AlertTriangle className="h-5 w-5 flex-shrink-0 text-amber-500" />
                                <div>
                                    <h3 className="mb-1 font-semibold text-amber-500">
                                        Import Warning
                                    </h3>
                                    <p className="text-sm text-amber-400">
                                        Importing data will modify your database. Make sure to backup your
                                        data before proceeding. This action cannot be undone.
                                    </p>
                                </div>
                            </div>
                        </Card>

                        {/* Submit */}
                        <div className="flex gap-3">
                            <Link href={`/databases/${database.uuid}`}>
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </Link>
                            <Button
                                type="submit"
                                disabled={
                                    isProcessing ||
                                    (importMethod === 'file' && !selectedFile) ||
                                    (importMethod === 'url' && !importUrl.trim())
                                }
                                className="flex-1"
                            >
                                {isProcessing ? (
                                    <>
                                        <Icons.Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Importing...
                                    </>
                                ) : (
                                    <>
                                        <Icons.Upload className="mr-2 h-4 w-4" />
                                        Start Import
                                    </>
                                )}
                            </Button>
                        </div>
                    </form>
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
                                        Database Type
                                    </div>
                                    <div className="font-medium text-foreground">
                                        {database.database_type.toUpperCase()}
                                    </div>
                                </div>
                                <div>
                                    <div className="mb-1 text-xs text-foreground-muted">
                                        Estimated Size
                                    </div>
                                    <div className="font-medium text-foreground">~45 MB</div>
                                </div>
                                <div>
                                    <div className="mb-1 text-xs text-foreground-muted">Tables</div>
                                    <div className="font-medium text-foreground">12 tables</div>
                                </div>
                                <div>
                                    <div className="mb-1 text-xs text-foreground-muted">
                                        Total Rows
                                    </div>
                                    <div className="font-medium text-foreground">~1.2M rows</div>
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

                {/* Progress Bar */}
                {isProcessing && (
                    <Card className="p-6">
                        <div className="mb-2 flex items-center justify-between text-sm">
                            <span className="font-medium text-foreground">
                                {activeTab === 'import' ? 'Importing...' : 'Exporting...'}
                            </span>
                            <span className="text-foreground-muted">{progress}%</span>
                        </div>
                        <div className="h-2 overflow-hidden rounded-full bg-background-tertiary">
                            <div
                                className="h-full bg-primary transition-all duration-300"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
