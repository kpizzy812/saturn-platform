import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../utils/test-utils';
import DatabaseMetrics from '@/pages/Databases/Metrics';
import type { StandaloneDatabase } from '@/types';

// Mock useDatabaseMetricsHistory hook
const mockUseDatabaseMetricsHistory = vi.fn();

vi.mock('@/hooks', () => ({
    useDatabaseMetricsHistory: (...args: any[]) => mockUseDatabaseMetricsHistory(...args),
}));

describe('Database Metrics Page', () => {
    const mockDatabase: StandaloneDatabase = {
        id: 1,
        uuid: 'test-db-uuid',
        name: 'Test PostgreSQL',
        database_type: 'postgresql',
        status: { state: 'running' },
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
    } as StandaloneDatabase;

    const mockMetrics = {
        cpu: {
            current: 45.5,
            average: 38.2,
            peak: 72.3,
            data: [
                { timestamp: '2024-01-01T10:00:00Z', value: 30 },
                { timestamp: '2024-01-01T11:00:00Z', value: 45 },
                { timestamp: '2024-01-01T12:00:00Z', value: 55 },
            ],
        },
        memory: {
            current: 2.5,
            total: 4.0,
            percentage: 62.5,
            data: [
                { timestamp: '2024-01-01T10:00:00Z', value: 2.0 },
                { timestamp: '2024-01-01T11:00:00Z', value: 2.3 },
                { timestamp: '2024-01-01T12:00:00Z', value: 2.5 },
            ],
        },
        storage: {
            used: 15.5,
            total: 50,
            percentage: 31.0,
        },
        connections: {
            current: 45,
            max: 100,
            percentage: 45.0,
            data: [
                { timestamp: '2024-01-01T10:00:00Z', value: 35 },
                { timestamp: '2024-01-01T11:00:00Z', value: 42 },
                { timestamp: '2024-01-01T12:00:00Z', value: 45 },
            ],
        },
        queries: {
            perSecond: 125,
            total: 450000,
            slow: 3,
            data: [
                { timestamp: '2024-01-01T10:00:00Z', value: 100 },
                { timestamp: '2024-01-01T11:00:00Z', value: 115 },
                { timestamp: '2024-01-01T12:00:00Z', value: 125 },
            ],
        },
        network: {
            in: 1.2,
            out: 0.8,
            data: [
                { timestamp: '2024-01-01T10:00:00Z', value: 1.5 },
                { timestamp: '2024-01-01T11:00:00Z', value: 1.8 },
                { timestamp: '2024-01-01T12:00:00Z', value: 2.0 },
            ],
        },
    };

    beforeEach(() => {
        vi.clearAllMocks();
        mockUseDatabaseMetricsHistory.mockReturnValue({
            metrics: mockMetrics,
            hasHistoricalData: true,
            isLoading: false,
            error: null,
        });
    });

    describe('rendering', () => {
        it('should render page title and breadcrumbs', () => {
            render(<DatabaseMetrics database={mockDatabase} />);

            expect(screen.getByText('Database Metrics')).toBeInTheDocument();
            expect(screen.getByText('Performance and resource utilization metrics')).toBeInTheDocument();
            expect(screen.getByText('Back to Test PostgreSQL')).toBeInTheDocument();
        });

        it('should display time range selector', () => {
            render(<DatabaseMetrics database={mockDatabase} />);

            expect(screen.getByDisplayValue('Last 24 Hours')).toBeInTheDocument();
        });
    });

    describe('metric cards', () => {
        it('should display CPU usage metric card', () => {
            render(<DatabaseMetrics database={mockDatabase} />);

            expect(screen.getByText('CPU Usage')).toBeInTheDocument();
            expect(screen.getByText('45.5%')).toBeInTheDocument();
            expect(screen.getByText(/Avg: 38.2%/)).toBeInTheDocument();
            expect(screen.getByText(/Peak: 72.3%/)).toBeInTheDocument();
        });

        it('should display memory metric card', () => {
            render(<DatabaseMetrics database={mockDatabase} />);

            expect(screen.getByText('Memory')).toBeInTheDocument();
            expect(screen.getByText('2.50 GB')).toBeInTheDocument();
            expect(screen.getByText(/62.5% of 4.0 GB/)).toBeInTheDocument();
        });

        it('should display storage metric card', () => {
            render(<DatabaseMetrics database={mockDatabase} />);

            expect(screen.getByText('Storage')).toBeInTheDocument();
            expect(screen.getByText('15.50 GB')).toBeInTheDocument();
            expect(screen.getByText(/31.0% of 50 GB/)).toBeInTheDocument();
        });

        it('should display active connections metric card', () => {
            render(<DatabaseMetrics database={mockDatabase} />);

            expect(screen.getByText('Active Connections')).toBeInTheDocument();
            expect(screen.getByText('45')).toBeInTheDocument();
            expect(screen.getByText(/45.0% of 100 max/)).toBeInTheDocument();
        });

        it('should display queries per second metric card', () => {
            render(<DatabaseMetrics database={mockDatabase} />);

            expect(screen.getByText('Queries/sec')).toBeInTheDocument();
            expect(screen.getByText('125')).toBeInTheDocument();
            expect(screen.getByText(/450,000 total/)).toBeInTheDocument();
            expect(screen.getByText(/3 slow/)).toBeInTheDocument();
        });

        it('should display network I/O metric card', () => {
            render(<DatabaseMetrics database={mockDatabase} />);

            expect(screen.getByText('Network I/O')).toBeInTheDocument();
            expect(screen.getByText('2.00 MB/s')).toBeInTheDocument();
            expect(screen.getByText(/In: 1.20 MB\/s/)).toBeInTheDocument();
            expect(screen.getByText(/Out: 0.80 MB\/s/)).toBeInTheDocument();
        });
    });

    describe('charts', () => {
        it('should display CPU usage chart', () => {
            render(<DatabaseMetrics database={mockDatabase} />);

            expect(screen.getByText('CPU Usage Over Time')).toBeInTheDocument();
        });

        it('should display memory usage chart', () => {
            render(<DatabaseMetrics database={mockDatabase} />);

            expect(screen.getByText('Memory Usage Over Time')).toBeInTheDocument();
        });

        it('should display connections chart', () => {
            render(<DatabaseMetrics database={mockDatabase} />);

            expect(screen.getByText('Active Connections Over Time')).toBeInTheDocument();
        });

        it('should display query rate chart', () => {
            render(<DatabaseMetrics database={mockDatabase} />);

            expect(screen.getByText('Query Rate Over Time')).toBeInTheDocument();
        });

        it('should display chart statistics', () => {
            render(<DatabaseMetrics database={mockDatabase} />);

            // Check for Min/Avg/Max labels
            const minLabels = screen.getAllByText(/Min:/);
            const avgLabels = screen.getAllByText(/Avg:/);
            const maxLabels = screen.getAllByText(/Max:/);

            expect(minLabels.length).toBeGreaterThan(0);
            expect(avgLabels.length).toBeGreaterThan(0);
            expect(maxLabels.length).toBeGreaterThan(0);
        });
    });

    describe('loading state', () => {
        it('should show loading spinner when metrics are loading', () => {
            mockUseDatabaseMetricsHistory.mockReturnValue({
                metrics: null,
                hasHistoricalData: false,
                isLoading: true,
                error: null,
            });

            render(<DatabaseMetrics database={mockDatabase} />);

            expect(screen.getByText('Loading metrics...')).toBeInTheDocument();
        });
    });

    describe('error state', () => {
        it('should display error message when fetch fails', () => {
            mockUseDatabaseMetricsHistory.mockReturnValue({
                metrics: null,
                hasHistoricalData: false,
                isLoading: false,
                error: 'Failed to connect to database',
            });

            render(<DatabaseMetrics database={mockDatabase} />);

            expect(screen.getByText('Failed to load metrics')).toBeInTheDocument();
            expect(screen.getByText('Failed to connect to database')).toBeInTheDocument();
        });
    });

    describe('no historical data state', () => {
        it('should show warning when no historical data exists', () => {
            mockUseDatabaseMetricsHistory.mockReturnValue({
                metrics: mockMetrics,
                hasHistoricalData: false,
                isLoading: false,
                error: null,
            });

            render(<DatabaseMetrics database={mockDatabase} />);

            expect(screen.getByText('No historical data yet')).toBeInTheDocument();
            expect(screen.getByText(/Metrics collection has started/)).toBeInTheDocument();
        });
    });

    describe('time range selection', () => {
        it('should pass selected time range to hook', () => {
            render(<DatabaseMetrics database={mockDatabase} />);

            expect(mockUseDatabaseMetricsHistory).toHaveBeenCalledWith(
                expect.objectContaining({
                    uuid: 'test-db-uuid',
                    timeRange: '24h',
                    autoRefresh: true,
                    refreshInterval: 60000,
                })
            );
        });

        it('should update time range when selector changes', async () => {
            const { user } = render(<DatabaseMetrics database={mockDatabase} />);

            const select = screen.getByDisplayValue('Last 24 Hours');
            await user.selectOptions(select, '7d');

            await waitFor(() => {
                expect(mockUseDatabaseMetricsHistory).toHaveBeenCalledWith(
                    expect.objectContaining({
                        timeRange: '7d',
                    })
                );
            });
        });
    });

    describe('trend calculation', () => {
        it('should display positive trend when metrics increase', () => {
            render(<DatabaseMetrics database={mockDatabase} />);

            // CPU data shows increase from 30 -> 55, so trend should be positive
            const trendElements = screen.getAllByText(/\+/);
            expect(trendElements.length).toBeGreaterThan(0);
        });

        it('should display trend percentage', () => {
            render(<DatabaseMetrics database={mockDatabase} />);

            // Should show "vs last period" text
            const periodTexts = screen.getAllByText(/vs last period/);
            expect(periodTexts.length).toBeGreaterThan(0);
        });
    });

    describe('auto-refresh', () => {
        it('should enable auto-refresh by default', () => {
            render(<DatabaseMetrics database={mockDatabase} />);

            expect(mockUseDatabaseMetricsHistory).toHaveBeenCalledWith(
                expect.objectContaining({
                    autoRefresh: true,
                    refreshInterval: 60000,
                })
            );
        });
    });
});
