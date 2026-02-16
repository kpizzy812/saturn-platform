import * as React from 'react';
import DOMPurify from 'dompurify';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import { useConfirm } from '@/components/ui';
import {
    Search,
    LayoutTemplate,
    Plus,
    Star,
    Download,
    CheckCircle,
    Filter,
    Copy,
    Trash2,
} from 'lucide-react';

interface Template {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    description?: string;
    category: string;
    icon?: string;
    is_official: boolean;
    is_public: boolean;
    version: string;
    tags: string[];
    usage_count: number;
    rating?: number;
    rating_count: number;
    created_by?: string;
    created_at: string;
}

interface Props {
    templates: {
        data: Template[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        last_page: number;
        total: number;
    };
    categories: Record<string, string>;
    filters: {
        search: string;
        category: string;
        official_only: boolean;
        sort: string;
        order: string;
    };
}

const categoryIcons: Record<string, string> = {
    nodejs: 'N',
    php: 'P',
    python: 'Py',
    ruby: 'Rb',
    go: 'Go',
    rust: 'Rs',
    java: 'J',
    dotnet: '.N',
    static: 'S',
    docker: 'D',
    general: 'G',
};

const categoryColors: Record<string, string> = {
    nodejs: 'bg-green-500/20 text-green-400',
    php: 'bg-purple-500/20 text-purple-400',
    python: 'bg-blue-500/20 text-blue-400',
    ruby: 'bg-red-500/20 text-red-400',
    go: 'bg-cyan-500/20 text-cyan-400',
    rust: 'bg-orange-500/20 text-orange-400',
    java: 'bg-amber-500/20 text-amber-400',
    dotnet: 'bg-violet-500/20 text-violet-400',
    static: 'bg-gray-500/20 text-gray-400',
    docker: 'bg-sky-500/20 text-sky-400',
    general: 'bg-slate-500/20 text-slate-400',
};

function TemplateCard({
    template,
    onDuplicate,
    onDelete,
}: {
    template: Template;
    onDuplicate: () => void;
    onDelete: () => void;
}) {
    const [showActions, setShowActions] = React.useState(false);

    return (
        <Card
            variant="glass"
            className="group relative transition-all hover:border-primary/50"
            onMouseEnter={() => setShowActions(true)}
            onMouseLeave={() => setShowActions(false)}
        >
            <CardContent className="p-5">
                <div className="mb-4 flex items-start justify-between">
                    <div
                        className={`flex h-12 w-12 items-center justify-center rounded-lg text-lg font-bold ${categoryColors[template.category] || categoryColors.general}`}
                    >
                        {template.icon || categoryIcons[template.category] || 'T'}
                    </div>
                    <div className="flex items-center gap-2">
                        {template.is_official && (
                            <Badge variant="success" size="sm" icon={<CheckCircle className="h-3 w-3" />}>
                                Official
                            </Badge>
                        )}
                        {!template.is_public && (
                            <Badge variant="warning" size="sm">
                                Private
                            </Badge>
                        )}
                    </div>
                </div>

                <Link href={`/admin/templates/${template.uuid}`}>
                    <h3 className="mb-1 font-semibold text-foreground hover:text-primary">
                        {template.name}
                    </h3>
                </Link>

                <p className="mb-3 line-clamp-2 text-sm text-foreground-muted">
                    {template.description || 'No description provided'}
                </p>

                <div className="mb-3 flex flex-wrap gap-1">
                    {template.tags?.slice(0, 3).map((tag) => (
                        <Badge key={tag} variant="default" size="sm">
                            {tag}
                        </Badge>
                    ))}
                    {(template.tags?.length || 0) > 3 && (
                        <Badge variant="default" size="sm">
                            +{template.tags.length - 3}
                        </Badge>
                    )}
                </div>

                <div className="flex items-center justify-between border-t border-border/50 pt-3 text-xs text-foreground-subtle">
                    <div className="flex items-center gap-3">
                        <span className="flex items-center gap-1">
                            <Download className="h-3 w-3" />
                            {template.usage_count}
                        </span>
                        {template.rating !== null && template.rating !== undefined && (
                            <span className="flex items-center gap-1">
                                <Star className="h-3 w-3 fill-warning text-warning" />
                                {template.rating.toFixed(1)}
                            </span>
                        )}
                    </div>
                    <span>{template.version}</span>
                </div>

                {/* Action buttons on hover */}
                {showActions && (
                    <div className="absolute bottom-3 right-3 flex gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={(e) => {
                                e.preventDefault();
                                onDuplicate();
                            }}
                            title="Duplicate"
                        >
                            <Copy className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={(e) => {
                                e.preventDefault();
                                onDelete();
                            }}
                            title="Delete"
                            className="hover:text-danger"
                        >
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default function AdminTemplatesIndex({ templates, categories, filters }: Props) {
    const confirm = useConfirm();
    const [search, setSearch] = React.useState(filters.search);
    const [category, setCategory] = React.useState(filters.category);
    const [officialOnly, setOfficialOnly] = React.useState(filters.official_only);

    const applyFilters = () => {
        router.get(
            '/admin/templates',
            {
                search: search || undefined,
                category: category !== 'all' ? category : undefined,
                official_only: officialOnly || undefined,
            },
            { preserveState: true }
        );
    };

    const handleDuplicate = async (uuid: string) => {
        const confirmed = await confirm({
            title: 'Duplicate Template',
            description: 'This will create a copy of this template.',
            confirmText: 'Duplicate',
        });
        if (confirmed) {
            router.post(`/admin/templates/${uuid}/duplicate`);
        }
    };

    const handleDelete = async (uuid: string) => {
        const confirmed = await confirm({
            title: 'Delete Template',
            description: 'Are you sure you want to delete this template? This action cannot be undone.',
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/admin/templates/${uuid}`);
        }
    };

    React.useEffect(() => {
        const timeoutId = setTimeout(() => {
            if (search !== filters.search) {
                applyFilters();
            }
        }, 300);
        return () => clearTimeout(timeoutId);
    }, [search]);

    const officialCount = templates.data.filter((t) => t.is_official).length;
    const publicCount = templates.data.filter((t) => t.is_public).length;

    return (
        <AdminLayout
            title="Application Templates"
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'Templates' },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Application Templates</h1>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Pre-configured application templates for quick deployment
                        </p>
                    </div>
                    <Link href="/admin/templates/create">
                        <Button variant="primary">
                            <Plus className="h-4 w-4" />
                            Create Template
                        </Button>
                    </Link>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-3">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Total Templates</p>
                                    <p className="text-2xl font-bold text-foreground">{templates.total}</p>
                                </div>
                                <LayoutTemplate className="h-8 w-8 text-primary/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Official</p>
                                    <p className="text-2xl font-bold text-success">{officialCount}</p>
                                </div>
                                <CheckCircle className="h-8 w-8 text-success/50" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Public</p>
                                    <p className="text-2xl font-bold text-info">{publicCount}</p>
                                </div>
                                <Star className="h-8 w-8 text-info/50" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card variant="glass" className="mb-6">
                    <CardContent className="p-4">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                                <Input
                                    placeholder="Search templates..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Select
                                    value={category}
                                    onChange={(e) => {
                                        setCategory(e.target.value);
                                        router.get(
                                            '/admin/templates',
                                            {
                                                search: search || undefined,
                                                category: e.target.value !== 'all' ? e.target.value : undefined,
                                                official_only: officialOnly || undefined,
                                            },
                                            { preserveState: true }
                                        );
                                    }}
                                    options={[
                                        { value: 'all', label: 'All Categories' },
                                        ...Object.entries(categories).map(([key, label]) => ({
                                            value: key,
                                            label: label as string,
                                        })),
                                    ]}
                                />
                                <Button
                                    variant={officialOnly ? 'primary' : 'secondary'}
                                    size="sm"
                                    onClick={() => {
                                        setOfficialOnly(!officialOnly);
                                        router.get(
                                            '/admin/templates',
                                            {
                                                search: search || undefined,
                                                category: category !== 'all' ? category : undefined,
                                                official_only: !officialOnly || undefined,
                                            },
                                            { preserveState: true }
                                        );
                                    }}
                                >
                                    <Filter className="h-4 w-4" />
                                    Official Only
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Templates Grid */}
                {templates.data.length === 0 ? (
                    <Card variant="glass">
                        <CardContent className="py-16 text-center">
                            <LayoutTemplate className="mx-auto h-12 w-12 text-foreground-muted" />
                            <h3 className="mt-4 text-lg font-medium text-foreground">No templates found</h3>
                            <p className="mt-2 text-sm text-foreground-muted">
                                {search || category !== 'all'
                                    ? 'Try adjusting your search or filters'
                                    : 'Create your first application template to get started'}
                            </p>
                            {!search && category === 'all' && (
                                <Link href="/admin/templates/create" className="mt-4 inline-block">
                                    <Button variant="primary">
                                        <Plus className="h-4 w-4" />
                                        Create Template
                                    </Button>
                                </Link>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {templates.data.map((template) => (
                                <TemplateCard
                                    key={template.id}
                                    template={template}
                                    onDuplicate={() => handleDuplicate(template.uuid)}
                                    onDelete={() => handleDelete(template.uuid)}
                                />
                            ))}
                        </div>

                        {/* Pagination */}
                        {templates.last_page > 1 && (
                            <div className="mt-6 flex items-center justify-center gap-2">
                                {templates.links.map((link, index) => (
                                    <React.Fragment key={index}>
                                        {link.url ? (
                                            <Link
                                                href={link.url}
                                                className={`rounded-md px-3 py-2 text-sm ${
                                                    link.active
                                                        ? 'bg-primary text-primary-foreground'
                                                        : 'bg-background text-foreground hover:bg-accent'
                                                }`}
                                                dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(link.label) }}
                                            />
                                        ) : (
                                            <span
                                                className="rounded-md px-3 py-2 text-sm text-foreground-muted"
                                                dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(link.label) }}
                                            />
                                        )}
                                    </React.Fragment>
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
        </AdminLayout>
    );
}
