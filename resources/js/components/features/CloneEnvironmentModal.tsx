import { useState, useEffect } from 'react';
import { Modal, ModalFooter, Button } from '@/components/ui';
import { Copy, Loader2, Check, Server } from 'lucide-react';

interface CloneEnvironmentModalProps {
    open: boolean;
    onClose: () => void;
    environmentName: string;
    environmentUuid: string;
    projectUuid: string;
    servers?: Array<{ id: number; name: string; ip: string }>;
    onCloned?: (newEnv: { uuid: string; name: string }) => void;
}

export function CloneEnvironmentModal({
    open,
    onClose,
    environmentName,
    environmentUuid,
    projectUuid,
    servers = [],
    onCloned,
}: CloneEnvironmentModalProps) {
    const [step, setStep] = useState<'configure' | 'cloning' | 'done'>('configure');
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [targetServerId, setTargetServerId] = useState<number | null>(null);
    const [cloneEnvVars, setCloneEnvVars] = useState(true);
    const [cloneScheduledTasks, setCloneScheduledTasks] = useState(true);
    const [cloneBackupConfigs, setCloneBackupConfigs] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [result, setResult] = useState<{ uuid: string; name: string } | null>(null);

    // Reset state when modal opens
    useEffect(() => {
        if (open) {
            setStep('configure');
            setName(`${environmentName}-clone`);
            setDescription(`Cloned from ${environmentName}`);
            setTargetServerId(null);
            setError(null);
            setResult(null);
        }
    }, [open, environmentName]);

    const handleClone = async () => {
        if (!name.trim()) {
            setError('Name is required');
            return;
        }

        setStep('cloning');
        setError(null);

        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(`/projects/${projectUuid}/environments/${environmentUuid}/clone`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    name: name.trim(),
                    description: description.trim() || undefined,
                    target_server_id: targetServerId,
                    clone_env_vars: cloneEnvVars,
                    clone_scheduled_tasks: cloneScheduledTasks,
                    clone_backup_configs: cloneBackupConfigs,
                }),
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.message || `Clone failed (${response.status})`);
            }

            const data = await response.json();
            setResult(data);
            setStep('done');
            onCloned?.(data);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Clone failed');
            setStep('configure');
        }
    };

    return (
        <Modal isOpen={open} onClose={onClose} title="Clone Environment" size="default">
            {step === 'configure' && (
                <div className="space-y-4 p-4">
                    <p className="text-sm text-muted-foreground">
                        Create a copy of <strong>{environmentName}</strong> with all its resources (applications, databases, services).
                        Data is not copied — only configurations.
                    </p>

                    {error && (
                        <div className="text-sm text-red-500 bg-red-500/10 border border-red-500/20 rounded px-3 py-2">
                            {error}
                        </div>
                    )}

                    <div className="space-y-3">
                        <div>
                            <label className="text-sm font-medium">Environment Name</label>
                            <input
                                type="text"
                                value={name}
                                onChange={e => setName(e.target.value)}
                                className="mt-1 w-full h-9 px-3 text-sm border rounded bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                                placeholder="e.g. feature-test"
                            />
                        </div>

                        <div>
                            <label className="text-sm font-medium">Description</label>
                            <input
                                type="text"
                                value={description}
                                onChange={e => setDescription(e.target.value)}
                                className="mt-1 w-full h-9 px-3 text-sm border rounded bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                                placeholder="Optional description"
                            />
                        </div>

                        {servers.length > 1 && (
                            <div>
                                <label className="text-sm font-medium flex items-center gap-1">
                                    <Server className="h-3.5 w-3.5" />
                                    Target Server
                                </label>
                                <select
                                    value={targetServerId ?? ''}
                                    onChange={e => setTargetServerId(e.target.value ? parseInt(e.target.value) : null)}
                                    className="mt-1 w-full h-9 px-3 text-sm border rounded bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                                >
                                    <option value="">Same as source</option>
                                    {servers.map(s => (
                                        <option key={s.id} value={s.id}>{s.name} ({s.ip})</option>
                                    ))}
                                </select>
                            </div>
                        )}

                        <div className="space-y-2 pt-2 border-t">
                            <span className="text-sm font-medium">Clone options</span>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={cloneEnvVars}
                                    onChange={e => setCloneEnvVars(e.target.checked)}
                                    className="rounded"
                                />
                                Environment variables
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={cloneScheduledTasks}
                                    onChange={e => setCloneScheduledTasks(e.target.checked)}
                                    className="rounded"
                                />
                                Scheduled tasks (cron jobs)
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={cloneBackupConfigs}
                                    onChange={e => setCloneBackupConfigs(e.target.checked)}
                                    className="rounded"
                                />
                                Backup configurations (disabled by default)
                            </label>
                        </div>
                    </div>
                </div>
            )}

            {step === 'cloning' && (
                <div className="flex flex-col items-center justify-center py-12 gap-3">
                    <Loader2 className="h-8 w-8 animate-spin text-primary" />
                    <p className="text-sm text-muted-foreground">Cloning environment...</p>
                </div>
            )}

            {step === 'done' && result && (
                <div className="flex flex-col items-center justify-center py-12 gap-3">
                    <div className="h-12 w-12 rounded-full bg-green-500/10 flex items-center justify-center">
                        <Check className="h-6 w-6 text-green-500" />
                    </div>
                    <p className="text-sm font-medium">Environment cloned successfully!</p>
                    <p className="text-xs text-muted-foreground">
                        New environment: <strong>{result.name}</strong>
                    </p>
                </div>
            )}

            <ModalFooter>
                {step === 'configure' && (
                    <>
                        <Button variant="ghost" onClick={onClose}>Cancel</Button>
                        <Button onClick={handleClone}>
                            <Copy className="h-4 w-4 mr-1.5" />
                            Clone Environment
                        </Button>
                    </>
                )}
                {step === 'done' && (
                    <Button onClick={onClose}>Close</Button>
                )}
            </ModalFooter>
        </Modal>
    );
}
