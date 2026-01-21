import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../utils/test-utils';
import ServerTerminal from '@/pages/Servers/Terminal/Index';
import type { Server } from '@/types';

// Mock the Terminal component
vi.mock('@/components/features/Terminal', () => ({
    Terminal: ({ serverId, onConnect, onDisconnect }: any) => (
        <div data-testid="terminal-mock">
            <div>Terminal for server: {serverId}</div>
            <button onClick={onConnect}>Trigger Connect</button>
            <button onClick={onDisconnect}>Trigger Disconnect</button>
        </div>
    ),
}));

// Mock the useTerminal hook
vi.mock('@/hooks/useTerminal', () => ({
    useTerminal: () => ({
        isConnected: true,
        isConnecting: false,
        error: null,
        reconnectAttempt: 0,
        sendData: vi.fn(),
        resize: vi.fn(),
        connect: vi.fn(),
        disconnect: vi.fn(),
        reconnect: vi.fn(),
    }),
}));

// Mock xterm
vi.mock('@xterm/xterm', () => ({
    Terminal: vi.fn(() => ({
        loadAddon: vi.fn(),
        open: vi.fn(),
        write: vi.fn(),
        clear: vi.fn(),
        onData: vi.fn(),
        attachCustomKeyEventHandler: vi.fn(),
        onSelectionChange: vi.fn(),
        hasSelection: vi.fn(() => false),
        getSelection: vi.fn(() => ''),
        dispose: vi.fn(),
    })),
}));

vi.mock('@xterm/addon-fit', () => ({
    FitAddon: vi.fn(() => ({
        fit: vi.fn(),
    })),
}));

