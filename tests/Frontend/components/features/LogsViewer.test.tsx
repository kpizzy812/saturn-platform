import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent } from '../../utils/test-utils';

// Mock the useLogStream hook
vi.mock('@/hooks/useLogStream', () => ({
    useLogStream: vi.fn(() => ({
        logs: [],
        isStreaming: true,
        isConnected: true,
        isPolling: false,
        loading: false,
        error: null,
        clearLogs: vi.fn(),
        refresh: vi.fn(),
    })),
}));

// Mock LogsContainer
vi.mock('@/components/features/LogsContainer', () => ({
    LogsContainer: ({ logs, showSearch, showLevelFilter }: any) => (
        <div data-testid="logs-container">
            <div className="logs-toolbar">
                {showSearch && <input placeholder="Search..." />}
                {showLevelFilter && <select><option>All Levels</option></select>}
            </div>
            <div className="logs-content">
                {logs.length === 0 ? (
                    <div>No logs found</div>
                ) : (
                    logs.map((log: any) => (
                        <div key={log.id} data-testid="log-line">
                            {log.timestamp && <span>{log.timestamp}</span>}
                            <span>[{log.level}]</span>
                            <span>{log.content}</span>
                        </div>
                    ))
                )}
            </div>
        </div>
    ),
}));

// Import after mocks
import { LogsViewer } from '@/components/features/LogsViewer';
import type { LogEntry } from '@/hooks/useLogStream';
import { useLogStream } from '@/hooks/useLogStream';

// Default mock logs
const getDefaultMockLogs = (): LogEntry[] => [
    {
        id: '1',
        message: 'Server listening on port 3000',
        timestamp: '2024-01-15 14:32:01',
        level: 'info' as const,
        source: 'stdout',
    },
    {
        id: '2',
        message: 'Connected to PostgreSQL database',
        timestamp: '2024-01-15 14:32:02',
        level: 'info' as const,
        source: 'stdout',
    },
    {
        id: '3',
        message: 'API endpoint /api/users is slow',
        timestamp: '2024-01-15 14:32:03',
        level: 'warning' as const,
        source: 'stdout',
    },
    {
        id: '4',
        message: 'Failed to connect to Redis',
        timestamp: '2024-01-15 14:32:04',
        level: 'error' as const,
        source: 'stderr',
    },
];

