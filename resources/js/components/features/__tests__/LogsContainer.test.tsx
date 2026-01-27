import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { LogsContainer, type LogLine } from '../LogsContainer';

declare const global: typeof globalThis;

// Mock localStorage
const localStorageMock = (() => {
    let store: Record<string, string> = {};
    return {
        getItem: (key: string) => store[key] || null,
        setItem: (key: string, value: string) => {
            store[key] = value;
        },
        removeItem: (key: string) => {
            delete store[key];
        },
        clear: () => {
            store = {};
        },
    };
})();

Object.defineProperty(window, 'localStorage', { value: localStorageMock });

// Mock clipboard API
Object.assign(navigator, {
    clipboard: {
        writeText: vi.fn().mockResolvedValue(undefined),
    },
});

// Mock isSecureContext so clipboard API is used
Object.defineProperty(window, 'isSecureContext', { value: true });

// Mock URL.createObjectURL and URL.revokeObjectURL
global.URL.createObjectURL = vi.fn(() => 'mock-url');
global.URL.revokeObjectURL = vi.fn();

describe('LogsContainer', () => {
    const mockLogs: LogLine[] = [
        { id: '1', content: 'Starting build...', level: 'info' },
        { id: '2', content: 'Installing dependencies...', level: 'info' },
        { id: '3', content: 'Build completed successfully', level: 'info' },
        { id: '4', content: 'Warning: deprecated API usage', level: 'warn' },
        { id: '5', content: 'Error: Failed to compile', level: 'error' },
    ];

    beforeEach(() => {
        localStorageMock.clear();
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render logs', () => {
            render(<LogsContainer logs={mockLogs} />);

            expect(screen.getByText('Starting build...')).toBeInTheDocument();
            expect(screen.getByText('Installing dependencies...')).toBeInTheDocument();
            expect(screen.getByText('Build completed successfully')).toBeInTheDocument();
        });

        it('should show empty state when no logs', () => {
            render(<LogsContainer logs={[]} emptyMessage="No logs available" />);

            expect(screen.getByText('No logs available')).toBeInTheDocument();
        });

        it('should show loading state when streaming with no logs', () => {
            render(
                <LogsContainer
                    logs={[]}
                    isStreaming={true}
                    loadingMessage="Waiting for logs..."
                />
            );

            expect(screen.getByText('Waiting for logs...')).toBeInTheDocument();
        });

        it('should show title when provided', () => {
            render(<LogsContainer logs={mockLogs} title="Build Logs" />);

            expect(screen.getByText('Build Logs')).toBeInTheDocument();
        });

        it('should show live indicator when streaming', () => {
            render(<LogsContainer logs={mockLogs} isStreaming={true} />);

            expect(screen.getByText('Live')).toBeInTheDocument();
        });

        it('should show connected indicator when connected but not streaming', () => {
            render(<LogsContainer logs={mockLogs} isConnected={true} isStreaming={false} />);

            expect(screen.getByText('Connected')).toBeInTheDocument();
        });

        it('should show line numbers when showLineNumbers is true', () => {
            render(<LogsContainer logs={mockLogs} showLineNumbers={true} />);

            // Line numbers are rendered as text content
            expect(screen.getByText('1')).toBeInTheDocument();
            expect(screen.getByText('2')).toBeInTheDocument();
        });
    });

    describe('toolbar controls', () => {
        it('should render search input when showSearch is true', () => {
            render(<LogsContainer logs={mockLogs} showSearch={true} />);

            expect(screen.getByPlaceholderText('Search...')).toBeInTheDocument();
        });

        it('should render level filter when showLevelFilter is true', () => {
            render(<LogsContainer logs={mockLogs} showLevelFilter={true} />);

            expect(screen.getByRole('combobox')).toBeInTheDocument();
            expect(screen.getByText('All Levels')).toBeInTheDocument();
        });

        it('should render download button when showDownload is true', () => {
            render(<LogsContainer logs={mockLogs} showDownload={true} />);

            expect(screen.getByText('Download')).toBeInTheDocument();
        });

        it('should render copy button when showCopy is true', () => {
            render(<LogsContainer logs={mockLogs} showCopy={true} />);

            expect(screen.getByText('Copy')).toBeInTheDocument();
        });

        it('should render auto-scroll toggle button', () => {
            render(<LogsContainer logs={mockLogs} />);

            expect(screen.getByText('Auto-scroll')).toBeInTheDocument();
        });
    });

    describe('search filtering', () => {
        it('should filter logs by search query', () => {
            render(<LogsContainer logs={mockLogs} showSearch={true} />);

            const searchInput = screen.getByPlaceholderText('Search...');
            fireEvent.change(searchInput, { target: { value: 'Error' } });

            // Only error log should be visible
            expect(screen.queryByText('Starting build...')).not.toBeInTheDocument();
            expect(screen.getByText('Error: Failed to compile')).toBeInTheDocument();
        });

        it('should be case insensitive', () => {
            render(<LogsContainer logs={mockLogs} showSearch={true} />);

            const searchInput = screen.getByPlaceholderText('Search...');
            fireEvent.change(searchInput, { target: { value: 'error' } });

            expect(screen.getByText('Error: Failed to compile')).toBeInTheDocument();
        });
    });

    describe('level filtering', () => {
        it('should filter logs by error level', () => {
            render(<LogsContainer logs={mockLogs} showLevelFilter={true} />);

            const levelSelect = screen.getByRole('combobox');
            fireEvent.change(levelSelect, { target: { value: 'error' } });

            expect(screen.queryByText('Starting build...')).not.toBeInTheDocument();
            expect(screen.getByText('Error: Failed to compile')).toBeInTheDocument();
        });

        it('should filter logs by warning level', () => {
            render(<LogsContainer logs={mockLogs} showLevelFilter={true} />);

            const levelSelect = screen.getByRole('combobox');
            fireEvent.change(levelSelect, { target: { value: 'warn' } });

            expect(screen.queryByText('Starting build...')).not.toBeInTheDocument();
            expect(screen.getByText('Warning: deprecated API usage')).toBeInTheDocument();
        });
    });

    describe('copy functionality', () => {
        it('should copy logs to clipboard', async () => {
            render(<LogsContainer logs={mockLogs} showCopy={true} />);

            const copyButton = screen.getByText('Copy');
            fireEvent.click(copyButton);

            expect(navigator.clipboard.writeText).toHaveBeenCalled();
        });
    });

    describe('footer', () => {
        it('should show total line count', () => {
            render(<LogsContainer logs={mockLogs} />);

            expect(screen.getByText('5 lines')).toBeInTheDocument();
        });

        it('should show filtered line count when filtering', () => {
            render(<LogsContainer logs={mockLogs} showSearch={true} />);

            const searchInput = screen.getByPlaceholderText('Search...');
            fireEvent.change(searchInput, { target: { value: 'Error' } });

            expect(screen.getByText('1 of 5 lines')).toBeInTheDocument();
        });

        it('should show keyboard shortcuts hint', () => {
            render(<LogsContainer logs={mockLogs} />);

            expect(screen.getByText('End')).toBeInTheDocument();
            expect(screen.getByText('scroll to bottom')).toBeInTheDocument();
            expect(screen.getByText('Home')).toBeInTheDocument();
            expect(screen.getByText('scroll to top')).toBeInTheDocument();
        });
    });

    describe('autoscroll toggle', () => {
        it('should toggle autoscroll when button is clicked', () => {
            render(<LogsContainer logs={mockLogs} storageKey="test" />);

            const autoScrollButton = screen.getByText('Auto-scroll');

            // Initially enabled, should have primary color
            expect(autoScrollButton.closest('button')).toHaveClass('text-primary');

            // Click to disable
            fireEvent.click(autoScrollButton);

            // Should no longer have primary color class
            expect(autoScrollButton.closest('button')).not.toHaveClass('text-primary');
        });

        it('should persist autoscroll preference', () => {
            render(<LogsContainer logs={mockLogs} storageKey="persist-test" />);

            const autoScrollButton = screen.getByText('Auto-scroll');
            fireEvent.click(autoScrollButton);

            expect(localStorageMock.getItem('saturn:autoscroll:logs-persist-test')).toBe('false');
        });
    });
});
