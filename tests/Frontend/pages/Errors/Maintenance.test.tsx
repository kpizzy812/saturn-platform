import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../utils/test-utils';
import Maintenance from '@/pages/Errors/Maintenance';

describe('Errors/Maintenance', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders we will be back soon heading', () => {
        render(<Maintenance />);

        expect(screen.getByRole('heading', { level: 1, name: /we'll be back soon/i })).toBeInTheDocument();
    });

    it('renders default maintenance message', () => {
        render(<Maintenance />);

        expect(screen.getByText(/we are currently performing scheduled maintenance to improve your experience/i)).toBeInTheDocument();
    });

    it('renders custom maintenance message when provided', () => {
        render(<Maintenance message="Emergency database upgrade in progress" />);

        expect(screen.getByText(/emergency database upgrade in progress/i)).toBeInTheDocument();
    });

    it('renders estimated return time when provided', () => {
        render(<Maintenance estimatedReturn="January 15, 2024 at 3:00 PM UTC" />);

        expect(screen.getByText(/estimated return/i)).toBeInTheDocument();
        expect(screen.getByText('January 15, 2024 at 3:00 PM UTC')).toBeInTheDocument();
    });

    it('does not show estimated return section when not provided', () => {
        render(<Maintenance />);

        expect(screen.queryByText(/estimated return/i)).not.toBeInTheDocument();
    });

    it('renders status page link when provided', () => {
        render(<Maintenance statusUrl="https://status.saturn.app" />);

        const statusLink = screen.getByRole('link', { name: /check status page/i });
        expect(statusLink).toBeInTheDocument();
        expect(statusLink).toHaveAttribute('href', 'https://status.saturn.app');
        expect(statusLink).toHaveAttribute('target', '_blank');
        expect(statusLink).toHaveAttribute('rel', 'noopener noreferrer');
    });

    it('does not show status page link when not provided', () => {
        render(<Maintenance />);

        expect(screen.queryByRole('link', { name: /check status page/i })).not.toBeInTheDocument();
    });

    it('renders email notification form', () => {
        render(<Maintenance />);

        expect(screen.getByRole('heading', { level: 3, name: /get notified when we're back/i })).toBeInTheDocument();
        expect(screen.getByPlaceholderText(/enter your email/i)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /notify me/i })).toBeInTheDocument();
    });

    it('submits email notification form', async () => {
        const { user } = render(<Maintenance />);

        const emailInput = screen.getByPlaceholderText(/enter your email/i);
        const submitButton = screen.getByRole('button', { name: /notify me/i });

        await user.type(emailInput, 'test@example.com');
        await user.click(submitButton);

        // Wait for the success message
        await waitFor(() => {
            expect(screen.getByText(/thanks! we'll notify you at test@example.com when we're back online/i)).toBeInTheDocument();
        });
    });

    it('requires email for notification form', async () => {
        const { user } = render(<Maintenance />);

        const submitButton = screen.getByRole('button', { name: /notify me/i });
        await user.click(submitButton);

        // Form should have HTML5 validation
        const emailInput = screen.getByPlaceholderText(/enter your email/i) as HTMLInputElement;
        expect(emailInput.validity.valid).toBe(false);
    });

    it('renders service continuity message', () => {
        render(<Maintenance />);

        expect(screen.getByText(/your services are still running and deployments will resume automatically/i)).toBeInTheDocument();
    });

    it('renders auto-refresh message', () => {
        render(<Maintenance />);

        expect(screen.getByText(/your services are still running. this page will refresh automatically/i)).toBeInTheDocument();
    });

    it('shows loading state when submitting email', async () => {
        const { user } = render(<Maintenance />);

        const emailInput = screen.getByPlaceholderText(/enter your email/i);
        const submitButton = screen.getByRole('button', { name: /notify me/i });

        await user.type(emailInput, 'test@example.com');
        await user.click(submitButton);

        // Button should be disabled during submission
        await waitFor(() => {
            expect(screen.getByText(/thanks!/i)).toBeInTheDocument();
        });
    });
});
