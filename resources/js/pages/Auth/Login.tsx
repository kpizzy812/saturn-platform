import { useForm, Link } from '@inertiajs/react';
import { AuthLayout } from '@/components/layout';
import { Input, Button, Checkbox } from '@/components/ui';
import { Github, Mail } from 'lucide-react';

interface Props {
    canResetPassword: boolean;
    status?: string;
}

export default function Login({ canResetPassword, status }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <AuthLayout title="Sign In" subtitle="Welcome back! Sign in to your account.">
            {status && (
                <div className="mb-4 rounded-md bg-primary/10 p-3 text-sm text-primary">
                    {status}
                </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-4">
                <Input
                    label="Email"
                    type="email"
                    placeholder="you@example.com"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                    required
                    autoFocus
                />

                <Input
                    label="Password"
                    type="password"
                    placeholder="••••••••"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                    required
                />

                <div className="flex items-center justify-between">
                    <Checkbox
                        label="Remember me"
                        checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                    />
                    {canResetPassword && (
                        <Link
                            href="/forgot-password"
                            className="text-sm text-primary hover:underline"
                        >
                            Forgot password?
                        </Link>
                    )}
                </div>

                <Button type="submit" className="w-full" loading={processing}>
                    Sign In
                </Button>

                {/* Divider */}
                <div className="relative my-6">
                    <div className="absolute inset-0 flex items-center">
                        <div className="w-full border-t border-border" />
                    </div>
                    <div className="relative flex justify-center text-xs uppercase">
                        <span className="bg-background-secondary px-2 text-foreground-muted">
                            Or continue with
                        </span>
                    </div>
                </div>

                {/* Social Login */}
                <div className="grid grid-cols-2 gap-3">
                    <Button type="button" variant="secondary">
                        <Github className="mr-2 h-4 w-4" />
                        GitHub
                    </Button>
                    <Button type="button" variant="secondary">
                        <Mail className="mr-2 h-4 w-4" />
                        Google
                    </Button>
                </div>
            </form>

            {/* Register Link */}
            <p className="mt-6 text-center text-sm text-foreground-muted">
                Don't have an account?{' '}
                <Link href="/register" className="text-primary hover:underline">
                    Sign up
                </Link>
            </p>
        </AuthLayout>
    );
}
