import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, Button } from '@/components/ui';
import {
    Download, RotateCw, ChevronDown, ChevronRight,
    CheckCircle, XCircle, AlertCircle, Terminal
} from 'lucide-react';
import type { Service } from '@/types';
import { getStatusIcon } from '@/lib/statusUtils';

interface BuildStep {
    id: number;
    name: string;
    status: 'pending' | 'running' | 'success' | 'failed';
    duration: string;
    logs: string[];
    startTime?: string;
    endTime?: string;
}

interface Props {
    service: Service;
    buildSteps?: BuildStep[];
}

export function BuildLogsTab({ service, buildSteps: initialBuildSteps = [] }: Props) {
    const [expandedSteps, setExpandedSteps] = useState<Set<number>>(new Set());
    const [buildSteps] = useState<BuildStep[]>(initialBuildSteps);

    const toggleStep = (stepId: number) => {
        setExpandedSteps((prev) => {
            const newSet = new Set(prev);
            if (newSet.has(stepId)) {
                newSet.delete(stepId);
            } else {
                newSet.add(stepId);
            }
            return newSet;
        });
    };

    const handleDownloadLogs = () => {
        const allLogs = buildSteps
            .map((step) => {
                const header = `\n=== ${step.name} ===\n`;
                const metadata = `Status: ${step.status}\nDuration: ${step.duration}\n`;
                const logs = step.logs.join('\n');
                return header + metadata + logs;
            })
            .join('\n\n');

        const blob = new Blob([allLogs], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `build-logs-${service.uuid}.txt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    const isBuilding = buildSteps.some((step) => step.status === 'running');
    const hasFailed = buildSteps.some((step) => step.status === 'failed');

    return (
        <div className="space-y-4">
            {/* Header Actions */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="font-medium text-foreground">Build Logs</h3>
                            <p className="mt-1 text-sm text-foreground-muted">
                                View detailed logs for each build step
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button variant="secondary" size="sm" onClick={handleDownloadLogs}>
                                <Download className="mr-2 h-4 w-4" />
                                Download Logs
                            </Button>
                            {hasFailed && (
                                <Button size="sm">
                                    <RotateCw className="mr-2 h-4 w-4" />
                                    Retry Build
                                </Button>
                            )}
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Build Progress */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            {isBuilding ? (
                                <>
                                    <AlertCircle className="h-5 w-5 animate-pulse text-warning" />
                                    <span className="font-medium text-foreground">Build in progress...</span>
                                </>
                            ) : hasFailed ? (
                                <>
                                    <XCircle className="h-5 w-5 text-danger" />
                                    <span className="font-medium text-danger">Build failed</span>
                                </>
                            ) : (
                                <>
                                    <CheckCircle className="h-5 w-5 text-primary" />
                                    <span className="font-medium text-primary">Build completed successfully</span>
                                </>
                            )}
                        </div>
                        <div className="text-sm text-foreground-muted">
                            Total: {buildSteps.reduce((acc, step) => {
                                if (step.duration.includes('m')) {
                                    const [min, sec] = step.duration.split('m');
                                    return acc + parseInt(min) * 60 + parseFloat(sec);
                                }
                                return acc + parseFloat(step.duration);
                            }, 0).toFixed(1)}s
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Build Steps */}
            <div className="space-y-2">
                {buildSteps.map((step) => (
                    <Card key={step.id}>
                        <CardContent className="p-0">
                            {/* Step Header */}
                            <button
                                onClick={() => toggleStep(step.id)}
                                className="flex w-full items-center justify-between p-4 text-left transition-colors hover:bg-background-secondary"
                            >
                                <div className="flex items-center gap-3">
                                    {getStatusIcon(step.status)}
                                    <div>
                                        <h4 className="font-medium text-foreground">{step.name}</h4>
                                        <div className="mt-1 flex items-center gap-2 text-xs text-foreground-muted">
                                            {step.startTime && (
                                                <>
                                                    <span>{step.startTime}</span>
                                                    {step.endTime && (
                                                        <>
                                                            <span>→</span>
                                                            <span>{step.endTime}</span>
                                                        </>
                                                    )}
                                                    <span>·</span>
                                                </>
                                            )}
                                            <span>{step.duration}</span>
                                        </div>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className={`rounded px-2 py-1 text-xs font-medium ${
                                        step.status === 'success'
                                            ? 'bg-primary/10 text-primary'
                                            : step.status === 'failed'
                                            ? 'bg-danger/10 text-danger'
                                            : step.status === 'running'
                                            ? 'bg-warning/10 text-warning'
                                            : 'bg-foreground-subtle/10 text-foreground-muted'
                                    }`}>
                                        {step.status}
                                    </span>
                                    {expandedSteps.has(step.id) ? (
                                        <ChevronDown className="h-5 w-5 text-foreground-muted" />
                                    ) : (
                                        <ChevronRight className="h-5 w-5 text-foreground-muted" />
                                    )}
                                </div>
                            </button>

                            {/* Step Logs */}
                            {expandedSteps.has(step.id) && (
                                <div className="border-t border-border">
                                    <div className="bg-background-tertiary p-4">
                                        <div className="flex items-center gap-2 mb-3">
                                            <Terminal className="h-4 w-4 text-foreground-muted" />
                                            <span className="text-xs font-medium text-foreground-muted">
                                                Build Output
                                            </span>
                                        </div>
                                        <pre className="overflow-x-auto rounded-md bg-black p-4 font-mono text-xs text-green-400">
                                            {step.logs.map((log, index) => (
                                                <div key={index} className="leading-relaxed">
                                                    {log}
                                                </div>
                                            ))}
                                            {step.status === 'running' && (
                                                <div className="mt-1 animate-pulse">▌</div>
                                            )}
                                        </pre>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </div>
    );
}
