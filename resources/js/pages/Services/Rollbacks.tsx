import { useState } from 'react';
import { Card, CardContent, Badge, Button, Modal, ModalFooter } from '@/components/ui';
import { GitCommit, Clock, User, RotateCw, Eye } from 'lucide-react';
import type { Service } from '@/types';
import { getStatusIcon, getStatusVariant } from '@/lib/statusUtils';

interface Props {
    service: Service;
}

type DeploymentStatus = 'success' | 'failed' | 'rolled_back';

interface Deployment {
    id: number;
    commit: string;
    commitFull: string;
    message: string;
    author: string;
    authorEmail: string;
    status: DeploymentStatus;
    timestamp: string;
    duration: string;
    isActive: boolean;
    changes: {
        filesChanged: number;
        additions: number;
        deletions: number;
    };
}

// Mock deployments data
const mockDeployments: Deployment[] = [
    {
        id: 1,
        commit: 'a1b2c3d',
        commitFull: 'a1b2c3d4e5f6789',
        message: 'feat: Add user authentication with JWT',
        author: 'John Doe',
        authorEmail: 'john@example.com',
        status: 'success',
        timestamp: '2 hours ago',
        duration: '3m 45s',
        isActive: true,
        changes: { filesChanged: 12, additions: 234, deletions: 89 },
    },
    {
        id: 2,
        commit: 'e4f5g6h',
        commitFull: 'e4f5g6h7i8j9012',
        message: 'fix: Resolve memory leak in background worker',
        author: 'Jane Smith',
        authorEmail: 'jane@example.com',
        status: 'success',
        timestamp: '5 hours ago',
        duration: '2m 30s',
        isActive: false,
        changes: { filesChanged: 4, additions: 45, deletions: 67 },
    },
    {
        id: 3,
        commit: 'i7j8k9l',
        commitFull: 'i7j8k9l0m1n2345',
        message: 'refactor: Update database schema for performance',
        author: 'John Doe',
        authorEmail: 'john@example.com',
        status: 'failed',
        timestamp: '1 day ago',
        duration: '1m 15s',
        isActive: false,
        changes: { filesChanged: 8, additions: 156, deletions: 78 },
    },
    {
        id: 4,
        commit: 'm1n2o3p',
        commitFull: 'm1n2o3p4q5r6789',
        message: 'feat: Implement caching layer with Redis',
        author: 'Bob Wilson',
        authorEmail: 'bob@example.com',
        status: 'success',
        timestamp: '2 days ago',
        duration: '4m 20s',
        isActive: false,
        changes: { filesChanged: 15, additions: 345, deletions: 123 },
    },
    {
        id: 5,
        commit: 'q4r5s6t',
        commitFull: 'q4r5s6t7u8v9012',
        message: 'chore: Update dependencies to latest versions',
        author: 'Jane Smith',
        authorEmail: 'jane@example.com',
        status: 'success',
        timestamp: '3 days ago',
        duration: '5m 10s',
        isActive: false,
        changes: { filesChanged: 3, additions: 23, deletions: 45 },
    },
    {
        id: 6,
        commit: 'u8v9w0x',
        commitFull: 'u8v9w0x1y2z3456',
        message: 'feat: Add webhook support for external integrations',
        author: 'Alice Brown',
        authorEmail: 'alice@example.com',
        status: 'rolled_back',
        timestamp: '4 days ago',
        duration: '3m 55s',
        isActive: false,
        changes: { filesChanged: 9, additions: 267, deletions: 34 },
    },
];

