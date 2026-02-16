import { describe, it, expect, vi } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
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
        post: vi.fn(),
        processing: false,
        errors: {},
    }),
}));

// Mock AppLayout
vi.mock('@/components/layout', () => ({
    AppLayout: ({ children }: any) => <div data-testid="app-layout">{children}</div>,
}));

// Mock useProjectActivity hook
vi.mock('@/hooks/useProjectActivity', () => ({
    useProjectActivity: () => ({
        activities: [],
        loading: false,
        error: null,
        actionFilter: '',
        setActionFilter: vi.fn(),
        loadMore: vi.fn(),
        hasMore: false,
    }),
}));

// Mock useGitBranches hook
vi.mock('@/hooks/useGitBranches', () => ({
    useGitBranches: () => ({
        branches: ['main', 'dev', 'staging'],
        defaultBranch: 'main',
        platform: null,
        isLoading: false,
        error: null,
        fetchBranches: vi.fn(),
        clearBranches: vi.fn(),
    }),
}));

// Mock BranchSelector component
vi.mock('@/components/ui/BranchSelector', () => ({
    BranchSelector: ({ value, onChange, placeholder }: any) => (
        <input value={value || ''} onChange={(e: any) => onChange(e.target.value)} placeholder={placeholder} data-testid="branch-selector" />
    ),
}));

