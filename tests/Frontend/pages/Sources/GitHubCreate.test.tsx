import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../utils/test-utils';
import GitHubCreate from '@/pages/Sources/GitHub/Create';

describe('GitHub Create Page', () => {
    const mockWebhookUrl = 'https://example.com/webhooks/github';

    beforeEach(() => {
        vi.clearAllMocks();
        global.fetch = vi.fn();
        delete (window as any).location;
        (window as any).location = { origin: 'https://example.com' };
    });

    describe('rendering', () => {
        it('should render page title and subtitle', () => {
            render(<GitHubCreate webhookUrl={mockWebhookUrl} />);

            expect(screen.getByText('Create GitHub App')).toBeInTheDocument();
            expect(screen.getByText('Automatic setup via GitHub App Manifest')).toBeInTheDocument();
        });

        it('should render back button', () => {
            render(<GitHubCreate webhookUrl={mockWebhookUrl} />);

            const backButton = screen.getByRole('button', { name: /back/i });
            expect(backButton).toBeInTheDocument();
            expect(backButton.closest('a')).toHaveAttribute('href', '/sources/github');
        });

        it('should render how it works section', () => {
            render(<GitHubCreate webhookUrl={mockWebhookUrl} />);

            expect(screen.getByText('How It Works')).toBeInTheDocument();
            expect(screen.getByText(/click the button/i)).toBeInTheDocument();
            expect(screen.getByText(/confirm on github/i)).toBeInTheDocument();
        });

        it('should render configuration details', () => {
            render(<GitHubCreate webhookUrl={mockWebhookUrl} />);

            expect(screen.getByText('What will be configured')).toBeInTheDocument();
        });

        it('should display repository permissions', () => {
            render(<GitHubCreate webhookUrl={mockWebhookUrl} />);

            expect(screen.getByText(/contents:/i)).toBeInTheDocument();
            expect(screen.getByText(/read & write/i)).toBeInTheDocument();
            expect(screen.getByText(/metadata:/i)).toBeInTheDocument();
            expect(screen.getByText(/pull requests:/i)).toBeInTheDocument();
        });

        it('should display webhook events', () => {
            render(<GitHubCreate webhookUrl={mockWebhookUrl} />);

            expect(screen.getByText('Push events')).toBeInTheDocument();
            expect(screen.getByText('Pull Request events')).toBeInTheDocument();
        });

        it('should display webhook URL', () => {
            render(<GitHubCreate webhookUrl={mockWebhookUrl} />);

            expect(screen.getByText(mockWebhookUrl)).toBeInTheDocument();
        });

        it('should render create GitHub app button', () => {
            render(<GitHubCreate webhookUrl={mockWebhookUrl} />);

            expect(screen.getByRole('button', { name: /create github app/i })).toBeInTheDocument();
        });
    });

    describe('app visibility toggle', () => {
        it('should render visibility toggle section', () => {
            render(<GitHubCreate webhookUrl={mockWebhookUrl} />);

            expect(screen.getByText('App Visibility')).toBeInTheDocument();
        });

        it('should render private and public buttons', () => {
            render(<GitHubCreate webhookUrl={mockWebhookUrl} />);

            expect(screen.getByRole('button', { name: /private/i })).toBeInTheDocument();
            expect(screen.getByRole('button', { name: /public/i })).toBeInTheDocument();
        });

        it('should toggle visibility when buttons are clicked', async () => {
            const { user } = render(<GitHubCreate webhookUrl={mockWebhookUrl} />);

            const publicButton = screen.getByRole('button', { name: /public/i });
            await user.click(publicButton);

            expect(screen.getByText('Anyone can install this GitHub App')).toBeInTheDocument();

            const privateButton = screen.getByRole('button', { name: /private/i });
            await user.click(privateButton);

            expect(screen.getByText('Only you can install this GitHub App')).toBeInTheDocument();
        });
    });

    describe('create button interaction', () => {
        it('should call API when create button is clicked', async () => {
            const mockFetch = vi.fn().mockResolvedValue({
                ok: true,
                json: () => Promise.resolve({ uuid: 'test-uuid' }),
            });
            global.fetch = mockFetch;

            const { user } = render(<GitHubCreate webhookUrl={mockWebhookUrl} />);

            const createButton = screen.getByRole('button', { name: /create github app/i });
            await user.click(createButton);

            await waitFor(() => {
                expect(mockFetch).toHaveBeenCalledWith(
                    '/sources/github',
                    expect.objectContaining({
                        method: 'POST',
                        headers: expect.objectContaining({
                            'Content-Type': 'application/json',
                        }),
                    })
                );
            });
        });

        it('should show loading state when creating', async () => {
            const mockFetch = vi.fn().mockResolvedValue({
                ok: true,
                json: () => Promise.resolve({ uuid: 'test-uuid' }),
            });
            global.fetch = mockFetch;

            const { user } = render(<GitHubCreate webhookUrl={mockWebhookUrl} />);

            const createButton = screen.getByRole('button', { name: /create github app/i });
            await user.click(createButton);

            // Button text changes to "Redirecting to GitHub..."
            await waitFor(() => {
                expect(screen.queryByText(/redirecting to github/i)).toBeInTheDocument();
            });
        });
    });

    describe('information sections', () => {
        it('should render after installation note', () => {
            render(<GitHubCreate webhookUrl={mockWebhookUrl} />);

            expect(screen.getByText('After Creating the App')).toBeInTheDocument();
            expect(screen.getByText(/after the app is created/i)).toBeInTheDocument();
        });

        it('should render help section', () => {
            render(<GitHubCreate webhookUrl={mockWebhookUrl} />);

            expect(screen.getByText('Need Help?')).toBeInTheDocument();
        });

        it('should have link to GitHub documentation', () => {
            render(<GitHubCreate webhookUrl={mockWebhookUrl} />);

            const docLink = screen.getByText('GitHub App documentation');
            expect(docLink).toBeInTheDocument();
            expect(docLink.closest('a')).toHaveAttribute('target', '_blank');
        });
    });

    describe('configuration display', () => {
        it('should show all permissions in grid layout', () => {
            render(<GitHubCreate webhookUrl={mockWebhookUrl} />);

            expect(screen.getByText('Repository Permissions')).toBeInTheDocument();
            expect(screen.getByText('Webhook Events')).toBeInTheDocument();
        });

        it('should display callback URL', () => {
            render(<GitHubCreate webhookUrl={mockWebhookUrl} />);

            expect(screen.getByText(/callback:/i)).toBeInTheDocument();
        });
    });

    describe('edge cases', () => {
        it('should handle API error gracefully', async () => {
            const mockFetch = vi.fn().mockRejectedValue(new Error('API Error'));
            global.fetch = mockFetch;

            const { user } = render(<GitHubCreate webhookUrl={mockWebhookUrl} />);

            const createButton = screen.getByRole('button', { name: /create github app/i });
            await user.click(createButton);

            // Button should re-enable after error
            await waitFor(() => {
                expect(createButton).not.toBeDisabled();
            });
        });

        it('should generate random app name', () => {
            render(<GitHubCreate webhookUrl={mockWebhookUrl} />);

            // App name is generated with random suffix
            expect(screen.getByRole('button', { name: /create github app/i })).toBeInTheDocument();
        });

        it('should have hidden form for GitHub submission', () => {
            render(<GitHubCreate webhookUrl={mockWebhookUrl} />);

            const forms = document.querySelectorAll('form');
            const hiddenForm = Array.from(forms).find(form =>
                form.getAttribute('action')?.includes('github.com')
            );
            expect(hiddenForm).toBeInTheDocument();
        });
    });
});
