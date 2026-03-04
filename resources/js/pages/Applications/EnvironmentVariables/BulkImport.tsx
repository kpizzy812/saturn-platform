import * as React from 'react';
import { Modal, ModalFooter, Button } from '@/components/ui';
import { Upload, FileText, AlertCircle, CheckCircle, AlertTriangle } from 'lucide-react';
import { cn } from '@/lib/utils';

interface ParsedVariable {
    key: string;
    value: string;
    status: 'new' | 'conflict';
    action: 'import' | 'skip';
}

export interface BulkImportProps {
    isOpen: boolean;
    onClose: () => void;
    applicationUuid: string;
    existingKeys: string[];
    onImported: () => void;
}

function parseEnvContent(text: string, existingKeys: string[]): ParsedVariable[] {
    const result: ParsedVariable[] = [];
    const seen = new Set<string>();

    for (const rawLine of text.split('\n')) {
        const line = rawLine.trim();
        if (!line || line.startsWith('#')) continue;

        const eqPos = line.indexOf('=');
        if (eqPos === -1) continue;

        const key = line.substring(0, eqPos).trim();
        let value = line.substring(eqPos + 1).trim();

        // Remove surrounding quotes
        if (
            value.length >= 2 &&
            ((value[0] === '"' && value[value.length - 1] === '"') ||
                (value[0] === "'" && value[value.length - 1] === "'"))
        ) {
            value = value.slice(1, -1);
        }

        if (!/^[A-Za-z_][A-Za-z0-9_]*$/.test(key)) continue;
        if (seen.has(key)) continue;
        seen.add(key);

        const isConflict = existingKeys.includes(key);
        result.push({
            key,
            value,
            status: isConflict ? 'conflict' : 'new',
            action: isConflict ? 'skip' : 'import',
        });
    }

    return result;
}

