import { useMemo } from 'react';
import { GitBranch, ArrowRight } from 'lucide-react';

interface Resource {
    uuid: string;
    name: string;
    type: 'application' | 'database' | 'service';
    status?: string;
}

interface DependencyEditorProps {
    currentUuid: string;
    currentName: string;
    dependsOn: string[];
    availableResources: Resource[];
    onChange: (uuids: string[]) => void;
    disabled?: boolean;
}

const TYPE_ICONS: Record<string, string> = {
    application: 'A',
    database: 'D',
    service: 'S',
};

const TYPE_COLORS: Record<string, string> = {
    application: 'bg-blue-500/10 text-blue-500 border-blue-500/20',
    database: 'bg-green-500/10 text-green-500 border-green-500/20',
    service: 'bg-purple-500/10 text-purple-500 border-purple-500/20',
};

export function DependencyEditor({
    currentUuid,
    currentName,
    dependsOn,
    availableResources,
    onChange,
    disabled,
}: DependencyEditorProps) {
    const selectableResources = useMemo(
        () => availableResources.filter(r => r.uuid !== currentUuid),
        [availableResources, currentUuid],
    );

    const selectedResources = useMemo(
        () => selectableResources.filter(r => dependsOn.includes(r.uuid)),
        [selectableResources, dependsOn],
    );

    const unselectedResources = useMemo(
        () => selectableResources.filter(r => !dependsOn.includes(r.uuid)),
        [selectableResources, dependsOn],
    );

    const toggleDependency = (uuid: string) => {
        if (disabled) return;
        if (dependsOn.includes(uuid)) {
            onChange(dependsOn.filter(u => u !== uuid));
        } else {
            onChange([...dependsOn, uuid]);
        }
    };

    if (selectableResources.length === 0) {
        return (
            <div className="text-sm text-muted-foreground">
                No other resources in this environment to depend on.
            </div>
        );
    }

    return (
        <div className="space-y-3">
            <div className="flex items-center gap-2">
                <GitBranch className="h-4 w-4 text-muted-foreground" />
                <h4 className="text-sm font-medium">Dependencies</h4>
            </div>

            <p className="text-xs text-muted-foreground">
                Select resources that must be running before <strong>{currentName}</strong> starts.
                Dependencies start first and stop last.
            </p>

            {/* Selected dependencies */}
            {selectedResources.length > 0 && (
                <div className="space-y-1">
                    <span className="text-xs font-medium text-muted-foreground uppercase">Depends on:</span>
                    <div className="flex flex-wrap gap-1.5">
                        {selectedResources.map(r => (
                            <button
                                key={r.uuid}
                                type="button"
                                onClick={() => toggleDependency(r.uuid)}
                                disabled={disabled}
                                className={`flex items-center gap-1.5 px-2 py-1 text-xs border rounded-md transition-colors ${TYPE_COLORS[r.type]} hover:opacity-80 disabled:opacity-50`}
                            >
                                <span className="font-bold text-[10px]">{TYPE_ICONS[r.type]}</span>
                                <span className="font-medium">{r.name}</span>
                                {!disabled && <span className="ml-1 opacity-60">&times;</span>}
                            </button>
                        ))}
                    </div>
                </div>
            )}

            {/* Startup order visualization */}
            {selectedResources.length > 0 && (
                <div className="flex items-center gap-2 text-xs text-muted-foreground bg-muted/50 rounded px-3 py-2">
                    <span className="font-medium">Startup order:</span>
                    {selectedResources.map((r, i) => (
                        <span key={r.uuid} className="flex items-center gap-1">
                            {i > 0 && <ArrowRight className="h-3 w-3" />}
                            <span className="font-mono">{r.name}</span>
                        </span>
                    ))}
                    <ArrowRight className="h-3 w-3" />
                    <span className="font-mono font-bold">{currentName}</span>
                </div>
            )}

            {/* Available resources to add */}
            {!disabled && unselectedResources.length > 0 && (
                <div className="space-y-1">
                    <span className="text-xs font-medium text-muted-foreground uppercase">Available:</span>
                    <div className="flex flex-wrap gap-1.5">
                        {unselectedResources.map(r => (
                            <button
                                key={r.uuid}
                                type="button"
                                onClick={() => toggleDependency(r.uuid)}
                                className="flex items-center gap-1.5 px-2 py-1 text-xs border border-dashed rounded-md transition-colors hover:bg-accent"
                            >
                                <span className="font-bold text-[10px] text-muted-foreground">{TYPE_ICONS[r.type]}</span>
                                <span>{r.name}</span>
                                <Plus className="h-3 w-3 text-muted-foreground" />
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

function Plus({ className }: { className?: string }) {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}>
            <path d="M5 12h14" />
            <path d="M12 5v14" />
        </svg>
    );
}
