import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';
import ObservabilityLogs from '../Logs';

vi.mock('@inertiajs/react', () => ({
    Link: ({ children, href, ...props }: any) => (
        <a href={href} {...props}>{children}</a>
    ),
    router: { visit: vi.fn(), post: vi.fn() },
    usePage: () => ({
        props: {
            auth: { name: 'Test User', email: 'test@test.com' },
            notifications: { unreadCount: 0, recent: [] },
        },
    }),
}));

vi.mock('@/components/layout', () => ({
    AppLayout: ({ children }: any) => <div data-testid="app-layout">{children}</div>,
}));

const mockToast = vi.fn();

vi.mock('@/components/ui', () => ({
    Card: ({ children, className }: any) => <div className={className} data-testid="card">{children}</div>,
    CardContent: ({ children, className }: any) => <div className={className}>{children}</div>,
    Badge: ({ children, variant }: any) => <span data-variant={variant}>{children}</span>,
    Button: ({ children, onClick, variant, size, disabled }: any) => (
        <button onClick={onClick} data-variant={variant} data-size={size} disabled={disabled}>{children}</button>
    ),
    Select: ({ children, value, onChange, disabled, className }: any) => (
        <select value={value} onChange={onChange} disabled={disabled} className={className}>{children}</select>
    ),
    Spinner: ({ className }: any) => <div data-testid="spinner" className={className} />,
}));

vi.mock('@/components/ui/Toast', () => ({
    useToast: () => ({
        toast: mockToast,
    }),
}));

const sampleResources = [
    { uuid: 'app-1', name: 'My Web App', type: 'application' as const, status: 'running' },
    { uuid: 'db-1', name: 'PostgreSQL DB', type: 'database' as const, status: 'running' },
    { uuid: 'svc-1', name: 'Redis Cache', type: 'service' as const, status: 'running' },
];

beforeEach(() => {
    vi.clearAllMocks();
    global.fetch = vi.fn();
});

describe('ObservabilityLogs', () => {
    it('renders the page title and description', () => {
        render(<ObservabilityLogs />);
        expect(screen.getByText('Logs Viewer')).toBeInTheDocument();
        expect(screen.getByText('View container logs from your resources')).toBeInTheDocument();
    });

    it('shows select resource prompt when no resource selected', () => {
        render(<ObservabilityLogs resources={sampleResources} />);
        expect(screen.getByText('Select a Resource')).toBeInTheDocument();
        expect(screen.getByText('Choose an application, service, or database to view its logs')).toBeInTheDocument();
    });

    it('renders resource dropdown with grouped options', () => {
        render(<ObservabilityLogs resources={sampleResources} />);
        expect(screen.getByText('My Web App')).toBeInTheDocument();
        expect(screen.getByText('PostgreSQL DB')).toBeInTheDocument();
        expect(screen.getByText('Redis Cache')).toBeInTheDocument();
    });

    it('renders resource group labels', () => {
        const { container } = render(<ObservabilityLogs resources={sampleResources} />);
        const optgroups = container.querySelectorAll('optgroup');
        const labels = Array.from(optgroups).map(og => og.getAttribute('label'));
        expect(labels).toContain('Applications');
        expect(labels).toContain('Databases');
        expect(labels).toContain('Services');
    });

    it('renders log level filter options', () => {
        render(<ObservabilityLogs resources={sampleResources} />);
        expect(screen.getByText('All Levels')).toBeInTheDocument();
        expect(screen.getByText('INFO')).toBeInTheDocument();
        expect(screen.getByText('WARN')).toBeInTheDocument();
        expect(screen.getByText('ERROR')).toBeInTheDocument();
        expect(screen.getByText('DEBUG')).toBeInTheDocument();
    });

    it('renders line count options', () => {
        render(<ObservabilityLogs resources={sampleResources} />);
        expect(screen.getByText('50 lines')).toBeInTheDocument();
        expect(screen.getByText('100 lines')).toBeInTheDocument();
        expect(screen.getByText('500 lines')).toBeInTheDocument();
        expect(screen.getByText('1000 lines')).toBeInTheDocument();
    });

    it('renders auto-refresh button', () => {
        render(<ObservabilityLogs resources={sampleResources} />);
        expect(screen.getByText('Auto-refresh')).toBeInTheDocument();
    });

    it('renders copy button', () => {
        render(<ObservabilityLogs resources={sampleResources} />);
        expect(screen.getByText('Copy')).toBeInTheDocument();
    });

    it('renders download options', () => {
        render(<ObservabilityLogs resources={sampleResources} />);
        expect(screen.getByText('Download TXT')).toBeInTheDocument();
        expect(screen.getByText('Download JSON')).toBeInTheDocument();
    });

    it('renders search input', () => {
        render(<ObservabilityLogs resources={sampleResources} />);
        expect(screen.getByPlaceholderText('Filter logs...')).toBeInTheDocument();
    });

    it('shows empty resources message when no resources', () => {
        render(<ObservabilityLogs resources={[]} />);
        expect(screen.getByText('No resources found')).toBeInTheDocument();
    });

    it('fetches and displays application logs (string format)', async () => {
        const user = userEvent.setup();
        (global.fetch as ReturnType<typeof vi.fn>).mockResolvedValueOnce({
            ok: true,
            json: async () => ({ logs: '2024-01-01T10:00:00Z Starting server\n2024-01-01T10:00:01Z Server ready' }),
        });

        render(<ObservabilityLogs resources={sampleResources} />);

        const resourceSelect = screen.getAllByRole('combobox')[1]; // [0] is Download select
        await user.selectOptions(resourceSelect, 'app-1');

        await waitFor(() => {
            expect(screen.getByText(/Starting server/)).toBeInTheDocument();
        });
    });

    it('fetches and displays service logs (containers format)', async () => {
        const user = userEvent.setup();
        (global.fetch as ReturnType<typeof vi.fn>).mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                service_uuid: 'svc-1',
                containers: {
                    'backend-svc-1': {
                        type: 'application',
                        name: 'backend',
                        status: 'running',
                        logs: '2024-01-01T10:00:00Z Container started\n2024-01-01T10:00:01Z Listening on port 3000',
                    },
                },
            }),
        });

        render(<ObservabilityLogs resources={sampleResources} />);

        const resourceSelect = screen.getAllByRole('combobox')[1];
        await user.selectOptions(resourceSelect, 'svc-1');

        await waitFor(() => {
            expect(screen.getByText(/Container started/)).toBeInTheDocument();
        });
    });

    it('displays error message on fetch failure', async () => {
        const user = userEvent.setup();
        (global.fetch as ReturnType<typeof vi.fn>).mockResolvedValueOnce({
            ok: false,
            status: 500,
            json: async () => ({ message: 'Failed to connect to server' }),
        });

        render(<ObservabilityLogs resources={sampleResources} />);

        const resourceSelect = screen.getAllByRole('combobox')[1];
        await user.selectOptions(resourceSelect, 'app-1');

        await waitFor(() => {
            expect(screen.getByText('Failed to Load Logs')).toBeInTheDocument();
        });
    });
});
