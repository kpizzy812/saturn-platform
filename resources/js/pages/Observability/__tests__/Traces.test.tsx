import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';
import ObservabilityTraces from '../Traces';

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
    CardContent: ({ children }: any) => <div>{children}</div>,
    Badge: ({ children, variant }: any) => <span data-variant={variant}>{children}</span>,
}));

vi.mock('@/lib/utils', () => ({
    formatRelativeTime: (ts: string) => '5 minutes ago',
}));

const sampleTraces = [
    {
        id: 'trace-1',
        name: 'Deploy Application',
        duration: 250,
        timestamp: '2026-02-18T12:00:00Z',
        status: 'success' as const,
        services: ['Application', 'Server'],
        spans: [
            {
                id: 'span-1',
                name: 'git clone',
                service: 'Application',
                duration: 150,
                startTime: 0,
                status: 'success' as const,
            },
            {
                id: 'span-2',
                name: 'docker build',
                service: 'Server',
                duration: 100,
                startTime: 150,
                status: 'success' as const,
            },
        ],
    },
    {
        id: 'trace-2',
        name: 'Database Backup',
        duration: 80,
        timestamp: '2026-02-18T11:00:00Z',
        status: 'error' as const,
        services: ['Server'],
        spans: [
            {
                id: 'span-3',
                name: 'pg_dump',
                service: 'Server',
                duration: 80,
                startTime: 0,
                status: 'error' as const,
                tags: { error: 'Connection refused' },
            },
        ],
    },
    {
        id: 'trace-3',
        name: 'Fast Operation',
        duration: 50,
        timestamp: '2026-02-18T10:00:00Z',
        status: 'success' as const,
        services: ['Application'],
        spans: [
            {
                id: 'span-4',
                name: 'health check',
                service: 'Application',
                duration: 50,
                startTime: 0,
                status: 'success' as const,
            },
        ],
    },
];

beforeEach(() => {
    vi.clearAllMocks();
});

describe('ObservabilityTraces', () => {
    it('renders page title and description', () => {
        render(<ObservabilityTraces />);
        expect(screen.getByText('Distributed Tracing')).toBeInTheDocument();
        expect(screen.getByText('Track requests across your microservices')).toBeInTheDocument();
    });

    it('renders trace list when traces provided', () => {
        render(<ObservabilityTraces traces={sampleTraces} />);
        expect(screen.getByText('Deploy Application')).toBeInTheDocument();
        expect(screen.getByText('Database Backup')).toBeInTheDocument();
        expect(screen.getByText('Fast Operation')).toBeInTheDocument();
    });

    it('renders status badges for each trace', () => {
        render(<ObservabilityTraces traces={sampleTraces} />);
        const successBadges = screen.getAllByText('success');
        const errorBadges = screen.getAllByText('error');
        expect(successBadges.length).toBe(2);
        expect(errorBadges.length).toBe(1);
    });

    it('shows duration for each trace', () => {
        render(<ObservabilityTraces traces={sampleTraces} />);
        expect(screen.getByText('250ms')).toBeInTheDocument();
        expect(screen.getByText('80ms')).toBeInTheDocument();
        expect(screen.getByText('50ms')).toBeInTheDocument();
    });

    it('shows span count for each trace', () => {
        render(<ObservabilityTraces traces={sampleTraces} />);
        expect(screen.getByText('2 spans')).toBeInTheDocument();
        expect(screen.getAllByText('1 spans').length).toBe(2);
    });

    it('shows service tags for each trace', () => {
        render(<ObservabilityTraces traces={sampleTraces} />);
        const applicationTags = screen.getAllByText('Application');
        const serverTags = screen.getAllByText('Server');
        expect(applicationTags.length).toBeGreaterThanOrEqual(2);
        expect(serverTags.length).toBeGreaterThanOrEqual(1);
    });

    it('shows "No trace selected" initially', () => {
        render(<ObservabilityTraces traces={sampleTraces} />);
        expect(screen.getByText('No trace selected')).toBeInTheDocument();
        expect(screen.getByText('Select a trace from the list to view details')).toBeInTheDocument();
    });

    it('shows trace detail when a trace is clicked', () => {
        render(<ObservabilityTraces traces={sampleTraces} />);
        fireEvent.click(screen.getByText('Deploy Application'));
        // Waterfall View should appear
        expect(screen.getByText('Waterfall View')).toBeInTheDocument();
        expect(screen.getByText('Service Dependencies')).toBeInTheDocument();
        expect(screen.getByText('Trace ID')).toBeInTheDocument();
    });

    it('renders search input', () => {
        render(<ObservabilityTraces traces={sampleTraces} />);
        expect(screen.getByPlaceholderText('Search traces...')).toBeInTheDocument();
    });

    it('filters traces by search query', () => {
        render(<ObservabilityTraces traces={sampleTraces} />);
        const searchInput = screen.getByPlaceholderText('Search traces...');
        fireEvent.change(searchInput, { target: { value: 'Database' } });
        expect(screen.getByText('Database Backup')).toBeInTheDocument();
        expect(screen.queryByText('Deploy Application')).not.toBeInTheDocument();
    });

    it('renders status filter dropdown', () => {
        render(<ObservabilityTraces traces={sampleTraces} />);
        expect(screen.getByText('All Statuses')).toBeInTheDocument();
        expect(screen.getByText('Success')).toBeInTheDocument();
        expect(screen.getByText('Error')).toBeInTheDocument();
    });

    it('filters traces by status', () => {
        render(<ObservabilityTraces traces={sampleTraces} />);
        const statusSelect = screen.getByDisplayValue('All Statuses');
        fireEvent.change(statusSelect, { target: { value: 'error' } });
        expect(screen.getByText('Database Backup')).toBeInTheDocument();
        expect(screen.queryByText('Deploy Application')).not.toBeInTheDocument();
    });

    it('renders duration filter dropdown', () => {
        render(<ObservabilityTraces traces={sampleTraces} />);
        expect(screen.getByText('All Durations')).toBeInTheDocument();
    });

    it('filters traces by duration - slow', () => {
        render(<ObservabilityTraces traces={sampleTraces} />);
        const durationSelect = screen.getByDisplayValue('All Durations');
        fireEvent.change(durationSelect, { target: { value: 'slow' } });
        expect(screen.getByText('Deploy Application')).toBeInTheDocument();
        expect(screen.queryByText('Fast Operation')).not.toBeInTheDocument();
    });

    it('filters traces by duration - fast', () => {
        render(<ObservabilityTraces traces={sampleTraces} />);
        const durationSelect = screen.getByDisplayValue('All Durations');
        fireEvent.change(durationSelect, { target: { value: 'fast' } });
        expect(screen.getByText('Database Backup')).toBeInTheDocument();
        expect(screen.getByText('Fast Operation')).toBeInTheDocument();
        expect(screen.queryByText('Deploy Application')).not.toBeInTheDocument();
    });

    it('renders empty state when no traces', () => {
        render(<ObservabilityTraces traces={[]} />);
        expect(screen.getByText('Recent Traces')).toBeInTheDocument();
    });

    it('shows waterfall view with span details when trace selected', () => {
        render(<ObservabilityTraces traces={sampleTraces} />);
        fireEvent.click(screen.getByText('Deploy Application'));
        expect(screen.getByText('git clone')).toBeInTheDocument();
        expect(screen.getByText('docker build')).toBeInTheDocument();
    });

    it('shows error tags in span detail', () => {
        render(<ObservabilityTraces traces={sampleTraces} />);
        fireEvent.click(screen.getByText('Database Backup'));
        expect(screen.getByText(/Connection refused/)).toBeInTheDocument();
    });
});
