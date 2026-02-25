import { useState } from 'react';
import { router } from '@inertiajs/react';
import { AuthLayout } from '@/components/layout';
import { Button, Select } from '@/components/ui';
import { Terminal, CheckCircle, XCircle, Shield } from 'lucide-react';

interface Props {
    code: string | null;
    error?: string;
    teams: { id: number; name: string }[];
    defaultTeamId: number | null;
}

export default function CliAuth({ code, error, teams, defaultTeamId }: Props) {
    const [selectedTeamId, setSelectedTeamId] = useState<string>(
        String(defaultTeamId ?? teams[0]?.id ?? '')
    );
    const [processing, setProcessing] = useState(false);
    const [success, setSuccess] = useState(false);
    const [denied, setDenied] = useState(false);

    if (error || !code) {
        return (
            <AuthLayout
                title="CLI Authorization"
                subtitle="Something went wrong."
            >
                <div className="space-y-6">
                    <div className="rounded-lg border border-danger/30 bg-danger/5 p-6">
                        <p className="text-center text-foreground">
                            {error || 'No authorization code provided.'}
                        </p>
                    </div>
                </div>
            </AuthLayout>
        );
    }

    if (success) {
        return (
            <AuthLayout
                title="CLI Authorized"
                subtitle="You can close this tab now."
            >
                <div className="space-y-6">
                    <div className="flex flex-col items-center gap-4 rounded-lg border border-success/30 bg-success/5 p-8">
                        <CheckCircle className="h-12 w-12 text-success" />
                        <p className="text-center text-lg font-semibold text-foreground">
                            Saturn CLI has been authorized!
                        </p>
                        <p className="text-center text-sm text-foreground-muted">
                            Return to your terminal to continue.
                        </p>
                    </div>
                </div>
            </AuthLayout>
        );
    }

    if (denied) {
        return (
            <AuthLayout
                title="CLI Authorization Denied"
                subtitle="The authorization request was denied."
            >
                <div className="space-y-6">
                    <div className="flex flex-col items-center gap-4 rounded-lg border border-danger/30 bg-danger/5 p-8">
                        <XCircle className="h-12 w-12 text-danger" />
                        <p className="text-center text-foreground">
                            You can close this tab now.
                        </p>
                    </div>
                </div>
            </AuthLayout>
        );
    }

    const handleApprove = () => {
        setProcessing(true);
        router.post('/cli/auth/approve', {
            code,
            team_id: selectedTeamId,
        }, {
            onSuccess: () => setSuccess(true),
            onError: () => setProcessing(false),
            onFinish: () => setProcessing(false),
        });
    };

    const handleDeny = () => {
        setProcessing(true);
        router.post('/cli/auth/deny', { code }, {
            onSuccess: () => setDenied(true),
            onError: () => setProcessing(false),
            onFinish: () => setProcessing(false),
        });
    };

    const teamOptions = teams.map((team) => ({
        value: String(team.id),
        label: team.name,
    }));

    return (
        <AuthLayout
            title="Authorize Saturn CLI"
            subtitle="A CLI session is requesting access to your account."
        >
            <div className="space-y-6">
                {/* Code display */}
                <div className="rounded-lg border border-border bg-background p-6">
                    <div className="flex items-center gap-4">
                        <div className="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-lg bg-primary/10">
                            <Terminal className="h-7 w-7 text-primary" />
                        </div>
                        <div className="flex-1">
                            <p className="text-sm text-foreground-muted">Confirmation Code</p>
                            <p className="font-mono text-2xl font-bold tracking-widest text-foreground">
                                {code}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Security notice */}
                <div className="flex items-start gap-3 rounded-lg border border-yellow-500/20 bg-yellow-500/10 p-4">
                    <Shield className="mt-0.5 h-5 w-5 flex-shrink-0 text-yellow-600 dark:text-yellow-500" />
                    <p className="text-sm text-yellow-600 dark:text-yellow-500">
                        Make sure this code matches what you see in your terminal.
                        Only authorize if you initiated this request.
                    </p>
                </div>

                {/* Team selector */}
                {teams.length > 1 && (
                    <Select
                        label="Team"
                        hint="The CLI token will be scoped to this team."
                        options={teamOptions}
                        value={selectedTeamId}
                        onChange={(e) => setSelectedTeamId(e.target.value)}
                    />
                )}

                {/* Action buttons */}
                <div className="flex gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        className="flex-1"
                        onClick={handleDeny}
                        disabled={processing}
                    >
                        <XCircle className="mr-2 h-4 w-4" />
                        Deny
                    </Button>
                    <Button
                        type="button"
                        className="flex-1"
                        onClick={handleApprove}
                        loading={processing}
                    >
                        <Terminal className="mr-2 h-4 w-4" />
                        Authorize CLI
                    </Button>
                </div>
            </div>
        </AuthLayout>
    );
}
