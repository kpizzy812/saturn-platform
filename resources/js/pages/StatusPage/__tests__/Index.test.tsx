import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import StatusPage from '../Index';

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: any) => <title>{title}</title>,
    Link: ({ children, href, ...props }: any) => (
        <a href={href} {...props}>{children}</a>
    ),
    usePage: () => ({
        props: {},
    }),
}));

vi.mock('@/layouts/PublicLayout', () => ({
    PublicLayout: ({ children }: any) => <div data-testid="public-layout">{children}</div>,
}));

vi.mock('@/components/StatusPage/UptimeBar', () => ({
    UptimeBar: ({ days }: any) => (
        <div data-testid="uptime-bar" data-days={days.length} />
    ),
}));

vi.mock('@/components/StatusPage/IncidentBanner', () => ({
    IncidentBanner: ({ incidents }: any) => (
        incidents && incidents.length > 0
            ? <div data-testid="incident-banner">{incidents.length} incidents</div>
            : null
    ),
}));

beforeEach(() => {
    vi.clearAllMocks();
});

const makeService = (overrides = {}) => ({
    name: 'Test Service',
    status: 'operational' as const,
    group: 'Services',
    resourceType: 'server',
    resourceId: 1,
    uptimePercent: 99.9,
    uptimeDays: Array.from({ length: 90 }, (_, i) => ({
        date: `2026-01-${String((i % 28) + 1).padStart(2, '0')}`,
        status: 'operational' as const,
        uptimePercent: 100,
    })),
    ...overrides,
});

const makeIncident = (overrides = {}) => ({
    id: 1,
    title: 'Test Incident',
    severity: 'minor' as const,
    status: 'investigating',
    startedAt: '2026-02-22T10:00:00Z',
    resolvedAt: null,
    updates: [],
    ...overrides,
});

describe('StatusPage', () => {
    it('renders with default props', () => {
        render(<StatusPage />);
        expect(screen.getByText('Saturn')).toBeInTheDocument();
        expect(screen.getByText('All Systems Operational')).toBeInTheDocument();
    });

    it('renders custom title and description', () => {
        render(<StatusPage title="My Platform" description="Service status" />);
        expect(screen.getByText('My Platform')).toBeInTheDocument();
        expect(screen.getByText('Service status')).toBeInTheDocument();
    });

    it('renders major outage banner', () => {
        render(<StatusPage overallStatus="major_outage" />);
        expect(screen.getByText('Major System Outage')).toBeInTheDocument();
    });

    it('renders partial outage banner', () => {
        render(<StatusPage overallStatus="partial_outage" />);
        expect(screen.getByText('Partial System Outage')).toBeInTheDocument();
    });

    it('renders maintenance banner', () => {
        render(<StatusPage overallStatus="maintenance" />);
        expect(screen.getByText('Under Maintenance')).toBeInTheDocument();
    });

    it('renders service groups with statuses', () => {
        const services = [
            makeService({ name: 'API Server', group: 'Infrastructure', resourceId: 1 }),
            makeService({ name: 'Database', group: 'Infrastructure', resourceId: 2 }),
            makeService({ name: 'Frontend', group: 'Applications', status: 'degraded', resourceId: 3, resourceType: 'application' }),
        ];

        render(<StatusPage services={services} />);
        expect(screen.getByText('Infrastructure')).toBeInTheDocument();
        expect(screen.getByText('Applications')).toBeInTheDocument();
        expect(screen.getByText('API Server')).toBeInTheDocument();
        expect(screen.getByText('Database')).toBeInTheDocument();
        expect(screen.getByText('Frontend')).toBeInTheDocument();
    });

    it('shows no services configured message when services empty', () => {
        render(<StatusPage services={[]} />);
        expect(screen.getByText('No services configured')).toBeInTheDocument();
    });

    it('renders operational status labels', () => {
        render(<StatusPage services={[makeService()]} />);
        expect(screen.getByText('Operational')).toBeInTheDocument();
    });

    it('renders outage status labels', () => {
        render(<StatusPage services={[makeService({ status: 'major_outage' })]} />);
        expect(screen.getByText('Outage')).toBeInTheDocument();
    });

    it('renders uptime bars for services with uptimeDays', () => {
        render(<StatusPage services={[makeService()]} />);
        expect(screen.getByTestId('uptime-bar')).toBeInTheDocument();
    });

    it('displays uptime percentage', () => {
        render(<StatusPage services={[makeService({ uptimePercent: 99.9 })]} />);
        expect(screen.getByText('99.9% uptime')).toBeInTheDocument();
    });

    it('renders active incidents', () => {
        render(<StatusPage incidents={[makeIncident()]} />);
        expect(screen.getByTestId('incident-banner')).toBeInTheDocument();
    });

    it('does not render incident banner when no active incidents', () => {
        const resolved = makeIncident({ resolvedAt: '2026-02-22T12:00:00Z' });
        render(<StatusPage incidents={[resolved]} />);
        expect(screen.queryByTestId('incident-banner')).not.toBeInTheDocument();
    });

    it('renders past resolved incidents section', () => {
        const resolved = makeIncident({
            title: 'Resolved Issue',
            resolvedAt: '2026-02-22T12:00:00Z',
            status: 'resolved',
        });
        render(<StatusPage incidents={[resolved]} />);
        expect(screen.getByText('Past Incidents')).toBeInTheDocument();
        expect(screen.getByText('Resolved Issue')).toBeInTheDocument();
    });
});
