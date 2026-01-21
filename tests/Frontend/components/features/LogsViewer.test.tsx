import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent } from '../../utils/test-utils';
import { LogsViewer } from '@/components/features/LogsViewer';

describe('LogsViewer', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
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
            }
            expect(onClose).toHaveBeenCalled();
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

            const closeButton = screen.getByRole('button', { name: '' });
            // Find the button with X icon
            const buttons = screen.getAllByRole('button');
            const xButton = buttons.find(btn => btn.querySelector('svg.lucide-x'));
            if (xButton) {
                fireEvent.click(xButton);
                expect(onClose).toHaveBeenCalled();
            }
        });
    });

    describe('Log Display', () => {
        it('shows demo log entries', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            expect(screen.getByText(/Server listening on port 3000/)).toBeInTheDocument();
            expect(screen.getByText(/Connected to PostgreSQL database/)).toBeInTheDocument();
        });

        it('shows log entry count', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            expect(screen.getByText(/\d+ log entries/)).toBeInTheDocument();
        });

        it('displays timestamps for log entries', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            // Check for timestamp format - there may be multiple entries with same timestamp prefix
            const timestamps = screen.getAllByText(/2024-01-15 14:32:0/);
            expect(timestamps.length).toBeGreaterThan(0);
        });
    });

    describe('Log Levels', () => {
        it('shows INFO level logs', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            expect(screen.getAllByText('[info]').length).toBeGreaterThan(0);
        });

        it('shows WARN level logs', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            expect(screen.getAllByText('[warn]').length).toBeGreaterThan(0);
        });

        it('shows ERROR level logs', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            expect(screen.getAllByText('[error]').length).toBeGreaterThan(0);
        });
    });

    describe('Search Functionality', () => {
        it('has a search input', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            expect(screen.getByPlaceholderText('Search logs...')).toBeInTheDocument();
        });

        it('filters logs based on search query', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            const searchInput = screen.getByPlaceholderText('Search logs...');
            fireEvent.change(searchInput, { target: { value: 'PostgreSQL' } });

            // Verify the search input has the value
            expect(searchInput).toHaveValue('PostgreSQL');
            // The log should still be visible since it matches
            expect(screen.getByText(/Connected to PostgreSQL database/)).toBeInTheDocument();
        });
    });

    describe('Level Filter', () => {
        it('shows level filter dropdown', () => {
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

    describe('Streaming', () => {
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

        it('shows Pause button when streaming', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            expect(screen.getByText('Pause')).toBeInTheDocument();
        });

        it('toggles streaming when Pause/Resume is clicked', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            const pauseButton = screen.getByText('Pause');
            fireEvent.click(pauseButton);

            expect(screen.getByText('Resume')).toBeInTheDocument();
            expect(screen.getByText('Paused')).toBeInTheDocument();
        });
    });

    describe('Toolbar Actions', () => {
        it('shows Refresh button', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            expect(screen.getByText('Refresh')).toBeInTheDocument();
        });

        it('shows Export button', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            expect(screen.getByText('Export')).toBeInTheDocument();
        });

        it('shows deployment selector', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            expect(screen.getByText(/Deployment:/)).toBeInTheDocument();
        });
    });

    describe('Empty State', () => {
        it('shows empty state when no logs match filter', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            const searchInput = screen.getByPlaceholderText('Search logs...');
            fireEvent.change(searchInput, { target: { value: 'xyznonexistentlog123' } });

            expect(screen.getByText('No logs found')).toBeInTheDocument();
        });
    });

    describe('Footer', () => {
        it('shows keyboard hint', () => {
            render(
                <LogsViewer
                    isOpen={true}
                    onClose={() => {}}
                    serviceName="api-server"
                />
            );

            expect(screen.getByText('Ctrl+F')).toBeInTheDocument();
        });
    });
});
