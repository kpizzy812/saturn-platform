import * as React from 'react';
import { SettingsLayout } from './Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Input, Button, Modal, ModalFooter, Badge, useToast } from '@/components/ui';
import { Terminal, Copy, Check, Plus, ExternalLink, Info } from 'lucide-react';
import { cn } from '@/lib/utils';

const ABILITIES: { id: string; label: string; description: string; required?: boolean }[] = [
    { id: 'read',           label: 'read',           description: 'List and view all resources', required: true },
    { id: 'deploy',         label: 'deploy',          description: 'Deploy, start/stop/restart, rollback, approve', required: true },
    { id: 'write',          label: 'write',           description: 'Create, update, delete resources, manage envs and backups', required: true },
    { id: 'read:sensitive', label: 'read:sensitive',  description: 'Read env var values (otherwise they are masked)' },
];

interface Props {
    saturn_url: string;
}

type TabId = 'claude-code' | 'cursor' | 'claude-desktop' | 'vscode';

const TABS: { id: TabId; label: string }[] = [
    { id: 'claude-code', label: 'Claude Code' },
    { id: 'cursor', label: 'Cursor' },
    { id: 'claude-desktop', label: 'Claude Desktop' },
    { id: 'vscode', label: 'VS Code' },
];

function buildSnippets(saturnUrl: string, token: string): Record<TabId, string> {
    const t = token.trim() || 'YOUR_TOKEN';
    const args = ['--yes', 'tsx', 'mcp/src/index.ts', '--url', saturnUrl, '--token', t];
    return {
        'claude-code': `claude mcp add saturn -- npx --yes tsx mcp/src/index.ts --url ${saturnUrl} --token ${t}`,
        'cursor': JSON.stringify({ saturn: { command: 'npx', args } }, null, 2),
        'claude-desktop': JSON.stringify({ mcpServers: { saturn: { command: 'npx', args } } }, null, 2),
        'vscode': JSON.stringify({ 'mcp.servers': { saturn: { command: 'npx', args } } }, null, 2),
    };
}

const TAB_HINTS: Record<TabId, string> = {
    'claude-code': 'Run this command in your terminal. Verify with: claude mcp list',
    'cursor': 'Open Settings → MCP and paste this JSON config.',
    'claude-desktop': 'Edit ~/.claude/claude_desktop_config.json and add/merge this block.',
    'vscode': 'Add to your .vscode/settings.json or VS Code User Settings.',
};

