import * as React from 'react';
import { SettingsLayout } from './Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Input, Button, Modal, ModalFooter, Badge, Select, Checkbox, useToast } from '@/components/ui';
import { Key, Copy, Trash2, Plus, Eye, EyeOff, Clock, Activity } from 'lucide-react';

interface ApiToken {
    id: number;
    name: string;
    token: string;
    lastUsed?: string;
    createdAt: string;
    expiresAt?: string;
    scopes: string[];
}

interface TokenActivity {
    id: number;
    action: string;
    ip: string;
    timestamp: string;
}

const mockTokens: ApiToken[] = [
    {
        id: 1,
        name: 'Production Deploy',
        token: 'sat_prod_1a2b3c4d5e6f7g8h9i0j',
        lastUsed: '2024-03-28',
        createdAt: '2024-01-15',
        expiresAt: '2025-01-15',
        scopes: ['read', 'write', 'deploy'],
    },
    {
        id: 2,
        name: 'CI/CD Pipeline',
        token: 'sat_cicd_9i8h7g6f5e4d3c2b1a0z',
        lastUsed: '2024-03-27',
        createdAt: '2024-02-10',
        scopes: ['read', 'deploy'],
    },
    {
        id: 3,
        name: 'Development Testing',
        token: 'sat_dev_x1y2z3a4b5c6d7e8f9g0',
        createdAt: '2024-03-01',
        expiresAt: '2024-09-01',
        scopes: ['read'],
    },
];

const mockActivity: TokenActivity[] = [
    {
        id: 1,
        action: 'Deploy triggered',
        ip: '192.168.1.100',
        timestamp: '2024-03-28 14:32:15',
    },
    {
        id: 2,
        action: 'Environment variables fetched',
        ip: '192.168.1.100',
        timestamp: '2024-03-28 14:30:42',
    },
    {
        id: 3,
        action: 'Service status checked',
        ip: '10.0.0.25',
        timestamp: '2024-03-27 09:15:22',
    },
];

const scopeOptions = [
    { value: 'read', label: 'Read', description: 'View projects, services, and logs' },
    { value: 'write', label: 'Write', description: 'Update configurations and settings' },
    { value: 'deploy', label: 'Deploy', description: 'Trigger deployments' },
    { value: 'admin', label: 'Admin', description: 'Full access to all resources' },
];

const expirationOptions = [
    { value: '30', label: '30 days' },
    { value: '90', label: '90 days' },
    { value: '180', label: '6 months' },
    { value: '365', label: '1 year' },
    { value: 'never', label: 'Never' },
];

