import { useForm, Link } from '@inertiajs/react';
import { AuthLayout } from '@/components/layout';
import { Input, Button } from '@/components/ui';
import { ArrowLeft } from 'lucide-react';

interface Props {
    status?: string;
}

export default function ForgotPassword({ status }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/forgot-password');
    };

    return (
        <AuthLayout
            title="Forgot Password"
            subtitle="Enter your email and we'll send you a reset link."
        >
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

                <Button type="submit" className="w-full" loading={processing}>
                    Send Reset Link
                </Button>
            </form>

            <Link
                href="/login"
                className="mt-6 flex items-center justify-center text-sm text-foreground-muted hover:text-foreground"
            >
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back to login
            </Link>
        </AuthLayout>
    );
}
