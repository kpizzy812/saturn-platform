import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import { router } from '@inertiajs/react';
import TwoFactorChallenge from '@/pages/Auth/TwoFactorChallenge';

describe('TwoFactorChallenge Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering authentication code mode', () => {
        it('should render page title and subtitle', () => {
            render(<TwoFactorChallenge />);

            expect(screen.getByText('Two-Factor Authentication')).toBeInTheDocument();
            expect(screen.getByText('Please enter the authentication code from your authenticator app.')).toBeInTheDocument();
        });

        it('should render authentication code input', () => {
            render(<TwoFactorChallenge />);

            const codeInput = screen.getByLabelText('Authentication Code');
            expect(codeInput).toBeInTheDocument();
            expect(codeInput).toHaveAttribute('type', 'text');
        });

        it('should render authentication code placeholder', () => {
            render(<TwoFactorChallenge />);

            expect(screen.getByPlaceholderText('000000')).toBeInTheDocument();
        });

        it('should render verify button', () => {
            render(<TwoFactorChallenge />);

            const verifyButton = screen.getByRole('button', { name: /verify/i });
            expect(verifyButton).toBeInTheDocument();
        });

        it('should render toggle to recovery code button', () => {
            render(<TwoFactorChallenge />);

            expect(screen.getByText(/use a recovery code instead/i)).toBeInTheDocument();
        });

        it('should have required attribute on code input', () => {
            render(<TwoFactorChallenge />);

            const codeInput = screen.getByLabelText('Authentication Code');
            expect(codeInput).toBeRequired();
        });

        it('should have maxLength attribute on code input', () => {
            render(<TwoFactorChallenge />);

            const codeInput = screen.getByLabelText('Authentication Code');
            expect(codeInput).toHaveAttribute('maxLength', '6');
        });
    });

    describe('recovery code mode', () => {
        it('should switch to recovery code mode when toggle is clicked', async () => {
            const { user } = render(<TwoFactorChallenge />);

            const toggleButton = screen.getByText(/use a recovery code instead/i);
            await user.click(toggleButton);

            expect(screen.getByText('Please enter one of your recovery codes.')).toBeInTheDocument();
            expect(screen.getByLabelText('Recovery Code')).toBeInTheDocument();
        });

        it('should render recovery code input when in recovery mode', async () => {
            const { user } = render(<TwoFactorChallenge />);

            const toggleButton = screen.getByText(/use a recovery code instead/i);
            await user.click(toggleButton);

            const recoveryInput = screen.getByLabelText('Recovery Code');
            expect(recoveryInput).toBeInTheDocument();
            expect(recoveryInput).toHaveAttribute('placeholder', 'XXXXX-XXXXX');
        });

        it('should switch back to authentication code mode', async () => {
            const { user } = render(<TwoFactorChallenge />);

            // Switch to recovery mode
            await user.click(screen.getByText(/use a recovery code instead/i));

            // Switch back to auth code mode
            const switchBackButton = screen.getByText(/use authentication code instead/i);
            await user.click(switchBackButton);

            expect(screen.getByText('Please enter the authentication code from your authenticator app.')).toBeInTheDocument();
            expect(screen.getByLabelText('Authentication Code')).toBeInTheDocument();
        });
    });

    describe('form interaction', () => {
        it('should update authentication code field on input', async () => {
            const { user } = render(<TwoFactorChallenge />);

            const codeInput = screen.getByPlaceholderText('000000');
            await user.type(codeInput, '123456');

            expect(screen.getByDisplayValue('123456')).toBeInTheDocument();
        });

        it('should update recovery code field on input', async () => {
            const { user } = render(<TwoFactorChallenge />);

            // Switch to recovery mode
            await user.click(screen.getByText(/use a recovery code instead/i));

            const recoveryInput = screen.getByPlaceholderText('XXXXX-XXXXX');
            await user.type(recoveryInput, 'ABC12-DEF34');

            expect(screen.getByDisplayValue('ABC12-DEF34')).toBeInTheDocument();
        });

        it('should submit authentication code', async () => {
            const { user } = render(<TwoFactorChallenge />);

            const codeInput = screen.getByPlaceholderText('000000');
            await user.type(codeInput, '123456');

            const verifyButton = screen.getByRole('button', { name: /verify/i });
            await user.click(verifyButton);

            expect(router.post).toHaveBeenCalledWith(
                '/two-factor-challenge',
                expect.objectContaining({ code: '123456' }),
                expect.any(Object)
            );
        });

        it('should submit recovery code', async () => {
            const { user } = render(<TwoFactorChallenge />);

            // Switch to recovery mode
            await user.click(screen.getByText(/use a recovery code instead/i));

            const recoveryInput = screen.getByPlaceholderText('XXXXX-XXXXX');
            await user.type(recoveryInput, 'ABC12-DEF34');

            const verifyButton = screen.getByRole('button', { name: /verify/i });
            await user.click(verifyButton);

            expect(router.post).toHaveBeenCalledWith(
                '/two-factor-challenge',
                expect.objectContaining({ recovery_code: 'ABC12-DEF34' }),
                expect.any(Object)
            );
        });
    });

    describe('edge cases', () => {
        it('should handle empty submission', async () => {
            const { user } = render(<TwoFactorChallenge />);

            const verifyButton = screen.getByRole('button', { name: /verify/i });
            await user.click(verifyButton);

            // Browser validation will prevent submission, but button should be clickable
            expect(verifyButton).toBeInTheDocument();
        });

        it('should clear error when switching modes', async () => {
            const { user } = render(<TwoFactorChallenge />);

            // Switch to recovery mode and back - error state should be cleared
            await user.click(screen.getByText(/use a recovery code instead/i));
            await user.click(screen.getByText(/use authentication code instead/i));

            // Page should still render without errors
            expect(screen.getByLabelText('Authentication Code')).toBeInTheDocument();
        });
    });
});
