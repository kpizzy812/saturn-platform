import { AppLayout } from '@/components/layout';
import { Alert, Button, Card, Progress } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import { Link } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import * as Icons from 'lucide-react';
import { cn } from '@/lib/utils';
import type { DatabaseImportStatus, StandaloneDatabase } from '@/types';

type TabId = 'remote' | 'upload' | 'cli' | 'export';

interface Props {
    database: StandaloneDatabase;
}

function getCsrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
}

function getPlaceholder(dbType: string): string {
    switch (dbType) {
        case 'postgresql':
            return 'postgresql://user:password@host:5432/database';
        case 'mysql':
            return 'mysql://user:password@host:3306/database';
        case 'mariadb':
            return 'mariadb://user:password@host:3306/database';
        case 'mongodb':
            return 'mongodb://user:password@host:27017/database';
        case 'redis':
        case 'keydb':
        case 'dragonfly':
            return 'redis://user:password@host:6379';
        default:
            return 'scheme://user:password@host:port/database';
    }
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

function getExportFormatInfo(dbType: string): { format: string; description: string; extension: string } {
    switch (dbType) {
        case 'postgresql':
            return { format: 'pg_dump custom', description: 'PostgreSQL custom format — supports parallel restore and selective table recovery', extension: '.dmp' };
        case 'mysql':
            return { format: 'mysqldump', description: 'MySQL SQL dump — plain text SQL with routines, events, and consistent snapshot', extension: '.dmp' };
        case 'mariadb':
            return { format: 'mariadb-dump', description: 'MariaDB SQL dump — plain text SQL with routines, events, and consistent snapshot', extension: '.dmp' };
        case 'mongodb':
            return { format: 'mongodump', description: 'MongoDB binary archive with gzip compression', extension: '.tar.gz' };
        case 'redis':
        case 'keydb':
        case 'dragonfly':
            return { format: 'RDB snapshot', description: 'Redis-compatible binary RDB dump — full point-in-time snapshot of all keys', extension: '.rdb' };
        case 'clickhouse':
            return { format: 'ClickHouse dump', description: 'DDL schemas + Native format data for each table, packaged as tar.gz', extension: '.tar.gz' };
        default:
            return { format: 'Native dump', description: 'Database-native backup format', extension: '.dmp' };
    }
}

function ImportProgressCard({ status, onReset }: { status: DatabaseImportStatus; onReset: () => void }) {
    const isActive = status.status === 'pending' || status.status === 'in_progress';
    const isFailed = status.status === 'failed';
    const isCompleted = status.status === 'completed';

    return (
        <Card className="p-6">
            <div className="mb-4 flex items-center gap-3">
                {isActive && <Icons.Loader2 className="h-5 w-5 animate-spin text-primary" />}
                {isCompleted && <Icons.CheckCircle2 className="h-5 w-5 text-success" />}
                {isFailed && <Icons.XCircle className="h-5 w-5 text-danger" />}
                <h3 className="font-semibold text-foreground">
                    {isActive && 'Import in Progress'}
                    {isCompleted && 'Import Completed'}
                    {isFailed && 'Import Failed'}
                </h3>
            </div>

            <Progress value={status.progress} max={100} variant={isFailed ? 'danger' : isCompleted ? 'success' : 'default'} size="lg" className="mb-3" />

            <div className="mb-2 text-sm text-foreground-muted">
                {status.progress}% complete
            </div>

            {status.output && (
                <div className="mt-3 rounded bg-background-secondary p-3 text-xs text-foreground-muted">
                    {status.output}
                </div>
            )}

            {status.error && (
                <Alert variant="danger" className="mt-3">
                    <Icons.AlertCircle className="h-4 w-4 flex-shrink-0" />
                    <span>{status.error}</span>
                </Alert>
            )}

            {!isActive && (
                <Button variant="secondary" onClick={onReset} className="mt-4">
                    {isCompleted ? 'Import Another' : 'Try Again'}
                </Button>
            )}
        </Card>
    );
}

export default function DatabaseImport({ database }: Props) {
    const [activeTab, setActiveTab] = useState<TabId>('remote');
    const [isProcessing, setIsProcessing] = useState(false);
    const [copiedIndex, setCopiedIndex] = useState<number | null>(null);
    const { addToast } = useToast();

    // Remote pull state
    const [connectionString, setConnectionString] = useState('');
    const [showConnectionString, setShowConnectionString] = useState(false);
    const [remoteImportStatus, setRemoteImportStatus] = useState<DatabaseImportStatus | null>(null);

    // File upload state
    const [uploadedFile, setUploadedFile] = useState<File | null>(null);
    const [isUploading, setIsUploading] = useState(false);
    const [uploadProgress, setUploadProgress] = useState(0);
    const [uploadComplete, setUploadComplete] = useState(false);
    const [fileImportStatus, setFileImportStatus] = useState<DatabaseImportStatus | null>(null);
    const [isDragging, setIsDragging] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    // Polling ref
    const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const importCommands = getImportCommands(database);

    const isRedisLike = ['redis', 'keydb', 'dragonfly'].includes(database.database_type);

    const copyToClipboard = (text: string, index: number) => {
        navigator.clipboard.writeText(text);
        setCopiedIndex(index);
        setTimeout(() => setCopiedIndex(null), 2000);
    };

    // Poll for import status
    const startPolling = useCallback((importUuid: string, setter: (status: DatabaseImportStatus | null) => void) => {
        if (pollingRef.current) {
            clearInterval(pollingRef.current);
        }

        const poll = async () => {
            try {
                const response = await fetch(`/databases/${database.uuid}/import/status/${importUuid}`, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'include',
                });
                if (response.ok) {
                    const data: DatabaseImportStatus = await response.json();
                    setter(data);
                    if (data.status === 'completed' || data.status === 'failed') {
                        if (pollingRef.current) {
                            clearInterval(pollingRef.current);
                            pollingRef.current = null;
                        }
                    }
                }
            } catch {
                // Silently retry on network errors
            }
        };

        poll();
        pollingRef.current = setInterval(poll, 2000);
    }, [database.uuid]);

    useEffect(() => {
        return () => {
            if (pollingRef.current) {
                clearInterval(pollingRef.current);
            }
        };
    }, []);

    // Handle remote pull
    const handleRemoteImport = async () => {
        if (!connectionString.trim()) {
            addToast('error', 'Please enter a connection string.');
            return;
        }

        setIsProcessing(true);
        try {
            const response = await fetch(`/databases/${database.uuid}/import/remote`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'include',
                body: JSON.stringify({ connection_string: connectionString }),
            });

            const data = await response.json().catch(() => ({}));

            if (response.ok && data.success) {
                addToast('success', 'Import started! Pulling data from remote database...');
                setRemoteImportStatus({
                    uuid: data.import_id,
                    status: 'pending',
                    progress: 0,
                    mode: 'remote_pull',
                    output: null,
                    error: null,
                    started_at: null,
                    finished_at: null,
                });
                startPolling(data.import_id, setRemoteImportStatus);
            } else {
                addToast('error', data.error || 'Failed to start import.');
            }
        } catch {
            addToast('error', 'Failed to connect to the server.');
        } finally {
            setIsProcessing(false);
        }
    };

    // Handle file upload
    const handleFileUpload = async (file: File) => {
        setUploadedFile(file);
        setIsUploading(true);
        setUploadProgress(0);

        const formData = new FormData();
        formData.append('file', file);

        try {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', `/upload/backup/${database.uuid}`);
            xhr.setRequestHeader('X-CSRF-TOKEN', getCsrfToken());
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.withCredentials = true;

            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    setUploadProgress(Math.round((e.loaded / e.total) * 100));
                }
            };

            xhr.onload = () => {
                setIsUploading(false);
                if (xhr.status >= 200 && xhr.status < 300) {
                    setUploadComplete(true);
                    addToast('success', 'File uploaded successfully. Ready to restore.');
                } else {
                    const errorData = JSON.parse(xhr.responseText || '{}');
                    addToast('error', errorData.error || 'Upload failed.');
                    setUploadedFile(null);
                }
            };

            xhr.onerror = () => {
                setIsUploading(false);
                addToast('error', 'Upload failed. Check your connection.');
                setUploadedFile(null);
            };

            xhr.send(formData);
        } catch {
            setIsUploading(false);
            addToast('error', 'Failed to upload file.');
            setUploadedFile(null);
        }
    };

    // Handle file restore after upload
    const handleFileRestore = async () => {
        setIsProcessing(true);
        try {
            const response = await fetch(`/databases/${database.uuid}/import/upload`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'include',
            });

            const data = await response.json().catch(() => ({}));

            if (response.ok && data.success) {
                addToast('success', 'Restore started!');
                setFileImportStatus({
                    uuid: data.import_id,
                    status: 'pending',
                    progress: 0,
                    mode: 'file_upload',
                    output: null,
                    error: null,
                    started_at: null,
                    finished_at: null,
                });
                startPolling(data.import_id, setFileImportStatus);
            } else {
                addToast('error', data.error || 'Failed to start restore.');
            }
        } catch {
            addToast('error', 'Failed to connect to the server.');
        } finally {
            setIsProcessing(false);
        }
    };

    // Export status state
    const [exportStatus, setExportStatus] = useState<'idle' | 'in_progress' | 'completed' | 'failed'>('idle');
    const exportPollingRef = useRef<ReturnType<typeof setInterval> | null>(null);

    // Cleanup export polling on unmount
    useEffect(() => {
        return () => {
            if (exportPollingRef.current) {
                clearInterval(exportPollingRef.current);
            }
        };
    }, []);

    const handleExport = async () => {
        setIsProcessing(true);
        setExportStatus('in_progress');
        try {
            const response = await fetch(`/databases/${database.uuid}/export`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'include',
            });

            if (response.status === 419) {
                addToast('error', 'Session expired. Please refresh the page and try again.');
                setExportStatus('idle');
                setIsProcessing(false);
                return;
            }

            const data = await response.json().catch(() => ({}));

            if (response.ok && data.success && data.execution_id) {
                addToast('success', 'Export started. The file will download automatically when ready.');
                // Start polling for export status
                const pollExportStatus = async () => {
                    try {
                        const statusRes = await fetch(`/databases/${database.uuid}/export/status/${data.execution_id}`, {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'include',
                        });
                        if (statusRes.status === 419) {
                            addToast('error', 'Session expired. Please refresh the page.');
                            if (exportPollingRef.current) clearInterval(exportPollingRef.current);
                            setExportStatus('failed');
                            setIsProcessing(false);
                            return;
                        }
                        if (statusRes.ok) {
                            const statusData = await statusRes.json();
                            setExportStatus(statusData.status);
                            if (statusData.status === 'completed' && statusData.download_url) {
                                if (exportPollingRef.current) clearInterval(exportPollingRef.current);
                                setIsProcessing(false);
                                addToast('success', 'Export complete! Downloading...');
                                window.location.href = statusData.download_url;
                            } else if (statusData.status === 'failed') {
                                if (exportPollingRef.current) clearInterval(exportPollingRef.current);
                                setIsProcessing(false);
                                addToast('error', statusData.error || 'Export failed.');
                            }
                        }
                    } catch {
                        // Silently retry on network errors
                    }
                };
                pollExportStatus();
                exportPollingRef.current = setInterval(pollExportStatus, 2000);
            } else {
                addToast('error', data.error || 'Failed to initiate export.');
                setExportStatus('idle');
                setIsProcessing(false);
            }
        } catch {
            addToast('error', 'Failed to connect to the server.');
            setExportStatus('idle');
            setIsProcessing(false);
        }
    };

    // Drag and drop handlers
    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(true);
    };

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(false);
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(false);
        const file = e.dataTransfer.files[0];
        if (file) {
            handleFileUpload(file);
        }
    };

    const resetRemoteImport = () => {
        setRemoteImportStatus(null);
        setConnectionString('');
    };

    const resetFileImport = () => {
        setFileImportStatus(null);
        setUploadedFile(null);
        setUploadComplete(false);
        setUploadProgress(0);
    };

    const tabs: { id: TabId; label: string; icon: React.ElementType }[] = [
        { id: 'remote', label: 'Pull from Remote', icon: Icons.CloudDownload },
        { id: 'upload', label: 'Upload File', icon: Icons.HardDriveUpload },
        { id: 'cli', label: 'CLI Instructions', icon: Icons.Terminal },
        { id: 'export', label: 'Export', icon: Icons.Download },
    ];

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
                    <p className="text-foreground-muted">Import data from external sources or export your database</p>
                </div>

                {/* Tabs */}
                <div className="flex gap-2 overflow-x-auto border-b border-border">
                    {tabs.map((tab) => (
                        <button
                            key={tab.id}
                            onClick={() => setActiveTab(tab.id)}
                            className={cn(
                                'whitespace-nowrap px-4 py-2 text-sm font-medium transition-colors',
                                activeTab === tab.id
                                    ? 'border-b-2 border-primary text-primary'
                                    : 'text-foreground-muted hover:text-foreground'
                            )}
                        >
                            <div className="flex items-center gap-2">
                                <tab.icon className="h-4 w-4" />
                                {tab.label}
                            </div>
                        </button>
                    ))}
                </div>

                {/* Tab: Pull from Remote */}
                {activeTab === 'remote' && (
                    <div className="space-y-6">
                        {remoteImportStatus ? (
                            <ImportProgressCard status={remoteImportStatus} onReset={resetRemoteImport} />
                        ) : (
                            <>
                                <Card className="p-6">
                                    <div className="mb-4 flex items-center gap-3">
                                        <Icons.CloudDownload className="h-6 w-6 text-primary" />
                                        <h2 className="text-lg font-semibold text-foreground">
                                            Pull from Remote Database
                                        </h2>
                                    </div>
                                    <p className="mb-6 text-sm text-foreground-muted">
                                        Paste the connection string from your external database provider (Railway, Supabase, RDS, etc.).
                                        Saturn will pull the data and restore it into this database.
                                    </p>

                                    {isRedisLike && (
                                        <Alert variant="warning" className="mb-4">
                                            <Icons.AlertTriangle className="h-4 w-4 flex-shrink-0" />
                                            <span>Remote pull is not supported for Redis-like databases. Please use the Upload File tab instead.</span>
                                        </Alert>
                                    )}

                                    {!isRedisLike && (
                                        <>
                                            <div className="mb-4">
                                                <label htmlFor="connection-string" className="mb-2 block text-sm font-medium text-foreground">
                                                    Connection String
                                                </label>
                                                <div className="relative">
                                                    <input
                                                        id="connection-string"
                                                        type={showConnectionString ? 'text' : 'password'}
                                                        value={connectionString}
                                                        onChange={(e) => setConnectionString(e.target.value)}
                                                        placeholder={getPlaceholder(database.database_type)}
                                                        className="w-full rounded-md border border-border bg-background px-3 py-2 pr-10 text-sm text-foreground placeholder:text-foreground-muted/50 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                                                    />
                                                    <button
                                                        type="button"
                                                        onClick={() => setShowConnectionString(!showConnectionString)}
                                                        className="absolute right-2 top-1/2 -translate-y-1/2 text-foreground-muted hover:text-foreground"
                                                    >
                                                        {showConnectionString ? (
                                                            <Icons.EyeOff className="h-4 w-4" />
                                                        ) : (
                                                            <Icons.Eye className="h-4 w-4" />
                                                        )}
                                                    </button>
                                                </div>
                                            </div>

                                            <Alert variant="warning" className="mb-4">
                                                <Icons.AlertTriangle className="h-4 w-4 flex-shrink-0" />
                                                <span>This will overwrite existing data in your database. Make sure to create a backup first.</span>
                                            </Alert>

                                            <Button
                                                onClick={handleRemoteImport}
                                                disabled={isProcessing || !connectionString.trim()}
                                                className="w-full"
                                            >
                                                {isProcessing ? (
                                                    <>
                                                        <Icons.Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                        Starting Import...
                                                    </>
                                                ) : (
                                                    <>
                                                        <Icons.CloudDownload className="mr-2 h-4 w-4" />
                                                        Start Import
                                                    </>
                                                )}
                                            </Button>
                                        </>
                                    )}
                                </Card>

                                <Card className="border-info/30 bg-info/5 p-4">
                                    <div className="flex gap-3">
                                        <Icons.Info className="h-5 w-5 flex-shrink-0 text-info" />
                                        <div>
                                            <h3 className="mb-1 font-semibold text-info">Where to find your connection string</h3>
                                            <ul className="space-y-1 text-sm text-foreground-muted">
                                                <li><strong>Railway:</strong> Settings → Variables → DATABASE_URL</li>
                                                <li><strong>Supabase:</strong> Settings → Database → Connection string</li>
                                                <li><strong>AWS RDS:</strong> Console → Connectivity → Endpoint</li>
                                                <li><strong>DigitalOcean:</strong> Database → Connection Details → URI</li>
                                            </ul>
                                        </div>
                                    </div>
                                </Card>
                            </>
                        )}
                    </div>
                )}

                {/* Tab: Upload File */}
                {activeTab === 'upload' && (
                    <div className="space-y-6">
                        {fileImportStatus ? (
                            <ImportProgressCard status={fileImportStatus} onReset={resetFileImport} />
                        ) : (
                            <>
                                <Card className="p-6">
                                    <div className="mb-4 flex items-center gap-3">
                                        <Icons.HardDriveUpload className="h-6 w-6 text-primary" />
                                        <h2 className="text-lg font-semibold text-foreground">
                                            Upload Dump File
                                        </h2>
                                    </div>
                                    <p className="mb-6 text-sm text-foreground-muted">
                                        Upload a database dump file (.sql, .dump, .gz, .archive, .rdb, etc.) to restore into this database.
                                    </p>

                                    {!uploadComplete ? (
                                        <>
                                            {/* Drag and drop zone */}
                                            <div
                                                onDragOver={handleDragOver}
                                                onDragLeave={handleDragLeave}
                                                onDrop={handleDrop}
                                                onClick={() => fileInputRef.current?.click()}
                                                className={cn(
                                                    'cursor-pointer rounded-lg border-2 border-dashed p-8 text-center transition-colors',
                                                    isDragging
                                                        ? 'border-primary bg-primary/5'
                                                        : 'border-border hover:border-primary/50 hover:bg-background-secondary'
                                                )}
                                            >
                                                <input
                                                    ref={fileInputRef}
                                                    type="file"
                                                    onChange={(e) => {
                                                        const file = e.target.files?.[0];
                                                        if (file) handleFileUpload(file);
                                                    }}
                                                    className="hidden"
                                                    accept=".sql,.dump,.backup,.gz,.tar,.zip,.bz2,.archive,.bson,.json,.rdb,.aof"
                                                />
                                                {isUploading ? (
                                                    <div className="space-y-3">
                                                        <Icons.Loader2 className="mx-auto h-8 w-8 animate-spin text-primary" />
                                                        <p className="text-sm font-medium text-foreground">
                                                            Uploading {uploadedFile?.name}...
                                                        </p>
                                                        <Progress value={uploadProgress} max={100} variant="success" size="default" />
                                                        <p className="text-xs text-foreground-muted">{uploadProgress}%</p>
                                                    </div>
                                                ) : (
                                                    <>
                                                        <Icons.Upload className="mx-auto mb-3 h-8 w-8 text-foreground-muted" />
                                                        <p className="mb-1 text-sm font-medium text-foreground">
                                                            Drop your dump file here or click to browse
                                                        </p>
                                                        <p className="text-xs text-foreground-muted">
                                                            Supports .sql, .dump, .gz, .archive, .rdb (max 2GB)
                                                        </p>
                                                    </>
                                                )}
                                            </div>
                                        </>
                                    ) : (
                                        <>
                                            {/* File uploaded, show restore button */}
                                            <div className="rounded-lg border border-success/30 bg-success/5 p-4">
                                                <div className="flex items-center gap-3">
                                                    <Icons.FileCheck className="h-5 w-5 text-success" />
                                                    <div className="flex-1">
                                                        <p className="text-sm font-medium text-foreground">
                                                            {uploadedFile?.name}
                                                        </p>
                                                        <p className="text-xs text-foreground-muted">
                                                            {uploadedFile?.size ? `${(uploadedFile.size / 1024 / 1024).toFixed(2)} MB` : 'Uploaded'}
                                                        </p>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        onClick={resetFileImport}
                                                        className="text-foreground-muted hover:text-foreground"
                                                    >
                                                        <Icons.X className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </div>

                                            <Alert variant="warning" className="mt-4">
                                                <Icons.AlertTriangle className="h-4 w-4 flex-shrink-0" />
                                                <span>This will overwrite existing data in your database. Make sure to create a backup first.</span>
                                            </Alert>

                                            <Button
                                                onClick={handleFileRestore}
                                                disabled={isProcessing}
                                                className="mt-4 w-full"
                                            >
                                                {isProcessing ? (
                                                    <>
                                                        <Icons.Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                        Starting Restore...
                                                    </>
                                                ) : (
                                                    <>
                                                        <Icons.DatabaseBackup className="mr-2 h-4 w-4" />
                                                        Restore Database
                                                    </>
                                                )}
                                            </Button>
                                        </>
                                    )}
                                </Card>
                            </>
                        )}
                    </div>
                )}

                {/* Tab: CLI Instructions */}
                {activeTab === 'cli' && (
                    <div className="space-y-6">
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

                {/* Tab: Export */}
                {activeTab === 'export' && (
                    <div className="space-y-6">
                        {/* Export Info */}
                        <Card className="p-6">
                            <div className="mb-4 flex items-center gap-3">
                                <Icons.Download className="h-6 w-6 text-primary" />
                                <h2 className="text-lg font-semibold text-foreground">
                                    Export Database
                                </h2>
                            </div>
                            <p className="mb-6 text-sm text-foreground-muted">
                                Create a full backup of your database. The file will download automatically when ready.
                            </p>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <div className="mb-1 text-xs text-foreground-muted">
                                        Database
                                    </div>
                                    <div className="font-medium text-foreground">
                                        {database.name}
                                    </div>
                                </div>
                                <div>
                                    <div className="mb-1 text-xs text-foreground-muted">
                                        Type
                                    </div>
                                    <div className="font-medium text-foreground">
                                        {database.database_type?.toUpperCase() || 'Unknown'}
                                    </div>
                                </div>
                            </div>

                            {(() => {
                                const formatInfo = getExportFormatInfo(database.database_type);
                                return (
                                    <div className="mt-4 rounded-lg border border-border bg-background-secondary p-4">
                                        <div className="flex items-start gap-3">
                                            <Icons.FileArchive className="mt-0.5 h-5 w-5 text-foreground-muted" />
                                            <div>
                                                <div className="text-sm font-medium text-foreground">
                                                    Format: {formatInfo.format} <span className="text-foreground-muted">({formatInfo.extension})</span>
                                                </div>
                                                <div className="mt-1 text-xs text-foreground-muted">
                                                    {formatInfo.description}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })()}
                        </Card>

                        {/* Export Status */}
                        {exportStatus === 'in_progress' && isProcessing && (
                            <Card className="p-4">
                                <div className="flex items-center gap-3">
                                    <Icons.Loader2 className="h-5 w-5 animate-spin text-primary" />
                                    <div>
                                        <p className="text-sm font-medium text-foreground">
                                            Creating backup...
                                        </p>
                                        <p className="text-xs text-foreground-muted">
                                            The file will download automatically when ready
                                        </p>
                                    </div>
                                </div>
                            </Card>
                        )}

                        {/* Export Button */}
                        <Button
                            onClick={handleExport}
                            disabled={isProcessing}
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
