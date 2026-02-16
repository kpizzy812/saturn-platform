import { useForm, Link } from '@inertiajs/react';
import { AuthLayout } from '@/components/layout';
import { Input, Button } from '@/components/ui';
import { Github, Mail } from 'lucide-react';

export default function Register() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/register');
    };

    return (
        <AuthLayout title="Create Account" subtitle="Start deploying your applications for free.">
            <form onSubmit={handleSubmit} className="space-y-4">
                <Input
                    label="Name"
                    type="text"
                    placeholder="John Doe"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    error={errors.name}
                    required
                    autoFocus
                />

                <Input
                    label="Email"
                    type="email"
                    placeholder="you@example.com"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                    required
                />

                <Input
                    label="Password"
                    type="password"
                    placeholder="••••••••"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                    hint="Must be at least 8 characters"
                    required
                />

                <Input
                    label="Confirm Password"
                    type="password"
                    placeholder="••••••••"
                    value={data.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                    error={errors.password_confirmation}
                    required
                />

                <Button type="submit" className="w-full" loading={processing}>
                    Create Account
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

            {/* Login Link */}
            <p className="mt-4 text-center text-sm text-foreground-muted">
                Already have an account?{' '}
                <Link href="/login" className="text-primary hover:underline">
                    Sign in
                </Link>
            </p>
        </AuthLayout>
    );
}
