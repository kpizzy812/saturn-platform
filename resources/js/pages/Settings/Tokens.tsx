import * as React from 'react';
import { SettingsLayout } from './Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Input, Button, Modal, ModalFooter, Badge, useToast } from '@/components/ui';
import { router } from '@inertiajs/react';
import { Key, Copy, Trash2, Plus, Eye, EyeOff } from 'lucide-react';

interface ApiToken {
    id: number;
    name: string;
    token: string;
    lastUsed?: string;
    createdAt: string;
}

const mockTokens: ApiToken[] = [
    {
        id: 1,
        name: 'Production Deploy',
        token: 'sat_prod_1a2b3c4d5e6f7g8h9i0j',
        lastUsed: '2024-03-28',
        createdAt: '2024-01-15',
    },
    {
        id: 2,
        name: 'CI/CD Pipeline',
        token: 'sat_cicd_9i8h7g6f5e4d3c2b1a0z',
        lastUsed: '2024-03-27',
        createdAt: '2024-02-10',
    },
    {
        id: 3,
        name: 'Development Testing',
        token: 'sat_dev_x1y2z3a4b5c6d7e8f9g0',
        createdAt: '2024-03-01',
    },
];

export default function TokensSettings() {
    const [tokens, setTokens] = React.useState<ApiToken[]>(mockTokens);
    const [showCreateModal, setShowCreateModal] = React.useState(false);
    const [showRevokeModal, setShowRevokeModal] = React.useState(false);
    const [showNewTokenModal, setShowNewTokenModal] = React.useState(false);
    const [tokenToRevoke, setTokenToRevoke] = React.useState<ApiToken | null>(null);
    const [newTokenName, setNewTokenName] = React.useState('');
    const [newlyCreatedToken, setNewlyCreatedToken] = React.useState('');
    const [isCreating, setIsCreating] = React.useState(false);
    const [isRevoking, setIsRevoking] = React.useState(false);
    const [visibleTokens, setVisibleTokens] = React.useState<Set<number>>(new Set());
    const { addToast } = useToast();

    const handleCreateToken = (e: React.FormEvent) => {
        e.preventDefault();
        setIsCreating(true);

        router.post('/settings/tokens', { name: newTokenName }, {
            onSuccess: (response) => {
                // The backend should return the newly created token in the response
                const generatedToken = (response as any)?.token || `sat_${newTokenName.toLowerCase().replace(/\s+/g, '_')}_${Math.random().toString(36).substring(2, 15)}`;
                const newToken: ApiToken = {
                    id: tokens.length + 1,
                    name: newTokenName,
                    token: generatedToken,
                    createdAt: new Date().toISOString().split('T')[0],
                };

                setTokens([...tokens, newToken]);
                setNewlyCreatedToken(generatedToken);
                setNewTokenName('');
                setShowCreateModal(false);
                setShowNewTokenModal(true);
                addToast({
                    title: 'Token created',
                    description: 'Your API token has been created successfully.',
                });
            },
            onError: (errors) => {
                addToast({
                    title: 'Failed to create token',
                    description: 'An error occurred while creating the API token.',
                    variant: 'danger',
                });
                console.error(errors);
            },
            onFinish: () => {
                setIsCreating(false);
            }
        });
    };

    const handleRevokeToken = () => {
        if (tokenToRevoke) {
            setIsRevoking(true);

            router.delete(`/settings/tokens/${tokenToRevoke.id}`, {
                onSuccess: () => {
                    setTokens(tokens.filter((t) => t.id !== tokenToRevoke.id));
                    addToast({
                        title: 'Token revoked',
                        description: 'The API token has been revoked successfully.',
                    });
                    setTokenToRevoke(null);
                    setShowRevokeModal(false);
                },
                onError: (errors) => {
                    addToast({
                        title: 'Failed to revoke token',
                        description: 'An error occurred while revoking the API token.',
                        variant: 'danger',
                    });
                    console.error(errors);
                },
                onFinish: () => {
                    setIsRevoking(false);
                }
            });
        }
    };

    const handleCopyToken = (token: string) => {
        navigator.clipboard.writeText(token);
        addToast({
            title: 'Copied to clipboard',
            description: 'API token copied to clipboard.',
        });
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

    const maskToken = (token: string) => {
        const parts = token.split('_');
        if (parts.length >= 3) {
            return `${parts[0]}_${parts[1]}_${'•'.repeat(parts[2].length)}`;
        }
        return '•'.repeat(token.length);
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
                                                        <p className="font-medium text-foreground">{token.name}</p>
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
                                                        {token.lastUsed ? (
                                                            <p className="mt-1 text-xs text-foreground-subtle">
                                                                Last used {new Date(token.lastUsed).toLocaleDateString()}
                                                            </p>
                                                        ) : (
                                                            <Badge variant="warning" className="mt-1">
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
                description="Give your token a descriptive name"
            >
                <form onSubmit={handleCreateToken}>
                    <Input
                        label="Token Name"
                        value={newTokenName}
                        onChange={(e) => setNewTokenName(e.target.value)}
                        placeholder="e.g., Production Deploy"
                        required
                    />

                    <ModalFooter>
                        <Button type="button" variant="secondary" onClick={() => setShowCreateModal(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" loading={isCreating}>
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
                    <Button variant="secondary" onClick={() => setShowRevokeModal(false)} disabled={isRevoking}>
                        Cancel
                    </Button>
                    <Button variant="danger" onClick={handleRevokeToken} loading={isRevoking}>
                        Revoke Token
                    </Button>
                </ModalFooter>
            </Modal>
        </SettingsLayout>
    );
}
