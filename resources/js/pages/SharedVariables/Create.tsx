import { Head, useForm, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Input, Select, Checkbox } from '@/components/ui';
import { ArrowLeft, Save, Building2, FolderKanban, Layers } from 'lucide-react';
import { Link } from '@inertiajs/react';

interface Props {
    teams: { id: number; name: string }[];
    projects: { id: number; name: string; team_id: number }[];
    environments: { id: number; name: string; project_id: number }[];
}

export default function SharedVariablesCreate({ teams = [], projects = [], environments = [] }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        key: '',
        value: '',
        is_secret: false,
        scope: 'team',
        team_id: teams[0]?.id || '',
        project_id: '',
        environment_id: '',
    });

    const filteredProjects = projects.filter(p => p.team_id === Number(data.team_id));
    const filteredEnvironments = environments.filter(e => e.project_id === Number(data.project_id));

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/shared-variables');
    };

    const scopeOptions = [
        { value: 'team', label: 'Team Level', icon: Building2, description: 'Available to all projects in the team' },
        { value: 'project', label: 'Project Level', icon: FolderKanban, description: 'Available to all environments in the project' },
        { value: 'environment', label: 'Environment Level', icon: Layers, description: 'Only available in this environment' },
    ];

    return (
        <AppLayout
            title="Create Shared Variable"
            breadcrumbs={[
                { label: 'Dashboard', href: '/new' },
                { label: 'Shared Variables', href: '/shared-variables' },
                { label: 'Create' },
            ]}
        >
            <Head title="Create Shared Variable" />

            <div className="max-w-2xl mx-auto space-y-6">
                <div className="flex items-center gap-4">
                    <Link href="/shared-variables">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back
                        </Button>
                    </Link>
                    <h1 className="text-2xl font-bold">Create Shared Variable</h1>
                </div>

                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Variable Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Scope Selection */}
                            <div className="space-y-3">
                                <label className="text-sm font-medium">Scope</label>
                                <div className="grid gap-3">
                                    {scopeOptions.map(option => (
                                        <label
                                            key={option.value}
                                            className={`flex items-center gap-4 p-4 rounded-lg border cursor-pointer transition-colors ${
                                                data.scope === option.value
                                                    ? 'border-primary bg-primary/5'
                                                    : 'border-border hover:border-primary/50'
                                            }`}
                                        >
                                            <input
                                                type="radio"
                                                name="scope"
                                                value={option.value}
                                                checked={data.scope === option.value}
                                                onChange={(e) => setData('scope', e.target.value)}
                                                className="sr-only"
                                            />
                                            <option.icon className="h-5 w-5 text-primary" />
                                            <div>
                                                <div className="font-medium">{option.label}</div>
                                                <div className="text-sm text-foreground-muted">{option.description}</div>
                                            </div>
                                        </label>
                                    ))}
                                </div>
                            </div>

                            {/* Team Selection */}
                            <div>
                                <label className="text-sm font-medium">Team</label>
                                <Select
                                    value={String(data.team_id)}
                                    onChange={(e) => {
                                        setData('team_id', e.target.value);
                                        setData('project_id', '');
                                        setData('environment_id', '');
                                    }}
                                >
                                    {teams.map(team => (
                                        <option key={team.id} value={team.id}>{team.name}</option>
                                    ))}
                                </Select>
                            </div>

                            {/* Project Selection (for project/environment scope) */}
                            {(data.scope === 'project' || data.scope === 'environment') && (
                                <div>
                                    <label className="text-sm font-medium">Project</label>
                                    <Select
                                        value={String(data.project_id)}
                                        onChange={(e) => {
                                            setData('project_id', e.target.value);
                                            setData('environment_id', '');
                                        }}
                                    >
                                        <option value="">Select a project</option>
                                        {filteredProjects.map(project => (
                                            <option key={project.id} value={project.id}>{project.name}</option>
                                        ))}
                                    </Select>
                                </div>
                            )}

                            {/* Environment Selection (for environment scope) */}
                            {data.scope === 'environment' && data.project_id && (
                                <div>
                                    <label className="text-sm font-medium">Environment</label>
                                    <Select
                                        value={String(data.environment_id)}
                                        onChange={(e) => setData('environment_id', e.target.value)}
                                    >
                                        <option value="">Select an environment</option>
                                        {filteredEnvironments.map(env => (
                                            <option key={env.id} value={env.id}>{env.name}</option>
                                        ))}
                                    </Select>
                                </div>
                            )}

                            {/* Variable Key */}
                            <div>
                                <label className="text-sm font-medium">Variable Name</label>
                                <Input
                                    value={data.key}
                                    onChange={(e) => setData('key', e.target.value.toUpperCase().replace(/[^A-Z0-9_]/g, '_'))}
                                    placeholder="MY_VARIABLE"
                                    className="font-mono"
                                />
                                {errors.key && <p className="text-sm text-danger mt-1">{errors.key}</p>}
                            </div>

                            {/* Variable Value */}
                            <div>
                                <label className="text-sm font-medium">Value</label>
                                <Input
                                    type={data.is_secret ? 'password' : 'text'}
                                    value={data.value}
                                    onChange={(e) => setData('value', e.target.value)}
                                    placeholder="Variable value"
                                />
                                {errors.value && <p className="text-sm text-danger mt-1">{errors.value}</p>}
                            </div>

                            {/* Secret Toggle */}
                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="is_secret"
                                    checked={data.is_secret}
                                    onChange={(e) => setData('is_secret', e.target.checked)}
                                />
                                <label htmlFor="is_secret" className="text-sm">
                                    This is a secret value (will be hidden in UI)
                                </label>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-3 mt-6">
                        <Link href="/shared-variables">
                            <Button variant="ghost">Cancel</Button>
                        </Link>
                        <Button type="submit" disabled={processing}>
                            <Save className="h-4 w-4 mr-2" />
                            Create Variable
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
