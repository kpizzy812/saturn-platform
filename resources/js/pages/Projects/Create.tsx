import { AppLayout } from '@/components/layout';
import { Link } from '@inertiajs/react';
import { GitBranch, Database, FileCode, Container, Zap, Folder, ArrowLeft, ChevronRight } from 'lucide-react';

interface DeployOption {
    icon: React.ReactNode;
    title: string;
    description: string;
    href: string;
    badge?: string;
    iconBg: string;
    iconColor: string;
}

const deployOptions: DeployOption[] = [
    {
        icon: <GitBranch className="h-5 w-5" />,
        title: 'Git Repository',
        description: 'GitHub, GitLab, Bitbucket',
        href: '/applications/create',
        iconBg: 'bg-gradient-to-br from-zinc-700 to-zinc-800',
        iconColor: 'text-white',
    },
    {
        icon: <Database className="h-5 w-5" />,
        title: 'Database',
        description: 'PostgreSQL, MySQL, MongoDB, Redis',
        href: '/databases/create',
        iconBg: 'bg-gradient-to-br from-blue-500 to-blue-600',
        iconColor: 'text-white',
    },
    {
        icon: <FileCode className="h-5 w-5" />,
        title: 'Template',
        description: 'Start from a pre-built template',
        href: '/templates',
        iconBg: 'bg-gradient-to-br from-purple-500 to-purple-600',
        iconColor: 'text-white',
    },
    {
        icon: <Container className="h-5 w-5" />,
        title: 'Docker Image',
        description: 'Deploy from Docker Hub or private registry',
        href: '/applications/create?source=docker',
        iconBg: 'bg-gradient-to-br from-cyan-500 to-cyan-600',
        iconColor: 'text-white',
    },
    {
        icon: <Zap className="h-5 w-5" />,
        title: 'Function',
        description: 'Deploy serverless functions',
        href: '/projects/create/function',
        badge: 'Coming Soon',
        iconBg: 'bg-gradient-to-br from-amber-500 to-amber-600',
        iconColor: 'text-white',
    },
    {
        icon: <Folder className="h-5 w-5" />,
        title: 'Empty Project',
        description: 'Start with a blank project',
        href: '/projects/create/empty',
        iconBg: 'bg-gradient-to-br from-emerald-500 to-emerald-600',
        iconColor: 'text-white',
    },
];

function DeployOptionCard({ option }: { option: DeployOption }) {
    const isDisabled = !!option.badge;

    if (isDisabled) {
        return (
            <div className="flex cursor-not-allowed items-center justify-between rounded-xl border border-border/50 bg-background-secondary/50 p-4 opacity-50">
                <div className="flex items-center gap-4">
                    <div className={`flex h-11 w-11 items-center justify-center rounded-xl ${option.iconBg} ${option.iconColor} shadow-lg`}>
                        {option.icon}
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <h3 className="font-medium text-foreground">{option.title}</h3>
                            <span className="rounded-full bg-background-tertiary px-2.5 py-0.5 text-xs font-medium text-foreground-muted">
                                {option.badge}
                            </span>
                        </div>
                        <p className="mt-0.5 text-sm text-foreground-muted">{option.description}</p>
                    </div>
                </div>
                <ChevronRight className="h-5 w-5 text-foreground-subtle" />
            </div>
        );
    }

    return (
        <Link
            href={option.href}
            className="group flex items-center justify-between rounded-xl border border-border/50 bg-gradient-to-br from-background-secondary to-background-secondary/50 p-4 transition-all duration-300 hover:-translate-y-0.5 hover:border-border hover:shadow-xl hover:shadow-black/20"
        >
            <div className="flex items-center gap-4">
                <div className={`flex h-11 w-11 items-center justify-center rounded-xl ${option.iconBg} ${option.iconColor} shadow-lg transition-transform duration-300 group-hover:scale-110`}>
                    {option.icon}
                </div>
                <div>
                    <h3 className="font-medium text-foreground transition-colors group-hover:text-white">{option.title}</h3>
                    <p className="mt-0.5 text-sm text-foreground-muted">{option.description}</p>
                </div>
            </div>
            <ChevronRight className="h-5 w-5 text-foreground-subtle transition-transform duration-300 group-hover:translate-x-1 group-hover:text-foreground-muted" />
        </Link>
    );
}

export default function ProjectCreate() {
    return (
        <AppLayout title="New Project" showNewProject={false}>
            <div className="flex min-h-full items-start justify-center py-12">
                <div className="w-full max-w-lg px-4">
                    {/* Back link */}
                    <Link
                        href="/dashboard"
                        className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                    >
                        <ArrowLeft className="mr-2 h-4 w-4 transition-transform group-hover:-translate-x-1" />
                        Back to Dashboard
                    </Link>

                    {/* Header */}
                    <div className="mb-8 text-center">
                        <h1 className="text-2xl font-semibold text-foreground">Create a new project</h1>
                        <p className="mt-2 text-foreground-muted">
                            Choose how you want to deploy your project
                        </p>
                    </div>

                    {/* Options Grid */}
                    <div className="space-y-3">
                        {deployOptions.map((option) => (
                            <DeployOptionCard key={option.title} option={option} />
                        ))}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
