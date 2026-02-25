import * as React from 'react';
import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Input, Badge, useToast } from '@/components/ui';
import { Terminal, Copy, Search, ChevronDown, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

interface CLICommand {
    name: string;
    description: string;
    usage: string;
    category: string;
    options?: { flag: string; description: string }[];
    examples?: { command: string; description: string }[];
}

const commands: CLICommand[] = [
    // Auth
    {
        name: 'login',
        description: 'Authenticate with a Saturn instance via browser',
        usage: 'saturn login [url]',
        category: 'Auth',
        examples: [
            { command: 'saturn login https://saturn.ac', description: 'Login to production' },
            { command: 'saturn login https://dev.saturn.ac', description: 'Login to dev environment' },
            { command: 'saturn login', description: 'Login using default instance URL' },
        ],
    },
    // Context
    {
        name: 'context add',
        description: 'Add a new Saturn instance connection (for CI/CD or manual setup)',
        usage: 'saturn context add <name> <url> <token>',
        category: 'Context',
        examples: [
            { command: 'saturn context add production https://saturn.app my-api-token', description: 'Add production instance' },
            { command: 'saturn context add local http://localhost:8000 root', description: 'Add local dev instance' },
        ],
    },
    {
        name: 'context list',
        description: 'List all configured instances',
        usage: 'saturn context list',
        category: 'Context',
    },
    {
        name: 'context use',
        description: 'Switch to a different instance',
        usage: 'saturn context use <name>',
        category: 'Context',
        examples: [
            { command: 'saturn context use production', description: 'Switch to production' },
        ],
    },
    {
        name: 'context verify',
        description: 'Verify connection to the current instance',
        usage: 'saturn context verify',
        category: 'Context',
    },
    {
        name: 'context set-token',
        description: 'Update the API token for an instance',
        usage: 'saturn context set-token <name> <token>',
        category: 'Context',
    },
    // Applications
    {
        name: 'app list',
        description: 'List all applications',
        usage: 'saturn app list',
        category: 'Applications',
    },
    {
        name: 'app create',
        description: 'Create a new application',
        usage: 'saturn app create <type> [options]',
        category: 'Applications',
        options: [
            { flag: '--server-uuid <uuid>', description: 'Target server UUID' },
            { flag: '--project-uuid <uuid>', description: 'Project UUID' },
            { flag: '--environment-name <name>', description: 'Environment name' },
        ],
        examples: [
            { command: 'saturn app create public --server-uuid <uuid> --project-uuid <uuid> --environment-name production --git-repository https://github.com/user/repo', description: 'Create from public repo' },
            { command: 'saturn app create dockerimage --server-uuid <uuid> --project-uuid <uuid> --environment-name production --docker-image nginx:latest', description: 'Create from Docker image' },
        ],
    },
    {
        name: 'app start / stop / restart',
        description: 'Control application lifecycle',
        usage: 'saturn app start|stop|restart <uuid>',
        category: 'Applications',
        examples: [
            { command: 'saturn app restart abc-123-def', description: 'Restart application' },
        ],
    },
    {
        name: 'app env list / sync',
        description: 'Manage application environment variables',
        usage: 'saturn app env list|sync <uuid> [options]',
        category: 'Applications',
        options: [
            { flag: '--file <path>', description: 'Path to .env file for sync' },
        ],
        examples: [
            { command: 'saturn app env list abc-123', description: 'List env vars' },
            { command: 'saturn app env sync abc-123 --file .env.production', description: 'Sync from .env file' },
        ],
    },
    {
        name: 'app rollback list',
        description: 'List rollback events for an application',
        usage: 'saturn app rollback list <app-uuid> [--take <n>]',
        category: 'Applications',
        options: [
            { flag: '--take <n>', description: 'Number of rollback events to retrieve' },
        ],
        examples: [
            { command: 'saturn app rollback list abc-123', description: 'List all rollback events' },
            { command: 'saturn app rollback list abc-123 --take 5', description: 'List last 5 rollback events' },
        ],
    },
    {
        name: 'app rollback execute',
        description: 'Rollback to a previous deployment',
        usage: 'saturn app rollback execute <app-uuid> <deployment-uuid>',
        category: 'Applications',
        options: [
            { flag: '-f, --force', description: 'Skip confirmation prompt' },
        ],
        examples: [
            { command: 'saturn app rollback execute abc-123 dep-456', description: 'Rollback to specific deployment' },
            { command: 'saturn app rollback execute abc-123 dep-456 --force', description: 'Rollback without confirmation' },
        ],
    },
    // Deployments
    {
        name: 'deploy uuid',
        description: 'Deploy a resource by UUID',
        usage: 'saturn deploy uuid <uuid>',
        category: 'Deployments',
        options: [
            { flag: '-w, --wait', description: 'Wait for deployment to complete before exiting' },
            { flag: '--timeout <seconds>', description: 'Timeout in seconds when using --wait (default 600)' },
            { flag: '--poll-interval <seconds>', description: 'Poll interval in seconds when using --wait (default 3)' },
        ],
        examples: [
            { command: 'saturn deploy uuid abc-123-def', description: 'Deploy specific resource' },
            { command: 'saturn deploy uuid abc-123-def --wait', description: 'Deploy and wait for completion' },
            { command: 'saturn deploy uuid abc-123-def --wait --timeout 300', description: 'Deploy with 5min timeout' },
        ],
    },
    {
        name: 'deploy batch',
        description: 'Deploy multiple resources at once',
        usage: 'saturn deploy batch <uuid1>,<uuid2>,...',
        category: 'Deployments',
        options: [
            { flag: '-w, --wait', description: 'Wait for all deployments to complete' },
            { flag: '--timeout <seconds>', description: 'Timeout in seconds when using --wait (default 600)' },
        ],
        examples: [
            { command: 'saturn deploy batch app1,app2,app3', description: 'Deploy multiple apps' },
            { command: 'saturn deploy batch app1,app2 --wait', description: 'Deploy and wait for all to complete' },
        ],
    },
    {
        name: 'deploy list',
        description: 'List deployments for an application',
        usage: 'saturn deploy list <app-uuid>',
        category: 'Deployments',
    },
    {
        name: 'deploy cancel',
        description: 'Cancel a running deployment',
        usage: 'saturn deploy cancel <deployment-uuid>',
        category: 'Deployments',
    },
    {
        name: 'deploy tag',
        description: 'Deploy all resources by tag name',
        usage: 'saturn deploy tag <tag-name>',
        category: 'Deployments',
        options: [
            { flag: '--force', description: 'Force deployment' },
            { flag: '-w, --wait', description: 'Wait for all deployments to complete' },
            { flag: '--timeout <seconds>', description: 'Timeout in seconds when using --wait (default 600)' },
        ],
        examples: [
            { command: 'saturn deploy tag v1.0.0', description: 'Deploy all resources tagged v1.0.0' },
            { command: 'saturn deploy tag production --force', description: 'Force deploy by tag' },
            { command: 'saturn deploy tag v1.0.0 --wait', description: 'Deploy by tag and wait for completion' },
        ],
    },
    {
        name: 'deploy pr',
        description: 'Deploy a PR preview for an application',
        usage: 'saturn deploy pr <app-uuid> <pr-id>',
        category: 'Deployments',
        options: [
            { flag: '--force', description: 'Force deployment' },
            { flag: '-w, --wait', description: 'Wait for deployment to complete' },
            { flag: '--timeout <seconds>', description: 'Timeout in seconds when using --wait (default 600)' },
        ],
        examples: [
            { command: 'saturn deploy pr abc-123 42', description: 'Deploy PR #42 preview' },
            { command: 'saturn deploy pr abc-123 42 --force', description: 'Force deploy PR preview' },
            { command: 'saturn deploy pr abc-123 42 --wait', description: 'Deploy PR and wait for completion' },
        ],
    },
    // Services
    {
        name: 'service list',
        description: 'List all one-click services',
        usage: 'saturn service list',
        category: 'Services',
    },
    {
        name: 'service create',
        description: 'Create a one-click service (n8n, WordPress, etc.)',
        usage: 'saturn service create <type> [options]',
        category: 'Services',
        options: [
            { flag: '--server-uuid <uuid>', description: 'Target server UUID' },
            { flag: '--project-uuid <uuid>', description: 'Project UUID' },
            { flag: '--environment-name <name>', description: 'Environment name' },
            { flag: '--name <name>', description: 'Custom service name' },
            { flag: '--instant-deploy', description: 'Deploy immediately after creation' },
            { flag: '--list-types', description: 'List available service types' },
        ],
        examples: [
            { command: 'saturn service create --list-types', description: 'List all available services' },
            { command: 'saturn service create n8n --server-uuid=<uuid> --project-uuid=<uuid> --environment-name=production --instant-deploy', description: 'Deploy n8n instantly' },
        ],
    },
    {
        name: 'service start / stop / restart',
        description: 'Control service lifecycle',
        usage: 'saturn service start|stop|restart <uuid>',
        category: 'Services',
    },
    // Databases
    {
        name: 'database list',
        description: 'List all databases',
        usage: 'saturn database list',
        category: 'Databases',
    },
    {
        name: 'database create',
        description: 'Create a standalone database',
        usage: 'saturn database create <type> [options]',
        category: 'Databases',
        examples: [
            { command: 'saturn database create postgresql --server-uuid=<uuid> --project-uuid=<uuid> --environment-name=production', description: 'Create PostgreSQL database' },
        ],
    },
    {
        name: 'database backup',
        description: 'Manage database backups',
        usage: 'saturn database backup list|create|trigger <uuid>',
        category: 'Databases',
    },
    // Servers
    {
        name: 'server list',
        description: 'List all connected servers',
        usage: 'saturn server list',
        category: 'Servers',
    },
    {
        name: 'server get',
        description: 'Get server details',
        usage: 'saturn server get <uuid>',
        category: 'Servers',
    },
    {
        name: 'server validate',
        description: 'Validate server SSH connection',
        usage: 'saturn server validate <uuid>',
        category: 'Servers',
    },
    // Projects
    {
        name: 'project list',
        description: 'List all projects',
        usage: 'saturn project list',
        category: 'Projects',
    },
    {
        name: 'project create',
        description: 'Create a new project',
        usage: 'saturn project create --name <name>',
        category: 'Projects',
        examples: [
            { command: 'saturn project create --name "My Project"', description: 'Create a new project' },
        ],
    },
    // Utility
    {
        name: 'version',
        description: 'Show CLI version',
        usage: 'saturn version',
        category: 'Utility',
    },
    {
        name: 'update',
        description: 'Update CLI to the latest version',
        usage: 'saturn update',
        category: 'Utility',
    },
    {
        name: 'teams list / current',
        description: 'List teams or show current team',
        usage: 'saturn teams list|current',
        category: 'Utility',
    },
    {
        name: 'completion',
        description: 'Generate shell completion scripts',
        usage: 'saturn completion <bash|zsh|fish|powershell>',
        category: 'Utility',
    },
];

const categories = Array.from(new Set(commands.map(cmd => cmd.category)));

const cliTabs = [
    { id: 'setup', label: 'Setup', href: '/cli/setup' },
    { id: 'commands', label: 'Commands', href: '/cli/commands' },
];

export default function CLICommands() {
    const [searchQuery, setSearchQuery] = React.useState('');
    const [expandedCommands, setExpandedCommands] = React.useState<Set<string>>(new Set());
    const { addToast } = useToast();

    const filteredCommands = commands.filter(cmd =>
        cmd.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        cmd.description.toLowerCase().includes(searchQuery.toLowerCase()) ||
        cmd.category.toLowerCase().includes(searchQuery.toLowerCase())
    );

    const groupedCommands = categories.reduce((acc, category) => {
        acc[category] = filteredCommands.filter(cmd => cmd.category === category);
        return acc;
    }, {} as Record<string, CLICommand[]>);

    const toggleCommand = (commandName: string) => {
        setExpandedCommands(prev => {
            const newSet = new Set(prev);
            if (newSet.has(commandName)) {
                newSet.delete(commandName);
            } else {
                newSet.add(commandName);
            }
            return newSet;
        });
    };

    const handleCopy = (text: string) => {
        navigator.clipboard.writeText(text);
        addToast('success', 'Copied to clipboard', 'Command copied to clipboard.');
    };

    return (
        <AppLayout title="CLI Commands">
            <div className="mx-auto max-w-5xl space-y-6">
                {/* Header */}
                <div className="space-y-2">
                    <h1 className="text-2xl font-semibold text-foreground">CLI Command Reference</h1>
                    <p className="text-sm text-foreground-muted">
                        Complete guide to all Saturn CLI commands
                    </p>
                </div>

                {/* CLI Tab Navigation */}
                <div className="flex items-center gap-1 border-b border-border">
                    {cliTabs.map((tab) => (
                        <Link
                            key={tab.id}
                            href={tab.href}
                            className={cn(
                                'px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px',
                                tab.id === 'commands'
                                    ? 'border-primary text-foreground'
                                    : 'border-transparent text-foreground-muted hover:text-foreground hover:border-border'
                            )}
                        >
                            {tab.label}
                        </Link>
                    ))}
                </div>

                {/* Search */}
                <Card>
                    <CardContent className="py-4">
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                            <Input
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Search commands..."
                                className="pl-10"
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* Quick Reference */}
                <Card>
                    <CardHeader>
                        <CardTitle>Quick Reference</CardTitle>
                        <CardDescription>
                            Essential commands to get started
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="rounded-lg border border-border bg-background p-3">
                                <code className="text-sm font-medium text-foreground">saturn login &lt;url&gt;</code>
                                <p className="mt-1 text-xs text-foreground-muted">Authenticate via browser</p>
                            </div>
                            <div className="rounded-lg border border-border bg-background p-3">
                                <code className="text-sm font-medium text-foreground">saturn deploy uuid &lt;uuid&gt; --wait</code>
                                <p className="mt-1 text-xs text-foreground-muted">Deploy and wait for completion</p>
                            </div>
                            <div className="rounded-lg border border-border bg-background p-3">
                                <code className="text-sm font-medium text-foreground">saturn app list</code>
                                <p className="mt-1 text-xs text-foreground-muted">List all applications</p>
                            </div>
                            <div className="rounded-lg border border-border bg-background p-3">
                                <code className="text-sm font-medium text-foreground">saturn service create</code>
                                <p className="mt-1 text-xs text-foreground-muted">Create one-click service</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Global Flags */}
                <Card>
                    <CardHeader>
                        <CardTitle>Global Flags</CardTitle>
                        <CardDescription>
                            Available for all commands
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-2 sm:grid-cols-2">
                            <div className="rounded-lg border border-border bg-background-secondary p-3">
                                <code className="text-sm font-medium text-foreground">--token &lt;token&gt;</code>
                                <p className="mt-1 text-xs text-foreground-muted">Override authentication token</p>
                            </div>
                            <div className="rounded-lg border border-border bg-background-secondary p-3">
                                <code className="text-sm font-medium text-foreground">--context &lt;name&gt;</code>
                                <p className="mt-1 text-xs text-foreground-muted">Use specific context</p>
                            </div>
                            <div className="rounded-lg border border-border bg-background-secondary p-3">
                                <code className="text-sm font-medium text-foreground">--format table|json|pretty</code>
                                <p className="mt-1 text-xs text-foreground-muted">Output format</p>
                            </div>
                            <div className="rounded-lg border border-border bg-background-secondary p-3">
                                <code className="text-sm font-medium text-foreground">--debug</code>
                                <p className="mt-1 text-xs text-foreground-muted">Enable debug logging</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Commands by Category */}
                {Object.entries(groupedCommands).map(([category, cmds]) => (
                    cmds.length > 0 && (
                        <Card key={category}>
                            <CardHeader>
                                <div className="flex items-center gap-2">
                                    <CardTitle>{category}</CardTitle>
                                    <Badge variant="default">{cmds.length}</Badge>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    {cmds.map((cmd) => {
                                        const isExpanded = expandedCommands.has(cmd.name);
                                        return (
                                            <div
                                                key={cmd.name}
                                                className="rounded-lg border border-border bg-background"
                                            >
                                                {/* Command Header */}
                                                <button
                                                    onClick={() => toggleCommand(cmd.name)}
                                                    className="flex w-full items-center justify-between p-4 text-left transition-colors hover:bg-background-secondary"
                                                >
                                                    <div className="flex-1">
                                                        <div className="flex items-center gap-3">
                                                            <Terminal className="h-4 w-4 text-primary" />
                                                            <code className="font-medium text-foreground">
                                                                saturn {cmd.name}
                                                            </code>
                                                        </div>
                                                        <p className="mt-1 text-sm text-foreground-muted">
                                                            {cmd.description}
                                                        </p>
                                                    </div>
                                                    {isExpanded ? (
                                                        <ChevronDown className="h-5 w-5 text-foreground-muted" />
                                                    ) : (
                                                        <ChevronRight className="h-5 w-5 text-foreground-muted" />
                                                    )}
                                                </button>

                                                {/* Command Details */}
                                                {isExpanded && (
                                                    <div className="space-y-4 border-t border-border p-4">
                                                        {/* Usage */}
                                                        <div>
                                                            <p className="mb-2 text-sm font-medium text-foreground">
                                                                Usage
                                                            </p>
                                                            <div className="relative">
                                                                <pre className="overflow-x-auto rounded-lg bg-background-tertiary p-3 pr-12">
                                                                    <code className="text-sm text-foreground">
                                                                        {cmd.usage}
                                                                    </code>
                                                                </pre>
                                                                <button
                                                                    onClick={() => handleCopy(cmd.usage)}
                                                                    className="absolute right-2 top-2 rounded-md p-1.5 text-foreground-muted transition-colors hover:bg-background-secondary hover:text-foreground"
                                                                >
                                                                    <Copy className="h-3 w-3" />
                                                                </button>
                                                            </div>
                                                        </div>

                                                        {/* Options */}
                                                        {cmd.options && cmd.options.length > 0 && (
                                                            <div>
                                                                <p className="mb-2 text-sm font-medium text-foreground">
                                                                    Options
                                                                </p>
                                                                <div className="space-y-2">
                                                                    {cmd.options.map((option, idx) => (
                                                                        <div
                                                                            key={idx}
                                                                            className="rounded-lg border border-border bg-background-secondary p-3"
                                                                        >
                                                                            <code className="text-sm font-medium text-foreground">
                                                                                {option.flag}
                                                                            </code>
                                                                            <p className="mt-1 text-sm text-foreground-muted">
                                                                                {option.description}
                                                                            </p>
                                                                        </div>
                                                                    ))}
                                                                </div>
                                                            </div>
                                                        )}

                                                        {/* Examples */}
                                                        {cmd.examples && cmd.examples.length > 0 && (
                                                            <div>
                                                                <p className="mb-2 text-sm font-medium text-foreground">
                                                                    Examples
                                                                </p>
                                                                <div className="space-y-3">
                                                                    {cmd.examples.map((example, idx) => (
                                                                        <div key={idx}>
                                                                            <p className="mb-1 text-xs text-foreground-subtle">
                                                                                {example.description}
                                                                            </p>
                                                                            <div className="relative">
                                                                                <pre className="overflow-x-auto rounded-lg bg-background-tertiary p-3 pr-12">
                                                                                    <code className="text-sm text-foreground">
                                                                                        {example.command}
                                                                                    </code>
                                                                                </pre>
                                                                                <button
                                                                                    onClick={() => handleCopy(example.command)}
                                                                                    className="absolute right-2 top-2 rounded-md p-1.5 text-foreground-muted transition-colors hover:bg-background-secondary hover:text-foreground"
                                                                                >
                                                                                    <Copy className="h-3 w-3" />
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                    ))}
                                                                </div>
                                                            </div>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </CardContent>
                        </Card>
                    )
                ))}
            </div>
        </AppLayout>
    );
}
