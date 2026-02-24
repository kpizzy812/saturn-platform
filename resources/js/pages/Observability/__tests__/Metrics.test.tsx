import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import ObservabilityMetrics from '../Metrics';

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

vi.mock('@/components/ui', () => ({
    Card: ({ children, className }: any) => <div className={className} data-testid="card">{children}</div>,
    CardHeader: ({ children }: any) => <div>{children}</div>,
    CardTitle: ({ children }: any) => <h3>{children}</h3>,
    CardContent: ({ children, className }: any) => <div className={className}>{children}</div>,
    Badge: ({ children, variant }: any) => <span data-variant={variant}>{children}</span>,
    Button: ({ children, onClick, variant, size, disabled }: any) => (
        <button onClick={onClick} data-variant={variant} data-size={size} disabled={disabled}>{children}</button>
    ),
    useToast: () => ({
        addToast: vi.fn(),
    }),
}));

vi.mock('@/components/ui/Chart', () => ({
    LineChart: ({ data, height }: any) => (
        <div data-testid="line-chart" data-height={height} data-points={data?.length ?? 0}>chart</div>
    ),
}));

vi.mock('@/lib/csv', () => ({
    CSV_BOM: '\uFEFF',
    downloadFile: vi.fn(),
}));

const sampleServers = [
    { uuid: 'server-1', name: 'Production Server' },
    { uuid: 'server-2', name: 'Staging Server' },
];

beforeEach(() => {
    vi.clearAllMocks();
    globalThis.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ metrics: {}, historicalData: {} }),
    });
});

describe('ObservabilityMetrics', () => {
    it('renders page title and description', () => {
        render(<ObservabilityMetrics servers={sampleServers} />);
        expect(screen.getByText('Metrics Dashboard')).toBeInTheDocument();
        expect(screen.getByText('Monitor system performance and resource utilization')).toBeInTheDocument();
    });

    it('renders server selector with all servers', () => {
        render(<ObservabilityMetrics servers={sampleServers} />);
        expect(screen.getByText('Production Server')).toBeInTheDocument();
        expect(screen.getByText('Staging Server')).toBeInTheDocument();
    });

    it('renders time range selector', () => {
        render(<ObservabilityMetrics servers={sampleServers} />);
        expect(screen.getByText('Last 1 hour')).toBeInTheDocument();
        expect(screen.getByText('Last 24 hours')).toBeInTheDocument();
        expect(screen.getByText('Last 7 days')).toBeInTheDocument();
        expect(screen.getByText('Last 30 days')).toBeInTheDocument();
    });

    it('renders refresh button', () => {
        render(<ObservabilityMetrics servers={sampleServers} />);
        // Component calls fetchMetrics on mount which sets isLoading=true, so text is "Refreshing..."
        const refreshButton = screen.getByText(/Refresh/);
        expect(refreshButton).toBeInTheDocument();
    });

    it('renders export button', () => {
        render(<ObservabilityMetrics servers={sampleServers} />);
        const exportButton = screen.getByText(/Export/);
        expect(exportButton).toBeInTheDocument();
    });

    it('shows no servers message when servers array is empty', () => {
        render(<ObservabilityMetrics servers={[]} />);
        expect(screen.getByText('No servers found')).toBeInTheDocument();
        expect(screen.getByText('Add a server to start collecting metrics')).toBeInTheDocument();
    });

    it('renders server and time range labels', () => {
        render(<ObservabilityMetrics servers={sampleServers} />);
        expect(screen.getByText('Server')).toBeInTheDocument();
        expect(screen.getByText('Time Range')).toBeInTheDocument();
    });
});
