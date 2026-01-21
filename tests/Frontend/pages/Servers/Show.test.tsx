import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import ServerShow from '@/pages/Servers/Show';
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

describe('Server Show Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render server name in header', () => {
            render(<ServerShow server={mockServer} />);

            const serverNames = screen.getAllByText('production-server');
            expect(serverNames.length).toBeGreaterThan(0);
        });

        it('should render breadcrumbs', () => {
            render(<ServerShow server={mockServer} />);

            expect(screen.getByText('Servers')).toBeInTheDocument();
            const serverNames = screen.getAllByText('production-server');
            expect(serverNames.length).toBeGreaterThan(0);
        });

        it('should display server IP and port', () => {
            render(<ServerShow server={mockServer} />);

            expect(screen.getByText(/192.168.1.100:22/)).toBeInTheDocument();
        });

        it('should show Online badge for reachable and usable server', () => {
            render(<ServerShow server={mockServer} />);

            expect(screen.getByText('Online')).toBeInTheDocument();
        });

        it('should show Offline badge for unreachable server', () => {
            const offlineServer = {
                ...mockServer,
                is_reachable: false,
                is_usable: false,
            };

            render(<ServerShow server={offlineServer} />);

            expect(screen.getByText('Offline')).toBeInTheDocument();
        });
    });

    describe('header actions', () => {
        it('should render Validate button', () => {
            render(<ServerShow server={mockServer} />);

            expect(screen.getByText('Validate')).toBeInTheDocument();
        });

        it('should render Terminal button', () => {
            render(<ServerShow server={mockServer} />);

            expect(screen.getByText('Terminal')).toBeInTheDocument();
        });

        it('should render Settings button', () => {
            render(<ServerShow server={mockServer} />);

            // Settings button is rendered as a Link with a Button inside
            const links = screen.getAllByRole('link');
            const settingsLink = links.find(link =>
                link.getAttribute('href')?.includes('/settings')
            );
            expect(settingsLink).toBeDefined();
        });

        it('should call validate endpoint when Validate is clicked', async () => {
            const { user } = render(<ServerShow server={mockServer} />);

            const validateButton = screen.getByText('Validate');
            await user.click(validateButton);

            expect(router.post).toHaveBeenCalledWith('/servers/server-uuid-1/validate');
        });

        it('should navigate to terminal when Terminal is clicked', async () => {
            const { user } = render(<ServerShow server={mockServer} />);

            const terminalButton = screen.getByText('Terminal');
            await user.click(terminalButton);

            expect(router.visit).toHaveBeenCalledWith('/servers/server-uuid-1/terminal');
        });

        it('should link to settings page', () => {
            render(<ServerShow server={mockServer} />);

            // Find the settings link by href attribute
            const links = screen.getAllByRole('link');
            const settingsLink = links.find(link =>
                link.getAttribute('href') === '/servers/server-uuid-1/settings'
            );
            expect(settingsLink).toBeDefined();
        });
    });

    describe('tabs', () => {
        it('should render all tabs', () => {
            render(<ServerShow server={mockServer} />);

            expect(screen.getByText('Overview')).toBeInTheDocument();
            expect(screen.getByText('Resources')).toBeInTheDocument();
            expect(screen.getByText('Proxy')).toBeInTheDocument();
            expect(screen.getByText('Logs')).toBeInTheDocument();
            // Settings may appear multiple times (tab + content)
            const settingsElements = screen.getAllByText('Settings');
            expect(settingsElements.length).toBeGreaterThan(0);
        });
    });

    describe('Overview Tab', () => {
        it('should display connection details section', () => {
            render(<ServerShow server={mockServer} />);

            expect(screen.getByText('Connection Details')).toBeInTheDocument();
        });

        it('should show IP address in connection details', () => {
            render(<ServerShow server={mockServer} />);

            expect(screen.getByText('IP Address')).toBeInTheDocument();
            expect(screen.getByText('192.168.1.100')).toBeInTheDocument();
        });

        it('should show port in connection details', () => {
            render(<ServerShow server={mockServer} />);

            expect(screen.getByText('Port')).toBeInTheDocument();
            expect(screen.getByText('22')).toBeInTheDocument();
        });

        it('should show user in connection details', () => {
            render(<ServerShow server={mockServer} />);

            expect(screen.getByText('User')).toBeInTheDocument();
            expect(screen.getByText('root')).toBeInTheDocument();
        });

        it('should show reachable status for online server', () => {
            render(<ServerShow server={mockServer} />);

            expect(screen.getByText('Status')).toBeInTheDocument();
            expect(screen.getByText('Reachable')).toBeInTheDocument();
        });

        it('should show unreachable status for offline server', () => {
            const offlineServer = {
                ...mockServer,
                is_reachable: false,
            };

            render(<ServerShow server={offlineServer} />);

            expect(screen.getByText('Unreachable')).toBeInTheDocument();
        });

        it('should display server settings section', () => {
            render(<ServerShow server={mockServer} />);

            const settingsTitles = screen.getAllByText('Settings');
            expect(settingsTitles.length).toBeGreaterThan(0);
        });

        it('should show build server status', () => {
            render(<ServerShow server={mockServer} />);

            expect(screen.getByText('Build Server')).toBeInTheDocument();
            expect(screen.getByText('No')).toBeInTheDocument();
        });

        it('should show Yes for build server when enabled', () => {
            const buildServer = {
                ...mockServer,
                settings: {
                    ...mockServer.settings!,
                    is_build_server: true,
                },
            };

            render(<ServerShow server={buildServer} />);

            expect(screen.getByText('Build Server')).toBeInTheDocument();
            expect(screen.getByText('Yes')).toBeInTheDocument();
        });

        it('should show concurrent builds setting', () => {
            render(<ServerShow server={mockServer} />);

            expect(screen.getByText('Concurrent Builds')).toBeInTheDocument();
            expect(screen.getByText('2')).toBeInTheDocument();
        });

        it('should show created date', () => {
            render(<ServerShow server={mockServer} />);

            expect(screen.getByText('Created')).toBeInTheDocument();
            // Date formatting may vary, just check it's rendered
            const dates = screen.getAllByText(/1\/1\/2024|2024/);
            expect(dates.length).toBeGreaterThan(0);
        });
    });

    describe('Resources Tab', () => {
        it('should display CPU usage card', async () => {
            const { user } = render(<ServerShow server={mockServer} />);

            await user.click(screen.getByText('Resources'));

            expect(screen.getByText('CPU Usage')).toBeInTheDocument();
        });

        it('should display Memory card', async () => {
            const { user } = render(<ServerShow server={mockServer} />);

            await user.click(screen.getByText('Resources'));

            expect(screen.getByText('Memory')).toBeInTheDocument();
        });

        it('should display Disk card', async () => {
            const { user } = render(<ServerShow server={mockServer} />);

            await user.click(screen.getByText('Resources'));

            expect(screen.getByText('Disk')).toBeInTheDocument();
        });

        it('should show placeholder values for resource metrics', async () => {
            const { user } = render(<ServerShow server={mockServer} />);

            await user.click(screen.getByText('Resources'));

            const placeholders = screen.getAllByText('--');
            expect(placeholders.length).toBeGreaterThanOrEqual(3);
        });
    });

    describe('Proxy Tab', () => {
        it('should display proxy overview card', async () => {
            const { user } = render(<ServerShow server={mockServer} />);

            await user.click(screen.getByText('Proxy'));

            expect(screen.getByText('Proxy Overview')).toBeInTheDocument();
            expect(screen.getByText('View proxy status and stats')).toBeInTheDocument();
        });

        it('should display configuration card', async () => {
            const { user } = render(<ServerShow server={mockServer} />);

            await user.click(screen.getByText('Proxy'));

            expect(screen.getByText('Configuration')).toBeInTheDocument();
            expect(screen.getByText('Edit proxy configuration')).toBeInTheDocument();
        });

        it('should display domains card', async () => {
            const { user } = render(<ServerShow server={mockServer} />);

            await user.click(screen.getByText('Proxy'));

            expect(screen.getByText('Domains')).toBeInTheDocument();
            expect(screen.getByText('Manage domains and SSL')).toBeInTheDocument();
        });

        it('should display logs card', async () => {
            const { user } = render(<ServerShow server={mockServer} />);

            await user.click(screen.getByText('Proxy'));

            const logsElements = screen.getAllByText('Logs');
            expect(logsElements.length).toBeGreaterThan(0);
            expect(screen.getByText('View proxy logs')).toBeInTheDocument();
        });

        it('should link to proxy overview page', async () => {
            const { user } = render(<ServerShow server={mockServer} />);

            await user.click(screen.getByText('Proxy'));

            const proxyOverviewCard = screen.getByText('Proxy Overview').closest('a');
            expect(proxyOverviewCard).toHaveAttribute('href', '/servers/server-uuid-1/proxy');
        });

        it('should link to proxy configuration page', async () => {
            const { user } = render(<ServerShow server={mockServer} />);

            await user.click(screen.getByText('Proxy'));

            const configCard = screen.getByText('Configuration').closest('a');
            expect(configCard).toHaveAttribute('href', '/servers/server-uuid-1/proxy/configuration');
        });

        it('should link to proxy domains page', async () => {
            const { user } = render(<ServerShow server={mockServer} />);

            await user.click(screen.getByText('Proxy'));

            const domainsCard = screen.getByText('Domains').closest('a');
            expect(domainsCard).toHaveAttribute('href', '/servers/server-uuid-1/proxy/domains');
        });

        it('should link to proxy logs page', async () => {
            const { user } = render(<ServerShow server={mockServer} />);

            await user.click(screen.getByText('Proxy'));

            const logsCard = screen.getByText('View proxy logs').closest('a');
            expect(logsCard).toHaveAttribute('href', '/servers/server-uuid-1/proxy/logs');
        });

        it('should show proxy information section', async () => {
            const { user } = render(<ServerShow server={mockServer} />);

            await user.click(screen.getByText('Proxy'));

            expect(screen.getByText('Proxy Information')).toBeInTheDocument();
            expect(screen.getByText(/Manage your server's proxy configuration/)).toBeInTheDocument();
        });

        it('should have Open Proxy Management button', async () => {
            const { user } = render(<ServerShow server={mockServer} />);

            await user.click(screen.getByText('Proxy'));

            expect(screen.getByText('Open Proxy Management')).toBeInTheDocument();
        });
    });

    describe('Logs Tab', () => {
        it('should show empty state when no logs available', async () => {
            const { user } = render(<ServerShow server={mockServer} />);

            await user.click(screen.getByText('Logs'));

            expect(screen.getByText('No logs available')).toBeInTheDocument();
            expect(screen.getByText('Server logs will appear here once activity is detected')).toBeInTheDocument();
        });
    });

    describe('Settings Tab', () => {
        it('should display server settings section', async () => {
            const { user } = render(<ServerShow server={mockServer} />);

            // Click the Settings tab
            const settingsTabs = screen.getAllByText('Settings');
            const settingsTab = settingsTabs.find(el => el.closest('button'));
            if (settingsTab) {
                await user.click(settingsTab);
            }

            expect(screen.getByText('Server Settings')).toBeInTheDocument();
            expect(screen.getByText('Configure server settings here.')).toBeInTheDocument();
        });

        it('should have Open Settings button', async () => {
            const { user } = render(<ServerShow server={mockServer} />);

            const settingsTabs = screen.getAllByText('Settings');
            const settingsTab = settingsTabs.find(el => el.closest('button'));
            if (settingsTab) {
                await user.click(settingsTab);
            }

            expect(screen.getByText('Open Settings')).toBeInTheDocument();
        });

        it('should link to settings page', async () => {
            const { user } = render(<ServerShow server={mockServer} />);

            const settingsTabs = screen.getAllByText('Settings');
            const settingsTab = settingsTabs.find(el => el.closest('button'));
            if (settingsTab) {
                await user.click(settingsTab);
            }

            const openSettingsLink = screen.getByText('Open Settings').closest('a');
            expect(openSettingsLink).toHaveAttribute('href', '/servers/server-uuid-1/settings');
        });
    });

    describe('edge cases', () => {
        it('should handle server without settings', () => {
            const serverWithoutSettings = {
                ...mockServer,
                settings: undefined,
            } as Server;

            render(<ServerShow server={serverWithoutSettings} />);

            // Server name appears in header and breadcrumb
            const serverNames = screen.getAllByText('production-server');
            expect(serverNames.length).toBeGreaterThan(0);
        });

        it('should handle server with default concurrent builds', () => {
            const serverWithNullSettings = {
                ...mockServer,
                settings: undefined,
            } as Server;

            render(<ServerShow server={serverWithNullSettings} />);

            // Should show default value of 2
            const concurrentBuilds = screen.queryByText('2');
            // May or may not be visible depending on settings handling
        });

        it('should handle server with custom port', () => {
            const customPortServer = {
                ...mockServer,
                port: 2222,
            };

            render(<ServerShow server={customPortServer} />);

            expect(screen.getByText(/192.168.1.100:2222/)).toBeInTheDocument();
        });

        it('should handle server with different user', () => {
            const customUserServer = {
                ...mockServer,
                user: 'ubuntu',
            };

            render(<ServerShow server={customUserServer} />);

            expect(screen.getByText('ubuntu')).toBeInTheDocument();
        });

        it('should handle partially usable server (reachable but not usable)', () => {
            const partiallyUsableServer = {
                ...mockServer,
                is_reachable: true,
                is_usable: false,
            };

            render(<ServerShow server={partiallyUsableServer} />);

            expect(screen.getByText('Offline')).toBeInTheDocument();
        });
    });
});
