import * as React from 'react';
import { Modal, ModalFooter } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge } from '@/components/ui/Badge';
import { router } from '@inertiajs/react';
import { Folder, Lock, Unlock, Globe, AlertTriangle } from 'lucide-react';

interface Project {
    id: number;
    name: string;
}

interface Member {
    id: number;
    name: string;
    email: string;
    role: string;
}

interface Props {
    isOpen: boolean;
    onClose: () => void;
    member: Member | null;
    onSuccess?: () => void;
}

export function ConfigureProjectsModal({ isOpen, onClose, member, onSuccess }: Props) {
    const [projects, setProjects] = React.useState<Project[]>([]);
    const [grantAll, setGrantAll] = React.useState(false);
    const [selectedProjects, setSelectedProjects] = React.useState<number[]>([]);
    const [isLoading, setIsLoading] = React.useState(false);
    const [isSaving, setIsSaving] = React.useState(false);
    const [error, setError] = React.useState<string | null>(null);

    // Load data when modal opens
    React.useEffect(() => {
        if (isOpen && member) {
            setIsLoading(true);
            setError(null);
            fetch(`/settings/team/members/${member.id}/projects`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
            })
                .then(res => {
                    if (!res.ok) {
                        throw new Error('Failed to load project access settings');
                    }
                    return res.json();
                })
                .then(data => {
                    setProjects(data.projects);

                    // Determine current state based on allow-by-default model
                    // null = all projects (full access), [] = no access, [1,2,3] = specific
                    const allowedProjects = data.allowed_projects;

                    if (allowedProjects === null || (Array.isArray(allowedProjects) && allowedProjects.includes('*'))) {
                        // Full access to all projects (null or ['*'])
                        setGrantAll(true);
                        setSelectedProjects(data.projects.map((p: Project) => p.id));
                    } else if (Array.isArray(allowedProjects) && allowedProjects.length === 0) {
                        // No access (empty array)
                        setGrantAll(false);
                        setSelectedProjects([]);
                    } else {
                        // Specific projects
                        setGrantAll(false);
                        setSelectedProjects(allowedProjects);
                    }
                })
                .catch(err => {
                    setError(err.message);
                })
                .finally(() => setIsLoading(false));
        }
    }, [isOpen, member]);

    const toggleProject = (projectId: number) => {
        if (grantAll) return; // Can't toggle if grant all is enabled

        setSelectedProjects(prev =>
            prev.includes(projectId)
                ? prev.filter(id => id !== projectId)
                : [...prev, projectId]
        );
    };

    const handleGrantAllChange = (checked: boolean) => {
        setGrantAll(checked);
        if (checked) {
            // When granting all access, visually select all projects
            setSelectedProjects(projects.map(p => p.id));
        }
    };

    const selectAll = () => {
        if (!grantAll) {
            setSelectedProjects(projects.map(p => p.id));
        }
    };

    const selectNone = () => {
        if (!grantAll) {
            setSelectedProjects([]);
            setGrantAll(false);
        }
    };

    const handleSave = () => {
        if (!member) return;

        setIsSaving(true);
        setError(null);

        const payload = grantAll
            ? { grant_all: true, allowed_projects: ['*'] }
            : { grant_all: false, allowed_projects: selectedProjects };

        fetch(`/settings/team/members/${member.id}/projects`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
            },
            credentials: 'include',
            body: JSON.stringify(payload),
        })
            .then(res => {
                if (!res.ok) {
                    return res.json().then(data => {
                        throw new Error(data.message || 'Failed to update project access');
                    });
                }
                return res.json();
            })
            .then(() => {
                onSuccess?.();
                onClose();
                // Reload the page to reflect changes
                router.reload();
            })
            .catch(err => {
                setError(err.message);
            })
            .finally(() => {
                setIsSaving(false);
            });
    };

    const handleClose = () => {
        setError(null);
        onClose();
    };

    if (!member) return null;

    // Calculate access status
    const getAccessStatus = () => {
        if (grantAll) {
            return { label: 'Full Access', variant: 'success' as const, icon: <Unlock className="h-4 w-4" /> };
        } else if (selectedProjects.length === 0) {
            return { label: 'No Access', variant: 'danger' as const, icon: <Lock className="h-4 w-4" /> };
        } else if (selectedProjects.length === projects.length && projects.length > 0) {
            return { label: `All Projects (${projects.length})`, variant: 'success' as const, icon: <Globe className="h-4 w-4" /> };
        } else {
            return { label: `${selectedProjects.length} of ${projects.length} projects`, variant: 'warning' as const, icon: <Folder className="h-4 w-4" /> };
        }
    };

    const accessStatus = getAccessStatus();

    return (
        <Modal
            isOpen={isOpen}
            onClose={handleClose}
            title="Configure Project Access"
            description={`Manage which projects ${member.name} can access`}
            size="lg"
        >
            {isLoading ? (
                <div className="flex items-center justify-center py-8">
                    <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                </div>
            ) : error ? (
                <div className="rounded-lg border border-danger/50 bg-danger/10 p-4 text-center text-danger">
                    {error}
                </div>
            ) : (
                <div className="space-y-6">
                    {/* Current Access Status */}
                    <div className="flex items-center justify-between rounded-lg border border-border bg-background-secondary p-3">
                        <span className="text-sm font-medium text-foreground">Current Access:</span>
                        <Badge variant={accessStatus.variant} className="flex items-center gap-1.5">
                            {accessStatus.icon}
                            {accessStatus.label}
                        </Badge>
                    </div>

                    {/* Grant All Access Toggle */}
                    <div className="space-y-3">
                        <label className="flex cursor-pointer items-center gap-3 rounded-lg border border-border p-3 transition-all hover:border-border/80 hover:bg-background-secondary">
                            <Checkbox
                                checked={grantAll}
                                onCheckedChange={(checked) => handleGrantAllChange(checked)}
                            />
                            <div className="flex-1">
                                <div className="flex items-center gap-2">
                                    <Unlock className="h-4 w-4 text-success" />
                                    <p className="font-medium text-foreground">Grant Access to All Projects</p>
                                </div>
                                <p className="text-xs text-foreground-muted">
                                    Access to all current and future projects in this team
                                </p>
                            </div>
                        </label>
                    </div>

                    {/* Project Selection */}
                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <label className="text-sm font-medium text-foreground">
                                Specific Projects
                            </label>
                            {!grantAll && (
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={selectAll}
                                        className="text-xs text-primary hover:underline"
                                    >
                                        Select all
                                    </button>
                                    <span className="text-foreground-muted">|</span>
                                    <button
                                        type="button"
                                        onClick={selectNone}
                                        className="text-xs text-primary hover:underline"
                                    >
                                        Clear
                                    </button>
                                </div>
                            )}
                        </div>

                        {grantAll && (
                            <div className="flex items-center gap-2 rounded-lg border border-info/50 bg-info/10 p-2 text-xs text-info">
                                <Globe className="h-4 w-4 flex-shrink-0" />
                                <span>Full access granted - individual selections disabled</span>
                            </div>
                        )}

                        <div className="max-h-64 space-y-1 overflow-y-auto rounded-lg border border-border p-3">
                            {projects.length === 0 ? (
                                <p className="py-4 text-center text-sm text-foreground-muted">
                                    No projects in this team
                                </p>
                            ) : (
                                projects.map((project) => (
                                    <label
                                        key={project.id}
                                        className={`flex cursor-pointer items-center gap-3 rounded-lg p-2 transition-colors ${
                                            grantAll
                                                ? 'cursor-not-allowed opacity-60'
                                                : 'hover:bg-background-tertiary'
                                        }`}
                                    >
                                        <Checkbox
                                            checked={selectedProjects.includes(project.id)}
                                            onCheckedChange={() => toggleProject(project.id)}
                                            disabled={grantAll}
                                        />
                                        <Folder className="h-4 w-4 text-foreground-muted" />
                                        <span className="text-sm text-foreground">{project.name}</span>
                                    </label>
                                ))
                            )}
                        </div>

                        {!grantAll && selectedProjects.length === 0 && (
                            <div className="flex items-center gap-2 text-xs text-warning">
                                <AlertTriangle className="h-4 w-4" />
                                <span>No access - user will not see any projects</span>
                            </div>
                        )}
                    </div>
                </div>
            )}

            <ModalFooter>
                <Button variant="secondary" onClick={handleClose} disabled={isSaving}>
                    Cancel
                </Button>
                <Button onClick={handleSave} loading={isSaving} disabled={isLoading || !!error}>
                    Save Changes
                </Button>
            </ModalFooter>
        </Modal>
    );
}
