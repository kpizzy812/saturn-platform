import { useState, useRef, useEffect } from 'react';
import { useForm, Link } from '@inertiajs/react';
import { AuthLayout } from '@/components/layout';
import { Button, Checkbox } from '@/components/ui';
import { Shield, KeyRound } from 'lucide-react';

export default function Verify() {
    const [code, setCode] = useState<string[]>(['', '', '', '', '', '']);
    const [useBackup, setUseBackup] = useState(false);
    const inputRefs = useRef<(HTMLInputElement | null)[]>([]);

    const { data, setData, post, processing, errors } = useForm({
        code: '',
        recovery_code: '',
        remember: false,
    });

    useEffect(() => {
        // Focus first input on mount
        if (!useBackup && inputRefs.current[0]) {
            inputRefs.current[0].focus();
        }
    }, [useBackup]);

    const handleCodeChange = (index: number, value: string) => {
        // Only allow digits
        if (!/^\d*$/.test(value)) return;

        const newCode = [...code];
        newCode[index] = value.slice(-1); // Only take last character
        setCode(newCode);

        // Auto-focus next input
        if (value && index < 5 && inputRefs.current[index + 1]) {
            inputRefs.current[index + 1]?.focus();
        }

        // Update form data
        setData('code', newCode.join(''));
    };

    const handleKeyDown = (index: number, e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Backspace' && !code[index] && index > 0) {
            // Move to previous input on backspace if current is empty
            inputRefs.current[index - 1]?.focus();
        } else if (e.key === 'ArrowLeft' && index > 0) {
            inputRefs.current[index - 1]?.focus();
        } else if (e.key === 'ArrowRight' && index < 5) {
            inputRefs.current[index + 1]?.focus();
        }
    };

    const handlePaste = (e: React.ClipboardEvent) => {
        e.preventDefault();
        const pastedData = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
        const newCode = pastedData.split('');

        // Pad with empty strings if needed
        while (newCode.length < 6) {
            newCode.push('');
        }

        setCode(newCode);
        setData('code', pastedData);

        // Focus last filled input or first empty
        const lastFilledIndex = Math.min(pastedData.length, 5);
        inputRefs.current[lastFilledIndex]?.focus();
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/two-factor-challenge');
    };

    const handleBackupSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/two-factor-challenge');
    };

    return (
        <AuthLayout
            title="Two-Factor Authentication"
            subtitle="Enter the verification code from your authenticator app."
        >
            <div className="space-y-6">
                {/* Icon */}
                <div className="flex justify-center">
                    <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                        {useBackup ? (
                            <KeyRound className="h-8 w-8 text-primary" />
                        ) : (
                            <Shield className="h-8 w-8 text-primary" />
                        )}
                    </div>
                </div>

                {useBackup ? (
                    // Backup Code Form
                    <form onSubmit={handleBackupSubmit} className="space-y-4">
                        <div className="space-y-2">
                            <label className="text-sm font-medium text-foreground">
                                Backup Code
                            </label>
                            <input
                                type="text"
                                placeholder="ABC123-DEF456"
                                value={data.recovery_code}
                                onChange={(e) => setData('recovery_code', e.target.value.toUpperCase())}
                                className="w-full rounded-lg border border-border bg-background px-4 py-2 font-mono text-center text-lg tracking-wide text-foreground placeholder:text-foreground-muted focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                required
                                autoFocus
                            />
                            {errors.recovery_code && (
                                <p className="text-sm text-red-500">{errors.recovery_code}</p>
                            )}
                        </div>

                        <Button type="submit" className="w-full" loading={processing}>
                            Verify Backup Code
                        </Button>

                        <button
                            type="button"
                            onClick={() => setUseBackup(false)}
                            className="w-full text-sm text-primary hover:underline"
                        >
                            Use authenticator code instead
                        </button>
                    </form>
                ) : (
                    // Regular Code Form
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Code Input Boxes */}
                        <div className="space-y-2">
                            <label className="text-sm font-medium text-foreground">
                                Verification Code
                            </label>
                            <div className="flex justify-center gap-2">
                                {code.map((digit, index) => (
                                    <input
                                        key={index}
                                        ref={(el) => { inputRefs.current[index] = el; }}
                                        type="text"
                                        inputMode="numeric"
                                        maxLength={1}
                                        value={digit}
                                        onChange={(e) => handleCodeChange(index, e.target.value)}
                                        onKeyDown={(e) => handleKeyDown(index, e)}
                                        onPaste={handlePaste}
                                        className="h-14 w-12 rounded-lg border border-border bg-background text-center text-2xl font-semibold text-foreground transition-all focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                        required
                                    />
                                ))}
                            </div>
                            {errors.code && (
                                <p className="text-center text-sm text-red-500">{errors.code}</p>
                            )}
                        </div>

                        <div className="flex items-center justify-between">
                            <Checkbox
                                label="Trust this device"
                                checked={data.remember}
                                onChange={(e) => setData('remember', e.target.checked)}
                            />
                        </div>

                        <Button type="submit" className="w-full" loading={processing}>
                            Verify Code
                        </Button>

                        <button
                            type="button"
                            onClick={() => setUseBackup(true)}
                            className="w-full text-sm text-primary hover:underline"
                        >
                            Use backup code instead
                        </button>
                    </form>
                )}

                {/* Back to Login */}
                <div className="text-center">
                    <Link
                        href="/login"
                        className="text-sm text-foreground-muted hover:text-foreground"
                    >
                        Back to login
                    </Link>
                </div>
            </div>
        </AuthLayout>
    );
}
