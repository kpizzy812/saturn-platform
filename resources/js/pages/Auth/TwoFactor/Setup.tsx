import { useState } from 'react';
import { useForm, Link } from '@inertiajs/react';
import { AuthLayout } from '@/components/layout';
import { Input, Button, Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui';
import { Shield, Copy, Check } from 'lucide-react';

interface Props {
    qrCode: string;
    manualEntryCode: string;
}

export default function Setup({ qrCode, manualEntryCode }: Props) {
    const [showBackupCodes, setShowBackupCodes] = useState(false);
    const [copiedCode, setCopiedCode] = useState(false);
    const [copiedBackup, setCopiedBackup] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        code: '',
    });

    const backupCodes = [
        'ABC123-DEF456',
        'GHI789-JKL012',
        'MNO345-PQR678',
        'STU901-VWX234',
        'YZA567-BCD890',
        'EFG123-HIJ456',
        'KLM789-NOP012',
        'QRS345-TUV678',
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/user/two-factor-authentication/enable', {
            onSuccess: () => setShowBackupCodes(true),
        });
    };

    const handleCopyCode = () => {
        navigator.clipboard.writeText(manualEntryCode);
        setCopiedCode(true);
        setTimeout(() => setCopiedCode(false), 2000);
    };

    const handleCopyBackupCodes = () => {
        navigator.clipboard.writeText(backupCodes.join('\n'));
        setCopiedBackup(true);
        setTimeout(() => setCopiedBackup(false), 2000);
    };

    if (showBackupCodes) {
        return (
            <AuthLayout
                title="Backup Codes"
                subtitle="Save these codes in a safe place. You can use them to access your account if you lose your device."
            >
                <div className="space-y-6">
                    <div className="flex items-center justify-center text-primary">
                        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                            <Shield className="h-8 w-8" />
                        </div>
                    </div>

                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <p className="text-sm font-medium text-foreground">
                                Your backup codes
                            </p>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={handleCopyBackupCodes}
                            >
                                {copiedBackup ? (
                                    <>
                                        <Check className="mr-2 h-4 w-4" />
                                        Copied
                                    </>
                                ) : (
                                    <>
                                        <Copy className="mr-2 h-4 w-4" />
                                        Copy All
                                    </>
                                )}
                            </Button>
                        </div>

                        <div className="grid grid-cols-2 gap-3 rounded-lg bg-background p-4 font-mono text-sm">
                            {backupCodes.map((code, index) => (
                                <div key={index} className="text-foreground-muted">
                                    {code}
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-lg border border-yellow-500/20 bg-yellow-500/10 p-4">
                        <p className="text-sm text-yellow-600 dark:text-yellow-500">
                            Store these codes securely. Each code can only be used once.
                        </p>
                    </div>

                    <Link href="/dashboard">
                        <Button type="button" className="w-full">
                            Continue to Dashboard
                        </Button>
                    </Link>
                </div>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout
            title="Enable Two-Factor Authentication"
            subtitle="Secure your account with an additional layer of protection."
        >
            <div className="space-y-6">
                {/* Step 1: Scan QR Code */}
                <Card className="border-0 bg-background p-0">
                    <CardHeader>
                        <div className="flex items-center gap-3">
                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                                1
                            </div>
                            <div>
                                <CardTitle className="text-base">Scan QR Code</CardTitle>
                                <CardDescription>
                                    Use an authenticator app like Google Authenticator or Authy
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="flex justify-center">
                            <div
                                className="rounded-lg bg-white p-4"
                                dangerouslySetInnerHTML={{ __html: qrCode }}
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* Manual Entry Code */}
                <div className="space-y-2">
                    <p className="text-sm font-medium text-foreground-muted">
                        Or enter this code manually:
                    </p>
                    <div className="flex items-center gap-2">
                        <code className="flex-1 rounded-lg border border-border bg-background-secondary px-4 py-2 font-mono text-sm text-foreground">
                            {manualEntryCode}
                        </code>
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            onClick={handleCopyCode}
                        >
                            {copiedCode ? (
                                <Check className="h-4 w-4" />
                            ) : (
                                <Copy className="h-4 w-4" />
                            )}
                        </Button>
                    </div>
                </div>

                {/* Step 2: Verify Code */}
                <Card className="border-0 bg-background p-0">
                    <CardHeader>
                        <div className="flex items-center gap-3">
                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                                2
                            </div>
                            <div>
                                <CardTitle className="text-base">Verify Code</CardTitle>
                                <CardDescription>
                                    Enter the 6-digit code from your authenticator app
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <Input
                                label="Verification Code"
                                type="text"
                                placeholder="000000"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))}
                                error={errors.code}
                                required
                                autoFocus
                                className="text-center text-lg font-mono tracking-widest"
                            />

                            <Button type="submit" className="w-full" loading={processing}>
                                <Shield className="mr-2 h-4 w-4" />
                                Enable Two-Factor Authentication
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                {/* Cancel Link */}
                <div className="text-center">
                    <Link
                        href="/dashboard"
                        className="text-sm text-foreground-muted hover:text-foreground"
                    >
                        Skip for now
                    </Link>
                </div>
            </div>
        </AuthLayout>
    );
}
