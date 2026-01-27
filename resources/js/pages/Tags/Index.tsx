import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Input, Badge, useConfirm } from '@/components/ui';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';
import {
    Plus,
    Search,
    Filter,
    Tag as TagIcon,
    MoreVertical,
    Edit2,
    Trash2,
    Users,
    Package,
    Server as ServerIcon,
    Database as DatabaseIcon,
} from 'lucide-react';
import type { TagWithResources } from '@/types';

interface Props {
    tags: TagWithResources[];
}

export default function TagsIndex({ tags = [] }: Props) {
    const confirm = useConfirm();
    const [searchQuery, setSearchQuery] = useState('');
    const [showCreateModal, setShowCreateModal] = useState(false);

    // Filter tags
    const filteredTags = tags.filter(tag =>
        tag.name.toLowerCase().includes(searchQuery.toLowerCase())
    );

    const handleDelete = async (tagId: number, e: React.MouseEvent) => {
        e.preventDefault();
        const confirmed = await confirm({
            title: 'Delete Tag',
            description: 'Are you sure you want to delete this tag? This action cannot be undone.',
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/tags/${tagId}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <AppLayout
            title="Tags"
            breadcrumbs={[{ label: 'Tags' }]}
        >
            <div className="mx-auto max-w-7xl px-6 py-8">
                {/* Header */}
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Tags</h1>
                        <p className="text-foreground-muted">Organize and manage your resources with tags</p>
                    </div>
                    <Button onClick={() => setShowCreateModal(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        New Tag
                    </Button>
                </div>

                {/* Search */}
                <div className="mb-6">
                    <div className="relative flex-1 max-w-md">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                        <Input
                            placeholder="Search tags..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                </div>

                {/* Tags Grid */}
                {filteredTags.length === 0 ? (
                    tags.length === 0 ? <EmptyState onCreateClick={() => setShowCreateModal(true)} /> : <NoResults />
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {filteredTags.map((tag) => (
                            <TagCard
                                key={tag.id}
                                tag={tag}
                                onDelete={(e) => handleDelete(tag.id, e)}
                            />
                        ))}
                    </div>
                )}
            </div>

            {/* Create Modal */}
            {showCreateModal && (
                <CreateTagModal onClose={() => setShowCreateModal(false)} />
            )}
        </AppLayout>
    );
}

interface TagCardProps {
    tag: TagWithResources;
    onDelete: (e: React.MouseEvent) => void;
}

function TagCard({ tag, onDelete }: TagCardProps) {
    const totalResources = tag.applications_count + tag.services_count + (tag.databases_count || 0);
    const tagColor = tag.color || '#6366f1';

    return (
        <Link href={`/tags/${tag.name}`}>
            <Card className="transition-all hover:border-primary/50 hover:shadow-lg">
                <CardContent className="p-4">
                    {/* Header */}
                    <div className="flex items-start justify-between">
                        <div className="flex items-center gap-3 flex-1 min-w-0">
                            <div
                                className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
                                style={{ backgroundColor: `${tagColor}20` }}
                            >
                                <TagIcon className="h-5 w-5" style={{ color: tagColor }} />
                            </div>
                            <div className="min-w-0 flex-1">
                                <h3 className="font-medium text-foreground truncate">{tag.name}</h3>
                                <p className="text-sm text-foreground-muted">
                                    {totalResources} {totalResources === 1 ? 'resource' : 'resources'}
                                </p>
                            </div>
                        </div>
                        <Dropdown>
                            <DropdownTrigger>
                                <button
                                    className="rounded-md p-1 text-foreground-muted hover:bg-background-tertiary hover:text-foreground"
                                    onClick={(e) => e.preventDefault()}
                                >
                                    <MoreVertical className="h-4 w-4" />
                                </button>
                            </DropdownTrigger>
                            <DropdownContent align="right">
                                <DropdownItem onClick={(e) => {
                                    e.preventDefault();
                                    router.visit(`/tags/${tag.name}`);
                                }}>
                                    <Edit2 className="h-4 w-4" />
                                    Edit Tag
                                </DropdownItem>
                                <DropdownDivider />
                                <DropdownItem onClick={onDelete} danger>
                                    <Trash2 className="h-4 w-4" />
                                    Delete
                                </DropdownItem>
                            </DropdownContent>
                        </Dropdown>
                    </div>

                    {/* Resource Breakdown */}
                    <div className="mt-4 space-y-2">
                        {tag.applications_count > 0 && (
                            <div className="flex items-center justify-between text-xs">
                                <div className="flex items-center gap-2 text-foreground-muted">
                                    <Package className="h-3 w-3" />
                                    <span>Applications</span>
                                </div>
                                <Badge variant="outline" className="text-xs">
                                    {tag.applications_count}
                                </Badge>
                            </div>
                        )}
                        {tag.services_count > 0 && (
                            <div className="flex items-center justify-between text-xs">
                                <div className="flex items-center gap-2 text-foreground-muted">
                                    <ServerIcon className="h-3 w-3" />
                                    <span>Services</span>
                                </div>
                                <Badge variant="outline" className="text-xs">
                                    {tag.services_count}
                                </Badge>
                            </div>
                        )}
                        {(tag.databases_count || 0) > 0 && (
                            <div className="flex items-center justify-between text-xs">
                                <div className="flex items-center gap-2 text-foreground-muted">
                                    <DatabaseIcon className="h-3 w-3" />
                                    <span>Databases</span>
                                </div>
                                <Badge variant="outline" className="text-xs">
                                    {tag.databases_count}
                                </Badge>
                            </div>
                        )}
                    </div>

                    {/* Last updated */}
                    <p className="mt-4 text-xs text-foreground-subtle">
                        Updated {new Date(tag.updated_at).toLocaleDateString()}
                    </p>
                </CardContent>
            </Card>
        </Link>
    );
}

function EmptyState({ onCreateClick }: { onCreateClick: () => void }) {
    return (
        <Card className="p-12 text-center">
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                <TagIcon className="h-8 w-8 text-foreground-muted" />
            </div>
            <h3 className="mt-4 text-lg font-medium text-foreground">No tags yet</h3>
            <p className="mt-2 text-foreground-muted">
                Create tags to organize your applications, services, and databases.
            </p>
            <Button onClick={onCreateClick} className="mt-6">
                <Plus className="mr-2 h-4 w-4" />
                Create Tag
            </Button>
        </Card>
    );
}

function NoResults() {
    return (
        <Card className="p-12 text-center">
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                <Filter className="h-8 w-8 text-foreground-muted" />
            </div>
            <h3 className="mt-4 text-lg font-medium text-foreground">No tags found</h3>
            <p className="mt-2 text-foreground-muted">
                Try adjusting your search query.
            </p>
        </Card>
    );
}

interface CreateTagModalProps {
    onClose: () => void;
}

function CreateTagModal({ onClose }: CreateTagModalProps) {
    const [name, setName] = useState('');
    const [color, setColor] = useState('#6366f1');

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/tags', { name, color }, {
            onSuccess: () => onClose(),
            preserveScroll: true,
        });
    };

    const colorPresets = [
        '#6366f1', // indigo
        '#8b5cf6', // violet
        '#ec4899', // pink
        '#f59e0b', // amber
        '#10b981', // emerald
        '#06b6d4', // cyan
        '#ef4444', // red
        '#64748b', // slate
    ];

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={onClose}>
            <Card className="w-full max-w-md" onClick={(e) => e.stopPropagation()}>
                <CardContent className="p-6">
                    <h2 className="text-xl font-bold text-foreground mb-4">Create New Tag</h2>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-foreground mb-2">
                                Tag Name
                            </label>
                            <Input
                                type="text"
                                placeholder="e.g. production, staging, frontend"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                required
                                autoFocus
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-foreground mb-2">
                                Color
                            </label>
                            <div className="flex gap-2 flex-wrap">
                                {colorPresets.map((presetColor) => (
                                    <button
                                        key={presetColor}
                                        type="button"
                                        className={`h-10 w-10 rounded-lg border-2 transition-all ${
                                            color === presetColor
                                                ? 'border-foreground scale-110'
                                                : 'border-transparent hover:border-foreground-muted'
                                        }`}
                                        style={{ backgroundColor: presetColor }}
                                        onClick={() => setColor(presetColor)}
                                    />
                                ))}
                                <input
                                    type="color"
                                    value={color}
                                    onChange={(e) => setColor(e.target.value)}
                                    className="h-10 w-10 cursor-pointer rounded-lg border-2 border-border"
                                />
                            </div>
                        </div>

                        <div className="flex justify-end gap-3 pt-4">
                            <Button type="button" variant="outline" onClick={onClose}>
                                Cancel
                            </Button>
                            <Button type="submit">
                                Create Tag
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
