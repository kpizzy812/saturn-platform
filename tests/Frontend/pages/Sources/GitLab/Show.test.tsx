import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../../utils/test-utils';
import GitLabShow from '@/pages/Sources/GitLab/Show';

// Mock AppLayout
vi.mock('@/components/layout', () => ({
    AppLayout: ({ children }: any) => <div>{children}</div>,
}));

// Mock useToast
const mockAddToast = vi.fn();
vi.mock('@/components/ui/Toast', () => ({
    useToast: () => ({ addToast: mockAddToast }),
    ToastProvider: ({ children }: any) => <>{children}</>,
}));

// Mock useConfirm
const mockConfirm = vi.fn();
vi.mock('@/components/ui', async () => {
    const actual = await vi.importActual('@/components/ui');
    return {
        ...actual,
        useConfirm: () => mockConfirm,
    };
});

// Mock router
const mockRouterPut = vi.fn();
const mockRouterDelete = vi.fn();
vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual('../../../utils/test-utils');
    return {
        ...actual,
        router: {
            put: mockRouterPut,
            delete: mockRouterDelete,
            post: vi.fn(),
            get: vi.fn(),
        },
    };
});

describe('GitLab Show Page', () => {
    const mockSource = {
        id: 1,
        uuid: 'test-uuid',
        name: 'My GitLab',
        api_url: 'https://gitlab.com/api/v4',
        html_url: 'https://gitlab.com',
        app_id: 12345,
        group_name: 'my-org',
        deploy_key_id: null,
        is_public: true,
        is_system_wide: false,
        connected: true,
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-02T00:00:00Z',
    };

    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders source name and status', () => {
        render(<GitLabShow source={mockSource} applicationsCount={0} />);

        expect(screen.getByRole('heading', { name: 'My GitLab' })).toBeInTheDocument();
        expect(screen.getByText('Connected')).toBeInTheDocument();
    });

    it('displays connection method and applications count', () => {
        render(<GitLabShow source={mockSource} applicationsCount={5} />);

        // OAuth appears in badge and in text, just check for applications count
        expect(screen.getByText(/5 applications/)).toBeInTheDocument();
    });

    it('shows singular "application" when count is 1', () => {
        render(<GitLabShow source={mockSource} applicationsCount={1} />);

        expect(screen.getByText(/1 application$/)).toBeInTheDocument();
    });

    it('renders connection details section', () => {
        render(<GitLabShow source={mockSource} applicationsCount={0} />);

        expect(screen.getByText('Connection Details')).toBeInTheDocument();
        expect(screen.getByText('GitLab instance configuration')).toBeInTheDocument();
    });

    it('displays all connection details in view mode', () => {
        render(<GitLabShow source={mockSource} applicationsCount={0} />);

        // Check labels
        expect(screen.getByText('Name')).toBeInTheDocument();
        expect(screen.getByText('Group')).toBeInTheDocument();
        expect(screen.getByText('API URL')).toBeInTheDocument();
        expect(screen.getByText('HTML URL')).toBeInTheDocument();
        expect(screen.getByText('Connection Method')).toBeInTheDocument();
        expect(screen.getByText('App ID')).toBeInTheDocument();
        expect(screen.getByText('Visibility')).toBeInTheDocument();

        // Check values
        expect(screen.getByText('my-org')).toBeInTheDocument();
        expect(screen.getByText('https://gitlab.com/api/v4')).toBeInTheDocument();
        expect(screen.getByText('https://gitlab.com')).toBeInTheDocument();
        expect(screen.getByText('12345')).toBeInTheDocument();
    });

    it('shows edit button in view mode', () => {
        render(<GitLabShow source={mockSource} applicationsCount={0} />);

        expect(screen.getByText('Edit')).toBeInTheDocument();
    });

    it('enters edit mode when Edit button is clicked', () => {
        render(<GitLabShow source={mockSource} applicationsCount={0} />);

        const editButton = screen.getByText('Edit');
        fireEvent.click(editButton);

        expect(screen.getByText('Cancel')).toBeInTheDocument();
        expect(screen.getByText('Save')).toBeInTheDocument();
    });

    it('renders "Open GitLab" external link', () => {
        render(<GitLabShow source={mockSource} applicationsCount={0} />);

        const openButton = screen.getByText('Open GitLab');
        expect(openButton).toBeInTheDocument();

        const link = openButton.closest('a');
        expect(link).toHaveAttribute('href', 'https://gitlab.com');
        expect(link).toHaveAttribute('target', '_blank');
    });

    it('displays setup warning when not connected', () => {
        const disconnectedSource = { ...mockSource, connected: false };
        render(<GitLabShow source={disconnectedSource} applicationsCount={0} />);

        expect(screen.getByText('Setup Required')).toBeInTheDocument();
        expect(screen.getByText(/This GitLab connection is not fully configured/)).toBeInTheDocument();
    });

    it('renders danger zone section', () => {
        render(<GitLabShow source={mockSource} applicationsCount={0} />);

        expect(screen.getByText('Danger Zone')).toBeInTheDocument();
        expect(screen.getByText('Delete GitLab Connection')).toBeInTheDocument();
    });

    it('disables delete button when applications are using the source', () => {
        render(<GitLabShow source={mockSource} applicationsCount={3} />);

        const deleteButton = screen.getByText('Delete');
        expect(deleteButton).toBeDisabled();
        expect(screen.getByText(/Cannot delete: 3 application\(s\) are using this source/)).toBeInTheDocument();
    });

    it('enables delete button when no applications are using the source', () => {
        render(<GitLabShow source={mockSource} applicationsCount={0} />);

        const deleteButton = screen.getByText('Delete');
        expect(deleteButton).not.toBeDisabled();
    });

    it('displays "Deploy Key" as connection method when deploy_key_id exists', () => {
        const sourceWithDeployKey = { ...mockSource, deploy_key_id: 999, app_id: null };
        render(<GitLabShow source={sourceWithDeployKey} applicationsCount={0} />);

        expect(screen.getByText('Deploy Key')).toBeInTheDocument();
    });

    it('shows visibility badge correctly', () => {
        render(<GitLabShow source={mockSource} applicationsCount={0} />);

        expect(screen.getByText('Public')).toBeInTheDocument();
    });
});
