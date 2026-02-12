import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../utils/test-utils';

// Mock useServiceMetrics hook
const mockMetricsData = {
    containers: [
        {
            name: 'api',
            container_id: 'abc123def456',
            cpu: { percent: 23.5, formatted: '23.5%' },
            memory: {
                used: '512 MB',
                limit: '2 GB',
                percent: 25,
                used_bytes: 536870912,
                limit_bytes: 2147483648,
            },
            network: { rx: '1.2 MB/s', tx: '0.8 MB/s' },
            disk: { read: '10 MB/s', write: '5 MB/s' },
            pids: '42',
        },
        {
            name: 'worker',
            container_id: 'def456ghi789',
            cpu: { percent: 15.2, formatted: '15.2%' },
            memory: {
                used: '256 MB',
                limit: '1 GB',
                percent: 25,
                used_bytes: 268435456,
                limit_bytes: 1073741824,
            },
            network: { rx: '0.5 MB/s', tx: '0.3 MB/s' },
            disk: { read: '2 MB/s', write: '1 MB/s' },
            pids: '18',
        },
    ],
    summary: {
        cpu_percent: 38.7,
        memory_used_bytes: 805306368,
        memory_limit_bytes: 3221225472,
        memory_percent: 25,
        container_count: 2,
    },
    isLoading: false,
    error: null,
    refetch: vi.fn(),
    lastUpdated: new Date('2024-01-15T10:00:00.000Z'),
};

vi.mock('@/hooks/useServiceMetrics', () => ({
    useServiceMetrics: () => mockMetricsData,
}));

// Import after mocks
import { MetricsTab } from '@/pages/Services/Metrics';
import type { Service } from '@/types';

const mockService: Service = {
    id: 1,
    uuid: 'service-uuid-123',
    name: 'production-api',
    description: 'Main production API service',
    docker_compose_raw: 'version: "3.8"\nservices:\n  api:\n    image: node:18',
    environment_id: 1,
    destination_id: 1,
    created_at: '2024-01-01T00:00:00.000Z',
    updated_at: '2024-01-15T00:00:00.000Z',
};

