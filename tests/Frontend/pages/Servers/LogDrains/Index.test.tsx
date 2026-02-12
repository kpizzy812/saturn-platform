import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../../utils/test-utils';
import ServerLogDrainsIndex from '@/pages/Servers/LogDrains/Index';
import type { Server } from '@/types';
import { router } from '@inertiajs/react';

const mockServer: Server = {
    id: 1,
    uuid: 'server-uuid-1',
    name: 'production-server',
    description: 'Main production server',
    ip: '192.168.1.100',
    port: 22,
    user: 'root',
    is_reachable: true,
    is_usable: true,
    settings: {
        id: 1,
        server_id: 1,
        is_build_server: false,
        concurrent_builds: 2,
    },
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-15T00:00:00Z',
};

const mockLogDrains = [
    {
        id: 1,
        uuid: 'drain-uuid-1',
        name: 'Datadog Production',
        type: 'http' as const,
        endpoint: 'https://http-intake.logs.datadoghq.com',
        enabled: true,
        created_at: '2024-01-01T00:00:00Z',
    },
    {
        id: 2,
        uuid: 'drain-uuid-2',
        name: 'Papertrail Staging',
        type: 'syslog' as const,
        endpoint: 'logs.papertrailapp.com:12345',
        enabled: false,
        created_at: '2024-01-02T00:00:00Z',
    },
    {
        id: 3,
        uuid: 'drain-uuid-3',
        name: 'Custom TCP Logger',
        type: 'tcp' as const,
        endpoint: '10.0.0.1:9000',
        enabled: true,
        created_at: '2024-01-03T00:00:00Z',
    },
];

