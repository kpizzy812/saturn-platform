import * as React from 'react';
import { Head } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Input, Badge, useToast } from '@/components/ui';
import { Terminal, Copy, Download, CheckCircle2, ChevronRight, Command } from 'lucide-react';

interface InstallCommand {
    os: string;
    command: string;
    icon: React.ComponentType<{ className?: string }>;
    description: string;
}

const installCommands: InstallCommand[] = [
    {
        os: 'macOS',
        command: 'brew install saturn-cli',
        icon: Terminal,
        description: 'Install via Homebrew',
    },
    {
        os: 'Linux',
        command: 'curl -fsSL https://get.saturn.app/install.sh | sh',
        icon: Terminal,
        description: 'Install via shell script',
    },
    {
        os: 'Windows',
        command: 'iwr https://get.saturn.app/install.ps1 -useb | iex',
        icon: Terminal,
        description: 'Install via PowerShell',
    },
];

export default function CLISetup() {
    const [selectedOS, setSelectedOS] = React.useState<string>('macOS');
    const [apiToken, setApiToken] = React.useState('');
    const { addToast } = useToast();

    const selectedCommand = installCommands.find(cmd => cmd.os === selectedOS);

    const handleCopy = (text: string, label: string) => {
        navigator.clipboard.writeText(text);
        addToast('success', 'Copied to clipboard', `${label} copied to clipboard.`);
    };

    return (
        <>
            <Head title="CLI Setup | Saturn" />
            <div className="min-h-screen bg-background p-6">
                <div className="mx-auto max-w-4xl space-y-6">
                    {/* Header */}
                    <div className="space-y-2">
                        <h1 className="text-3xl font-bold text-foreground">Saturn CLI</h1>
                        <p className="text-foreground-muted">
                            Install and configure the Saturn command-line interface
                        </p>
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
                                    <p className="text-sm text-foreground-muted">v2.1.0</p>
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

                            {/* Alternative Installation Methods */}
                            <div className="mt-6 rounded-lg border border-border bg-background p-4">
                                <p className="mb-2 text-sm font-medium text-foreground">
                                    Alternative: Install via npm
                                </p>
                                <div className="relative">
                                    <pre className="overflow-x-auto rounded-lg bg-background-tertiary p-3 pr-12">
                                        <code className="text-sm text-foreground-muted">
                                            npm install -g @saturn/cli
                                        </code>
                                    </pre>
                                    <button
                                        onClick={() => handleCopy('npm install -g @saturn/cli', 'npm command')}
                                        className="absolute right-2 top-2 rounded-md p-1.5 text-foreground-muted transition-colors hover:bg-background-secondary hover:text-foreground"
                                    >
                                        <Copy className="h-3 w-3" />
                                    </button>
                                </div>
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
                                            saturn version 2.1.0
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
                                        Method 1: Interactive login (recommended)
                                    </p>
                                    <div className="relative">
                                        <pre className="overflow-x-auto rounded-lg bg-background-tertiary p-4 pr-12">
                                            <code className="text-sm text-foreground">
                                                saturn login
                                            </code>
                                        </pre>
                                        <button
                                            onClick={() => handleCopy('saturn login', 'Login command')}
                                            className="absolute right-3 top-3 rounded-md p-2 text-foreground-muted transition-colors hover:bg-background-secondary hover:text-foreground"
                                        >
                                            <Copy className="h-4 w-4" />
                                        </button>
                                    </div>
                                    <p className="mt-2 text-xs text-foreground-subtle">
                                        This will open your browser to authenticate
                                    </p>
                                </div>

                                <div className="my-4 flex items-center gap-3">
                                    <div className="h-px flex-1 bg-border" />
                                    <span className="text-xs text-foreground-subtle">OR</span>
                                    <div className="h-px flex-1 bg-border" />
                                </div>

                                <div>
                                    <p className="mb-3 text-sm text-foreground-muted">
                                        Method 2: Use an API token
                                    </p>
                                    <Input
                                        label="API Token"
                                        type="password"
                                        value={apiToken}
                                        onChange={(e) => setApiToken(e.target.value)}
                                        placeholder="sat_xxxxxxxxxxxxxxxx"
                                        hint="Create a token in Settings → API Tokens"
                                    />
                                    <div className="relative mt-3">
                                        <pre className="overflow-x-auto rounded-lg bg-background-tertiary p-4 pr-12">
                                            <code className="text-sm text-foreground">
                                                saturn login --token {apiToken || 'YOUR_TOKEN'}
                                            </code>
                                        </pre>
                                        <button
                                            onClick={() => handleCopy(`saturn login --token ${apiToken || 'YOUR_TOKEN'}`, 'Token login command')}
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
                                <a
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
                                </a>

                                <a
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
                                </a>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