describe('Service Metrics Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockMetricsData.isLoading = false;
        mockMetricsData.error = null;
        mockMetricsData.containers = [
            {
                name: 'api',
                container_id: 'abc123def456',
                cpu: { percent: 23.5, formatted: '23.5%' },
                memory: {
                    used: '512 MB',
                    limit: '2 GB',
                    percent: 25,
                    used_bytes: 536870912,
                    limit_bytes: 2147483648,
                },
                network: { rx: '1.2 MB/s', tx: '0.8 MB/s' },
                disk: { read: '10 MB/s', write: '5 MB/s' },
                pids: '42',
            },
            {
                name: 'worker',
                container_id: 'def456ghi789',
                cpu: { percent: 15.2, formatted: '15.2%' },
                memory: {
                    used: '256 MB',
                    limit: '1 GB',
                    percent: 25,
                    used_bytes: 268435456,
                    limit_bytes: 1073741824,
                },
                network: { rx: '0.5 MB/s', tx: '0.3 MB/s' },
                disk: { read: '2 MB/s', write: '1 MB/s' },
                pids: '18',
            },
        ];
    });

    it('renders time range buttons', () => {
        render(<MetricsTab service={mockService} />);
        expect(screen.getByText('1 Hour')).toBeInTheDocument();
        expect(screen.getByText('6 Hours')).toBeInTheDocument();
        expect(screen.getByText('24 Hours')).toBeInTheDocument();
        expect(screen.getByText('7 Days')).toBeInTheDocument();
    });

    it('renders auto-refresh toggle', () => {
        render(<MetricsTab service={mockService} />);
        const autoRefreshButtons = screen.getAllByText(/Auto-refresh/);
        expect(autoRefreshButtons.length).toBeGreaterThan(0);
    });

    it('displays Refresh Now button', () => {
        render(<MetricsTab service={mockService} />);
        expect(screen.getByText('Refresh Now')).toBeInTheDocument();
    });

    it('shows last updated timestamp', () => {
        render(<MetricsTab service={mockService} />);
        const bodyText = document.body.textContent || '';
        expect(bodyText).toContain('Updated');
    });

    it('renders CPU Usage summary card', () => {
        render(<MetricsTab service={mockService} />);
        expect(screen.getByText('CPU Usage')).toBeInTheDocument();
        expect(screen.getByText('38.7%')).toBeInTheDocument();
    });

    it('renders Memory summary card', () => {
        render(<MetricsTab service={mockService} />);
        const memoryHeaders = screen.getAllByText('Memory');
        expect(memoryHeaders.length).toBeGreaterThan(0);
        const bodyText = document.body.textContent || '';
        // Should show memory usage
        expect(bodyText).toMatch(/\d+\.?\d* (B|KB|MB|GB)/);
        expect(bodyText).toContain('25%');
    });

    it('renders Containers count card', () => {
        render(<MetricsTab service={mockService} />);
        expect(screen.getByText('Containers')).toBeInTheDocument();
        expect(screen.getByText('2')).toBeInTheDocument();
    });

    it('renders Status card', () => {
        render(<MetricsTab service={mockService} />);
        expect(screen.getByText('Status')).toBeInTheDocument();
        expect(screen.getByText('Running')).toBeInTheDocument();
    });

    it('displays Container Details section', () => {
        render(<MetricsTab service={mockService} />);
        expect(screen.getByText('Container Details')).toBeInTheDocument();
    });

    it('shows container names', () => {
        render(<MetricsTab service={mockService} />);
        const apiContainers = screen.getAllByText('api');
        const workerContainers = screen.getAllByText('worker');
        expect(apiContainers.length).toBeGreaterThan(0);
        expect(workerContainers.length).toBeGreaterThan(0);
    });

    it('displays container IDs', () => {
        render(<MetricsTab service={mockService} />);
        expect(screen.getByText('abc123def456')).toBeInTheDocument();
        expect(screen.getByText('def456ghi789')).toBeInTheDocument();
    });

    it('renders CPU Usage by Container section', () => {
        render(<MetricsTab service={mockService} />);
        expect(screen.getByText('CPU Usage by Container')).toBeInTheDocument();
    });

    it('shows CPU percentages for each container', () => {
        render(<MetricsTab service={mockService} />);
        // Check for CPU percentages in the document
        const bodyText = document.body.textContent || '';
        expect(bodyText).toContain('23.5%');
        expect(bodyText).toContain('15.2%');
    });

    it('renders Memory Usage by Container section', () => {
        render(<MetricsTab service={mockService} />);
        expect(screen.getByText('Memory Usage by Container')).toBeInTheDocument();
    });

    it('shows memory usage for each container', () => {
        render(<MetricsTab service={mockService} />);
        expect(screen.getByText('512 MB')).toBeInTheDocument();
        expect(screen.getByText('256 MB')).toBeInTheDocument();
    });

    it('renders Network I/O section', () => {
        render(<MetricsTab service={mockService} />);
        expect(screen.getByText('Network I/O')).toBeInTheDocument();
    });

    it('displays network table headers', () => {
        render(<MetricsTab service={mockService} />);
        const allContainers = screen.getAllByText('Container');
        expect(allContainers.length).toBeGreaterThan(0);
        expect(screen.getByText('Received')).toBeInTheDocument();
        expect(screen.getByText('Transmitted')).toBeInTheDocument();
        expect(screen.getByText('Disk Read')).toBeInTheDocument();
        expect(screen.getByText('Disk Write')).toBeInTheDocument();
    });

    it('shows network statistics for containers', () => {
        render(<MetricsTab service={mockService} />);
        expect(screen.getByText('1.2 MB/s')).toBeInTheDocument();
        expect(screen.getByText('0.8 MB/s')).toBeInTheDocument();
        expect(screen.getByText('0.5 MB/s')).toBeInTheDocument();
        expect(screen.getByText('0.3 MB/s')).toBeInTheDocument();
    });

    it('displays disk I/O statistics', () => {
        render(<MetricsTab service={mockService} />);
        expect(screen.getByText('10 MB/s')).toBeInTheDocument();
        expect(screen.getByText('5 MB/s')).toBeInTheDocument();
        expect(screen.getByText('2 MB/s')).toBeInTheDocument();
        expect(screen.getByText('1 MB/s')).toBeInTheDocument();
    });

    it('shows PID counts', () => {
        render(<MetricsTab service={mockService} />);
        const bodyText = document.body.textContent || '';
        expect(bodyText).toContain('42');
        expect(bodyText).toContain('18');
    });

    it('allows changing time range', () => {
        render(<MetricsTab service={mockService} />);

        const hourButton = screen.getByText('1 Hour');
        fireEvent.click(hourButton);

        // Button should become active
        expect(hourButton.className).toContain('bg-foreground');
    });

    it('allows toggling auto-refresh', () => {
        render(<MetricsTab service={mockService} />);

        const autoRefreshButton = screen.getAllByText(/Auto-refresh/)[0];
        fireEvent.click(autoRefreshButton);

        // Should show Off state
        expect(screen.getByText(/Auto-refresh Off/)).toBeInTheDocument();
    });

    it('calls refetch when Refresh Now is clicked', () => {
        render(<MetricsTab service={mockService} />);

        const refreshButton = screen.getByText('Refresh Now');
        fireEvent.click(refreshButton);

        expect(mockMetricsData.refetch).toHaveBeenCalled();
    });

    it('shows error state when metrics fail to load', () => {
        mockMetricsData.error = 'Failed to fetch metrics';
        mockMetricsData.containers = [];

        render(<MetricsTab service={mockService} />);

        expect(screen.getByText('Unable to fetch metrics')).toBeInTheDocument();
        expect(screen.getByText('Failed to fetch metrics')).toBeInTheDocument();
    });

    it('displays Retry button on error', () => {
        mockMetricsData.error = 'Failed to fetch metrics';
        mockMetricsData.containers = [];

        render(<MetricsTab service={mockService} />);

        expect(screen.getByText('Retry')).toBeInTheDocument();
    });

    it('retry button calls refetch on error', () => {
        mockMetricsData.error = 'Failed to fetch metrics';
        mockMetricsData.containers = [];

        render(<MetricsTab service={mockService} />);

        const retryButton = screen.getByText('Retry');
        fireEvent.click(retryButton);

        expect(mockMetricsData.refetch).toHaveBeenCalled();
    });

    it('shows loading state', () => {
        mockMetricsData.isLoading = true;
        mockMetricsData.containers = [];

        render(<MetricsTab service={mockService} />);

        // Should show skeleton loaders
        const skeletons = document.querySelectorAll('.animate-pulse');
        expect(skeletons.length).toBeGreaterThan(0);
    });

    it('handles empty container state', () => {
        mockMetricsData.containers = [];
        mockMetricsData.summary = null;

        render(<MetricsTab service={mockService} />);

        // Should still render the controls
        expect(screen.getByText('1 Hour')).toBeInTheDocument();
    });

    it('formats bytes correctly', () => {
        render(<MetricsTab service={mockService} />);

        // Should display properly formatted byte values
        const bodyText = document.body.textContent || '';
        expect(bodyText).toMatch(/\d+\.?\d* (B|KB|MB|GB)/);
    });

    it('displays progress bars for CPU usage', () => {
        render(<MetricsTab service={mockService} />);

        // Find progress bars (they have specific width styles)
        const progressBars = document.querySelectorAll('.h-2.rounded-full');
        expect(progressBars.length).toBeGreaterThan(0);
    });

    it('displays progress bars for memory usage', () => {
        render(<MetricsTab service={mockService} />);

        // Should have progress bars rendered
        const progressBars = document.querySelectorAll('.h-2.rounded-full.bg-warning');
        expect(progressBars.length).toBeGreaterThan(0);
    });
});
