import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../../utils/test-utils';
import ServerCleanupIndex from '@/pages/Servers/Cleanup/Index';
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

const mockCleanupStats = {
    unused_images: 5,
    unused_containers: 3,
    unused_volumes: 2,
    unused_networks: 1,
    total_size: '2.5 GB',
};

describe('Server Cleanup Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page title and description', () => {
            render(<ServerCleanupIndex server={mockServer} cleanupStats={mockCleanupStats} />);

            expect(screen.getByText('Server Cleanup')).toBeInTheDocument();
            expect(screen.getByText(/Free up disk space on production-server/)).toBeInTheDocument();
        });

        it('should render breadcrumbs', () => {
            render(<ServerCleanupIndex server={mockServer} />);

            const breadcrumbs = screen.getAllByText('Servers');
            expect(breadcrumbs.length).toBeGreaterThan(0);
        });

        it('should render back to server link', () => {
            render(<ServerCleanupIndex server={mockServer} />);

            const backLink = screen.getByText('Back to Server');
            expect(backLink).toBeInTheDocument();
            expect(backLink.closest('a')).toHaveAttribute('href', '/servers/server-uuid-1');
        });

        it('should render warning banner', () => {
            render(<ServerCleanupIndex server={mockServer} />);

            expect(screen.getByText('Caution Required')).toBeInTheDocument();
            expect(screen.getByText(/Cleanup operations are permanent and cannot be undone/)).toBeInTheDocument();
        });
    });

    describe('cleanup stats', () => {
        it('should display cleanup summary with total size', () => {
            render(<ServerCleanupIndex server={mockServer} cleanupStats={mockCleanupStats} />);

            expect(screen.getByText('Cleanup Summary')).toBeInTheDocument();
            expect(screen.getByText('2.5 GB')).toBeInTheDocument();
        });

        it('should display Clean Up All button', () => {
            render(<ServerCleanupIndex server={mockServer} cleanupStats={mockCleanupStats} />);

            const cleanupAllButton = screen.getByRole('button', { name: /clean up all/i });
            expect(cleanupAllButton).toBeInTheDocument();
        });

        it('should use default stats when none provided', () => {
            render(<ServerCleanupIndex server={mockServer} />);

            expect(screen.getByText('N/A')).toBeInTheDocument();
        });
    });

    describe('cleanup items', () => {
        it('should render all cleanup item categories', () => {
            render(<ServerCleanupIndex server={mockServer} cleanupStats={mockCleanupStats} />);

            expect(screen.getByText('Unused Docker Images')).toBeInTheDocument();
            expect(screen.getByText('Stopped Containers')).toBeInTheDocument();
            expect(screen.getByText('Unused Volumes')).toBeInTheDocument();
            expect(screen.getByText('Unused Networks')).toBeInTheDocument();
        });

        it('should display cleanup item descriptions', () => {
            render(<ServerCleanupIndex server={mockServer} cleanupStats={mockCleanupStats} />);

            expect(screen.getByText('Remove Docker images that are not being used by any containers')).toBeInTheDocument();
            expect(screen.getByText('Remove containers that have been stopped')).toBeInTheDocument();
            expect(screen.getByText('Remove Docker volumes not attached to any containers')).toBeInTheDocument();
            expect(screen.getByText('Remove Docker networks with no connected containers')).toBeInTheDocument();
        });

        it('should display cleanup item counts', () => {
            render(<ServerCleanupIndex server={mockServer} cleanupStats={mockCleanupStats} />);

            expect(screen.getByText('5')).toBeInTheDocument();
            expect(screen.getByText('3')).toBeInTheDocument();
            expect(screen.getByText('2')).toBeInTheDocument();
            expect(screen.getByText('1')).toBeInTheDocument();
        });

        it('should render clean up button for each item', () => {
            render(<ServerCleanupIndex server={mockServer} cleanupStats={mockCleanupStats} />);

            const cleanupButtons = screen.getAllByRole('button', { name: /clean up/i });
            // 4 individual cleanup buttons + 1 cleanup all button
            expect(cleanupButtons.length).toBe(5);
        });
    });

    describe('cleanup actions', () => {
        it('should show confirmation modal when Clean Up All is clicked', async () => {
            const { user } = render(<ServerCleanupIndex server={mockServer} cleanupStats={mockCleanupStats} />);

            const cleanupAllButton = screen.getByRole('button', { name: /clean up all/i });
            await user.click(cleanupAllButton);

            await waitFor(() => {
                expect(screen.getByRole('button', { name: /cancel/i })).toBeInTheDocument();
            });
        });

        it('should show confirmation modal for cleanup all', async () => {
            const { user } = render(<ServerCleanupIndex server={mockServer} cleanupStats={mockCleanupStats} />);

            const cleanupAllButton = screen.getByRole('button', { name: /clean up all/i });
            await user.click(cleanupAllButton);

            // Confirmation modal should appear
            await waitFor(() => {
                expect(screen.getByRole('button', { name: /cancel/i })).toBeInTheDocument();
            });

            expect(screen.getByText('Clean Up All Resources')).toBeInTheDocument();
        });

        it('should show confirmation when individual cleanup is clicked', async () => {
            const { user } = render(<ServerCleanupIndex server={mockServer} cleanupStats={mockCleanupStats} />);

            const cleanupButtons = screen.getAllByRole('button', { name: /^clean up$/i });
            const firstCleanupButton = cleanupButtons[0];

            await user.click(firstCleanupButton);

            await waitFor(() => {
                expect(screen.getByRole('button', { name: /cancel/i })).toBeInTheDocument();
            });
        });

        it('should disable cleanup button when count is zero', () => {
            const emptyStats = {
                unused_images: 0,
                unused_containers: 0,
                unused_volumes: 0,
                unused_networks: 0,
                total_size: '0 GB',
            };

            render(<ServerCleanupIndex server={mockServer} cleanupStats={emptyStats} />);

            const cleanupButtons = screen.getAllByRole('button', { name: /^clean up$/i });
            cleanupButtons.forEach(button => {
                expect(button).toBeDisabled();
            });
        });
    });

    describe('automatic cleanup section', () => {
        it('should render automatic cleanup coming soon section', () => {
            render(<ServerCleanupIndex server={mockServer} />);

            expect(screen.getByText('Automatic Cleanup (Coming Soon)')).toBeInTheDocument();
            expect(screen.getByText(/Configure automatic cleanup schedules/)).toBeInTheDocument();
        });
    });

    describe('edge cases', () => {
        it('should handle server with different UUID', () => {
            const differentServer = {
                ...mockServer,
                uuid: 'different-uuid',
                name: 'staging-server',
            };

            render(<ServerCleanupIndex server={differentServer} cleanupStats={mockCleanupStats} />);

            expect(screen.getByText(/Free up disk space on staging-server/)).toBeInTheDocument();
        });

        it('should handle missing cleanup stats', () => {
            render(<ServerCleanupIndex server={mockServer} />);

            expect(screen.getByText('N/A')).toBeInTheDocument();
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

            render(<ServerCleanupIndex server={minimalServer} />);

            expect(screen.getByText('Server Cleanup')).toBeInTheDocument();
        });

        it('should handle partial cleanup stats', () => {
            const partialStats = {
                unused_images: 10,
                unused_containers: 0,
                unused_volumes: 0,
                unused_networks: 0,
                total_size: '500 MB',
            };

            render(<ServerCleanupIndex server={mockServer} cleanupStats={partialStats} />);

            expect(screen.getByText('10')).toBeInTheDocument();
            expect(screen.getByText('500 MB')).toBeInTheDocument();
        });
    });
});
