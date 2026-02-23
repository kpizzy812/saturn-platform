import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Badge, Input } from '@/components/ui';
import { Progress } from '@/components/ui/Progress';
import { Plus, HardDrive, Grid3x3, List, Search, Filter } from 'lucide-react';
import { StaggerList, StaggerItem, FadeIn } from '@/components/animation';
import type { Volume } from '@/types';

interface Props {
    volumes?: Volume[];
}

type ViewMode = 'grid' | 'list';
type FilterStatus = 'all' | 'active' | 'creating' | 'error';

export default function VolumesIndex({ volumes }: Props) {
    const [viewMode, setViewMode] = useState<ViewMode>('grid');
    const [filterStatus, setFilterStatus] = useState<FilterStatus>('all');
    const [searchQuery, setSearchQuery] = useState('');

    // Show loading state when volumes data is not yet available
    if (!volumes) {
        return (
            <AppLayout
                title="Volumes"
                breadcrumbs={[{ label: 'Volumes' }]}
            >
                <div className="flex items-center justify-center p-12">
                    <div className="text-center">
                        <HardDrive className="mx-auto h-12 w-12 animate-pulse text-foreground-muted" />
                        <p className="mt-4 text-foreground-muted">Loading volumes...</p>
                    </div>
                </div>
            </AppLayout>
        );
    }

    // Filter volumes
    const filteredVolumes = volumes.filter(volume => {
        const matchesSearch = volume.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            volume.description?.toLowerCase().includes(searchQuery.toLowerCase());
        const matchesStatus = filterStatus === 'all' || volume.status === filterStatus;
        return matchesSearch && matchesStatus;
    });

    // Calculate total storage
    const totalSize = volumes.reduce((sum, vol) => sum + vol.size, 0);
    const totalUsed = volumes.reduce((sum, vol) => sum + vol.used, 0);

    return (
        <AppLayout
            title="Volumes"
            breadcrumbs={[{ label: 'Volumes' }]}
        >
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Volumes</h1>
                    <p className="text-foreground-muted">Persistent storage for your services</p>
                </div>
                <Link href="/volumes/create">
                    <Button className="group">
                        <Plus className="mr-2 h-4 w-4 group-hover:animate-wiggle" />
                        Create Volume
                    </Button>
                </Link>
            </div>

            {/* Storage Overview */}
            <div className="mb-6 grid gap-4 md:grid-cols-3">
                <Card>
                    <CardContent className="p-4">
                        <div className="text-sm text-foreground-muted">Total Volumes</div>
                        <div className="mt-1 text-2xl font-bold text-foreground">{volumes.length}</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="text-sm text-foreground-muted">Total Storage</div>
                        <div className="mt-1 text-2xl font-bold text-foreground">{totalSize} GB</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-4">
                        <div className="text-sm text-foreground-muted">Used Storage</div>
                        <div className="mt-1 text-2xl font-bold text-foreground">{totalUsed} GB</div>
                        <Progress value={totalUsed} max={totalSize} className="mt-2" size="sm" />
                    </CardContent>
                </Card>
            </div>

            {/* Filters and View Toggle */}
            <div className="mb-6 flex items-center justify-between gap-4">
                <div className="flex flex-1 items-center gap-3">
                    <div className="relative flex-1 max-w-md">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                        <Input
                            placeholder="Search volumes..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <Filter className="h-4 w-4 text-foreground-muted" />
                        {(['all', 'active', 'creating', 'error'] as const).map((status) => (
                            <button
                                key={status}
                                onClick={() => setFilterStatus(status)}
                                className={`rounded-md border px-3 py-1.5 text-sm transition-colors ${
                                    filterStatus === status
                                        ? 'border-primary bg-primary/10 text-primary'
                                        : 'border-border bg-background-secondary text-foreground-muted hover:bg-background-tertiary'
                                }`}
                            >
                                {status.charAt(0).toUpperCase() + status.slice(1)}
                            </button>
                        ))}
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <button
                        onClick={() => setViewMode('grid')}
                        className={`rounded-md border p-2 transition-colors ${
                            viewMode === 'grid'
                                ? 'border-primary bg-primary/10 text-primary'
                                : 'border-border bg-background-secondary text-foreground-muted hover:bg-background-tertiary'
                        }`}
                    >
                        <Grid3x3 className="h-4 w-4" />
                    </button>
                    <button
                        onClick={() => setViewMode('list')}
                        className={`rounded-md border p-2 transition-colors ${
                            viewMode === 'list'
                                ? 'border-primary bg-primary/10 text-primary'
                                : 'border-border bg-background-secondary text-foreground-muted hover:bg-background-tertiary'
                        }`}
                    >
                        <List className="h-4 w-4" />
                    </button>
                </div>
            </div>

            {/* Volumes Grid/List */}
            {filteredVolumes.length === 0 ? (
                <EmptyState hasSearch={searchQuery.length > 0} />
            ) : viewMode === 'grid' ? (
                <StaggerList className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {filteredVolumes.map((volume, i) => (
                        <StaggerItem key={volume.id} index={i}>
                            <VolumeCard volume={volume} />
                        </StaggerItem>
                    ))}
                </StaggerList>
            ) : (
                <StaggerList className="space-y-2">
                    {filteredVolumes.map((volume, i) => (
                        <StaggerItem key={volume.id} index={i}>
                            <VolumeListItem volume={volume} />
                        </StaggerItem>
                    ))}
                </StaggerList>
            )}
        </AppLayout>
    );
}

