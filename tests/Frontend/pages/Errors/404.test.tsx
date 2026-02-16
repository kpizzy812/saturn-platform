import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import NotFound from '@/pages/Errors/404';

describe('Errors/404', () => {
    it('renders 404 error code', () => {
        render(<NotFound />);

        expect(screen.getByRole('heading', { level: 1, name: '404' })).toBeInTheDocument();
    });

    it('renders page not found heading', () => {
        render(<NotFound />);

        expect(screen.getByRole('heading', { level: 2, name: /page not found/i })).toBeInTheDocument();
    });

    it('renders error description', () => {
        render(<NotFound />);

        expect(screen.getByText(/the page you're looking for doesn't exist or has been moved/i)).toBeInTheDocument();
    });

    it('renders go to dashboard button', () => {
        render(<NotFound />);

        const dashboardButton = screen.getByRole('link', { name: /go to dashboard/i });
        expect(dashboardButton).toBeInTheDocument();
        expect(dashboardButton).toHaveAttribute('href', '/');
    });

    it('renders go back button', () => {
        render(<NotFound />);

        expect(screen.getByRole('button', { name: /go back/i })).toBeInTheDocument();
    });

    it('renders search suggestion with keyboard shortcut', () => {
        render(<NotFound />);

        expect(screen.getByText(/try using/i)).toBeInTheDocument();
        expect(screen.getByText('Cmd+K')).toBeInTheDocument();
        expect(screen.getByText(/to search/i)).toBeInTheDocument();
    });

    it('renders quick links section', () => {
        render(<NotFound />);

        expect(screen.getByText(/quick links:/i)).toBeInTheDocument();
    });

    it('renders all quick links', () => {
        render(<NotFound />);

        expect(screen.getByRole('link', { name: /^projects$/i })).toHaveAttribute('href', '/projects');
        expect(screen.getByRole('link', { name: /^services$/i })).toHaveAttribute('href', '/services');
        expect(screen.getByRole('link', { name: /^databases$/i })).toHaveAttribute('href', '/databases');
        expect(screen.getByRole('link', { name: /^settings$/i })).toHaveAttribute('href', '/settings');
    });

    it('calls window.history.back when go back is clicked', async () => {
        const historyBackSpy = vi.spyOn(window.history, 'back').mockImplementation(() => {});
        const { user } = render(<NotFound />);

        const goBackButton = screen.getByRole('button', { name: /go back/i });
        await user.click(goBackButton);

        expect(historyBackSpy).toHaveBeenCalled();
        historyBackSpy.mockRestore();
    });
});
