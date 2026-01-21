import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, Button } from '@/components/ui';
import {
    Download, RotateCw, ChevronDown, ChevronRight,
    CheckCircle, XCircle, Clock, AlertCircle, Terminal
} from 'lucide-react';
import type { Service } from '@/types';

interface Props {
    service: Service;
}

interface BuildStep {
    id: number;
    name: string;
    status: 'pending' | 'running' | 'success' | 'failed';
    duration: string;
    logs: string[];
    startTime?: string;
    endTime?: string;
}

// Mock build steps data
const mockBuildSteps: BuildStep[] = [
    {
        id: 1,
        name: 'Clone Repository',
        status: 'success',
        duration: '2.3s',
        startTime: '14:32:01',
        endTime: '14:32:03',
        logs: [
            'Cloning into /tmp/build-123...',
            'remote: Enumerating objects: 145, done.',
            'remote: Counting objects: 100% (145/145), done.',
            'remote: Compressing objects: 100% (98/98), done.',
            'remote: Total 145 (delta 42), reused 145 (delta 42)',
            'Receiving objects: 100% (145/145), 1.23 MiB | 2.34 MiB/s, done.',
            'Resolving deltas: 100% (42/42), done.',
        ],
    },
    {
        id: 2,
        name: 'Install Dependencies',
        status: 'success',
        duration: '45.7s',
        startTime: '14:32:03',
        endTime: '14:32:49',
        logs: [
            'npm install',
            'npm WARN deprecated package@1.0.0: Use package@2.0.0 instead',
            'added 847 packages, and audited 848 packages in 44s',
            '127 packages are looking for funding',
            '  run `npm fund` for details',
            'found 0 vulnerabilities',
        ],
    },
    {
        id: 3,
        name: 'Build Application',
        status: 'success',
        duration: '1m 23s',
        startTime: '14:32:49',
        endTime: '14:34:12',
        logs: [
            'npm run build',
            '> app@1.0.0 build',
            '> vite build',
            'vite v5.0.0 building for production...',
            'transforming...',
            '✓ 234 modules transformed.',
            'rendering chunks...',
            'computing gzip size...',
            'dist/index.html                   0.45 kB │ gzip:  0.30 kB',
            'dist/assets/index-a1b2c3d4.css  12.34 kB │ gzip:  3.45 kB',
            'dist/assets/index-e5f6g7h8.js  145.67 kB │ gzip: 45.67 kB',
            '✓ built in 82.3s',
        ],
    },
    {
        id: 4,
        name: 'Run Tests',
        status: 'success',
        duration: '12.4s',
        startTime: '14:34:12',
        endTime: '14:34:24',
        logs: [
            'npm run test',
            '> app@1.0.0 test',
            '> jest',
            'PASS  src/components/Button.test.tsx',
            'PASS  src/components/Card.test.tsx',
            'PASS  src/utils/helpers.test.ts',
            '',
            'Test Suites: 3 passed, 3 total',
            'Tests:       24 passed, 24 total',
            'Snapshots:   0 total',
            'Time:        11.234 s',
            'Ran all test suites.',
        ],
    },
    {
        id: 5,
        name: 'Create Docker Image',
        status: 'success',
        duration: '34.2s',
        startTime: '14:34:24',
        endTime: '14:34:58',
        logs: [
            'Building Docker image...',
            'Step 1/8 : FROM node:18-alpine',
            ' ---> a1b2c3d4e5f6',
            'Step 2/8 : WORKDIR /app',
            ' ---> Using cache',
            ' ---> b2c3d4e5f6g7',
            'Step 3/8 : COPY package*.json ./',
            ' ---> Using cache',
            ' ---> c3d4e5f6g7h8',
            'Step 4/8 : RUN npm ci --only=production',
            ' ---> Running in d4e5f6g7h8i9',
            'added 234 packages in 12.3s',
            ' ---> e5f6g7h8i9j0',
            'Step 5/8 : COPY dist ./dist',
            ' ---> f6g7h8i9j0k1',
            'Successfully built f6g7h8i9j0k1',
            'Successfully tagged production-api:latest',
        ],
    },
    {
        id: 6,
        name: 'Deploy Container',
        status: 'running',
        duration: '8.1s',
        startTime: '14:34:58',
        logs: [
            'Deploying container to server...',
            'Stopping old container...',
            'Container stopped successfully',
            'Starting new container...',
            'Container started with ID: abc123def456',
            'Waiting for health check...',
        ],
    },
];

export function BuildLogsTab({ service }: Props) {
    const [expandedSteps, setExpandedSteps] = useState<Set<number>>(new Set([6])); // Expand running step by default
    const [buildSteps] = useState<BuildStep[]>(mockBuildSteps);

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

    const getStatusIcon = (status: BuildStep['status']) => {
        switch (status) {
            case 'success':
                return <CheckCircle className="h-5 w-5 text-primary" />;
            case 'failed':
                return <XCircle className="h-5 w-5 text-danger" />;
            case 'running':
                return <AlertCircle className="h-5 w-5 animate-pulse text-warning" />;
            case 'pending':
                return <Clock className="h-5 w-5 text-foreground-muted" />;
        }
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
