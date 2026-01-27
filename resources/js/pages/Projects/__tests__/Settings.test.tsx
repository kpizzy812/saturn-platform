import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import ProjectSettings from '../Settings';

// Mock Inertia
vi.mock('@inertiajs/react', () => ({
    Link: ({ children, href, ...props }: any) => (
        <a href={href} {...props}>{children}</a>
    ),
    router: { delete: vi.fn(), post: vi.fn(), patch: vi.fn() },
    useForm: (defaults: any) => ({
        data: defaults,
        setData: vi.fn(),
        patch: vi.fn(),
        processing: false,
        errors: {},
    }),
}));

// Mock AppLayout
vi.mock('@/components/layout', () => ({
    AppLayout: ({ children }: any) => <div data-testid="app-layout">{children}</div>,
}));

// Mock UI components
vi.mock('@/components/ui', () => ({
    Card: ({ children, className }: any) => <div className={className}>{children}</div>,
    CardContent: ({ children }: any) => <div>{children}</div>,
    CardHeader: ({ children }: any) => <div>{children}</div>,
    CardTitle: ({ children, className }: any) => <h3 className={className}>{children}</h3>,
    CardDescription: ({ children }: any) => <p>{children}</p>,
    Badge: ({ children, variant }: any) => <span data-variant={variant}>{children}</span>,
    Button: ({ children, onClick, disabled, variant }: any) => (
        <button onClick={onClick} disabled={disabled} data-variant={variant}>{children}</button>
    ),
    Input: ({ value, onChange, placeholder, className, ...props }: any) => (
        <input value={value} onChange={onChange} placeholder={placeholder} className={className} {...props} />
    ),
    Select: ({ value, onChange, options }: any) => (
        <select value={value} onChange={onChange}>
            {options?.map((o: any) => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
    ),
}));

const defaultProps = {
    project: {
        id: 1,
        uuid: 'test-uuid-123',
        name: 'TestProject',
        description: 'A test project',
        created_at: '2025-01-01T00:00:00Z',
        updated_at: '2025-01-15T00:00:00Z',
        is_empty: false,
        resources_count: { applications: 2, services: 1, databases: 0 },
        total_resources: 3,
        default_server_id: null,
    },
    environments: [
        { id: 1, uuid: 'env-uuid-1', name: 'production', created_at: '2025-01-01T00:00:00Z', is_empty: false },
        { id: 2, uuid: 'env-uuid-2', name: 'staging', created_at: '2025-01-02T00:00:00Z', is_empty: true },
    ],
    sharedVariables: [
        { id: 1, key: 'API_KEY', value: 'secret123', is_shown_once: false },
    ],
    servers: [
        { id: 1, name: 'prod-server', ip: '1.2.3.4' },
        { id: 2, name: 'staging-server', ip: '5.6.7.8' },
    ],
    notificationChannels: {
        discord: { enabled: true, configured: true },
        slack: { enabled: false, configured: false },
        telegram: { enabled: true, configured: true },
        email: { enabled: false, configured: true },
        webhook: { enabled: false, configured: false },
    },
    teamMembers: [
        { id: 1, name: 'John Doe', email: 'john@example.com', role: 'owner' },
        { id: 2, name: 'Jane Smith', email: 'jane@example.com', role: 'member' },
    ],
    teamName: 'My Team',
};

describe('ProjectSettings', () => {
    it('renders all main sections', () => {
        render(<ProjectSettings {...defaultProps} />);

        expect(screen.getByText('Project Settings')).toBeInTheDocument();
        expect(screen.getByText('General')).toBeInTheDocument();
        expect(screen.getByText('Environments')).toBeInTheDocument();
        expect(screen.getByText('Shared Variables')).toBeInTheDocument();
        expect(screen.getByText('Default Server')).toBeInTheDocument();
        expect(screen.getByText('Notifications')).toBeInTheDocument();
        expect(screen.getByText('Team Access')).toBeInTheDocument();
        expect(screen.getByText('Project Information')).toBeInTheDocument();
        expect(screen.getByText('Danger Zone')).toBeInTheDocument();
    });

    it('renders environments list', () => {
        render(<ProjectSettings {...defaultProps} />);

        expect(screen.getByText('production')).toBeInTheDocument();
        expect(screen.getByText('staging')).toBeInTheDocument();
        expect(screen.getByText('has resources')).toBeInTheDocument();
    });

    it('renders shared variables', () => {
        render(<ProjectSettings {...defaultProps} />);

        expect(screen.getByText('API_KEY')).toBeInTheDocument();
        // Value should be masked by default
        expect(screen.getByText('••••••••')).toBeInTheDocument();
    });

    it('renders server options in select', () => {
        render(<ProjectSettings {...defaultProps} />);

        expect(screen.getByText('No default server')).toBeInTheDocument();
        expect(screen.getByText('prod-server (1.2.3.4)')).toBeInTheDocument();
        expect(screen.getByText('staging-server (5.6.7.8)')).toBeInTheDocument();
    });

    it('renders notification channels with status', () => {
        render(<ProjectSettings {...defaultProps} />);

        expect(screen.getByText('Discord')).toBeInTheDocument();
        expect(screen.getByText('Telegram')).toBeInTheDocument();

        const enabledBadges = screen.getAllByText('Enabled');
        expect(enabledBadges).toHaveLength(2); // Discord + Telegram

        const notConfiguredBadges = screen.getAllByText('Not configured');
        expect(notConfiguredBadges).toHaveLength(2); // Slack + Webhook
    });

    it('renders team members', () => {
        render(<ProjectSettings {...defaultProps} />);

        expect(screen.getByText('John Doe')).toBeInTheDocument();
        expect(screen.getByText('jane@example.com')).toBeInTheDocument();
        expect(screen.getByText('owner')).toBeInTheDocument();
        expect(screen.getByText('member')).toBeInTheDocument();
    });

    it('renders project info metadata', () => {
        render(<ProjectSettings {...defaultProps} />);

        expect(screen.getByText('test-uuid-123')).toBeInTheDocument();
    });

    it('shows resource warning when project is not empty', () => {
        render(<ProjectSettings {...defaultProps} />);

        expect(screen.getByText('Project cannot be deleted')).toBeInTheDocument();
        expect(screen.getByText(/2 application/)).toBeInTheDocument();
        expect(screen.getByText(/1 service/)).toBeInTheDocument();
    });

    it('renders links to external settings pages', () => {
        render(<ProjectSettings {...defaultProps} />);

        expect(screen.getByText('Configure Notifications')).toBeInTheDocument();
        expect(screen.getByText('Manage Team')).toBeInTheDocument();
        expect(screen.getByText('All shared variables')).toBeInTheDocument();
    });
});
