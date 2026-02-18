import * as React from 'react';
import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Input, Badge, useToast } from '@/components/ui';
import { Terminal, Copy, Download, CheckCircle2, ChevronRight, Command } from 'lucide-react';
import { cn } from '@/lib/utils';

interface InstallCommand {
    os: string;
    command: string;
    icon: React.ComponentType<{ className?: string }>;
    description: string;
}

function getInstallCommands(origin: string): InstallCommand[] {
    return [
        {
            os: 'macOS',
            command: 'brew install kpizzy812/saturn/saturn-cli',
            icon: Terminal,
            description: 'Install via Homebrew',
        },
        {
            os: 'Linux',
            command: `curl -fsSL ${origin}/install.sh | sh`,
            icon: Terminal,
            description: 'Install via shell script',
        },
        {
            os: 'Windows',
            command: `iwr ${origin}/install.ps1 -useb | iex`,
            icon: Terminal,
            description: 'Install via PowerShell',
        },
    ];
}

const cliTabs = [
    { id: 'setup', label: 'Setup', href: '/cli/setup' },
    { id: 'commands', label: 'Commands', href: '/cli/commands' },
];

export default function CLISetup() {
    const [selectedOS, setSelectedOS] = React.useState<string>('macOS');
    const [apiToken, setApiToken] = React.useState('');
    const { addToast } = useToast();

    const installCommands = getInstallCommands(window.location.origin);
    const selectedCommand = installCommands.find(cmd => cmd.os === selectedOS);

    const handleCopy = (text: string, label: string) => {
        navigator.clipboard.writeText(text);
        addToast('success', 'Copied to clipboard', `${label} copied to clipboard.`);
    };

    return (
        <AppLayout title="CLI Setup">
            <div className="mx-auto max-w-4xl space-y-6">
                {/* Header */}
                <div className="space-y-2">
                    <h1 className="text-2xl font-semibold text-foreground">Saturn CLI</h1>
                    <p className="text-sm text-foreground-muted">
                        Install and configure the Saturn command-line interface
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
                                tab.id === 'setup'
                                    ? 'border-primary text-foreground'
                                    : 'border-transparent text-foreground-muted hover:text-foreground hover:border-border'
                            )}
                        >
                            {tab.label}
                        </Link>
                    ))}
                </div>

                {/* Version Info */}
                <Card>
                    <CardContent className="flex items-center justify-between py-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                <Terminal className="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <p className="font-medium text-foreground">Latest Version</p>
                                <p className="text-sm text-foreground-muted">v1.4.0</p>
                            </div>
                        </div>
                        <Badge variant="success">Stable</Badge>
                    </CardContent>
                </Card>

                {/* Installation */}
                <Card>
                    <CardHeader>
                        <CardTitle>Installation</CardTitle>
                        <CardDescription>
                            Choose your operating system and run the installation command
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {/* OS Selector */}
                        <div className="mb-4 flex gap-2">
                            {installCommands.map((cmd) => (
                                <button
                                    key={cmd.os}
                                    onClick={() => setSelectedOS(cmd.os)}
                                    className={`rounded-lg px-4 py-2 text-sm font-medium transition-colors ${
                                        selectedOS === cmd.os
                                            ? 'bg-primary text-white'
                                            : 'bg-background-secondary text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
                                    }`}
                                >
                                    {cmd.os}
                                </button>
                            ))}
                        </div>

                        {/* Selected Command */}
                        {selectedCommand && (
                            <div className="space-y-3">
                                <p className="text-sm text-foreground-muted">
                                    {selectedCommand.description}
                                </p>
                                <div className="relative">
                                    <pre className="overflow-x-auto rounded-lg bg-background-tertiary p-4 pr-12">
                                        <code className="text-sm text-foreground">
                                            {selectedCommand.command}
                                        </code>
                                    </pre>
                                    <button
                                        onClick={() => handleCopy(selectedCommand.command, 'Command')}
                                        className="absolute right-3 top-3 rounded-md p-2 text-foreground-muted transition-colors hover:bg-background-secondary hover:text-foreground"
                                    >
                                        <Copy className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        )}

                        {/* Alternative: Download from GitHub */}
                        <div className="mt-6 rounded-lg border border-border bg-background p-4">
                            <p className="text-sm text-foreground-muted">
                                Or download binaries directly from{' '}
                                <a
                                    href="https://github.com/kpizzy812/saturn-cli/releases/latest"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="font-medium text-primary hover:underline"
                                >
                                    GitHub Releases
                                </a>
                            </p>
                        </div>
                    </CardContent>
                </Card>

                {/* Verify Installation */}
                <Card>
                    <CardHeader>
                        <CardTitle>Verify Installation</CardTitle>
                        <CardDescription>
                            Check that the CLI was installed correctly
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div>
                                <p className="mb-2 text-sm text-foreground-muted">
                                    Run this command to verify the installation:
                                </p>
                                <div className="relative">
                                    <pre className="overflow-x-auto rounded-lg bg-background-tertiary p-4 pr-12">
                                        <code className="text-sm text-foreground">
                                            saturn --version
                                        </code>
                                    </pre>
                                    <button
                                        onClick={() => handleCopy('saturn --version', 'Verify command')}
                                        className="absolute right-3 top-3 rounded-md p-2 text-foreground-muted transition-colors hover:bg-background-secondary hover:text-foreground"
                                    >
                                        <Copy className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            <div className="flex items-start gap-3 rounded-lg border border-primary/20 bg-primary/5 p-4">
                                <CheckCircle2 className="mt-0.5 h-5 w-5 flex-shrink-0 text-primary" />
                                <div>
                                    <p className="text-sm font-medium text-foreground">
                                        Expected output
                                    </p>
                                    <code className="mt-1 block text-sm text-foreground-muted">
                                        saturn version 1.4.0
                                    </code>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Login */}
                <Card>
                    <CardHeader>
                        <CardTitle>Authentication</CardTitle>
                        <CardDescription>
                            Connect the CLI to your Saturn account
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div>
                                <p className="mb-3 text-sm text-foreground-muted">
                                    Add your Saturn instance with an API token
                                </p>
                                <Input
                                    label="API Token"
                                    type="password"
                                    value={apiToken}
                                    onChange={(e) => setApiToken(e.target.value)}
                                    placeholder="Enter your API token"
                                    hint="Create a token in Settings → API Tokens"
                                />
                                <div className="relative mt-3">
                                    <pre className="overflow-x-auto rounded-lg bg-background-tertiary p-4 pr-12">
                                        <code className="text-sm text-foreground">
                                            saturn context add saturn {window.location.origin} {apiToken || 'YOUR_TOKEN'}
                                        </code>
                                    </pre>
                                    <button
                                        onClick={() => handleCopy(`saturn context add saturn ${window.location.origin} ${apiToken || 'YOUR_TOKEN'}`, 'Context add command')}
                                        className="absolute right-3 top-3 rounded-md p-2 text-foreground-muted transition-colors hover:bg-background-secondary hover:text-foreground"
                                    >
                                        <Copy className="h-4 w-4" />
                                    </button>
                                </div>
                                <p className="mt-2 text-xs text-foreground-subtle">
                                    This saves the connection to ~/.config/saturn/config.json
                                </p>
                            </div>

                            <div>
                                <p className="mb-3 text-sm text-foreground-muted">
                                    Verify the connection
                                </p>
                                <div className="relative">
                                    <pre className="overflow-x-auto rounded-lg bg-background-tertiary p-4 pr-12">
                                        <code className="text-sm text-foreground">
                                            saturn context verify
                                        </code>
                                    </pre>
                                    <button
                                        onClick={() => handleCopy('saturn context verify', 'Verify command')}
                                        className="absolute right-3 top-3 rounded-md p-2 text-foreground-muted transition-colors hover:bg-background-secondary hover:text-foreground"
                                    >
                                        <Copy className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Next Steps */}
                <Card>
                    <CardHeader>
                        <CardTitle>Next Steps</CardTitle>
                        <CardDescription>
                            Get started with the Saturn CLI
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            <Link
                                href="/cli/commands"
                                className="flex items-center justify-between rounded-lg border border-border bg-background p-4 transition-colors hover:border-border/80 hover:bg-background-secondary"
                            >
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                        <Command className="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <p className="font-medium text-foreground">View all commands</p>
                                        <p className="text-sm text-foreground-muted">
                                            Explore available CLI commands
                                        </p>
                                    </div>
                                </div>
                                <ChevronRight className="h-5 w-5 text-foreground-muted" />
                            </Link>

                            <Link
                                href="/settings/tokens"
                                className="flex items-center justify-between rounded-lg border border-border bg-background p-4 transition-colors hover:border-border/80 hover:bg-background-secondary"
                            >
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                        <Download className="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <p className="font-medium text-foreground">Create API token</p>
                                        <p className="text-sm text-foreground-muted">
                                            Generate a token for CI/CD pipelines
                                        </p>
                                    </div>
                                </div>
                                <ChevronRight className="h-5 w-5 text-foreground-muted" />
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
