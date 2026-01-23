import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Badge, useConfirm } from '@/components/ui';
import { ArrowLeft, Plus, Key, CheckCircle, XCircle, Trash2, Eye, EyeOff } from 'lucide-react';
import type { Server as ServerType } from '@/types';

interface Props {
    server: ServerType;
    privateKeys?: PrivateKey[];
}

interface PrivateKey {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    fingerprint: string;
    is_default: boolean;
    created_at: string;
}

export default function ServerPrivateKeysIndex({ server, privateKeys = [] }: Props) {
    const confirm = useConfirm();

    const handleDelete = async (key: PrivateKey) => {
        const confirmed = await confirm({
            title: 'Delete SSH Key',
            description: `Are you sure you want to delete SSH key "${key.name}"?`,
            confirmText: 'Delete',
            variant: 'danger',
        });
        if (confirmed) {
            router.delete(`/servers/${server.uuid}/private-keys/${key.uuid}`);
        }
    };

    const handleSetDefault = (key: PrivateKey) => {
        router.patch(`/servers/${server.uuid}/private-keys/${key.uuid}`, {
            is_default: true,
        });
    };

    return (
        <AppLayout
            title={`${server.name} - SSH Keys`}
            breadcrumbs={[
                { label: 'Servers', href: '/servers' },
                { label: server.name, href: `/servers/${server.uuid}` },
                { label: 'SSH Keys' },
            ]}
        >
            {/* Header */}
            <div className="mb-6">
                <Link
                    href={`/servers/${server.uuid}`}
                    className="mb-4 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                >
                    <ArrowLeft className="mr-2 h-4 w-4" />
                    Back to Server
                </Link>
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-primary/10">
                            <Key className="h-7 w-7 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-foreground">SSH Private Keys</h1>
                            <p className="text-foreground-muted">Manage SSH keys for {server.name}</p>
                        </div>
                    </div>
                    <Button onClick={() => router.visit(`/servers/${server.uuid}/private-keys/create`)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add SSH Key
                    </Button>
                </div>
            </div>

            {/* Info Card */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex items-start gap-3">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-warning/10">
                            <Key className="h-5 w-5 text-warning" />
                        </div>
                        <div>
                            <h4 className="font-medium text-foreground">Security Notice</h4>
                            <p className="mt-1 text-sm text-foreground-muted">
                                SSH private keys are stored encrypted and are used to authenticate with this server.
                                Never share your private keys or commit them to version control.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Private Keys List */}
            {privateKeys.length > 0 ? (
                <div className="space-y-3">
                    {privateKeys.map((key) => (
                        <Card key={key.uuid}>
                            <CardContent className="p-5">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-4">
                                        <div className={`flex h-12 w-12 items-center justify-center rounded-lg ${
                                            key.is_default ? 'bg-primary/10' : 'bg-background-tertiary'
                                        }`}>
                                            <Key className={`h-6 w-6 ${
                                                key.is_default ? 'text-primary' : 'text-foreground-subtle'
                                            }`} />
                                        </div>
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                <h3 className="font-semibold text-foreground">{key.name}</h3>
                                                {key.is_default && (
                                                    <Badge variant="primary" size="sm">Default</Badge>
                                                )}
                                            </div>
                                            {key.description && (
                                                <p className="mt-0.5 text-sm text-foreground-muted">{key.description}</p>
                                            )}
                                            <p className="mt-1 font-mono text-xs text-foreground-subtle">
                                                {key.fingerprint}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {!key.is_default && (
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                onClick={() => handleSetDefault(key)}
                                            >
                                                Set Default
                                            </Button>
                                        )}
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => handleDelete(key)}
                                        >
                                            <Trash2 className="h-4 w-4 text-danger" />
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            ) : (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16">
                        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-secondary">
                            <Key className="h-8 w-8 text-foreground-subtle" />
                        </div>
                        <h3 className="mt-4 font-medium text-foreground">No SSH keys configured</h3>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Add an SSH private key to authenticate with this server
                        </p>
                        <Button
                            className="mt-4"
                            onClick={() => router.visit(`/servers/${server.uuid}/private-keys/create`)}
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Add SSH Key
                        </Button>
                    </CardContent>
                </Card>
            )}

            {/* How to Generate SSH Keys */}
            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>How to Generate SSH Keys</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                    <div>
                        <p className="text-sm text-foreground-muted">
                            To generate a new SSH key pair, run the following command in your terminal:
                        </p>
                        <pre className="mt-2 rounded-lg bg-background-tertiary p-3 font-mono text-xs text-foreground">
                            ssh-keygen -t ed25519 -C "your_email@example.com"
                        </pre>
                    </div>
                    <div>
                        <p className="text-sm text-foreground-muted">
                            For legacy systems that don't support Ed25519, use RSA:
                        </p>
                        <pre className="mt-2 rounded-lg bg-background-tertiary p-3 font-mono text-xs text-foreground">
                            ssh-keygen -t rsa -b 4096 -C "your_email@example.com"
                        </pre>
                    </div>
                    <div className="rounded-lg border border-warning/50 bg-warning/10 p-3">
                        <p className="text-sm text-warning">
                            <strong>Important:</strong> Keep your private key secure. Only add the public key (.pub file)
                            to servers and services. Never share your private key.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
