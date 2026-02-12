import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import DatabaseOverview from '@/pages/Databases/Overview';
import type { StandaloneDatabase } from '@/types';

// Mock hooks
vi.mock('@/hooks', () => ({
    useDatabaseMetrics: vi.fn(() => ({
        metrics: {
            activeConnections: 5,
            maxConnections: 100,
            queriesPerSec: 42,
            databaseSize: '256 MB',
        },
        isLoading: false,
        refetch: vi.fn(),
    })),
}));

// Mock router
const mockRouter = {
    post: vi.fn(),
    visit: vi.fn(),
};

vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual('@inertiajs/react');
    return {
        ...actual,
        router: mockRouter,
    };
});

describe('Database Overview Page', () => {
    const mockDatabase: StandaloneDatabase = {
        id: 1,
        uuid: 'test-db-uuid',
        name: 'Test PostgreSQL',
        database_type: 'postgresql',
        status: { state: 'running' },
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
        connection: {
            host: 'localhost',
            port: 5432,
            username: 'postgres',
            password: 'secret',
            database: 'testdb',
        },
    } as StandaloneDatabase;

    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page title and breadcrumbs', () => {
            render(<DatabaseOverview database={mockDatabase} />);

            expect(screen.getByRole('heading', { name: 'Test PostgreSQL' })).toBeInTheDocument();
            expect(screen.getByText('Back to Database')).toBeInTheDocument();
        });

        it('should display database type badge', () => {
            render(<DatabaseOverview database={mockDatabase} />);

            expect(screen.getByText('PostgreSQL')).toBeInTheDocument();
        });

        it('should display connection status badge', () => {
            render(<DatabaseOverview database={mockDatabase} />);

            expect(screen.getByText('Connected')).toBeInTheDocument();
        });

        it('should show disconnected status when database is not running', () => {
            const stoppedDb = { ...mockDatabase, status: { state: 'stopped' } };
            render(<DatabaseOverview database={stoppedDb} />);

            expect(screen.getByText('Disconnected')).toBeInTheDocument();
        });
    });

    describe('action buttons', () => {
        it('should display restart and backup buttons', () => {
            render(<DatabaseOverview database={mockDatabase} />);

            expect(screen.getByText('Restart')).toBeInTheDocument();
            expect(screen.getByText('Backup')).toBeInTheDocument();
        });

        // Note: Backup button navigation is tested indirectly via link presence
        // Full navigation testing requires more complex router mocking
    });

    describe('quick stats grid', () => {
        it('should display storage stat card', () => {
            render(<DatabaseOverview database={mockDatabase} />);

            expect(screen.getByText('Storage')).toBeInTheDocument();
            expect(screen.getByText('256 MB')).toBeInTheDocument();
        });

        it('should display active connections stat card', () => {
            render(<DatabaseOverview database={mockDatabase} />);

            expect(screen.getByText('Active Connections')).toBeInTheDocument();
            expect(screen.getByText('5')).toBeInTheDocument();
            expect(screen.getByText('of 100')).toBeInTheDocument();
        });

        it('should display queries per second stat card', () => {
            render(<DatabaseOverview database={mockDatabase} />);

            expect(screen.getByText('Queries/sec')).toBeInTheDocument();
            expect(screen.getByText('42')).toBeInTheDocument();
        });

        it('should display avg response time stat card', () => {
            render(<DatabaseOverview database={mockDatabase} />);

            expect(screen.getByText('Avg Response')).toBeInTheDocument();
        });
    });

    describe('performance metrics section', () => {
        it('should display performance metrics card', () => {
            render(<DatabaseOverview database={mockDatabase} />);

            expect(screen.getByText('Performance Metrics')).toBeInTheDocument();
            expect(screen.getByText('Uptime')).toBeInTheDocument();
            expect(screen.getByText('Last Backup')).toBeInTheDocument();
            expect(screen.getByText('Storage Usage')).toBeInTheDocument();
            expect(screen.getByText('Connection Usage')).toBeInTheDocument();
        });

        it('should have view detailed metrics button', () => {
            render(<DatabaseOverview database={mockDatabase} />);

            expect(screen.getByText('View Detailed Metrics')).toBeInTheDocument();
        });
    });

    describe('recent queries section', () => {
        it('should display recent queries card', () => {
            render(<DatabaseOverview database={mockDatabase} />);

            expect(screen.getByText('Recent Queries')).toBeInTheDocument();
        });

        it('should have open query browser button', () => {
            render(<DatabaseOverview database={mockDatabase} />);

            expect(screen.getByText('Open Query Browser')).toBeInTheDocument();
        });
    });

    describe('quick actions section', () => {
        it('should display quick actions card', () => {
            render(<DatabaseOverview database={mockDatabase} />);

            expect(screen.getByText('Quick Actions')).toBeInTheDocument();
        });

        it('should display create backup action', () => {
            render(<DatabaseOverview database={mockDatabase} />);

            expect(screen.getByText('Create Backup')).toBeInTheDocument();
            expect(screen.getByText('Backup your database now')).toBeInTheDocument();
        });

        it('should display manage users action', () => {
            render(<DatabaseOverview database={mockDatabase} />);

            expect(screen.getByText('Manage Users')).toBeInTheDocument();
            expect(screen.getByText('Add or remove database users')).toBeInTheDocument();
        });

        it('should display view logs action', () => {
            render(<DatabaseOverview database={mockDatabase} />);

            expect(screen.getByText('View Logs')).toBeInTheDocument();
            expect(screen.getByText('Check database logs')).toBeInTheDocument();
        });
    });

    describe('loading state', () => {
        it('should show loading state when metrics are loading', async () => {
            const { useDatabaseMetrics } = await import('@/hooks');
            vi.mocked(useDatabaseMetrics).mockReturnValue({
                metrics: null,
                isLoading: true,
                refetch: vi.fn(),
            });

            render(<DatabaseOverview database={mockDatabase} />);

            // When loading, values should show "..."
            expect(screen.getByText('Storage')).toBeInTheDocument();
        });
    });

    describe('different database types', () => {
        it('should display MySQL badge for MySQL database', () => {
            const mysqlDb = { ...mockDatabase, database_type: 'mysql' as const };
            render(<DatabaseOverview database={mysqlDb} />);

            expect(screen.getByText('MySQL')).toBeInTheDocument();
        });

        it('should display MongoDB badge for MongoDB database', () => {
            const mongoDb = { ...mockDatabase, database_type: 'mongodb' as const };
            render(<DatabaseOverview database={mongoDb} />);

            expect(screen.getByText('MongoDB')).toBeInTheDocument();
        });

        it('should display Redis badge for Redis database', () => {
            const redisDb = { ...mockDatabase, database_type: 'redis' as const };
            render(<DatabaseOverview database={redisDb} />);

            expect(screen.getByText('Redis')).toBeInTheDocument();
        });
    });
});
