import * as React from 'react';
import { AdminLayout } from '@/layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import {
    KeyRound,
    Shield,
    Server,
    Box,
    GitBranch,
    Copy,
    CheckCircle,
    AlertTriangle,
    RefreshCw,
    Calendar,
    Users,
    ExternalLink,
    Fingerprint,
} from 'lucide-react';

interface SshKeyDetails {
    id: number;
    uuid: string;
    name: string;
    description?: string;
    fingerprint?: string;
    md5_fingerprint?: string;
    public_key?: string;
    is_git_related: boolean;
    team_id: number;
    team_name: string;
    servers_count: number;
    applications_count: number;
    github_apps_count: number;
    gitlab_apps_count: number;
    is_in_use: boolean;
    created_at: string;
    updated_at: string;
}

interface ServerUsage {
    id: number;
    uuid: string;
    name: string;
    ip: string;
    team_name: string;
}

interface AppUsage {
    id: number;
    uuid: string;
    name: string;
    status: string;
}

interface GitAppUsage {
    id: number;
    name: string;
    type: 'github' | 'gitlab';
}

interface Props {
    sshKey: SshKeyDetails;
    usage: {
        servers: ServerUsage[];
        applications: AppUsage[];
        github_apps: GitAppUsage[];
        gitlab_apps: GitAppUsage[];
    };
}

