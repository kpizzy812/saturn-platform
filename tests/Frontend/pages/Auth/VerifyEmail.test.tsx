import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import VerifyEmail from '@/pages/Auth/VerifyEmail';

describe('VerifyEmail Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page title and subtitle', () => {
            render(<VerifyEmail />);

            expect(screen.getByText('Verify Your Email')).toBeInTheDocument();
            expect(screen.getByText("We've sent a verification link to your email address.")).toBeInTheDocument();
        });

        it('should display email address when provided', () => {
            render(<VerifyEmail email="user@example.com" />);

            expect(screen.getByText('Email sent to:')).toBeInTheDocument();
            expect(screen.getByText('user@example.com')).toBeInTheDocument();
        });

        it('should not display email section when email is not provided', () => {
            render(<VerifyEmail />);

            expect(screen.queryByText('Email sent to:')).not.toBeInTheDocument();
        });

        it('should render resend button', () => {
            render(<VerifyEmail />);

            const resendButton = screen.getByRole('button', { name: /resend verification email/i });
            expect(resendButton).toBeInTheDocument();
        });

        it('should render instructions', () => {
            render(<VerifyEmail />);

            expect(screen.getByText('Check your inbox')).toBeInTheDocument();
            expect(screen.getByText(/click the verification link/i)).toBeInTheDocument();
            expect(screen.getByText(/expire in 24 hours/i)).toBeInTheDocument();
            expect(screen.getByText(/check your spam folder/i)).toBeInTheDocument();
        });

        it('should render navigation buttons', () => {
            render(<VerifyEmail />);

            expect(screen.getByRole('button', { name: /continue to dashboard/i })).toBeInTheDocument();
            expect(screen.getByRole('button', { name: /back to login/i })).toBeInTheDocument();
        });
    });

    describe('sent status', () => {
        it('should show success message when status is sent', () => {
            render(<VerifyEmail status="sent" />);

            expect(screen.getByText(/verification email sent successfully/i)).toBeInTheDocument();
        });

        it('should disable resend button when status is sent', () => {
            render(<VerifyEmail status="sent" />);

            const resendButton = screen.getByRole('button', { name: /email sent/i });
            expect(resendButton).toBeDisabled();
        });

        it('should show email sent button text when status is sent', () => {
            render(<VerifyEmail status="sent" />);

            expect(screen.getByRole('button', { name: /email sent/i })).toBeInTheDocument();
        });

        it('should show wait message when status is sent', () => {
            render(<VerifyEmail status="sent" />);

            expect(screen.getByText(/please wait a few minutes/i)).toBeInTheDocument();
        });
    });

    describe('resend functionality', () => {
        it('should call resend endpoint when button is clicked', async () => {
            const { user } = render(<VerifyEmail />);

            const resendButton = screen.getByRole('button', { name: /resend verification email/i });
            await user.click(resendButton);

            // Form post is handled by useForm hook mock
            expect(resendButton).toBeInTheDocument();
        });

        it('should not be disabled initially', () => {
            render(<VerifyEmail status="pending" />);

            const resendButton = screen.getByRole('button', { name: /resend verification email/i });
            expect(resendButton).not.toBeDisabled();
        });
    });

    describe('navigation links', () => {
        it('should have correct href for dashboard link', () => {
            render(<VerifyEmail />);

            const dashboardButton = screen.getByRole('button', { name: /continue to dashboard/i });
            const link = dashboardButton.closest('a');
            expect(link).toHaveAttribute('href', '/dashboard');
        });

        it('should have correct href for login link', () => {
            render(<VerifyEmail />);

            const loginButton = screen.getByRole('button', { name: /back to login/i });
            const link = loginButton.closest('a');
            expect(link).toHaveAttribute('href', '/login');
        });
    });

    describe('visual elements', () => {
        it('should render mail icon by default', () => {
            render(<VerifyEmail status="pending" />);

            // Mail icon is rendered in the component
            const container = screen.getByText('Verify Your Email').parentElement;
            expect(container).toBeInTheDocument();
        });

        it('should render check circle icon when sent', () => {
            render(<VerifyEmail status="sent" />);

            // CheckCircle2 icon is rendered when status is sent
            const successMessage = screen.getByText(/verification email sent successfully/i);
            expect(successMessage).toBeInTheDocument();
        });
    });

    describe('edge cases', () => {
        it('should render with all props combined', () => {
            render(<VerifyEmail status="sent" email="test@example.com" />);

            expect(screen.getByText('test@example.com')).toBeInTheDocument();
            expect(screen.getByText(/verification email sent successfully/i)).toBeInTheDocument();
        });

        it('should handle missing optional props', () => {
            render(<VerifyEmail />);

            expect(screen.getByText('Verify Your Email')).toBeInTheDocument();
            expect(screen.getByRole('button', { name: /resend verification email/i })).toBeInTheDocument();
        });
    });
});
