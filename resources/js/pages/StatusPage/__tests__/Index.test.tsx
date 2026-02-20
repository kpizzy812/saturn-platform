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

beforeEach(() => {
    vi.clearAllMocks();
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
        const groups = {
            Infrastructure: [
                { name: 'API Server', status: 'operational' as const },
                { name: 'Database', status: 'operational' as const },
            ],
            Applications: [
                { name: 'Frontend', status: 'degraded' as const },
            ],
        };

        render(<StatusPage groups={groups} />);
        expect(screen.getByText('Infrastructure')).toBeInTheDocument();
        expect(screen.getByText('Applications')).toBeInTheDocument();
        expect(screen.getByText('API Server')).toBeInTheDocument();
        expect(screen.getByText('Database')).toBeInTheDocument();
        expect(screen.getByText('Frontend')).toBeInTheDocument();
    });

    it('shows no services configured message when groups empty', () => {
        render(<StatusPage groups={{}} />);
        expect(screen.getByText('No services configured')).toBeInTheDocument();
    });

    it('renders operational status labels', () => {
        const groups = {
            Services: [
                { name: 'Test Service', status: 'operational' as const },
            ],
        };
        render(<StatusPage groups={groups} />);
        expect(screen.getByText('Operational')).toBeInTheDocument();
    });

    it('renders outage status labels', () => {
        const groups = {
            Services: [
                { name: 'Down Service', status: 'major_outage' as const },
            ],
        };
        render(<StatusPage groups={groups} />);
        expect(screen.getByText('Outage')).toBeInTheDocument();
    });
});
