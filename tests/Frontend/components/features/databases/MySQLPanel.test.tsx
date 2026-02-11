import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../../utils/test-utils';
import { MySQLPanel } from '@/components/features/databases/MySQLPanel';
import type { StandaloneDatabase } from '@/types';

// Mock hooks module before imports
vi.mock('@/hooks', () => ({
    useDatabaseMetrics: vi.fn(() => ({
        metrics: {
            activeConnections: 15,
            maxConnections: 150,
            databaseSize: '512 MB',
            queriesPerSec: 125,
            slowQueries: 3,
        },
        isLoading: false,
        refetch: vi.fn(),
    })),
    useDatabaseLogs: vi.fn(() => ({
        logs: [],
        isLoading: false,
        refetch: vi.fn(),
    })),
    useDatabaseUsers: vi.fn(() => ({
        users: [],
        isLoading: false,
        refetch: vi.fn(),
    })),
    useMysqlSettings: vi.fn(() => ({
        settings: {
            slowQueryLog: true,
            binaryLogging: false,
            maxConnections: 150,
            innodbBufferPoolSize: '128M',
            queryCacheSize: 'N/A (deprecated in MySQL 8.0)',
            queryTimeout: 28800,
        },
        isLoading: false,
        refetch: vi.fn(),
    })),
    formatMetricValue: vi.fn((value, suffix = '') => {
        if (value === null || value === undefined) return 'N/A';
        return `${value}${suffix}`;
    }),
}));

// Mock clipboard API
const writeTextMock = vi.fn().mockResolvedValue(undefined);

// Delete existing clipboard if it exists and create new one
delete (navigator as any).clipboard;
Object.defineProperty(navigator, 'clipboard', {
    value: {
        writeText: writeTextMock,
    },
    writable: true,
    configurable: true,
});

const mockDatabase: StandaloneDatabase = {
    id: 1,
    uuid: 'db-1',
    name: 'test-mysql',
    type: 'mysql',
    description: 'Test MySQL database',
    environment_id: 1,
    destination_id: 1,
    status: 'running',
    created_at: '2024-01-01',
    updated_at: '2024-01-01',
    internal_db_url: 'mysql://root:password@localhost:3306/test-mysql',
    connection: {
        internal_host: 'localhost',
        port: '3306',
        username: 'root',
        password: 'password',
    },
};

describe('MySQLPanel', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render tabs for MySQL', () => {
            render(<MySQLPanel database={mockDatabase} />);

            expect(screen.getByText('Overview')).toBeInTheDocument();
            expect(screen.getByText('Users')).toBeInTheDocument();
            expect(screen.getByText('Settings')).toBeInTheDocument();
            expect(screen.getByText('Logs')).toBeInTheDocument();
        });

        it('should render Overview tab by default', () => {
            render(<MySQLPanel database={mockDatabase} />);

            expect(screen.getByText('Connection String')).toBeInTheDocument();
            expect(screen.getByText('Database Statistics')).toBeInTheDocument();
        });
    });

    describe('Overview tab', () => {
        it('should display MySQL-specific statistics', () => {
            render(<MySQLPanel database={mockDatabase} />);

            expect(screen.getByText('Active Connections')).toBeInTheDocument();
            expect(screen.getByText('Database Size')).toBeInTheDocument();
            expect(screen.getByText('Queries/sec')).toBeInTheDocument();
            expect(screen.getByText('Slow Queries')).toBeInTheDocument();
        });

        it('should display connection details', () => {
            render(<MySQLPanel database={mockDatabase} />);

            expect(screen.getByText('Host')).toBeInTheDocument();
            expect(screen.getByText('Port')).toBeInTheDocument();
            expect(screen.getByText('Database')).toBeInTheDocument();
            expect(screen.getByText('Username')).toBeInTheDocument();
            expect(screen.getByText('Password')).toBeInTheDocument();
        });

        it('should use MySQL connection string format', () => {
            render(<MySQLPanel database={mockDatabase} />);

            const connectionString = screen.getByText(/mysql:\/\//);
            expect(connectionString).toBeInTheDocument();
        });
    });

    describe('Users tab', () => {
        it('should display database users', async () => {
            const { user } = render(<MySQLPanel database={mockDatabase} />);

            await user.click(screen.getByText('Users'));

            await waitFor(() => {
                expect(screen.getByText('Database Users')).toBeInTheDocument();
            });
        });
    });

    describe('Settings tab', () => {
        it('should display MySQL logging settings', async () => {
            const { user } = render(<MySQLPanel database={mockDatabase} />);

            await user.click(screen.getByText('Settings'));

            await waitFor(() => {
                expect(screen.getByText('Logging Settings')).toBeInTheDocument();
            });
        });

        it('should display slow query log option', async () => {
            const { user } = render(<MySQLPanel database={mockDatabase} />);

            await user.click(screen.getByText('Settings'));

            await waitFor(() => {
                expect(screen.getByText('Slow Query Log')).toBeInTheDocument();
                expect(screen.getByText('Binary Logging')).toBeInTheDocument();
            });
        });
    });

    describe('Logs tab', () => {
        it('should display recent logs', async () => {
            const { user } = render(<MySQLPanel database={mockDatabase} />);

            await user.click(screen.getByText('Logs'));

            await waitFor(() => {
                expect(screen.getByText('Recent Logs')).toBeInTheDocument();
            });
        });
    });
});
