import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../utils/test-utils';

// Mock the @inertiajs/react module
vi.mock('@inertiajs/react', () => ({
    Head: ({ children, title }: { children?: React.ReactNode; title?: string }) => (
        <title>{title}</title>
    ),
    Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    router: {
        visit: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
    },
    usePage: () => ({
        props: {
            auth: {
                user: { id: 1, name: 'Test User', email: 'test@example.com' },
                team: { id: 1, name: 'Test Team' },
            },
        },
    }),
}));

// Mock the useSentinelMetrics hook
const mockUseSentinelMetrics = vi.fn();
vi.mock('@/hooks/useSentinelMetrics', () => ({
    useSentinelMetrics: () => mockUseSentinelMetrics(),
}));

// Import after mocks
import SentinelIndex from '@/pages/Servers/Sentinel/Index';
import SentinelAlerts from '@/pages/Servers/Sentinel/Alerts';
import SentinelMetrics from '@/pages/Servers/Sentinel/Metrics';
import { SentinelWidget } from '@/components/features/SentinelWidget';

const mockServer = {
    id: 1,
    uuid: 'server-uuid-123',
    name: 'Production Server',
    description: 'Main production server',
    ip: '192.168.1.100',
    port: 22,
    user: 'root',
    is_reachable: true,
    is_usable: true,
    settings: null,
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-01T00:00:00Z',
};

const mockMetrics = {
    cpu: {
        current: '45%',
        percentage: 45,
        trend: [40, 42, 45, 43, 46, 44, 45, 47, 45, 44],
    },
    memory: {
        current: '8.5 GB',
        percentage: 53,
        total: '16 GB',
        trend: [50, 51, 52, 53, 52, 53, 54, 53, 52, 53],
    },
    disk: {
        current: '25.3 GB',
        percentage: 51,
        total: '50 GB',
        trend: [48, 49, 50, 51, 51, 50, 51, 52, 51, 51],
    },
    network: {
        current: '2.4 MB/s',
        in: '1.2 MB/s',
        out: '1.2 MB/s',
    },
};

const mockAlerts = [
    {
        id: 1,
        title: 'High CPU Usage',
        message: 'CPU usage has been above 80% for 10 minutes',
        severity: 'warning' as const,
        timestamp: '2024-01-01T12:00:00Z',
    },
    {
        id: 2,
        title: 'Memory Alert',
        message: 'Memory usage is approaching limit',
        severity: 'critical' as const,
        timestamp: '2024-01-01T11:30:00Z',
    },
];

const mockHistoricalData = {
    cpu: {
        data: Array.from({ length: 24 }, (_, i) => ({ label: `${i}`, value: 40 + Math.random() * 20 })),
        average: '45%',
        peak: '67%',
    },
    memory: {
        data: Array.from({ length: 24 }, (_, i) => ({ label: `${i}`, value: 50 + Math.random() * 15 })),
        average: '8.2 GB',
        peak: '10.5 GB',
    },
    disk: {
        data: Array.from({ length: 24 }, (_, i) => ({ label: `${i}`, value: 48 + Math.random() * 8 })),
        average: '24.8 GB',
        peak: '26.2 GB',
    },
    network: {
        data: Array.from({ length: 24 }, (_, i) => ({ label: `${i}`, value: 30 + Math.random() * 40 })),
        average: '2.1 MB/s',
        peak: '5.8 MB/s',
    },
};

const mockProcesses = [
    { pid: 1001, name: 'dockerd', cpu: 12.5, memory: 8.2, user: 'root' },
    { pid: 1002, name: 'nginx', cpu: 5.3, memory: 2.1, user: 'www-data' },
    { pid: 1003, name: 'postgres', cpu: 3.8, memory: 15.6, user: 'postgres' },
];

const mockContainers = [
    {
        name: 'saturn-proxy',
        cpu: 8.5,
        memory: 12.3,
        network_in: '1.2 MB/s',
        network_out: '0.8 MB/s',
        status: 'running' as const,
    },
    {
        name: 'app-production',
        cpu: 15.2,
        memory: 25.6,
        network_in: '2.5 MB/s',
        network_out: '1.8 MB/s',
        status: 'running' as const,
    },
];

