import { useState } from 'react';
import { useForm, Link } from '@inertiajs/react';
import { AuthLayout } from '@/components/layout';
import { Button } from '@/components/ui';
import { Mail, CheckCircle2, Clock } from 'lucide-react';

interface Props {
    status?: 'pending' | 'sent';
    email?: string;
}

export default function VerifyEmail({ status = 'pending', email }: Props) {
    const [resendStatus, setResendStatus] = useState(status);
    const { post, processing } = useForm();

    const handleResend = () => {
        post('/email/verification-notification', {
            onSuccess: () => setResendStatus('sent'),
        });
    };

    return (
        <AuthLayout
            title="Verify Your Email"
            subtitle="We've sent a verification link to your email address."
        >
            <div className="space-y-6">
                {/* Status Icon */}
                <div className="flex justify-center">
                    <div className="flex h-20 w-20 items-center justify-center rounded-full bg-primary/10">
                        {resendStatus === 'sent' ? (
                            <CheckCircle2 className="h-10 w-10 text-primary" />
                        ) : (
                            <Mail className="h-10 w-10 text-primary" />
                        )}
                    </div>
                </div>

                {/* Email Display */}
                {email && (
                    <div className="space-y-2 text-center">
                        <p className="text-sm text-foreground-muted">Email sent to:</p>
                        <p className="font-mono text-lg font-semibold text-foreground">
                            {email}
                        </p>
                    </div>
                )}

                {/* Success Message */}
                {resendStatus === 'sent' && (
                    <div className="rounded-lg border border-green-500/20 bg-green-500/10 p-4">
                        <div className="flex items-center gap-3">
                            <CheckCircle2 className="h-5 w-5 flex-shrink-0 text-green-600 dark:text-green-500" />
                            <p className="text-sm text-green-600 dark:text-green-500">
                                Verification email sent successfully! Please check your inbox.
                            </p>
                        </div>
                    </div>
                )}

                {/* Instructions */}
                <div className="space-y-3 rounded-lg bg-background p-4">
                    <h3 className="font-semibold text-foreground">Check your inbox</h3>
                    <ul className="space-y-2 text-sm text-foreground-muted">
                        <li className="flex items-start gap-2">
                            <span className="mt-0.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary" />
                            Click the verification link in the email we sent you
                        </li>
                        <li className="flex items-start gap-2">
                            <span className="mt-0.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary" />
                            The link will expire in 24 hours
                        </li>
                        <li className="flex items-start gap-2">
                            <span className="mt-0.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-primary" />
                            Check your spam folder if you don't see it
                        </li>
                    </ul>
                </div>

                {/* Resend Button */}
                <div className="space-y-3">
                    <Button
                        type="button"
                        variant="secondary"
                        className="w-full"
                        onClick={handleResend}
                        loading={processing}
                        disabled={resendStatus === 'sent'}
                    >
                        {resendStatus === 'sent' ? (
                            <>
                                <CheckCircle2 className="mr-2 h-4 w-4" />
                                Email Sent
                            </>
                        ) : (
                            <>
                                <Mail className="mr-2 h-4 w-4" />
                                Resend Verification Email
                            </>
                        )}
                    </Button>

                    {resendStatus === 'sent' && (
                        <p className="flex items-center justify-center gap-2 text-xs text-foreground-muted">
                            <Clock className="h-3 w-3" />
                            Please wait a few minutes before requesting another email
                        </p>
                    )}
                </div>

                {/* Divider */}
                <div className="relative">
                    <div className="absolute inset-0 flex items-center">
                        <div className="w-full border-t border-border" />
                    </div>
                    <div className="relative flex justify-center text-xs uppercase">
                        <span className="bg-background-secondary px-2 text-foreground-muted">
                            Or
                        </span>
                    </div>
                </div>

                {/* Actions */}
                <div className="space-y-2">
                    <Link href="/dashboard">
                        <Button type="button" variant="outline" className="w-full">
                            Continue to Dashboard
                        </Button>
                    </Link>
                    <Link href="/login">
                        <Button type="button" variant="ghost" className="w-full">
                            Back to Login
                        </Button>
                    </Link>
                </div>

                {/* Support Info */}
                <div className="text-center">
                    <p className="text-xs text-foreground-subtle">
                        Having trouble?{' '}
                        <Link href="/support" className="text-primary hover:underline">
                            Contact support
                        </Link>
                    </p>
                </div>
            </div>
        </AuthLayout>
    );
}
