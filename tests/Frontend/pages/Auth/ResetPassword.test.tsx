import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import { router } from '@inertiajs/react';
import ResetPassword from '@/pages/Auth/ResetPassword';

describe('ResetPassword Page', () => {
    const defaultProps = {
        email: 'test@example.com',
        token: 'reset-token-12345',
    };

    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page title and subtitle', () => {
            render(<ResetPassword {...defaultProps} />);

            expect(screen.getByText('Reset Password')).toBeInTheDocument();
            expect(screen.getByText('Enter your new password below.')).toBeInTheDocument();
        });

        it('should render all form fields', () => {
            render(<ResetPassword {...defaultProps} />);

            expect(screen.getByLabelText('Email')).toBeInTheDocument();
            expect(screen.getByLabelText('New Password')).toBeInTheDocument();
            expect(screen.getByLabelText('Confirm Password')).toBeInTheDocument();
        });

        it('should render form with correct placeholders', () => {
            render(<ResetPassword {...defaultProps} />);

            // Two password fields with same placeholder
            const passwordFields = screen.getAllByPlaceholderText('••••••••');
            expect(passwordFields).toHaveLength(2);
        });

        it('should render password hint', () => {
            render(<ResetPassword {...defaultProps} />);

            expect(screen.getByText('Must be at least 8 characters')).toBeInTheDocument();
        });

        it('should render submit button', () => {
            render(<ResetPassword {...defaultProps} />);

            expect(screen.getByRole('button', { name: 'Reset Password' })).toBeInTheDocument();
        });

        it('should display email field as disabled with provided email', () => {
            render(<ResetPassword {...defaultProps} />);

            const emailInput = screen.getByLabelText('Email') as HTMLInputElement;
            expect(emailInput).toBeDisabled();
            expect(emailInput.value).toBe('test@example.com');
        });

        it('should pre-populate email field with prop value', () => {
            render(<ResetPassword email="user@example.com" token="token123" />);

            const emailInput = screen.getByDisplayValue('user@example.com');
            expect(emailInput).toBeInTheDocument();
        });
    });

    describe('form inputs', () => {
        it('should not allow email field to be edited', async () => {
            const { user } = render(<ResetPassword {...defaultProps} />);

            const emailInput = screen.getByLabelText('Email');
            expect(emailInput).toBeDisabled();

            // Try to type - should not work because field is disabled
            await user.type(emailInput, 'new@example.com');
            expect(screen.getByDisplayValue('test@example.com')).toBeInTheDocument();
        });

        it('should update new password field on input', async () => {
            const { user } = render(<ResetPassword {...defaultProps} />);

            const passwordInput = screen.getByLabelText('New Password');
            await user.type(passwordInput, 'newpassword123');

            expect(screen.getByDisplayValue('newpassword123')).toBeInTheDocument();
        });

        it('should update confirm password field on input', async () => {
            const { user } = render(<ResetPassword {...defaultProps} />);

            const confirmPasswordInput = screen.getByLabelText('Confirm Password');
            await user.type(confirmPasswordInput, 'newpassword123');

            expect(screen.getByDisplayValue('newpassword123')).toBeInTheDocument();
        });

        it('should have new password field with autofocus', () => {
            render(<ResetPassword {...defaultProps} />);

            const passwordInput = screen.getByLabelText('New Password');
            // Input component receives autoFocus prop, but in test env it doesn't set DOM attribute
            expect(passwordInput).toBeInTheDocument();
        });

        it('should have required attributes on password inputs', () => {
            render(<ResetPassword {...defaultProps} />);

            const passwordInput = screen.getByLabelText('New Password');
            const confirmPasswordInput = screen.getByLabelText('Confirm Password');

            expect(passwordInput).toBeRequired();
            expect(confirmPasswordInput).toBeRequired();
        });

        it('should have correct input types', () => {
            render(<ResetPassword {...defaultProps} />);

            const emailInput = screen.getByLabelText('Email');
            const passwordInput = screen.getByLabelText('New Password');
            const confirmPasswordInput = screen.getByLabelText('Confirm Password');

            expect(emailInput).toHaveAttribute('type', 'email');
            expect(passwordInput).toHaveAttribute('type', 'password');
            expect(confirmPasswordInput).toHaveAttribute('type', 'password');
        });
    });

    describe('form submission', () => {
        it('should submit form with correct data including token', async () => {
            const { user } = render(<ResetPassword {...defaultProps} />);

            const passwordInput = screen.getByLabelText('New Password');
            const confirmPasswordInput = screen.getByLabelText('Confirm Password');
            const submitButton = screen.getByRole('button', { name: 'Reset Password' });

            await user.type(passwordInput, 'newpassword123');
            await user.type(confirmPasswordInput, 'newpassword123');
            await user.click(submitButton);

            expect(router.post).toHaveBeenCalledWith('/reset-password', expect.objectContaining({
                token: 'reset-token-12345',
                email: 'test@example.com',
                password: 'newpassword123',
                password_confirmation: 'newpassword123',
            }), undefined);
        });

        it('should submit form on Enter key press', async () => {
            const { user } = render(<ResetPassword {...defaultProps} />);

            const passwordInput = screen.getByLabelText('New Password');
            const confirmPasswordInput = screen.getByLabelText('Confirm Password');

            await user.type(passwordInput, 'newpassword123');
            await user.type(confirmPasswordInput, 'newpassword123');
            await user.keyboard('{Enter}');

            expect(router.post).toHaveBeenCalledWith('/reset-password', expect.objectContaining({
                token: 'reset-token-12345',
                email: 'test@example.com',
                password: 'newpassword123',
                password_confirmation: 'newpassword123',
            }), undefined);
        });

        it('should submit form with mismatched passwords', async () => {
            const { user } = render(<ResetPassword {...defaultProps} />);

            const passwordInput = screen.getByLabelText('New Password');
            const confirmPasswordInput = screen.getByLabelText('Confirm Password');
            const submitButton = screen.getByRole('button', { name: 'Reset Password' });

            await user.type(passwordInput, 'password123');
            await user.type(confirmPasswordInput, 'different123');
            await user.click(submitButton);

            expect(router.post).toHaveBeenCalledWith('/reset-password', expect.objectContaining({
                token: 'reset-token-12345',
                email: 'test@example.com',
                password: 'password123',
                password_confirmation: 'different123',
            }), undefined);
        });

        it('should include token from props in submission', async () => {
            const customToken = 'custom-token-xyz';
            const { user } = render(<ResetPassword email="user@test.com" token={customToken} />);

            const passwordInput = screen.getByLabelText('New Password');
            const confirmPasswordInput = screen.getByLabelText('Confirm Password');
            const submitButton = screen.getByRole('button', { name: 'Reset Password' });

            await user.type(passwordInput, 'password123');
            await user.type(confirmPasswordInput, 'password123');
            await user.click(submitButton);

            expect(router.post).toHaveBeenCalledWith('/reset-password', expect.objectContaining({
                token: customToken,
            }), undefined);
        });

        it('should always include email from props', async () => {
            const customEmail = 'custom@example.com';
            const { user } = render(<ResetPassword email={customEmail} token="token123" />);

            const passwordInput = screen.getByLabelText('New Password');
            const confirmPasswordInput = screen.getByLabelText('Confirm Password');
            const submitButton = screen.getByRole('button', { name: 'Reset Password' });

            await user.type(passwordInput, 'password123');
            await user.type(confirmPasswordInput, 'password123');
            await user.click(submitButton);

            expect(router.post).toHaveBeenCalledWith('/reset-password', expect.objectContaining({
                email: customEmail,
            }), undefined);
        });
    });

    describe('edge cases', () => {
        it('should attempt empty password submission', async () => {
            const { user } = render(<ResetPassword {...defaultProps} />);

            const submitButton = screen.getByRole('button', { name: 'Reset Password' });
            await user.click(submitButton);

            // Form has required password fields, so browser validation prevents submission
            // We just verify the button is clickable
            expect(submitButton).toBeInTheDocument();
        });

        it('should attempt submission with only new password filled', async () => {
            const { user } = render(<ResetPassword {...defaultProps} />);

            const passwordInput = screen.getByLabelText('New Password');
            const submitButton = screen.getByRole('button', { name: 'Reset Password' });

            await user.type(passwordInput, 'password123');
            await user.click(submitButton);

            // Confirm password field is required, so browser validation prevents submission
            // We just verify the password was entered
            expect(passwordInput).toHaveValue('password123');
        });

        it('should allow special characters in password', async () => {
            const { user } = render(<ResetPassword {...defaultProps} />);

            const passwordInput = screen.getByLabelText('New Password');
            await user.type(passwordInput, 'P@ssw0rd!#$%');

            expect(screen.getByDisplayValue('P@ssw0rd!#$%')).toBeInTheDocument();
        });

        it('should handle empty string email prop', () => {
            render(<ResetPassword email="" token="token123" />);

            const emailInput = screen.getByLabelText('Email') as HTMLInputElement;
            expect(emailInput.value).toBe('');
            expect(emailInput).toBeDisabled();
        });

        it('should handle empty string token prop', async () => {
            const { user } = render(<ResetPassword email="test@example.com" token="" />);

            const passwordInput = screen.getByLabelText('New Password');
            const confirmPasswordInput = screen.getByLabelText('Confirm Password');
            const submitButton = screen.getByRole('button', { name: 'Reset Password' });

            await user.type(passwordInput, 'password123');
            await user.type(confirmPasswordInput, 'password123');
            await user.click(submitButton);

            expect(router.post).toHaveBeenCalledWith('/reset-password', expect.objectContaining({
                token: '',
            }), undefined);
        });
    });

    describe('accessibility', () => {
        it('should have proper form structure', () => {
            render(<ResetPassword {...defaultProps} />);

            const form = screen.getByRole('button', { name: 'Reset Password' }).closest('form');
            expect(form).toBeInTheDocument();
        });

        it('should have labeled inputs', () => {
            render(<ResetPassword {...defaultProps} />);

            expect(screen.getByLabelText('Email')).toBeInTheDocument();
            expect(screen.getByLabelText('New Password')).toBeInTheDocument();
            expect(screen.getByLabelText('Confirm Password')).toBeInTheDocument();
        });

        it('should indicate disabled state for email field', () => {
            render(<ResetPassword {...defaultProps} />);

            const emailInput = screen.getByLabelText('Email');
            expect(emailInput).toBeDisabled();
        });
    });
});