export default function MCPSettings({ saturn_url }: Props) {
    const { toast } = useToast();
    const [token, setToken] = React.useState('');
    const [activeTab, setActiveTab] = React.useState<TabId>('claude-code');
    const [showCreateModal, setShowCreateModal] = React.useState(false);
    const [creating, setCreating] = React.useState(false);
    const [newTokenName, setNewTokenName] = React.useState('MCP Token');
    const [selectedAbilities, setSelectedAbilities] = React.useState<string[]>(['read', 'deploy', 'write']);
    const [copied, setCopied] = React.useState<string | null>(null);

    const snippets = buildSnippets(saturn_url, token);

    const handleGenerateToken = async (e: React.FormEvent) => {
        e.preventDefault();
        setCreating(true);

        try {
            await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });

            const xsrfCookie = document.cookie
                .split('; ')
                .find(row => row.startsWith('XSRF-TOKEN='))
                ?.split('=')[1];
            const csrfToken = xsrfCookie
                ? decodeURIComponent(xsrfCookie)
                : document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const response = await fetch('/settings/tokens', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-XSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ name: newTokenName || 'MCP Token', abilities: selectedAbilities }),
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || 'Failed to create token');
            }

            const data = await response.json();
            setToken(data.token);
            setShowCreateModal(false);
            toast({ title: 'Token created', description: 'Your API token is ready. The commands below are now filled in.' });
        } catch (error) {
            toast({
                title: 'Failed to create token',
                description: error instanceof Error ? error.message : 'An error occurred.',
                variant: 'error',
            });
        } finally {
            setCreating(false);
        }
    };

    const copyToClipboard = async (text: string, key: string) => {
        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(text);
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            }
            setCopied(key);
            setTimeout(() => setCopied(null), 2000);
            toast({ title: 'Copied to clipboard' });
        } catch {
            toast({ title: 'Failed to copy', variant: 'error' });
        }
    };

    return (
        <SettingsLayout activeSection="mcp">
            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h2 className="text-xl font-semibold text-foreground">MCP / AI Tools</h2>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Connect Claude, Cursor, and other AI agents to manage your Saturn infrastructure directly from chat.
                    </p>
                </div>

                {/* Step 1: Token */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <span className="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">1</span>
                            <CardTitle className="text-base">Get an API Token</CardTitle>
                        </div>
                        <CardDescription>
                            Paste an existing token or generate a new one. For full access to all 96 tools, select{' '}
                            <Badge variant="outline">read</Badge> + <Badge variant="outline">deploy</Badge> + <Badge variant="outline">write</Badge>.
                            Add <Badge variant="outline">read:sensitive</Badge> to also read env var values.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center gap-3">
                            <Input
                                type="password"
                                value={token}
                                onChange={e => setToken(e.target.value)}
                                placeholder="Paste your token here, or generate one below"
                                className="font-mono text-sm"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setShowCreateModal(true)}
                                className="shrink-0"
                            >
                                <Plus className="mr-2 h-4 w-4" />
                                Generate
                            </Button>
                        </div>
                        {token && (
                            <p className="mt-2 flex items-center gap-1.5 text-xs text-success">
                                <Check className="h-3.5 w-3.5" />
                                Token set — commands below are ready to copy.
                            </p>
                        )}
                    </CardContent>
                </Card>

                {/* Step 2: Install commands */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <span className="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">2</span>
                            <CardTitle className="text-base">Add to Your AI Tool</CardTitle>
                        </div>
                        <CardDescription>
                            Copy the ready-made command for your tool of choice.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {/* Tabs */}
                        <div className="flex gap-1 rounded-lg bg-background-secondary p-1">
                            {TABS.map(tab => (
                                <button
                                    key={tab.id}
                                    onClick={() => setActiveTab(tab.id)}
                                    className={cn(
                                        'flex-1 rounded-md px-3 py-1.5 text-xs font-medium transition-colors',
                                        activeTab === tab.id
                                            ? 'bg-background text-foreground shadow-sm'
                                            : 'text-foreground-muted hover:text-foreground'
                                    )}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>

                        {/* Hint */}
                        <p className="text-xs text-foreground-muted">{TAB_HINTS[activeTab]}</p>

                        {/* Code block */}
                        <div className="relative">
                            <pre className="overflow-x-auto rounded-lg border border-border bg-background-secondary p-4 text-xs font-mono text-foreground leading-relaxed whitespace-pre-wrap break-all">
                                {snippets[activeTab]}
                            </pre>
                            <button
                                onClick={() => copyToClipboard(snippets[activeTab], activeTab)}
                                className={cn(
                                    'absolute right-2 top-2 flex items-center gap-1.5 rounded-md px-2 py-1 text-xs transition-colors',
                                    copied === activeTab
                                        ? 'bg-success/10 text-success'
                                        : 'bg-background text-foreground-muted hover:text-foreground border border-border'
                                )}
                            >
                                {copied === activeTab ? (
                                    <><Check className="h-3 w-3" /> Copied</>
                                ) : (
                                    <><Copy className="h-3 w-3" /> Copy</>
                                )}
                            </button>
                        </div>
                    </CardContent>
                </Card>

                {/* Footer info */}
                <div className="flex items-center justify-between text-xs text-foreground-muted">
                    <div className="flex items-center gap-1.5">
                        <Terminal className="h-3.5 w-3.5" />
                        <span>96 tools available · Start with <code className="rounded bg-background-secondary px-1">saturn_overview</code></span>
                    </div>
                    <a
                        href="https://github.com/your-org/saturn/blob/main/mcp/README.md"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex items-center gap-1 hover:text-foreground transition-colors"
                    >
                        Docs <ExternalLink className="h-3 w-3" />
                    </a>
                </div>
            </div>

            {/* Generate Token Modal */}
            <Modal
                isOpen={showCreateModal}
                onClose={() => setShowCreateModal(false)}
                title="Generate MCP Token"
                description="Creates a new API token with read + deploy abilities — the minimum needed for AI agents."
            >
                <form onSubmit={handleGenerateToken}>
                    <div className="space-y-4 py-2">
                        <div className="space-y-1.5">
                            <label className="text-sm font-medium text-foreground">Token name</label>
                            <Input
                                value={newTokenName}
                                onChange={e => setNewTokenName(e.target.value)}
                                placeholder="MCP Token"
                                autoFocus
                            />
                        </div>
                        <div className="space-y-2">
                            <label className="text-sm font-medium text-foreground">Abilities</label>
                            {ABILITIES.map(ability => (
                                <label key={ability.id} className="flex items-start gap-3 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={selectedAbilities.includes(ability.id)}
                                        onChange={e => {
                                            if (ability.required) return;
                                            setSelectedAbilities(prev =>
                                                e.target.checked
                                                    ? [...prev, ability.id]
                                                    : prev.filter(a => a !== ability.id)
                                            );
                                        }}
                                        disabled={ability.required}
                                        className="mt-0.5 h-4 w-4 rounded border-border"
                                    />
                                    <div>
                                        <span className="text-sm font-mono text-foreground">{ability.label}</span>
                                        {ability.required && <span className="ml-1.5 text-xs text-foreground-muted">(required)</span>}
                                        <p className="text-xs text-foreground-muted">{ability.description}</p>
                                    </div>
                                </label>
                            ))}
                        </div>
                        <div className="flex items-start gap-2 rounded-lg bg-background-secondary p-3 text-xs text-foreground-muted">
                            <Info className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                            <span>For full access to all 96 MCP tools, keep <code className="font-mono">read + deploy + write</code> selected.</span>
                        </div>
                    </div>
                    <ModalFooter>
                        <Button type="button" variant="outline" onClick={() => setShowCreateModal(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={creating}>
                            {creating ? 'Creating…' : 'Create Token'}
                        </Button>
                    </ModalFooter>
                </form>
            </Modal>
        </SettingsLayout>
    );
}
