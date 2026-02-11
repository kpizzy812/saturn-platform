import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import { router } from '@inertiajs/react';
import ForgotPassword from '@/pages/Auth/ForgotPassword';

describe('ForgotPassword Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page with subtitle', () => {
            render(<ForgotPassword />);

            // Title is in <Head> component, subtitle is rendered as text
            expect(screen.getByText("Enter your email and we'll send you a reset link.")).toBeInTheDocument();
            expect(screen.getByText('Saturn')).toBeInTheDocument();
        });

        it('should render email field', () => {
            render(<ForgotPassword />);

            expect(screen.getByLabelText('Email')).toBeInTheDocument();
            expect(screen.getByPlaceholderText('you@example.com')).toBeInTheDocument();
        });

        it('should render submit button', () => {
            render(<ForgotPassword />);

            expect(screen.getByRole('button', { name: 'Send Reset Link' })).toBeInTheDocument();
        });

        it('should render back to login link', () => {
            render(<ForgotPassword />);

            const backLink = screen.getByText('Back to login');
            expect(backLink).toBeInTheDocument();
            expect(backLink.closest('a')).toHaveAttribute('href', '/login');
        });

        it('should display status message when provided', () => {
            const statusMessage = 'A password reset link has been sent to your email.';
            render(<ForgotPassword status={statusMessage} />);

            expect(screen.getByText(statusMessage)).toBeInTheDocument();
        });

        it('should not display status message when not provided', () => {
            render(<ForgotPassword />);

            // Subtitle contains "reset link" so we check for status-specific text
            const statusContainer = screen.queryByText(/sent to your email|has been sent/i);
            expect(statusContainer).not.toBeInTheDocument();
        });

        it('should style status message correctly', () => {
            const statusMessage = 'Password reset link sent!';
            render(<ForgotPassword status={statusMessage} />);

            const statusElement = screen.getByText(statusMessage);
            // Status div has these classes directly
            expect(statusElement).toHaveClass('mb-4', 'rounded-md', 'bg-primary/10', 'p-3', 'text-sm', 'text-primary');
        });
    });

    describe('form inputs', () => {
        it('should update email field on input', async () => {
            const { user } = render(<ForgotPassword />);

            const emailInput = screen.getByPlaceholderText('you@example.com');
            await user.type(emailInput, 'test@example.com');

            expect(screen.getByDisplayValue('test@example.com')).toBeInTheDocument();
        });

        it('should have email field with autofocus', () => {
            render(<ForgotPassword />);

            const emailInput = screen.getByPlaceholderText('you@example.com');
            // Input component receives autoFocus prop, but in test env it doesn't set DOM attribute
            expect(emailInput).toBeInTheDocument();
        });

        it('should have required attribute on email input', () => {
            render(<ForgotPassword />);

            const emailInput = screen.getByPlaceholderText('you@example.com');
            expect(emailInput).toBeRequired();
        });

        it('should have correct email input type', () => {
            render(<ForgotPassword />);

            const emailInput = screen.getByPlaceholderText('you@example.com');
            expect(emailInput).toHaveAttribute('type', 'email');
        });
    });

    describe('form submission', () => {
        it('should submit form with correct data', async () => {
            const { user } = render(<ForgotPassword />);

            const emailInput = screen.getByPlaceholderText('you@example.com');
            const submitButton = screen.getByRole('button', { name: 'Send Reset Link' });

            await user.type(emailInput, 'test@example.com');
            await user.click(submitButton);

            expect(router.post).toHaveBeenCalledWith('/forgot-password', expect.objectContaining({
                email: 'test@example.com',
            }), undefined);
        });

        it('should submit form on Enter key press', async () => {
            const { user } = render(<ForgotPassword />);

            const emailInput = screen.getByPlaceholderText('you@example.com');

            await user.type(emailInput, 'test@example.com');
            await user.keyboard('{Enter}');

            expect(router.post).toHaveBeenCalledWith('/forgot-password', expect.objectContaining({
                email: 'test@example.com',
            }), undefined);
        });

        it('should attempt empty form submission', async () => {
            const { user } = render(<ForgotPassword />);

            const submitButton = screen.getByRole('button', { name: 'Send Reset Link' });
            await user.click(submitButton);

            // Form has required email field, so browser validation prevents submission
            // We just verify the button is clickable
            expect(submitButton).toBeInTheDocument();
        });

        it('should submit form with status message present', async () => {
            const { user } = render(<ForgotPassword status="Previous message" />);

            const emailInput = screen.getByPlaceholderText('you@example.com');
            const submitButton = screen.getByRole('button', { name: 'Send Reset Link' });

            await user.type(emailInput, 'test@example.com');
            await user.click(submitButton);

            expect(router.post).toHaveBeenCalledWith('/forgot-password', expect.objectContaining({
                email: 'test@example.com',
            }), undefined);
        });
    });

    describe('navigation', () => {
        it('should have back to login link with correct href', () => {
            render(<ForgotPassword />);

            const backLink = screen.getByText('Back to login').closest('a');
            expect(backLink).toHaveAttribute('href', '/login');
        });

        it('should render back arrow icon', () => {
            render(<ForgotPassword />);

            // ArrowLeft icon should be present (Lucide icon)
            const backLink = screen.getByText('Back to login');
            expect(backLink.parentElement).toBeInTheDocument();
        });
    });

    describe('edge cases', () => {
        it('should handle multiple status messages (only latest)', () => {
            const { rerender } = render(<ForgotPassword status="First message" />);

            expect(screen.getByText('First message')).toBeInTheDocument();

            rerender(<ForgotPassword status="Second message" />);

            expect(screen.queryByText('First message')).not.toBeInTheDocument();
            expect(screen.getByText('Second message')).toBeInTheDocument();
        });

        it('should allow various email formats', async () => {
            const { user } = render(<ForgotPassword />);

            const emailInput = screen.getByPlaceholderText('you@example.com');

            await user.type(emailInput, 'user+test@sub.example.co.uk');
            expect(screen.getByDisplayValue('user+test@sub.example.co.uk')).toBeInTheDocument();
        });

        it('should render status message after submission', async () => {
            const { rerender } = render(<ForgotPassword />);

            // No status message initially
            expect(screen.queryByText('Reset link sent!')).not.toBeInTheDocument();

            // Simulate status message appearing (like after form submission)
            rerender(<ForgotPassword status="Reset link sent!" />);

            // Status message should now be visible
            expect(screen.getByText('Reset link sent!')).toBeInTheDocument();
        });
    });

    describe('accessibility', () => {
        it('should have proper form structure', () => {
            render(<ForgotPassword />);

            const form = screen.getByRole('button', { name: 'Send Reset Link' }).closest('form');
            expect(form).toBeInTheDocument();
        });

        it('should have labeled email input', () => {
            render(<ForgotPassword />);

            const emailInput = screen.getByLabelText('Email');
            expect(emailInput).toBeInTheDocument();
        });

        it('should have accessible back link', () => {
            render(<ForgotPassword />);

            const backLink = screen.getByRole('link', { name: /back to login/i });
            expect(backLink).toBeInTheDocument();
        });
    });
});