describe('Sentinel Index Page', () => {
    beforeEach(() => {
        mockUseSentinelMetrics.mockReturnValue({
            metrics: mockMetrics,
            alerts: mockAlerts,
            historicalData: null,
            processes: null,
            containers: null,
            isLoading: false,
            error: null,
            refetch: vi.fn(),
        });
    });

    it('renders the page title and header', () => {
        render(<SentinelIndex server={mockServer} />);
        expect(screen.getByText('Server Health Monitor')).toBeInTheDocument();
        expect(screen.getByText(/Real-time monitoring for/)).toBeInTheDocument();
    });

    it('displays server health status', () => {
        render(<SentinelIndex server={mockServer} />);
        expect(screen.getByText('Server Status')).toBeInTheDocument();
        // Health status appears in a sentence, use regex to match
        expect(screen.getByText(/Healthy/)).toBeInTheDocument();
    });

    it('shows CPU metric card', () => {
        render(<SentinelIndex server={mockServer} />);
        expect(screen.getByText('CPU Usage')).toBeInTheDocument();
        // CPU percentage appears in both the value and badge, so use getAllByText
        expect(screen.getAllByText('45%').length).toBeGreaterThan(0);
    });

    it('shows memory metric card', () => {
        render(<SentinelIndex server={mockServer} />);
        expect(screen.getByText('Memory')).toBeInTheDocument();
        expect(screen.getByText('8.5 GB')).toBeInTheDocument();
    });

    it('shows disk metric card', () => {
        render(<SentinelIndex server={mockServer} />);
        expect(screen.getByText('Disk Usage')).toBeInTheDocument();
        expect(screen.getByText('25.3 GB')).toBeInTheDocument();
    });

    it('displays active alerts section', () => {
        render(<SentinelIndex server={mockServer} />);
        expect(screen.getByText('Active Alerts')).toBeInTheDocument();
        expect(screen.getByText('High CPU Usage')).toBeInTheDocument();
        expect(screen.getByText('Memory Alert')).toBeInTheDocument();
    });

    it('shows no alerts message when no alerts exist', () => {
        mockUseSentinelMetrics.mockReturnValue({
            metrics: mockMetrics,
            alerts: [],
            historicalData: null,
            processes: null,
            containers: null,
            isLoading: false,
            error: null,
            refetch: vi.fn(),
        });

        render(<SentinelIndex server={mockServer} />);
        expect(screen.getByText('No Active Alerts')).toBeInTheDocument();
        expect(screen.getByText('Your server is running smoothly')).toBeInTheDocument();
    });

    it('displays error message when metrics fail to load', () => {
        mockUseSentinelMetrics.mockReturnValue({
            metrics: null,
            alerts: null,
            historicalData: null,
            processes: null,
            containers: null,
            isLoading: false,
            error: new Error('Network error'),
            refetch: vi.fn(),
        });

        render(<SentinelIndex server={mockServer} />);
        expect(screen.getByText(/Failed to load metrics/)).toBeInTheDocument();
    });
});

describe('Sentinel Alerts Page', () => {
    const mockAlertRules = [
        {
            id: 1,
            name: 'High CPU Alert',
            metric: 'cpu' as const,
            condition: 'above' as const,
            threshold: 80,
            duration: 5,
            severity: 'warning' as const,
            notificationChannels: ['email' as const, 'slack' as const],
            enabled: true,
            created_at: '2024-01-01T00:00:00Z',
        },
        {
            id: 2,
            name: 'Memory Critical',
            metric: 'memory' as const,
            condition: 'above' as const,
            threshold: 90,
            duration: 10,
            severity: 'critical' as const,
            notificationChannels: ['email' as const],
            enabled: true,
            created_at: '2024-01-01T00:00:00Z',
        },
    ];

    const mockAlertHistory = [
        {
            id: 1,
            rule_name: 'High CPU Alert',
            severity: 'warning' as const,
            message: 'CPU usage exceeded 80%',
            triggered_at: '2024-01-01T12:00:00Z',
            resolved_at: '2024-01-01T12:15:00Z',
        },
    ];

    it('renders the alerts page', () => {
        render(
            <SentinelAlerts
                server={mockServer}
                alertRules={mockAlertRules}
                alertHistory={mockAlertHistory}
            />
        );
        expect(screen.getByText('Alert Management')).toBeInTheDocument();
    });

    it('displays alert rules', () => {
        render(
            <SentinelAlerts
                server={mockServer}
                alertRules={mockAlertRules}
                alertHistory={mockAlertHistory}
            />
        );
        // Alert names appear in both rules and history, so use getAllByText
        expect(screen.getAllByText('High CPU Alert').length).toBeGreaterThan(0);
        expect(screen.getByText('Memory Critical')).toBeInTheDocument();
    });

    it('shows alert history', () => {
        render(
            <SentinelAlerts
                server={mockServer}
                alertRules={mockAlertRules}
                alertHistory={mockAlertHistory}
            />
        );
        expect(screen.getByText('Alert History')).toBeInTheDocument();
        expect(screen.getByText(/CPU usage exceeded 80%/)).toBeInTheDocument();
    });

    it('displays empty state when no alert rules exist', () => {
        render(<SentinelAlerts server={mockServer} alertRules={[]} alertHistory={[]} />);
        expect(screen.getByText('No Alert Rules')).toBeInTheDocument();
        expect(screen.getByText('Create your first alert rule to get notified')).toBeInTheDocument();
    });
});

