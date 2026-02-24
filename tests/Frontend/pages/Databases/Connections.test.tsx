import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../utils/test-utils';
import DatabaseConnections from '@/pages/Databases/Connections';
import { router } from '@inertiajs/react';
import type { StandaloneDatabase } from '@/types';

// Create navigator.clipboard if it doesn't exist
if (!navigator.clipboard) {
    Object.assign(navigator, {
        clipboard: {
            writeText: vi.fn(() => Promise.resolve()),
        },
    });
}

describe('Database Connections Page', () => {
    const mockDatabase: StandaloneDatabase = {
        id: 1,
        uuid: 'test-db-uuid',
        name: 'Test PostgreSQL',
        database_type: 'postgresql',
        status: { state: 'running' },
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
        connection: {
            external_host: 'db.example.com',
            internal_host: 'postgres-container',
            port: 5432,
            public_port: 54320,
            database: 'mydb',
            username: 'postgres',
            password: 'secretpassword',
        },
        internal_db_url: 'postgresql://postgres:secretpassword@postgres-container:5432/mydb',
        external_db_url: 'postgresql://postgres:secretpassword@db.example.com:54320/mydb',
        connection_pool_enabled: false,
        connection_pool_max: 100,
        connection_pool_size: 20,
    } as StandaloneDatabase;

    const mockActiveConnections = [
        {
            id: 1,
            pid: 1234,
            user: 'postgres',
            database: 'mydb',
            state: 'active' as const,
            query: 'SELECT * FROM users WHERE id = 1',
            duration: '0.5s',
            clientAddr: '192.168.1.100',
        },
        {
            id: 2,
            pid: 1235,
            user: 'app_user',
            database: 'mydb',
            state: 'idle' as const,
            query: '',
            duration: '10s',
            clientAddr: '192.168.1.101',
        },
        {
            id: 3,
            pid: 1236,
            user: 'app_user',
            database: 'mydb',
            state: 'idle in transaction' as const,
            query: 'BEGIN',
            duration: '30s',
            clientAddr: '192.168.1.102',
        },
    ];

    // Set up clipboard mock before each test
    let writeTextSpy: ReturnType<typeof vi.spyOn>;

    beforeEach(() => {
        vi.clearAllMocks();
        global.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ available: true, connections: [] }),
        });

        // Mock clipboard.writeText with a spy
        writeTextSpy = vi.spyOn(navigator.clipboard, 'writeText').mockResolvedValue(undefined);
    });

    describe('rendering', () => {
        it('should render page title and breadcrumbs', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => ({ available: true, connections: [] }),
            });

            render(<DatabaseConnections database={mockDatabase} />);

            expect(screen.getByText('Connection Management')).toBeInTheDocument();
            expect(screen.getByText('Manage connection strings and active connections')).toBeInTheDocument();
            expect(screen.getByText('Back to Database')).toBeInTheDocument();
        });
    });

    describe('internal URL section', () => {
        it('should display internal URL with recommended badge', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => ({ available: true, connections: [] }),
            });

            render(<DatabaseConnections database={mockDatabase} />);

            expect(screen.getByText('Internal URL')).toBeInTheDocument();
            expect(screen.getByText('Recommended')).toBeInTheDocument();
            expect(screen.getByText('postgresql://postgres:secretpassword@postgres-container:5432/mydb')).toBeInTheDocument();
        });

        it('should copy internal URL to clipboard when copy button is clicked', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => ({ available: true, connections: [] }),
            });

            const { user } = render(<DatabaseConnections database={mockDatabase} />);

            // Wait for the internal URL to appear
            await waitFor(() => {
                expect(screen.getByText('postgresql://postgres:secretpassword@postgres-container:5432/mydb')).toBeInTheDocument();
            });

            // Find the copy button by its title
            const copyButton = screen.getByTitle('Copy internal URL');

            await user.click(copyButton);

            // Verify clipboard was called
            expect(writeTextSpy).toHaveBeenCalledWith('postgresql://postgres:secretpassword@postgres-container:5432/mydb');
        });
    });

    describe('external URL section', () => {
        it('should display external URL when public port is available', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => ({ available: true, connections: [] }),
            });

            render(<DatabaseConnections database={mockDatabase} />);

            await waitFor(() => {
                expect(screen.getByText('External URL')).toBeInTheDocument();
                const externalUrls = screen.getAllByText('postgresql://postgres:secretpassword@db.example.com:54320/mydb');
                expect(externalUrls.length).toBeGreaterThan(0);
            });
        });
    });

    describe('connection string section', () => {
        it('should display full connection string', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => ({ available: true, connections: [] }),
            });

            render(<DatabaseConnections database={mockDatabase} />);

            expect(screen.getByText('Connection String')).toBeInTheDocument();
            expect(screen.getByText('Full Connection String')).toBeInTheDocument();
        });

        it('should display environment variables format', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => ({ available: true, connections: [] }),
            });

            render(<DatabaseConnections database={mockDatabase} />);

            expect(screen.getByText('Environment Variables Format')).toBeInTheDocument();
            expect(screen.getByText(/DATABASE_URL=/)).toBeInTheDocument();
            expect(screen.getByText(/DB_HOST=/)).toBeInTheDocument();
            expect(screen.getByText(/DB_PORT=/)).toBeInTheDocument();
        });
    });

    describe('connection details section', () => {
        it('should display all connection details', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => ({ available: true, connections: [] }),
            });

            render(<DatabaseConnections database={mockDatabase} />);

            await waitFor(() => {
                expect(screen.getByText('Connection Details')).toBeInTheDocument();
                expect(screen.getByText('Internal Host (Docker network)')).toBeInTheDocument();
                expect(screen.getByText('External Host')).toBeInTheDocument();
                expect(screen.getByText('Internal Port')).toBeInTheDocument();
                expect(screen.getByText('Public Port')).toBeInTheDocument();
                // Use getAllByText for "Database" as it appears multiple times
                const databaseLabels = screen.getAllByText('Database');
                expect(databaseLabels.length).toBeGreaterThan(0);
                expect(screen.getByText('Username')).toBeInTheDocument();
                expect(screen.getByText('Password')).toBeInTheDocument();
            });
        });

        it('should display connection detail values', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => ({ available: true, connections: [] }),
            });

            render(<DatabaseConnections database={mockDatabase} />);

            expect(screen.getByText('postgres-container')).toBeInTheDocument();
            expect(screen.getByText('db.example.com')).toBeInTheDocument();
            expect(screen.getByText('5432')).toBeInTheDocument();
            expect(screen.getByText('54320')).toBeInTheDocument();
            expect(screen.getByText('mydb')).toBeInTheDocument();
            expect(screen.getByText('postgres')).toBeInTheDocument();
        });

        it('should hide password by default', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => ({ available: true, connections: [] }),
            });

            render(<DatabaseConnections database={mockDatabase} />);

            expect(screen.getByText('••••••••••••••••••••')).toBeInTheDocument();
            expect(screen.queryByText('secretpassword')).not.toBeInTheDocument();
        });

        it('should show password when eye icon is clicked', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => ({ available: true, connections: [] }),
            });

            const { user } = render(<DatabaseConnections database={mockDatabase} />);

            const showPasswordButton = screen.getByTitle('Show password');
            await user.click(showPasswordButton);

            await waitFor(() => {
                expect(screen.getByText('secretpassword')).toBeInTheDocument();
            });
        });
    });

    describe('connection pooling section', () => {
        it('should display connection pooling settings', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => ({ available: true, connections: [] }),
            });

            render(<DatabaseConnections database={mockDatabase} />);

            expect(screen.getByText('Connection Pooling')).toBeInTheDocument();
            expect(screen.getByText('Enable connection pooling')).toBeInTheDocument();
        });

        it('should show pool settings when pooling is enabled', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => ({ available: true, connections: [] }),
            });

            const { user } = render(<DatabaseConnections database={mockDatabase} />);

            const checkbox = screen.getByRole('checkbox');
            await user.click(checkbox);

            await waitFor(() => {
                expect(screen.getByText('Pool Size')).toBeInTheDocument();
                expect(screen.getByText('Max Connections')).toBeInTheDocument();
            });
        });

        it('should save settings when save button is clicked', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => ({ available: true, connections: [] }),
            });

            const { user } = render(<DatabaseConnections database={mockDatabase} />);

            const saveButton = screen.getByText('Save Settings');
            await user.click(saveButton);

            await waitFor(() => {
                expect(router.patch).toHaveBeenCalledWith(
                    '/databases/test-db-uuid',
                    expect.objectContaining({
                        connection_pool_enabled: false,
                        connection_pool_size: 20,
                        connection_pool_max: 100,
                    }),
                    expect.any(Object)
                );
            });
        });
    });

    describe('active connections section', () => {
        it('should fetch and display active connections', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => ({ available: true, connections: mockActiveConnections }),
            });

            render(<DatabaseConnections database={mockDatabase} />);

            await waitFor(() => {
                expect(screen.getByText('Active Connections')).toBeInTheDocument();
                expect(screen.getByText('3 active connections')).toBeInTheDocument();
            });
        });

        it('should display connection details in table', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => ({ available: true, connections: mockActiveConnections }),
            });

            render(<DatabaseConnections database={mockDatabase} />);

            await waitFor(() => {
                expect(screen.getByText('1234')).toBeInTheDocument();
                const appUserElements = screen.getAllByText('app_user');
                expect(appUserElements.length).toBeGreaterThan(0);
                expect(screen.getByText('SELECT * FROM users WHERE id = 1')).toBeInTheDocument();
                expect(screen.getByText('192.168.1.100')).toBeInTheDocument();
            });
        });

        it('should display connection state badges', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => ({ available: true, connections: mockActiveConnections }),
            });

            render(<DatabaseConnections database={mockDatabase} />);

            await waitFor(() => {
                expect(screen.getByText('Active')).toBeInTheDocument();
                expect(screen.getByText('Idle')).toBeInTheDocument();
                expect(screen.getByText('In Transaction')).toBeInTheDocument();
            });
        });

        it('should refresh connections when refresh button is clicked', async () => {
            (global.fetch as any)
                .mockResolvedValueOnce({
                    ok: true,
                    json: async () => ({ available: true, connections: mockActiveConnections }),
                })
                .mockResolvedValueOnce({
                    ok: true,
                    json: async () => ({ available: true, connections: mockActiveConnections }),
                });

            const { user } = render(<DatabaseConnections database={mockDatabase} />);

            await waitFor(() => {
                expect(screen.getByText('3 active connections')).toBeInTheDocument();
            });

            const refreshButton = screen.getByText('Refresh');
            await user.click(refreshButton);

            await waitFor(() => {
                expect(global.fetch).toHaveBeenCalledTimes(2);
            });
        });

        it('should display kill button for each connection', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => ({ available: true, connections: mockActiveConnections }),
            });

            render(<DatabaseConnections database={mockDatabase} />);

            await waitFor(() => {
                const killButtons = screen.getAllByText('Kill');
                expect(killButtons).toHaveLength(3);
            });
        });

        it('should show confirmation modal when kill button is clicked', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => ({ available: true, connections: mockActiveConnections }),
            });

            const { user } = render(<DatabaseConnections database={mockDatabase} />);

            await waitFor(() => {
                expect(screen.getByText('1234')).toBeInTheDocument();
            });

            const killButtons = screen.getAllByText('Kill');
            await user.click(killButtons[0]);

            await waitFor(() => {
                const killConnectionElements = screen.getAllByText('Kill Connection');
                expect(killConnectionElements.length).toBeGreaterThan(0);
                expect(screen.getByText('Are you sure you want to kill this connection? This action cannot be undone.')).toBeInTheDocument();
            });
        });
    });

    describe('copy functionality', () => {
        it('should show "Copied to clipboard!" message after copying', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => ({ available: true, connections: [] }),
            });

            const { user } = render(<DatabaseConnections database={mockDatabase} />);

            const internalHostText = screen.getByText('postgres-container');
            const copyButton = internalHostText.parentElement?.querySelector('button');

            if (copyButton) {
                await user.click(copyButton);

                await waitFor(() => {
                    expect(screen.getByText('Copied to clipboard!')).toBeInTheDocument();
                });
            }
        });
    });

    describe('different database types', () => {
        it('should use correct default port for MySQL', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => ({ available: true, connections: [] }),
            });

            const mysqlDb = {
                ...mockDatabase,
                database_type: 'mysql' as const,
                connection: {
                    ...mockDatabase.connection,
                    port: undefined,
                },
            };

            render(<DatabaseConnections database={mysqlDb} />);

            expect(screen.getByText('3306')).toBeInTheDocument();
        });

        it('should use correct default port for MongoDB', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => ({ available: true, connections: [] }),
            });

            const mongoDb = {
                ...mockDatabase,
                database_type: 'mongodb' as const,
                connection: {
                    ...mockDatabase.connection,
                    port: undefined,
                },
            };

            render(<DatabaseConnections database={mongoDb} />);

            expect(screen.getByText('27017')).toBeInTheDocument();
        });

        it('should use correct default port for Redis', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => ({ available: true, connections: [] }),
            });

            const redisDb = {
                ...mockDatabase,
                database_type: 'redis' as const,
                connection: {
                    ...mockDatabase.connection,
                    port: undefined,
                },
            };

            render(<DatabaseConnections database={redisDb} />);

            expect(screen.getByText('6379')).toBeInTheDocument();
        });
    });
});
