import { useState, useCallback } from 'react';
import { Card, CardContent, Badge, Button, Modal, ModalFooter, useToast } from '@/components/ui';
import { Container, Clock, RefreshCw, AlertCircle, CheckCircle, XCircle, Info } from 'lucide-react';
import type { Service } from '@/types';

interface ContainerInfo {
    id: string;
    name: string;
    image: string;
    status: string;
    state: string;
    created: string;
}

interface Props {
    service: Service;
    containers?: ContainerInfo[];
}

export function RollbacksTab({ service, containers = [] }: Props) {
    const { addToast } = useToast();
    const [showRedeployModal, setShowRedeployModal] = useState(false);
    const [isRedeploying, setIsRedeploying] = useState(false);
    const [pullLatest, setPullLatest] = useState(true);

    const handleRedeploy = useCallback(async () => {
        setIsRedeploying(true);

        try {
            const response = await fetch(`/api/services/${service.uuid}/redeploy`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                },
                credentials: 'include',
                body: JSON.stringify({ pull_latest: pullLatest }),
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Redeploy failed');
            }

            addToast('success', pullLatest
                    ? 'Redeploy initiated: Service will restart with latest images'
                    : 'Restart initiated: Service restart initiated');
            setShowRedeployModal(false);
        } catch (err) {
            addToast('error', `Redeploy failed: ${err instanceof Error ? err.message : 'Unknown error'}`);
        } finally {
            setIsRedeploying(false);
        }
    }, [service.uuid, pullLatest, addToast]);

    const getStateColor = (state: string): string => {
        const stateLower = state.toLowerCase();
        if (stateLower === 'running') return 'text-success';
        if (stateLower === 'exited' || stateLower === 'dead') return 'text-danger';
        if (stateLower === 'paused' || stateLower === 'restarting') return 'text-warning';
        return 'text-foreground-muted';
    };

    const getStateIcon = (state: string) => {
        const stateLower = state.toLowerCase();
        if (stateLower === 'running') return <CheckCircle className="h-4 w-4 text-success" />;
        if (stateLower === 'exited' || stateLower === 'dead') return <XCircle className="h-4 w-4 text-danger" />;
        return <Clock className="h-4 w-4 text-warning" />;
    };

    return (
        <div className="space-y-4">
            {/* Info Card */}
            <Card className="border-info/50 bg-info/5">
                <CardContent className="p-4">
                    <div className="flex items-start gap-3">
                        <Info className="h-5 w-5 flex-shrink-0 text-info mt-0.5" />
                        <div>
                            <h3 className="font-medium text-foreground">
                                About Service Rollbacks
                            </h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Docker Compose services don&apos;t have traditional rollback functionality like Git-based applications.
                                Instead, you can redeploy the service to pull the latest images or restart with the current configuration.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Redeploy Actions */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="font-medium text-foreground">Redeploy Service</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Restart the service with optionally pulling the latest images
                            </p>
                        </div>
                        <Button onClick={() => setShowRedeployModal(true)}>
                            <RefreshCw className="mr-2 h-4 w-4" />
                            Redeploy
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Current Containers */}
            <Card>
                <CardContent className="p-4">
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="font-medium text-foreground">Current Containers</h3>
                        <Badge variant="info">
                            {containers.length} container{containers.length !== 1 ? 's' : ''}
                        </Badge>
                    </div>

                    {containers.length === 0 ? (
                        <div className="py-8 text-center">
                            <Container className="mx-auto mb-3 h-10 w-10 text-foreground-subtle" />
                            <p className="text-sm text-foreground-muted">
                                No containers found. The service may not be deployed yet.
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {containers.map((container) => (
                                <div
                                    key={container.id}
                                    className="rounded-lg border border-border bg-background-secondary/50 p-3"
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2">
                                                {getStateIcon(container.state)}
                                                <span className="font-medium text-foreground truncate">
                                                    {container.name}
                                                </span>
                                                <Badge
                                                    variant={container.state.toLowerCase() === 'running' ? 'success' : 'secondary'}
                                                    className="capitalize"
                                                >
                                                    {container.state}
                                                </Badge>
                                            </div>
                                            <div className="mt-2 space-y-1 text-xs text-foreground-muted">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium">Image:</span>
                                                    <code className="rounded bg-background px-1.5 py-0.5 text-foreground">
                                                        {container.image}
                                                    </code>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium">Status:</span>
                                                    <span>{container.status}</span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium">ID:</span>
                                                    <code className="font-mono">{container.id}</code>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Redeploy Confirmation Modal */}
            <Modal
                isOpen={showRedeployModal}
                onClose={() => setShowRedeployModal(false)}
                title="Redeploy Service"
                description="Choose how you want to redeploy the service"
            >
                <div className="space-y-4">
                    <div className="space-y-3">
                        <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-border p-3 hover:bg-background-secondary/50">
                            <input
                                type="radio"
                                name="redeploy_type"
                                checked={pullLatest}
                                onChange={() => setPullLatest(true)}
                                className="mt-1"
                            />
                            <div>
                                <div className="font-medium text-foreground">Pull Latest Images</div>
                                <p className="text-sm text-foreground-muted">
                                    Download the latest versions of all container images and restart the service
                                </p>
                            </div>
                        </label>
                        <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-border p-3 hover:bg-background-secondary/50">
                            <input
                                type="radio"
                                name="redeploy_type"
                                checked={!pullLatest}
                                onChange={() => setPullLatest(false)}
                                className="mt-1"
                            />
                            <div>
                                <div className="font-medium text-foreground">Restart Only</div>
                                <p className="text-sm text-foreground-muted">
                                    Restart the service using the current cached images
                                </p>
                            </div>
                        </label>
                    </div>

                    <div className="rounded-lg border border-warning/50 bg-warning/10 p-4">
                        <div className="flex items-start gap-2">
                            <AlertCircle className="h-5 w-5 text-warning flex-shrink-0 mt-0.5" />
                            <div className="text-sm text-foreground">
                                <p className="font-medium">This action will:</p>
                                <ul className="mt-2 list-disc list-inside space-y-1 text-foreground-muted">
                                    <li>Stop all service containers</li>
                                    {pullLatest && <li>Pull the latest images</li>}
                                    <li>Start the service with new containers</li>
                                    <li>May cause brief downtime</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <ModalFooter>
                        <Button
                            variant="secondary"
                            onClick={() => setShowRedeployModal(false)}
                            disabled={isRedeploying}
                        >
                            Cancel
                        </Button>
                        <Button onClick={handleRedeploy} disabled={isRedeploying}>
                            {isRedeploying ? (
                                <>
                                    <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
                                    Redeploying...
                                </>
                            ) : (
                                <>
                                    <RefreshCw className="mr-2 h-4 w-4" />
                                    Confirm Redeploy
                                </>
                            )}
                        </Button>
                    </ModalFooter>
                </div>
            </Modal>
        </div>
    );
}
