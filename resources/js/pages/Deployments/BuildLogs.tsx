import * as React from 'react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, CardHeader, CardTitle, Button } from '@/components/ui';
import {
    Download,
    RotateCw,
    ChevronDown,
    ChevronRight,
    CheckCircle,
    XCircle,
    Clock,
    AlertCircle,
    Terminal,
    Search,
    Filter,
    Maximize2,
    Minimize2,
    Copy,
    ChevronLeft,
} from 'lucide-react';
import { Link } from '@inertiajs/react';

interface Props {
    deploymentUuid?: string;
    buildSteps?: BuildStep[];
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
const MOCK_BUILD_STEPS: BuildStep[] = [
    {
        id: 1,
        name: 'Clone Repository',
        status: 'success',
        duration: '2.3s',
        startTime: '14:32:01',
        endTime: '14:32:03',
        logs: [
            '[14:32:01.123] Cloning into /tmp/build-123...',
            '[14:32:01.456] remote: Enumerating objects: 145, done.',
            '[14:32:01.789] remote: Counting objects: 100% (145/145), done.',
            '[14:32:02.012] remote: Compressing objects: 100% (98/98), done.',
            '[14:32:02.345] remote: Total 145 (delta 42), reused 145 (delta 42)',
            '[14:32:02.678] Receiving objects: 100% (145/145), 1.23 MiB | 2.34 MiB/s, done.',
            '[14:32:02.901] Resolving deltas: 100% (42/42), done.',
            '[14:32:03.134] ✓ Repository cloned successfully',
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
            '[14:32:03.234] Running: npm install',
            '[14:32:03.456] npm WARN deprecated package@1.0.0: Use package@2.0.0 instead',
            '[14:32:05.123] Downloading packages...',
            '[14:32:15.456] Installing: react@18.2.0',
            '[14:32:20.789] Installing: typescript@5.0.0',
            '[14:32:25.012] Installing: vite@5.0.0',
            '[14:32:30.345] Installing: tailwindcss@3.4.0',
            '[14:32:35.678] Installing: lucide-react@0.263.0',
            '[14:32:40.901] Building fresh packages...',
            '[14:32:48.234] added 847 packages, and audited 848 packages in 44s',
            '[14:32:48.567] 127 packages are looking for funding',
            '[14:32:48.890] run `npm fund` for details',
            '[14:32:49.123] found 0 vulnerabilities',
            '[14:32:49.456] ✓ Dependencies installed successfully',
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
            '[14:32:49.567] Running: npm run build',
            '[14:32:49.890] > app@1.0.0 build',
            '[14:32:50.123] > vite build',
            '[14:32:50.456] vite v5.0.0 building for production...',
            '[14:32:51.789] transforming...',
            '[14:33:00.012] ├─ components/ui/Button.tsx',
            '[14:33:02.345] ├─ components/ui/Card.tsx',
            '[14:33:04.678] ├─ components/ui/Input.tsx',
            '[14:33:06.901] ├─ components/layout/AppLayout.tsx',
            '[14:33:10.234] ├─ pages/Dashboard.tsx',
            '[14:33:15.567] ├─ pages/Deployments/Index.tsx',
            '[14:33:20.890] ├─ pages/Services/Show.tsx',
            '[14:33:25.123] └─ app.tsx',
            '[14:33:45.456] ✓ 234 modules transformed.',
            '[14:33:46.789] rendering chunks...',
            '[14:33:50.012] computing gzip size...',
            '[14:34:10.345] dist/index.html                   0.45 kB │ gzip:  0.30 kB',
            '[14:34:10.678] dist/assets/index-a1b2c3d4.css  12.34 kB │ gzip:  3.45 kB',
            '[14:34:10.901] dist/assets/index-e5f6g7h8.js  145.67 kB │ gzip: 45.67 kB',
            '[14:34:11.234] dist/assets/vendor-i9j0k1l2.js  234.56 kB │ gzip: 78.90 kB',
            '[14:34:11.567] ✓ built in 82.3s',
            '[14:34:12.890] Build completed successfully',
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
            '[14:34:12.123] Running: npm run test',
            '[14:34:12.456] > app@1.0.0 test',
            '[14:34:12.789] > jest',
            '[14:34:13.012] PASS  src/components/Button.test.tsx',
            '[14:34:14.345] PASS  src/components/Card.test.tsx',
            '[14:34:15.678] PASS  src/components/Input.test.tsx',
            '[14:34:17.901] PASS  src/utils/helpers.test.ts',
            '[14:34:19.234] PASS  src/hooks/useAuth.test.ts',
            '[14:34:21.567] ',
            '[14:34:21.890] Test Suites: 5 passed, 5 total',
            '[14:34:22.123] Tests:       42 passed, 42 total',
            '[14:34:22.456] Snapshots:   0 total',
            '[14:34:22.789] Time:        11.234 s',
            '[14:34:23.012] Ran all test suites.',
            '[14:34:24.345] ✓ All tests passed',
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
            '[14:34:24.456] Building Docker image...',
            '[14:34:25.789] Step 1/8 : FROM node:18-alpine',
            '[14:34:26.012]  ---> a1b2c3d4e5f6',
            '[14:34:26.345] Step 2/8 : WORKDIR /app',
            '[14:34:26.678]  ---> Using cache',
            '[14:34:26.901]  ---> b2c3d4e5f6g7',
            '[14:34:27.234] Step 3/8 : COPY package*.json ./',
            '[14:34:27.567]  ---> Using cache',
            '[14:34:27.890]  ---> c3d4e5f6g7h8',
            '[14:34:28.123] Step 4/8 : RUN npm ci --only=production',
            '[14:34:28.456]  ---> Running in d4e5f6g7h8i9',
            '[14:34:35.789] added 234 packages in 7.2s',
            '[14:34:36.012]  ---> e5f6g7h8i9j0',
            '[14:34:36.345] Step 5/8 : COPY dist ./dist',
            '[14:34:40.678]  ---> f6g7h8i9j0k1',
            '[14:34:41.901] Step 6/8 : EXPOSE 3000',
            '[14:34:42.234]  ---> Running in g7h8i9j0k1l2',
            '[14:34:42.567]  ---> h8i9j0k1l2m3',
            '[14:34:42.890] Step 7/8 : ENV NODE_ENV=production',
            '[14:34:43.123]  ---> Running in i9j0k1l2m3n4',
            '[14:34:43.456]  ---> j0k1l2m3n4o5',
            '[14:34:43.789] Step 8/8 : CMD ["node", "dist/server.js"]',
            '[14:34:44.012]  ---> Running in k1l2m3n4o5p6',
            '[14:34:44.345]  ---> l2m3n4o5p6q7',
            '[14:34:55.678] Successfully built l2m3n4o5p6q7',
            '[14:34:56.901] Successfully tagged production-api:a1b2c3d4',
            '[14:34:58.234] ✓ Docker image created successfully',
        ],
    },
    {
        id: 6,
        name: 'Deploy Container',
        status: 'running',
        duration: '8.1s',
        startTime: '14:34:58',
        logs: [
            '[14:34:58.345] Deploying container to server...',
            '[14:34:59.678] Pushing image to registry...',
            '[14:35:02.901] Image pushed successfully',
            '[14:35:03.234] Connecting to prod-server-1...',
            '[14:35:03.567] Connection established',
            '[14:35:03.890] Pulling image on remote server...',
            '[14:35:06.123] Image pulled successfully',
            '[14:35:06.456] Stopping old container...',
        ],
    },
];

export default function BuildLogsView({ deploymentUuid = 'dep-1', buildSteps: propBuildSteps }: Props) {
    const buildSteps = propBuildSteps || MOCK_BUILD_STEPS;
    const [expandedSteps, setExpandedSteps] = React.useState<Set<number>>(new Set([6])); // Expand running step by default
    const [expandAll, setExpandAll] = React.useState(false);
    const [searchQuery, setSearchQuery] = React.useState('');
    const [logLevel, setLogLevel] = React.useState<'all' | 'info' | 'warn' | 'error'>('all');
    const [isFullscreen, setIsFullscreen] = React.useState(false);

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

    const toggleExpandAll = () => {
        if (expandAll) {
            setExpandedSteps(new Set());
        } else {
            setExpandedSteps(new Set(buildSteps.map(s => s.id)));
        }
        setExpandAll(!expandAll);
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
                const timeRange = step.startTime && step.endTime
                    ? `Time: ${step.startTime} - ${step.endTime}\n`
                    : step.startTime
                    ? `Started: ${step.startTime}\n`
                    : '';
                const logs = step.logs.join('\n');
                return header + metadata + timeRange + '\n' + logs;
            })
            .join('\n\n');

