import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import EnvDiff from '../EnvDiff';

// Mock Inertia
vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title: string }) => <title>{title}</title>,
    Link: ({ children, href, ...props }: any) => (
        <a href={href} {...props}>{children}</a>
    ),
    router: { visit: vi.fn() },
}));

const defaultProps = {
    project: { id: 1, uuid: 'proj-uuid-123', name: 'Test Project' },
    environments: [
        { id: 1, uuid: 'env-1', name: 'development', type: 'development' },
        { id: 2, uuid: 'env-2', name: 'uat', type: 'uat' },
        { id: 3, uuid: 'env-3', name: 'production', type: 'production' },
    ],
};

describe('EnvDiff', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders the page with environment selectors', () => {
        render(<EnvDiff {...defaultProps} />);

        expect(screen.getByText('Environment Variable Diff')).toBeInTheDocument();
        expect(screen.getByText('Compare')).toBeInTheDocument();
    });

    it('renders environment options in selectors', () => {
        render(<EnvDiff {...defaultProps} />);

        const selects = screen.getAllByRole('combobox');
        // Source selector, Target selector, Type filter
        expect(selects.length).toBeGreaterThanOrEqual(2);
    });

    it('disables compare button when no environments selected', () => {
        render(<EnvDiff {...defaultProps} />);

        const compareBtn = screen.getByText('Compare').closest('button');
        expect(compareBtn).toBeDisabled();
    });

    it('shows back link to project', () => {
        render(<EnvDiff {...defaultProps} />);

        const backLink = screen.getByText('Test Project');
        expect(backLink.closest('a')).toHaveAttribute('href', '/projects/proj-uuid-123');
    });

    it('shows diff results when fetch succeeds', async () => {
        const mockResult = {
            resources: [
                {
                    name: 'my-api',
                    type: 'application',
                    source_env: 'development',
                    target_env: 'production',
                    matched: true,
                    diff: {
                        added: ['NEW_VAR'],
                        removed: ['OLD_VAR'],
                        changed: ['DB_HOST'],
                        unchanged: ['APP_NAME'],
                    },
                },
            ],
            summary: {
                total_resources: 1,
                matched_resources: 1,
                unmatched_resources: 0,
                total_added: 1,
                total_removed: 1,
                total_changed: 1,
                total_unchanged: 1,
            },
        };

        global.fetch = vi.fn().mockResolvedValueOnce({
            ok: true,
            json: () => Promise.resolve(mockResult),
        });

        render(<EnvDiff {...defaultProps} />);

        // Select environments
        const selects = screen.getAllByRole('combobox');
        fireEvent.change(selects[0], { target: { value: '1' } });
        fireEvent.change(selects[1], { target: { value: '2' } });

        // Click compare
        const compareBtn = screen.getByText('Compare').closest('button');
        fireEvent.click(compareBtn!);

        await waitFor(() => {
            expect(screen.getByText('my-api')).toBeInTheDocument();
        });

        expect(screen.getByText('1 added')).toBeInTheDocument();
        expect(screen.getByText('1 removed')).toBeInTheDocument();
        expect(screen.getByText('1 changed')).toBeInTheDocument();
    });

    it('shows error on fetch failure', async () => {
        global.fetch = vi.fn().mockResolvedValueOnce({
            ok: false,
            json: () => Promise.resolve({ message: 'Environments not found' }),
        });

        render(<EnvDiff {...defaultProps} />);

        const selects = screen.getAllByRole('combobox');
        fireEvent.change(selects[0], { target: { value: '1' } });
        fireEvent.change(selects[1], { target: { value: '2' } });

        const compareBtn = screen.getByText('Compare').closest('button');
        fireEvent.click(compareBtn!);

        await waitFor(() => {
            expect(screen.getByText('Environments not found')).toBeInTheDocument();
        });
    });

    it('shows unmatched resource indicator', async () => {
        const mockResult = {
            resources: [
                {
                    name: 'orphan-app',
                    type: 'application',
                    source_env: 'development',
                    target_env: 'production',
                    matched: false,
                    only_in: 'source',
                    diff: {
                        added: ['KEY1'],
                        removed: [],
                        changed: [],
                        unchanged: [],
                    },
                },
            ],
            summary: {
                total_resources: 1,
                matched_resources: 0,
                unmatched_resources: 1,
                total_added: 1,
                total_removed: 0,
                total_changed: 0,
                total_unchanged: 0,
            },
        };

        global.fetch = vi.fn().mockResolvedValueOnce({
            ok: true,
            json: () => Promise.resolve(mockResult),
        });

        render(<EnvDiff {...defaultProps} />);

        const selects = screen.getAllByRole('combobox');
        fireEvent.change(selects[0], { target: { value: '1' } });
        fireEvent.change(selects[1], { target: { value: '2' } });

        fireEvent.click(screen.getByText('Compare').closest('button')!);

        await waitFor(() => {
            expect(screen.getByText('Only in development')).toBeInTheDocument();
        });
    });
});
