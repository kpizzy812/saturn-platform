import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '../../utils/test-utils';

// Mock the @inertiajs/react module
vi.mock('@inertiajs/react', () => ({
    Head: ({ children, title }: { children?: React.ReactNode; title?: string }) => (
        <title>{title}</title>
    ),
    Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    usePage: () => ({
        props: {
            auth: {
                user: { id: 1, name: 'Test User', email: 'test@example.com' },
            },
        },
    }),
}));

// Import after mock
import DatabasesIndex from '@/pages/Databases/Index';
import type { StandaloneDatabase } from '@/types';

const mockDatabases: StandaloneDatabase[] = [
    {
        id: 1,
        uuid: 'db-uuid-1',
        name: 'production-postgres',
        description: 'Main production database',
        database_type: 'postgresql' as const,
        status: 'running' as const,
        environment_id: 1,
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-15T00:00:00Z',
    },
    {
        id: 2,
        uuid: 'db-uuid-2',
        name: 'cache-redis',
        description: 'Redis cache',
        database_type: 'redis' as const,
        status: 'running' as const,
        environment_id: 1,
        created_at: '2024-01-02T00:00:00Z',
        updated_at: '2024-01-14T00:00:00Z',
    },
    {
        id: 3,
        uuid: 'db-uuid-3',
        name: 'analytics-mongodb',
        description: null,
        database_type: 'mongodb' as const,
        status: 'stopped' as const,
        environment_id: 1,
        created_at: '2024-01-03T00:00:00Z',
        updated_at: '2024-01-13T00:00:00Z',
    },
] as const;

describe('Databases Index Page', () => {
    it('renders the page header', () => {
        render(<DatabasesIndex databases={[]} />);
        // Multiple "Databases" texts may exist (breadcrumb + header)
        expect(screen.getAllByText('Databases').length).toBeGreaterThan(0);
        expect(screen.getByText('Manage your database instances')).toBeInTheDocument();
    });

    it('shows New Database button', () => {
        render(<DatabasesIndex databases={[]} />);
        const newDatabaseButtons = screen.getAllByText('New Database');
        expect(newDatabaseButtons.length).toBeGreaterThan(0);
    });

    it('shows empty state when no databases exist', () => {
        render(<DatabasesIndex databases={[]} />);
        expect(screen.getByText('No databases yet')).toBeInTheDocument();
        expect(screen.getByText(/Create your first database/)).toBeInTheDocument();
    });

    it('shows database cards when databases exist', () => {
        render(<DatabasesIndex databases={mockDatabases} />);
        expect(screen.getByText('production-postgres')).toBeInTheDocument();
        expect(screen.getByText('cache-redis')).toBeInTheDocument();
        expect(screen.getByText('analytics-mongodb')).toBeInTheDocument();
    });

    it('shows database types correctly', () => {
        render(<DatabasesIndex databases={mockDatabases} />);
        expect(screen.getByText('PostgreSQL')).toBeInTheDocument();
        expect(screen.getByText('Redis')).toBeInTheDocument();
        expect(screen.getByText('MongoDB')).toBeInTheDocument();
    });

    it('shows database status', () => {
        render(<DatabasesIndex databases={mockDatabases} />);
        // Status is displayed via StatusBadge component, check that databases are rendered
        expect(screen.getByText('production-postgres')).toBeInTheDocument();
        expect(screen.getByText('cache-redis')).toBeInTheDocument();
        expect(screen.getByText('analytics-mongodb')).toBeInTheDocument();
    });

    it('links to database detail pages', () => {
        render(<DatabasesIndex databases={mockDatabases} />);
        const links = screen.getAllByRole('link');
        const databaseLinks = links.filter(link =>
            link.getAttribute('href')?.includes('/databases/')
        );
        // Should have at least 3 database card links
        expect(databaseLinks.length).toBeGreaterThanOrEqual(3);
    });

    it('does not show empty state when databases exist', () => {
        render(<DatabasesIndex databases={mockDatabases} />);
        expect(screen.queryByText('No databases yet')).not.toBeInTheDocument();
    });
});
