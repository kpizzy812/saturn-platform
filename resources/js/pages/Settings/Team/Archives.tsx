import { SettingsLayout } from '../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Link } from '@inertiajs/react';
import { ArrowLeft, Archive, Eye, Rocket, Activity } from 'lucide-react';

interface MemberArchiveItem {
    id: number;
    uuid: string;
    member_name: string;
    member_email: string;
    member_role: string;
    member_joined_at: string | null;
    kicked_by_name: string | null;
    kick_reason: string | null;
    total_actions: number;
    deploy_count: number;
    status: string;
    created_at: string;
}

interface Props {
    archives: MemberArchiveItem[];
}

export default function Archives({ archives }: Props) {
    const formatDate = (iso: string | null) => {
        if (!iso) return 'N/A';
        return new Date(iso).toLocaleDateString();
    };

    const getRoleBadgeVariant = (role: string): 'default' | 'success' | 'warning' | 'info' => {
        switch (role) {
            case 'owner':
                return 'warning';
            case 'admin':
                return 'success';
            case 'viewer':
                return 'info';
            default:
                return 'default';
        }
    };

    return (
        <SettingsLayout activeSection="team">
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Link href="/settings/team/index">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                    </Link>
                    <div className="flex-1">
                        <h2 className="text-2xl font-semibold text-foreground">Member Archives</h2>
                        <p className="text-sm text-foreground-muted">
                            History of removed team members and their contributions
                        </p>
                    </div>
                </div>

                {archives.length === 0 ? (
                    <Card>
                        <CardContent className="py-12">
                            <div className="flex flex-col items-center text-center">
                                <Archive className="h-12 w-12 text-foreground-subtle" />
                                <h3 className="mt-4 text-lg font-medium text-foreground">No archives yet</h3>
                                <p className="mt-1 text-sm text-foreground-muted">
                                    When team members are removed, their contribution archives will appear here.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle>Archived Members</CardTitle>
                            <CardDescription>
                                {archives.length} member{archives.length !== 1 ? 's' : ''} archived
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {archives.map((archive) => (
                                    <div
                                        key={archive.id}
                                        className="flex items-center justify-between rounded-lg border border-border bg-background p-4 transition-all hover:border-border/80"
                                    >
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                <p className="font-medium text-foreground">{archive.member_name}</p>
                                                <Badge variant={getRoleBadgeVariant(archive.member_role)}>
                                                    {archive.member_role}
                                                </Badge>
                                                <Badge
                                                    variant={archive.status === 'completed' ? 'success' : 'warning'}
                                                >
                                                    {archive.status}
                                                </Badge>
                                            </div>
                                            <div className="mt-1 flex flex-wrap items-center gap-3 text-sm text-foreground-muted">
                                                <span>{archive.member_email}</span>
                                                <span className="text-foreground-subtle">|</span>
                                                <span>Removed {formatDate(archive.created_at)}</span>
                                                {archive.kicked_by_name && (
                                                    <>
                                                        <span className="text-foreground-subtle">|</span>
                                                        <span>By {archive.kicked_by_name}</span>
                                                    </>
                                                )}
                                            </div>
                                            {archive.kick_reason && (
                                                <p className="mt-1 text-sm text-foreground-muted italic">
                                                    &quot;{archive.kick_reason.length > 100
                                                        ? archive.kick_reason.slice(0, 100) + '...'
                                                        : archive.kick_reason}&quot;
                                                </p>
                                            )}
                                            <div className="mt-2 flex items-center gap-4 text-xs text-foreground-subtle">
                                                <span className="flex items-center gap-1">
                                                    <Activity className="h-3 w-3" />
                                                    {archive.total_actions} actions
                                                </span>
                                                <span className="flex items-center gap-1">
                                                    <Rocket className="h-3 w-3" />
                                                    {archive.deploy_count} deploys
                                                </span>
                                                {archive.member_joined_at && (
                                                    <span>Joined {formatDate(archive.member_joined_at)}</span>
                                                )}
                                            </div>
                                        </div>
                                        <Link href={`/settings/team/archives/${archive.id}`}>
                                            <Button variant="secondary" size="sm">
                                                <Eye className="mr-2 h-4 w-4" />
                                                Details
                                            </Button>
                                        </Link>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </SettingsLayout>
    );
}
