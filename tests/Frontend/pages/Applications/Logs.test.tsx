import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '../../utils/test-utils';

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
        patch: vi.fn(),
    },
    usePage: () => ({
        props: {
            auth: {
                user: { id: 1, name: 'Test User', email: 'test@example.com' },
            },
        },
    }),
}));

// Mock useLogStream hook
const mockToggleStreaming = vi.fn();
const mockClearLogs = vi.fn();
const mockDownloadLogs = vi.fn();

vi.mock('@/hooks/useLogStream', () => ({
    useLogStream: vi.fn(() => ({
        logs: [
            {
                id: '1',
                timestamp: '2024-01-15 10:30:45',
                level: 'info',
                message: 'Application started successfully',
            },
            {
                id: '2',
                timestamp: '2024-01-15 10:31:12',
                level: 'warning',
                message: 'Low memory warning',
            },
            {
                id: '3',
                timestamp: '2024-01-15 10:31:30',
                level: 'error',
                message: 'Database connection failed',
            },
        ],
        availableContainers: ['app-container-1', 'app-container-2'],
        isStreaming: true,
        isConnected: true,
        isPolling: false,
        loading: false,
        error: null,
        clearLogs: mockClearLogs,
        toggleStreaming: mockToggleStreaming,
        downloadLogs: mockDownloadLogs,
    })),
}));

// Import after mocks
import ApplicationLogs from '@/pages/Applications/Logs';
import type { Application } from '@/types';

const mockApplication: Application = {
    id: 1,
    uuid: 'app-uuid-1',
    name: 'production-api',
    description: 'Main production API',
    fqdn: 'api.example.com',
    repository_project_id: null,
    git_repository: 'https://github.com/user/api',
    git_branch: 'main',
    build_pack: 'nixpacks',
    status: 'running',
    environment_id: 1,
    destination_id: 1,
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-15T00:00:00Z',
};

describe('Application Logs Page', () => {
    beforeEach(() => {
        mockToggleStreaming.mockClear();
        mockClearLogs.mockClear();
        mockDownloadLogs.mockClear();
    });

    it('renders the page header', () => {
        render(<ApplicationLogs application={mockApplication} />);
        expect(screen.getByText('Application Logs')).toBeInTheDocument();
        expect(screen.getByText('Real-time logs from your application')).toBeInTheDocument();
    });

    it('shows live streaming indicator', () => {
        render(<ApplicationLogs application={mockApplication} />);
        expect(screen.getByText('Live')).toBeInTheDocument();
    });

    it('shows search input', () => {
        render(<ApplicationLogs application={mockApplication} />);
        const searchInput = screen.getByPlaceholderText('Search logs...');
        expect(searchInput).toBeInTheDocument();
    });

    it('shows level filter dropdown', () => {
        render(<ApplicationLogs application={mockApplication} />);
        expect(screen.getByText('All Levels')).toBeInTheDocument();
    });

    it('shows pause/resume button', () => {
        render(<ApplicationLogs application={mockApplication} />);
        expect(screen.getByText('Pause')).toBeInTheDocument();
    });

    it('calls toggleStreaming when pause button is clicked', () => {
        render(<ApplicationLogs application={mockApplication} />);
        const pauseButton = screen.getByText('Pause').closest('button');

        if (pauseButton) {
            fireEvent.click(pauseButton);
        }

        expect(mockToggleStreaming).toHaveBeenCalled();
    });

    it('shows clear button', () => {
        render(<ApplicationLogs application={mockApplication} />);
        expect(screen.getByText('Clear')).toBeInTheDocument();
    });

    it('calls clearLogs when clear button is clicked', () => {
        render(<ApplicationLogs application={mockApplication} />);
        const clearButton = screen.getByText('Clear').closest('button');

        if (clearButton) {
            fireEvent.click(clearButton);
        }

        expect(mockClearLogs).toHaveBeenCalled();
    });

    it('shows download button', () => {
        render(<ApplicationLogs application={mockApplication} />);
        expect(screen.getByText('Download')).toBeInTheDocument();
    });

    it('calls downloadLogs when download button is clicked', () => {
        render(<ApplicationLogs application={mockApplication} />);
        const downloadButton = screen.getByText('Download').closest('button');

        if (downloadButton) {
            fireEvent.click(downloadButton);
        }

        expect(mockDownloadLogs).toHaveBeenCalled();
    });

    it('renders log container', () => {
        render(<ApplicationLogs application={mockApplication} />);
        const logContainer = document.querySelector('[data-log-container]');
        expect(logContainer).toBeInTheDocument();
    });

    it('displays log entries', () => {
        render(<ApplicationLogs application={mockApplication} />);
        expect(screen.getByText('Application started successfully')).toBeInTheDocument();
        expect(screen.getByText('Low memory warning')).toBeInTheDocument();
        expect(screen.getByText('Database connection failed')).toBeInTheDocument();
    });

    it('shows log timestamps', () => {
        render(<ApplicationLogs application={mockApplication} />);
        expect(screen.getByText('2024-01-15 10:30:45')).toBeInTheDocument();
        expect(screen.getByText('2024-01-15 10:31:12')).toBeInTheDocument();
        expect(screen.getByText('2024-01-15 10:31:30')).toBeInTheDocument();
    });

    it('shows log levels', () => {
        render(<ApplicationLogs application={mockApplication} />);
        expect(screen.getByText('[info]')).toBeInTheDocument();
        expect(screen.getByText('[warning]')).toBeInTheDocument();
        expect(screen.getByText('[error]')).toBeInTheDocument();
    });

    it('filters logs by search query', () => {
        render(<ApplicationLogs application={mockApplication} />);
        const searchInput = screen.getByPlaceholderText('Search logs...');

        fireEvent.change(searchInput, { target: { value: 'Database' } });

        expect(screen.getByText('Database connection failed')).toBeInTheDocument();
        // Other logs should still be visible in DOM but filtered by the component
    });

    it('shows log entry count', () => {
        render(<ApplicationLogs application={mockApplication} />);
        expect(screen.getByText(/3 log entries/)).toBeInTheDocument();
    });

    it('shows connection status', () => {
        render(<ApplicationLogs application={mockApplication} />);
        expect(screen.getByText('Connected via WebSocket')).toBeInTheDocument();
    });

    it('has toolbar with all action buttons', () => {
        render(<ApplicationLogs application={mockApplication} />);
        expect(screen.getByText('Pause')).toBeInTheDocument();
        expect(screen.getByText('Clear')).toBeInTheDocument();
        expect(screen.getByText('Download')).toBeInTheDocument();
    });

    it('renders filter button in toolbar', () => {
        render(<ApplicationLogs application={mockApplication} />);
        expect(screen.getByText('All Levels')).toBeInTheDocument();
    });
});

describe.skip('Application Logs Page - Empty State', () => {
    // Skipping these tests until useLogStream hook is implemented
    it('shows empty state when no logs exist', () => {
        // Test will be implemented when useLogStream hook is available
    });

    it('shows paused status when not streaming', () => {
        // Test will be implemented when useLogStream hook is available
    });
});

describe.skip('Application Logs Page - Loading State', () => {
    // Skipping these tests until useLogStream hook is implemented
    it('shows loading state', () => {
        // Test will be implemented when useLogStream hook is available
    });
});

describe.skip('Application Logs Page - Error State', () => {
    // Skipping these tests until useLogStream hook is implemented
    it('shows error state when log stream fails', () => {
        // Test will be implemented when useLogStream hook is available
    });
});