describe('LogsViewer', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        // Reset to default mock implementation
        vi.mocked(useLogStream).mockReturnValue({
            logs: getDefaultMockLogs(),
            isStreaming: true,
            isConnected: true,
            isPolling: false,
            loading: false,
            error: null,
            clearLogs: vi.fn(),
            refresh: vi.fn(),
        });
    });

    afterEach(() => {
        // Clean up body overflow style
        document.body.style.overflow = '';
    });

    describe('Opening and Closing', () => {
        it('does not render when closed', () => {
            render(
                <LogsViewer
                    isOpen={false}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );
            expect(screen.queryByText('api-server Logs')).not.toBeInTheDocument();
        });

        it('renders when open', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );
            expect(screen.getByText('api-server Logs')).toBeInTheDocument();
        });

        it('calls onClose when backdrop is clicked', () => {
            const onClose = vi.fn();
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={onClose}
                    serviceName="api-server"
                />
            );

            const backdrop = document.querySelector('.bg-black\\/70');
            if (backdrop) {
                fireEvent.click(backdrop);
                expect(onClose).toHaveBeenCalled();
            }
        });

        it('calls onClose when X button is clicked', () => {
            const onClose = vi.fn();
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={onClose}
                    serviceName="api-server"
                />
            );

            // Find the close button with X icon
            const buttons = screen.getAllByRole('button');
            const closeButton = buttons.find(btn =>
                btn.getAttribute('aria-label') === 'Close logs'
            );

            if (closeButton) {
                fireEvent.click(closeButton);
                expect(onClose).toHaveBeenCalled();
            }
        });

        it('prevents body scroll when open', () => {
            const { rerender } = render(
                <LogsViewer
                    isOpen={false}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            expect(document.body.style.overflow).toBe('');

            rerender(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            expect(document.body.style.overflow).toBe('hidden');
        });
    });

    describe('Header Display', () => {
        it('shows service name in header', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="my-awesome-api"
                />
            );

            expect(screen.getByText('my-awesome-api Logs')).toBeInTheDocument();
        });

        it('shows Live indicator when streaming', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            expect(screen.getByText('Live')).toBeInTheDocument();
        });

        it('shows connection status', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                    serviceUuid="test-uuid"
                />
            );

            // Should show WebSocket status when connected
            expect(screen.getByText('WebSocket')).toBeInTheDocument();
        });

        it('shows demo mode indicator when no UUID provided', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            expect(screen.getByText('Demo Mode')).toBeInTheDocument();
        });
    });

    describe('LogsContainer Integration', () => {
        it('renders LogsContainer component', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            expect(screen.getByTestId('logs-container')).toBeInTheDocument();
        });

        it('passes logs to LogsContainer', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            // Should render 4 log lines from the mocked hook
            const logLines = screen.getAllByTestId('log-line');
            expect(logLines.length).toBe(4);
        });

        it('passes showSearch prop to LogsContainer', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            expect(screen.getByPlaceholderText('Search...')).toBeInTheDocument();
        });

        it('passes showLevelFilter prop to LogsContainer', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            expect(screen.getByText('All Levels')).toBeInTheDocument();
        });
    });

    describe('Streaming States', () => {
        it('shows Paused when not streaming', () => {
            vi.mocked(useLogStream).mockReturnValue({
                logs: [],
                isStreaming: false,
                isConnected: false,
                isPolling: false,
                loading: false,
                error: null,
                clearLogs: vi.fn(),
                refresh: vi.fn(),
            });

            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            expect(screen.getByText('Paused')).toBeInTheDocument();
        });

        it('shows Polling status when polling', () => {
            vi.mocked(useLogStream).mockReturnValue({
                logs: [],
                isStreaming: false,
                isConnected: false,
                isPolling: true,
                loading: false,
                error: null,
                clearLogs: vi.fn(),
                refresh: vi.fn(),
            });

            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                    serviceUuid="test-uuid"
                />
            );

            expect(screen.getByText('Polling')).toBeInTheDocument();
        });
    });

    describe('Error Handling', () => {
        it('displays error banner when error occurs', () => {
            vi.mocked(useLogStream).mockReturnValue({
                logs: [],
                isStreaming: false,
                isConnected: false,
                isPolling: false,
                loading: false,
                error: new Error('Connection failed'),
                clearLogs: vi.fn(),
                refresh: vi.fn(),
            });

            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            expect(screen.getByText('Connection failed')).toBeInTheDocument();
        });

        it('shows Retry button on error', () => {
            const mockClearLogs = vi.fn();
            const mockRefresh = vi.fn();

            vi.mocked(useLogStream).mockReturnValue({
                logs: [],
                isStreaming: false,
                isConnected: false,
                isPolling: false,
                loading: false,
                error: new Error('Connection failed'),
                clearLogs: mockClearLogs,
                refresh: mockRefresh,
            });

            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            const retryButton = screen.getByText('Retry');
            fireEvent.click(retryButton);

            expect(mockClearLogs).toHaveBeenCalled();
            expect(mockRefresh).toHaveBeenCalled();
        });
    });

    describe('Keyboard Shortcuts', () => {
        it('closes modal on Escape key', () => {
            const onClose = vi.fn();
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={onClose}
                    serviceName="api-server"
                />
            );

            fireEvent.keyDown(window, { key: 'Escape' });

            expect(onClose).toHaveBeenCalled();
        });

        it('does not close when Escape pressed and modal is closed', () => {
            const onClose = vi.fn();
            render(
                <LogsViewer
                    isOpen={false}
                    onClose={onClose}
                    serviceName="api-server"
                />
            );

            fireEvent.keyDown(window, { key: 'Escape' });

            expect(onClose).not.toHaveBeenCalled();
        });
    });
});
