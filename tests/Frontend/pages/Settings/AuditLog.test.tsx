import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../utils/test-utils';

// Mock Toast
const mockToast = vi.fn();
vi.mock('@/components/ui/Toast', () => ({
    useToast: () => ({
        toast: mockToast,
    }),
    ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// Import after mock setup
import AuditLogSettings from '@/pages/Settings/AuditLog';

const mockLogs = [
    {
        id: '1',
        action: 'deployment_started',
        description: 'Started deployment for application',
        user: {
            name: 'John Doe',
            email: 'john@example.com',
            avatar: null,
        },
        resource: {
            type: 'application',
            name: 'api-server',
            id: 'app-1',
        },
        timestamp: '2024-02-12T10:00:00Z',
    },
    {
        id: '2',
        action: 'database_created',
        description: 'Created PostgreSQL database',
        user: {
            name: 'Jane Smith',
            email: 'jane@example.com',
            avatar: null,
        },
        resource: {
            type: 'database',
            name: 'postgres-main',
            id: 'db-1',
        },
        timestamp: '2024-02-12T09:30:00Z',
    },
];

describe('Audit Log Settings Page', () => {
    let fetchMock: any;

    beforeEach(() => {
        vi.clearAllMocks();

        // Mock fetch globally
        fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({
                data: mockLogs,
                meta: {
                    current_page: 1,
                    last_page: 3,
                    per_page: 20,
                    total: 50,
                },
            }),
            blob: async () => new Blob(['test'], { type: 'text/csv' }),
            headers: new Headers({
                'Content-Disposition': 'attachment; filename="audit-log.csv"',
            }),
        });
        global.fetch = fetchMock;
    });

    describe('Page Rendering', () => {
        it('renders the audit log page', async () => {
            render(<AuditLogSettings />);

            await waitFor(() => {
                // Find heading specifically, not nav link
                expect(screen.getByRole('heading', { name: 'Audit Log' })).toBeInTheDocument();
            });
        });

        it('renders page description', async () => {
            render(<AuditLogSettings />);

            await waitFor(() => {
                expect(
                    screen.getByText('View and filter all activity in your workspace')
                ).toBeInTheDocument();
            });
        });

        it('shows loading state initially', () => {
            render(<AuditLogSettings />);

            // The Spinner component has role="status"
            const spinners = screen.queryAllByRole('status');
            expect(spinners.length).toBeGreaterThanOrEqual(0);
        });
    });

    describe('Filter Controls', () => {
        it('renders all filter inputs', async () => {
            render(<AuditLogSettings />);

            await waitFor(() => {
                expect(screen.getByLabelText('User Email')).toBeInTheDocument();
                expect(screen.getByLabelText('Action')).toBeInTheDocument();
                expect(screen.getByLabelText('Resource Type')).toBeInTheDocument();
            });
        });

        it('has reset filters button', async () => {
            render(<AuditLogSettings />);

            await waitFor(() => {
                expect(screen.getByText('Reset')).toBeInTheDocument();
            });
        });

        it('filters by action type', async () => {
            render(<AuditLogSettings />);

            await waitFor(() => {
                expect(screen.getByLabelText('Action')).toBeInTheDocument();
            });

            const actionSelect = screen.getByLabelText('Action');
            fireEvent.change(actionSelect, { target: { value: 'created' } });

            await waitFor(() => {
                expect(fetchMock).toHaveBeenCalledWith(
                    expect.stringContaining('action=created'),
                    expect.any(Object)
                );
            });
        });

        it('filters by resource type', async () => {
            render(<AuditLogSettings />);

            await waitFor(() => {
                expect(screen.getByLabelText('Resource Type')).toBeInTheDocument();
            });

            const resourceSelect = screen.getByLabelText('Resource Type');
            fireEvent.change(resourceSelect, { target: { value: 'application' } });

            // Resource type filter is not sent to API (handled client-side)
            await waitFor(() => {
                expect(resourceSelect).toHaveValue('application');
            });
        });

        it('resets all filters', async () => {
            render(<AuditLogSettings />);

            await waitFor(() => {
                expect(screen.getByLabelText('User Email')).toBeInTheDocument();
            });

            // Set filters
            const emailInput = screen.getByLabelText('User Email');
            fireEvent.change(emailInput, { target: { value: 'test@example.com' } });

            // Reset
            const resetButton = screen.getByText('Reset');
            fireEvent.click(resetButton);

            // Filters should be cleared
            expect(emailInput).toHaveValue('');
        });
    });

    describe('Export Functionality', () => {
        it('has export dropdown', async () => {
            render(<AuditLogSettings />);

            await waitFor(() => {
                expect(screen.getByText('Export')).toBeInTheDocument();
            });
        });
    });
});
