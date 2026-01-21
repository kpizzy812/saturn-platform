import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import ProxyIndex from '@/pages/Servers/Proxy/Index';
import type { Server } from '@/types';
import { router } from '@inertiajs/react';

const mockServer: Server = {
    id: 1,
    uuid: 'server-1',
    name: 'production-server',
    description: 'Production server',
    ip: '192.168.1.100',
    port: 22,
    user: 'root',
    status: 'reachable',
    is_build_server: false,
    created_at: '2024-01-01',
    updated_at: '2024-01-01',
};

const mockProxyRunning = {
    type: 'traefik',
    status: 'running' as const,
    version: '2.10.4',
    uptime: '5 days',
    domains_count: 12,
    ssl_count: 8,
};

const mockProxyStopped = {
    type: 'nginx',
    status: 'stopped' as const,
    version: '1.24.0',
    uptime: undefined,
    domains_count: 0,
    ssl_count: 0,
};

describe('Proxy Management Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering with running proxy', () => {
        it('should render page title with proxy type', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyRunning} />);

            expect(screen.getByText('Traefik Proxy')).toBeInTheDocument();
        });

        it('should display running status badge', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyRunning} />);

            // Status appears in multiple places, use getAllByText
            expect(screen.getAllByText('Running').length).toBeGreaterThan(0);
        });

        it('should display proxy version', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyRunning} />);

            expect(screen.getByText(/Version: 2\.10\.4/)).toBeInTheDocument();
        });

        it('should display restart button when running', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyRunning} />);

            expect(screen.getByText('Restart')).toBeInTheDocument();
        });

        it('should display stop button when running', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyRunning} />);

            expect(screen.getByText('Stop')).toBeInTheDocument();
        });

        it('should not display start button when running', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyRunning} />);

            expect(screen.queryByText('Start')).not.toBeInTheDocument();
        });
    });

    describe('rendering with stopped proxy', () => {
        it('should display stopped status badge', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyStopped} />);

            // Status appears in multiple places, use getAllByText
            expect(screen.getAllByText('Stopped').length).toBeGreaterThan(0);
        });

        it('should display start button when stopped', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyStopped} />);

            expect(screen.getByText('Start')).toBeInTheDocument();
        });

        it('should not display restart button when stopped', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyStopped} />);

            expect(screen.queryByText('Restart')).not.toBeInTheDocument();
        });

        it('should not display stop button when stopped', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyStopped} />);

            expect(screen.queryByText('Stop')).not.toBeInTheDocument();
        });

        it('should capitalize proxy type name', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyStopped} />);

            expect(screen.getByText('Nginx Proxy')).toBeInTheDocument();
        });
    });

    describe('statistics display', () => {
        it('should display status stat', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyRunning} />);

            // Labels appear in multiple places, use getAllByText
            expect(screen.getAllByText('Status').length).toBeGreaterThan(0);
            expect(screen.getByText('running')).toBeInTheDocument();
        });

        it('should display domains count', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyRunning} />);

            expect(screen.getAllByText('Domains').length).toBeGreaterThan(0);
            const domainsCount = screen.getAllByText('12')[0];
            expect(domainsCount).toBeInTheDocument();
        });

        it('should display SSL certificates count', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyRunning} />);

            expect(screen.getAllByText('SSL Certificates').length).toBeGreaterThan(0);
            const sslCount = screen.getAllByText('8')[0];
            expect(sslCount).toBeInTheDocument();
        });

        it('should display uptime when running', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyRunning} />);

            expect(screen.getAllByText('Uptime').length).toBeGreaterThan(0);
            const uptime = screen.getAllByText('5 days')[0];
            expect(uptime).toBeInTheDocument();
        });

        it('should show 0 for domains when stopped', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyStopped} />);

            expect(screen.getAllByText('Domains').length).toBeGreaterThan(0);
            const domainsCount = screen.getAllByText('0')[0];
            expect(domainsCount).toBeInTheDocument();
        });

        it('should show 0 for SSL certificates when stopped', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyStopped} />);

            expect(screen.getAllByText('SSL Certificates').length).toBeGreaterThan(0);
        });
    });

    describe('breadcrumbs', () => {
        it('should display breadcrumbs with server name', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyRunning} />);

            expect(screen.getByText('Servers')).toBeInTheDocument();
            expect(screen.getByText(mockServer.name)).toBeInTheDocument();
            expect(screen.getByText('Proxy')).toBeInTheDocument();
        });
    });

    describe('proxy actions', () => {
        it('should call restart endpoint when restart clicked', async () => {
            const { user } = render(<ProxyIndex server={mockServer} proxy={mockProxyRunning} />);

            const restartButton = screen.getByText('Restart');
            await user.click(restartButton);

            expect(router.post).toHaveBeenCalledWith(
                `/servers/${mockServer.uuid}/proxy/restart`
            );
        });

        it('should call stop endpoint when stop clicked', async () => {
            const { user } = render(<ProxyIndex server={mockServer} proxy={mockProxyRunning} />);

            const stopButton = screen.getByText('Stop');
            await user.click(stopButton);

            expect(router.post).toHaveBeenCalledWith(
                `/servers/${mockServer.uuid}/proxy/stop`
            );
        });

        it('should call start endpoint when start clicked', async () => {
            const { user } = render(<ProxyIndex server={mockServer} proxy={mockProxyStopped} />);

            const startButton = screen.getByText('Start');
            await user.click(startButton);

            expect(router.post).toHaveBeenCalledWith(
                `/servers/${mockServer.uuid}/proxy/start`
            );
        });
    });

    describe('quick actions section', () => {
        it('should display configuration link', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyRunning} />);

            expect(screen.getByText('Configuration')).toBeInTheDocument();
        });

        it('should display domains link', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyRunning} />);

            // Domains appears in multiple places, use getAllByText
            expect(screen.getAllByText(/Domains/).length).toBeGreaterThan(0);
        });

        it('should display logs link', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyRunning} />);

            expect(screen.getByText('Logs')).toBeInTheDocument();
        });

        it('should display settings link', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyRunning} />);

            expect(screen.getByText(/Settings/)).toBeInTheDocument();
        });
    });

    describe('visual indicators', () => {
        it('should show primary color when running', () => {
            const { container } = render(<ProxyIndex server={mockServer} proxy={mockProxyRunning} />);

            // Status appears in multiple places, use getAllByText and get first
            const badges = screen.getAllByText('Running');
            const badge = badges[0].closest('span');
            expect(badge).toHaveClass('bg-success/15');
        });

        it('should show danger color when stopped', () => {
            const { container } = render(<ProxyIndex server={mockServer} proxy={mockProxyStopped} />);

            // Status appears in multiple places, use getAllByText and get first
            const badges = screen.getAllByText('Stopped');
            const badge = badges[0].closest('span');
            expect(badge).toHaveClass('bg-danger/15');
        });
    });

    describe('different proxy types', () => {
        it('should display Traefik proxy correctly', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyRunning} />);

            expect(screen.getByText('Traefik Proxy')).toBeInTheDocument();
        });

        it('should display Nginx proxy correctly', () => {
            render(<ProxyIndex server={mockServer} proxy={mockProxyStopped} />);

            expect(screen.getByText('Nginx Proxy')).toBeInTheDocument();
        });

        it('should display Caddy proxy correctly', () => {
            const caddyProxy = { ...mockProxyRunning, type: 'caddy' };
            render(<ProxyIndex server={mockServer} proxy={caddyProxy} />);

            expect(screen.getByText('Caddy Proxy')).toBeInTheDocument();
        });
    });

    describe('edge cases', () => {
        it('should handle proxy without version', () => {
            const proxyWithoutVersion = { ...mockProxyRunning, version: undefined };
            render(<ProxyIndex server={mockServer} proxy={proxyWithoutVersion} />);

            expect(screen.queryByText(/Version:/)).not.toBeInTheDocument();
        });

        it('should handle proxy without uptime', () => {
            const proxyWithoutUptime = { ...mockProxyRunning, uptime: undefined };
            render(<ProxyIndex server={mockServer} proxy={proxyWithoutUptime} />);

            // Page should still render
            expect(screen.getByText('Traefik Proxy')).toBeInTheDocument();
        });

        it('should handle undefined counts gracefully', () => {
            const proxyWithoutCounts = {
                ...mockProxyRunning,
                domains_count: undefined,
                ssl_count: undefined,
            };
            render(<ProxyIndex server={mockServer} proxy={proxyWithoutCounts} />);

            // Domains appears in multiple places, use getAllByText
            expect(screen.getAllByText('Domains').length).toBeGreaterThan(0);
            expect(screen.getAllByText('0').length).toBeGreaterThan(0);
        });
    });
});
