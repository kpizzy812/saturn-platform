import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../../utils/test-utils';
import GitLabIndex from '@/pages/Sources/GitLab/Index';

// Mock AppLayout to avoid layout complexities
vi.mock('@/components/layout', () => ({
    AppLayout: ({ children }: any) => <div>{children}</div>,
}));

// Mock useConfirm hook
const mockConfirm = vi.fn();
vi.mock('@/components/ui', async () => {
    const actual = await vi.importActual('@/components/ui');
    return {
        ...actual,
        useConfirm: () => mockConfirm,
    };
});

describe('GitLab Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders page title and description', () => {
        render(<GitLabIndex connections={[]} />);

        expect(screen.getByRole('heading', { name: 'GitLab Connections' })).toBeInTheDocument();
        expect(screen.getByText('Manage your GitLab instance connections')).toBeInTheDocument();
    });

    it('displays empty state when no connections exist', () => {
        render(<GitLabIndex connections={[]} />);

        expect(screen.getByText('No GitLab connections')).toBeInTheDocument();
        expect(screen.getByText(/Connect to GitLab.com or your self-hosted GitLab instance/)).toBeInTheDocument();
        expect(screen.getByText('Connect GitLab')).toBeInTheDocument();
    });

    it('renders "Add GitLab Connection" button', () => {
        render(<GitLabIndex connections={[]} />);

        const addButtons = screen.getAllByText(/Add GitLab Connection|Connect GitLab/);
        expect(addButtons.length).toBeGreaterThan(0);
    });

    it('displays connection cards when connections exist', () => {
        const connections = [
            {
                id: 1,
                uuid: 'test-uuid-1',
                name: 'My GitLab',
                instance_url: 'https://gitlab.com',
                status: 'active' as const,
                repos_count: 5,
                created_at: '2024-01-01',
            },
            {
                id: 2,
                uuid: 'test-uuid-2',
                name: 'Self-Hosted GitLab',
                instance_url: 'https://gitlab.example.com',
                status: 'suspended' as const,
                repos_count: 3,
                group: 'my-org',
                created_at: '2024-01-02',
            },
        ];

        render(<GitLabIndex connections={connections} />);

        expect(screen.getByText('My GitLab')).toBeInTheDocument();
        expect(screen.getByText('Self-Hosted GitLab')).toBeInTheDocument();
        expect(screen.getByText(/5 repositories/)).toBeInTheDocument();
        expect(screen.getByText(/3 repositories/)).toBeInTheDocument();
    });

    it('renders connection status badges', () => {
        const connections = [
            {
                id: 1,
                uuid: 'test-uuid-1',
                name: 'Active Connection',
                instance_url: 'https://gitlab.com',
                status: 'active' as const,
                repos_count: 5,
                created_at: '2024-01-01',
            },
        ];

        render(<GitLabIndex connections={connections} />);

        expect(screen.getByText('active')).toBeInTheDocument();
    });

    it('displays sync and delete buttons for each connection', () => {
        const connections = [
            {
                id: 1,
                uuid: 'test-uuid-1',
                name: 'My GitLab',
                instance_url: 'https://gitlab.com',
                status: 'active' as const,
                repos_count: 5,
                created_at: '2024-01-01',
            },
        ];

        render(<GitLabIndex connections={connections} />);

        expect(screen.getByText('Sync')).toBeInTheDocument();
        expect(screen.getByText('GitLab')).toBeInTheDocument(); // External link button
    });

    it('renders About GitLab Integration section', () => {
        render(<GitLabIndex connections={[]} />);

        expect(screen.getByText('About GitLab Integration')).toBeInTheDocument();
        expect(screen.getByText(/GitLab.com:/)).toBeInTheDocument();
        expect(screen.getByText(/Self-hosted GitLab:/)).toBeInTheDocument();
    });

    it('renders feature cards', () => {
        render(<GitLabIndex connections={[]} />);

        expect(screen.getByText('Automatic Deployments')).toBeInTheDocument();
        expect(screen.getByText('Merge Request Previews')).toBeInTheDocument();
        expect(screen.getByText('Multiple Instances')).toBeInTheDocument();
    });

    it('displays group name in connection info when present', () => {
        const connections = [
            {
                id: 1,
                uuid: 'test-uuid-1',
                name: 'My GitLab',
                instance_url: 'https://gitlab.com',
                status: 'active' as const,
                repos_count: 5,
                group: 'my-organization',
                created_at: '2024-01-01',
            },
        ];

        render(<GitLabIndex connections={connections} />);

        expect(screen.getByText(/@my-organization/)).toBeInTheDocument();
    });
});
