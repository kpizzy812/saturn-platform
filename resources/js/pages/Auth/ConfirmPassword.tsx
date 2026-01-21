import { useState, FormEvent } from 'react';
import { router } from '@inertiajs/react';
import { AuthLayout } from '@/components/layout';
import { Button, Input } from '@/components/ui';
import { Lock } from 'lucide-react';

export default function ConfirmPassword() {
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string>();
    const [isLoading, setIsLoading] = useState(false);

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        setError(undefined);

        router.post('/user/confirm-password', { password }, {
            onError: (errors) => {
                setError(errors.password || 'Invalid password');
                setIsLoading(false);
            },
            onSuccess: () => {
                setIsLoading(false);
            },
        });
    };

    return (
        <AuthLayout title="Confirm Password">
            <div className="mx-auto w-full max-w-md">
                <div className="mb-8 text-center">
                    <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                        <Lock className="h-6 w-6 text-primary" />
                    </div>
                    <h1 className="text-2xl font-bold text-foreground">Confirm Password</h1>
                    <p className="mt-2 text-sm text-foreground-muted">
                        Please confirm your password before continuing.
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <Input
                        type="password"
                        label="Password"
                        placeholder="Enter your password"
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        error={error}
                        required
                    />

                    <Button type="submit" className="w-full" disabled={isLoading}>
                        {isLoading ? 'Confirming...' : 'Confirm Password'}
                    </Button>
                </form>
            </div>
        </AuthLayout>
    );
}
