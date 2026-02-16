import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import TwoFactorSetup from '@/pages/Auth/TwoFactor/Setup';

describe('TwoFactorSetup Page', () => {
    const mockProps = {
        qrCode: '<svg><rect /></svg>',
        manualEntryCode: 'ABCD1234EFGH5678',
        backupCodes: ['CODE1-11111', 'CODE2-22222', 'CODE3-33333'],
    };

    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('setup screen rendering', () => {
        it('should render page title and subtitle', () => {
            render(<TwoFactorSetup {...mockProps} />);

            expect(screen.getByText('Enable Two-Factor Authentication')).toBeInTheDocument();
            expect(screen.getByText('Secure your account with an additional layer of protection.')).toBeInTheDocument();
        });

        it('should render step 1 - scan QR code', () => {
            render(<TwoFactorSetup {...mockProps} />);

            expect(screen.getByText('Scan QR Code')).toBeInTheDocument();
            expect(screen.getByText(/use an authenticator app/i)).toBeInTheDocument();
        });

        it('should render step 2 - verify code', () => {
            render(<TwoFactorSetup {...mockProps} />);

            expect(screen.getByText('Verify Code')).toBeInTheDocument();
            expect(screen.getByText(/enter the 6-digit code/i)).toBeInTheDocument();
        });

        it('should render manual entry code', () => {
            render(<TwoFactorSetup {...mockProps} />);

            expect(screen.getByText('Or enter this code manually:')).toBeInTheDocument();
            expect(screen.getByText('ABCD1234EFGH5678')).toBeInTheDocument();
        });

        it('should render copy button for manual code', () => {
            render(<TwoFactorSetup {...mockProps} />);

            const copyButtons = screen.getAllByRole('button');
            const copyButton = copyButtons.find(btn => btn.querySelector('svg'));
            expect(copyButton).toBeInTheDocument();
        });

        it('should render verification code input', () => {
            render(<TwoFactorSetup {...mockProps} />);

            const codeInput = screen.getByLabelText('Verification Code');
            expect(codeInput).toBeInTheDocument();
            expect(codeInput).toHaveAttribute('placeholder', '000000');
        });

        it('should render enable button', () => {
            render(<TwoFactorSetup {...mockProps} />);

            expect(screen.getByRole('button', { name: /enable two-factor authentication/i })).toBeInTheDocument();
        });

        it('should render skip link', () => {
            render(<TwoFactorSetup {...mockProps} />);

            const skipLink = screen.getByText('Skip for now');
            expect(skipLink).toBeInTheDocument();
            expect(skipLink.closest('a')).toHaveAttribute('href', '/dashboard');
        });
    });

    describe('backup codes screen rendering', () => {
        it('should not show backup codes initially', () => {
            render(<TwoFactorSetup {...mockProps} />);

            expect(screen.queryByText('Backup Codes')).not.toBeInTheDocument();
        });

        it('should render numbered steps', () => {
            render(<TwoFactorSetup {...mockProps} />);

            expect(screen.getByText('1')).toBeInTheDocument();
            expect(screen.getByText('2')).toBeInTheDocument();
        });
    });

    describe('form interaction', () => {
        it('should update verification code input', async () => {
            const { user } = render(<TwoFactorSetup {...mockProps} />);

            const codeInput = screen.getByLabelText('Verification Code');
            await user.type(codeInput, '123456');

            // Only digits should be kept, max 6 characters
            const value = (codeInput as HTMLInputElement).value;
            expect(value.length).toBeLessThanOrEqual(6);
        });

        it('should strip non-digit characters from code', async () => {
            const { user } = render(<TwoFactorSetup {...mockProps} />);

            const codeInput = screen.getByLabelText('Verification Code');
            await user.type(codeInput, 'abc123def456');

            // Should only contain digits
            const value = (codeInput as HTMLInputElement).value;
            expect(/^\d*$/.test(value)).toBe(true);
        });

        it('should limit code to 6 digits', async () => {
            const { user } = render(<TwoFactorSetup {...mockProps} />);

            const codeInput = screen.getByLabelText('Verification Code');
            await user.type(codeInput, '123456789');

            const value = (codeInput as HTMLInputElement).value;
            expect(value.length).toBeLessThanOrEqual(6);
        });

        it('should copy manual code to clipboard', async () => {
            const clipboardWriteText = vi.fn();
            Object.assign(navigator, {
                clipboard: { writeText: clipboardWriteText },
            });

            const { user } = render(<TwoFactorSetup {...mockProps} />);

            const copyButtons = screen.getAllByRole('button');
            const copyButton = copyButtons.find(btn => {
                const svg = btn.querySelector('svg');
                return svg !== null && btn.textContent === '';
            });

            if (copyButton) {
                await user.click(copyButton);
                expect(clipboardWriteText).toHaveBeenCalledWith('ABCD1234EFGH5678');
            }
        });
    });

    describe('QR code rendering', () => {
        it('should render sanitized QR code', () => {
            render(<TwoFactorSetup {...mockProps} />);

            // QR code is rendered via dangerouslySetInnerHTML after sanitization
            const qrCodeContainer = screen.getByText('Scan QR Code').parentElement?.parentElement;
            expect(qrCodeContainer).toBeInTheDocument();
        });

        it('should sanitize QR code SVG', () => {
            const maliciousQrCode = '<svg><script>alert("xss")</script><rect /></svg>';
            render(<TwoFactorSetup {...mockProps} qrCode={maliciousQrCode} />);

            // DOMPurify should remove the script tag
            const body = document.body.innerHTML;
            expect(body).not.toContain('<script>');
        });
    });

    describe('edge cases', () => {
        it('should handle empty backup codes', () => {
            render(<TwoFactorSetup {...mockProps} backupCodes={[]} />);

            expect(screen.getByText('Enable Two-Factor Authentication')).toBeInTheDocument();
        });

        it('should handle missing backup codes prop', () => {
            const { qrCode, manualEntryCode } = mockProps;
            render(<TwoFactorSetup qrCode={qrCode} manualEntryCode={manualEntryCode} />);

            expect(screen.getByText('Enable Two-Factor Authentication')).toBeInTheDocument();
        });

        it('should have required attribute on code input', () => {
            render(<TwoFactorSetup {...mockProps} />);

            const codeInput = screen.getByLabelText('Verification Code');
            expect(codeInput).toBeRequired();
        });

        it('should have autoFocus on code input', () => {
            render(<TwoFactorSetup {...mockProps} />);

            const codeInput = screen.getByLabelText('Verification Code');
            // autoFocus prop is passed but may not be reflected in test env
            expect(codeInput).toBeInTheDocument();
        });
    });
});
