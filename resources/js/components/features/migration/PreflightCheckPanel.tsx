import { CheckCircle, XCircle, AlertTriangle, ArrowRight, Key, HardDrive, Link2 } from 'lucide-react';
import { Badge } from '@/components/ui/Badge';
import { Card, CardContent } from '@/components/ui/Card';

interface AttributeChange {
    from: string | number | boolean | null;
    to: string | number | boolean | null;
}

interface EnvVarDiff {
    added: string[];
    removed: string[];
    changed: string[];
}

interface VolumeDiff {
    added: string[];
    removed: string[];
}

interface RewirePreview {
    key: string;
    current_value_masked: string;
    will_rewire: boolean;
}

interface PreflightData {
    mode?: string;
    summary?: {
        action?: string;
        resource_name?: string;
        resource_type?: string;
    };
    attribute_diff?: Record<string, AttributeChange>;
    env_var_diff?: EnvVarDiff;
    volume_diff?: VolumeDiff;
    rewire_preview?: RewirePreview[];
}

interface Props {
    data: PreflightData | null;
    loading?: boolean;
}

export function PreflightCheckPanel({ data, loading = false }: Props) {
    if (loading) {
        return (
            <div className="rounded-lg border border-border/50 p-4 text-center text-sm text-foreground-muted">
                Running pre-flight checks...
            </div>
        );
    }

    if (!data) return null;

    const attrChanges = data.attribute_diff ? Object.keys(data.attribute_diff) : [];
    const envAdded = data.env_var_diff?.added?.length ?? 0;
    const envRemoved = data.env_var_diff?.removed?.length ?? 0;
    const envChanged = data.env_var_diff?.changed?.length ?? 0;
    const volAdded = data.volume_diff?.added?.length ?? 0;
    const volRemoved = data.volume_diff?.removed?.length ?? 0;
    const rewireCount = data.rewire_preview?.filter((r) => r.will_rewire).length ?? 0;

    const hasChanges = attrChanges.length > 0 || envAdded > 0 || envRemoved > 0 || envChanged > 0 || volAdded > 0 || volRemoved > 0;

    return (
        <div className="space-y-3">
            <p className="text-sm font-medium text-foreground">Pre-flight Check Results</p>

            {/* Compatibility checks */}
            <div className="space-y-1.5">
                <CheckItem
                    pass={true}
                    label={`Mode: ${data.mode ?? 'promote'}`}
                />
                <CheckItem
                    pass={true}
                    label={`Target: ${data.summary?.resource_name ?? 'Unknown'} (${data.summary?.resource_type ?? 'Unknown'})`}
                />
                <CheckItem
                    pass={true}
                    label={`Action: ${data.summary?.action === 'update_existing' ? 'Update existing resource' : 'Create new resource'}`}
                />
            </div>

            {/* Config diff table */}
            {attrChanges.length > 0 && (
                <Card className="border-warning/30">
                    <CardContent className="p-3">
                        <p className="mb-2 flex items-center gap-2 text-xs font-medium text-warning">
                            <AlertTriangle className="h-3 w-3" />
                            {attrChanges.length} config attribute(s) will change
                        </p>
                        <div className="space-y-1.5">
                            {attrChanges.map((key) => {
                                const change = data.attribute_diff![key];
                                return (
                                    <div key={key} className="flex items-center gap-2 text-xs">
                                        <code className="rounded bg-foreground/5 px-1.5 py-0.5 font-mono">{key}</code>
                                        <span className="text-foreground-subtle line-through">
                                            {String(change.from ?? '(empty)')}
                                        </span>
                                        <ArrowRight className="h-3 w-3 text-foreground-subtle" />
                                        <span className="font-medium text-foreground">
                                            {String(change.to ?? '(empty)')}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Env var diff summary */}
            {(envAdded > 0 || envRemoved > 0 || envChanged > 0) && (
                <Card className="border-info/30">
                    <CardContent className="p-3">
                        <p className="mb-2 flex items-center gap-2 text-xs font-medium text-info">
                            <Key className="h-3 w-3" />
                            Environment variable differences (keys only)
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {envAdded > 0 && <Badge variant="success" size="sm">+{envAdded} new</Badge>}
                            {envRemoved > 0 && <Badge variant="danger" size="sm">-{envRemoved} removed</Badge>}
                            {envChanged > 0 && <Badge variant="warning" size="sm">~{envChanged} changed</Badge>}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Volume diff */}
            {(volAdded > 0 || volRemoved > 0) && (
                <Card className="border-info/30">
                    <CardContent className="p-3">
                        <p className="mb-2 flex items-center gap-2 text-xs font-medium text-info">
                            <HardDrive className="h-3 w-3" />
                            Volume changes
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {volAdded > 0 && <Badge variant="success" size="sm">+{volAdded} added</Badge>}
                            {volRemoved > 0 && <Badge variant="danger" size="sm">-{volRemoved} removed</Badge>}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Rewire preview */}
            {data.rewire_preview && rewireCount > 0 && (
                <Card className="border-primary/30">
                    <CardContent className="p-3">
                        <p className="mb-2 flex items-center gap-2 text-xs font-medium text-primary">
                            <Link2 className="h-3 w-3" />
                            {rewireCount} connection(s) will be rewired
                        </p>
                        <div className="space-y-1">
                            {data.rewire_preview.filter((r) => r.will_rewire).map((r) => (
                                <div key={r.key} className="flex items-center gap-2 text-xs">
                                    <code className="rounded bg-foreground/5 px-1.5 py-0.5 font-mono">{r.key}</code>
                                    <span className="text-foreground-subtle">{r.current_value_masked}</span>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* All clear */}
            {!hasChanges && !rewireCount && (
                <div className="flex items-center gap-2 rounded-lg border border-success/30 bg-success/5 p-3 text-sm text-success">
                    <CheckCircle className="h-4 w-4" />
                    No differences detected — resources are in sync.
                </div>
            )}
        </div>
    );
}

function CheckItem({ pass, label }: { pass: boolean; label: string }) {
    return (
        <div className="flex items-center gap-2 text-xs">
            {pass ? (
                <CheckCircle className="h-3.5 w-3.5 text-success" />
            ) : (
                <XCircle className="h-3.5 w-3.5 text-danger" />
            )}
            <span className={pass ? 'text-foreground' : 'text-danger'}>{label}</span>
        </div>
    );
}
