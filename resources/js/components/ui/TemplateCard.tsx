import * as React from 'react';
import { Link } from '@inertiajs/react';
import { Badge } from './Badge';
import { Download } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface Template {
    id: string;
    name: string;
    description: string;
    icon: React.ReactNode;
    iconBg: string;
    iconColor: string;
    category: string;
    tags: string[];
    deployCount: number;
    featured?: boolean;
}

interface TemplateCardProps {
    template: Template;
    featured?: boolean;
}

export function TemplateCard({ template, featured = false }: TemplateCardProps) {
    return (
        <Link
            href={`/templates/${template.id}`}
            className={cn(
                'group relative flex flex-col rounded-xl border border-border/50 bg-gradient-to-br from-background-secondary to-background-secondary/50 p-5 transition-all duration-300 hover:-translate-y-1 hover:border-border hover:shadow-xl hover:shadow-black/20',
                featured && 'md:col-span-2'
            )}
        >
            {/* Subtle gradient overlay on hover */}
            <div className="absolute inset-0 rounded-xl bg-gradient-to-br from-white/[0.02] to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100" />

            <div className="relative">
                {/* Header with icon */}
                <div className="mb-4 flex items-start justify-between">
                    <div className={`flex h-12 w-12 items-center justify-center rounded-xl ${template.iconBg} ${template.iconColor} shadow-lg transition-transform duration-300 group-hover:scale-110`}>
                        {template.icon}
                    </div>
                    {template.featured && (
                        <Badge variant="success" className="animate-pulse">
                            Featured
                        </Badge>
                    )}
                </div>

                {/* Content */}
                <div className="mb-4">
                    <h3 className="mb-1.5 text-lg font-semibold text-foreground transition-colors group-hover:text-white">
                        {template.name}
                    </h3>
                    <p className={cn(
                        'text-sm text-foreground-muted',
                        featured ? 'line-clamp-2' : 'line-clamp-3'
                    )}>
                        {template.description}
                    </p>
                </div>

                {/* Tags */}
                <div className="mb-4 flex flex-wrap gap-1.5">
                    {template.tags.slice(0, 3).map((tag) => (
                        <Badge key={tag} variant="default" className="text-xs">
                            {tag}
                        </Badge>
                    ))}
                    {template.tags.length > 3 && (
                        <Badge variant="default" className="text-xs">
                            +{template.tags.length - 3}
                        </Badge>
                    )}
                </div>

                {/* Footer */}
                <div className="flex items-center justify-between border-t border-border/30 pt-3">
                    <div className="flex items-center gap-1.5 text-xs text-foreground-muted">
                        <Download className="h-3.5 w-3.5" />
                        <span>{template.deployCount.toLocaleString()} deploys</span>
                    </div>
                    <Badge variant="default" className="text-xs">
                        {template.category}
                    </Badge>
                </div>
            </div>
        </Link>
    );
}
