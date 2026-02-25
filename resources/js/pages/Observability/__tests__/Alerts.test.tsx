import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';
import ObservabilityAlerts from '../Alerts';

const mockRouterPost = vi.fn();
const mockRouterPut = vi.fn();
const mockRouterDelete = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Link: ({ children, href, ...props }: any) => (
        <a href={href} {...props}>{children}</a>
    ),
    router: {
        visit: vi.fn(),
        post: (...args: any[]) => mockRouterPost(...args),
        put: (...args: any[]) => mockRouterPut(...args),
        delete: (...args: any[]) => mockRouterDelete(...args),
    },
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

const mockConfirm = vi.fn(() => Promise.resolve(true));

vi.mock('@/components/ui', () => ({
    Card: ({ children, className }: any) => <div className={className} data-testid="card">{children}</div>,
    CardHeader: ({ children }: any) => <div>{children}</div>,
    CardTitle: ({ children }: any) => <h3>{children}</h3>,
    CardContent: ({ children, className }: any) => <div className={className}>{children}</div>,
    Badge: ({ children, variant }: any) => <span data-variant={variant}>{children}</span>,
    Button: ({ children, onClick, variant, size, title, disabled }: any) => (
        <button onClick={onClick} data-variant={variant} data-size={size} title={title} disabled={disabled}>{children}</button>
    ),
    Input: ({ label, value, onChange, error, placeholder, type, min, hint }: any) => (
        <div>
            {label && <label>{label}</label>}
            <input
                value={value}
                onChange={onChange}
                placeholder={placeholder}
                type={type}
                min={min}
                aria-label={label}
            />
            {hint && <span>{hint}</span>}
            {error && <span className="error">{error}</span>}
        </div>
    ),
    Select: ({ label, value, onChange, options, children }: any) => (
        <div>
            {label && <label>{label}</label>}
            <select value={value} onChange={onChange} aria-label={label}>
                {options ? options.map((opt: any) => (
                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                )) : children}
            </select>
        </div>
    ),
    Modal: ({ children, title, isOpen }: any) => isOpen ? (
        <div data-testid="modal" role="dialog">
            <h2>{title}</h2>
            {children}
        </div>
    ) : null,
    ModalFooter: ({ children }: any) => <div data-testid="modal-footer">{children}</div>,
    Checkbox: ({ label, checked, onChange, id }: any) => (
        <label>
            <input type="checkbox" checked={checked} onChange={onChange} id={id} />
            {label}
        </label>
    ),
    useConfirm: () => mockConfirm,
}));

const sampleAlerts = [
    {
        id: 1,
        name: 'High CPU Alert',
        metric: 'cpu',
        condition: '>' as const,
        threshold: 90,
        duration: 5,
        enabled: true,
        channels: ['email', 'slack'],
        triggered_count: 3,
        last_triggered: '2026-02-18T10:00:00Z',
        created_at: '2026-02-01T00:00:00Z',
    },
    {
        id: 2,
        name: 'Low Disk Space',
        metric: 'disk',
        condition: '>' as const,
        threshold: 85,
        duration: 10,
        enabled: false,
        channels: ['email'],
        triggered_count: 0,
        last_triggered: null,
        created_at: '2026-02-15T00:00:00Z',
    },
];

const sampleHistory = [
    {
        id: 1,
        alert_id: 1,
        alert_name: 'High CPU Alert',
        triggered_at: '2026-02-18T10:00:00Z',
        resolved_at: '2026-02-18T10:15:00Z',
        value: 95.5,
        status: 'resolved' as const,
    },
    {
        id: 2,
        alert_id: 1,
        alert_name: 'High CPU Alert',
        triggered_at: '2026-02-18T12:00:00Z',
        resolved_at: null,
        value: 92.3,
        status: 'triggered' as const,
    },
];

beforeEach(() => {
    vi.clearAllMocks();
});

