import * as React from 'react';
import { Head } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Button, Checkbox, useToast } from '@/components/ui';
import { Terminal, Copy, Download, CheckCircle2, Package } from 'lucide-react';

interface Project {
    id: number;
    uuid: string;
    name: string;
    repository?: string;
    environment: string;
}

interface Props {
    project: Project;
}

export default function ProjectLocalSetup({ project }: Props) {
    const [selectedServices, setSelectedServices] = React.useState<Set<string>>(new Set(['api', 'database']));
    const [includeEnv, setIncludeEnv] = React.useState(true);
    const { addToast } = useToast();

    const services = [
        { id: 'api', name: 'API Server', port: 3000 },
        { id: 'database', name: 'PostgreSQL', port: 5432 },
        { id: 'redis', name: 'Redis Cache', port: 6379 },
        { id: 'worker', name: 'Background Worker', port: null },
    ];

    const handleCopy = (text: string, label: string) => {
        navigator.clipboard.writeText(text);
        addToast('success', 'Copied to clipboard', `${label} copied to clipboard.`);
    };

    const toggleService = (serviceId: string) => {
        setSelectedServices(prev => {
            const newSet = new Set(prev);
            if (newSet.has(serviceId)) {
                newSet.delete(serviceId);
            } else {
                newSet.add(serviceId);
            }
            return newSet;
        });
    };

    const generateDockerCompose = () => {
        const selectedServicesList = services.filter(s => selectedServices.has(s.id));

        let compose = `version: '3.8'\n\nservices:\n`;

        selectedServicesList.forEach(service => {
            if (service.id === 'api') {
                compose += `  api:\n    build: .\n    ports:\n      - "3000:3000"\n    environment:\n      - NODE_ENV=development\n    depends_on:\n      - database\n\n`;
            } else if (service.id === 'database') {
                compose += `  database:\n    image: postgres:15\n    ports:\n      - "5432:5432"\n    environment:\n      - POSTGRES_DB=myapp\n      - POSTGRES_USER=user\n      - POSTGRES_PASSWORD=password\n    volumes:\n      - postgres_data:/var/lib/postgresql/data\n\n`;
            } else if (service.id === 'redis') {
                compose += `  redis:\n    image: redis:7-alpine\n    ports:\n      - "6379:6379"\n\n`;
            } else if (service.id === 'worker') {
                compose += `  worker:\n    build: .\n    command: npm run worker\n    environment:\n      - NODE_ENV=development\n    depends_on:\n      - database\n      - redis\n\n`;
            }
        });

        compose += `volumes:\n  postgres_data:`;

        return compose;
    };

    const downloadDockerCompose = () => {
        const compose = generateDockerCompose();
        const blob = new Blob([compose], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'docker-compose.yml';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        addToast('success', 'Download started', 'docker-compose.yml has been downloaded.');
    };

    return (
        <>
            <Head title={`Local Setup - ${project.name} | Saturn`} />
            <div className="min-h-screen bg-background p-6">
                <div className="mx-auto max-w-4xl space-y-6">
                    {/* Header */}
                    <div className="space-y-2">
                        <h1 className="text-3xl font-bold text-foreground">Run {project.name} Locally</h1>
                        <p className="text-foreground-muted">
                            Set up your local development environment
                        </p>
                    </div>

                    {/* Prerequisites */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Prerequisites</CardTitle>
                            <CardDescription>
                                Make sure you have these tools installed
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                <div className="flex items-center justify-between rounded-lg border border-border bg-background p-3">
                                    <div className="flex items-center gap-3">
                                        <Terminal className="h-5 w-5 text-primary" />
                                        <div>
                                            <p className="font-medium text-foreground">Saturn CLI</p>
                                            <p className="text-sm text-foreground-muted">Required for environment sync</p>
                                        </div>
                                    </div>
                                    <a href="/cli/setup">
                                        <Button size="sm" variant="secondary">
                                            Install
                                        </Button>
                                    </a>
                                </div>
                                <div className="flex items-center justify-between rounded-lg border border-border bg-background p-3">
                                    <div className="flex items-center gap-3">
                                        <Package className="h-5 w-5 text-primary" />
                                        <div>
                                            <p className="font-medium text-foreground">Docker</p>
                                            <p className="text-sm text-foreground-muted">For running services locally</p>
                                        </div>
                                    </div>
                                    <a href="https://docker.com" target="_blank" rel="noopener noreferrer">
                                        <Button size="sm" variant="secondary">
                                            Install
                                        </Button>
                                    </a>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Step 1: Clone Repository */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">
                                    1
                                </div>
                                <CardTitle>Clone Repository</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                <p className="text-sm text-foreground-muted">
                                    Clone the project repository to your local machine
                                </p>
                                <div className="relative">
                                    <pre className="overflow-x-auto rounded-lg bg-background-tertiary p-4 pr-12">
                                        <code className="text-sm text-foreground">
                                            git clone {project.repository || 'https://github.com/yourusername/repo.git'}
                                        </code>
                                    </pre>
                                    <button
                                        onClick={() => handleCopy(
                                            `git clone ${project.repository || 'https://github.com/yourusername/repo.git'}`,
                                            'Clone command'
                                        )}
                                        className="absolute right-3 top-3 rounded-md p-2 text-foreground-muted transition-colors hover:bg-background-secondary hover:text-foreground"
                                    >
                                        <Copy className="h-4 w-4" />
                                    </button>
                                </div>
                                <div className="relative">
                                    <pre className="overflow-x-auto rounded-lg bg-background-tertiary p-4">
                                        <code className="text-sm text-foreground">
                                            cd {project.name}
                                        </code>
                                    </pre>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Step 2: Install Dependencies */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">
                                    2
                                </div>
                                <CardTitle>Install Dependencies</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                <p className="text-sm text-foreground-muted">
                                    Install the project dependencies
                                </p>
                                <div className="relative">
                                    <pre className="overflow-x-auto rounded-lg bg-background-tertiary p-4 pr-12">
                                        <code className="text-sm text-foreground">
                                            npm install
                                        </code>
                                    </pre>
                                    <button
                                        onClick={() => handleCopy('npm install', 'Install command')}
                                        className="absolute right-3 top-3 rounded-md p-2 text-foreground-muted transition-colors hover:bg-background-secondary hover:text-foreground"
                                    >
                                        <Copy className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Step 3: Environment Variables */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">
                                    3
                                </div>
                                <CardTitle>Set Up Environment Variables</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <Checkbox
                                    label="Pull environment variables from Saturn"
                                    checked={includeEnv}
                                    onChange={(e) => setIncludeEnv(e.target.checked)}
                                />

                                {includeEnv ? (
                                    <div className="space-y-3">
                                        <p className="text-sm text-foreground-muted">
                                            Use Saturn CLI to download environment variables
                                        </p>
                                        <div className="relative">
                                            <pre className="overflow-x-auto rounded-lg bg-background-tertiary p-4 pr-12">
                                                <code className="text-sm text-foreground">
                                                    saturn env pull --env {project.environment}
                                                </code>
                                            </pre>
                                            <button
                                                onClick={() => handleCopy(`saturn env pull --env ${project.environment}`, 'Env pull command')}
                                                className="absolute right-3 top-3 rounded-md p-2 text-foreground-muted transition-colors hover:bg-background-secondary hover:text-foreground"
                                            >
                                                <Copy className="h-4 w-4" />
                                            </button>
                                        </div>
                                        <div className="flex items-start gap-2 rounded-lg border border-primary/20 bg-primary/5 p-3">
                                            <CheckCircle2 className="mt-0.5 h-4 w-4 flex-shrink-0 text-primary" />
                                            <p className="text-xs text-foreground-muted">
                                                This will create a .env file with your {project.environment} environment variables
                                            </p>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="space-y-3">
                                        <p className="text-sm text-foreground-muted">
                                            Create a .env file manually
                                        </p>
                                        <div className="relative">
                                            <pre className="overflow-x-auto rounded-lg bg-background-tertiary p-4 pr-12">
                                                <code className="text-sm text-foreground">
                                                    cp .env.example .env
                                                </code>
                                            </pre>
                                            <button
                                                onClick={() => handleCopy('cp .env.example .env', 'Copy env command')}
                                                className="absolute right-3 top-3 rounded-md p-2 text-foreground-muted transition-colors hover:bg-background-secondary hover:text-foreground"
                                            >
                                                <Copy className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Step 4: Docker Compose */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">
                                        4
                                    </div>
                                    <CardTitle>Start Services with Docker</CardTitle>
                                </div>
                                <Button size="sm" variant="secondary" onClick={downloadDockerCompose}>
                                    <Download className="mr-2 h-4 w-4" />
                                    Download
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <p className="text-sm text-foreground-muted">
                                    Select the services you want to run locally
                                </p>

                                <div className="space-y-2">
                                    {services.map(service => (
                                        <div
                                            key={service.id}
                                            className="flex items-center justify-between rounded-lg border border-border bg-background p-3"
                                        >
                                            <Checkbox
                                                label={service.name}
                                                checked={selectedServices.has(service.id)}
                                                onChange={() => toggleService(service.id)}
                                            />
                                            {service.port && (
                                                <span className="text-sm text-foreground-muted">
                                                    Port: {service.port}
                                                </span>
                                            )}
                                        </div>
                                    ))}
                                </div>

                                <div className="space-y-3">
                                    <p className="text-sm font-medium text-foreground">
                                        Generated docker-compose.yml
                                    </p>
                                    <div className="relative">
                                        <pre className="max-h-96 overflow-auto rounded-lg bg-background-tertiary p-4 pr-12">
                                            <code className="text-xs text-foreground">
                                                {generateDockerCompose()}
                                            </code>
                                        </pre>
                                        <button
                                            onClick={() => handleCopy(generateDockerCompose(), 'Docker Compose')}
                                            className="absolute right-3 top-3 rounded-md p-2 text-foreground-muted transition-colors hover:bg-background-secondary hover:text-foreground"
                                        >
                                            <Copy className="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>

                                <div className="relative">
                                    <pre className="overflow-x-auto rounded-lg bg-background-tertiary p-4 pr-12">
                                        <code className="text-sm text-foreground">
                                            docker compose up -d
                                        </code>
                                    </pre>
                                    <button
                                        onClick={() => handleCopy('docker compose up -d', 'Docker up command')}
                                        className="absolute right-3 top-3 rounded-md p-2 text-foreground-muted transition-colors hover:bg-background-secondary hover:text-foreground"
                                    >
                                        <Copy className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Step 5: Run Application */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">
                                    5
                                </div>
                                <CardTitle>Run Your Application</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                <p className="text-sm text-foreground-muted">
                                    Use Saturn CLI to run with your environment variables
                                </p>
                                <div className="relative">
                                    <pre className="overflow-x-auto rounded-lg bg-background-tertiary p-4 pr-12">
                                        <code className="text-sm text-foreground">
                                            saturn run npm run dev
                                        </code>
                                    </pre>
                                    <button
                                        onClick={() => handleCopy('saturn run npm run dev', 'Run command')}
                                        className="absolute right-3 top-3 rounded-md p-2 text-foreground-muted transition-colors hover:bg-background-secondary hover:text-foreground"
                                    >
                                        <Copy className="h-4 w-4" />
                                    </button>
                                </div>

                                <div className="flex items-start gap-2 rounded-lg border border-primary/20 bg-primary/5 p-3">
                                    <CheckCircle2 className="mt-0.5 h-4 w-4 flex-shrink-0 text-primary" />
                                    <div className="text-xs text-foreground-muted">
                                        <p className="font-medium">Your application is now running!</p>
                                        <p className="mt-1">Open http://localhost:3000 in your browser</p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Quick Commands */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Quick Commands</CardTitle>
                            <CardDescription>
                                Useful commands for local development
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                <div className="rounded-lg border border-border bg-background p-3">
                                    <code className="text-sm font-medium text-foreground">saturn logs -f</code>
                                    <p className="mt-1 text-xs text-foreground-muted">Follow application logs</p>
                                </div>
                                <div className="rounded-lg border border-border bg-background p-3">
                                    <code className="text-sm font-medium text-foreground">docker compose logs -f</code>
                                    <p className="mt-1 text-xs text-foreground-muted">Follow Docker service logs</p>
                                </div>
                                <div className="rounded-lg border border-border bg-background p-3">
                                    <code className="text-sm font-medium text-foreground">docker compose down</code>
                                    <p className="mt-1 text-xs text-foreground-muted">Stop all services</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
