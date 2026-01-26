import * as React from 'react';
import { SettingsLayout } from './Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Input, Button, Modal, ModalFooter, Badge, useToast } from '@/components/ui';
import { router } from '@inertiajs/react';
import { Key, Copy, Trash2, Plus } from 'lucide-react';

interface ApiToken {
    id: number;
    name: string;
    abilities?: string[];
    last_used_at?: string;
    created_at: string;
    expires_at?: string;
}

interface Props {
    tokens: ApiToken[];
}

export default function TokensSettings({ tokens: initialTokens }: Props) {
    const [tokens, setTokens] = React.useState<ApiToken[]>(initialTokens);
    const [showCreateModal, setShowCreateModal] = React.useState(false);
    const [showRevokeModal, setShowRevokeModal] = React.useState(false);
    const [showNewTokenModal, setShowNewTokenModal] = React.useState(false);
    const [tokenToRevoke, setTokenToRevoke] = React.useState<ApiToken | null>(null);
    const [newTokenName, setNewTokenName] = React.useState('');
    const [newlyCreatedToken, setNewlyCreatedToken] = React.useState('');
    const [isCreating, setIsCreating] = React.useState(false);
    const [isRevoking, setIsRevoking] = React.useState(false);
    const { toast } = useToast();

    const handleCreateToken = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsCreating(true);

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const response = await fetch('/settings/tokens', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ name: newTokenName }),
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || 'Failed to create token');
            }

            const data = await response.json();

            const newToken: ApiToken = {
                id: data.id,
                name: data.name,
                abilities: data.abilities,
                created_at: data.created_at,
                expires_at: data.expires_at,
            };

            setTokens([...tokens, newToken]);
            setNewlyCreatedToken(data.token);
            setNewTokenName('');
            setShowCreateModal(false);
            setShowNewTokenModal(true);
            toast({
                title: 'Token created',
                description: 'Your API token has been created successfully.',
            });
        } catch (error) {
            toast({
                title: 'Failed to create token',
                description: error instanceof Error ? error.message : 'An error occurred while creating the API token.',
                variant: 'error',
            });
        } finally {
            setIsCreating(false);
        }
    };

    const handleRevokeToken = () => {
        if (tokenToRevoke) {
            setIsRevoking(true);

            router.delete(`/settings/tokens/${tokenToRevoke.id}`, {
                onSuccess: () => {
                    setTokens(tokens.filter((t) => t.id !== tokenToRevoke.id));
                    toast({
                        title: 'Token revoked',
                        description: 'The API token has been revoked successfully.',
                    });
                    setTokenToRevoke(null);
                    setShowRevokeModal(false);
                },
                onError: () => {
                    toast({
                        title: 'Failed to revoke token',
                        description: 'An error occurred while revoking the API token.',
                        variant: 'error',
                    });
                },
                onFinish: () => {
                    setIsRevoking(false);
                }
            });
        }
    };

    const handleCopyToken = (token: string) => {
        navigator.clipboard.writeText(token);
        toast({
            title: 'Copied to clipboard',
            description: 'API token copied to clipboard.',
        });
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
                                {tokens.map((token) => (
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
                                                    {token.abilities && token.abilities.length > 0 && (
                                                        <div className="mt-1 flex flex-wrap gap-1">
                                                            {token.abilities.map((ability) => (
                                                                <Badge key={ability} variant="default" className="text-xs">
                                                                    {ability}
                                                                </Badge>
                                                            ))}
                                                        </div>
                                                    )}
                                                    <div className="mt-1 flex items-center gap-2 text-xs text-foreground-subtle">
                                                        <span>Created {new Date(token.created_at).toLocaleDateString()}</span>
                                                        {token.last_used_at ? (
                                                            <span>• Last used {new Date(token.last_used_at).toLocaleDateString()}</span>
                                                        ) : (
                                                            <Badge variant="warning" className="ml-1">
                                                                Never used
                                                            </Badge>
                                                        )}
                                                        {token.expires_at && (
                                                            <span>• Expires {new Date(token.expires_at).toLocaleDateString()}</span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
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
                                ))}
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
