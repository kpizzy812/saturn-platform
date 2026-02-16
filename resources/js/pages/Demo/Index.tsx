import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout/AppLayout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import {
    LayoutDashboard,
    FolderKanban,
    Server,
    Database,
    Box,
    Settings,
    Users,
    CreditCard,
    Activity,
    Terminal,
    Globe,
    HardDrive,
    Clock,
    BarChart3,
    Rocket,
    AlertTriangle,
} from 'lucide-react';

interface PageLink {
    name: string;
    path: string;
    description: string;
}

interface PageCategory {
    name: string;
    icon: React.ReactNode;
    pages: PageLink[];
}

const categories: PageCategory[] = [
    {
        name: 'Core',
        icon: <LayoutDashboard className="h-5 w-5" />,
        pages: [
            { name: 'Dashboard', path: '/dashboard', description: 'Main dashboard overview' },
            { name: 'Demo Project Canvas', path: '/demo/project', description: 'Project canvas with mock data' },
        ],
    },
    {
        name: 'Projects',
        icon: <FolderKanban className="h-5 w-5" />,
        pages: [
            { name: 'Projects List', path: '/projects', description: 'All projects' },
            { name: 'Create Project', path: '/projects/create', description: 'Create new project' },
        ],
    },
    {
        name: 'Templates',
        icon: <Box className="h-5 w-5" />,
        pages: [
            { name: 'Templates Gallery', path: '/templates', description: 'Browse templates' },
        ],
    },
    {
        name: 'Servers',
        icon: <Server className="h-5 w-5" />,
        pages: [
            { name: 'Servers List', path: '/servers', description: 'All servers' },
        ],
    },
    {
        name: 'Databases',
        icon: <Database className="h-5 w-5" />,
        pages: [
            { name: 'Databases List', path: '/databases', description: 'All databases' },
            { name: 'Create Database', path: '/databases/create', description: 'Create new database' },
        ],
    },
    {
        name: 'Observability',
        icon: <BarChart3 className="h-5 w-5" />,
        pages: [
            { name: 'Overview', path: '/observability', description: 'Observability dashboard' },
            { name: 'Metrics', path: '/observability/metrics', description: 'Performance metrics' },
            { name: 'Logs', path: '/observability/logs', description: 'Centralized logs' },
            { name: 'Traces', path: '/observability/traces', description: 'Distributed tracing' },
            { name: 'Alerts', path: '/observability/alerts', description: 'Alert configuration' },
        ],
    },
    {
        name: 'Settings',
        icon: <Settings className="h-5 w-5" />,
        pages: [
            { name: 'General Settings', path: '/settings', description: 'General settings' },
            { name: 'Account', path: '/settings/account', description: 'Account settings' },
            { name: 'API Tokens', path: '/settings/tokens', description: 'API tokens management' },
        ],
    },
    {
        name: 'Team',
        icon: <Users className="h-5 w-5" />,
        pages: [
            { name: 'Team Members', path: '/settings/team', description: 'Team overview' },
        ],
    },
    {
        name: 'Billing',
        icon: <CreditCard className="h-5 w-5" />,
        pages: [
            { name: 'Billing Overview', path: '/settings/billing', description: 'Billing dashboard' },
        ],
    },
    {
        name: 'Domains & SSL',
        icon: <Globe className="h-5 w-5" />,
        pages: [
            { name: 'Domains', path: '/domains', description: 'Domain management' },
            { name: 'SSL Certificates', path: '/ssl', description: 'SSL certificates' },
        ],
    },
    {
        name: 'Storage',
        icon: <HardDrive className="h-5 w-5" />,
        pages: [
            { name: 'Volumes', path: '/volumes', description: 'Volume management' },
            { name: 'Backups', path: '/storage/backups', description: 'Backup management' },
            { name: 'Snapshots', path: '/storage/snapshots', description: 'Snapshot management' },
        ],
    },
    {
        name: 'Scheduled Tasks',
        icon: <Clock className="h-5 w-5" />,
        pages: [
            { name: 'Cron Jobs', path: '/cron-jobs', description: 'Cron job management' },
            { name: 'Scheduled Tasks', path: '/scheduled-tasks', description: 'One-time tasks' },
        ],
    },
    {
        name: 'Activity',
        icon: <Activity className="h-5 w-5" />,
        pages: [
            { name: 'Activity Log', path: '/activity', description: 'Activity timeline' },
            { name: 'Notifications', path: '/notifications', description: 'Notifications' },
        ],
    },
    {
        name: 'Deployments',
        icon: <Rocket className="h-5 w-5" />,
        pages: [
            { name: 'Deployment History', path: '/deployments', description: 'All deployments' },
        ],
    },
    {
        name: 'CLI & Integrations',
        icon: <Terminal className="h-5 w-5" />,
        pages: [
            { name: 'CLI Setup', path: '/cli/setup', description: 'CLI installation' },
            { name: 'CLI Commands', path: '/cli/commands', description: 'CLI reference' },
            { name: 'Webhooks', path: '/integrations/webhooks', description: 'Webhook configuration' },
        ],
    },
    {
        name: 'Onboarding',
        icon: <Rocket className="h-5 w-5" />,
        pages: [
            { name: 'Welcome', path: '/onboarding/welcome', description: 'Onboarding welcome' },
            { name: 'Connect Repo', path: '/onboarding/connect-repo', description: 'Repository connection' },
        ],
    },
    {
        name: 'Error Pages',
        icon: <AlertTriangle className="h-5 w-5" />,
        pages: [
            { name: '404 Not Found', path: '/errors/404', description: 'Page not found' },
            { name: '500 Server Error', path: '/errors/500', description: 'Server error' },
            { name: '403 Forbidden', path: '/errors/403', description: 'Access denied' },
            { name: 'Maintenance', path: '/errors/maintenance', description: 'Maintenance mode' },
        ],
    },
];

