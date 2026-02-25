import { AppLayout } from '@/components/layout';
import { TemplateCard, Template } from '@/components/ui/TemplateCard';
import { useState, useMemo } from 'react';
import { Search, ArrowLeft } from 'lucide-react';
import { Link } from '@inertiajs/react';
import { StaggerList, StaggerItem, FadeIn } from '@/components/animation';

interface Props {
    templates?: Template[];
}

const categories = ['All', 'Web Apps', 'Databases', 'APIs', 'Full Stack', 'Gaming'];

const categoryColors: Record<string, string> = {
    'All': 'bg-foreground-muted/20 text-foreground-muted hover:bg-foreground-muted/30',
    'Web Apps': 'bg-info/20 text-info hover:bg-info/30',
    'Databases': 'bg-success/20 text-success hover:bg-success/30',
    'APIs': 'bg-primary/20 text-primary hover:bg-primary/30',
    'Full Stack': 'bg-warning/20 text-warning hover:bg-warning/30',
    'Gaming': 'bg-danger/20 text-danger hover:bg-danger/30',
};

export default function TemplatesIndex({ templates }: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedCategory, setSelectedCategory] = useState('All');

    const filteredTemplates = useMemo(() => {
        if (!templates) return [];
        return templates.filter((template) => {
            const matchesSearch =
                template.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                template.description.toLowerCase().includes(searchQuery.toLowerCase()) ||
                template.tags.some(tag => tag.toLowerCase().includes(searchQuery.toLowerCase()));

            const matchesCategory =
                selectedCategory === 'All' ||
                template.category === selectedCategory;

            return matchesSearch && matchesCategory;
        });
    }, [templates, searchQuery, selectedCategory]);

    // Loading state
    if (!templates) {
        return (
            <AppLayout title="Templates" showNewProject={false}>
                <div className="mx-auto max-w-7xl">
                    <div className="flex items-center justify-center py-12">
                        <div className="text-center">
                            <div className="mb-4 h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent mx-auto" />
                            <p className="text-foreground-muted">Loading templates...</p>
                        </div>
                    </div>
                </div>
            </AppLayout>
        );
    }

    const featuredTemplates = filteredTemplates.filter(t => t.featured);
    const regularTemplates = filteredTemplates.filter(t => !t.featured);

    return (
        <AppLayout title="Templates" showNewProject={false}>
            <div className="mx-auto max-w-7xl">
                {/* Back link */}
                <Link
                    href="/projects/create"
                    className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="mr-2 h-4 w-4" />
                    Back to Create Project
                </Link>

                {/* Header */}
                <div className="mb-8">
                    <h1 className="mb-2 text-3xl font-bold text-foreground">Template Marketplace</h1>
                    <p className="text-foreground-muted">
                        Deploy production-ready applications in seconds. Choose from our curated templates.
                    </p>
                </div>

                {/* Search and Filters */}
                <div className="mb-8 space-y-4">
                    {/* Search Bar */}
                    <div className="relative">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-subtle" />
                        <input
                            type="text"
                            placeholder="Search templates..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="h-11 w-full rounded-lg border border-border bg-background-secondary pl-10 pr-4 text-sm text-foreground placeholder:text-foreground-subtle focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background"
                        />
                    </div>

                    {/* Category Filters */}
                    <div className="flex flex-wrap gap-2">
                        {categories.map((category) => (
                            <button
                                key={category}
                                onClick={() => setSelectedCategory(category)}
                                className={`rounded-lg px-4 py-2 text-sm font-medium transition-all ${
                                    selectedCategory === category
                                        ? categoryColors[category] + ' ring-2 ring-offset-2 ring-offset-background'
                                        : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary'
                                }`}
                            >
                                {category}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Results Count */}
                <div className="mb-4 text-sm text-foreground-muted">
                    {filteredTemplates.length} {filteredTemplates.length === 1 ? 'template' : 'templates'} found
                </div>

                {/* Featured Templates */}
                {featuredTemplates.length > 0 && (
                    <div className="mb-8">
                        <h2 className="mb-4 text-xl font-semibold text-foreground">Featured Templates</h2>
                        <StaggerList className="grid gap-4 md:grid-cols-2">
                            {featuredTemplates.map((template, i) => (
                                <StaggerItem key={template.id} index={i}>
                                    <TemplateCard template={template} featured />
                                </StaggerItem>
                            ))}
                        </StaggerList>
                    </div>
                )}

                {/* All Templates */}
                {regularTemplates.length > 0 && (
                    <div>
                        <h2 className="mb-4 text-xl font-semibold text-foreground">
                            {featuredTemplates.length > 0 ? 'More Templates' : 'All Templates'}
                        </h2>
                        <StaggerList className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {regularTemplates.map((template, i) => (
                                <StaggerItem key={template.id} index={i}>
                                    <TemplateCard template={template} />
                                </StaggerItem>
                            ))}
                        </StaggerList>
                    </div>
                )}

                {/* No Results */}
                {filteredTemplates.length === 0 && (
                    <FadeIn>
                    <div className="rounded-xl border border-border/50 bg-background-secondary p-12 text-center">
                        <Search className="mx-auto mb-4 h-12 w-12 text-foreground-subtle" />
                        <h3 className="mb-2 text-lg font-semibold text-foreground">No templates found</h3>
                        <p className="text-foreground-muted">
                            Try adjusting your search or filters to find what you're looking for.
                        </p>
                    </div>
                    </FadeIn>
                )}
            </div>
        </AppLayout>
    );
}
