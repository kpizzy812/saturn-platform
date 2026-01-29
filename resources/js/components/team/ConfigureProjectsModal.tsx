import * as React from 'react';
import { Modal, ModalFooter } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Badge } from '@/components/ui/Badge';
import { router } from '@inertiajs/react';
import { Folder, Lock, Unlock, AlertTriangle } from 'lucide-react';

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
    const [accessType, setAccessType] = React.useState<'all' | 'restricted'>('all');
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
                    if (data.allowed_projects === null) {
                        setAccessType('all');
                        setSelectedProjects(data.projects.map((p: Project) => p.id));
                    } else {
                        setAccessType('restricted');
                        setSelectedProjects(data.allowed_projects);
                    }
                })
                .catch(err => {
                    setError(err.message);
                })
                .finally(() => setIsLoading(false));
        }
    }, [isOpen, member]);

    const toggleProject = (projectId: number) => {
        setSelectedProjects(prev =>
            prev.includes(projectId)
                ? prev.filter(id => id !== projectId)
                : [...prev, projectId]
        );
    };

    const selectAll = () => {
        setSelectedProjects(projects.map(p => p.id));
    };

    const selectNone = () => {
        setSelectedProjects([]);
    };

    const handleSave = () => {
        if (!member) return;

        setIsSaving(true);
        setError(null);

        fetch(`/settings/team/members/${member.id}/projects`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
            },
            credentials: 'include',
            body: JSON.stringify({
                access_type: accessType,
                allowed_projects: accessType === 'restricted' ? selectedProjects : [],
            }),
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
                    {/* Access Type Selection */}
                    <div className="space-y-3">
                        <label className="text-sm font-medium text-foreground">
                            Access Level
                        </label>
                        <div className="space-y-2">
                            <button
                                type="button"
                                onClick={() => setAccessType('all')}
                                className={`flex w-full items-center gap-3 rounded-lg border p-3 text-left transition-all ${
                                    accessType === 'all'
                                        ? 'border-primary bg-primary/10'
                                        : 'border-border hover:border-border/80'
                                }`}
                            >
                                <Unlock className="h-5 w-5 text-success" />
                                <div className="flex-1">
                                    <p className="font-medium text-foreground">All Projects</p>
                                    <p className="text-xs text-foreground-muted">
                                        Access to all current and future projects
                                    </p>
                                </div>
                            </button>
                            <button
                                type="button"
                                onClick={() => setAccessType('restricted')}
                                className={`flex w-full items-center gap-3 rounded-lg border p-3 text-left transition-all ${
                                    accessType === 'restricted'
                                        ? 'border-primary bg-primary/10'
                                        : 'border-border hover:border-border/80'
                                }`}
                            >
                                <Lock className="h-5 w-5 text-warning" />
                                <div className="flex-1">
                                    <p className="font-medium text-foreground">Restricted Access</p>
                                    <p className="text-xs text-foreground-muted">
                                        Only access to selected projects below
                                    </p>
                                </div>
                            </button>
                        </div>
                    </div>

                    {/* Project Selection (only when restricted) */}
                    {accessType === 'restricted' && (
                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <label className="text-sm font-medium text-foreground">
                                    Select Projects
                                </label>
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
                            </div>
                            <Badge variant={selectedProjects.length > 0 ? 'success' : 'warning'}>
                                {selectedProjects.length} of {projects.length} selected
                            </Badge>
                            <div className="max-h-64 space-y-1 overflow-y-auto rounded-lg border border-border p-3">
                                {projects.length === 0 ? (
                                    <p className="py-4 text-center text-sm text-foreground-muted">
                                        No projects in this team
                                    </p>
                                ) : (
                                    projects.map((project) => (
                                        <label
                                            key={project.id}
                                            className="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-background-tertiary"
                                        >
                                            <Checkbox
                                                checked={selectedProjects.includes(project.id)}
                                                onChange={() => toggleProject(project.id)}
                                            />
                                            <Folder className="h-4 w-4 text-foreground-muted" />
                                            <span className="text-sm text-foreground">{project.name}</span>
                                        </label>
                                    ))
                                )}
                            </div>
                            {selectedProjects.length === 0 && (
                                <div className="flex items-center gap-2 text-xs text-warning">
                                    <AlertTriangle className="h-4 w-4" />
                                    <span>User will have no project access</span>
                                </div>
                            )}
                        </div>
                    )}
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
