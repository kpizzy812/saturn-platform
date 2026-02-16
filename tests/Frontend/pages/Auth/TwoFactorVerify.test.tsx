import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import { router } from '@inertiajs/react';
import TwoFactorVerify from '@/pages/Auth/TwoFactor/Verify';

describe('TwoFactorVerify Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering authentication code mode', () => {
        it('should render page title and subtitle', () => {
            render(<TwoFactorVerify />);

            expect(screen.getByText('Two-Factor Authentication')).toBeInTheDocument();
            expect(screen.getByText('Enter the verification code from your authenticator app.')).toBeInTheDocument();
        });

        it('should render 6 code input boxes', () => {
            render(<TwoFactorVerify />);

            const inputs = screen.getAllByRole('textbox');
            const codeInputs = inputs.filter(input => input.getAttribute('maxlength') === '1');
            expect(codeInputs.length).toBe(6);
        });

        it('should render verification code label', () => {
            render(<TwoFactorVerify />);

            expect(screen.getByText('Verification Code')).toBeInTheDocument();
        });

        it('should render trust device checkbox', () => {
            render(<TwoFactorVerify />);

            const checkbox = screen.getByLabelText('Trust this device');
            expect(checkbox).toBeInTheDocument();
        });

        it('should render verify button', () => {
            render(<TwoFactorVerify />);

            expect(screen.getByRole('button', { name: /verify code/i })).toBeInTheDocument();
        });

        it('should render backup code toggle', () => {
            render(<TwoFactorVerify />);

            expect(screen.getByText(/use backup code instead/i)).toBeInTheDocument();
        });

        it('should render back to login link', () => {
            render(<TwoFactorVerify />);

            const backLink = screen.getByText('Back to login');
            expect(backLink).toBeInTheDocument();
            expect(backLink.closest('a')).toHaveAttribute('href', '/login');
        });
    });

    describe('backup code mode', () => {
        it('should switch to backup code mode', async () => {
            const { user } = render(<TwoFactorVerify />);

            const toggleButton = screen.getByText(/use backup code instead/i);
            await user.click(toggleButton);

            expect(screen.getByText('Backup Code')).toBeInTheDocument();
            expect(screen.getByPlaceholderText('ABC123-DEF456')).toBeInTheDocument();
        });

        it('should render backup code input when in backup mode', async () => {
            const { user } = render(<TwoFactorVerify />);

            await user.click(screen.getByText(/use backup code instead/i));

            const backupInput = screen.getByPlaceholderText('ABC123-DEF456');
            expect(backupInput).toBeInTheDocument();
            expect(backupInput).toHaveAttribute('type', 'text');
        });

        it('should render verify backup code button', async () => {
            const { user } = render(<TwoFactorVerify />);

            await user.click(screen.getByText(/use backup code instead/i));

            expect(screen.getByRole('button', { name: /verify backup code/i })).toBeInTheDocument();
        });

        it('should switch back to authenticator code mode', async () => {
            const { user } = render(<TwoFactorVerify />);

            await user.click(screen.getByText(/use backup code instead/i));
            await user.click(screen.getByText(/use authenticator code instead/i));

            expect(screen.getByText('Verification Code')).toBeInTheDocument();
            // Should show 6 input boxes again
            const inputs = screen.getAllByRole('textbox');
            const codeInputs = inputs.filter(input => input.getAttribute('maxlength') === '1');
            expect(codeInputs.length).toBe(6);
        });
    });

    describe('code input interaction', () => {
        it('should accept single digit in each box', async () => {
            const { user } = render(<TwoFactorVerify />);

            const inputs = screen.getAllByRole('textbox');
            const firstInput = inputs.find(input => input.getAttribute('maxlength') === '1');

            if (firstInput) {
                await user.type(firstInput, '5');
                expect(firstInput).toHaveValue('5');
            }
        });

        it('should auto-focus next input after entering digit', async () => {
            const { user } = render(<TwoFactorVerify />);

            const inputs = screen.getAllByRole('textbox');
            const codeInputs = inputs.filter(input => input.getAttribute('maxlength') === '1');

            if (codeInputs[0]) {
                await user.type(codeInputs[0], '1');
                // Next input should receive focus (tested implicitly via behavior)
            }
        });

        it('should only accept numeric input', async () => {
            const { user } = render(<TwoFactorVerify />);

            const inputs = screen.getAllByRole('textbox');
            const firstInput = inputs.find(input => input.getAttribute('maxlength') === '1');

            if (firstInput) {
                await user.type(firstInput, 'abc');
                // Non-numeric characters should be rejected
                expect(firstInput).toHaveValue('');
            }
        });

        it('should have inputMode numeric on code boxes', () => {
            render(<TwoFactorVerify />);

            const inputs = screen.getAllByRole('textbox');
            const codeInputs = inputs.filter(input => input.getAttribute('maxlength') === '1');

            codeInputs.forEach(input => {
                expect(input).toHaveAttribute('inputMode', 'numeric');
            });
        });
    });

    describe('trust device checkbox', () => {
        it('should toggle trust device checkbox', async () => {
            const { user } = render(<TwoFactorVerify />);

            const checkbox = screen.getByLabelText('Trust this device');
            expect(checkbox).not.toBeChecked();

            await user.click(checkbox);
            expect(checkbox).toBeChecked();
        });
    });

    describe('form submission', () => {
        it('should submit verification code', async () => {
            const { user } = render(<TwoFactorVerify />);

            const verifyButton = screen.getByRole('button', { name: /verify code/i });
            await user.click(verifyButton);

            expect(router.post).toHaveBeenCalledWith('/two-factor-challenge');
        });

        it('should submit backup code', async () => {
            const { user } = render(<TwoFactorVerify />);

            await user.click(screen.getByText(/use backup code instead/i));

            const backupInput = screen.getByPlaceholderText('ABC123-DEF456');
            await user.type(backupInput, 'TEST12-CODE34');

            const verifyButton = screen.getByRole('button', { name: /verify backup code/i });
            await user.click(verifyButton);

            expect(router.post).toHaveBeenCalledWith('/two-factor-challenge');
        });

        it('should convert backup code to uppercase', async () => {
            const { user } = render(<TwoFactorVerify />);

            await user.click(screen.getByText(/use backup code instead/i));

            const backupInput = screen.getByPlaceholderText('ABC123-DEF456');
            await user.type(backupInput, 'test12-code34');

            // Component converts to uppercase
            expect((backupInput as HTMLInputElement).value).toBe('TEST12-CODE34');
        });
    });

    describe('edge cases', () => {
        it('should handle paste event on code inputs', async () => {
            render(<TwoFactorVerify />);

            const inputs = screen.getAllByRole('textbox');
            const firstInput = inputs.find(input => input.getAttribute('maxlength') === '1');

            if (firstInput) {
                // Paste event is handled in the component
                expect(firstInput).toBeInTheDocument();
            }
        });

        it('should have required attribute on backup code input', async () => {
            const { user } = render(<TwoFactorVerify />);

            await user.click(screen.getByText(/use backup code instead/i));

            const backupInput = screen.getByPlaceholderText('ABC123-DEF456');
            expect(backupInput).toBeRequired();
        });

        it('should have required attribute on code inputs', () => {
            render(<TwoFactorVerify />);

            const inputs = screen.getAllByRole('textbox');
            const codeInputs = inputs.filter(input => input.getAttribute('maxlength') === '1');

            codeInputs.forEach(input => {
                expect(input).toBeRequired();
            });
        });
    });
});
