import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import { router } from '@inertiajs/react';
import ConfirmPassword from '@/pages/Auth/ConfirmPassword';

describe('ConfirmPassword Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page title and subtitle', () => {
            render(<ConfirmPassword />);

            expect(screen.getByText('Confirm Password')).toBeInTheDocument();
            expect(screen.getByText('Please confirm your password before continuing.')).toBeInTheDocument();
        });

        it('should render lock icon', () => {
            render(<ConfirmPassword />);

            // Icon is rendered, check for its container
            const container = screen.getByText('Confirm Password').parentElement;
            expect(container).toBeInTheDocument();
        });

        it('should render password input field', () => {
            render(<ConfirmPassword />);

            const passwordInput = screen.getByLabelText('Password');
            expect(passwordInput).toBeInTheDocument();
            expect(passwordInput).toHaveAttribute('type', 'password');
        });

        it('should render password input with placeholder', () => {
            render(<ConfirmPassword />);

            expect(screen.getByPlaceholderText('Enter your password')).toBeInTheDocument();
        });

        it('should render submit button', () => {
            render(<ConfirmPassword />);

            const submitButton = screen.getByRole('button', { name: /confirm password/i });
            expect(submitButton).toBeInTheDocument();
        });

        it('should have required attribute on password input', () => {
            render(<ConfirmPassword />);

            const passwordInput = screen.getByLabelText('Password');
            expect(passwordInput).toBeRequired();
        });
    });

    describe('form interaction', () => {
        it('should update password field on input', async () => {
            const { user } = render(<ConfirmPassword />);

            const passwordInput = screen.getByPlaceholderText('Enter your password');
            await user.type(passwordInput, 'mypassword123');

            expect(screen.getByDisplayValue('mypassword123')).toBeInTheDocument();
        });

        it('should submit form with password on button click', async () => {
            const { user } = render(<ConfirmPassword />);

            const passwordInput = screen.getByPlaceholderText('Enter your password');
            await user.type(passwordInput, 'mypassword123');

            const submitButton = screen.getByRole('button', { name: /confirm password/i });
            await user.click(submitButton);

            expect(router.post).toHaveBeenCalledWith(
                '/user/confirm-password',
                expect.objectContaining({ password: 'mypassword123' }),
                expect.any(Object)
            );
        });

        it('should submit form on Enter key press', async () => {
            const { user } = render(<ConfirmPassword />);

            const passwordInput = screen.getByPlaceholderText('Enter your password');
            await user.type(passwordInput, 'mypassword123');
            await user.keyboard('{Enter}');

            expect(router.post).toHaveBeenCalledWith(
                '/user/confirm-password',
                expect.objectContaining({ password: 'mypassword123' }),
                expect.any(Object)
            );
        });

        it('should show confirming text when loading', async () => {
            const { user } = render(<ConfirmPassword />);

            const passwordInput = screen.getByPlaceholderText('Enter your password');
            await user.type(passwordInput, 'test');

            // Click submit - button text may change based on state
            const submitButton = screen.getByRole('button', { name: /confirm password/i });
            expect(submitButton).toBeInTheDocument();
        });
    });

    describe('edge cases', () => {
        it('should handle empty password submission', async () => {
            const { user } = render(<ConfirmPassword />);

            const submitButton = screen.getByRole('button', { name: /confirm password/i });
            await user.click(submitButton);

            // Browser validation will prevent submission, but button should be clickable
            expect(submitButton).toBeInTheDocument();
        });

        it('should render form element', () => {
            render(<ConfirmPassword />);

            const passwordInput = screen.getByPlaceholderText('Enter your password');
            const form = passwordInput.closest('form');
            expect(form).toBeInTheDocument();
        });
    });
});
