import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../../utils/test-utils';
import { RedisPanel } from '@/components/features/databases/RedisPanel';
import type { StandaloneDatabase } from '@/types';

// Mock hooks module before imports
vi.mock('@/hooks', () => ({
    useDatabaseMetrics: vi.fn(() => ({
        metrics: {
            totalKeys: 152,
            memoryUsed: '1.2 MB',
            opsPerSec: 250,
            hitRate: '95.5%',
        },
        isLoading: false,
        refetch: vi.fn(),
    })),
    useDatabaseLogs: vi.fn(() => ({
        logs: [],
        isLoading: false,
        refetch: vi.fn(),
    })),
    useRedisKeys: vi.fn(() => ({
        keys: [],
        isLoading: false,
        refetch: vi.fn(),
    })),
    useRedisMemory: vi.fn(() => ({
        memory: {
            usedMemory: '1.2 MB',
            peakMemory: '2.5 MB',
            fragmentationRatio: '1.05',
            maxMemory: '2 GB',
            evictionPolicy: 'noeviction',
        },
        isLoading: false,
        refetch: vi.fn(),
    })),
    useRedisFlush: vi.fn(() => ({
        flush: vi.fn(async () => true),
        isLoading: false,
    })),
    useRedisPersistence: vi.fn(() => ({
        persistence: {
            rdbEnabled: true,
            rdbSaveRules: '3600:1,300:100,60:10000',
            rdbLastSaveTime: '2024-01-01 12:00:00',
            rdbLastBgsaveStatus: 'ok',
            aofEnabled: false,
            aofFsync: 'everysec',
        },
        isLoading: false,
        refetch: vi.fn(),
    })),
    useRedisKeyValue: vi.fn(() => ({
        keyValue: null,
        isLoading: false,
        fetchKeyValue: vi.fn(async () => null),
    })),
    useRedisSetKeyValue: vi.fn(() => ({
        setKeyValue: vi.fn(async () => true),
        isLoading: false,
    })),
    formatMetricValue: vi.fn((value, suffix = '') => {
        if (value === null || value === undefined) return 'N/A';
        return `${value}${suffix}`;
    }),
    formatRdbSaveRules: vi.fn((rules) => rules || 'N/A'),
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
    name: 'test-redis',
    type: 'redis',
    database_type: 'redis',
    description: 'Test Redis database',
    environment_id: 1,
    destination_id: 1,
    status: 'running',
    created_at: '2024-01-01',
    updated_at: '2024-01-01',
    config: {},
    internal_db_url: 'redis://:password@localhost:6379',
    connection: {
        internal_host: 'localhost',
        port: '6379',
    },
    redis_password: 'test-password',
};

describe('RedisPanel', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render tabs for Redis', () => {
            render(<RedisPanel database={mockDatabase} />);

            expect(screen.getByText('Overview')).toBeInTheDocument();
            expect(screen.getByText('Keys')).toBeInTheDocument();
            expect(screen.getByText('Settings')).toBeInTheDocument();
            expect(screen.getByText('Logs')).toBeInTheDocument();
        });

        it('should render Overview tab by default', () => {
            render(<RedisPanel database={mockDatabase} />);

            expect(screen.getByText('Connection String')).toBeInTheDocument();
            expect(screen.getByText('Database Statistics')).toBeInTheDocument();
        });

        it('should display Redis-specific statistics', () => {
            render(<RedisPanel database={mockDatabase} />);

            expect(screen.getByText('Total Keys')).toBeInTheDocument();
            expect(screen.getByText('Memory Used')).toBeInTheDocument();
            expect(screen.getByText('Ops/sec')).toBeInTheDocument();
            expect(screen.getByText('Hit Rate')).toBeInTheDocument();
        });
    });

    describe('Overview tab', () => {
        it('should display connection details', () => {
            render(<RedisPanel database={mockDatabase} />);

            expect(screen.getByText('Host')).toBeInTheDocument();
            expect(screen.getByText('Port')).toBeInTheDocument();
            expect(screen.getByText('Database')).toBeInTheDocument();
            expect(screen.getByText('Password')).toBeInTheDocument();
        });

        it('should use default Redis port 6379', () => {
            render(<RedisPanel database={mockDatabase} />);

            const portValue = screen.getByText('6379');
            expect(portValue).toBeInTheDocument();
        });

        it('should use Redis connection string format', () => {
            render(<RedisPanel database={mockDatabase} />);

            const connectionString = screen.getByText(/redis:\/\//);
            expect(connectionString).toBeInTheDocument();
        });

        it('should display memory usage information', () => {
            render(<RedisPanel database={mockDatabase} />);

            expect(screen.getByText('Memory Usage')).toBeInTheDocument();
        });
    });

    describe('Keys tab', () => {
        it('should display keys list', async () => {
            const { user } = render(<RedisPanel database={mockDatabase} />);

            await user.click(screen.getByText('Keys'));

            await waitFor(() => {
                expect(screen.getByText('Key Browser')).toBeInTheDocument();
            });
        });

        it('should display Create Key button', async () => {
            const { user } = render(<RedisPanel database={mockDatabase} />);

            await user.click(screen.getByText('Keys'));

            await waitFor(() => {
                expect(screen.getByText('Create Key')).toBeInTheDocument();
            });
        });

        it('should display search input for keys', async () => {
            const { user } = render(<RedisPanel database={mockDatabase} />);

            await user.click(screen.getByText('Keys'));

            await waitFor(() => {
                const searchInput = screen.getByPlaceholderText(/Search keys/);
                expect(searchInput).toBeInTheDocument();
            });
        });

        it('should display Refresh button in keys tab', async () => {
            const { user } = render(<RedisPanel database={mockDatabase} />);

            await user.click(screen.getByText('Keys'));

            await waitFor(() => {
                const refreshButtons = screen.getAllByRole('button', { name: /Refresh/i });
                expect(refreshButtons.length).toBeGreaterThan(0);
            });
        });
    });

    describe('Settings tab', () => {
        it('should display Redis settings', async () => {
            const { user } = render(<RedisPanel database={mockDatabase} />);

            await user.click(screen.getByText('Settings'));

            await waitFor(() => {
                expect(screen.getByText('Persistence Settings')).toBeInTheDocument();
            });
        });
    });

    describe('Logs tab', () => {
        it('should display recent logs', async () => {
            const { user } = render(<RedisPanel database={mockDatabase} />);

            await user.click(screen.getByText('Logs'));

            await waitFor(() => {
                expect(screen.getByText('Recent Logs')).toBeInTheDocument();
            });
        });
    });

});
