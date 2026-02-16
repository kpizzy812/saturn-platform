import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../utils/test-utils';
import ApprovalsIndex from '@/pages/Approvals/Index';

describe('Approvals/Index', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        // Mock fetch globally
        global.fetch = vi.fn();
        // Mock CSRF token
        document.head.innerHTML = '<meta name="csrf-token" content="test-token">';
    });

    it('renders page heading and description', () => {
        render(<ApprovalsIndex />);

        expect(screen.getByRole('heading', { level: 1, name: /pending approvals/i })).toBeInTheDocument();
        expect(screen.getByText(/review and approve deployment, migration, and transfer requests/i)).toBeInTheDocument();
    });

    it('renders refresh button', () => {
        render(<ApprovalsIndex />);

        expect(screen.getByRole('button', { name: /refresh/i })).toBeInTheDocument();
    });

    it('renders all tabs', () => {
        render(<ApprovalsIndex />);

        expect(screen.getByRole('button', { name: /deployments/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /migrations/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /transfers/i })).toBeInTheDocument();
    });

    it('shows deployments tab by default', () => {
        render(<ApprovalsIndex />);

        const deploymentsTab = screen.getByRole('button', { name: /deployments/i });
        // The active tab has specific classes
        expect(deploymentsTab.className).toContain('text-foreground');
    });

    it('switches to migrations tab', async () => {
        const { user } = render(<ApprovalsIndex />);

        const migrationsTab = screen.getByRole('button', { name: /migrations/i });
        await user.click(migrationsTab);

        expect(migrationsTab.className).toContain('text-foreground');
    });

    it('switches to transfers tab', async () => {
        const { user } = render(<ApprovalsIndex />);

        const transfersTab = screen.getByRole('button', { name: /transfers/i });
        await user.click(transfersTab);

        expect(transfersTab.className).toContain('text-foreground');
    });

    it('displays empty state for deployments', async () => {
        (global.fetch as any).mockResolvedValueOnce({
            ok: true,
            json: async () => [],
        }).mockResolvedValueOnce({
            ok: true,
            json: async () => ({ data: [] }),
        }).mockResolvedValueOnce({
            ok: true,
            json: async () => [],
        });

        render(<ApprovalsIndex />);

        await waitFor(() => {
            expect(screen.getByRole('heading', { level: 3, name: /no pending deployments/i })).toBeInTheDocument();
        });

        expect(screen.getByText(/all deployment requests have been processed/i)).toBeInTheDocument();
    });

    it('displays empty state for migrations', async () => {
        (global.fetch as any).mockResolvedValueOnce({
            ok: true,
            json: async () => [],
        }).mockResolvedValueOnce({
            ok: true,
            json: async () => ({ data: [] }),
        }).mockResolvedValueOnce({
            ok: true,
            json: async () => [],
        });

        const { user } = render(<ApprovalsIndex />);

        const migrationsTab = screen.getByRole('button', { name: /migrations/i });
        await user.click(migrationsTab);

        await waitFor(() => {
            expect(screen.getByRole('heading', { level: 3, name: /no pending migrations/i })).toBeInTheDocument();
        });
    });

    it('displays empty state for transfers', async () => {
        (global.fetch as any).mockResolvedValueOnce({
            ok: true,
            json: async () => [],
        }).mockResolvedValueOnce({
            ok: true,
            json: async () => ({ data: [] }),
        }).mockResolvedValueOnce({
            ok: true,
            json: async () => [],
        });

        const { user } = render(<ApprovalsIndex />);

        const transfersTab = screen.getByRole('button', { name: /transfers/i });
        await user.click(transfersTab);

        await waitFor(() => {
            expect(screen.getByRole('heading', { level: 3, name: /no pending transfers/i })).toBeInTheDocument();
        });
    });

    it('renders info about approvals for deployments', async () => {
        (global.fetch as any).mockResolvedValue({
            ok: true,
            json: async () => [],
        });

        render(<ApprovalsIndex />);

        await waitFor(() => {
            expect(screen.getByText(/about approvals/i)).toBeInTheDocument();
        });

        expect(screen.getByText(/deployments to production environments require approval/i)).toBeInTheDocument();
    });

    it('renders info about approvals for migrations', async () => {
        (global.fetch as any)
            .mockResolvedValueOnce({
                ok: true,
                json: async () => [],
            })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ data: [] }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => [],
            });

        const { user } = render(<ApprovalsIndex />);

        // Wait for initial load
        await waitFor(() => {
            expect(screen.queryByText(/loading/i)).not.toBeInTheDocument();
        });

        const migrationsTab = screen.getByRole('button', { name: /migrations/i });
        await user.click(migrationsTab);

        await waitFor(() => {
            expect(screen.getByText(/migrations to production environments require approval/i)).toBeInTheDocument();
        });
    });

    it('renders info about approvals for transfers', async () => {
        (global.fetch as any)
            .mockResolvedValueOnce({
                ok: true,
                json: async () => [],
            })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ data: [] }),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => [],
            });

        const { user } = render(<ApprovalsIndex />);

        // Wait for initial load
        await waitFor(() => {
            expect(screen.queryByText(/loading/i)).not.toBeInTheDocument();
        });

        const transfersTab = screen.getByRole('button', { name: /transfers/i });
        await user.click(transfersTab);

        await waitFor(() => {
            expect(screen.getByText(/transfers to production environments require approval/i)).toBeInTheDocument();
        });
    });

    it('shows loading state initially', () => {
        (global.fetch as any).mockImplementation(() => new Promise(() => {}));

        render(<ApprovalsIndex />);

        expect(screen.getByText(/loading pending deployments/i)).toBeInTheDocument();
    });

    it('handles fetch error for deployments', async () => {
        (global.fetch as any).mockResolvedValueOnce({
            ok: false,
        }).mockResolvedValueOnce({
            ok: true,
            json: async () => ({ data: [] }),
        }).mockResolvedValueOnce({
            ok: true,
            json: async () => [],
        });

        render(<ApprovalsIndex />);

        await waitFor(() => {
            expect(screen.getByText(/failed to load pending approvals/i)).toBeInTheDocument();
        });
    });

    it('shows retry button on error', async () => {
        (global.fetch as any).mockResolvedValueOnce({
            ok: false,
        }).mockResolvedValueOnce({
            ok: true,
            json: async () => ({ data: [] }),
        }).mockResolvedValueOnce({
            ok: true,
            json: async () => [],
        });

        render(<ApprovalsIndex />);

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /retry/i })).toBeInTheDocument();
        });
    });
});
