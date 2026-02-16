import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../utils/test-utils';
import { router } from '@inertiajs/react';
import ServerError from '@/pages/Errors/500';

describe('Errors/500', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders 500 error code', () => {
        render(<ServerError />);

        expect(screen.getByRole('heading', { level: 1, name: '500' })).toBeInTheDocument();
    });

    it('renders something went wrong heading', () => {
        render(<ServerError />);

        expect(screen.getByRole('heading', { level: 2, name: /something went wrong/i })).toBeInTheDocument();
    });

    it('renders default error message', () => {
        render(<ServerError />);

        expect(screen.getByText(/an unexpected error occurred on our servers/i)).toBeInTheDocument();
    });

    it('renders custom error message when provided', () => {
        render(<ServerError message="Database connection failed" />);

        expect(screen.getByText(/database connection failed/i)).toBeInTheDocument();
    });

    it('renders generated error ID when not provided', () => {
        render(<ServerError />);

        const errorIdElement = screen.getByText(/ERR-/);
        expect(errorIdElement).toBeInTheDocument();
    });

    it('renders custom error ID when provided', () => {
        render(<ServerError errorId="ERR-ABC123" />);

        expect(screen.getByText('ERR-ABC123')).toBeInTheDocument();
    });

    it('renders try again button', () => {
        render(<ServerError />);

        expect(screen.getByRole('button', { name: /try again/i })).toBeInTheDocument();
    });

    it('renders go to dashboard button', () => {
        render(<ServerError />);

        const dashboardButton = screen.getByRole('link', { name: /go to dashboard/i });
        expect(dashboardButton).toBeInTheDocument();
        expect(dashboardButton).toHaveAttribute('href', '/');
    });

    it('renders contact support link', () => {
        render(<ServerError />);

        const supportLink = screen.getByRole('link', { name: /contact support/i });
        expect(supportLink).toBeInTheDocument();
        expect(supportLink).toHaveAttribute('href', '/support');
    });

    it('renders error reference section', () => {
        render(<ServerError />);

        expect(screen.getByText(/error reference/i)).toBeInTheDocument();
        expect(screen.getByText(/share this id with support for faster assistance/i)).toBeInTheDocument();
    });

    it('renders help text', () => {
        render(<ServerError />);

        expect(screen.getByText(/if this issue persists, please contact your system administrator/i)).toBeInTheDocument();
    });

    it('renders copy error ID button', () => {
        const writeTextMock = vi.fn();
        Object.defineProperty(navigator, 'clipboard', {
            value: {
                writeText: writeTextMock,
            },
            writable: true,
            configurable: true,
        });

        render(<ServerError errorId="ERR-TEST123" />);

        // Button should exist with title attribute
        const buttons = screen.getAllByRole('button');
        const copyButton = buttons.find(btn => btn.getAttribute('title') === 'Copy error ID');
        expect(copyButton).toBeInTheDocument();
    });

    it('displays error ID in code element', () => {
        render(<ServerError errorId="ERR-TEST123" />);

        // Error ID should be displayed in a code element
        const codeElements = screen.getAllByText('ERR-TEST123');
        expect(codeElements.length).toBeGreaterThan(0);
    });

    it('calls router.reload when try again is clicked', async () => {
        const { user } = render(<ServerError />);

        const tryAgainButton = screen.getByRole('button', { name: /try again/i });
        await user.click(tryAgainButton);

        expect(router.reload).toHaveBeenCalled();
    });
});
