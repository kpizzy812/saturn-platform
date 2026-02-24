import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import { IncidentBanner } from '../IncidentBanner';

vi.mock('lucide-react', () => ({
    AlertTriangle: ({ className }: any) => <span data-testid="icon-alert" className={className} />,
    XCircle: ({ className }: any) => <span data-testid="icon-xcircle" className={className} />,
    Wrench: ({ className }: any) => <span data-testid="icon-wrench" className={className} />,
    Info: ({ className }: any) => <span data-testid="icon-info" className={className} />,
}));

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

describe('IncidentBanner', () => {
    it('returns null when incidents array is empty', () => {
        const { container } = render(<IncidentBanner incidents={[]} />);
        expect(container.innerHTML).toBe('');
    });

    it('renders an incident', () => {
        render(<IncidentBanner incidents={[makeIncident()]} />);
        expect(screen.getByTestId('incident-banner')).toBeInTheDocument();
        expect(screen.getByText('Test Incident')).toBeInTheDocument();
    });

    it('shows investigating status label', () => {
        render(<IncidentBanner incidents={[makeIncident()]} />);
        expect(screen.getByText('Investigating')).toBeInTheDocument();
    });

    it('renders critical incident with red styling', () => {
        render(<IncidentBanner incidents={[makeIncident({ severity: 'critical' })]} />);
        const banner = screen.getByTestId('incident-banner');
        const incidentDiv = banner.firstChild as HTMLElement;
        expect(incidentDiv.className).toContain('border-red-500/20');
    });

    it('renders major incident with orange styling', () => {
        render(<IncidentBanner incidents={[makeIncident({ severity: 'major' })]} />);
        const banner = screen.getByTestId('incident-banner');
        const incidentDiv = banner.firstChild as HTMLElement;
        expect(incidentDiv.className).toContain('border-orange-500/20');
    });

    it('renders maintenance incident with blue styling', () => {
        render(<IncidentBanner incidents={[makeIncident({ severity: 'maintenance' })]} />);
        const banner = screen.getByTestId('incident-banner');
        const incidentDiv = banner.firstChild as HTMLElement;
        expect(incidentDiv.className).toContain('border-blue-500/20');
    });

    it('renders incident updates timeline', () => {
        const incident = makeIncident({
            updates: [
                { status: 'investigating', message: 'Looking into it', postedAt: '2026-02-22T10:00:00Z' },
                { status: 'identified', message: 'Found the issue', postedAt: '2026-02-22T10:30:00Z' },
            ],
        });
        render(<IncidentBanner incidents={[incident]} />);
        expect(screen.getByText('Looking into it')).toBeInTheDocument();
        expect(screen.getByText('Found the issue')).toBeInTheDocument();
    });

    it('renders multiple incidents', () => {
        const incidents = [
            makeIncident({ id: 1, title: 'First Incident' }),
            makeIncident({ id: 2, title: 'Second Incident', severity: 'critical' }),
        ];
        render(<IncidentBanner incidents={incidents} />);
        expect(screen.getByText('First Incident')).toBeInTheDocument();
        expect(screen.getByText('Second Incident')).toBeInTheDocument();
    });
});