export default function AdminSshKeyShow({ sshKey, usage }: Props) {
    const [isAuditing, setIsAuditing] = React.useState(false);
    const [copied, setCopied] = React.useState(false);

    const handleAudit = () => {
        setIsAuditing(true);
        router.post(`/admin/ssh-keys/${sshKey.id}/audit`, {}, {
            preserveScroll: true,
            onFinish: () => setIsAuditing(false),
        });
    };

    const handleCopyPublicKey = async () => {
        if (sshKey.public_key) {
            await navigator.clipboard.writeText(sshKey.public_key);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        }
    };

    const totalUsage = sshKey.servers_count + sshKey.applications_count + sshKey.github_apps_count + sshKey.gitlab_apps_count;
    const allGitApps = [...(usage.github_apps ?? []), ...(usage.gitlab_apps ?? [])];

    return (
        <AdminLayout
            title={`SSH Key: ${sshKey.name}`}
            breadcrumbs={[
                { label: 'Admin', href: '/admin' },
                { label: 'SSH Keys', href: '/admin/ssh-keys' },
                { label: sshKey.name },
            ]}
        >
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8">
                    <div className="flex items-start justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex h-16 w-16 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-purple-600 text-white">
                                <KeyRound className="h-8 w-8" />
                            </div>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h1 className="text-2xl font-semibold text-foreground">{sshKey.name}</h1>
                                    <Badge variant={sshKey.is_git_related ? 'primary' : 'secondary'}>
                                        {sshKey.is_git_related ? 'Git Deploy Key' : 'SSH Key'}
                                    </Badge>
                                    {!sshKey.is_in_use && (
                                        <Badge variant="warning">
                                            <AlertTriangle className="mr-1 h-3 w-3" />
                                            Unused
                                        </Badge>
                                    )}
                                </div>
                                {sshKey.description && (
                                    <p className="mt-1 text-sm text-foreground-muted">{sshKey.description}</p>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Stats */}
                <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Status</p>
                                    <div className="flex items-center gap-2">
                                        {sshKey.is_in_use ? (
                                            <>
                                                <CheckCircle className="h-5 w-5 text-success" />
                                                <span className="text-lg font-bold text-success">In Use</span>
                                            </>
                                        ) : (
                                            <>
                                                <AlertTriangle className="h-5 w-5 text-warning" />
                                                <span className="text-lg font-bold text-warning">Unused</span>
                                            </>
                                        )}
                                    </div>
                                </div>
                                <Shield className="h-8 w-8 text-primary/50" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Total Usage</p>
                                    <p className="text-2xl font-bold text-primary">{totalUsage}</p>
                                </div>
                                <KeyRound className="h-8 w-8 text-primary/50" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Team</p>
                                    <Link
                                        href={`/admin/teams/${sshKey.team_id}`}
                                        className="text-lg font-bold text-foreground hover:text-primary"
                                    >
                                        {sshKey.team_name}
                                    </Link>
                                </div>
                                <Users className="h-8 w-8 text-warning/50" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Created</p>
                                    <p className="text-lg font-bold text-foreground">
                                        {new Date(sshKey.created_at).toLocaleDateString()}
                                    </p>
                                </div>
                                <Calendar className="h-8 w-8 text-foreground-muted/50" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card variant="glass">
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-foreground-subtle">Updated</p>
                                    <p className="text-lg font-bold text-foreground">
                                        {new Date(sshKey.updated_at).toLocaleDateString()}
                                    </p>
                                </div>
                                <Calendar className="h-8 w-8 text-foreground-muted/50" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Security Info */}
                <Card variant="glass" className="mb-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Fingerprint className="h-5 w-5" />
                            Security Information
                        </CardTitle>
                        <CardDescription>Key fingerprints and public key</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-xs font-medium text-foreground-subtle">SHA256 Fingerprint</p>
                                    <p className="mt-1 break-all font-mono text-sm text-foreground">
                                        {sshKey.fingerprint ? `SHA256:${sshKey.fingerprint}` : 'Not available'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs font-medium text-foreground-subtle">MD5 Fingerprint</p>
                                    <p className="mt-1 break-all font-mono text-sm text-foreground">
                                        {sshKey.md5_fingerprint ? `MD5:${sshKey.md5_fingerprint}` : 'Not available'}
                                    </p>
                                </div>
                            </div>

                            {sshKey.public_key && sshKey.public_key !== 'Error loading private key' && (
                                <div>
                                    <div className="flex items-center justify-between">
                                        <p className="text-xs font-medium text-foreground-subtle">Public Key</p>
                                        <Button variant="ghost" size="sm" onClick={handleCopyPublicKey}>
                                            {copied ? (
                                                <>
                                                    <CheckCircle className="mr-1 h-3 w-3 text-success" />
                                                    Copied
                                                </>
                                            ) : (
                                                <>
                                                    <Copy className="mr-1 h-3 w-3" />
                                                    Copy
                                                </>
                                            )}
                                        </Button>
                                    </div>
                                    <pre className="mt-1 max-h-24 overflow-auto rounded-lg border border-border/50 bg-background/50 p-3 font-mono text-xs text-foreground">
                                        {sshKey.public_key}
                                    </pre>
                                </div>
                            )}

                            <div className="flex justify-end">
                                <Button
                                    variant="secondary"
                                    onClick={handleAudit}
                                    disabled={isAuditing}
                                >
                                    <RefreshCw className={`mr-1 h-4 w-4 ${isAuditing ? 'animate-spin' : ''}`} />
                                    {isAuditing ? 'Running Audit...' : 'Run Security Audit'}
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Servers Usage */}
                <Card variant="glass" className="mb-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Server className="h-5 w-5" />
                            Servers ({usage.servers?.length ?? 0})
                        </CardTitle>
                        <CardDescription>Servers using this SSH key for connection</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {(usage.servers?.length ?? 0) === 0 ? (
                            <p className="py-4 text-center text-sm text-foreground-muted">No servers use this key</p>
                        ) : (
                            usage.servers.map((server) => (
                                <div key={server.id} className="flex items-center justify-between border-b border-border/50 py-3 last:border-0">
                                    <div className="flex items-center gap-3">
                                        <Server className="h-5 w-5 text-foreground-muted" />
                                        <div>
                                            <Link
                                                href={`/admin/servers/${server.uuid}`}
                                                className="font-medium text-foreground hover:text-primary"
                                            >
                                                {server.name}
                                            </Link>
                                            <p className="text-xs text-foreground-subtle">{server.ip}</p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs text-foreground-subtle">{server.team_name}</span>
                                        <Link href={`/admin/servers/${server.uuid}`}>
                                            <Button variant="ghost" size="sm">
                                                <ExternalLink className="h-4 w-4" />
                                            </Button>
                                        </Link>
                                    </div>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                {/* Applications Usage */}
                <Card variant="glass" className="mb-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Box className="h-5 w-5" />
                            Applications ({usage.applications?.length ?? 0})
                        </CardTitle>
                        <CardDescription>Applications using this key for deployment</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {(usage.applications?.length ?? 0) === 0 ? (
                            <p className="py-4 text-center text-sm text-foreground-muted">No applications use this key</p>
                        ) : (
                            usage.applications.map((app) => (
                                <div key={app.id} className="flex items-center justify-between border-b border-border/50 py-3 last:border-0">
                                    <div className="flex items-center gap-3">
                                        <Box className="h-5 w-5 text-foreground-muted" />
                                        <div>
                                            <span className="font-medium text-foreground">{app.name}</span>
                                        </div>
                                    </div>
                                    <Badge
                                        variant={app.status?.toLowerCase().includes('running') ? 'success' : 'default'}
                                        size="sm"
                                    >
                                        {app.status || 'Unknown'}
                                    </Badge>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                {/* Git Apps Usage */}
                <Card variant="glass">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <GitBranch className="h-5 w-5" />
                            Git Apps ({allGitApps.length})
                        </CardTitle>
                        <CardDescription>GitHub and GitLab apps using this key</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {allGitApps.length === 0 ? (
                            <p className="py-4 text-center text-sm text-foreground-muted">No git apps use this key</p>
                        ) : (
                            allGitApps.map((gitApp) => (
                                <div key={`${gitApp.type}-${gitApp.id}`} className="flex items-center justify-between border-b border-border/50 py-3 last:border-0">
                                    <div className="flex items-center gap-3">
                                        <GitBranch className="h-5 w-5 text-foreground-muted" />
                                        <span className="font-medium text-foreground">{gitApp.name}</span>
                                    </div>
                                    <Badge variant={gitApp.type === 'github' ? 'default' : 'secondary'} size="sm">
                                        {gitApp.type === 'github' ? 'GitHub' : 'GitLab'}
                                    </Badge>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
