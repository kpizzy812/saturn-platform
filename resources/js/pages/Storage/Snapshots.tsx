import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Badge, Input, Modal, ModalFooter } from '@/components/ui';
import { ArrowLeft, Plus, RotateCcw, Trash2, Clock, HardDrive, Camera, AlertCircle } from 'lucide-react';

interface Props {
    volumeId?: string;
    volumeName?: string;
}

interface Snapshot {
    id: number;
    name: string;
    size: string;
    source_volume: string;
    created_at: string;
}

export default function StorageSnapshots({ volumeId = 'vol_123', volumeName = 'app-data' }: Props) {
    const [snapshots] = useState<Snapshot[]>([]);
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showRestoreModal, setShowRestoreModal] = useState(false);
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [selectedSnapshot, setSelectedSnapshot] = useState<Snapshot | null>(null);
    const [newSnapshotName, setNewSnapshotName] = useState('');

    const handleCreateSnapshot = () => {
        setShowCreateModal(true);
        setNewSnapshotName(`snapshot-${new Date().toISOString().split('T')[0]}`);
    };

    const confirmCreate = () => {
        if (newSnapshotName.trim()) {
            router.post(`/storage/${volumeId}/snapshots`, {
                name: newSnapshotName,
            });
            setShowCreateModal(false);
            setNewSnapshotName('');
        }
    };

    const handleRestore = (snapshot: Snapshot) => {
        setSelectedSnapshot(snapshot);
        setShowRestoreModal(true);
    };

    const confirmRestore = () => {
        if (selectedSnapshot) {
            router.post(`/storage/${volumeId}/snapshots/${selectedSnapshot.id}/restore`);
            setShowRestoreModal(false);
            setSelectedSnapshot(null);
        }
    };

    const handleDelete = (snapshot: Snapshot) => {
        setSelectedSnapshot(snapshot);
        setShowDeleteModal(true);
    };

    const confirmDelete = () => {
        if (selectedSnapshot) {
            router.delete(`/storage/${volumeId}/snapshots/${selectedSnapshot.id}`);
            setShowDeleteModal(false);
            setSelectedSnapshot(null);
        }
    };

    return (
        <AppLayout
            title={`${volumeName} - Snapshots`}
            breadcrumbs={[
                { label: 'Storage', href: '/volumes' },
                { label: volumeName, href: `/volumes/${volumeId}` },
                { label: 'Snapshots' }
            ]}
        >
            {/* Back Button */}
            <Link
                href={`/volumes/${volumeId}`}
                className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
            >
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back to {volumeName}
            </Link>

            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Volume Snapshots</h1>
                    <p className="text-foreground-muted">Point-in-time snapshots of {volumeName}</p>
                </div>
                <Button onClick={handleCreateSnapshot}>
                    <Plus className="mr-2 h-4 w-4" />
                    Create Snapshot
                </Button>
            </div>

            {/* Info Card */}
            <Card className="mb-6">
                <CardContent className="p-6">
                    <div className="flex items-start gap-3">
                        <Camera className="mt-1 h-5 w-5 text-foreground-muted" />
                        <div className="flex-1">
                            <h3 className="font-medium text-foreground">About Snapshots</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Snapshots capture the state of your volume at a specific point in time. You can use them to restore your data or create new volumes.
                            </p>
                            <div className="mt-3 flex items-center gap-4 text-sm">
                                <div className="flex items-center gap-2">
                                    <Badge variant="info">Instant</Badge>
                                    <span className="text-foreground-muted">Created in seconds</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge variant="success">Incremental</Badge>
                                    <span className="text-foreground-muted">Only changes are stored</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Snapshots List */}
            <div className="space-y-3">
                <h2 className="text-lg font-medium text-foreground">Snapshots ({snapshots.length})</h2>

                {snapshots.length === 0 ? (
                    <Card>
                        <CardContent className="p-12 text-center">
                            <Camera className="mx-auto h-12 w-12 text-foreground-subtle" />
                            <h3 className="mt-4 font-medium text-foreground">No snapshots yet</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Create your first snapshot to preserve the current state
                            </p>
                            <Button onClick={handleCreateSnapshot} className="mt-6">
                                <Plus className="mr-2 h-4 w-4" />
                                Create Snapshot
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-2">
                        {snapshots.map((snapshot) => (
                            <SnapshotCard
                                key={snapshot.id}
                                snapshot={snapshot}
                                onRestore={() => handleRestore(snapshot)}
                                onDelete={() => handleDelete(snapshot)}
                            />
                        ))}
                    </div>
                )}
            </div>

            {/* Create Snapshot Modal */}
            <Modal
                isOpen={showCreateModal}
                onClose={() => setShowCreateModal(false)}
                title="Create Snapshot"
                description="Create a point-in-time snapshot of this volume"
            >
                <div className="space-y-4">
                    <Input
                        label="Snapshot Name"
                        value={newSnapshotName}
                        onChange={(e) => setNewSnapshotName(e.target.value)}
                        placeholder="my-snapshot"
                        hint="Use a descriptive name to identify this snapshot later"
                    />

                    <div className="rounded-lg border border-border bg-background-tertiary p-4">
                        <p className="text-sm font-medium text-foreground">Snapshot Details</p>
                        <div className="mt-2 space-y-1 text-sm text-foreground-muted">
                            <p>Source Volume: {volumeName}</p>
                            <p>Created: {new Date().toLocaleString()}</p>
                        </div>
                    </div>

                    <ModalFooter>
                        <Button variant="secondary" onClick={() => setShowCreateModal(false)}>
                            Cancel
                        </Button>
                        <Button onClick={confirmCreate} disabled={!newSnapshotName.trim()}>
                            Create Snapshot
                        </Button>
                    </ModalFooter>
                </div>
            </Modal>

            {/* Restore Confirmation Modal */}
            <Modal
                isOpen={showRestoreModal}
                onClose={() => setShowRestoreModal(false)}
                title="Restore Snapshot"
                description="Are you sure you want to restore this snapshot?"
            >
                {selectedSnapshot && (
                    <div className="space-y-4">
                        <div className="rounded-lg border border-border bg-background-tertiary p-4">
                            <p className="text-sm font-medium text-foreground">Snapshot Details</p>
                            <div className="mt-2 space-y-1 text-sm text-foreground-muted">
                                <p>Name: {selectedSnapshot.name}</p>
                                <p>Size: {selectedSnapshot.size}</p>
                                <p>Source: {selectedSnapshot.source_volume}</p>
                                <p>Created: {new Date(selectedSnapshot.created_at).toLocaleString()}</p>
                            </div>
                        </div>

                        <div className="rounded-lg border border-warning/50 bg-warning/10 p-4">
                            <div className="flex items-start gap-3">
                                <AlertCircle className="mt-0.5 h-4 w-4 text-warning" />
                                <div>
                                    <p className="text-sm font-medium text-warning">Warning</p>
                                    <p className="mt-1 text-sm text-foreground-muted">
                                        This will overwrite the current volume data with the snapshot. Any changes made after the snapshot was created will be lost.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <ModalFooter>
                            <Button variant="secondary" onClick={() => setShowRestoreModal(false)}>
                                Cancel
                            </Button>
                            <Button variant="danger" onClick={confirmRestore}>
                                Restore Snapshot
                            </Button>
                        </ModalFooter>
                    </div>
                )}
            </Modal>

            {/* Delete Confirmation Modal */}
            <Modal
                isOpen={showDeleteModal}
                onClose={() => setShowDeleteModal(false)}
                title="Delete Snapshot"
                description="Are you sure you want to delete this snapshot?"
            >
                {selectedSnapshot && (
                    <div className="space-y-4">
                        <div className="rounded-lg border border-border bg-background-tertiary p-4">
                            <p className="text-sm font-medium text-foreground">Snapshot Details</p>
                            <div className="mt-2 space-y-1 text-sm text-foreground-muted">
                                <p>Name: {selectedSnapshot.name}</p>
                                <p>Size: {selectedSnapshot.size}</p>
                                <p>Created: {new Date(selectedSnapshot.created_at).toLocaleString()}</p>
                            </div>
                        </div>

                        <div className="rounded-lg border border-danger/50 bg-danger/10 p-4">
                            <p className="text-sm font-medium text-danger">This action cannot be undone</p>
                            <p className="mt-1 text-sm text-foreground-muted">
                                Once deleted, you won't be able to restore from this snapshot.
                            </p>
                        </div>

                        <ModalFooter>
                            <Button variant="secondary" onClick={() => setShowDeleteModal(false)}>
                                Cancel
                            </Button>
                            <Button variant="danger" onClick={confirmDelete}>
                                Delete Snapshot
                            </Button>
                        </ModalFooter>
                    </div>
                )}
            </Modal>
        </AppLayout>
    );
}

function SnapshotCard({
    snapshot,
    onRestore,
    onDelete,
}: {
    snapshot: Snapshot;
    onRestore: () => void;
    onDelete: () => void;
}) {
    return (
        <Card>
            <CardContent className="p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-background-tertiary">
                            <Camera className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <h3 className="font-medium text-foreground">{snapshot.name}</h3>
                            <div className="mt-1 flex items-center gap-4 text-sm text-foreground-muted">
                                <span className="flex items-center gap-1">
                                    <HardDrive className="h-3.5 w-3.5" />
                                    {snapshot.size}
                                </span>
                                <span className="flex items-center gap-1">
                                    <Clock className="h-3.5 w-3.5" />
                                    {new Date(snapshot.created_at).toLocaleString()}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <Button variant="secondary" size="sm" onClick={onRestore}>
                            <RotateCcw className="mr-2 h-4 w-4" />
                            Restore
                        </Button>
                        <Button variant="danger" size="sm" onClick={onDelete}>
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
