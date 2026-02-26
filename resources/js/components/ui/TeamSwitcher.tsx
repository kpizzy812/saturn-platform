import * as React from 'react';
import { router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Building2, User } from 'lucide-react';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';

interface TeamSwitcherProps {
    className?: string;
}

export function TeamSwitcher({ className }: TeamSwitcherProps) {
    const { props } = usePage();
    const currentTeam = props.team;
    const teams = props.teams || [];
    const [isLoading, setIsLoading] = React.useState(false);

    // Don't render if user has only one team
    if (teams.length <= 1) {
        return null;
    }

    const handleTeamSwitch = (teamId: number) => {
        if (teamId === currentTeam?.id) return;

        setIsLoading(true);
        router.post(
            `/teams/switch/${teamId}`,
            {},
            {
                onFinish: () => setIsLoading(false),
            }
        );
    };

    const getRoleLabel = (role: string) => {
        const labels: Record<string, string> = {
            owner: 'Owner',
            admin: 'Admin',
            developer: 'Developer',
            member: 'Member',
            viewer: 'Viewer',
        };
        return labels[role] || role;
    };

    const getRoleBadgeClass = (role: string) => {
        const classes: Record<string, string> = {
            owner: 'bg-amber-500/20 text-amber-400',
            admin: 'bg-purple-500/20 text-purple-400',
            developer: 'bg-blue-500/20 text-blue-400',
            member: 'bg-green-500/20 text-green-400',
            viewer: 'bg-gray-500/20 text-gray-400',
        };
        return classes[role] || 'bg-gray-500/20 text-gray-400';
    };

    return (
        <Dropdown>
            <DropdownTrigger>
                <button
                    className={`flex items-center gap-2 rounded-lg border border-border bg-background-secondary px-3 py-2 text-sm transition-all duration-200 hover:border-border/80 hover:bg-background-tertiary ${className}`}
                    disabled={isLoading}
                >
                    {currentTeam?.logo ? (
                        <img src={currentTeam.logo} alt={currentTeam.name} className="h-4 w-4 rounded object-cover" />
                    ) : currentTeam?.personal_team ? (
                        <User className="h-4 w-4 text-foreground-muted" />
                    ) : (
                        <Building2 className="h-4 w-4 text-foreground-muted" />
                    )}
                    <span className="max-w-[120px] truncate text-foreground">
                        {currentTeam?.name || 'Select Team'}
                    </span>
                    <ChevronsUpDown className="h-4 w-4 text-foreground-muted" />
                </button>
            </DropdownTrigger>
            <DropdownContent align="left" className="w-64">
                <div className="px-3 py-2">
                    <p className="text-xs font-medium uppercase tracking-wider text-foreground-muted">
                        Switch Workspace
                    </p>
                </div>
                <DropdownDivider />
                <div className="max-h-64 overflow-y-auto">
                    {teams.map((team) => (
                        <DropdownItem
                            key={team.id}
                            onClick={() => handleTeamSwitch(team.id)}
                            className="flex items-center justify-between"
                        >
                            <div className="flex items-center gap-2">
                                {team.logo ? (
                                    <img src={team.logo} alt={team.name} className="h-4 w-4 rounded object-cover" />
                                ) : team.personal_team ? (
                                    <User className="h-4 w-4 text-foreground-muted" />
                                ) : (
                                    <Building2 className="h-4 w-4 text-foreground-muted" />
                                )}
                                <div className="flex flex-col">
                                    <span className="max-w-[140px] truncate text-sm">
                                        {team.name}
                                    </span>
                                    <span
                                        className={`mt-0.5 w-fit rounded px-1.5 py-0.5 text-[10px] font-medium ${getRoleBadgeClass(team.role)}`}
                                    >
                                        {getRoleLabel(team.role)}
                                    </span>
                                </div>
                            </div>
                            {currentTeam?.id === team.id && (
                                <Check className="h-4 w-4 text-primary" />
                            )}
                        </DropdownItem>
                    ))}
                </div>
            </DropdownContent>
        </Dropdown>
    );
}
