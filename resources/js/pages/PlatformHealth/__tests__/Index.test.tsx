import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';
import PlatformHealth from '../Index';

vi.mock('@inertiajs/react', () => ({
    Link: ({ children, href, ...props }: any) => (
        <a href={href} {...props}>{children}</a>
    ),
    router: { visit: vi.fn(), post: vi.fn(), reload: vi.fn() },
    usePage: () => ({
        props: {
            auth: { name: 'Test User', email: 'test@test.com' },
            notifications: { unreadCount: 0, recent: [] },
        },
    }),
}));

vi.mock('@/components/layout', () => ({
    AppLayout: ({ children }: any) => <div data-testid="app-layout">{children}</div>,
}));

vi.mock('@/components/ui/Card', () => ({
    Card: ({ children, className }: any) => <div className={className} data-testid="card">{children}</div>,
    CardHeader: ({ children }: any) => <div>{children}</div>,
    CardTitle: ({ children }: any) => <h3>{children}</h3>,
    CardContent: ({ children, className }: any) => <div className={className}>{children}</div>,
}));

vi.mock('@/components/ui/Badge', () => ({
    Badge: ({ children, variant }: any) => <span data-variant={variant}>{children}</span>,
}));

const sampleSummary = {
    totalServers: 3,
    healthyServers: 2,
    degradedServers: 1,
    downServers: 0,
    totalApps: 5,
    activeDeployments: 1,
};

const sampleServers = [
    {
        id: 1,
        name: 'prod-server-1',
        ip: '10.0.0.1',
        status: 'healthy',
        cpu: 45.2,
        memory: 62.8,
        disk: 33.1,
        uptime: 864000,
        checkedAt: '2 minutes ago',
    },
    {
        id: 2,
        name: 'staging-server',
        ip: '10.0.0.2',
        status: 'degraded',
        cpu: 92.1,
        memory: 88.5,
        disk: 71.0,
        uptime: 172800,
        checkedAt: '5 minutes ago',
    },
];

const sampleResources = [
    { id: 1, uuid: 'app-1', name: 'frontend-app', type: 'Application', status: 'running' },
    { id: 2, uuid: 'svc-1', name: 'api-gateway', type: 'Service', status: 'running' },
    { id: 3, uuid: 'db-1', name: 'postgres-main', type: 'Database', status: 'running' },
];

const sampleAlerts = [
    { id: 1, alertName: 'High CPU Usage', value: 95.3, triggeredAt: '5 minutes ago' },
];

const sampleDeployments = [
    {
        id: 1,
        uuid: 'dep-1',
        appName: 'frontend-app',
        serverName: 'prod-server-1',
        status: 'finished',
        commit: 'abc1234',
        createdAt: '10 minutes ago',
    },
    {
        id: 2,
        uuid: 'dep-2',
        appName: 'api-gateway',
        serverName: 'prod-server-1',
        status: 'in_progress',
        commit: 'def5678',
        createdAt: '2 minutes ago',
    },
];

beforeEach(() => {
    vi.clearAllMocks();
});

describe('PlatformHealth', () => {
    it('renders with default empty state', () => {
        render(<PlatformHealth />);
        expect(screen.getByText('Platform Health')).toBeInTheDocument();
        expect(screen.getByText('No servers connected')).toBeInTheDocument();
        expect(screen.getByText('No resources deployed')).toBeInTheDocument();
        expect(screen.getByText('No active alerts')).toBeInTheDocument();
        expect(screen.getByText('No recent deployments')).toBeInTheDocument();
    });

    it('renders summary cards correctly', () => {
        render(<PlatformHealth summary={sampleSummary} />);
        expect(screen.getByText('2/3')).toBeInTheDocument();
        expect(screen.getByText('5')).toBeInTheDocument();
        expect(screen.getByText('1')).toBeInTheDocument(); // active deployments
    });

    it('renders servers grid with usage bars', () => {
        render(<PlatformHealth summary={sampleSummary} servers={sampleServers} />);
        expect(screen.getByText('prod-server-1')).toBeInTheDocument();
        expect(screen.getByText('staging-server')).toBeInTheDocument();
        expect(screen.getByText('10.0.0.1')).toBeInTheDocument();
    });

    it('renders resources table', () => {
        render(<PlatformHealth resources={sampleResources} />);
        expect(screen.getByText('frontend-app')).toBeInTheDocument();
        expect(screen.getByText('api-gateway')).toBeInTheDocument();
        expect(screen.getByText('postgres-main')).toBeInTheDocument();
    });

    it('renders active alerts', () => {
        render(<PlatformHealth activeAlerts={sampleAlerts} />);
        expect(screen.getByText('High CPU Usage')).toBeInTheDocument();
    });

    it('renders recent deployments', () => {
        render(<PlatformHealth recentDeployments={sampleDeployments} />);
        expect(screen.getByText('frontend-app')).toBeInTheDocument();
        expect(screen.getByText('abc1234')).toBeInTheDocument();
    });

    it('shows no active alerts message when empty', () => {
        render(<PlatformHealth activeAlerts={[]} />);
        expect(screen.getByText('No active alerts')).toBeInTheDocument();
    });

    it('handles refresh button click', async () => {
        const inertia = await import('@inertiajs/react');
        render(<PlatformHealth />);
        fireEvent.click(screen.getByText('Refresh'));
        expect(inertia.router.reload).toHaveBeenCalled();
    });
});
