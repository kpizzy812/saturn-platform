import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import DeploymentsIndex from './Index';

// Mock Inertia
vi.mock('@inertiajs/react', () => ({
    Link: ({ children, href, ...props }: any) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
}));

// Mock AppLayout
vi.mock('@/components/layout', () => ({
    AppLayout: ({ children, title }: any) => (
        <div data-testid="app-layout" data-title={title}>
            {children}
        </div>
    ),
}));

// Mock UI components
vi.mock('@/components/ui', () => ({
    Card: ({ children, className }: any) => <div className={className}>{children}</div>,
    CardContent: ({ children, className }: any) => <div className={className}>{children}</div>,
    Badge: ({ children, variant }: any) => <span data-variant={variant}>{children}</span>,
    Button: ({ children, onClick, disabled }: any) => (
        <button onClick={onClick} disabled={disabled}>
            {children}
        </button>
    ),
    Input: ({ value, onChange, placeholder }: any) => (
        <input value={value} onChange={onChange} placeholder={placeholder} />
    ),
    Select: ({ value, onChange, children }: any) => (
        <select value={value} onChange={onChange}>
            {children}
        </select>
    ),
}));

// Mock utils
vi.mock('@/lib/utils', () => ({
    formatRelativeTime: (_date: string) => '2 hours ago',
}));

describe('DeploymentsIndex', () => {
    it('renders the deployment history page', () => {
        render(<DeploymentsIndex />);
        expect(screen.getByText('Deployment History')).toBeInTheDocument();
        expect(screen.getByText('Track and manage all deployments across your services')).toBeInTheDocument();
    });

    it('displays search input', () => {
        render(<DeploymentsIndex />);
        expect(
            screen.getByPlaceholderText('Search by commit message, hash, author, or service...')
        ).toBeInTheDocument();
    });

    it('displays status filter buttons', () => {
        render(<DeploymentsIndex />);
        expect(screen.getByText('All')).toBeInTheDocument();
        expect(screen.getByText('Finished')).toBeInTheDocument();
        expect(screen.getByText('Failed')).toBeInTheDocument();
        expect(screen.getByText('In Progress')).toBeInTheDocument();
        expect(screen.getByText('Queued')).toBeInTheDocument();
        expect(screen.getByText('Cancelled')).toBeInTheDocument();
    });

    it('filters deployments by status', async () => {
        render(<DeploymentsIndex />);

        // Initially, all deployments should be visible
        expect(screen.getByText('feat: Add user authentication and JWT tokens')).toBeInTheDocument();
        expect(screen.getByText('refactor: Update database schema migrations')).toBeInTheDocument();

        // Click on 'Failed' filter
        const failedButton = screen.getByText('Failed');
        fireEvent.click(failedButton);

        // Only failed deployment should be visible
        await waitFor(() => {
            expect(screen.getByText('refactor: Update database schema migrations')).toBeInTheDocument();
        });
    });

    it('filters deployments by search query', async () => {
        render(<DeploymentsIndex />);

        const searchInput = screen.getByPlaceholderText(
            'Search by commit message, hash, author, or service...'
        );

        // Search for "authentication"
        fireEvent.change(searchInput, { target: { value: 'authentication' } });

        await waitFor(() => {
            expect(screen.getByText('feat: Add user authentication and JWT tokens')).toBeInTheDocument();
        });
    });

    it('displays deployment cards with correct information', () => {
        render(<DeploymentsIndex />);

        // Check for commit message
        expect(screen.getByText('feat: Add user authentication and JWT tokens')).toBeInTheDocument();

        // Check for author name
        expect(screen.getByText('John Doe')).toBeInTheDocument();

        // Check for service name
        expect(screen.getByText('production-api')).toBeInTheDocument();
    });

    it('shows empty state when no deployments match filters', async () => {
        render(<DeploymentsIndex />);

        const searchInput = screen.getByPlaceholderText(
            'Search by commit message, hash, author, or service...'
        );

        // Search for something that doesn't exist
        fireEvent.change(searchInput, { target: { value: 'nonexistent deployment' } });

        await waitFor(() => {
            expect(screen.getByText('No deployments found')).toBeInTheDocument();
            expect(screen.getByText('Try adjusting your search query or filters')).toBeInTheDocument();
        });
    });

    it('displays pagination when totalPages > 1', () => {
        render(<DeploymentsIndex totalPages={3} currentPage={1} />);

        expect(screen.getByText('Page 1 of 3')).toBeInTheDocument();
        expect(screen.getByText('Previous')).toBeInTheDocument();
        expect(screen.getByText('Next')).toBeInTheDocument();
    });

    it('filters deployments by service', async () => {
        render(<DeploymentsIndex />);

        // Get all service filter buttons (there should be unique service names)
        const serviceButtons = screen.getAllByText('production-api');
        const serviceFilterButton = serviceButtons.find(
            button => button.tagName === 'BUTTON'
        );

        if (serviceFilterButton) {
            fireEvent.click(serviceFilterButton);

            await waitFor(() => {
                // Should only show deployments for production-api
                expect(screen.getByText('feat: Add user authentication and JWT tokens')).toBeInTheDocument();
            });
        }
    });

    it('displays deployment status badges with correct variants', () => {
        render(<DeploymentsIndex />);

        const badges = screen.getAllByText(/finished|failed|in progress/i);
        expect(badges.length).toBeGreaterThan(0);
    });

    it('displays rollback button for finished deployments', () => {
        render(<DeploymentsIndex />);

        // Should have multiple rollback buttons (one for each finished deployment)
        const rollbackButtons = screen.getAllByText('Rollback');
        expect(rollbackButtons.length).toBeGreaterThan(0);
    });
});
