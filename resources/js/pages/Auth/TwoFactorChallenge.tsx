import { useState, FormEvent } from 'react';
import { router } from '@inertiajs/react';
import { AuthLayout } from '@/components/layout';
import { Button, Input } from '@/components/ui';
import { Shield, Key } from 'lucide-react';

export default function TwoFactorChallenge() {
    const [code, setCode] = useState('');
    const [recoveryCode, setRecoveryCode] = useState('');
    const [useRecovery, setUseRecovery] = useState(false);
    const [error, setError] = useState<string>();
    const [isLoading, setIsLoading] = useState(false);

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        setError(undefined);

        const data = useRecovery
            ? { recovery_code: recoveryCode }
            : { code };

        router.post('/two-factor-challenge', data, {
            onError: (errors) => {
                setError(errors.code || errors.recovery_code || 'Invalid code');
                setIsLoading(false);
            },
            onSuccess: () => {
                setIsLoading(false);
            },
        });
    };

    return (
        <AuthLayout title="Two-Factor Authentication">
            <div className="mx-auto w-full max-w-md">
                <div className="mb-8 text-center">
                    <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                        <Shield className="h-6 w-6 text-primary" />
                    </div>
                    <h1 className="text-2xl font-bold text-foreground">Two-Factor Authentication</h1>
                    <p className="mt-2 text-sm text-foreground-muted">
                        {useRecovery
                            ? 'Please enter one of your recovery codes.'
                            : 'Please enter the authentication code from your authenticator app.'}
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {useRecovery ? (
                        <Input
                            type="text"
                            label="Recovery Code"
                            placeholder="XXXXX-XXXXX"
                            value={recoveryCode}
                            onChange={(e) => setRecoveryCode(e.target.value)}
                            error={error}
                            required
                        />
                    ) : (
                        <Input
                            type="text"
                            label="Authentication Code"
                            placeholder="000000"
                            value={code}
                            onChange={(e) => setCode(e.target.value)}
                            error={error}
                            maxLength={6}
                            required
                        />
                    )}

                    <Button type="submit" className="w-full" disabled={isLoading}>
                        {isLoading ? 'Verifying...' : 'Verify'}
                    </Button>

                    <button
                        type="button"
                        className="w-full text-center text-sm text-foreground-muted hover:text-foreground"
                        onClick={() => {
                            setUseRecovery(!useRecovery);
                            setError(undefined);
                        }}
                    >
                        {useRecovery ? (
                            <span className="flex items-center justify-center gap-2">
                                <Shield className="h-4 w-4" />
                                Use authentication code instead
                            </span>
                        ) : (
                            <span className="flex items-center justify-center gap-2">
                                <Key className="h-4 w-4" />
                                Use a recovery code instead
                            </span>
                        )}
                    </button>
                </form>
            </div>
        </AuthLayout>
    );
}
