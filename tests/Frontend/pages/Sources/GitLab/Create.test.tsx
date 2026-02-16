import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../../utils/test-utils';
import GitLabCreate from '@/pages/Sources/GitLab/Create';

// Mock AppLayout to avoid layout complexities
vi.mock('@/components/layout', () => ({
    AppLayout: ({ children }: any) => <div>{children}</div>,
}));

// Mock router from test-utils already provides router.post
const mockRouterPost = vi.fn();
vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual('../../../utils/test-utils');
    return {
        ...actual,
        router: {
            post: mockRouterPost,
            get: vi.fn(),
            put: vi.fn(),
            delete: vi.fn(),
        },
    };
});

describe('GitLab Create Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders page title and description', () => {
        render(<GitLabCreate />);

        expect(screen.getByRole('heading', { name: 'Connect GitLab' })).toBeInTheDocument();
        expect(screen.getByText(/Connect to GitLab.com or a self-hosted GitLab instance/)).toBeInTheDocument();
    });

    it('renders all form fields', () => {
        render(<GitLabCreate />);

        // Check for inputs via placeholders
        expect(screen.getByPlaceholderText('My GitLab')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('https://gitlab.com')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('https://gitlab.com/api/v4')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('my-organization')).toBeInTheDocument();
    });

    it('renders OAuth application section', () => {
        render(<GitLabCreate />);

        expect(screen.getByText(/OAuth Application \(optional\)/)).toBeInTheDocument();
        expect(screen.getByPlaceholderText('123456')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('••••••••••••••••')).toBeInTheDocument();
    });

    it('renders submit button', () => {
        render(<GitLabCreate />);

        expect(screen.getByText('Create GitLab Connection')).toBeInTheDocument();
    });

    it('has pre-filled default values for URLs', () => {
        render(<GitLabCreate />);

        const apiUrlInput = screen.getByPlaceholderText('https://gitlab.com/api/v4');
        const htmlUrlInput = screen.getByPlaceholderText('https://gitlab.com');

        expect(apiUrlInput).toHaveValue('https://gitlab.com/api/v4');
        expect(htmlUrlInput).toHaveValue('https://gitlab.com');
    });

    it('updates form fields when user types', () => {
        render(<GitLabCreate />);

        const nameInput = screen.getByPlaceholderText('My GitLab');
        fireEvent.change(nameInput, { target: { value: 'Test GitLab' } });

        expect(nameInput).toHaveValue('Test GitLab');
    });

    it('renders setup instructions card', () => {
        render(<GitLabCreate />);

        expect(screen.getByText('Setting up a GitLab OAuth Application')).toBeInTheDocument();
        expect(screen.getByText(/Go to GitLab Settings/)).toBeInTheDocument();
        expect(screen.getByText('GitLab OAuth Documentation')).toBeInTheDocument();
    });

    it('renders external documentation link', () => {
        render(<GitLabCreate />);

        const docLink = screen.getByText('GitLab OAuth Documentation').closest('a');
        expect(docLink).toHaveAttribute('href', 'https://docs.gitlab.com/ee/integration/oauth_provider.html');
        expect(docLink).toHaveAttribute('target', '_blank');
    });

    it('displays connection settings section', () => {
        render(<GitLabCreate />);

        expect(screen.getByText('Connection Settings')).toBeInTheDocument();
    });
});