describe('Server LogDrains Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page title and description', () => {
            render(<ServerLogDrainsIndex server={mockServer} logDrains={mockLogDrains} />);

            const logDrainsText = screen.getAllByText('Log Drains');
            expect(logDrainsText.length).toBeGreaterThan(0);
            expect(screen.getByText('Forward server logs to external services')).toBeInTheDocument();
        });

        it('should render breadcrumbs', () => {
            render(<ServerLogDrainsIndex server={mockServer} />);

            const breadcrumbs = screen.getAllByText('Servers');
            expect(breadcrumbs.length).toBeGreaterThan(0);
        });

        it('should render back to server link', () => {
            render(<ServerLogDrainsIndex server={mockServer} />);

            const backLink = screen.getByText('Back to Server');
            expect(backLink).toBeInTheDocument();
            expect(backLink.closest('a')).toHaveAttribute('href', '/servers/server-uuid-1');
        });

        it('should render New Log Drain button', () => {
            render(<ServerLogDrainsIndex server={mockServer} />);

            const newButton = screen.getByRole('button', { name: /new log drain/i });
            expect(newButton).toBeInTheDocument();
        });

        it('should render info card about log drains', () => {
            render(<ServerLogDrainsIndex server={mockServer} />);

            expect(screen.getByText('About Log Drains')).toBeInTheDocument();
            expect(screen.getByText(/Log drains allow you to forward server and application logs/)).toBeInTheDocument();
        });
    });

    describe('log drains list', () => {
        it('should render all log drains', () => {
            render(<ServerLogDrainsIndex server={mockServer} logDrains={mockLogDrains} />);

            expect(screen.getByText('Datadog Production')).toBeInTheDocument();
            expect(screen.getByText('Papertrail Staging')).toBeInTheDocument();
            expect(screen.getByText('Custom TCP Logger')).toBeInTheDocument();
        });

        it('should display log drain endpoints', () => {
            render(<ServerLogDrainsIndex server={mockServer} logDrains={mockLogDrains} />);

            expect(screen.getByText('https://http-intake.logs.datadoghq.com')).toBeInTheDocument();
            expect(screen.getByText('logs.papertrailapp.com:12345')).toBeInTheDocument();
            expect(screen.getByText('10.0.0.1:9000')).toBeInTheDocument();
        });

        it('should display enabled status badges', () => {
            render(<ServerLogDrainsIndex server={mockServer} logDrains={mockLogDrains} />);

            const enabledBadges = screen.getAllByText('Enabled');
            const disabledBadges = screen.getAllByText('Disabled');

            expect(enabledBadges.length).toBe(2);
            expect(disabledBadges.length).toBe(1);
        });

        it('should display log drain type badges', () => {
            render(<ServerLogDrainsIndex server={mockServer} logDrains={mockLogDrains} />);

            expect(screen.getByText('HTTP')).toBeInTheDocument();
            expect(screen.getByText('SYSLOG')).toBeInTheDocument();
            expect(screen.getByText('TCP')).toBeInTheDocument();
        });

        it('should render toggle and delete buttons for each drain', () => {
            render(<ServerLogDrainsIndex server={mockServer} logDrains={mockLogDrains} />);

            const allButtons = screen.getAllByRole('button');
            // Should have buttons for: toggle (3x), delete (3x), New Log Drain (1x), Popular Services (4x)
            expect(allButtons.length).toBeGreaterThan(6);
        });
    });

    describe('empty state', () => {
        it('should show empty state when no log drains', () => {
            render(<ServerLogDrainsIndex server={mockServer} logDrains={[]} />);

            expect(screen.getByText('No log drains configured')).toBeInTheDocument();
            expect(screen.getByText('Set up your first log drain to start forwarding logs')).toBeInTheDocument();
        });

        it('should render Create Log Drain button in empty state', () => {
            render(<ServerLogDrainsIndex server={mockServer} logDrains={[]} />);

            const createButtons = screen.getAllByRole('button');
            const createButton = createButtons.find(btn => btn.textContent?.includes('Create Log Drain'));
            expect(createButton).toBeInTheDocument();
        });

        it('should not show log drain items in empty state', () => {
            render(<ServerLogDrainsIndex server={mockServer} logDrains={[]} />);

            expect(screen.queryByText('Datadog Production')).not.toBeInTheDocument();
            expect(screen.queryByText('Enabled')).not.toBeInTheDocument();
        });
    });

    describe('popular services section', () => {
        it('should render popular log services section', () => {
            render(<ServerLogDrainsIndex server={mockServer} />);

            expect(screen.getByText('Popular Log Services')).toBeInTheDocument();
        });

        it('should display all popular services', () => {
            render(<ServerLogDrainsIndex server={mockServer} />);

            expect(screen.getByText('Datadog')).toBeInTheDocument();
            expect(screen.getByText('New Relic')).toBeInTheDocument();
            expect(screen.getByText('Papertrail')).toBeInTheDocument();
            expect(screen.getByText('Logtail')).toBeInTheDocument();
        });

        it('should have external links for popular services', () => {
            render(<ServerLogDrainsIndex server={mockServer} />);

            const datadogLink = screen.getByText('Datadog').closest('a');
            const newRelicLink = screen.getByText('New Relic').closest('a');

            expect(datadogLink).toHaveAttribute('href', 'https://www.datadoghq.com');
            expect(datadogLink).toHaveAttribute('target', '_blank');
            expect(datadogLink).toHaveAttribute('rel', 'noopener noreferrer');

            expect(newRelicLink).toHaveAttribute('href', 'https://newrelic.com');
        });
    });

    describe('interactions', () => {
        it('should navigate to create page when New Log Drain is clicked', async () => {
            const { user } = render(<ServerLogDrainsIndex server={mockServer} />);

            const newButton = screen.getByRole('button', { name: /new log drain/i });
            await user.click(newButton);

            expect(router.visit).toHaveBeenCalledWith('/servers/server-uuid-1/log-drains/create');
        });

        it('should have toggle buttons for each drain', () => {
            render(<ServerLogDrainsIndex server={mockServer} logDrains={mockLogDrains} />);

            const allButtons = screen.getAllByRole('button');
            // Should have multiple buttons (toggles, deletes, new drain, create)
            expect(allButtons.length).toBeGreaterThan(mockLogDrains.length);
        });

        it('should display all log drain information', () => {
            render(<ServerLogDrainsIndex server={mockServer} logDrains={mockLogDrains} />);

            // Check all drains are rendered with their information
            expect(screen.getByText('Datadog Production')).toBeInTheDocument();
            expect(screen.getByText('https://http-intake.logs.datadoghq.com')).toBeInTheDocument();
            expect(screen.getByText('HTTP')).toBeInTheDocument();
        });

        it('should render action buttons for each drain', () => {
            render(<ServerLogDrainsIndex server={mockServer} logDrains={mockLogDrains} />);

            // Each drain should have toggle and delete buttons
            const allButtons = screen.getAllByRole('button');
            // Expect multiple buttons (at least 2 per drain + header button)
            expect(allButtons.length).toBeGreaterThan(mockLogDrains.length * 2);
        });
    });

    describe('edge cases', () => {
        it('should handle server with different UUID', () => {
            const differentServer = {
                ...mockServer,
                uuid: 'different-uuid',
                name: 'staging-server',
            };

            render(<ServerLogDrainsIndex server={differentServer} logDrains={mockLogDrains} />);

            const newButton = screen.getByRole('button', { name: /new log drain/i });
            expect(newButton).toBeInTheDocument();
        });

        it('should render with minimal server data', () => {
            const minimalServer: Server = {
                id: 2,
                uuid: 'minimal-uuid',
                name: 'minimal-server',
                description: null,
                ip: '127.0.0.1',
                port: 22,
                user: 'root',
                is_reachable: true,
                is_usable: true,
                settings: null,
                created_at: '2024-01-01T00:00:00Z',
                updated_at: '2024-01-01T00:00:00Z',
            };

            render(<ServerLogDrainsIndex server={minimalServer} />);

            const logDrainsText = screen.getAllByText('Log Drains');
            expect(logDrainsText.length).toBeGreaterThan(0);
        });

        it('should handle single log drain', () => {
            render(<ServerLogDrainsIndex server={mockServer} logDrains={[mockLogDrains[0]]} />);

            expect(screen.getByText('Datadog Production')).toBeInTheDocument();
            expect(screen.queryByText('Papertrail Staging')).not.toBeInTheDocument();
        });

        it('should handle all drains disabled', () => {
            const disabledDrains = mockLogDrains.map(drain => ({ ...drain, enabled: false }));

            render(<ServerLogDrainsIndex server={mockServer} logDrains={disabledDrains} />);

            const disabledBadges = screen.getAllByText('Disabled');
            expect(disabledBadges.length).toBe(3);
        });

        it('should handle all drains enabled', () => {
            const enabledDrains = mockLogDrains.map(drain => ({ ...drain, enabled: true }));

            render(<ServerLogDrainsIndex server={mockServer} logDrains={enabledDrains} />);

            const enabledBadges = screen.getAllByText('Enabled');
            expect(enabledBadges.length).toBe(3);
        });
    });
});
