import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import Forbidden from '@/pages/Errors/403';

describe('Errors/403', () => {
    it('renders 403 error code', () => {
        render(<Forbidden />);

        expect(screen.getByRole('heading', { level: 1, name: '403' })).toBeInTheDocument();
    });

    it('renders access denied heading', () => {
        render(<Forbidden />);

        expect(screen.getByRole('heading', { level: 2, name: /access denied/i })).toBeInTheDocument();
    });

    it('renders default reason message', () => {
        render(<Forbidden />);

        expect(screen.getByText(/you do not have the necessary permissions/i)).toBeInTheDocument();
    });

    it('renders custom reason when provided', () => {
        render(<Forbidden reason="Admin access required" />);

        expect(screen.getByText(/admin access required/i)).toBeInTheDocument();
    });

    it('renders default resource name', () => {
        render(<Forbidden />);

        expect(screen.getByText(/this resource/i)).toBeInTheDocument();
    });

    it('renders custom resource name when provided', () => {
        render(<Forbidden resource="production environment" />);

        expect(screen.getByText(/production environment/i)).toBeInTheDocument();
    });

    it('renders request access button', () => {
        render(<Forbidden />);

        const requestButton = screen.getByRole('link', { name: /request access/i });
        expect(requestButton).toBeInTheDocument();
        expect(requestButton).toHaveAttribute('href', '/settings/team');
    });

    it('renders go to dashboard button', () => {
        render(<Forbidden />);

        const dashboardButton = screen.getByRole('link', { name: /go to dashboard/i });
        expect(dashboardButton).toBeInTheDocument();
        expect(dashboardButton).toHaveAttribute('href', '/');
    });

    it('renders go back button', () => {
        render(<Forbidden />);

        expect(screen.getByRole('button', { name: /go back/i })).toBeInTheDocument();
    });

    it('renders why am I seeing this section', () => {
        render(<Forbidden />);

        expect(screen.getByText(/why am i seeing this/i)).toBeInTheDocument();
    });

    it('renders permission reasons', () => {
        render(<Forbidden />);

        expect(screen.getByText(/your team role might not include this access level/i)).toBeInTheDocument();
        expect(screen.getByText(/the resource may be restricted to certain team members/i)).toBeInTheDocument();
    });

    it('renders contact links', () => {
        render(<Forbidden />);

        expect(screen.getByRole('link', { name: /contact your team admin/i })).toHaveAttribute('href', '/settings/team');
        expect(screen.getByRole('link', { name: /reach out to support/i })).toHaveAttribute('href', '/support');
    });

    it('calls window.history.back when go back is clicked', async () => {
        const historyBackSpy = vi.spyOn(window.history, 'back').mockImplementation(() => {});
        const { user } = render(<Forbidden />);

        const goBackButton = screen.getByRole('button', { name: /go back/i });
        await user.click(goBackButton);

        expect(historyBackSpy).toHaveBeenCalled();
        historyBackSpy.mockRestore();
    });
});
