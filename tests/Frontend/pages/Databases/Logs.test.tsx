import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import DatabaseLogs from '@/pages/Databases/Logs';
import type { StandaloneDatabase } from '@/types';

// Mock router
const mockRouter = {
    visit: vi.fn(),
};

vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual('@inertiajs/react');
    return {
        ...actual,
        router: mockRouter,
    };
});

// Mock the useLogStream hook
const mockUseLogStream = {
    logs: [
        { id: 1, timestamp: '2024-01-15T10:30:00Z', level: 'info' as const, message: 'Database started successfully', source: 'postgres' },
        { id: 2, timestamp: '2024-01-15T10:31:00Z', level: 'warning' as const, message: 'Connection pool near capacity', source: 'postgres' },
        { id: 3, timestamp: '2024-01-15T10:32:00Z', level: 'error' as const, message: 'Query timeout', source: 'postgres' },
    ],
    isStreaming: true,
    isConnected: true,
    isPolling: false,
    loading: false,
    error: null,
    clearLogs: vi.fn(),
    toggleStreaming: vi.fn(),
    downloadLogs: vi.fn(),
};

vi.mock('@/hooks/useLogStream', () => ({
    useLogStream: vi.fn(() => mockUseLogStream),
}));

const mockDatabase: StandaloneDatabase = {
    id: 1,
    uuid: 'db-uuid-1',
    name: 'production-postgres',
    description: 'Main production database',
    database_type: 'postgresql',
    status: 'running',
    environment_id: 1,
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-15T00:00:00Z',
};