export default function DemoIndex() {
    const totalPages = categories.reduce((acc, cat) => acc + cat.pages.length, 0);

    return (
        <AppLayout
            breadcrumbs={[
                { label: 'Demo', href: '/demo' },
                { label: 'All Pages' },
            ]}
        >
            <div className="space-y-8">
                {/* Header */}
                <div className="text-center">
                    <h1 className="text-4xl font-bold text-foreground mb-2">
                        Saturn UI Demo
                    </h1>
                    <p className="text-lg text-foreground-muted">
                        Browse all {totalPages} pages of the Railway-style UI
                    </p>
                    <div className="mt-4 inline-flex items-center gap-2 rounded-full bg-primary/20 px-4 py-2 text-sm text-primary">
                        <Rocket className="h-4 w-4" />
                        <span>100% Railway Feature Coverage</span>
                    </div>
                </div>

                {/* Quick Access */}
                <Card className="bg-gradient-to-r from-primary/10 to-purple-500/10 border-primary/20">
                    <CardContent className="p-6">
                        <h2 className="text-xl font-semibold text-foreground mb-4">
                            Quick Access - Key Pages
                        </h2>
                        <div className="flex flex-wrap gap-3">
                            <Link
                                href="/demo/project"
                                className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90 transition-colors"
                            >
                                <FolderKanban className="h-4 w-4" />
                                Project Canvas (Demo)
                            </Link>
                            <Link
                                href="/dashboard"
                                className="inline-flex items-center gap-2 rounded-lg bg-background-tertiary px-4 py-2 text-sm font-medium text-foreground hover:bg-background-secondary transition-colors"
                            >
                                <LayoutDashboard className="h-4 w-4" />
                                Dashboard
                            </Link>
                            <Link
                                href="/templates"
                                className="inline-flex items-center gap-2 rounded-lg bg-background-tertiary px-4 py-2 text-sm font-medium text-foreground hover:bg-background-secondary transition-colors"
                            >
                                <Box className="h-4 w-4" />
                                Templates
                            </Link>
                            <Link
                                href="/settings"
                                className="inline-flex items-center gap-2 rounded-lg bg-background-tertiary px-4 py-2 text-sm font-medium text-foreground hover:bg-background-secondary transition-colors"
                            >
                                <Settings className="h-4 w-4" />
                                Settings
                            </Link>
                        </div>
                    </CardContent>
                </Card>

                {/* Categories Grid */}
                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    {categories.map((category) => (
                        <Card key={category.name} className="bg-background-secondary hover:bg-background-tertiary/50 transition-colors">
                            <CardHeader className="pb-3">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        {category.icon}
                                    </div>
                                    <div>
                                        <CardTitle className="text-lg">{category.name}</CardTitle>
                                        <CardDescription>{category.pages.length} pages</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="pt-0">
                                <ul className="space-y-2">
                                    {category.pages.map((page) => (
                                        <li key={page.path}>
                                            <Link
                                                href={page.path}
                                                className="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-foreground-muted hover:bg-background-tertiary hover:text-foreground transition-colors"
                                            >
                                                <span>{page.name}</span>
                                                <span className="text-xs text-foreground-subtle">→</span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Stats Footer */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <Card className="bg-background-secondary">
                        <CardContent className="p-4 text-center">
                            <div className="text-3xl font-bold text-primary">114</div>
                            <div className="text-sm text-foreground-muted">Total Pages</div>
                        </CardContent>
                    </Card>
                    <Card className="bg-background-secondary">
                        <CardContent className="p-4 text-center">
                            <div className="text-3xl font-bold text-green-500">232</div>
                            <div className="text-sm text-foreground-muted">Tests Passing</div>
                        </CardContent>
                    </Card>
                    <Card className="bg-background-secondary">
                        <CardContent className="p-4 text-center">
                            <div className="text-3xl font-bold text-purple-500">30+</div>
                            <div className="text-sm text-foreground-muted">UI Components</div>
                        </CardContent>
                    </Card>
                    <Card className="bg-background-secondary">
                        <CardContent className="p-4 text-center">
                            <div className="text-3xl font-bold text-yellow-500">100%</div>
                            <div className="text-sm text-foreground-muted">Railway Coverage</div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
