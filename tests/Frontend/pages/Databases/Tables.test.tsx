import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../utils/test-utils';
import DatabaseTables from '@/pages/Databases/Tables';
import type { StandaloneDatabase } from '@/types';

// Note: global.fetch is set up in beforeEach with a default implementation

// Mock localStorage
const localStorageMock = (() => {
    let store: Record<string, string> = {};
    return {
        getItem: (key: string) => store[key] || null,
        setItem: (key: string, value: string) => {
            store[key] = value;
        },
        clear: () => {
            store = {};
        },
    };
})();

Object.defineProperty(window, 'localStorage', {
    value: localStorageMock,
});

// Mock TableDataViewer component
vi.mock('@/components/features/TableDataViewer', () => ({
    TableDataViewer: ({ tableName }: { tableName: string }) => (
        <div data-testid="table-data-viewer">Table data for {tableName}</div>
    ),
}));

describe('Database Tables Page', () => {
    const mockDatabase: StandaloneDatabase = {
        id: 1,
        uuid: 'test-db-uuid',
        name: 'Test PostgreSQL',
        database_type: 'postgresql',
        status: { state: 'running' },
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
    } as StandaloneDatabase;

    const mockTablesResponse = {
        available: true,
        tables: [
            { name: 'users', rows: 1500, size: '256 KB' },
            { name: 'posts', rows: 5000, size: '1.2 MB' },
            { name: 'comments', rows: 12000, size: '2.5 MB' },
        ],
    };

    const mockColumnsResponse = {
        success: true,
        columns: [
            { name: 'id', type: 'integer', nullable: false, default: null, is_primary: true },
            { name: 'email', type: 'varchar(255)', nullable: false, default: null, is_primary: false },
            { name: 'created_at', type: 'timestamp', nullable: true, default: 'CURRENT_TIMESTAMP', is_primary: false },
        ],
    };

    beforeEach(() => {
        vi.clearAllMocks();
        localStorageMock.clear();
        // Provide default fetch that returns empty tables
        global.fetch = vi.fn().mockResolvedValue({
            json: async () => ({ available: true, tables: [] }),
        });
    });

    describe('rendering', () => {
        it('should render page title and breadcrumbs', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                json: async () => mockTablesResponse,
            });

            render(<DatabaseTables database={mockDatabase} />);

            expect(screen.getByText('Database Tables')).toBeInTheDocument();
            expect(screen.getByText('Browse tables and explore schemas')).toBeInTheDocument();
        });

        it('should display refresh and query browser buttons', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                json: async () => mockTablesResponse,
            });

            render(<DatabaseTables database={mockDatabase} />);

            expect(screen.getByText('Refresh')).toBeInTheDocument();
            expect(screen.getByText('Query Browser')).toBeInTheDocument();
        });
    });

    describe('tables list', () => {
        it('should fetch and display tables on mount', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                json: async () => mockTablesResponse,
            });

            render(<DatabaseTables database={mockDatabase} />);

            await waitFor(() => {
                expect(global.fetch).toHaveBeenCalledWith('/_internal/databases/test-db-uuid/tables', expect.any(Object));
            });

            await waitFor(() => {
                expect(screen.getByText('users')).toBeInTheDocument();
                expect(screen.getByText('posts')).toBeInTheDocument();
                expect(screen.getByText('comments')).toBeInTheDocument();
            });
        });

        it('should display table row counts', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                json: async () => mockTablesResponse,
            });

            render(<DatabaseTables database={mockDatabase} />);

            await waitFor(() => {
                expect(screen.getByText('1,500 rows')).toBeInTheDocument();
                expect(screen.getByText('5,000 rows')).toBeInTheDocument();
                expect(screen.getByText('12,000 rows')).toBeInTheDocument();
            });
        });

        it('should display table sizes', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                json: async () => mockTablesResponse,
            });

            render(<DatabaseTables database={mockDatabase} />);

            await waitFor(() => {
                expect(screen.getByText('256.0 KB')).toBeInTheDocument();
                expect(screen.getByText('1.2 MB')).toBeInTheDocument();
                expect(screen.getByText('2.5 MB')).toBeInTheDocument();
            });
        });

        it('should show total table count', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                json: async () => mockTablesResponse,
            });

            const { container } = render(<DatabaseTables database={mockDatabase} />);

            await waitFor(() => {
                // Find the stats section with table count
                const statsEl = container.querySelector('.text-xl.font-bold');
                expect(statsEl).not.toBeNull();
                expect(statsEl?.textContent).toBe('3');
            });
        });

        it('should show loading state while fetching', () => {
            (global.fetch as any).mockImplementationOnce(() => new Promise(() => {}));

            render(<DatabaseTables database={mockDatabase} />);

            // Should show loading spinner
            const refreshIcon = screen.getByText('Refresh').parentElement?.querySelector('svg');
            expect(refreshIcon).toHaveClass('animate-spin');
        });

        it('should show error state when fetch fails', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                json: async () => ({ available: false, error: 'Connection failed' }),
            });

            render(<DatabaseTables database={mockDatabase} />);

            await waitFor(() => {
                expect(screen.getByText('Connection failed')).toBeInTheDocument();
            });
        });

        it('should show empty state when no tables exist', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                json: async () => ({ available: true, tables: [] }),
            });

            render(<DatabaseTables database={mockDatabase} />);

            await waitFor(() => {
                expect(screen.getByText('No tables found')).toBeInTheDocument();
            });
        });
    });

    describe('search functionality', () => {
        it('should filter tables by search query', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                json: async () => mockTablesResponse,
            });

            const { user } = render(<DatabaseTables database={mockDatabase} />);

            await waitFor(() => {
                expect(screen.getByText('users')).toBeInTheDocument();
            });

            const searchInput = screen.getByPlaceholderText('Search tables...');
            await user.type(searchInput, 'post');

            expect(screen.getByText('posts')).toBeInTheDocument();
            expect(screen.queryByText('users')).not.toBeInTheDocument();
            expect(screen.queryByText('comments')).not.toBeInTheDocument();
        });
    });

    describe('table selection', () => {
        it('should show empty state when no table is selected', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                json: async () => mockTablesResponse,
            });

            render(<DatabaseTables database={mockDatabase} />);

            await waitFor(() => {
                expect(screen.getByText('No Table Selected')).toBeInTheDocument();
                expect(screen.getByText('Select a table from the list to view its schema and details.')).toBeInTheDocument();
            });
        });

        it('should display table details when table is selected', async () => {
            (global.fetch as any)
                .mockResolvedValueOnce({
                    json: async () => mockTablesResponse,
                })
                .mockResolvedValueOnce({
                    json: async () => mockColumnsResponse,
                });

            const { user } = render(<DatabaseTables database={mockDatabase} />);

            await waitFor(() => {
                expect(screen.getByText('users')).toBeInTheDocument();
            });

            const usersButton = screen.getByText('users').closest('button');
            if (usersButton) {
                await user.click(usersButton);
            }

            await waitFor(() => {
                expect(screen.getByText('Schema')).toBeInTheDocument();
                expect(screen.getByText('Data')).toBeInTheDocument();
            });
        });

        it('should fetch columns when table is selected', async () => {
            (global.fetch as any)
                .mockResolvedValueOnce({
                    json: async () => mockTablesResponse,
                })
                .mockResolvedValueOnce({
                    json: async () => mockColumnsResponse,
                });

            const { user } = render(<DatabaseTables database={mockDatabase} />);

            await waitFor(() => {
                expect(screen.getByText('users')).toBeInTheDocument();
            });

            const usersButton = screen.getByText('users').closest('button');
            if (usersButton) {
                await user.click(usersButton);
            }

            await waitFor(() => {
                expect(global.fetch).toHaveBeenCalledWith('/_internal/databases/test-db-uuid/tables/users/columns');
            });
        });
    });

    describe('schema tab', () => {
        it('should display columns table in schema tab', async () => {
            (global.fetch as any)
                .mockResolvedValueOnce({
                    json: async () => mockTablesResponse,
                })
                .mockResolvedValueOnce({
                    json: async () => mockColumnsResponse,
                });

            const { user } = render(<DatabaseTables database={mockDatabase} />);

            await waitFor(() => {
                expect(screen.getByText('users')).toBeInTheDocument();
            });

            const usersButton = screen.getByText('users').closest('button');
            if (usersButton) {
                await user.click(usersButton);
            }

            // Switch to Schema tab (default is Data)
            await waitFor(() => {
                expect(screen.getByText('Schema')).toBeInTheDocument();
            });
            await user.click(screen.getByText('Schema'));

            await waitFor(() => {
                expect(screen.getByText('Columns')).toBeInTheDocument();
                expect(screen.getByText('id')).toBeInTheDocument();
                expect(screen.getByText('email')).toBeInTheDocument();
                expect(screen.getByText('created_at')).toBeInTheDocument();
            });
        });

        it('should display column types', async () => {
            (global.fetch as any)
                .mockResolvedValueOnce({
                    json: async () => mockTablesResponse,
                })
                .mockResolvedValueOnce({
                    json: async () => mockColumnsResponse,
                });

            const { user } = render(<DatabaseTables database={mockDatabase} />);

            await waitFor(() => {
                expect(screen.getByText('users')).toBeInTheDocument();
            });

            const usersButton = screen.getByText('users').closest('button');
            if (usersButton) {
                await user.click(usersButton);
            }

            // Switch to Schema tab
            await waitFor(() => {
                expect(screen.getByText('Schema')).toBeInTheDocument();
            });
            await user.click(screen.getByText('Schema'));

            await waitFor(() => {
                expect(screen.getByText('integer')).toBeInTheDocument();
                expect(screen.getByText('varchar(255)')).toBeInTheDocument();
                expect(screen.getByText('timestamp')).toBeInTheDocument();
            });
        });

        it('should show primary key badge', async () => {
            (global.fetch as any)
                .mockResolvedValueOnce({
                    json: async () => mockTablesResponse,
                })
                .mockResolvedValueOnce({
                    json: async () => mockColumnsResponse,
                });

            const { user } = render(<DatabaseTables database={mockDatabase} />);

            await waitFor(() => {
                expect(screen.getByText('users')).toBeInTheDocument();
            });

            const usersButton = screen.getByText('users').closest('button');
            if (usersButton) {
                await user.click(usersButton);
            }

            // Switch to Schema tab
            await waitFor(() => {
                expect(screen.getByText('Schema')).toBeInTheDocument();
            });
            await user.click(screen.getByText('Schema'));

            await waitFor(() => {
                expect(screen.getByText('PK')).toBeInTheDocument();
            });
        });
    });

    describe('data tab', () => {
        it('should switch to data tab when clicked', async () => {
            (global.fetch as any)
                .mockResolvedValueOnce({
                    json: async () => mockTablesResponse,
                })
                .mockResolvedValueOnce({
                    json: async () => mockColumnsResponse,
                });

            const { user } = render(<DatabaseTables database={mockDatabase} />);

            await waitFor(() => {
                expect(screen.getByText('users')).toBeInTheDocument();
            });

            const usersButton = screen.getByText('users').closest('button');
            if (usersButton) {
                await user.click(usersButton);
            }

            await waitFor(() => {
                expect(screen.getByText('Data')).toBeInTheDocument();
            });

            const dataTabButton = screen.getAllByText('Data')[0].closest('button');
            if (dataTabButton) {
                await user.click(dataTabButton);
            }

            await waitFor(() => {
                expect(screen.getByTestId('table-data-viewer')).toBeInTheDocument();
                expect(screen.getByText('Table data for users')).toBeInTheDocument();
            });
        });
    });

    describe('sidebar', () => {
        it('should allow collapsing the sidebar', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                json: async () => mockTablesResponse,
            });

            const { user } = render(<DatabaseTables database={mockDatabase} />);

            await waitFor(() => {
                expect(screen.getByText('users')).toBeInTheDocument();
            });

            const collapseButton = screen.getByTitle('Hide sidebar');
            await user.click(collapseButton);

            // Sidebar should be hidden
            expect(localStorageMock.getItem('tables-sidebar-collapsed')).toBe('true');
        });
    });

    describe('refresh functionality', () => {
        it('should refetch tables when refresh button is clicked', async () => {
            (global.fetch as any)
                .mockResolvedValueOnce({
                    json: async () => mockTablesResponse,
                })
                .mockResolvedValueOnce({
                    json: async () => mockTablesResponse,
                });

            const { user } = render(<DatabaseTables database={mockDatabase} />);

            await waitFor(() => {
                expect(screen.getByText('users')).toBeInTheDocument();
            });

            const refreshButton = screen.getByText('Refresh');
            await user.click(refreshButton);

            await waitFor(() => {
                expect(global.fetch).toHaveBeenCalledTimes(2);
            });
        });
    });
});