        const blob = new Blob([allLogs], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `build-logs-${deploymentUuid}.txt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    const isBuilding = buildSteps.some((step) => step.status === 'running');
    const hasFailed = buildSteps.some((step) => step.status === 'failed');
    const totalDuration = buildSteps.reduce((acc, step) => {
        if (step.duration.includes('m')) {
            const [min, sec] = step.duration.split('m');
            return acc + parseInt(min) * 60 + parseFloat(sec);
        }
        return acc + parseFloat(step.duration);
    }, 0);

    const filterLogs = (logs: string[]) => {
        let filtered = logs;

        // Filter by log level
        if (logLevel !== 'all') {
            filtered = filtered.filter(log => {
                const lower = log.toLowerCase();
                if (logLevel === 'error') return lower.includes('error') || lower.includes('failed');
                if (logLevel === 'warn') return lower.includes('warn') || lower.includes('warning');
                if (logLevel === 'info') return !lower.includes('error') && !lower.includes('warn');
                return true;
            });
        }

        // Filter by search
        if (searchQuery) {
            const query = searchQuery.toLowerCase();
            filtered = filtered.filter(log => log.toLowerCase().includes(query));
        }

        return filtered;
    };

    const content = (
        <div className="space-y-4">
            {/* Header Actions */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex-1">
                            <h3 className="font-medium text-foreground">Build Logs</h3>
                            <div className="mt-1 flex items-center gap-2 text-sm text-foreground-muted">
                                {isBuilding ? (
                                    <>
                                        <AlertCircle className="h-4 w-4 animate-pulse text-warning" />
                                        <span>Build in progress...</span>
                                    </>
                                ) : hasFailed ? (
                                    <>
                                        <XCircle className="h-4 w-4 text-danger" />
                                        <span>Build failed</span>
                                    </>
                                ) : (
                                    <>
                                        <CheckCircle className="h-4 w-4 text-primary" />
                                        <span>Build completed successfully</span>
                                    </>
                                )}
                                <span>·</span>
                                <span>Total: {Math.floor(totalDuration / 60)}m {(totalDuration % 60).toFixed(1)}s</span>
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Button variant="secondary" size="sm" onClick={toggleExpandAll}>
                                {expandAll ? (
                                    <>
                                        <Minimize2 className="mr-2 h-4 w-4" />
                                        Collapse All
                                    </>
                                ) : (
                                    <>
                                        <Maximize2 className="mr-2 h-4 w-4" />
                                        Expand All
                                    </>
                                )}
                            </Button>
                            <Button variant="secondary" size="sm" onClick={handleDownloadLogs}>
                                <Download className="mr-2 h-4 w-4" />
                                Download
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

            {/* Filters */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-foreground-muted" />
                            <input
                                type="text"
                                placeholder="Search within logs..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="w-full rounded-md border border-border bg-background-secondary py-2 pl-10 pr-3 text-sm text-foreground placeholder-foreground-muted focus:border-primary focus:outline-none"
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <Filter className="h-4 w-4 text-foreground-muted" />
                            <select
                                value={logLevel}
                                onChange={(e) => setLogLevel(e.target.value as any)}
                                className="rounded-md border border-border bg-background-secondary px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none"
                            >
                                <option value="all">All Levels</option>
                                <option value="info">Info</option>
                                <option value="warn">Warnings</option>
                                <option value="error">Errors</option>
                            </select>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Build Steps */}
            <div className="space-y-2">
                {buildSteps.map((step) => {
                    const filteredLogs = filterLogs(step.logs);
                    const hasFilteredResults = filteredLogs.length > 0;
                    const showStep = !searchQuery && logLevel === 'all' || hasFilteredResults;

                    if (!showStep) return null;

                    return (
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
                                                {(searchQuery || logLevel !== 'all') && (
                                                    <>
                                                        <span>·</span>
                                                        <span>{filteredLogs.length} / {step.logs.length} lines</span>
                                                    </>
                                                )}
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
                                            <div className="mb-3 flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <Terminal className="h-4 w-4 text-foreground-muted" />
                                                    <span className="text-xs font-medium text-foreground-muted">
                                                        Build Output
                                                    </span>
                                                </div>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => {
                                                        navigator.clipboard.writeText(filteredLogs.join('\n'));
                                                    }}
                                                >
                                                    <Copy className="h-3.5 w-3.5" />
                                                </Button>
                                            </div>
                                            <pre className="max-h-96 overflow-x-auto overflow-y-auto rounded-md bg-black p-4 font-mono text-xs text-green-400">
                                                {filteredLogs.length === 0 ? (
                                                    <div className="text-center text-foreground-muted">
                                                        No logs match your filters
                                                    </div>
                                                ) : (
                                                    filteredLogs.map((log, index) => (
                                                        <div key={index} className="leading-relaxed hover:bg-green-400/10">
                                                            {log}
                                                        </div>
                                                    ))
                                                )}
                                                {step.status === 'running' && (
                                                    <div className="mt-1 animate-pulse">▌</div>
                                                )}
                                            </pre>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    );
                })}
            </div>
        </div>
    );

    return (
        <AppLayout
            title="Build Logs"
            breadcrumbs={[
                { label: 'Deployments', href: '/deployments' },
                { label: deploymentUuid, href: `/deployments/${deploymentUuid}` },
                { label: 'Build Logs' },
            ]}
        >
            <div className="mb-4 flex items-center justify-between">
                <Link href={`/deployments/${deploymentUuid}`}>
                    <Button variant="ghost" size="sm">
                        <ChevronLeft className="mr-1 h-4 w-4" />
                        Back to Deployment
                    </Button>
                </Link>
            </div>

            {content}
        </AppLayout>
    );
}
