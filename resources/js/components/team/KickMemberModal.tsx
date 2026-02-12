import { useState, useEffect, useCallback } from 'react';
import { router } from '@inertiajs/react';
import { Modal, ModalFooter } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Checkbox } from '@/components/ui/Checkbox';
import { Textarea } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import {
    Loader2,
    AlertTriangle,
    CheckCircle,
    Activity,
    Rocket,
    Plus,
    Clock,
    ArrowRight,
    UserX,
    Check,
    ExternalLink,
} from 'lucide-react';

interface TeamMemberOption {
    id: number;
    name: string;
    email: string;
}

interface TopResource {
    type: string;
    full_type: string;
    id: number;
    name: string;
    action_count: number;
}

interface RecentActivity {
    id: number;
    action: string;
    formatted_action: string;
    resource_type: string | null;
    resource_name: string | null;
    description: string | null;
    created_at: string;
}

interface Contributions {
    total_actions: number;
    deploy_count: number;
    created_count: number;
    by_action: Record<string, number>;
    by_resource_type: Array<{ type: string; full_type: string; count: number }>;
    top_resources: TopResource[];
    first_action: string | null;
    last_action: string | null;
    recent_activities: RecentActivity[];
}

interface TransferAssignment {
    resource_type: string;
    resource_id: number;
    resource_name: string;
    to_user_id: number;
}

interface KickMemberModalProps {
    isOpen: boolean;
    onClose: () => void;
    member: {
        id: number;
        name: string;
        email: string;
        role: string;
    };
    teamMembers?: TeamMemberOption[];
}

type Step = 1 | 2 | 3 | 4;