export function BulkImport({ isOpen, onClose, applicationUuid, existingKeys, onImported }: BulkImportProps) {
    const [content, setContent] = React.useState('');
    const [preview, setPreview] = React.useState<ParsedVariable[]>([]);
    const [step, setStep] = React.useState<'input' | 'preview'>('input');
    const [isSaving, setIsSaving] = React.useState(false);
    const [error, setError] = React.useState<string | null>(null);

    const handleFileUpload = () => {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.txt,.env';
        input.onchange = (e: Event) => {
            const file = (e.target as HTMLInputElement).files?.[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (ev) => setContent((ev.target?.result as string) || '');
            reader.readAsText(file);
        };
        input.click();
    };

    const handleParse = () => {
        const parsed = parseEnvContent(content, existingKeys);
        setPreview(parsed);
        setStep('preview');
    };

    const toggleAction = (key: string) => {
        setPreview((prev) =>
            prev.map((v) =>
                v.key === key ? { ...v, action: v.action === 'import' ? 'skip' : 'import' } : v,
            ),
        );
    };

    const handleImport = async () => {
        setIsSaving(true);
        setError(null);
        try {
            const csrfToken =
                document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const conflictResolution: Record<string, string> = {};
            preview.forEach((v) => {
                conflictResolution[v.key] = v.action;
            });

            const response = await fetch(`/web-api/applications/${applicationUuid}/envs/bulk-import`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ content, save: true, conflict_resolution: conflictResolution }),
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error((data as { message?: string }).message || 'Import failed');
            }

            onImported();
            handleClose();
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Import failed');
        } finally {
            setIsSaving(false);
        }
    };

    const handleClose = () => {
        setContent('');
        setPreview([]);
        setStep('input');
        setError(null);
        onClose();
    };

    const importCount = preview.filter((v) => v.action === 'import').length;

    return (
        <Modal isOpen={isOpen} onClose={handleClose} title="Bulk Import Variables" size="xl">
            {step === 'input' ? (
                <div className="space-y-4">
                    <p className="text-sm text-foreground-muted">
                        Paste your .env file content below or upload a file to import environment variables in bulk.
                    </p>
                    <div className="flex justify-end">
                        <Button size="sm" variant="secondary" onClick={handleFileUpload}>
                            <Upload className="mr-2 h-4 w-4" />
                            Upload File
                        </Button>
                    </div>
                    <textarea
                        value={content}
                        onChange={(e) => setContent(e.target.value)}
                        placeholder={'DATABASE_URL=postgres://...\nREDIS_URL=redis://...\nSECRET_KEY=your-secret'}
                        rows={10}
                        className="w-full resize-none rounded-lg border border-border bg-background/50 px-3 py-2 font-mono text-sm text-foreground placeholder:text-foreground-muted focus:outline-none focus:ring-2 focus:ring-primary/20"
                    />
                    <ModalFooter>
                        <Button variant="secondary" onClick={handleClose}>
                            Cancel
                        </Button>
                        <Button onClick={handleParse} disabled={!content.trim()}>
                            <FileText className="mr-2 h-4 w-4" />
                            Preview
                        </Button>
                    </ModalFooter>
                </div>
            ) : (
                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-foreground-muted">
                            Review variables before importing. Click "Overwrite" to resolve conflicts.
                        </p>
                        <div className="flex gap-3 text-xs">
                            <span className="text-success">{importCount} to import</span>
                            <span className="text-foreground-muted">
                                {preview.length - importCount} to skip
                            </span>
                        </div>
                    </div>

                    {preview.length === 0 ? (
                        <div className="flex items-center gap-2 rounded-lg border border-warning/50 bg-warning/5 p-3 text-sm text-warning">
                            <AlertCircle className="h-4 w-4 shrink-0" />
                            No valid variables found in the pasted content.
                        </div>
                    ) : (
                        <div className="max-h-72 overflow-y-auto rounded-lg border border-border">
                            <table className="w-full text-sm">
                                <thead className="sticky top-0 border-b border-border bg-background/95 backdrop-blur-sm">
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-foreground-muted">
                                            Key
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-foreground-muted">
                                            Value
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-foreground-muted">
                                            Status
                                        </th>
                                        <th className="px-3 py-2 text-center text-xs font-medium text-foreground-muted">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {preview.map((v) => (
                                        <tr
                                            key={v.key}
                                            className={cn(
                                                'border-b border-border/50 transition-opacity',
                                                v.action === 'skip' && 'opacity-40',
                                            )}
                                        >
                                            <td className="px-3 py-2 font-mono font-medium text-foreground">
                                                {v.key}
                                            </td>
                                            <td className="px-3 py-2 font-mono text-foreground-muted">
                                                {v.value ? (
                                                    '•'.repeat(Math.min(v.value.length, 12))
                                                ) : (
                                                    <em>(empty)</em>
                                                )}
                                            </td>
                                            <td className="px-3 py-2">
                                                {v.status === 'conflict' ? (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-warning/10 px-2 py-0.5 text-xs font-medium text-warning">
                                                        <AlertTriangle className="h-3 w-3" />
                                                        Conflict
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-success/10 px-2 py-0.5 text-xs font-medium text-success">
                                                        <CheckCircle className="h-3 w-3" />
                                                        New
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-center">
                                                {v.status === 'conflict' && (
                                                    <button
                                                        type="button"
                                                        onClick={() => toggleAction(v.key)}
                                                        className={cn(
                                                            'rounded px-2 py-0.5 text-xs font-medium transition-colors',
                                                            v.action === 'import'
                                                                ? 'bg-primary/10 text-primary hover:bg-primary/20'
                                                                : 'bg-border/50 text-foreground-muted hover:bg-border',
                                                        )}
                                                    >
                                                        {v.action === 'import' ? 'Overwrite' : 'Skip'}
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {error && (
                        <div className="flex items-center gap-2 rounded-lg border border-danger/50 bg-danger/5 p-3 text-sm text-danger">
                            <AlertCircle className="h-4 w-4 shrink-0" />
                            {error}
                        </div>
                    )}

                    <ModalFooter>
                        <Button variant="secondary" onClick={() => setStep('input')}>
                            Back
                        </Button>
                        <Button onClick={handleImport} disabled={isSaving || importCount === 0} loading={isSaving}>
                            Import{' '}
                            {importCount > 0 ? `${importCount} Variable${importCount !== 1 ? 's' : ''}` : ''}
                        </Button>
                    </ModalFooter>
                </div>
            )}
        </Modal>
    );
}
