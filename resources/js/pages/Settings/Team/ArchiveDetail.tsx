import { useState } from 'react';
import { SettingsLayout } from '../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Textarea } from '@/components/ui/Input';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';
import { useConfirmation } from '@/components/ui/ConfirmationModal';
import { useToast } from '@/components/ui/Toast';
import { Link, router } from '@inertiajs/react';
import ArchiveResourceManager from './ArchiveResourceManager';
import type { FullResource, EnvironmentOption } from './ArchiveResourceManager';
import {
    ArrowLeft,
    Mail,
    Calendar,
    Shield,
    Activity,
    Rocket,
    Plus,
    Clock,
    ArrowRightLeft,
    FolderOpen,
    UserX,
    Download,
    FileText,
    Trash2,
    MoreVertical,
    Pencil,
    StickyNote,
} from 'lucide-react';

interface ContributionSummary {
    total_actions: number;
    deploy_count: number;
    created_count: number;
    by_action: Record<string, number>;
    by_resource_type: Array<{ type: string; full_type: string; count: number }>;
    top_resources: Array<{
        type: string;
        full_type: string;
        id: number;
        name: string;
        action_count: number;
    }>;
    first_action: string | null;
    last_action: string | null;
}

interface AccessSnapshot {
    role: string;
    allowed_projects: string[] | null;
    permission_set_id: number | null;
}

interface Transfer {
    id: number;
    resource_type: string;
    resource_name: string;
    to_user: string;
    status: string;
    completed_at: string | null;
}

interface TeamMember {
    id: number;
    name: string;
    email: string;
}

interface ArchiveData {
    id: number;
    uuid: string;
    member_name: string;
    member_email: string;
    member_role: string;
    member_joined_at: string | null;
    kicked_by_name: string | null;
    kick_reason: string | null;
    contribution_summary: ContributionSummary | null;
    access_snapshot: AccessSnapshot | null;
    status: string;
    notes: string | null;
    created_at: string;
}

interface Props {
    archive: ArchiveData;
    transfers: Transfer[];
    teamMembers: TeamMember[];
    memberResources: FullResource[];
    environments: EnvironmentOption[];
}

const getInitials = (name: string) =>
    name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);

