import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../utils/test-utils';
import ServersIndex from '@/pages/Servers/Index';
import type { Server } from '@/types';
import { router } from '@inertiajs/react';

const mockServers: Server[] = [
    {
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
    },
    {
        id: 2,
        uuid: 'server-uuid-2',
        name: 'staging-server',
        description: 'Staging environment server',
        ip: '192.168.1.101',
        port: 22,
        user: 'deploy',
        is_reachable: false,
        is_usable: false,
        settings: {
            id: 2,
            server_id: 2,
            is_build_server: true,
            concurrent_builds: 4,
        },
        created_at: '2024-02-01T00:00:00Z',
        updated_at: '2024-02-15T00:00:00Z',
    },
];

describe('Servers Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        global.confirm = vi.fn(() => true);
    });

    describe('rendering', () => {
        it('should render page title and description', () => {
            render(<ServersIndex servers={mockServers} />);

            // "Servers" appears in multiple places (title, breadcrumb)
            const serversText = screen.getAllByText('Servers');
            expect(serversText.length).toBeGreaterThan(0);
            expect(screen.getByText('Manage your connected servers')).toBeInTheDocument();
        });

        it('should render breadcrumbs', () => {
            render(<ServersIndex servers={mockServers} />);

            const breadcrumbs = screen.getAllByText('Servers');
            expect(breadcrumbs.length).toBeGreaterThan(0);
        });

        it('should render Add Server button', () => {
            render(<ServersIndex servers={mockServers} />);

            const addButton = screen.getByText('Add Server');
            expect(addButton).toBeInTheDocument();
        });

        it('should render all servers in the list', () => {
            render(<ServersIndex servers={mockServers} />);

            expect(screen.getByText('production-server')).toBeInTheDocument();
            expect(screen.getByText('staging-server')).toBeInTheDocument();
        });

        it('should display server IP and port information', () => {
            render(<ServersIndex servers={mockServers} />);

            expect(screen.getByText(/192.168.1.100:22/)).toBeInTheDocument();
            expect(screen.getByText(/192.168.1.101:22/)).toBeInTheDocument();
        });
    });

    describe('empty state', () => {
        it('should render empty state when no servers', () => {
            render(<ServersIndex servers={[]} />);

            expect(screen.getByText('No servers connected')).toBeInTheDocument();
            expect(screen.getByText('Add your first server to start deploying applications.')).toBeInTheDocument();
        });

        it('should render Add Server button in empty state', () => {
            render(<ServersIndex servers={[]} />);

            const addButtons = screen.getAllByText('Add Server');
            expect(addButtons.length).toBeGreaterThan(0);
        });

        it('should not render server cards in empty state', () => {
            render(<ServersIndex servers={[]} />);

            expect(screen.queryByText('production-server')).not.toBeInTheDocument();
            expect(screen.queryByText('staging-server')).not.toBeInTheDocument();
        });
    });

    describe('server status indicators', () => {
        it('should show Online badge for reachable and usable servers', () => {
            render(<ServersIndex servers={mockServers} />);

            const onlineBadges = screen.getAllByText('Online');
            expect(onlineBadges.length).toBeGreaterThan(0);
        });

        it('should show Offline badge for unreachable servers', () => {
            render(<ServersIndex servers={mockServers} />);

            const offlineBadges = screen.getAllByText('Offline');
            expect(offlineBadges.length).toBeGreaterThan(0);
        });

        it('should show Connected status for online servers', () => {
            render(<ServersIndex servers={mockServers} />);

            expect(screen.getByText('Connected')).toBeInTheDocument();
        });

        it('should show Disconnected status for offline servers', () => {
            render(<ServersIndex servers={mockServers} />);

            expect(screen.getByText('Disconnected')).toBeInTheDocument();
        });

        it('should render status icons correctly', () => {
            render(<ServersIndex servers={mockServers} />);

            // Both status text should be present
            expect(screen.getByText('Connected')).toBeInTheDocument();
            expect(screen.getByText('Disconnected')).toBeInTheDocument();
        });
    });

    describe('server descriptions', () => {
        it('should display server description when present', () => {
            render(<ServersIndex servers={mockServers} />);

            expect(screen.getByText('Main production server')).toBeInTheDocument();
            expect(screen.getByText('Staging environment server')).toBeInTheDocument();
        });

        it('should handle servers without description', () => {
            const serverWithoutDescription = [
                {
                    ...mockServers[0],
                    description: null,
                },
            ];

            render(<ServersIndex servers={serverWithoutDescription} />);

            expect(screen.getByText('production-server')).toBeInTheDocument();
        });
    });

    describe('navigation', () => {
        it('should link to server detail page when card is clicked', async () => {
            const { user } = render(<ServersIndex servers={mockServers} />);

            const serverCard = screen.getByText('production-server').closest('a');
            expect(serverCard).toHaveAttribute('href', '/servers/server-uuid-1');
        });

        it('should link to create server page from Add Server button', () => {
            render(<ServersIndex servers={mockServers} />);

            const addButton = screen.getByText('Add Server').closest('a');
            expect(addButton).toHaveAttribute('href', '/servers/create');
        });
    });

    describe('dropdown menu actions', () => {
        it('should show dropdown menu for each server', async () => {
            const { user } = render(<ServersIndex servers={mockServers} />);

            // Find all dropdown trigger buttons
            const dropdownButtons = screen.getAllByRole('button');
            const dropdownTriggers = dropdownButtons.filter(btn =>
                btn.querySelector('svg') && btn.className.includes('rounded-md')
            );

            expect(dropdownTriggers.length).toBe(mockServers.length);
        });

        it('should call validate endpoint when Validate Server is clicked', async () => {
            const { user } = render(<ServersIndex servers={mockServers} />);

            // Open dropdown menu by clicking the MoreVertical button
            const dropdownButtons = screen.getAllByRole('button');
            const dropdownTrigger = dropdownButtons.find(btn =>
                btn.querySelector('svg') && btn.className.includes('rounded-md')
            );

            if (dropdownTrigger) {
                await user.click(dropdownTrigger);

                // Click Validate Server option
                await waitFor(() => {
                    const validateButton = screen.getByText('Validate Server');
                    expect(validateButton).toBeInTheDocument();
                });

                const validateButton = screen.getByText('Validate Server');
                await user.click(validateButton);

                expect(router.post).toHaveBeenCalledWith('/servers/server-uuid-1/validate');
            }
        });

        it('should navigate to terminal when Open Terminal is clicked', async () => {
            const { user } = render(<ServersIndex servers={mockServers} />);

            const dropdownButtons = screen.getAllByRole('button');
            const dropdownTrigger = dropdownButtons.find(btn =>
                btn.querySelector('svg') && btn.className.includes('rounded-md')
            );

            if (dropdownTrigger) {
                await user.click(dropdownTrigger);

                await waitFor(() => {
                    const terminalButton = screen.getByText('Open Terminal');
                    expect(terminalButton).toBeInTheDocument();
                });

                const terminalButton = screen.getByText('Open Terminal');
                await user.click(terminalButton);

                expect(router.visit).toHaveBeenCalledWith('/servers/server-uuid-1/terminal');
            }
        });

        it('should navigate to settings when Server Settings is clicked', async () => {
            const { user } = render(<ServersIndex servers={mockServers} />);

            const dropdownButtons = screen.getAllByRole('button');
            const dropdownTrigger = dropdownButtons.find(btn =>
                btn.querySelector('svg') && btn.className.includes('rounded-md')
            );

            if (dropdownTrigger) {
                await user.click(dropdownTrigger);

                await waitFor(() => {
                    const settingsButton = screen.getByText('Server Settings');
                    expect(settingsButton).toBeInTheDocument();
                });

                const settingsButton = screen.getByText('Server Settings');
                await user.click(settingsButton);

                expect(router.visit).toHaveBeenCalledWith('/servers/server-uuid-1/settings');
            }
        });

        it('should show confirmation dialog when Delete Server is clicked', async () => {
            const { user } = render(<ServersIndex servers={mockServers} />);

            const dropdownButtons = screen.getAllByRole('button');
            const dropdownTrigger = dropdownButtons.find(btn =>
                btn.querySelector('svg') && btn.className.includes('rounded-md')
            );

            if (dropdownTrigger) {
                await user.click(dropdownTrigger);

                await waitFor(() => {
                    const deleteButton = screen.getByText('Delete Server');
                    expect(deleteButton).toBeInTheDocument();
                });

                const deleteButton = screen.getByText('Delete Server');
                await user.click(deleteButton);

                expect(global.confirm).toHaveBeenCalledWith(
                    expect.stringContaining('production-server')
                );
            }
        });

        it('should call delete endpoint when deletion is confirmed', async () => {
            const { user } = render(<ServersIndex servers={mockServers} />);

            const dropdownButtons = screen.getAllByRole('button');
            const dropdownTrigger = dropdownButtons.find(btn =>
                btn.querySelector('svg') && btn.className.includes('rounded-md')
            );

            if (dropdownTrigger) {
                await user.click(dropdownTrigger);

                await waitFor(() => {
                    const deleteButton = screen.getByText('Delete Server');
                    expect(deleteButton).toBeInTheDocument();
                });

                const deleteButton = screen.getByText('Delete Server');
                await user.click(deleteButton);

                expect(router.delete).toHaveBeenCalledWith('/servers/server-uuid-1');
            }
        });

        it('should not delete when user cancels', async () => {
            global.confirm = vi.fn(() => false);
            const { user } = render(<ServersIndex servers={mockServers} />);

            const dropdownButtons = screen.getAllByRole('button');
            const dropdownTrigger = dropdownButtons.find(btn =>
                btn.querySelector('svg') && btn.className.includes('rounded-md')
            );

            if (dropdownTrigger) {
                await user.click(dropdownTrigger);

                await waitFor(() => {
                    const deleteButton = screen.getByText('Delete Server');
                    expect(deleteButton).toBeInTheDocument();
                });

                const deleteButton = screen.getByText('Delete Server');
                await user.click(deleteButton);

                expect(router.delete).not.toHaveBeenCalled();
            }
        });
    });

    describe('edge cases', () => {
        it('should handle single server correctly', () => {
            render(<ServersIndex servers={[mockServers[0]]} />);

            expect(screen.getByText('production-server')).toBeInTheDocument();
            expect(screen.queryByText('staging-server')).not.toBeInTheDocument();
        });

        it('should handle server with minimal data', () => {
            const minimalServer: Server = {
                id: 3,
                uuid: 'server-uuid-3',
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

            render(<ServersIndex servers={[minimalServer]} />);

            expect(screen.getByText('minimal-server')).toBeInTheDocument();
            expect(screen.getByText(/127.0.0.1:22/)).toBeInTheDocument();
        });

        it('should handle servers with different port numbers', () => {
            const customPortServer: Server = {
                ...mockServers[0],
                port: 2222,
            };

            render(<ServersIndex servers={[customPortServer]} />);

            expect(screen.getByText(/192.168.1.100:2222/)).toBeInTheDocument();
        });
    });
});