describe('Sentinel Metrics Page', () => {
    beforeEach(() => {
        mockUseSentinelMetrics.mockReturnValue({
            metrics: mockMetrics,
            alerts: null,
            historicalData: mockHistoricalData,
            processes: mockProcesses,
            containers: mockContainers,
            isLoading: false,
            error: null,
            refetch: vi.fn(),
        });
    });

    it('renders the metrics page', () => {
        render(<SentinelMetrics server={mockServer} />);
        expect(screen.getByText('Detailed Metrics')).toBeInTheDocument();
    });

    it('displays time range selector', () => {
        render(<SentinelMetrics server={mockServer} />);
        expect(screen.getByText('1 Hour')).toBeInTheDocument();
        expect(screen.getByText('24 Hours')).toBeInTheDocument();
        expect(screen.getByText('7 Days')).toBeInTheDocument();
        expect(screen.getByText('30 Days')).toBeInTheDocument();
    });

    it('shows current stats summary', () => {
        render(<SentinelMetrics server={mockServer} />);
        expect(screen.getByText('CPU Usage')).toBeInTheDocument();
        expect(screen.getByText('Memory')).toBeInTheDocument();
        expect(screen.getByText('Disk')).toBeInTheDocument();
        expect(screen.getByText('Network')).toBeInTheDocument();
    });

    it('displays historical charts', () => {
        render(<SentinelMetrics server={mockServer} />);
        expect(screen.getByText('CPU Usage Over Time')).toBeInTheDocument();
        expect(screen.getByText('Memory Usage Over Time')).toBeInTheDocument();
    });

    it('shows top processes table', () => {
        render(<SentinelMetrics server={mockServer} />);
        expect(screen.getByText('Top Processes')).toBeInTheDocument();
        expect(screen.getByText('dockerd')).toBeInTheDocument();
        expect(screen.getByText('nginx')).toBeInTheDocument();
        // Process names might appear multiple times in the table, so use getAllByText
        expect(screen.getAllByText('postgres').length).toBeGreaterThan(0);
    });

    it('displays container statistics', () => {
        render(<SentinelMetrics server={mockServer} />);
        expect(screen.getByText('Container Statistics')).toBeInTheDocument();
        expect(screen.getByText('saturn-proxy')).toBeInTheDocument();
        expect(screen.getByText('app-production')).toBeInTheDocument();
    });
});

describe('SentinelWidget Component', () => {
    beforeEach(() => {
        mockUseSentinelMetrics.mockReturnValue({
            metrics: mockMetrics,
            alerts: mockAlerts,
            historicalData: null,
            processes: null,
            containers: null,
            isLoading: false,
            error: null,
            refetch: vi.fn(),
        });
    });

    it('renders widget in full mode', () => {
        render(<SentinelWidget server={mockServer} />);
        expect(screen.getByText('Server Health')).toBeInTheDocument();
        expect(screen.getByText('Status')).toBeInTheDocument();
    });

    it('renders widget in compact mode', () => {
        render(<SentinelWidget server={mockServer} compact />);
        expect(screen.getByText('Server Health')).toBeInTheDocument();
    });

    it('displays health status badge', () => {
        render(<SentinelWidget server={mockServer} />);
        expect(screen.getByText(/Healthy/)).toBeInTheDocument();
    });

    it('shows active alert count', () => {
        render(<SentinelWidget server={mockServer} />);
        expect(screen.getByText('2 alerts')).toBeInTheDocument();
    });

    it('displays CPU metric with sparkline', () => {
        render(<SentinelWidget server={mockServer} />);
        expect(screen.getByText('CPU')).toBeInTheDocument();
        // CPU percentage might appear multiple times, so use getAllByText
        expect(screen.getAllByText('45%').length).toBeGreaterThan(0);
    });

    it('shows loading state', () => {
        mockUseSentinelMetrics.mockReturnValue({
            metrics: null,
            alerts: null,
            historicalData: null,
            processes: null,
            containers: null,
            isLoading: true,
            error: null,
            refetch: vi.fn(),
        });

        render(<SentinelWidget server={mockServer} />);
        // Widget should show loading placeholders
        const { container } = render(<SentinelWidget server={mockServer} />);
        expect(container.querySelector('.animate-pulse')).toBeInTheDocument();
    });
});