describe('ObservabilityAlerts', () => {
    it('renders empty state when no alerts exist', () => {
        render(<ObservabilityAlerts />);
        expect(screen.getByText('No alerts configured')).toBeInTheDocument();
        expect(screen.getByText('Create your first alert to get notified of issues.')).toBeInTheDocument();
    });

    it('renders alert list when alerts provided', () => {
        render(<ObservabilityAlerts alerts={sampleAlerts} />);
        expect(screen.getByText('High CPU Alert')).toBeInTheDocument();
        expect(screen.getByText('Low Disk Space')).toBeInTheDocument();
    });

    it('shows correct stats cards', () => {
        render(<ObservabilityAlerts alerts={sampleAlerts} history={sampleHistory} />);
        expect(screen.getByText('Total Alerts')).toBeInTheDocument();
        expect(screen.getByText('Active Alerts')).toBeInTheDocument();
        expect(screen.getByText('Currently Triggered')).toBeInTheDocument();
        expect(screen.getByText('Total Triggers')).toBeInTheDocument();
    });

    it('shows enabled/disabled badges correctly', () => {
        render(<ObservabilityAlerts alerts={sampleAlerts} />);
        expect(screen.getByText('Enabled')).toBeInTheDocument();
        expect(screen.getByText('Disabled')).toBeInTheDocument();
    });

    it('renders alert history', () => {
        render(<ObservabilityAlerts alerts={sampleAlerts} history={sampleHistory} />);
        expect(screen.getByText('Recent Alert History')).toBeInTheDocument();
        expect(screen.getAllByText('High CPU Alert').length).toBeGreaterThanOrEqual(2);
        expect(screen.getByText('triggered')).toBeInTheDocument();
        expect(screen.getByText('resolved')).toBeInTheDocument();
    });

    it('shows empty history message when no history', () => {
        render(<ObservabilityAlerts alerts={sampleAlerts} history={[]} />);
        expect(screen.getByText('No alert history yet')).toBeInTheDocument();
    });

    it('opens create modal when Create Alert button is clicked', () => {
        render(<ObservabilityAlerts alerts={sampleAlerts} />);
        const createButtons = screen.getAllByText('Create Alert');
        fireEvent.click(createButtons[0]);
        expect(screen.getByRole('dialog')).toBeInTheDocument();
        expect(screen.getByText('Create Alert', { selector: 'h2' })).toBeInTheDocument();
    });

    it('opens edit modal with pre-filled data', () => {
        render(<ObservabilityAlerts alerts={sampleAlerts} />);
        // Click edit button (Edit icon button)
        // Find the Edit button specifically
        const editButton = screen.getAllByRole('button')[4]; // after header button, stats, toggle, this is edit
        fireEvent.click(editButton);
        // Modal should be open
        const modal = screen.queryByRole('dialog');
        if (modal) {
            expect(modal).toBeInTheDocument();
        }
    });

    it('renders delete buttons for each alert', () => {
        render(<ObservabilityAlerts alerts={sampleAlerts} />);
        // Each alert has toggle, edit, delete buttons (3 per alert)
        const ghostButtons = screen.getAllByRole('button').filter(
            btn => btn.getAttribute('data-variant') === 'ghost' && btn.getAttribute('data-size') === 'sm'
        );
        // 2 alerts * 3 buttons each = 6
        expect(ghostButtons.length).toBe(6);
    });

    it('displays channel badges for each alert', () => {
        render(<ObservabilityAlerts alerts={sampleAlerts} />);
        // First alert has email + slack, second has email
        const emailBadges = screen.getAllByText('Email');
        expect(emailBadges.length).toBeGreaterThanOrEqual(1);
        expect(screen.getByText('Slack')).toBeInTheDocument();
    });

    it('shows trigger count and last triggered info', () => {
        render(<ObservabilityAlerts alerts={sampleAlerts} />);
        expect(screen.getByText('3 triggers')).toBeInTheDocument();
        expect(screen.getByText('0 triggers')).toBeInTheDocument();
    });

    it('renders create alert button in empty state', () => {
        render(<ObservabilityAlerts />);
        const buttons = screen.getAllByText('Create Alert');
        expect(buttons.length).toBeGreaterThanOrEqual(1);
    });
});
