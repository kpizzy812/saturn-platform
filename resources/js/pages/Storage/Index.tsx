import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Input } from '@/components/ui';
import {
    Plus,
    Search,
    Database,
    CheckCircle,
    XCircle,
    Server,
    Cloud
} from 'lucide-react';
import { StaggerList, StaggerItem, FadeIn } from '@/components/animation';
import type { S3Storage } from '@/types';

interface Props {
    storages: S3Storage[];
}

// Provider configuration for display
const providerInfo: Record<string, { name: string; color: string; icon: string }> = {
    aws: { name: 'AWS S3', color: 'bg-gradient-to-br from-orange-500 to-orange-600', icon: '🔶' },
    wasabi: { name: 'Wasabi', color: 'bg-gradient-to-br from-green-500 to-green-600', icon: '🌿' },
    backblaze: { name: 'Backblaze B2', color: 'bg-gradient-to-br from-red-500 to-red-600', icon: '🔴' },
    minio: { name: 'MinIO', color: 'bg-gradient-to-br from-pink-500 to-pink-600', icon: '🪣' },
    custom: { name: 'Custom S3', color: 'bg-gradient-to-br from-blue-500 to-blue-600', icon: '☁️' },
};

// Detect provider from endpoint
function detectProvider(storage: S3Storage): string {
    const endpoint = storage.endpoint?.toLowerCase() || '';
    if (!endpoint || endpoint.includes('amazonaws.com')) return 'aws';
    if (endpoint.includes('wasabi')) return 'wasabi';
    if (endpoint.includes('backblaze')) return 'backblaze';
    if (endpoint.includes('minio')) return 'minio';
    return 'custom';
}

export default function StorageIndex({ storages = [] }: Props) {
    const [searchQuery, setSearchQuery] = useState('');

    // Filter storages
    const filteredStorages = storages.filter(storage =>
        storage.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        storage.bucket.toLowerCase().includes(searchQuery.toLowerCase())
    );

    return (
        <AppLayout
            title="Storage"
            breadcrumbs={[{ label: 'Storage' }]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Storage</h1>
                        <p className="text-foreground-muted">Manage S3-compatible backup destinations</p>
                    </div>
                    <Link href="/storage/create">
                        <Button className="group">
                            <Plus className="mr-2 h-4 w-4 group-hover:animate-wiggle" />
                            Add Storage
                        </Button>
                    </Link>
                </div>

                {/* Search */}
                <div className="mb-6">
                    <div className="relative max-w-md">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                        <Input
                            placeholder="Search storage..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                </div>

                {/* Storage Grid */}
                {filteredStorages.length === 0 ? (
                    storages.length === 0 ? <EmptyState /> : <NoResults />
                ) : (
                    <StaggerList className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {filteredStorages.map((storage, i) => (
                            <StaggerItem key={storage.id} index={i}>
                                <StorageCard storage={storage} />
                            </StaggerItem>
                        ))}
                    </StaggerList>
                )}
            </div>
        </AppLayout>
    );
}

interface StorageCardProps {
    storage: S3Storage;
}

function StorageCard({ storage }: StorageCardProps) {
    const provider = detectProvider(storage);
    const info = providerInfo[provider];

    return (
        <Link href={`/storage/${storage.uuid}`}>
            <Card className="transition-all hover:border-primary/50 hover:shadow-lg">
                <CardContent className="p-4">
                    {/* Header */}
                    <div className="flex items-start justify-between">
                        <div className="flex items-center gap-3 flex-1 min-w-0">
                            <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ${info.color} text-white text-xl`}>
                                {info.icon}
                            </div>
                            <div className="min-w-0 flex-1">
                                <h3 className="font-medium text-foreground truncate">{storage.name}</h3>
                                <p className="text-sm text-foreground-muted truncate">
                                    {info.name}
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Status & Info */}
                    <div className="mt-4 space-y-2">
                        <div className="flex items-center justify-between">
                            {storage.is_usable ? (
                                <div className="flex items-center gap-1.5 text-sm text-success">
                                    <CheckCircle className="h-4 w-4" />
                                    <span>Connected</span>
                                </div>
                            ) : (
                                <div className="flex items-center gap-1.5 text-sm text-danger">
                                    <XCircle className="h-4 w-4" />
                                    <span>Connection Failed</span>
                                </div>
                            )}
                        </div>

                        {/* Bucket Info */}
                        <div className="flex items-center gap-2 text-xs text-foreground-muted">
                            <Database className="h-3 w-3" />
                            <span className="truncate">{storage.bucket}</span>
                        </div>

                        {/* Region */}
                        <div className="flex items-center gap-2 text-xs text-foreground-muted">
                            <Server className="h-3 w-3" />
                            <span>{storage.region}</span>
                        </div>

                        {/* Description */}
                        {storage.description && (
                            <p className="text-xs text-foreground-subtle truncate">
                                {storage.description}
                            </p>
                        )}
                    </div>

                    {/* Last updated */}
                    <p className="mt-4 text-xs text-foreground-subtle">
                        Updated {new Date(storage.updated_at).toLocaleDateString()}
                    </p>
                </CardContent>
            </Card>
        </Link>
    );
}

function EmptyState() {
    return (
        <FadeIn>
            <Card className="p-12 text-center">
                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                    <Cloud className="h-8 w-8 text-foreground-muted animate-pulse-soft" />
                </div>
                <h3 className="mt-4 text-lg font-medium text-foreground">No storage configured</h3>
                <p className="mt-2 text-foreground-muted">
                    Add an S3-compatible storage provider to enable database backups.
                </p>
                <Link href="/storage/create" className="mt-6 inline-block">
                    <Button>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Storage
                    </Button>
                </Link>
            </Card>
        </FadeIn>
    );
}

function NoResults() {
    return (
        <FadeIn>
            <Card className="p-12 text-center">
                <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                    <Search className="h-8 w-8 text-foreground-muted animate-pulse-soft" />
                </div>
                <h3 className="mt-4 text-lg font-medium text-foreground">No storage found</h3>
                <p className="mt-2 text-foreground-muted">
                    Try adjusting your search query.
                </p>
            </Card>
        </FadeIn>
    );
}
