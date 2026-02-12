import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../../utils/test-utils';
import ServerSettingsIndex from '@/pages/Servers/Settings/Index';
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

describe('Server Settings Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page title and description', () => {
            render(<ServerSettingsIndex server={mockServer} />);

            expect(screen.getByText('Server Settings')).toBeInTheDocument();
            const serverNames = screen.getAllByText('production-server');
            expect(serverNames.length).toBeGreaterThan(0);
        });

        it('should render breadcrumbs', () => {
            render(<ServerSettingsIndex server={mockServer} />);

            const breadcrumbs = screen.getAllByText('Servers');
            expect(breadcrumbs.length).toBeGreaterThan(0);
        });

        it('should render back to server link', () => {
            render(<ServerSettingsIndex server={mockServer} />);

            const backLink = screen.getByText('Back to Server');
            expect(backLink).toBeInTheDocument();
            expect(backLink.closest('a')).toHaveAttribute('href', '/servers/server-uuid-1');
        });
    });

    describe('settings sections', () => {
        it('should render all three settings sections', () => {
            render(<ServerSettingsIndex server={mockServer} />);

            expect(screen.getByText('General Settings')).toBeInTheDocument();
            expect(screen.getByText('Docker Configuration')).toBeInTheDocument();
            expect(screen.getByText('Network Settings')).toBeInTheDocument();
        });

        it('should display settings section descriptions', () => {
            render(<ServerSettingsIndex server={mockServer} />);

            expect(screen.getByText('Configure server name, description, and basic information')).toBeInTheDocument();
            expect(screen.getByText('Manage Docker settings and build configurations')).toBeInTheDocument();
            expect(screen.getByText('Configure network, firewall, and connectivity settings')).toBeInTheDocument();
        });

        it('should have correct links for each settings section', () => {
            render(<ServerSettingsIndex server={mockServer} />);

            const generalLink = screen.getByText('General Settings').closest('a');
            const dockerLink = screen.getByText('Docker Configuration').closest('a');
            const networkLink = screen.getByText('Network Settings').closest('a');

            expect(generalLink).toHaveAttribute('href', '/servers/server-uuid-1/settings/general');
            expect(dockerLink).toHaveAttribute('href', '/servers/server-uuid-1/settings/docker');
            expect(networkLink).toHaveAttribute('href', '/servers/server-uuid-1/settings/network');
        });
    });

    describe('danger zone', () => {
        it('should render danger zone section', () => {
            render(<ServerSettingsIndex server={mockServer} />);

            expect(screen.getByText('Danger Zone')).toBeInTheDocument();
            expect(screen.getByText('Delete this server')).toBeInTheDocument();
            expect(screen.getByText('Once you delete a server, there is no going back. Please be certain.')).toBeInTheDocument();
        });

        it('should render delete server button', () => {
            render(<ServerSettingsIndex server={mockServer} />);

            const deleteButton = screen.getByRole('button', { name: /delete server/i });
            expect(deleteButton).toBeInTheDocument();
        });

        it('should show confirmation modal when delete button is clicked', async () => {
            const { user } = render(<ServerSettingsIndex server={mockServer} />);

            const deleteButton = screen.getByRole('button', { name: /delete server/i });
            await user.click(deleteButton);

            await waitFor(() => {
                expect(screen.getByRole('button', { name: /cancel/i })).toBeInTheDocument();
            });
        });

        it('should call delete endpoint when deletion is confirmed', async () => {
            const { user } = render(<ServerSettingsIndex server={mockServer} />);

            const deleteButton = screen.getByRole('button', { name: /delete server/i });
            await user.click(deleteButton);

            await waitFor(() => {
                expect(screen.getByRole('button', { name: /cancel/i })).toBeInTheDocument();
            });

            const confirmButtons = screen.getAllByRole('button', { name: /delete/i });
            const confirmButton = confirmButtons[confirmButtons.length - 1];
            await user.click(confirmButton);

            await waitFor(() => {
                expect(router.delete).toHaveBeenCalledWith('/servers/server-uuid-1');
            });
        });

        it('should not delete when user cancels', async () => {
            const { user } = render(<ServerSettingsIndex server={mockServer} />);

            const deleteButton = screen.getByRole('button', { name: /delete server/i });
            await user.click(deleteButton);

            await waitFor(() => {
                expect(screen.getByRole('button', { name: /cancel/i })).toBeInTheDocument();
            });

            const cancelButton = screen.getByRole('button', { name: /cancel/i });
            await user.click(cancelButton);

            expect(router.delete).not.toHaveBeenCalled();
        });
    });

    describe('navigation', () => {
        it('should navigate to settings sections when cards are clicked', () => {
            render(<ServerSettingsIndex server={mockServer} />);

            const generalLink = screen.getByText('General Settings').closest('a');
            expect(generalLink).toHaveAttribute('href', '/servers/server-uuid-1/settings/general');
        });

        it('should have correct server UUID in all links', () => {
            render(<ServerSettingsIndex server={mockServer} />);

            const links = screen.getAllByRole('link');
            const settingsLinks = links.filter(link =>
                link.getAttribute('href')?.includes('/servers/server-uuid-1')
            );

            expect(settingsLinks.length).toBeGreaterThan(0);
        });
    });

    describe('edge cases', () => {
        it('should handle server with different UUID', () => {
            const differentServer = {
                ...mockServer,
                uuid: 'different-uuid',
                name: 'staging-server',
            };

            render(<ServerSettingsIndex server={differentServer} />);

            const serverNames = screen.getAllByText('staging-server');
            expect(serverNames.length).toBeGreaterThan(0);
            const generalLink = screen.getByText('General Settings').closest('a');
            expect(generalLink).toHaveAttribute('href', '/servers/different-uuid/settings/general');
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

            render(<ServerSettingsIndex server={minimalServer} />);

            expect(screen.getByText('Server Settings')).toBeInTheDocument();
            const serverNames = screen.getAllByText('minimal-server');
            expect(serverNames.length).toBeGreaterThan(0);
        });
    });
});