export default function ArchiveDetail({ archive, transfers, memberResources, environments }: Props) {
    const contributions = archive.contribution_summary;
    const access = archive.access_snapshot;
    const { addToast } = useToast();

    // Notes editing state
    const [isEditingNotes, setIsEditingNotes] = useState(false);
    const [notesValue, setNotesValue] = useState(archive.notes ?? '');
    const [isSavingNotes, setIsSavingNotes] = useState(false);

    const [_isDeleting, setIsDeleting] = useState(false);

    const { open: openDeleteConfirm, ConfirmationDialog: DeleteDialog } = useConfirmation({
        title: 'Delete Archive',
        description: `Are you sure you want to delete the archive for ${archive.member_name}? The archive can be restored later from the archives list.`,
        confirmText: 'Delete Archive',
        cancelText: 'Cancel',
        variant: 'danger',
        onConfirm: async () => {
            setIsDeleting(true);
            router.delete(`/settings/team/archives/${archive.id}`, {
                onSuccess: () => {
                    addToast('success', 'Archive deleted');
                },
                onError: () => {
                    addToast('error', 'Failed to delete archive');
                },
                onFinish: () => {
                    setIsDeleting(false);
                },
            });
        },
    });

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

    const handleExport = (format: 'json' | 'csv') => {
        window.location.href = `/settings/team/archives/${archive.id}/export?format=${format}`;
    };

    const handleSaveNotes = () => {
        setIsSavingNotes(true);
        router.patch(
            `/settings/team/archives/${archive.id}/notes`,
            { notes: notesValue },
            {
                onSuccess: () => {
                    setIsEditingNotes(false);
                    addToast('success', 'Notes saved');
                },
                onError: () => {
                    addToast('error', 'Failed to save notes');
                },
                onFinish: () => {
                    setIsSavingNotes(false);
                },
            },
        );
    };

    return (
        <SettingsLayout activeSection="team">
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Link href="/settings/team/archives">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                    </Link>
                    <div className="flex-1">
                        <h2 className="text-2xl font-semibold text-foreground">Member Archive</h2>
                        <p className="text-sm text-foreground-muted">Archived data for {archive.member_name}</p>
                    </div>
                    <Badge variant={archive.status === 'completed' ? 'success' : 'warning'}>{archive.status}</Badge>

                    {/* Actions Dropdown */}
                    <Dropdown>
                        <DropdownTrigger>
                            <Button variant="secondary" size="icon">
                                <MoreVertical className="h-4 w-4" />
                            </Button>
                        </DropdownTrigger>
                        <DropdownContent width="md">
                            <DropdownItem icon={<Download className="h-4 w-4" />} onClick={() => handleExport('json')}>
                                Download JSON
                            </DropdownItem>
                            <DropdownItem icon={<FileText className="h-4 w-4" />} onClick={() => handleExport('csv')}>
                                Download CSV
                            </DropdownItem>
                            <DropdownDivider />
                            <DropdownItem icon={<Trash2 className="h-4 w-4" />} danger onClick={openDeleteConfirm}>
                                Delete Archive
                            </DropdownItem>
                        </DropdownContent>
                    </Dropdown>
                </div>

                {/* Member Profile (Frozen) */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-4">
                            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-500 text-base font-semibold text-white">
                                {getInitials(archive.member_name)}
                            </div>
                            <div>
                                <CardTitle>Member Profile</CardTitle>
                                <CardDescription>Information captured at time of removal</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="flex items-center gap-3">
                                <UserX className="h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-sm font-medium text-foreground">{archive.member_name}</p>
                                    <p className="text-xs text-foreground-muted">Name</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Mail className="h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-sm font-medium text-foreground">{archive.member_email}</p>
                                    <p className="text-xs text-foreground-muted">Email</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Shield className="h-5 w-5 text-foreground-muted" />
                                <div>
                                    <Badge variant={getRoleBadgeVariant(archive.member_role)}>
                                        {archive.member_role}
                                    </Badge>
                                    <p className="text-xs text-foreground-muted">Role</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Calendar className="h-5 w-5 text-foreground-muted" />
                                <div>
                                    <p className="text-sm font-medium text-foreground">
                                        {formatDate(archive.member_joined_at)}
                                    </p>
                                    <p className="text-xs text-foreground-muted">Joined</p>
                                </div>
                            </div>
                        </div>

                        {/* Kick info */}
                        <div className="mt-4 rounded-lg border border-border bg-background-secondary p-3">
                            <div className="flex items-center gap-4 text-sm">
                                <span className="text-foreground-muted">
                                    Removed on {formatDate(archive.created_at)}
                                </span>
                                {archive.kicked_by_name && (
                                    <span className="text-foreground-muted">by {archive.kicked_by_name}</span>
                                )}
                            </div>
                            {archive.kick_reason && (
                                <p className="mt-2 text-sm italic text-foreground-muted">
                                    &quot;{archive.kick_reason}&quot;
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Contribution Summary */}
                <Card>
                    <CardHeader>
                        <CardTitle>Contributions</CardTitle>
                        <CardDescription>Summary of all team activity</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!contributions ? (
                            <p className="text-sm italic text-foreground-muted">No activity recorded</p>
                        ) : (
                            <>
                            {/* Stat cards */}
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <StatCard
                                    icon={<Activity className="h-4 w-4" />}
                                    label="Total Actions"
                                    value={contributions.total_actions}
                                />
                                <StatCard
                                    icon={<Rocket className="h-4 w-4" />}
                                    label="Deployments"
                                    value={contributions.deploy_count}
                                />
                                <StatCard
                                    icon={<Plus className="h-4 w-4" />}
                                    label="Created"
                                    value={contributions.created_count}
                                />
                                <StatCard
                                    icon={<Clock className="h-4 w-4" />}
                                    label="Active Period"
                                    value={
                                        contributions.first_action
                                            ? `${formatDate(contributions.first_action)} - ${formatDate(contributions.last_action)}`
                                            : 'No activity'
                                    }
                                    small
                                />
                            </div>

                            {/* Action breakdown */}
                            {Object.keys(contributions.by_action).length > 0 && (
                                <div className="mt-4">
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-foreground-muted">
                                        Actions Breakdown
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        {Object.entries(contributions.by_action).map(([action, count]) => (
                                            <Badge key={action} variant="default">
                                                {action}: {count}
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Top resources */}
                            {contributions.top_resources.length > 0 && (
                                <div className="mt-4">
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-foreground-muted">
                                        Top Resources
                                    </p>
                                    <div className="space-y-2">
                                        {contributions.top_resources.map((r) => (
                                            <div
                                                key={`${r.full_type}:${r.id}`}
                                                className="flex items-center justify-between rounded-lg border border-border bg-background p-3"
                                            >
                                                <div className="flex items-center gap-2">
                                                    <Badge variant="default">{r.type}</Badge>
                                                    <span className="text-sm text-foreground">{r.name}</span>
                                                </div>
                                                <span className="text-sm text-foreground-muted">
                                                    {r.action_count} actions
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Resource type breakdown */}
                            {contributions.by_resource_type.length > 0 && (
                                <div className="mt-4">
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-foreground-muted">
                                        By Resource Type
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        {contributions.by_resource_type.map((rt) => (
                                            <Badge key={rt.full_type} variant="default">
                                                {rt.type}: {rt.count}
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                            )}
                            </>
                        )}
                    </CardContent>
                </Card>

                {/* Notes */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Notes</CardTitle>
                                <CardDescription>Internal notes about this archived member</CardDescription>
                            </div>
                            {!isEditingNotes && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        setNotesValue(archive.notes ?? '');
                                        setIsEditingNotes(true);
                                    }}
                                >
                                    <Pencil className="mr-1.5 h-3.5 w-3.5" />
                                    {archive.notes ? 'Edit' : 'Add Note'}
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {isEditingNotes ? (
                            <div className="space-y-3">
                                <Textarea
                                    value={notesValue}
                                    onChange={(e) => setNotesValue(e.target.value)}
                                    placeholder="Add notes about this member..."
                                    rows={4}
                                />
                                <div className="flex gap-2">
                                    <Button onClick={handleSaveNotes} loading={isSavingNotes} size="sm">
                                        Save
                                    </Button>
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={() => setIsEditingNotes(false)}
                                        disabled={isSavingNotes}
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </div>
                        ) : archive.notes ? (
                            <div className="flex items-start gap-2">
                                <StickyNote className="mt-0.5 h-4 w-4 shrink-0 text-foreground-muted" />
                                <p className="whitespace-pre-wrap text-sm text-foreground">{archive.notes}</p>
                            </div>
                        ) : (
                            <p className="text-sm italic text-foreground-muted">No notes yet</p>
                        )}
                    </CardContent>
                </Card>

                {/* Existing Transfers */}
                <Card>
                    <CardHeader>
                        <CardTitle>Resource Transfers</CardTitle>
                        <CardDescription>Resources attributed to other team members</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {transfers.length === 0 ? (
                            <p className="text-sm italic text-foreground-muted">No resource transfers</p>
                        ) : (
                            <div className="space-y-2">
                                {transfers.map((t) => (
                                    <div
                                        key={t.id}
                                        className="flex items-center justify-between rounded-lg border border-border bg-background p-3"
                                    >
                                        <div className="flex items-center gap-3">
                                            <ArrowRightLeft className="h-4 w-4 text-foreground-muted" />
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <Badge variant="default">{t.resource_type}</Badge>
                                                    <span className="text-sm font-medium text-foreground">
                                                        {t.resource_name}
                                                    </span>
                                                </div>
                                                <p className="text-xs text-foreground-muted">
                                                    Transferred to {t.to_user}
                                                </p>
                                            </div>
                                        </div>
                                        <Badge variant={t.status === 'completed' ? 'success' : 'default'}>
                                            {t.status}
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Resource Manager */}
                <ArchiveResourceManager
                    archiveId={archive.id}
                    resources={memberResources}
                    environments={environments}
                />

                {/* Access Snapshot */}
                {access && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Access Snapshot</CardTitle>
                            <CardDescription>Permissions at time of removal</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                <div className="flex items-center gap-2">
                                    <Shield className="h-4 w-4 text-foreground-muted" />
                                    <span className="text-sm text-foreground-muted">Role:</span>
                                    <Badge variant={getRoleBadgeVariant(access.role)}>{access.role}</Badge>
                                </div>
                                {access.permission_set_id && (
                                    <div className="flex items-center gap-2">
                                        <FolderOpen className="h-4 w-4 text-foreground-muted" />
                                        <span className="text-sm text-foreground-muted">
                                            Permission Set ID: {access.permission_set_id}
                                        </span>
                                    </div>
                                )}
                                <div className="flex items-center gap-2">
                                    <FolderOpen className="h-4 w-4 text-foreground-muted" />
                                    <span className="text-sm text-foreground-muted">
                                        Project Access:{' '}
                                        {access.allowed_projects === null
                                            ? 'Full access'
                                            : Array.isArray(access.allowed_projects) &&
                                                access.allowed_projects.includes('*')
                                              ? 'Full access'
                                              : `${access.allowed_projects?.length ?? 0} projects`}
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

            <DeleteDialog />
        </SettingsLayout>
    );
}

function StatCard({
    icon,
    label,
    value,
    small,
}: {
    icon: React.ReactNode;
    label: string;
    value: number | string;
    small?: boolean;
}) {
    return (
        <div className="rounded-lg border border-border bg-background-secondary p-3">
            <div className="flex items-center gap-1.5 text-foreground-muted">{icon}</div>
            <p className={`mt-1 font-semibold text-foreground ${small ? 'text-xs' : 'text-lg'}`}>{value}</p>
            <p className="text-xs text-foreground-muted">{label}</p>
        </div>
    );
}
