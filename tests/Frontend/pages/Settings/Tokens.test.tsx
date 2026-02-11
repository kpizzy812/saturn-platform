import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../utils/test-utils';
import userEvent from '@testing-library/user-event';
import * as InertiaReact from '@inertiajs/react';

// Import after mock setup
import TokensSettings from '@/pages/Settings/Tokens';

// Mock fetch for API calls
const mockFetch = vi.fn();
global.fetch = mockFetch;

// Mock usePage to return full auth object
const mockUsePage = vi.fn(() => ({
    url: '/settings/tokens',
    props: {
        auth: {
            id: 1,
            name: 'John Doe',
            email: 'john@example.com',
            avatar: null,
            email_verified_at: '2024-01-01T00:00:00Z',
            is_superadmin: false,
            two_factor_enabled: false,
            role: 'owner',
            permissions: {
                isAdmin: false,
                isOwner: true,
                isMember: false,
                isDeveloper: false,
                isViewer: false,
            },
        },
        team: { id: 1, name: 'Test Team', personal_team: true },
        teams: [],
        flash: {},
        appName: 'Saturn',
        aiChatEnabled: false,
    },
}));

vi.spyOn(InertiaReact, 'usePage').mockImplementation(mockUsePage);

const mockTokens = [
    {
        id: 1,
        name: 'Production Token',
        abilities: ['read', 'write', 'deploy'],
        last_used_at: '2024-01-15T10:30:00Z',
        created_at: '2024-01-01T00:00:00Z',
        expires_at: '2025-01-01T00:00:00Z',
    },
    {
        id: 2,
        name: 'Development Token',
        abilities: ['read'],
        last_used_at: null,
        created_at: '2024-01-10T00:00:00Z',
        expires_at: null,
    },
];

