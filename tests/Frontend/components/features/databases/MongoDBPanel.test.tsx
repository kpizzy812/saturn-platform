import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../../utils/test-utils';
import { MongoDBPanel } from '@/components/features/databases/MongoDBPanel';
import type { StandaloneDatabase } from '@/types';

// Mock hooks module before imports
vi.mock('@/hooks', () => ({
    useDatabaseMetrics: vi.fn(() => ({
        metrics: {
            collections: 5,
            documents: 1234,
            databaseSize: '128 MB',
            indexSize: '24 MB',
        },
        isLoading: false,
        refetch: vi.fn(),
    })),
    useDatabaseLogs: vi.fn(() => ({
        logs: [
            { timestamp: '2024-01-01 12:00:00', level: 'INFO', message: 'Database started' },
        ],
        isLoading: false,
        refetch: vi.fn(),
    })),
    useMongoCollections: vi.fn(() => ({
        collections: [],
        isLoading: false,
        refetch: vi.fn(),
    })),
    useMongoIndexes: vi.fn(() => ({
        indexes: [],
        isLoading: false,
        refetch: vi.fn(),
    })),
    useMongoReplicaSet: vi.fn(() => ({
        replicaSet: null,
        isLoading: false,
        refetch: vi.fn(),
    })),
    useMongoStorageSettings: vi.fn(() => ({
        settings: {
            storageEngine: 'WiredTiger',
            cacheSize: 'Default (50% RAM)',
            journalEnabled: true,
            directoryPerDb: false,
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
    name: 'test-mongodb',
    type: 'mongodb',
    description: 'Test MongoDB database',
    environment_id: 1,
    destination_id: 1,
    status: 'running',
    created_at: '2024-01-01',
    updated_at: '2024-01-01',
    internal_db_url: 'mongodb://admin:password@localhost:27017/test-mongodb',
    connection: {
        internal_host: 'localhost',
        port: '27017',
        username: 'admin',
        password: 'password',
    },
};

describe('MongoDBPanel', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render tabs for MongoDB', () => {
            render(<MongoDBPanel database={mockDatabase} />);

            expect(screen.getByText('Overview')).toBeInTheDocument();
            // Collections appears in both tabs and stats, so check for multiple
            expect(screen.getAllByText('Collections').length).toBeGreaterThan(0);
            expect(screen.getByText('Indexes')).toBeInTheDocument();
            expect(screen.getByText('Settings')).toBeInTheDocument();
            expect(screen.getByText('Logs')).toBeInTheDocument();
        });

        it('should render Overview tab by default', () => {
            render(<MongoDBPanel database={mockDatabase} />);

            expect(screen.getByText('Connection String')).toBeInTheDocument();
            expect(screen.getByText('Database Statistics')).toBeInTheDocument();
        });
    });

    describe('Overview tab', () => {
        it('should display MongoDB-specific statistics', () => {
            render(<MongoDBPanel database={mockDatabase} />);

            // Collections appears in both tabs and stats
            expect(screen.getAllByText('Collections').length).toBeGreaterThan(0);
            expect(screen.getByText('Documents')).toBeInTheDocument();
            expect(screen.getByText('Database Size')).toBeInTheDocument();
            expect(screen.getByText('Index Size')).toBeInTheDocument();
        });

        it('should display connection details', () => {
            render(<MongoDBPanel database={mockDatabase} />);

            expect(screen.getByText('Host')).toBeInTheDocument();
            expect(screen.getByText('Port')).toBeInTheDocument();
            expect(screen.getByText('Database')).toBeInTheDocument();
            expect(screen.getByText('Username')).toBeInTheDocument();
            expect(screen.getByText('Password')).toBeInTheDocument();
        });

        it('should use MongoDB connection string format', () => {
            render(<MongoDBPanel database={mockDatabase} />);

            const connectionString = screen.getByText(/mongodb:\/\//);
            expect(connectionString).toBeInTheDocument();
        });

        it('should use default MongoDB port 27017', () => {
            render(<MongoDBPanel database={mockDatabase} />);

            // Port appears in both connection string and port field, so use getAllByText
            const portElements = screen.getAllByText(/27017/);
            expect(portElements.length).toBeGreaterThan(0);
        });
    });

    describe('Collections tab', () => {
        it('should display collections list', async () => {
            const { user } = render(<MongoDBPanel database={mockDatabase} />);

            // Get all Collections text elements and click the tab (first one in tabs)
            const collectionsElements = screen.getAllByText('Collections');
            await user.click(collectionsElements[0]);

            await waitFor(() => {
                expect(screen.getByText('Collections Browser')).toBeInTheDocument();
            });
        });

        it('should have refresh button', async () => {
            const { user } = render(<MongoDBPanel database={mockDatabase} />);

            const collectionsElements = screen.getAllByText('Collections');
            await user.click(collectionsElements[0]);

            await waitFor(() => {
                expect(screen.getByText('Refresh')).toBeInTheDocument();
            });
        });
    });

    describe('Indexes tab', () => {
        it('should display indexes', async () => {
            const { user } = render(<MongoDBPanel database={mockDatabase} />);

            await user.click(screen.getByText('Indexes'));

            await waitFor(() => {
                expect(screen.getByText('Index Management')).toBeInTheDocument();
            });
        });

        it('should have create index button', async () => {
            const { user } = render(<MongoDBPanel database={mockDatabase} />);

            await user.click(screen.getByText('Indexes'));

            await waitFor(() => {
                expect(screen.getByText('Create Index')).toBeInTheDocument();
            });
        });
    });

    describe('Settings tab', () => {
        it('should display MongoDB settings', async () => {
            const { user } = render(<MongoDBPanel database={mockDatabase} />);

            await user.click(screen.getByText('Settings'));

            await waitFor(() => {
                expect(screen.getByText('Replica Set Status')).toBeInTheDocument();
            });
        });

        it('should display storage settings', async () => {
            const { user } = render(<MongoDBPanel database={mockDatabase} />);

            await user.click(screen.getByText('Settings'));

            await waitFor(() => {
                expect(screen.getByText('Storage Settings')).toBeInTheDocument();
            });
        });
    });

    describe('Logs tab', () => {
        it('should display recent logs', async () => {
            const { user } = render(<MongoDBPanel database={mockDatabase} />);

            await user.click(screen.getByText('Logs'));

            await waitFor(() => {
                expect(screen.getByText('Recent Logs')).toBeInTheDocument();
            });
        });

        it('should show log levels', async () => {
            const { user } = render(<MongoDBPanel database={mockDatabase} />);

            await user.click(screen.getByText('Logs'));

            await waitFor(() => {
                expect(screen.getAllByText('INFO').length).toBeGreaterThan(0);
            });
        });
    });
});