// Mock UI components
vi.mock('@/components/ui', () => ({
    Card: ({ children, className }: any) => <div className={className}>{children}</div>,
    CardContent: ({ children }: any) => <div>{children}</div>,
    CardHeader: ({ children }: any) => <div>{children}</div>,
    CardTitle: ({ children, className }: any) => <h3 className={className}>{children}</h3>,
    CardDescription: ({ children }: any) => <p>{children}</p>,
    Badge: ({ children, variant }: any) => <span data-variant={variant}>{children}</span>,
    Button: ({ children, onClick, disabled, variant, type, size }: any) => (
        <button onClick={onClick} disabled={disabled} data-variant={variant} type={type}>{children}</button>
    ),
    Input: ({ value, onChange, placeholder, className, ...props }: any) => (
        <input value={value} onChange={onChange} placeholder={placeholder} className={className} {...props} />
    ),
    Select: ({ value, onChange, options, className }: any) => (
        <select value={value} onChange={onChange} className={className}>
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
        is_archived: false,
        archived_at: null,
    },
    environments: [
        { id: 1, uuid: 'env-uuid-1', name: 'production', created_at: '2025-01-01T00:00:00Z', is_empty: false, default_git_branch: 'main' },
        { id: 2, uuid: 'env-uuid-2', name: 'staging', created_at: '2025-01-02T00:00:00Z', is_empty: true, default_git_branch: null },
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
    projectTags: [],
    availableTags: [],
    userTeams: [],
    quotas: {
        application: { current: 2, limit: null },
        service: { current: 1, limit: null },
        database: { current: 0, limit: null },
        environment: { current: 2, limit: null },
    },
    deploymentDefaults: {
        default_build_pack: null,
        default_auto_deploy: null,
        default_force_https: null,
        default_preview_deployments: null,
        default_auto_rollback: null,
    },
    projectRepositories: ['https://github.com/example/repo.git'],
    notificationOverrides: {
        deployment_success: null,
        deployment_failure: null,
        backup_success: null,
        backup_failure: null,
        status_change: null,
        custom_discord_webhook: null,
        custom_slack_webhook: null,
        custom_webhook_url: null,
    },
};

describe('ProjectSettings', () => {
    it('renders all main sections', () => {
        render(<ProjectSettings {...defaultProps} />);

        expect(screen.getByText('Project Settings')).toBeInTheDocument();
        expect(screen.getByText('General')).toBeInTheDocument();
        // "Environments" appears in both section title and Resource Limits
        expect(screen.getAllByText('Environments').length).toBeGreaterThanOrEqual(1);
        expect(screen.getByText('Shared Variables')).toBeInTheDocument();
        expect(screen.getByText('Default Server')).toBeInTheDocument();
        expect(screen.getByText('Tags')).toBeInTheDocument();
        expect(screen.getByText('Notification Overrides')).toBeInTheDocument();
        expect(screen.getByText('Team Access')).toBeInTheDocument();
        expect(screen.getByText('Resource Limits')).toBeInTheDocument();
        expect(screen.getByText('Default Deployment Settings')).toBeInTheDocument();
        expect(screen.getByText('Project Information')).toBeInTheDocument();
        expect(screen.getByText('Activity Log')).toBeInTheDocument();
        expect(screen.getByText('Danger Zone')).toBeInTheDocument();
    });

    it('renders environments list', () => {
        render(<ProjectSettings {...defaultProps} />);

        // "production" and "staging" appear in both Environments section and per-env branches
        expect(screen.getAllByText('production').length).toBeGreaterThanOrEqual(1);
        expect(screen.getAllByText('staging').length).toBeGreaterThanOrEqual(1);
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

    it('renders notification override events with inherit state', () => {
        render(<ProjectSettings {...defaultProps} />);

        expect(screen.getByText('Deployment Success')).toBeInTheDocument();
        expect(screen.getByText('Deployment Failure')).toBeInTheDocument();
        expect(screen.getByText('Backup Success')).toBeInTheDocument();
        expect(screen.getByText('Backup Failure')).toBeInTheDocument();
        expect(screen.getByText('Status Change')).toBeInTheDocument();

        // All should be "Inherit" by default (null values)
        const inheritBadges = screen.getAllByText('Inherit');
        expect(inheritBadges).toHaveLength(5);
    });

    it('renders team channel badges in notification overrides', () => {
        render(<ProjectSettings {...defaultProps} />);

        expect(screen.getByText('Team Channels')).toBeInTheDocument();
        expect(screen.getByText('Discord')).toBeInTheDocument();
        expect(screen.getByText('Telegram')).toBeInTheDocument();
        expect(screen.getByText('Slack')).toBeInTheDocument();
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

    it('shows resource warning when attempting to delete non-empty project', async () => {
        render(<ProjectSettings {...defaultProps} />);

        // Find delete button by looking for buttons with "Delete Project" text
        const allButtons = screen.getAllByRole('button');
        const deleteButton = allButtons.find(btn => btn.textContent?.includes('Delete Project'));

        if (!deleteButton) {
            throw new Error('Delete Project button not found');
        }

        fireEvent.click(deleteButton);

        // Now the warning should appear
        await waitFor(() => {
            expect(screen.getByText(/This will also delete/)).toBeInTheDocument();
        });
        expect(screen.getByText(/2 application/)).toBeInTheDocument();
        expect(screen.getByText(/1 service/)).toBeInTheDocument();
    });

    it('renders links to external settings pages', () => {
        render(<ProjectSettings {...defaultProps} />);

        expect(screen.getByText('Team Notifications')).toBeInTheDocument();
        expect(screen.getByText('Manage Team')).toBeInTheDocument();
        expect(screen.getByText('All shared variables')).toBeInTheDocument();
    });

    it('renders tags section with empty state', () => {
        render(<ProjectSettings {...defaultProps} />);

        expect(screen.getByText('No tags')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('Search or create tag...')).toBeInTheDocument();
    });

    it('renders tags when provided', () => {
        const props = {
            ...defaultProps,
            projectTags: [
                { id: 1, name: 'backend' },
                { id: 2, name: 'critical' },
            ],
        };
        render(<ProjectSettings {...props} />);

        expect(screen.getByText('backend')).toBeInTheDocument();
        expect(screen.getByText('critical')).toBeInTheDocument();
    });

    it('renders resource limits with current usage', () => {
        render(<ProjectSettings {...defaultProps} />);

        expect(screen.getByText('Applications')).toBeInTheDocument();
        expect(screen.getByText('Services')).toBeInTheDocument();
        expect(screen.getByText('Databases')).toBeInTheDocument();
        // "Environments" appears in both the Environments section and Resource Limits
        expect(screen.getAllByText('Environments').length).toBeGreaterThanOrEqual(2);
    });

    it('renders deployment defaults section', () => {
        render(<ProjectSettings {...defaultProps} />);

        expect(screen.getByText('Build Pack')).toBeInTheDocument();
        expect(screen.getByText('Auto Deploy')).toBeInTheDocument();
        expect(screen.getByText('Force HTTPS')).toBeInTheDocument();
        expect(screen.getByText('Preview Deployments')).toBeInTheDocument();
        expect(screen.getByText('Auto Rollback on Failure')).toBeInTheDocument();
        // Per-environment branches
        expect(screen.getByText('Git Branch per Environment')).toBeInTheDocument();
        expect(screen.getByText('Save Branches')).toBeInTheDocument();
    });

    it('renders export and clone buttons', () => {
        render(<ProjectSettings {...defaultProps} />);

        expect(screen.getByText('Export Config')).toBeInTheDocument();
        expect(screen.getByText('Clone Project')).toBeInTheDocument();
    });

    it('renders archive button for active project', () => {
        render(<ProjectSettings {...defaultProps} />);

        expect(screen.getByText('Archive Project')).toBeInTheDocument();
        expect(screen.getByText('Disable the project and prevent new deployments')).toBeInTheDocument();
    });

    it('renders unarchive button for archived project', () => {
        const props = {
            ...defaultProps,
            project: { ...defaultProps.project, is_archived: true, archived_at: '2025-01-10T00:00:00Z' },
        };
        render(<ProjectSettings {...props} />);

        expect(screen.getByText('Unarchive Project')).toBeInTheDocument();
        expect(screen.getByText('Restore this project to active state')).toBeInTheDocument();
    });

    it('renders transfer section when user has other teams', () => {
        const props = {
            ...defaultProps,
            userTeams: [
                { id: 2, name: 'Other Team' },
                { id: 3, name: 'Third Team' },
            ],
        };
        render(<ProjectSettings {...props} />);

        expect(screen.getByText('Transfer Project')).toBeInTheDocument();
        expect(screen.getByText('Move this project to another team')).toBeInTheDocument();
    });

    it('hides transfer section when user has no other teams', () => {
        render(<ProjectSettings {...defaultProps} />);

        // Transfer should not be visible since userTeams is empty
        expect(screen.queryByText('Transfer Project')).not.toBeInTheDocument();
    });

    it('renders activity log section with empty state', () => {
        render(<ProjectSettings {...defaultProps} />);

        expect(screen.getByText('Activity Log')).toBeInTheDocument();
        expect(screen.getByText('No activity recorded yet')).toBeInTheDocument();
    });
});
