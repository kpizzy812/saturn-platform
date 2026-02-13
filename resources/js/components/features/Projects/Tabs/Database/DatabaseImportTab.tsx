import { useCallback, useEffect, useRef, useState } from 'react';
import { Alert, Button, Progress } from '@/components/ui';
import { useToast } from '@/components/ui/Toast';
import {
    CloudDownload,
    HardDriveUpload,
    Terminal,
    Download,
    Loader2,
    CheckCircle2,
    XCircle,
    AlertTriangle,
    AlertCircle,
    Eye,
    EyeOff,
    Upload,
    FileCheck,
    X,
    Copy,
    Check,
    Info,
    DatabaseBackup,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type { DatabaseImportStatus } from '@/types';
import type { SelectedService } from '../../types';

type SubTab = 'remote' | 'upload' | 'cli' | 'export';

interface DatabaseImportTabProps {
    service: SelectedService;
}

function getCsrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
}

function getPlaceholder(dbType: string): string {
    switch (dbType) {
        case 'postgresql':
        case 'postgres':
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

function getImportCommands(uuid: string, dbType: string): { label: string; command: string }[] {
    switch (dbType) {
        case 'postgresql':
        case 'postgres':
            return [
                { label: 'Import SQL dump', command: `docker exec -i ${uuid} psql -U postgres -d postgres < dump.sql` },
                { label: 'Import compressed dump', command: `gunzip -c dump.sql.gz | docker exec -i ${uuid} psql -U postgres -d postgres` },
                { label: 'Restore custom format', command: `docker exec -i ${uuid} pg_restore -U postgres -d postgres < dump.custom` },
            ];
        case 'mysql':
        case 'mariadb':
            return [
                { label: 'Import SQL dump', command: `docker exec -i ${uuid} mysql -u root -p<PASSWORD> <DATABASE> < dump.sql` },
                { label: 'Import compressed dump', command: `gunzip -c dump.sql.gz | docker exec -i ${uuid} mysql -u root -p<PASSWORD> <DATABASE>` },
            ];
        case 'mongodb':
            return [
                { label: 'Import BSON dump', command: `docker exec -i ${uuid} mongorestore --db <DATABASE> /dump/` },
                { label: 'Import JSON', command: `docker exec -i ${uuid} mongoimport --db <DATABASE> --collection <COLLECTION> --file data.json` },
            ];
        case 'redis':
        case 'keydb':
        case 'dragonfly':
            return [
                { label: 'Restore RDB file', command: `docker cp dump.rdb ${uuid}:/data/dump.rdb && docker restart ${uuid}` },
            ];
        case 'clickhouse':
            return [
                { label: 'Import CSV', command: `docker exec -i ${uuid} clickhouse-client --query="INSERT INTO <TABLE> FORMAT CSV" < data.csv` },
                { label: 'Import SQL dump', command: `docker exec -i ${uuid} clickhouse-client < dump.sql` },
            ];
        default:
            return [
                { label: 'Import SQL dump', command: `docker exec -i ${uuid} < dump.sql` },
            ];
    }
}

function getExportFormatInfo(dbType: string): { format: string; description: string } {
    switch (dbType) {
        case 'postgresql':
        case 'postgres':
            return { format: 'pg_dump custom (.dmp)', description: 'Supports parallel restore and selective recovery' };
        case 'mysql':
            return { format: 'mysqldump (.dmp)', description: 'Plain text SQL statements' };
        case 'mariadb':
            return { format: 'mariadb-dump (.dmp)', description: 'Plain text SQL statements' };
        case 'mongodb':
            return { format: 'mongodump (.tar.gz)', description: 'Binary archive with gzip compression' };
        default:
            return { format: 'Native dump', description: 'Database-native backup format' };
    }
}

function ImportProgressCard({ status, onReset }: { status: DatabaseImportStatus; onReset: () => void }) {
    const isActive = status.status === 'pending' || status.status === 'in_progress';
    const isFailed = status.status === 'failed';
    const isCompleted = status.status === 'completed';

    return (
        <div className="rounded-lg border border-border bg-background-secondary p-4">
            <div className="mb-3 flex items-center gap-2">
                {isActive && <Loader2 className="h-4 w-4 animate-spin text-primary" />}
                {isCompleted && <CheckCircle2 className="h-4 w-4 text-success" />}
                {isFailed && <XCircle className="h-4 w-4 text-danger" />}
                <h3 className="text-sm font-semibold text-foreground">
                    {isActive && 'Import in Progress'}
                    {isCompleted && 'Import Completed'}
                    {isFailed && 'Import Failed'}
                </h3>
            </div>

            <Progress value={status.progress} max={100} variant={isFailed ? 'danger' : isCompleted ? 'success' : 'default'} size="default" className="mb-2" />

            <div className="mb-1 text-xs text-foreground-muted">
                {status.progress}% complete
            </div>

            {status.output && (
                <div className="mt-2 rounded bg-background p-2 text-xs text-foreground-muted">
                    {status.output}
                </div>
            )}

            {status.error && (
                <Alert variant="danger" className="mt-2">
                    <AlertCircle className="h-3.5 w-3.5 flex-shrink-0" />
                    <span className="text-xs">{status.error}</span>
                </Alert>
            )}

            {!isActive && (
                <Button size="sm" variant="secondary" onClick={onReset} className="mt-3">
                    {isCompleted ? 'Import Another' : 'Try Again'}
                </Button>
            )}
        </div>
    );
}

export function DatabaseImportTab({ service }: DatabaseImportTabProps) {
    const [activeSubTab, setActiveSubTab] = useState<SubTab>('remote');
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

    const dbType = service.dbType?.toLowerCase() || '';
    const importCommands = getImportCommands(service.uuid, dbType);
    const isRedisLike = ['redis', 'keydb', 'dragonfly'].includes(dbType);

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
                const response = await fetch(`/databases/${service.uuid}/import/status/${importUuid}`, {
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
    }, [service.uuid]);

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
            const response = await fetch(`/databases/${service.uuid}/import/remote`, {
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
            xhr.open('POST', `/upload/backup/${service.uuid}`);
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
            const response = await fetch(`/databases/${service.uuid}/import/upload`, {
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
            const response = await fetch(`/databases/${service.uuid}/export`, {
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
                const pollExportStatus = async () => {
                    try {
                        const statusRes = await fetch(`/databases/${service.uuid}/export/status/${data.execution_id}`, {
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

    const subTabs: { id: SubTab; label: string; icon: React.ElementType }[] = [
        { id: 'remote', label: 'Remote', icon: CloudDownload },
        { id: 'upload', label: 'Upload', icon: HardDriveUpload },
        { id: 'cli', label: 'CLI', icon: Terminal },
        { id: 'export', label: 'Export', icon: Download },
    ];

    return (
        <div className="space-y-4">
            {/* Sub-tabs */}
            <div className="flex gap-1 rounded-lg border border-border bg-background-secondary p-1">
                {subTabs.map((tab) => (
                    <button
                        key={tab.id}
                        onClick={() => setActiveSubTab(tab.id)}
                        className={cn(
                            'flex flex-1 items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium transition-colors',
                            activeSubTab === tab.id
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-foreground-muted hover:text-foreground'
                        )}
                    >
                        <tab.icon className="h-3.5 w-3.5" />
                        {tab.label}
                    </button>
                ))}
            </div>

            {/* Remote Pull */}
            {activeSubTab === 'remote' && (
                <div className="space-y-3">
                    {remoteImportStatus ? (
                        <ImportProgressCard status={remoteImportStatus} onReset={resetRemoteImport} />
                    ) : (
                        <>
                            <div>
                                <div className="mb-2 flex items-center gap-2">
                                    <CloudDownload className="h-4 w-4 text-primary" />
                                    <h3 className="text-sm font-semibold text-foreground">Pull from Remote</h3>
                                </div>
                                <p className="mb-3 text-xs text-foreground-muted">
                                    Paste the connection string from your external database provider to pull data into this database.
                                </p>
                            </div>

                            {isRedisLike ? (
                                <Alert variant="warning">
                                    <AlertTriangle className="h-3.5 w-3.5 flex-shrink-0" />
                                    <span className="text-xs">Remote pull is not supported for Redis-like databases. Use the Upload tab instead.</span>
                                </Alert>
                            ) : (
                                <>
                                    <div>
                                        <label htmlFor="sidebar-connection-string" className="mb-1 block text-xs font-medium text-foreground">
                                            Connection String
                                        </label>
                                        <div className="relative">
                                            <input
                                                id="sidebar-connection-string"
                                                type={showConnectionString ? 'text' : 'password'}
                                                value={connectionString}
                                                onChange={(e) => setConnectionString(e.target.value)}
                                                placeholder={getPlaceholder(dbType)}
                                                className="w-full rounded-md border border-border bg-background px-3 py-1.5 pr-8 text-xs text-foreground placeholder:text-foreground-muted/50 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => setShowConnectionString(!showConnectionString)}
                                                className="absolute right-2 top-1/2 -translate-y-1/2 text-foreground-muted hover:text-foreground"
                                            >
                                                {showConnectionString ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                                            </button>
                                        </div>
                                    </div>

                                    <Alert variant="warning">
                                        <AlertTriangle className="h-3.5 w-3.5 flex-shrink-0" />
                                        <span className="text-xs">This will overwrite existing data. Create a backup first.</span>
                                    </Alert>

                                    <Button
                                        size="sm"
                                        onClick={handleRemoteImport}
                                        disabled={isProcessing || !connectionString.trim()}
                                        className="w-full"
                                    >
                                        {isProcessing ? (
                                            <>
                                                <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />
                                                Starting Import...
                                            </>
                                        ) : (
                                            <>
                                                <CloudDownload className="mr-1.5 h-3.5 w-3.5" />
                                                Start Import
                                            </>
                                        )}
                                    </Button>

                                    <div className="rounded-lg border border-info/30 bg-info/5 p-3">
                                        <div className="flex gap-2">
                                            <Info className="h-3.5 w-3.5 flex-shrink-0 text-info mt-0.5" />
                                            <div>
                                                <h4 className="mb-1 text-xs font-semibold text-info">Where to find your connection string</h4>
                                                <ul className="space-y-0.5 text-xs text-foreground-muted">
                                                    <li><strong>Railway:</strong> Settings &rarr; Variables &rarr; DATABASE_URL</li>
                                                    <li><strong>Supabase:</strong> Settings &rarr; Database &rarr; Connection string</li>
                                                    <li><strong>AWS RDS:</strong> Console &rarr; Connectivity &rarr; Endpoint</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </>
                            )}
                        </>
                    )}
                </div>
            )}

            {/* Upload File */}
            {activeSubTab === 'upload' && (
                <div className="space-y-3">
                    {fileImportStatus ? (
                        <ImportProgressCard status={fileImportStatus} onReset={resetFileImport} />
                    ) : (
                        <>
                            <div className="mb-2 flex items-center gap-2">
                                <HardDriveUpload className="h-4 w-4 text-primary" />
                                <h3 className="text-sm font-semibold text-foreground">Upload Dump File</h3>
                            </div>

                            {!uploadComplete ? (
                                <div
                                    onDragOver={handleDragOver}
                                    onDragLeave={handleDragLeave}
                                    onDrop={handleDrop}
                                    onClick={() => fileInputRef.current?.click()}
                                    className={cn(
                                        'cursor-pointer rounded-lg border-2 border-dashed p-6 text-center transition-colors',
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
                                        <div className="space-y-2">
                                            <Loader2 className="mx-auto h-6 w-6 animate-spin text-primary" />
                                            <p className="text-xs font-medium text-foreground">
                                                Uploading {uploadedFile?.name}...
                                            </p>
                                            <Progress value={uploadProgress} max={100} variant="success" size="default" />
                                            <p className="text-xs text-foreground-muted">{uploadProgress}%</p>
                                        </div>
                                    ) : (
                                        <>
                                            <Upload className="mx-auto mb-2 h-6 w-6 text-foreground-muted" />
                                            <p className="mb-1 text-xs font-medium text-foreground">
                                                Drop file here or click to browse
                                            </p>
                                            <p className="text-xs text-foreground-muted">
                                                .sql, .dump, .gz, .archive, .rdb (max 2GB)
                                            </p>
                                        </>
                                    )}
                                </div>
                            ) : (
                                <>
                                    <div className="rounded-lg border border-success/30 bg-success/5 p-3">
                                        <div className="flex items-center gap-2">
                                            <FileCheck className="h-4 w-4 text-success" />
                                            <div className="flex-1 min-w-0">
                                                <p className="truncate text-xs font-medium text-foreground">
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
                                                <X className="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                    </div>

                                    <Alert variant="warning">
                                        <AlertTriangle className="h-3.5 w-3.5 flex-shrink-0" />
                                        <span className="text-xs">This will overwrite existing data. Create a backup first.</span>
                                    </Alert>

                                    <Button
                                        size="sm"
                                        onClick={handleFileRestore}
                                        disabled={isProcessing}
                                        className="w-full"
                                    >
                                        {isProcessing ? (
                                            <>
                                                <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />
                                                Starting Restore...
                                            </>
                                        ) : (
                                            <>
                                                <DatabaseBackup className="mr-1.5 h-3.5 w-3.5" />
                                                Restore Database
                                            </>
                                        )}
                                    </Button>
                                </>
                            )}
                        </>
                    )}
                </div>
            )}

            {/* CLI Instructions */}
            {activeSubTab === 'cli' && (
                <div className="space-y-3">
                    <div className="mb-2 flex items-center gap-2">
                        <Terminal className="h-4 w-4 text-primary" />
                        <h3 className="text-sm font-semibold text-foreground">Import via CLI</h3>
                    </div>
                    <p className="text-xs text-foreground-muted">
                        SSH into your server and use the commands below. Replace placeholder values with your actual credentials.
                    </p>

                    <div className="space-y-2">
                        {importCommands.map((cmd, index) => (
                            <div key={index} className="rounded-lg border border-border bg-background-secondary p-3">
                                <div className="mb-1.5 flex items-center justify-between">
                                    <span className="text-xs font-medium text-foreground">{cmd.label}</span>
                                    <button
                                        type="button"
                                        onClick={() => copyToClipboard(cmd.command, index)}
                                        className="flex items-center gap-1 text-xs text-foreground-muted hover:text-foreground"
                                    >
                                        {copiedIndex === index ? (
                                            <>
                                                <Check className="h-3 w-3 text-success" />
                                                <span className="text-xs">Copied</span>
                                            </>
                                        ) : (
                                            <Copy className="h-3 w-3" />
                                        )}
                                    </button>
                                </div>
                                <pre className="overflow-x-auto rounded bg-background p-2 text-xs text-foreground-muted">
                                    <code>{cmd.command}</code>
                                </pre>
                            </div>
                        ))}
                    </div>

                    <div className="rounded-lg border border-info/30 bg-info/5 p-3">
                        <div className="flex gap-2">
                            <Info className="h-3.5 w-3.5 flex-shrink-0 text-info mt-0.5" />
                            <div>
                                <h4 className="mb-1 text-xs font-semibold text-info">Tips</h4>
                                <ul className="space-y-0.5 text-xs text-foreground-muted">
                                    <li>Create a backup before importing</li>
                                    <li>Use <code className="rounded bg-background px-1 text-xs">screen</code> or <code className="rounded bg-background px-1 text-xs">tmux</code> for large imports</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Export */}
            {activeSubTab === 'export' && (
                <div className="space-y-3">
                    <div className="mb-2 flex items-center gap-2">
                        <Download className="h-4 w-4 text-primary" />
                        <h3 className="text-sm font-semibold text-foreground">Export Database</h3>
                    </div>
                    <p className="text-xs text-foreground-muted">
                        Create a full backup. The file will download automatically when ready.
                    </p>

                    {/* Format info */}
                    {(() => {
                        const formatInfo = getExportFormatInfo(dbType);
                        return (
                            <div className="rounded-lg border border-border bg-background-secondary p-3">
                                <div className="text-xs font-medium text-foreground">{formatInfo.format}</div>
                                <div className="text-xs text-foreground-muted">{formatInfo.description}</div>
                            </div>
                        );
                    })()}

                    {/* Export Status */}
                    {exportStatus === 'in_progress' && isProcessing && (
                        <div className="flex items-center gap-2 rounded-lg border border-border bg-background-secondary p-3">
                            <Loader2 className="h-4 w-4 animate-spin text-primary" />
                            <div>
                                <p className="text-xs font-medium text-foreground">Creating backup...</p>
                                <p className="text-xs text-foreground-muted">Auto-download when ready</p>
                            </div>
                        </div>
                    )}

                    <Button
                        size="sm"
                        onClick={handleExport}
                        disabled={isProcessing}
                        className="w-full"
                    >
                        {isProcessing ? (
                            <>
                                <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />
                                Exporting...
                            </>
                        ) : (
                            <>
                                <Download className="mr-1.5 h-3.5 w-3.5" />
                                Export Database
                            </>
                        )}
                    </Button>
                </div>
            )}
        </div>
    );
}