export function RollbacksTab({ service }: Props) {
    const [deployments] = useState<Deployment[]>(mockDeployments);
    const [showRollbackModal, setShowRollbackModal] = useState(false);
    const [showDiffModal, setShowDiffModal] = useState(false);
    const [selectedDeployment, setSelectedDeployment] = useState<Deployment | null>(null);

    const handleRollbackClick = (deployment: Deployment) => {
        setSelectedDeployment(deployment);
        setShowRollbackModal(true);
    };

    const handleViewDiff = (deployment: Deployment) => {
        setSelectedDeployment(deployment);
        setShowDiffModal(true);
    };

    const handleConfirmRollback = () => {
        console.log('Rolling back to deployment:', selectedDeployment?.id);
        setShowRollbackModal(false);
        setSelectedDeployment(null);
    };

    return (
        <div className="space-y-4">
            {/* Header Info */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="font-medium text-foreground">Deployment History</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Roll back to any previous deployment with a single click
                            </p>
                        </div>
                        <Badge variant="info">
                            {deployments.length} deployments
                        </Badge>
                    </div>
                </CardContent>
            </Card>

            {/* Deployments List */}
            <div className="space-y-3">
                {deployments.map((deployment) => (
                    <Card key={deployment.id}>
                        <CardContent className="p-4">
                            <div className="flex items-start gap-4">
                                {/* Status Icon */}
                                <div className="mt-1">{getStatusIcon(deployment.status)}</div>

                                {/* Deployment Info */}
                                <div className="flex-1 min-w-0">
                                    {/* Header */}
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <div className="flex items-center gap-2">
                                                    <GitCommit className="h-3.5 w-3.5 text-foreground-muted" />
                                                    <code className="text-sm font-medium text-foreground">
                                                        {deployment.commit}
                                                    </code>
                                                </div>
                                                {deployment.isActive && (
                                                    <Badge variant="success">Active</Badge>
                                                )}
                                                <Badge variant={getStatusVariant(deployment.status)} className="capitalize">
                                                    {deployment.status.replace('_', ' ')}
                                                </Badge>
                                            </div>
                                            <p className="mt-1 text-sm text-foreground line-clamp-1">
                                                {deployment.message}
                                            </p>
                                        </div>

                                        {/* Actions */}
                                        <div className="flex items-center gap-2 flex-shrink-0">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleViewDiff(deployment)}
                                            >
                                                <Eye className="mr-1 h-3 w-3" />
                                                Diff
                                            </Button>
                                            {!deployment.isActive && deployment.status === 'success' && (
                                                <Button
                                                    variant="secondary"
                                                    size="sm"
                                                    onClick={() => handleRollbackClick(deployment)}
                                                >
                                                    <RotateCw className="mr-1 h-3 w-3" />
                                                    Rollback
                                                </Button>
                                            )}
                                        </div>
                                    </div>

                                    {/* Meta Info */}
                                    <div className="mt-3 flex items-center gap-4 text-xs text-foreground-muted flex-wrap">
                                        <div className="flex items-center gap-1">
                                            <User className="h-3 w-3" />
                                            <span>{deployment.author}</span>
                                        </div>
                                        <span>·</span>
                                        <div className="flex items-center gap-1">
                                            <Clock className="h-3 w-3" />
                                            <span>{deployment.timestamp}</span>
                                        </div>
                                        <span>·</span>
                                        <span>Duration: {deployment.duration}</span>
                                        <span>·</span>
                                        <span className="text-primary">+{deployment.changes.additions}</span>
                                        <span className="text-danger">-{deployment.changes.deletions}</span>
                                        <span>({deployment.changes.filesChanged} files)</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {/* Rollback Confirmation Modal */}
            <Modal
                isOpen={showRollbackModal}
                onClose={() => setShowRollbackModal(false)}
                title="Confirm Rollback"
                description="Are you sure you want to roll back to this deployment?"
            >
                {selectedDeployment && (
                    <div className="space-y-4">
                        <div className="rounded-lg border border-border bg-background-tertiary p-4">
                            <div className="flex items-center gap-2">
                                <GitCommit className="h-4 w-4 text-foreground-muted" />
                                <code className="text-sm font-medium text-foreground">
                                    {selectedDeployment.commitFull}
                                </code>
                            </div>
                            <p className="mt-2 text-sm text-foreground">
                                {selectedDeployment.message}
                            </p>
                            <div className="mt-3 flex items-center gap-3 text-xs text-foreground-muted">
                                <span>{selectedDeployment.author}</span>
                                <span>·</span>
                                <span>{selectedDeployment.timestamp}</span>
                            </div>
                        </div>

                        <div className="rounded-lg border border-warning bg-warning/10 p-4">
                            <div className="flex items-start gap-2">
                                <AlertCircle className="h-5 w-5 text-warning flex-shrink-0 mt-0.5" />
                                <div className="text-sm text-foreground">
                                    <p className="font-medium">This action will:</p>
                                    <ul className="mt-2 list-disc list-inside space-y-1 text-foreground-muted">
                                        <li>Stop the current deployment</li>
                                        <li>Deploy the selected version</li>
                                        <li>May cause brief downtime</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <ModalFooter>
                            <Button
                                variant="secondary"
                                onClick={() => setShowRollbackModal(false)}
                            >
                                Cancel
                            </Button>
                            <Button onClick={handleConfirmRollback}>
                                <RotateCw className="mr-2 h-4 w-4" />
                                Confirm Rollback
                            </Button>
                        </ModalFooter>
                    </div>
                )}
            </Modal>

            {/* Diff View Modal */}
            <Modal
                isOpen={showDiffModal}
                onClose={() => setShowDiffModal(false)}
                title="Deployment Changes"
                size="xl"
            >
                {selectedDeployment && (
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <GitCommit className="h-4 w-4 text-foreground-muted" />
                                <code className="text-sm font-medium text-foreground">
                                    {selectedDeployment.commitFull}
                                </code>
                            </div>
                            <div className="flex items-center gap-2 text-sm">
                                <span className="text-primary">+{selectedDeployment.changes.additions}</span>
                                <span className="text-danger">-{selectedDeployment.changes.deletions}</span>
                            </div>
                        </div>

                        <p className="text-sm text-foreground">{selectedDeployment.message}</p>

                        {/* Mock diff view */}
                        <div className="rounded-lg border border-border bg-background font-mono text-xs">
                            <div className="border-b border-border bg-background-secondary px-3 py-2">
                                <span className="text-foreground-muted">src/auth/login.ts</span>
                            </div>
                            <div className="p-3 space-y-0.5">
                                <div className="text-foreground-muted">@@ -12,7 +12,8 @@ export function login()</div>
                                <div className="text-foreground-muted pl-4">function authenticateUser(credentials) {'{}'}</div>
                                <div className="bg-danger/20 text-danger pl-4">-  const token = generateToken();</div>
                                <div className="bg-primary/20 text-primary pl-4">+  const token = generateJWT();</div>
                                <div className="bg-primary/20 text-primary pl-4">+  const refreshToken = generateRefreshToken();</div>
                                <div className="text-foreground-muted pl-4">  return token;</div>
                            </div>
                        </div>

                        <ModalFooter>
                            <Button
                                variant="secondary"
                                onClick={() => setShowDiffModal(false)}
                            >
                                Close
                            </Button>
                        </ModalFooter>
                    </div>
                )}
            </Modal>
        </div>
    );
}