function VolumeCard({ volume }: { volume: Volume }) {
    return (
        <Link href={`/volumes/${volume.uuid}`}>
            <Card className="transition-all hover:border-primary/50 hover:shadow-lg">
                <CardContent className="p-4">
                    <div className="flex items-start justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-background-tertiary">
                                <HardDrive className="h-5 w-5 text-foreground-muted" />
                            </div>
                            <div>
                                <h3 className="font-medium text-foreground">{volume.name}</h3>
                                <p className="mt-0.5 text-xs text-foreground-muted">
                                    {volume.description || 'No description'}
                                </p>
                            </div>
                        </div>
                        <VolumeStatusBadge status={volume.status} />
                    </div>

                    <div className="mt-4 space-y-2">
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-foreground-muted">Storage</span>
                            <span className="font-medium text-foreground">
                                {volume.used} GB / {volume.size} GB
                            </span>
                        </div>
                        <Progress value={volume.used} max={volume.size} size="sm" />
                    </div>

                    <div className="mt-4 flex items-center justify-between text-xs text-foreground-muted">
                        <StorageClassBadge storageClass={volume.storage_class} />
                        <span>
                            {volume.attached_services.length > 0
                                ? `${volume.attached_services.length} service${volume.attached_services.length > 1 ? 's' : ''}`
                                : 'Not attached'}
                        </span>
                    </div>
                </CardContent>
            </Card>
        </Link>
    );
}

function VolumeListItem({ volume }: { volume: Volume }) {
    return (
        <Link href={`/volumes/${volume.uuid}`}>
            <Card className="transition-all hover:border-primary/50">
                <CardContent className="p-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-background-tertiary">
                                <HardDrive className="h-5 w-5 text-foreground-muted" />
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h3 className="font-medium text-foreground">{volume.name}</h3>
                                    <VolumeStatusBadge status={volume.status} />
                                    <StorageClassBadge storageClass={volume.storage_class} />
                                </div>
                                <p className="mt-0.5 text-sm text-foreground-muted">
                                    {volume.description || 'No description'}
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center gap-8">
                            <div className="w-48">
                                <div className="flex items-center justify-between text-xs text-foreground-muted mb-1">
                                    <span>Usage</span>
                                    <span className="font-medium">
                                        {volume.used} / {volume.size} GB
                                    </span>
                                </div>
                                <Progress value={volume.used} max={volume.size} size="sm" />
                            </div>
                            <div className="text-sm text-foreground-muted w-32 text-right">
                                {volume.attached_services.length > 0
                                    ? volume.attached_services[0].name
                                    : 'Not attached'}
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </Link>
    );
}

function VolumeStatusBadge({ status }: { status: Volume['status'] }) {
    const variants: Record<Volume['status'], { variant: 'default' | 'success' | 'warning' | 'danger'; label: string }> = {
        active: { variant: 'success', label: 'Active' },
        creating: { variant: 'warning', label: 'Creating' },
        deleting: { variant: 'warning', label: 'Deleting' },
        error: { variant: 'danger', label: 'Error' },
    };

    const { variant, label } = variants[status];
    return <Badge variant={variant}>{label}</Badge>;
}

function StorageClassBadge({ storageClass }: { storageClass: Volume['storage_class'] }) {
    const labels: Record<Volume['storage_class'], string> = {
        standard: 'Standard',
        fast: 'Fast SSD',
        archive: 'Archive',
    };

    return (
        <span className="rounded-full bg-background-tertiary px-2 py-0.5 text-xs text-foreground-muted">
            {labels[storageClass]}
        </span>
    );
}

function EmptyState({ hasSearch }: { hasSearch: boolean }) {
    if (hasSearch) {
        return (
            <FadeIn>
                <Card className="p-12 text-center">
                    <HardDrive className="mx-auto h-12 w-12 text-foreground-muted animate-pulse-soft" />
                    <h3 className="mt-4 text-lg font-medium text-foreground">No volumes found</h3>
                    <p className="mt-2 text-foreground-muted">
                        Try adjusting your search query or filters
                    </p>
                </Card>
            </FadeIn>
        );
    }

    return (
        <FadeIn>
            <Card className="p-12 text-center">
                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                    <HardDrive className="h-8 w-8 text-foreground-muted animate-pulse-soft" />
                </div>
                <h3 className="mt-4 text-lg font-medium text-foreground">No volumes yet</h3>
                <p className="mt-2 text-foreground-muted">
                    Create your first volume to provide persistent storage for your services.
                </p>
                <Link href="/volumes/create" className="mt-6 inline-block">
                    <Button>
                        <Plus className="mr-2 h-4 w-4" />
                        Create Volume
                    </Button>
                </Link>
            </Card>
        </FadeIn>
    );
}