export function KickMemberModal({ isOpen, onClose, member, teamMembers: initialTeamMembers }: KickMemberModalProps) {
    const [step, setStep] = useState<Step>(1);
    const [isLoading, setIsLoading] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [contributions, setContributions] = useState<Contributions | null>(null);
    const [teamMembers, setTeamMembers] = useState<TeamMemberOption[]>(initialTeamMembers ?? []);
    const [error, setError] = useState<string | null>(null);

    // Step 2 state
    const [reason, setReason] = useState('');
    const [enableTransfers, setEnableTransfers] = useState(false);
    const [transferAssignments, setTransferAssignments] = useState<Record<string, number>>({});
    const [bulkAssignee, setBulkAssignee] = useState<string>('');

    // Step 4 state
    const [archiveId, setArchiveId] = useState<number | null>(null);

    const resetState = useCallback(() => {
        setStep(1);
        setContributions(null);
        setError(null);
        setReason('');
        setEnableTransfers(false);
        setTransferAssignments({});
        setBulkAssignee('');
        setArchiveId(null);
        setIsLoading(false);
        setIsSubmitting(false);
    }, []);

    useEffect(() => {
        if (!isOpen) {
            resetState();
        }
    }, [isOpen, resetState]);

    // Fetch contributions when modal opens
    useEffect(() => {
        if (isOpen && !contributions && !isLoading) {
            setIsLoading(true);
            setError(null);

            fetch(`/settings/team/members/${member.id}/contributions`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((res) => {
                    if (!res.ok) throw new Error('Failed to load contributions');
                    return res.json();
                })
                .then((data) => {
                    setContributions(data.contributions);
                    if (data.teamMembers && !initialTeamMembers?.length) {
                        setTeamMembers(data.teamMembers);
                    }
                })
                .catch((err) => setError(err.message))
                .finally(() => setIsLoading(false));
        }
    }, [isOpen, member.id, contributions, isLoading, initialTeamMembers]);

    const getResourceKey = (r: TopResource) => `${r.full_type}:${r.id}`;

    const handleBulkAssign = (userId: string) => {
        setBulkAssignee(userId);
        if (!userId || !contributions) return;
        const newAssignments: Record<string, number> = {};
        for (const r of contributions.top_resources) {
            newAssignments[getResourceKey(r)] = Number(userId);
        }
        setTransferAssignments(newAssignments);
    };

    const handleAssignResource = (resource: TopResource, userId: string) => {
        setTransferAssignments((prev) => {
            const next = { ...prev };
            if (userId) {
                next[getResourceKey(resource)] = Number(userId);
            } else {
                delete next[getResourceKey(resource)];
            }
            return next;
        });
        setBulkAssignee('');
    };

    const buildTransfers = (): TransferAssignment[] => {
        if (!enableTransfers || !contributions) return [];
        return contributions.top_resources
            .filter((r) => transferAssignments[getResourceKey(r)])
            .map((r) => ({
                resource_type: r.full_type,
                resource_id: r.id,
                resource_name: r.name,
                to_user_id: transferAssignments[getResourceKey(r)],
            }));
    };

    const handleSubmit = () => {
        setIsSubmitting(true);
        setError(null);

        const transfers = buildTransfers();

        fetch(`/settings/team/members/${member.id}/kick`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({
                reason: reason || null,
                transfers,
            }),
        })
            .then((res) => {
                if (!res.ok) throw new Error('Failed to remove member');
                return res.json();
            })
            .then((data) => {
                setArchiveId(data.archive_id);
                setStep(4);
            })
            .catch((err) => setError(err.message))
            .finally(() => setIsSubmitting(false));
    };

    const formatDate = (iso: string | null) => {
        if (!iso) return 'N/A';
        return new Date(iso).toLocaleDateString();
    };

    const transferCount = buildTransfers().length;

    return (
        <Modal isOpen={isOpen} onClose={step === 4 ? () => {} : onClose} title="Remove Team Member" size="lg">
            {/* Step Indicator */}
            <div className="mb-6 flex items-center justify-center gap-4">
                {([1, 2, 3, 4] as Step[]).map((s) => (
                    <StepIndicator
                        key={s}
                        step={s}
                        currentStep={step}
                        label={['Contributions', 'Options', 'Confirm', 'Done'][s - 1]}
                    />
                ))}
            </div>

            {error && (
                <div className="mb-4 flex items-center gap-2 rounded-lg border border-red-500/20 bg-red-500/10 p-3 text-sm text-red-500">
                    <AlertTriangle className="h-4 w-4 shrink-0" />
                    {error}
                </div>
            )}

            {/* Step 1: Contributions */}
            {step === 1 && (
                <div className="space-y-4">
                    {isLoading ? (
                        <div className="flex items-center justify-center py-12">
                            <Loader2 className="h-6 w-6 animate-spin text-foreground-muted" />
                            <span className="ml-2 text-sm text-foreground-muted">Loading contributions...</span>
                        </div>
                    ) : contributions ? (
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

                            {/* Top resources */}
                            {contributions.top_resources.length > 0 && (
                                <div>
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-foreground-muted">
                                        Top Resources
                                    </p>
                                    <div className="max-h-48 space-y-1.5 overflow-y-auto">
                                        {contributions.top_resources.map((r) => (
                                            <div
                                                key={getResourceKey(r)}
                                                className="flex items-center justify-between rounded-lg border border-border bg-background p-2.5"
                                            >
                                                <div className="flex items-center gap-2">
                                                    <Badge variant="default">{r.type}</Badge>
                                                    <span className="text-sm text-foreground">
                                                        {r.name}
                                                    </span>
                                                </div>
                                                <span className="text-xs text-foreground-muted">
                                                    {r.action_count} actions
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Recent activity mini timeline */}
                            {contributions.recent_activities.length > 0 && (
                                <div>
                                    <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-foreground-muted">
                                        Recent Activity
                                    </p>
                                    <div className="max-h-36 space-y-1 overflow-y-auto">
                                        {contributions.recent_activities.slice(0, 8).map((a) => (
                                            <div
                                                key={a.id}
                                                className="flex items-center gap-2 rounded px-2 py-1.5 text-sm"
                                            >
                                                <span className="font-medium text-foreground">
                                                    {a.formatted_action}
                                                </span>
                                                {a.resource_type && (
                                                    <Badge variant="default">{a.resource_type}</Badge>
                                                )}
                                                {a.resource_name && (
                                                    <span className="text-foreground-muted">{a.resource_name}</span>
                                                )}
                                                <span className="ml-auto text-xs text-foreground-subtle">
                                                    {formatDate(a.created_at)}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </>
                    ) : (
                        <div className="py-8 text-center text-sm text-foreground-muted">
                            No contribution data available
                        </div>
                    )}

                    <ModalFooter>
                        <Button variant="secondary" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button onClick={() => setStep(2)} disabled={isLoading}>
                            Next
                            <ArrowRight className="ml-1 h-4 w-4" />
                        </Button>
                    </ModalFooter>
                </div>
            )}

            {/* Step 2: Options */}
            {step === 2 && (
                <div className="space-y-4">
                    <div>
                        <label className="mb-1 block text-sm font-medium text-foreground">
                            Reason for removal (optional)
                        </label>
                        <Textarea
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            placeholder="e.g., Project completed, contract ended..."
                            rows={3}
                        />
                    </div>

                    {contributions && contributions.top_resources.length > 0 && teamMembers.length > 0 && (
                        <div className="space-y-3">
                            <div className="rounded-lg border border-border bg-background-secondary p-3">
                                <Checkbox
                                    label="Transfer resource attribution"
                                    hint="Assign this member's top resources to other team members"
                                    checked={enableTransfers}
                                    onCheckedChange={(checked) => setEnableTransfers(checked)}
                                />
                            </div>

                            {enableTransfers && (
                                <div className="space-y-3">
                                    {/* Bulk assign */}
                                    <Select
                                        label="Quick assign all to"
                                        value={bulkAssignee}
                                        onChange={(e) => handleBulkAssign(e.target.value)}
                                    >
                                        <option value="">Select member...</option>
                                        {teamMembers.map((m) => (
                                            <option key={m.id} value={String(m.id)}>
                                                {m.name} ({m.email})
                                            </option>
                                        ))}
                                    </Select>

                                    {/* Per-resource assignment */}
                                    <div className="max-h-48 space-y-2 overflow-y-auto">
                                        {contributions.top_resources.map((r) => (
                                            <div
                                                key={getResourceKey(r)}
                                                className="flex items-center gap-2 rounded-lg border border-border bg-background p-2"
                                            >
                                                <div className="flex min-w-0 flex-1 items-center gap-2">
                                                    <Badge variant="default">{r.type}</Badge>
                                                    <span className="truncate text-sm">{r.name}</span>
                                                </div>
                                                <select
                                                    className="rounded border border-border bg-background px-2 py-1 text-sm"
                                                    value={
                                                        transferAssignments[getResourceKey(r)]
                                                            ? String(transferAssignments[getResourceKey(r)])
                                                            : ''
                                                    }
                                                    onChange={(e) => handleAssignResource(r, e.target.value)}
                                                >
                                                    <option value="">Skip</option>
                                                    {teamMembers.map((m) => (
                                                        <option key={m.id} value={String(m.id)}>
                                                            {m.name}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    <ModalFooter>
                        <Button variant="secondary" onClick={() => setStep(1)}>
                            Back
                        </Button>
                        <Button onClick={() => setStep(3)}>
                            Next
                            <ArrowRight className="ml-1 h-4 w-4" />
                        </Button>
                    </ModalFooter>
                </div>
            )}

            {/* Step 3: Confirm */}
            {step === 3 && (
                <div className="space-y-4">
                    <div className="rounded-lg border border-border bg-background-secondary p-4">
                        <p className="text-sm font-medium text-foreground">Removing from team:</p>
                        <div className="mt-2 space-y-1 text-sm text-foreground-muted">
                            <p>
                                <span className="font-medium text-foreground">{member.name}</span> ({member.email})
                            </p>
                            <p>
                                Role: <Badge variant="default">{member.role}</Badge>
                            </p>
                        </div>
                    </div>

                    <div className="rounded-lg border border-border bg-background-secondary p-4">
                        <p className="text-sm font-medium text-foreground">What will happen:</p>
                        <ul className="mt-2 space-y-1 text-sm text-foreground-muted">
                            <li className="flex items-center gap-2">
                                <Check className="h-3.5 w-3.5 text-primary" />
                                An archive of all contributions will be saved
                            </li>
                            {reason && (
                                <li className="flex items-center gap-2">
                                    <Check className="h-3.5 w-3.5 text-primary" />
                                    Reason: &quot;{reason.length > 60 ? reason.slice(0, 60) + '...' : reason}&quot;
                                </li>
                            )}
                            {transferCount > 0 && (
                                <li className="flex items-center gap-2">
                                    <Check className="h-3.5 w-3.5 text-primary" />
                                    {transferCount} resource attribution{transferCount !== 1 ? 's' : ''} will be
                                    transferred
                                </li>
                            )}
                            <li className="flex items-center gap-2">
                                <Check className="h-3.5 w-3.5 text-primary" />
                                Member will lose access to all team resources
                            </li>
                        </ul>
                    </div>

                    <div className="flex items-start gap-2 rounded-lg border border-yellow-500/20 bg-yellow-500/10 p-3">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-yellow-500" />
                        <p className="text-sm text-yellow-700 dark:text-yellow-400">
                            This action cannot be undone. The member will need to be re-invited to rejoin the team.
                        </p>
                    </div>

                    <ModalFooter>
                        <Button variant="secondary" onClick={() => setStep(2)} disabled={isSubmitting}>
                            Back
                        </Button>
                        <Button variant="danger" onClick={handleSubmit} disabled={isSubmitting}>
                            {isSubmitting ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Removing...
                                </>
                            ) : (
                                <>
                                    <UserX className="mr-2 h-4 w-4" />
                                    Confirm & Remove
                                </>
                            )}
                        </Button>
                    </ModalFooter>
                </div>
            )}

            {/* Step 4: Success */}
            {step === 4 && (
                <div className="space-y-4">
                    <div className="flex flex-col items-center py-6">
                        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-green-500/10">
                            <CheckCircle className="h-8 w-8 text-green-500" />
                        </div>
                        <h3 className="mt-4 text-lg font-semibold text-foreground">Member Removed</h3>
                        <p className="mt-1 text-center text-sm text-foreground-muted">
                            {member.name} has been removed from the team. An archive of their contributions has been
                            saved.
                        </p>
                    </div>

                    {archiveId && (
                        <div className="flex justify-center">
                            <Button
                                variant="secondary"
                                onClick={() => router.visit(`/settings/team/archives/${archiveId}`)}
                            >
                                <ExternalLink className="mr-2 h-4 w-4" />
                                View Archive
                            </Button>
                        </div>
                    )}

                    <ModalFooter>
                        <Button
                            onClick={() => {
                                onClose();
                                router.visit('/settings/team/index');
                            }}
                        >
                            Done
                        </Button>
                    </ModalFooter>
                </div>
            )}
        </Modal>
    );
}

function StepIndicator({ step, currentStep, label }: { step: Step; currentStep: Step; label: string }) {
    const isActive = step === currentStep;
    const isCompleted = step < currentStep;

    return (
        <div className="flex flex-col items-center gap-1">
            <div
                className={`flex h-8 w-8 items-center justify-center rounded-full border-2 transition-colors ${
                    isActive
                        ? 'border-primary bg-primary text-white'
                        : isCompleted
                          ? 'border-primary bg-primary text-white'
                          : 'border-border bg-background text-foreground-muted'
                }`}
            >
                {isCompleted ? <Check className="h-4 w-4" /> : <span className="text-sm">{step}</span>}
            </div>
            <span
                className={`text-xs ${
                    isActive ? 'text-foreground' : isCompleted ? 'text-foreground-muted' : 'text-foreground-subtle'
                }`}
            >
                {label}
            </span>
        </div>
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