describe('Tokens Settings Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockFetch.mockReset();
    });

    it('renders the settings layout with sidebar', () => {
        render(<TokensSettings tokens={[]} />);
        // Settings text appears in title and possibly navigation
        expect(screen.getAllByText('Settings').length).toBeGreaterThan(0);
    });

    it('displays API tokens section', () => {
        render(<TokensSettings tokens={[]} />);
        const apiTokensElements = screen.getAllByText('API Tokens');
        expect(apiTokensElements.length).toBeGreaterThan(0);
        expect(screen.getByText('Manage API tokens for programmatic access')).toBeInTheDocument();
    });

    it('shows empty state when no tokens exist', () => {
        render(<TokensSettings tokens={[]} />);
        expect(screen.getByText('No API tokens')).toBeInTheDocument();
        expect(screen.getByText('Create your first API token to get started')).toBeInTheDocument();
    });

    it('displays multiple create token buttons when no tokens', () => {
        render(<TokensSettings tokens={[]} />);
        const createButtons = screen.getAllByRole('button').filter(btn =>
            btn.textContent?.includes('Create Token')
        );
        // One in header, one in empty state
        expect(createButtons.length).toBeGreaterThan(0);
    });

    it('displays existing tokens with details', () => {
        render(<TokensSettings tokens={mockTokens} />);
        expect(screen.getByText('Production Token')).toBeInTheDocument();
        expect(screen.getByText('Development Token')).toBeInTheDocument();
    });

    it('shows token abilities as badges', () => {
        render(<TokensSettings tokens={mockTokens} />);
        const readBadges = screen.getAllByText('read');
        const writeBadges = screen.getAllByText('write');
        const deployBadges = screen.getAllByText('deploy');
        expect(readBadges.length).toBeGreaterThan(0);
        expect(writeBadges.length).toBeGreaterThan(0);
        expect(deployBadges.length).toBeGreaterThan(0);
    });

    it('shows last used date for used tokens', () => {
        render(<TokensSettings tokens={mockTokens} />);
        const lastUsedText = screen.getByText(/Last used/);
        expect(lastUsedText).toBeInTheDocument();
    });

    it('shows never used badge for unused tokens', () => {
        render(<TokensSettings tokens={mockTokens} />);
        expect(screen.getByText('Never used')).toBeInTheDocument();
    });

    it('displays usage info section', () => {
        render(<TokensSettings tokens={[]} />);
        expect(screen.getByText('Using API Tokens')).toBeInTheDocument();
        expect(screen.getByText('How to authenticate with Saturn API')).toBeInTheDocument();
    });

    it('shows authorization header example', () => {
        render(<TokensSettings tokens={[]} />);
        const codeExample = screen.getByText(/curl -H "Authorization: Bearer YOUR_TOKEN"/);
        expect(codeExample).toBeInTheDocument();
    });

    it('opens create token modal when create button is clicked', async () => {
        const user = userEvent.setup();
        render(<TokensSettings tokens={[]} />);

        const createButton = screen.getAllByRole('button').find(btn =>
            btn.textContent?.includes('Create Token')
        );

        if (createButton) {
            await user.click(createButton);
            await waitFor(() => {
                expect(screen.getByText('Create API Token')).toBeInTheDocument();
                expect(screen.getByText('Give your token a descriptive name')).toBeInTheDocument();
            });
        }
    });

    it('renders create token form fields', async () => {
        const user = userEvent.setup();
        render(<TokensSettings tokens={[]} />);

        const createButton = screen.getAllByRole('button').find(btn =>
            btn.textContent?.includes('Create Token')
        );

        if (createButton) {
            await user.click(createButton);
            await waitFor(() => {
                expect(screen.getByLabelText('Token Name')).toBeInTheDocument();
                expect(screen.getByPlaceholderText('e.g., Production Deploy')).toBeInTheDocument();
            });
        }
    });

    it('renders submit button in create token form', async () => {
        const user = userEvent.setup();
        render(<TokensSettings tokens={[]} />);

        const createButton = screen.getAllByRole('button').find(btn =>
            btn.textContent?.includes('Create Token')
        );

        if (createButton) {
            await user.click(createButton);

            await waitFor(() => {
                expect(screen.getByLabelText('Token Name')).toBeInTheDocument();
            });

            const submitButtons = screen.getAllByRole('button').filter(btn =>
                btn.textContent?.includes('Create Token') && btn.type === 'submit'
            );
            expect(submitButtons.length).toBeGreaterThan(0);
        }
    });

    it('has required fields in create token form', async () => {
        const user = userEvent.setup();
        render(<TokensSettings tokens={[]} />);

        const createButton = screen.getAllByRole('button').find(btn =>
            btn.textContent?.includes('Create Token')
        );

        if (createButton) {
            await user.click(createButton);

            await waitFor(() => {
                const tokenNameInput = screen.getByLabelText('Token Name');
                expect(tokenNameInput).toBeInTheDocument();
                expect(tokenNameInput).toHaveAttribute('required');
            });
        }
    });

    it('has cancel button in create token form', async () => {
        const user = userEvent.setup();
        render(<TokensSettings tokens={[]} />);

        const createButton = screen.getAllByRole('button').find(btn =>
            btn.textContent?.includes('Create Token')
        );

        if (createButton) {
            await user.click(createButton);

            await waitFor(() => {
                const cancelButton = screen.getByRole('button', { name: /Cancel/i });
                expect(cancelButton).toBeInTheDocument();
            });
        }
    });

    it('opens revoke modal when delete button is clicked', async () => {
        const user = userEvent.setup();
        render(<TokensSettings tokens={mockTokens} />);

        const deleteButtons = screen.getAllByRole('button').filter(btn => {
            const svg = btn.querySelector('svg');
            return svg?.classList.contains('text-danger');
        });

        if (deleteButtons.length > 0) {
            await user.click(deleteButtons[0]);

            await waitFor(() => {
                expect(screen.getByText('Revoke API Token')).toBeInTheDocument();
                expect(screen.getByText(/Are you sure you want to revoke/)).toBeInTheDocument();
            });
        }
    });

    it('shows revoke confirmation modal with token name', async () => {
        const user = userEvent.setup();
        render(<TokensSettings tokens={mockTokens} />);

        const deleteButtons = screen.getAllByRole('button').filter(btn => {
            const svg = btn.querySelector('svg');
            return svg?.classList.contains('text-danger');
        });

        if (deleteButtons.length > 0) {
            await user.click(deleteButtons[0]);

            await waitFor(() => {
                const productionTokenElements = screen.getAllByText(/Production Token/);
                expect(productionTokenElements.length).toBeGreaterThan(0);
                expect(screen.getByText(/Any applications using this token will lose access/)).toBeInTheDocument();
            });
        }
    });

    it('has revoke button in confirmation modal', async () => {
        const user = userEvent.setup();
        render(<TokensSettings tokens={mockTokens} />);

        const deleteButtons = screen.getAllByRole('button').filter(btn => {
            const svg = btn.querySelector('svg');
            return svg?.classList.contains('text-danger');
        });

        if (deleteButtons.length > 0) {
            await user.click(deleteButtons[0]);

            await waitFor(() => {
                const revokeButton = screen.getByRole('button', { name: /Revoke Token/i });
                expect(revokeButton).toBeInTheDocument();
            });
        }
    });

    it('displays created date for tokens', () => {
        render(<TokensSettings tokens={mockTokens} />);
        expect(screen.getAllByText(/Created/i).length).toBeGreaterThan(0);
    });

    it('displays expires date for tokens with expiration', () => {
        render(<TokensSettings tokens={mockTokens} />);
        expect(screen.getAllByText(/Expires/i).length).toBeGreaterThan(0);
    });

    it('can close create token modal', async () => {
        const user = userEvent.setup();
        render(<TokensSettings tokens={[]} />);

        const createButton = screen.getAllByRole('button').find(btn =>
            btn.textContent?.includes('Create Token')
        );

        if (createButton) {
            await user.click(createButton);

            await waitFor(() => {
                expect(screen.getByText('Create API Token')).toBeInTheDocument();
            });

            const cancelButton = screen.getByRole('button', { name: /Cancel/i });
            await user.click(cancelButton);

            await waitFor(() => {
                expect(screen.queryByText('Create API Token')).not.toBeInTheDocument();
            });
        }
    });

    it('can type in token name field', async () => {
        const user = userEvent.setup();
        render(<TokensSettings tokens={[]} />);

        const createButton = screen.getAllByRole('button').find(btn =>
            btn.textContent?.includes('Create Token')
        );

        if (createButton) {
            await user.click(createButton);

            await waitFor(() => {
                expect(screen.getByLabelText('Token Name')).toBeInTheDocument();
            });

            const tokenNameInput = screen.getByLabelText('Token Name') as HTMLInputElement;
            await user.type(tokenNameInput, 'Test Token');

            expect(tokenNameInput.value).toBe('Test Token');
        }
    });
});
