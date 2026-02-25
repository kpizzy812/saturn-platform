import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import MigrationTimeline from '../Timeline';

// Mock Inertia
vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title: string }) => <title>{title}</title>,
    Link: ({ children, href, ...props }: any) => (
        <a href={href} {...props}>{children}</a>
    ),
    router: { visit: vi.fn() },
}));

const defaultProps = {
    project: { id: 1, uuid: 'proj-1', name: 'Test Project' },
    environments: [
        { id: 1, name: 'development', type: 'development' },
        { id: 2, name: 'uat', type: 'uat' },
        { id: 3, name: 'production', type: 'production' },
    ],
    migrations: [] as any[],
};

describe('MigrationTimeline', () => {
    it('renders the page header', () => {
        render(<MigrationTimeline {...defaultProps} />);

        expect(screen.getByText('Migration Timeline')).toBeInTheDocument();
        expect(screen.getByText('Test Project')).toBeInTheDocument();
    });

    it('renders environment chain badges', () => {
        render(<MigrationTimeline {...defaultProps} />);

        expect(screen.getByText('development')).toBeInTheDocument();
        expect(screen.getByText('uat')).toBeInTheDocument();
        expect(screen.getByText('production')).toBeInTheDocument();
    });

    it('shows empty state when no migrations', () => {
        render(<MigrationTimeline {...defaultProps} />);

        expect(screen.getByText('No migrations found for this project.')).toBeInTheDocument();
    });

    it('renders migration cards in correct columns', () => {
        const migrations = [
            {
                uuid: 'mig-1',
                status: 'completed',
                source: { name: 'api-app' },
                source_type: 'App\\Models\\Application',
                source_environment: { id: 1, name: 'development' },
                target_environment: { id: 2, name: 'uat' },
                target_environment_id: 2,
                created_at: '2026-02-20T10:00:00Z',
                options: { mode: 'promote' },
            },
            {
                uuid: 'mig-2',
                status: 'pending',
                source: { name: 'web-app' },
                source_type: 'App\\Models\\Application',
                source_environment: { id: 2, name: 'uat' },
                target_environment: { id: 3, name: 'production' },
                target_environment_id: 3,
                created_at: '2026-02-20T12:00:00Z',
                options: { mode: 'promote' },
            },
        ];

        render(<MigrationTimeline {...defaultProps} migrations={migrations as any} />);

        expect(screen.getByText('api-app')).toBeInTheDocument();
        expect(screen.getByText('web-app')).toBeInTheDocument();
        expect(screen.getByText('Completed')).toBeInTheDocument();
        expect(screen.getByText('Pending')).toBeInTheDocument();
    });

    it('links migration cards to detail page', () => {
        const migrations = [
            {
                uuid: 'mig-1',
                status: 'completed',
                source: { name: 'api-app' },
                source_type: 'App\\Models\\Application',
                source_environment: { id: 1, name: 'dev' },
                target_environment: { id: 2, name: 'uat' },
                target_environment_id: 2,
                created_at: '2026-02-20T10:00:00Z',
                options: {},
            },
        ];

        render(<MigrationTimeline {...defaultProps} migrations={migrations as any} />);

        const link = screen.getByText('api-app').closest('a');
        expect(link).toHaveAttribute('href', '/migrations/mig-1');
    });
});