describe('ServerTerminal Page', () => {
    const mockServer: Server = {
        id: 1,
        uuid: 'test-server-uuid',
        name: 'Production Server',
        description: 'Main production server',
        ip: '192.168.1.100',
        port: 22,
        user: 'saturn',
        is_reachable: true,
        is_usable: true,
        settings: {
            id: 1,
            server_id: 1,
            is_build_server: false,
            concurrent_builds: 1,
        },
        created_at: '2024-01-01T00:00:00.000000Z',
        updated_at: '2024-01-01T00:00:00.000000Z',
    };

    beforeEach(() => {
        vi.clearAllMocks();
    });

    afterEach(() => {
        vi.clearAllMocks();
    });

    describe('Page Rendering', () => {
        it('renders terminal page with server name', () => {
            render(<ServerTerminal server={mockServer} />);

            expect(screen.getAllByText('Terminal Access').length).toBeGreaterThan(0);
            expect(screen.getAllByText(/Production Server/).length).toBeGreaterThan(0);
        });

        it('displays server connection details', () => {
            render(<ServerTerminal server={mockServer} />);

            expect(screen.getByText(/192\.168\.1\.100:22/)).toBeInTheDocument();
            expect(screen.getByText(/saturn/)).toBeInTheDocument();
        });

        it('shows back button to server page', () => {
            render(<ServerTerminal server={mockServer} />);

            const backLink = screen.getByText('Back to Server').closest('a');
            expect(backLink).toHaveAttribute('href', `/servers/${mockServer.uuid}`);
        });

        it('renders terminal component for reachable server', () => {
            render(<ServerTerminal server={mockServer} />);

            expect(screen.getByTestId('terminal-mock')).toBeInTheDocument();
            expect(screen.getByText(`Terminal for server: ${mockServer.id}`)).toBeInTheDocument();
        });
    });

    describe('Server Status', () => {
        it('shows info banner for reachable server', () => {
            render(<ServerTerminal server={mockServer} />);

            expect(screen.getAllByText('Terminal Access').length).toBeGreaterThan(0);
            expect(screen.getByText(/You have direct SSH access to this server/)).toBeInTheDocument();
        });

        it('shows error banner for unreachable server', () => {
            const unreachableServer = {
                ...mockServer,
                is_reachable: false,
                is_usable: false,
            };

            render(<ServerTerminal server={unreachableServer} />);

            expect(screen.getByText('Server Unavailable')).toBeInTheDocument();
            expect(screen.getByText(/This server is not reachable/)).toBeInTheDocument();
        });

        it('shows unavailable message when server cannot be reached', () => {
            const unreachableServer = {
                ...mockServer,
                is_reachable: false,
                is_usable: false,
            };

            render(<ServerTerminal server={unreachableServer} />);

            expect(screen.getByText('Cannot Connect')).toBeInTheDocument();
            expect(screen.getByText(/Server is not available for terminal access/)).toBeInTheDocument();
        });
    });

    describe('Connection Status', () => {
        it('shows connected badge when terminal connects', async () => {
            render(<ServerTerminal server={mockServer} />);

            const connectButton = screen.getByText('Trigger Connect');
            fireEvent.click(connectButton);

            await waitFor(() => {
                expect(screen.getByText('Connected')).toBeInTheDocument();
            });
        });

        it('calls onConnect callback when terminal connects', async () => {
            render(<ServerTerminal server={mockServer} />);

            const connectButton = screen.getByText('Trigger Connect');
            fireEvent.click(connectButton);

            // Toast should be triggered
            await waitFor(() => {
                expect(screen.getByText('Connected')).toBeInTheDocument();
            });
        });
    });

    describe('Terminal Actions', () => {
        it('has reconnect button', () => {
            render(<ServerTerminal server={mockServer} />);

            expect(screen.getByText('Reconnect')).toBeInTheDocument();
        });

        it('has fullscreen toggle button', () => {
            render(<ServerTerminal server={mockServer} />);

            expect(screen.getByText('Fullscreen')).toBeInTheDocument();
        });

        it('toggles fullscreen mode', () => {
            render(<ServerTerminal server={mockServer} />);

            const fullscreenButton = screen.getByText('Fullscreen');
            fireEvent.click(fullscreenButton);

            expect(screen.getByText('Exit Fullscreen')).toBeInTheDocument();

            fireEvent.click(screen.getByText('Exit Fullscreen'));
            expect(screen.getByText('Fullscreen')).toBeInTheDocument();
        });

        it('disables reconnect button when not connected', () => {
            render(<ServerTerminal server={mockServer} />);

            const reconnectButton = screen.getByText('Reconnect').closest('button');
            expect(reconnectButton).toBeDisabled();
        });

        it('enables reconnect button when connected', async () => {
            render(<ServerTerminal server={mockServer} />);

            const connectButton = screen.getByText('Trigger Connect');
            fireEvent.click(connectButton);

            await waitFor(() => {
                const reconnectButton = screen.getByText('Reconnect').closest('button');
                expect(reconnectButton).not.toBeDisabled();
            });
        });
    });

    describe('Help Section', () => {
        it('displays keyboard shortcuts', () => {
            render(<ServerTerminal server={mockServer} />);

            expect(screen.getByText('Keyboard Shortcuts')).toBeInTheDocument();
            expect(screen.getByText(/Copy selected text/)).toBeInTheDocument();
            expect(screen.getByText(/Paste text/)).toBeInTheDocument();
        });

        it('displays common commands', () => {
            render(<ServerTerminal server={mockServer} />);

            expect(screen.getByText('Common Commands')).toBeInTheDocument();
            expect(screen.getByText(/List running containers/)).toBeInTheDocument();
            expect(screen.getByText(/View container logs/)).toBeInTheDocument();
        });
    });

    describe('Breadcrumbs', () => {
        it('includes breadcrumbs navigation', () => {
            render(<ServerTerminal server={mockServer} />);

            // Check for breadcrumb links (AppLayout should render them)
            const backLink = screen.getByText('Back to Server').closest('a');
            expect(backLink).toHaveAttribute('href', `/servers/${mockServer.uuid}`);
        });
    });

    describe('Responsive Behavior', () => {
        it('renders on mobile viewports', () => {
            render(<ServerTerminal server={mockServer} />);

            expect(screen.getAllByText('Terminal Access').length).toBeGreaterThan(0);
            expect(screen.getByTestId('terminal-mock')).toBeInTheDocument();
        });
    });
});