describe('Database Logs Page', () => {
    beforeEach(() => {
        // Clear mock function calls but keep implementation
        mockUseLogStream.clearLogs.mockClear();
        mockUseLogStream.toggleStreaming.mockClear();
        mockUseLogStream.downloadLogs.mockClear();

        // Reset state
        mockUseLogStream.loading = false;
        mockUseLogStream.error = null;
        mockUseLogStream.isStreaming = true;
        mockUseLogStream.isConnected = true;
        mockUseLogStream.isPolling = false;
        mockUseLogStream.logs = [
            { id: 1, timestamp: '2024-01-15T10:30:00Z', level: 'info' as const, message: 'Database started successfully', source: 'postgres' },
            { id: 2, timestamp: '2024-01-15T10:31:00Z', level: 'warning' as const, message: 'Connection pool near capacity', source: 'postgres' },
            { id: 3, timestamp: '2024-01-15T10:32:00Z', level: 'error' as const, message: 'Query timeout', source: 'postgres' },
        ];
    });

    describe('rendering', () => {
        it('should render page title and description', () => {
            render(<DatabaseLogs database={mockDatabase} />);

            expect(screen.getByText('Database Logs')).toBeInTheDocument();
            expect(screen.getByText(`Real-time log streaming for ${mockDatabase.name}`)).toBeInTheDocument();
        });

        it('should display back link', () => {
            render(<DatabaseLogs database={mockDatabase} />);

            expect(screen.getByText(`Back to ${mockDatabase.name}`)).toBeInTheDocument();
        });

        it('should display breadcrumbs', () => {
            render(<DatabaseLogs database={mockDatabase} />);

            expect(screen.getByText('Databases')).toBeInTheDocument();
            expect(screen.getByText(mockDatabase.name)).toBeInTheDocument();
            expect(screen.getByText('Logs')).toBeInTheDocument();
        });

        it('should show loading state when logs are loading', () => {
            mockUseLogStream.loading = true;
            mockUseLogStream.logs = [];
            render(<DatabaseLogs database={mockDatabase} />);

            expect(screen.getByText('Loading logs...')).toBeInTheDocument();
        });
    });

    describe('streaming controls', () => {
        it('should display connection status badge', () => {
            render(<DatabaseLogs database={mockDatabase} />);

            expect(screen.getByText('WebSocket Connected')).toBeInTheDocument();
        });

        it('should display pause button when streaming', () => {
            render(<DatabaseLogs database={mockDatabase} />);

            expect(screen.getByText('Pause')).toBeInTheDocument();
        });

        it('should display resume button when not streaming', () => {
            mockUseLogStream.isStreaming = false;
            render(<DatabaseLogs database={mockDatabase} />);

            expect(screen.getByText('Resume')).toBeInTheDocument();
        });

        it('should call toggleStreaming when pause/resume is clicked', async () => {
            const { user } = render(<DatabaseLogs database={mockDatabase} />);

            const pauseButton = screen.getByText('Pause');
            await user.click(pauseButton);

            expect(mockUseLogStream.toggleStreaming).toHaveBeenCalled();
        });
    });

    describe('log actions', () => {
        it('should display clear button', () => {
            render(<DatabaseLogs database={mockDatabase} />);

            expect(screen.getByText('Clear')).toBeInTheDocument();
        });

        it('should display download button', () => {
            render(<DatabaseLogs database={mockDatabase} />);

            expect(screen.getByText('Download')).toBeInTheDocument();
        });

        it('should call clearLogs when clear is clicked', async () => {
            const { user } = render(<DatabaseLogs database={mockDatabase} />);

            const clearButton = screen.getByText('Clear');
            await user.click(clearButton);

            expect(mockUseLogStream.clearLogs).toHaveBeenCalled();
        });

        it('should call downloadLogs when download is clicked', async () => {
            const { user } = render(<DatabaseLogs database={mockDatabase} />);

            const downloadButton = screen.getByText('Download');
            await user.click(downloadButton);

            expect(mockUseLogStream.downloadLogs).toHaveBeenCalled();
        });
    });

    describe('log entries display', () => {
        it('should display log entries count', () => {
            render(<DatabaseLogs database={mockDatabase} />);

            expect(screen.getByText('3 entries')).toBeInTheDocument();
        });

        it('should display log messages', () => {
            render(<DatabaseLogs database={mockDatabase} />);

            expect(screen.getByText('Database started successfully')).toBeInTheDocument();
            expect(screen.getByText('Connection pool near capacity')).toBeInTheDocument();
            expect(screen.getByText('Query timeout')).toBeInTheDocument();
        });

        it('should display "Live" indicator when streaming', () => {
            render(<DatabaseLogs database={mockDatabase} />);

            expect(screen.getByText('Live')).toBeInTheDocument();
        });

        it('should show empty state when no logs', () => {
            mockUseLogStream.logs = [];
            mockUseLogStream.loading = false;
            render(<DatabaseLogs database={mockDatabase} />);

            expect(screen.getByText('No logs yet')).toBeInTheDocument();
            expect(screen.getByText('Logs will appear here when the database starts generating output')).toBeInTheDocument();
        });
    });

    describe('connection states', () => {
        it('should show polling badge when using polling fallback', () => {
            mockUseLogStream.isPolling = true;
            mockUseLogStream.isConnected = false;
            render(<DatabaseLogs database={mockDatabase} />);

            expect(screen.getByText('Polling')).toBeInTheDocument();
        });

        it('should display polling indicator text', () => {
            mockUseLogStream.isPolling = true;
            mockUseLogStream.isConnected = false;
            render(<DatabaseLogs database={mockDatabase} />);

            expect(screen.getByText('Using polling fallback')).toBeInTheDocument();
        });

        it('should display WebSocket indicator when connected', () => {
            render(<DatabaseLogs database={mockDatabase} />);

            expect(screen.getByText('WebSocket connected')).toBeInTheDocument();
        });
    });

    describe('error handling', () => {
        it('should display error message when error occurs', () => {
            mockUseLogStream.error = { message: 'Connection failed' };
            render(<DatabaseLogs database={mockDatabase} />);

            expect(screen.getByText('Error Loading Logs')).toBeInTheDocument();
            expect(screen.getByText('Connection failed')).toBeInTheDocument();
        });
    });

    describe('footer info', () => {
        it('should display database info in footer', () => {
            render(<DatabaseLogs database={mockDatabase} />);

            expect(screen.getByText(`Showing real-time logs from ${mockDatabase.name} (${mockDatabase.database_type})`)).toBeInTheDocument();
        });
    });
});
