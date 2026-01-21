import * as React from 'react';
import { Link } from '@inertiajs/react';
import {
    Folder,
    Server,
    Database,
    Rocket,
    Users,
    FileText,
    Settings,
    LucideIcon,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/Button';

export type EmptyStateVariant =
    | 'projects'
    | 'services'
    | 'databases'
    | 'deployments'
    | 'team-members'
    | 'logs'
    | 'variables'
    | 'generic';

interface EmptyStateAction {
    label: string;
    href?: string;
    onClick?: () => void;
    variant?: 'default' | 'secondary';
}

interface EmptyStateProps {
    variant?: EmptyStateVariant;
    icon?: LucideIcon;
    title?: string;
    description?: string;
    actions?: EmptyStateAction[];
    className?: string;
}

const variantConfig: Record<
    EmptyStateVariant,
    {
        icon: LucideIcon;
        title: string;
        description: string;
        gradient: string;
    }
> = {
    projects: {
        icon: Folder,
        title: 'No projects yet',
        description: 'Get started by creating your first project to organize your services and deployments.',
        gradient: 'from-primary/20 via-purple-500/20 to-pink-500/20',
    },
    services: {
        icon: Server,
        title: 'No services found',
        description: 'Deploy your first service to start building and shipping your applications.',
        gradient: 'from-blue-500/20 via-primary/20 to-emerald-500/20',
    },
    databases: {
        icon: Database,
        title: 'No databases yet',
        description: 'Create a database to store and manage your application data.',
        gradient: 'from-purple-500/20 via-pink-500/20 to-danger/20',
    },
    deployments: {
        icon: Rocket,
        title: 'No deployments',
        description: 'Your deployment history will appear here once you start deploying services.',
        gradient: 'from-amber-500/20 via-orange-500/20 to-danger/20',
    },
    'team-members': {
        icon: Users,
        title: 'No team members',
        description: 'Invite team members to collaborate on your projects and services.',
        gradient: 'from-emerald-500/20 via-teal-500/20 to-cyan-500/20',
    },
    logs: {
        icon: FileText,
        title: 'No logs available',
        description: 'Logs will appear here once your service starts generating activity.',
        gradient: 'from-slate-500/20 via-zinc-500/20 to-gray-500/20',
    },
    variables: {
        icon: Settings,
        title: 'No environment variables',
        description: 'Add environment variables to configure your service settings.',
        gradient: 'from-indigo-500/20 via-violet-500/20 to-purple-500/20',
    },
    generic: {
        icon: FileText,
        title: 'Nothing here yet',
        description: 'Get started by adding some content.',
        gradient: 'from-primary/20 via-purple-500/20 to-pink-500/20',
    },
};

export const EmptyState = React.forwardRef<HTMLDivElement, EmptyStateProps>(
    (
        {
            variant = 'generic',
            icon: CustomIcon,
            title: customTitle,
            description: customDescription,
            actions = [],
            className,
        },
        ref
    ) => {
        const config = variantConfig[variant];
        const Icon = CustomIcon || config.icon;
        const title = customTitle || config.title;
        const description = customDescription || config.description;

        return (
            <div
                ref={ref}
                className={cn('flex items-center justify-center py-16 px-6', className)}
            >
                <div className="w-full max-w-md text-center">
                    {/* Icon with gradient glow */}
                    <div className="relative mb-6 flex justify-center">
                        <div className="relative">
                            {/* Animated gradient background */}
                            <div
                                className={cn(
                                    'absolute inset-0 animate-pulse blur-2xl',
                                    'bg-gradient-to-r',
                                    config.gradient
                                )}
                            />
                            {/* Icon container */}
                            <div className="relative flex h-20 w-20 items-center justify-center rounded-2xl border border-border/50 bg-gradient-to-br from-background-secondary to-background-secondary/50">
                                <Icon className="h-10 w-10 text-foreground-muted" />
                            </div>
                        </div>
                    </div>

                    {/* Content */}
                    <h3 className="mb-2 text-lg font-semibold text-foreground">{title}</h3>
                    <p className="mb-6 text-sm text-foreground-muted">{description}</p>

                    {/* Actions */}
                    {actions.length > 0 && (
                        <div className="flex flex-col gap-3 sm:flex-row sm:justify-center">
                            {actions.map((action, index) => {
                                const buttonContent = (
                                    <Button
                                        variant={action.variant || 'default'}
                                        onClick={action.onClick}
                                        className="w-full sm:w-auto"
                                    >
                                        {action.label}
                                    </Button>
                                );

                                if (action.href) {
                                    return (
                                        <Link key={index} href={action.href}>
                                            {buttonContent}
                                        </Link>
                                    );
                                }

                                return <React.Fragment key={index}>{buttonContent}</React.Fragment>;
                            })}
                        </div>
                    )}
                </div>
            </div>
        );
    }
);

EmptyState.displayName = 'EmptyState';

// Preset components for common use cases
export const EmptyProjects = (props: Omit<EmptyStateProps, 'variant'>) => (
    <EmptyState variant="projects" {...props} />
);

export const EmptyServices = (props: Omit<EmptyStateProps, 'variant'>) => (
    <EmptyState variant="services" {...props} />
);

export const EmptyDatabases = (props: Omit<EmptyStateProps, 'variant'>) => (
    <EmptyState variant="databases" {...props} />
);

export const EmptyDeployments = (props: Omit<EmptyStateProps, 'variant'>) => (
    <EmptyState variant="deployments" {...props} />
);

export const EmptyTeamMembers = (props: Omit<EmptyStateProps, 'variant'>) => (
    <EmptyState variant="team-members" {...props} />
);

export const EmptyLogs = (props: Omit<EmptyStateProps, 'variant'>) => (
    <EmptyState variant="logs" {...props} />
);

export const EmptyVariables = (props: Omit<EmptyStateProps, 'variant'>) => (
    <EmptyState variant="variables" {...props} />
);
