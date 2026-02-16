import * as React from 'react';
import { Head } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Input, Badge, useToast } from '@/components/ui';
import { Terminal, Copy, Search, ChevronDown, ChevronRight, Book } from 'lucide-react';

interface CLICommand {
    name: string;
    description: string;
    usage: string;
    category: string;
    options?: { flag: string; description: string }[];
    examples?: { command: string; description: string }[];
}

const commands: CLICommand[] = [
    {
        name: 'login',
        description: 'Authenticate with your Saturn account',
        usage: 'saturn login [options]',
        category: 'Authentication',
        options: [
            { flag: '--token <token>', description: 'Use an API token for authentication' },
            { flag: '--org <org>', description: 'Specify organization to login to' },
        ],
        examples: [
            { command: 'saturn login', description: 'Interactive login via browser' },
            { command: 'saturn login --token sat_xxx', description: 'Login with API token' },
        ],
    },
    {
        name: 'logout',
        description: 'Sign out from your Saturn account',
        usage: 'saturn logout',
        category: 'Authentication',
        examples: [
            { command: 'saturn logout', description: 'Clear local authentication' },
        ],
    },
    {
        name: 'deploy',
        description: 'Deploy your application to Saturn',
        usage: 'saturn deploy [options]',
        category: 'Deployment',
        options: [
            { flag: '--env <environment>', description: 'Specify deployment environment' },
            { flag: '--build-args <args>', description: 'Pass build arguments' },
            { flag: '--watch', description: 'Watch for changes and auto-deploy' },
            { flag: '--detach', description: 'Run deployment in background' },
        ],
        examples: [
            { command: 'saturn deploy', description: 'Deploy current directory' },
            { command: 'saturn deploy --env production', description: 'Deploy to production environment' },
            { command: 'saturn deploy --watch', description: 'Deploy and watch for changes' },
        ],
    },
    {
        name: 'logs',
        description: 'View application logs',
        usage: 'saturn logs [service] [options]',
        category: 'Deployment',
        options: [
            { flag: '--follow, -f', description: 'Follow log output' },
            { flag: '--tail <n>', description: 'Show last N lines' },
            { flag: '--since <time>', description: 'Show logs since timestamp' },
        ],
        examples: [
            { command: 'saturn logs', description: 'View recent logs' },
            { command: 'saturn logs api-server -f', description: 'Follow logs for api-server' },
            { command: 'saturn logs --tail 100', description: 'Show last 100 log lines' },
        ],
    },
    {
        name: 'run',
        description: 'Run your application locally with Saturn environment',
        usage: 'saturn run [command]',
        category: 'Development',
        options: [
            { flag: '--env <environment>', description: 'Use specific environment variables' },
            { flag: '--service <service>', description: 'Run specific service' },
        ],
        examples: [
            { command: 'saturn run npm start', description: 'Run npm start with Saturn env' },
            { command: 'saturn run --service api npm test', description: 'Run tests for api service' },
        ],
    },
    {
        name: 'env',
        description: 'Manage environment variables',
        usage: 'saturn env [action] [options]',
        category: 'Configuration',
        options: [
            { flag: 'list', description: 'List all environment variables' },
            { flag: 'set <key> <value>', description: 'Set an environment variable' },
            { flag: 'unset <key>', description: 'Remove an environment variable' },
            { flag: 'pull', description: 'Download remote env to .env file' },
            { flag: 'push', description: 'Upload local .env to remote' },
        ],
        examples: [
            { command: 'saturn env list', description: 'List all variables' },
            { command: 'saturn env set DATABASE_URL postgres://...', description: 'Set a variable' },
            { command: 'saturn env pull', description: 'Download variables to .env' },
        ],
    },
    {
        name: 'projects',
        description: 'List and manage projects',
        usage: 'saturn projects [action]',
        category: 'Projects',
        options: [
            { flag: 'list', description: 'List all projects' },
            { flag: 'switch <project>', description: 'Switch to a different project' },
            { flag: 'create <name>', description: 'Create a new project' },
        ],
        examples: [
            { command: 'saturn projects list', description: 'Show all projects' },
            { command: 'saturn projects switch my-api', description: 'Switch to my-api project' },
        ],
    },
    {
        name: 'services',
        description: 'Manage services in your project',
        usage: 'saturn services [action]',
        category: 'Projects',
        options: [
            { flag: 'list', description: 'List all services' },
            { flag: 'restart <service>', description: 'Restart a service' },
            { flag: 'scale <service> <replicas>', description: 'Scale a service' },
        ],
        examples: [
            { command: 'saturn services list', description: 'List all services' },
            { command: 'saturn services restart api', description: 'Restart api service' },
            { command: 'saturn services scale worker 3', description: 'Scale worker to 3 replicas' },
        ],
    },
    {
        name: 'status',
        description: 'Check deployment status',
        usage: 'saturn status [service]',
        category: 'Deployment',
        examples: [
            { command: 'saturn status', description: 'Show status of all services' },
            { command: 'saturn status api-server', description: 'Show status of api-server' },
        ],
    },
    {
        name: 'whoami',
        description: 'Display current authenticated user',
        usage: 'saturn whoami',
        category: 'Authentication',
        examples: [
            { command: 'saturn whoami', description: 'Show current user and organization' },
        ],
    },
];

const categories = Array.from(new Set(commands.map(cmd => cmd.category)));

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
        <>
            <Head title="CLI Commands | Saturn" />
            <div className="min-h-screen bg-background p-6">
                <div className="mx-auto max-w-5xl space-y-6">
                    {/* Header */}
                    <div className="space-y-2">
                        <h1 className="text-3xl font-bold text-foreground">CLI Command Reference</h1>
                        <p className="text-foreground-muted">
                            Complete guide to all Saturn CLI commands
                        </p>
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
                                    <code className="text-sm font-medium text-foreground">saturn login</code>
                                    <p className="mt-1 text-xs text-foreground-muted">Authenticate with Saturn</p>
                                </div>
                                <div className="rounded-lg border border-border bg-background p-3">
                                    <code className="text-sm font-medium text-foreground">saturn deploy</code>
                                    <p className="mt-1 text-xs text-foreground-muted">Deploy your application</p>
                                </div>
                                <div className="rounded-lg border border-border bg-background p-3">
                                    <code className="text-sm font-medium text-foreground">saturn logs -f</code>
                                    <p className="mt-1 text-xs text-foreground-muted">Follow application logs</p>
                                </div>
                                <div className="rounded-lg border border-border bg-background p-3">
                                    <code className="text-sm font-medium text-foreground">saturn run</code>
                                    <p className="mt-1 text-xs text-foreground-muted">Run locally with env</p>
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

                    {/* Documentation Link */}
                    <Card>
                        <CardContent className="py-4">
                            <a
                                href="https://docs.saturn.app/cli"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="flex items-center justify-between rounded-lg border border-border bg-background p-4 transition-colors hover:border-border/80 hover:bg-background-secondary"
                            >
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                        <Book className="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <p className="font-medium text-foreground">Full CLI Documentation</p>
                                        <p className="text-sm text-foreground-muted">
                                            Read the complete CLI guide
                                        </p>
                                    </div>
                                </div>
                                <ChevronRight className="h-5 w-5 text-foreground-muted" />
                            </a>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
