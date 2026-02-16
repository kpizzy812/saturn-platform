import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import OAuthConnect from '@/pages/Auth/OAuth/Connect';

describe('OAuthConnect Page', () => {
    const mockProviders = [
        {
            name: 'GitHub',
            provider: 'github',
            icon: vi.fn(),
            connected: true,
            lastSynced: '2024-01-15T10:30:00Z',
            email: 'user@github.com',
        },
        {
            name: 'Google',
            provider: 'google',
            icon: vi.fn(),
            connected: false,
        },
    ];

    beforeEach(() => {
        vi.clearAllMocks();
        // Mock window.location.href
        delete (window as any).location;
        (window as any).location = { href: '' };
    });

    describe('rendering', () => {
        it('should render page title and subtitle', () => {
            render(<OAuthConnect providers={[]} />);

            expect(screen.getByText('Connect Accounts')).toBeInTheDocument();
            expect(screen.getByText('Link your accounts to enable seamless deployments and collaboration.')).toBeInTheDocument();
        });

        it('should render default providers when none provided', () => {
            render(<OAuthConnect providers={[]} />);

            expect(screen.getByText('GitHub')).toBeInTheDocument();
            expect(screen.getByText('Google')).toBeInTheDocument();
            expect(screen.getByText('GitLab')).toBeInTheDocument();
        });

        it('should render connected badge for connected providers', () => {
            render(<OAuthConnect providers={mockProviders} />);

            expect(screen.getByText('Connected')).toBeInTheDocument();
        });

        it('should render not connected badge for disconnected providers', () => {
            render(<OAuthConnect providers={mockProviders} />);

            expect(screen.getByText('Not connected')).toBeInTheDocument();
        });

        it('should display email for connected providers', () => {
            render(<OAuthConnect providers={mockProviders} />);

            expect(screen.getByText('user@github.com')).toBeInTheDocument();
        });

        it('should display last synced information for connected providers', () => {
            render(<OAuthConnect providers={mockProviders} />);

            expect(screen.getByText(/last synced:/i)).toBeInTheDocument();
        });

        it('should render info card', () => {
            render(<OAuthConnect providers={[]} />);

            expect(screen.getByText(/why connect accounts\?/i)).toBeInTheDocument();
            expect(screen.getByText(/linking your git providers/i)).toBeInTheDocument();
        });

        it('should render navigation buttons', () => {
            render(<OAuthConnect providers={[]} />);

            expect(screen.getByRole('button', { name: /back/i })).toBeInTheDocument();
            expect(screen.getByRole('button', { name: /continue to dashboard/i })).toBeInTheDocument();
        });
    });

    describe('provider connection', () => {
        it('should render connect button for disconnected providers', () => {
            render(<OAuthConnect providers={mockProviders} />);

            const connectButtons = screen.getAllByRole('button', { name: /^connect$/i });
            expect(connectButtons.length).toBeGreaterThan(0);
        });

        it('should render disconnect button for connected providers', () => {
            render(<OAuthConnect providers={mockProviders} />);

            expect(screen.getByRole('button', { name: /disconnect/i })).toBeInTheDocument();
        });

        it('should handle connect button click', async () => {
            const { user } = render(<OAuthConnect providers={mockProviders} />);

            const connectButton = screen.getByRole('button', { name: /^connect$/i });
            await user.click(connectButton);

            // Should redirect to OAuth URL
            expect(window.location.href).toContain('/auth/');
        });
    });

    describe('provider descriptions', () => {
        it('should show GitHub description', () => {
            render(<OAuthConnect providers={[]} />);

            expect(screen.getByText(/from GitHub repositories/i)).toBeInTheDocument();
        });

        it('should show GitLab description', () => {
            render(<OAuthConnect providers={[]} />);

            expect(screen.getByText(/from GitLab repositories/i)).toBeInTheDocument();
        });

        it('should show Google description', () => {
            render(<OAuthConnect providers={[]} />);

            expect(screen.getByText(/Google Cloud integrations/i)).toBeInTheDocument();
        });
    });

    describe('navigation', () => {
        it('should call window.history.back when back button is clicked', async () => {
            const mockBack = vi.fn();
            window.history.back = mockBack;

            const { user } = render(<OAuthConnect providers={[]} />);

            const backButton = screen.getByRole('button', { name: /back/i });
            await user.click(backButton);

            expect(mockBack).toHaveBeenCalled();
        });

        it('should redirect to dashboard when continue button is clicked', async () => {
            const { user } = render(<OAuthConnect providers={[]} />);

            const continueButton = screen.getByRole('button', { name: /continue to dashboard/i });
            await user.click(continueButton);

            expect(window.location.href).toBe('/dashboard');
        });
    });

    describe('edge cases', () => {
        it('should handle empty providers array', () => {
            render(<OAuthConnect providers={[]} />);

            // Should render default providers
            expect(screen.getByText('GitHub')).toBeInTheDocument();
            expect(screen.getByText('Google')).toBeInTheDocument();
            expect(screen.getByText('GitLab')).toBeInTheDocument();
        });

        it('should merge provided data with defaults', () => {
            const partialProviders = [
                {
                    name: 'GitHub',
                    provider: 'github',
                    icon: vi.fn(),
                    connected: true,
                },
            ];

            render(<OAuthConnect providers={partialProviders as any} />);

            expect(screen.getByText('Connected')).toBeInTheDocument();
        });
    });
});
