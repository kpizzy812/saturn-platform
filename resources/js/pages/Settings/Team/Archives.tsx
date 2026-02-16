import { useState } from 'react';
import { SettingsLayout } from '../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Checkbox } from '@/components/ui/Checkbox';
import { Link, router } from '@inertiajs/react';
import { ArrowLeft, Archive, Eye, Rocket, Activity, Download, FileText, RotateCcw, Trash2 } from 'lucide-react';

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
    deleted_at: string | null;
}

interface Props {
    archives: MemberArchiveItem[];
    showDeleted: boolean;
}

const getInitials = (name: string) =>
    name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);

export default function Archives({ archives, showDeleted }: Props) {
    const [selectedIds, setSelectedIds] = useState<number[]>([]);

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

    const toggleSelect = (id: number) => {
        setSelectedIds((prev) => (prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]));
    };

    const toggleSelectAll = () => {
        const activeArchives = archives.filter((a) => !a.deleted_at);
        if (selectedIds.length === activeArchives.length) {
            setSelectedIds([]);
        } else {
            setSelectedIds(activeArchives.map((a) => a.id));
        }
    };

    const handleBulkExport = (format: 'json' | 'csv') => {
        const params = new URLSearchParams();
        params.set('format', format);
        selectedIds.forEach((id) => params.append('ids[]', String(id)));
        window.location.href = `/settings/team/archives/export-all?${params.toString()}`;
    };

    const handleExportAll = (format: 'json' | 'csv') => {
        window.location.href = `/settings/team/archives/export-all?format=${format}`;
    };

    const handleToggleDeleted = () => {
        router.get('/settings/team/archives', { show_deleted: !showDeleted ? '1' : '' }, { preserveState: true });
    };

    const handleRestore = (id: number) => {
        router.post(`/settings/team/archives/${id}/restore`);
    };

    const activeArchives = archives.filter((a) => !a.deleted_at);

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
                    <div className="flex items-center gap-2">
                        <Button variant="secondary" size="sm" onClick={handleToggleDeleted}>
                            {showDeleted ? (
                                <>
                                    <Archive className="mr-1.5 h-3.5 w-3.5" />
                                    Hide Deleted
                                </>
                            ) : (
                                <>
                                    <Trash2 className="mr-1.5 h-3.5 w-3.5" />
                                    Show Deleted
                                </>
                            )}
                        </Button>
                        {archives.length > 0 && (
                            <>
                                <Button variant="secondary" size="sm" onClick={() => handleExportAll('json')}>
                                    <Download className="mr-1.5 h-3.5 w-3.5" />
                                    Export All
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                {/* Bulk action bar */}
                {selectedIds.length > 0 && (
                    <div className="flex items-center gap-3 rounded-lg border border-primary/20 bg-primary/5 px-4 py-2">
                        <span className="text-sm font-medium text-foreground">
                            {selectedIds.length} selected
                        </span>
                        <Button variant="secondary" size="sm" onClick={() => handleBulkExport('json')}>
                            <Download className="mr-1.5 h-3.5 w-3.5" />
                            Export JSON
                        </Button>
                        <Button variant="secondary" size="sm" onClick={() => handleBulkExport('csv')}>
                            <FileText className="mr-1.5 h-3.5 w-3.5" />
                            Export CSV
                        </Button>
                        <Button variant="ghost" size="sm" onClick={() => setSelectedIds([])}>
                            Clear
                        </Button>
                    </div>
                )}

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
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Archived Members</CardTitle>
                                    <CardDescription>
                                        {archives.length} member{archives.length !== 1 ? 's' : ''} archived
                                        {showDeleted && archives.some((a) => a.deleted_at) && (
                                            <span className="ml-1 text-foreground-subtle">
                                                ({archives.filter((a) => a.deleted_at).length} deleted)
                                            </span>
                                        )}
                                    </CardDescription>
                                </div>
                                {activeArchives.length > 1 && (
                                    <Button variant="ghost" size="sm" onClick={toggleSelectAll}>
                                        {selectedIds.length === activeArchives.length ? 'Deselect All' : 'Select All'}
                                    </Button>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {archives.map((archive) => {
                                    const isDeleted = !!archive.deleted_at;
                                    return (
                                        <div
                                            key={archive.id}
                                            className={`flex items-center gap-3 rounded-lg border border-border bg-background p-4 transition-all hover:border-border/80 ${isDeleted ? 'opacity-50' : ''}`}
                                        >
                                            {/* Checkbox (only for active archives) */}
                                            {!isDeleted && (
                                                <Checkbox
                                                    checked={selectedIds.includes(archive.id)}
                                                    onCheckedChange={() => toggleSelect(archive.id)}
                                                />
                                            )}

                                            {/* Avatar */}
                                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-500 text-sm font-semibold text-white">
                                                {getInitials(archive.member_name)}
                                            </div>

                                            <div className="flex-1">
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium text-foreground">
                                                        {archive.member_name}
                                                    </p>
                                                    <Badge variant={getRoleBadgeVariant(archive.member_role)}>
                                                        {archive.member_role}
                                                    </Badge>
                                                    {isDeleted ? (
                                                        <Badge variant="default">deleted</Badge>
                                                    ) : (
                                                        <Badge
                                                            variant={
                                                                archive.status === 'completed' ? 'success' : 'warning'
                                                            }
                                                        >
                                                            {archive.status}
                                                        </Badge>
                                                    )}
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
                                                    <p className="mt-1 text-sm italic text-foreground-muted">
                                                        &quot;
                                                        {archive.kick_reason.length > 100
                                                            ? archive.kick_reason.slice(0, 100) + '...'
                                                            : archive.kick_reason}
                                                        &quot;
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
                                            <div className="flex items-center gap-2">
                                                {isDeleted ? (
                                                    <Button
                                                        variant="secondary"
                                                        size="sm"
                                                        onClick={() => handleRestore(archive.id)}
                                                    >
                                                        <RotateCcw className="mr-1.5 h-3.5 w-3.5" />
                                                        Restore
                                                    </Button>
                                                ) : (
                                                    <Link href={`/settings/team/archives/${archive.id}`}>
                                                        <Button variant="secondary" size="sm">
                                                            <Eye className="mr-2 h-4 w-4" />
                                                            Details
                                                        </Button>
                                                    </Link>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </SettingsLayout>
    );
}
