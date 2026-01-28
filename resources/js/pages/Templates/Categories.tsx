import { AppLayout } from '@/components/layout';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
import * as Icons from 'lucide-react';
import { cn } from '@/lib/utils';

interface CategoryData {
    id: string;
    name: string;
    icon: React.ReactNode;
    description: string;
    count: number;
    gradient: string;
    featured?: boolean;
}

const categories: CategoryData[] = [
    {
        id: 'web-apps',
        name: 'Web Apps',
        icon: <Icons.Globe className="h-8 w-8" />,
        description: 'Full-stack web applications and SPAs',
        count: 42,
        gradient: 'from-blue-500/20 to-blue-600/20 hover:from-blue-500/30 hover:to-blue-600/30',
        featured: true,
    },
    {
        id: 'databases',
        name: 'Databases',
        icon: <Icons.Database className="h-8 w-8" />,
        description: 'PostgreSQL, MySQL, MongoDB, Redis and more',
        count: 28,
        gradient: 'from-emerald-500/20 to-emerald-600/20 hover:from-emerald-500/30 hover:to-emerald-600/30',
        featured: true,
    },
    {
        id: 'apis',
        name: 'APIs',
        icon: <Icons.Server className="h-8 w-8" />,
        description: 'RESTful and GraphQL API backends',
        count: 35,
        gradient: 'from-purple-500/20 to-purple-600/20 hover:from-purple-500/30 hover:to-purple-600/30',
    },
    {
        id: 'full-stack',
        name: 'Full Stack',
        icon: <Icons.Layers className="h-8 w-8" />,
        description: 'Complete applications with frontend and backend',
        count: 19,
        gradient: 'from-amber-500/20 to-amber-600/20 hover:from-amber-500/30 hover:to-amber-600/30',
    },
    {
        id: 'gaming',
        name: 'Gaming',
        icon: <Icons.Gamepad2 className="h-8 w-8" />,
        description: 'Game servers and gaming platforms',
        count: 12,
        gradient: 'from-pink-500/20 to-pink-600/20 hover:from-pink-500/30 hover:to-pink-600/30',
    },
    {
        id: 'cms',
        name: 'CMS',
        icon: <Icons.FileText className="h-8 w-8" />,
        description: 'Content management systems and blogs',
        count: 16,
        gradient: 'from-indigo-500/20 to-indigo-600/20 hover:from-indigo-500/30 hover:to-indigo-600/30',
    },
];

export default function TemplatesCategories() {
    const [hoveredId, setHoveredId] = useState<string | null>(null);

    const featuredCategories = categories.filter(c => c.featured);
    const regularCategories = categories.filter(c => !c.featured);

    return (
        <AppLayout title="Template Categories" showNewProject={false}>
            <div className="mx-auto max-w-7xl">
                {/* Back link */}
                <Link
                    href="/templates"
                    className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                >
                    <Icons.ArrowLeft className="mr-2 h-4 w-4" />
                    Back to Templates
                </Link>

                {/* Header */}
                <div className="mb-8">
                    <h1 className="mb-2 text-3xl font-bold text-foreground">Browse by Category</h1>
                    <p className="text-foreground-muted">
                        Find the perfect template for your next project. Explore our curated categories.
                    </p>
                </div>

                {/* Featured Categories */}
                {featuredCategories.length > 0 && (
                    <div className="mb-8">
                        <h2 className="mb-4 flex items-center gap-2 text-xl font-semibold text-foreground">
                            <Icons.Star className="h-5 w-5 fill-yellow-500 text-yellow-500" />
                            Featured Categories
                        </h2>
                        <div className="grid gap-6 md:grid-cols-2">
                            {featuredCategories.map((category) => (
                                <CategoryCard
                                    key={category.id}
                                    category={category}
                                    isHovered={hoveredId === category.id}
                                    onHover={() => setHoveredId(category.id)}
                                    onLeave={() => setHoveredId(null)}
                                    large
                                />
                            ))}
                        </div>
                    </div>
                )}

                {/* All Categories */}
                <div>
                    <h2 className="mb-4 text-xl font-semibold text-foreground">All Categories</h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {regularCategories.map((category) => (
                            <CategoryCard
                                key={category.id}
                                category={category}
                                isHovered={hoveredId === category.id}
                                onHover={() => setHoveredId(category.id)}
                                onLeave={() => setHoveredId(null)}
                            />
                        ))}
                    </div>
                </div>

                {/* Stats */}
                <div className="mt-12 rounded-xl border border-border/50 bg-background-secondary p-6">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="text-center">
                            <div className="mb-1 text-3xl font-bold text-foreground">
                                {categories.reduce((sum, cat) => sum + cat.count, 0)}
                            </div>
                            <div className="text-sm text-foreground-muted">Total Templates</div>
                        </div>
                        <div className="text-center">
                            <div className="mb-1 text-3xl font-bold text-foreground">
                                {categories.length}
                            </div>
                            <div className="text-sm text-foreground-muted">Categories</div>
                        </div>
                        <div className="text-center">
                            <div className="mb-1 text-3xl font-bold text-foreground">1M+</div>
                            <div className="text-sm text-foreground-muted">Total Deployments</div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

interface CategoryCardProps {
    category: CategoryData;
    isHovered: boolean;
    onHover: () => void;
    onLeave: () => void;
    large?: boolean;
}

function CategoryCard({ category, isHovered, onHover, onLeave, large = false }: CategoryCardProps) {
    return (
        <Link
            href={`/templates?category=${category.id}`}
            className={cn(
                'group relative overflow-hidden rounded-xl border border-border/50 bg-gradient-to-br transition-all duration-300',
                category.gradient,
                isHovered
                    ? 'scale-105 border-border shadow-2xl shadow-black/30'
                    : 'hover:-translate-y-1 hover:border-border hover:shadow-xl hover:shadow-black/20',
                large ? 'p-8' : 'p-6'
            )}
            onMouseEnter={onHover}
            onMouseLeave={onLeave}
        >
            {/* Animated background gradient */}
            <div className="absolute inset-0 bg-gradient-to-br from-white/[0.03] to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100" />

            <div className="relative">
                {/* Icon */}
                <div className="mb-4 flex items-center justify-between">
                    <div
                        className={cn(
                            'flex items-center justify-center rounded-xl bg-background text-foreground shadow-lg transition-transform duration-300 group-hover:scale-110',
                            large ? 'h-16 w-16' : 'h-12 w-12'
                        )}
                    >
                        {category.icon}
                    </div>
                    {category.featured && (
                        <div className="rounded-full bg-yellow-500/20 px-3 py-1 text-xs font-medium text-yellow-500">
                            Featured
                        </div>
                    )}
                </div>

                {/* Content */}
                <h3
                    className={cn(
                        'mb-2 font-semibold text-foreground transition-colors group-hover:text-white',
                        large ? 'text-2xl' : 'text-lg'
                    )}
                >
                    {category.name}
                </h3>
                <p className={cn('mb-4 text-foreground-muted', large ? 'text-base' : 'text-sm')}>
                    {category.description}
                </p>

                {/* Template count */}
                <div className="flex items-center gap-2 text-sm text-foreground-muted">
                    <Icons.Package className="h-4 w-4" />
                    <span>
                        {category.count} {category.count === 1 ? 'template' : 'templates'}
                    </span>
                </div>

                {/* Arrow indicator */}
                <div className="absolute bottom-6 right-6 opacity-0 transition-all duration-300 group-hover:translate-x-1 group-hover:opacity-100">
                    <Icons.ArrowRight className="h-5 w-5 text-foreground" />
                </div>
            </div>
        </Link>
    );
}