export default function APITokensSettings() {
    const [tokens, setTokens] = React.useState<ApiToken[]>(mockTokens);
    const [activity, setActivity] = React.useState<TokenActivity[]>(mockActivity);
    const [showCreateModal, setShowCreateModal] = React.useState(false);
    const [showRevokeModal, setShowRevokeModal] = React.useState(false);
    const [showNewTokenModal, setShowNewTokenModal] = React.useState(false);
    const [showActivityModal, setShowActivityModal] = React.useState(false);
    const [tokenToRevoke, setTokenToRevoke] = React.useState<ApiToken | null>(null);
    const [selectedToken, setSelectedToken] = React.useState<ApiToken | null>(null);
    const [newTokenName, setNewTokenName] = React.useState('');
    const [newTokenExpiration, setNewTokenExpiration] = React.useState('365');
    const [newTokenScopes, setNewTokenScopes] = React.useState<Set<string>>(new Set(['read']));
    const [newlyCreatedToken, setNewlyCreatedToken] = React.useState('');
    const [isCreating, setIsCreating] = React.useState(false);
    const [visibleTokens, setVisibleTokens] = React.useState<Set<number>>(new Set());
    const { addToast } = useToast();

    const handleCreateToken = (e: React.FormEvent) => {
        e.preventDefault();
        setIsCreating(true);

        // Simulate API call
        setTimeout(() => {
            const generatedToken = `sat_${newTokenName.toLowerCase().replace(/\s+/g, '_')}_${Math.random().toString(36).substring(2, 15)}`;

            let expiresAt: string | undefined;
            if (newTokenExpiration !== 'never') {
                const expiryDate = new Date();
                expiryDate.setDate(expiryDate.getDate() + parseInt(newTokenExpiration));
                expiresAt = expiryDate.toISOString().split('T')[0];
            }

            const newToken: ApiToken = {
                id: tokens.length + 1,
                name: newTokenName,
                token: generatedToken,
                createdAt: new Date().toISOString().split('T')[0],
                expiresAt,
                scopes: Array.from(newTokenScopes),
            };

            setTokens([...tokens, newToken]);
            setNewlyCreatedToken(generatedToken);
            setNewTokenName('');
            setNewTokenExpiration('365');
            setNewTokenScopes(new Set(['read']));
            setIsCreating(false);
            setShowCreateModal(false);
            setShowNewTokenModal(true);
        }, 1000);
    };

    const handleRevokeToken = () => {
        if (tokenToRevoke) {
            setTokens(tokens.filter((t) => t.id !== tokenToRevoke.id));
            setTokenToRevoke(null);
            setShowRevokeModal(false);
            addToast('success', 'Token revoked', 'The API token has been revoked successfully.');
        }
    };

    const handleCopyToken = (token: string) => {
        navigator.clipboard.writeText(token);
        addToast('success', 'Copied to clipboard', 'API token copied to clipboard.');
    };

    const toggleTokenVisibility = (tokenId: number) => {
        setVisibleTokens((prev) => {
            const newSet = new Set(prev);
            if (newSet.has(tokenId)) {
                newSet.delete(tokenId);
            } else {
                newSet.add(tokenId);
            }
            return newSet;
        });
    };

    const toggleScope = (scope: string) => {
        setNewTokenScopes(prev => {
            const newSet = new Set(prev);
            if (newSet.has(scope)) {
                newSet.delete(scope);
            } else {
                newSet.add(scope);
            }
            return newSet;
        });
    };

    const maskToken = (token: string) => {
        const parts = token.split('_');
        if (parts.length >= 3) {
            return `${parts[0]}_${parts[1]}_${'•'.repeat(parts[2].length)}`;
        }
        return '•'.repeat(token.length);
    };

    const isTokenExpired = (expiresAt?: string) => {
        if (!expiresAt) return false;
        return new Date(expiresAt) < new Date();
    };

    const getDaysUntilExpiry = (expiresAt?: string) => {
        if (!expiresAt) return null;
        const days = Math.ceil((new Date(expiresAt).getTime() - new Date().getTime()) / (1000 * 60 * 60 * 24));
        return days;
    };

    return (
        <SettingsLayout activeSection="tokens">
            <div className="space-y-6">
                {/* API Tokens */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>API Tokens</CardTitle>
                                <CardDescription>
                                    Manage API tokens for programmatic access
                                </CardDescription>
                            </div>
                            <Button onClick={() => setShowCreateModal(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Create Token
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {tokens.length === 0 ? (
                            <div className="rounded-lg border-2 border-dashed border-border p-8 text-center">
                                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-background-tertiary">
                                    <Key className="h-6 w-6 text-foreground-muted" />
                                </div>
                                <h3 className="mt-4 text-sm font-medium text-foreground">No API tokens</h3>
                                <p className="mt-1 text-sm text-foreground-muted">
                                    Create your first API token to get started
                                </p>
                                <Button className="mt-4" onClick={() => setShowCreateModal(true)}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Create Token
                                </Button>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {tokens.map((token) => {
                                    const isVisible = visibleTokens.has(token.id);
                                    const expired = isTokenExpired(token.expiresAt);
                                    const daysUntilExpiry = getDaysUntilExpiry(token.expiresAt);

                                    return (
                                        <div
                                            key={token.id}
                                            className="flex items-center justify-between rounded-lg border border-border bg-background p-4"
                                        >
                                            <div className="flex-1">
                                                <div className="flex items-center gap-3">
                                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                                        <Key className="h-5 w-5 text-primary" />
                                                    </div>
                                                    <div className="flex-1">
                                                        <div className="flex items-center gap-2">
                                                            <p className="font-medium text-foreground">{token.name}</p>
                                                            {expired && (
                                                                <Badge variant="danger">Expired</Badge>
                                                            )}
                                                            {!expired && daysUntilExpiry !== null && daysUntilExpiry <= 30 && (
                                                                <Badge variant="warning">
                                                                    Expires in {daysUntilExpiry} days
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        <div className="mt-1 flex items-center gap-2">
                                                            <code className="text-xs text-foreground-muted">
                                                                {isVisible ? token.token : maskToken(token.token)}
                                                            </code>
                                                            <button
                                                                onClick={() => toggleTokenVisibility(token.id)}
                                                                className="text-foreground-muted transition-colors hover:text-foreground"
                                                            >
                                                                {isVisible ? (
                                                                    <EyeOff className="h-3 w-3" />
                                                                ) : (
                                                                    <Eye className="h-3 w-3" />
                                                                )}
                                                            </button>
                                                        </div>
                                                        <div className="mt-2 flex flex-wrap items-center gap-2">
                                                            {token.scopes.map(scope => (
                                                                <Badge key={scope} variant="default" className="text-xs">
                                                                    {scope}
                                                                </Badge>
                                                            ))}
                                                        </div>
                                                        {token.lastUsed ? (
                                                            <p className="mt-2 text-xs text-foreground-subtle">
                                                                Last used {new Date(token.lastUsed).toLocaleDateString()}
                                                            </p>
                                                        ) : (
                                                            <Badge variant="warning" className="mt-2">
                                                                Never used
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => {
                                                        setSelectedToken(token);
                                                        setShowActivityModal(true);
                                                    }}
                                                    title="View activity"
                                                >
                                                    <Activity className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleCopyToken(token.token)}
                                                >
                                                    <Copy className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => {
                                                        setTokenToRevoke(token);
                                                        setShowRevokeModal(true);
                                                    }}
                                                >
                                                    <Trash2 className="h-4 w-4 text-danger" />
                                                </Button>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Token Usage Info */}
                <Card>
                    <CardHeader>
                        <CardTitle>Using API Tokens</CardTitle>
                        <CardDescription>
                            How to authenticate with Saturn API
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3 text-sm">
                            <p className="text-foreground-muted">
                                Include your API token in the Authorization header:
                            </p>
                            <pre className="overflow-x-auto rounded-lg bg-background p-4 text-foreground-muted">
                                <code>curl -H "Authorization: Bearer YOUR_TOKEN" https://api.saturn.app/v1/...</code>
                            </pre>
                            <p className="text-foreground-subtle">
                                Keep your tokens secure and never share them publicly. Tokens have the same permissions as your account.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Create Token Modal */}
            <Modal
                isOpen={showCreateModal}
                onClose={() => setShowCreateModal(false)}
                title="Create API Token"
                description="Configure your new API token"
                size="lg"
            >
                <form onSubmit={handleCreateToken}>
                    <div className="space-y-4">
                        <Input
                            label="Token Name"
                            value={newTokenName}
                            onChange={(e) => setNewTokenName(e.target.value)}
                            placeholder="e.g., Production Deploy"
                            required
                        />

                        <Select
                            label="Expiration"
                            value={newTokenExpiration}
                            onChange={(e) => setNewTokenExpiration(e.target.value)}
                            options={expirationOptions}
                        />

                        <div>
                            <label className="mb-2 block text-sm font-medium text-foreground">
                                Scopes
                            </label>
                            <p className="mb-3 text-sm text-foreground-muted">
                                Select the permissions for this token
                            </p>
                            <div className="space-y-2">
                                {scopeOptions.map(scope => (
                                    <div
                                        key={scope.value}
                                        className="rounded-lg border border-border bg-background p-3"
                                    >
                                        <Checkbox
                                            label={scope.label}
                                            checked={newTokenScopes.has(scope.value)}
                                            onChange={() => toggleScope(scope.value)}
                                        />
                                        <p className="ml-6 mt-1 text-xs text-foreground-muted">
                                            {scope.description}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    <ModalFooter>
                        <Button type="button" variant="secondary" onClick={() => setShowCreateModal(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" loading={isCreating} disabled={newTokenScopes.size === 0}>
                            Create Token
                        </Button>
                    </ModalFooter>
                </form>
            </Modal>

            {/* New Token Created Modal */}
            <Modal
                isOpen={showNewTokenModal}
                onClose={() => setShowNewTokenModal(false)}
                title="Token Created Successfully"
                description="Make sure to copy your token now. You won't be able to see it again!"
                size="lg"
            >
                <div className="space-y-4">
                    <div className="rounded-lg bg-background p-4">
                        <code className="break-all text-sm text-foreground">{newlyCreatedToken}</code>
                    </div>
                    <Button
                        variant="secondary"
                        className="w-full"
                        onClick={() => handleCopyToken(newlyCreatedToken)}
                    >
                        <Copy className="mr-2 h-4 w-4" />
                        Copy to Clipboard
                    </Button>
                </div>

                <ModalFooter>
                    <Button onClick={() => setShowNewTokenModal(false)}>Done</Button>
                </ModalFooter>
            </Modal>

            {/* Revoke Token Modal */}
            <Modal
                isOpen={showRevokeModal}
                onClose={() => setShowRevokeModal(false)}
                title="Revoke API Token"
                description={`Are you sure you want to revoke "${tokenToRevoke?.name}"? Any applications using this token will lose access.`}
            >
                <ModalFooter>
                    <Button variant="secondary" onClick={() => setShowRevokeModal(false)}>
                        Cancel
                    </Button>
                    <Button variant="danger" onClick={handleRevokeToken}>
                        Revoke Token
                    </Button>
                </ModalFooter>
            </Modal>

            {/* Token Activity Modal */}
            <Modal
                isOpen={showActivityModal}
                onClose={() => setShowActivityModal(false)}
                title={`Activity Log - ${selectedToken?.name}`}
                description="Recent activity for this token"
                size="lg"
            >
                <div className="space-y-2">
                    {activity.length === 0 ? (
                        <div className="rounded-lg border-2 border-dashed border-border p-8 text-center">
                            <Activity className="mx-auto h-8 w-8 text-foreground-muted" />
                            <p className="mt-2 text-sm text-foreground-muted">No activity recorded</p>
                        </div>
                    ) : (
                        activity.map(item => (
                            <div
                                key={item.id}
                                className="flex items-start gap-3 rounded-lg border border-border bg-background p-3"
                            >
                                <Activity className="mt-0.5 h-4 w-4 text-primary" />
                                <div className="flex-1">
                                    <p className="text-sm font-medium text-foreground">{item.action}</p>
                                    <div className="mt-1 flex items-center gap-3 text-xs text-foreground-muted">
                                        <span>IP: {item.ip}</span>
                                        <span>•</span>
                                        <span className="flex items-center gap-1">
                                            <Clock className="h-3 w-3" />
                                            {item.timestamp}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        ))
                    )}
                </div>

                <ModalFooter>
                    <Button onClick={() => setShowActivityModal(false)}>Close</Button>
                </ModalFooter>
            </Modal>
        </SettingsLayout>
    );
}
