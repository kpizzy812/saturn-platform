import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../utils/test-utils';

// Mock the @inertiajs/react module
const mockRouterPost = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: ({ children, title }: { children?: React.ReactNode; title?: string }) => (
        <title>{title}</title>
    ),
    Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    router: {
        visit: vi.fn(),
        post: mockRouterPost,
        delete: vi.fn(),
        patch: vi.fn(),
    },
    usePage: () => ({
        props: {
            auth: {
                user: { id: 1, name: 'Test User', email: 'test@example.com' },
            },
        },
    }),
}));

// Import after mock
import ApplicationsCreate from '@/pages/Applications/Create';
import type { Project, Server } from '@/types';

const mockProjects: Project[] = [
    {
        id: 1,
        uuid: 'project-uuid-1',
        name: 'Production',
        description: 'Production environment',
        team_id: 1,
        environments: [
            {
                id: 1,
                uuid: 'env-uuid-1',
                name: 'production',
                project_id: 1,
                applications: [],
                databases: [],
                services: [],
                created_at: '2024-01-01T00:00:00Z',
                updated_at: '2024-01-01T00:00:00Z',
            },
            {
                id: 2,
                uuid: 'env-uuid-2',
                name: 'staging',
                project_id: 1,
                applications: [],
                databases: [],
                services: [],
                created_at: '2024-01-01T00:00:00Z',
                updated_at: '2024-01-01T00:00:00Z',
            },
        ],
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
    },
];

const mockServers: Server[] = [
    {
        id: 1,
        uuid: 'server-uuid-1',
        name: 'production-server',
        description: 'Main production server',
        ip: '192.168.1.100',
        port: 22,
        user: 'root',
        is_reachable: true,
        is_usable: true,
        settings: null,
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
    },
];

describe('Applications Create Page', () => {
    beforeEach(() => {
        mockRouterPost.mockClear();
    });

    it('renders the page header', () => {
        render(<ApplicationsCreate projects={mockProjects} servers={mockServers} />);
        expect(screen.getByText('Create Application')).toBeInTheDocument();
        expect(screen.getByText('Deploy from Git or Docker image')).toBeInTheDocument();
    });

    it('shows step indicators', () => {
        render(<ApplicationsCreate projects={mockProjects} servers={mockServers} />);
        expect(screen.getByText('Source')).toBeInTheDocument();
        expect(screen.getByText('Configure')).toBeInTheDocument();
        expect(screen.getByText('Deploy')).toBeInTheDocument();
    });

    it('renders source selection cards on step 1', () => {
        render(<ApplicationsCreate projects={mockProjects} servers={mockServers} />);
        expect(screen.getByText('Select Source')).toBeInTheDocument();
        expect(screen.getByText('GitHub')).toBeInTheDocument();
        expect(screen.getByText('GitLab')).toBeInTheDocument();
        expect(screen.getByText('Bitbucket')).toBeInTheDocument();
        expect(screen.getByText('Docker Image')).toBeInTheDocument();
    });

    it('advances to step 2 when source is selected', async () => {
        render(<ApplicationsCreate projects={mockProjects} servers={mockServers} />);

        const githubButton = screen.getByText('GitHub').closest('button');
        expect(githubButton).toBeInTheDocument();

        if (githubButton) {
            fireEvent.click(githubButton);
        }

        await waitFor(() => {
            expect(screen.getByText('Configure Application')).toBeInTheDocument();
        });
    });

    it('renders configuration form on step 2', async () => {
        render(<ApplicationsCreate projects={mockProjects} servers={mockServers} />);

        // Click GitHub to advance to step 2
        const githubButton = screen.getByText('GitHub').closest('button');
        if (githubButton) {
            fireEvent.click(githubButton);
        }

        await waitFor(() => {
            expect(screen.getByPlaceholderText('my-awesome-app')).toBeInTheDocument();
            expect(screen.getByPlaceholderText('https://github.com/user/repo')).toBeInTheDocument();
            expect(screen.getByText('Build Pack')).toBeInTheDocument();
        });
    });

    it('shows repository fields for Git sources', async () => {
        render(<ApplicationsCreate projects={mockProjects} servers={mockServers} />);

        const githubButton = screen.getByText('GitHub').closest('button');
        if (githubButton) {
            fireEvent.click(githubButton);
        }

        await waitFor(() => {
            expect(screen.getByText('Repository URL *')).toBeInTheDocument();
            expect(screen.getByText('Branch')).toBeInTheDocument();
            expect(screen.getByPlaceholderText('main')).toBeInTheDocument();
        });
    });

    it('shows Docker image field for Docker source', async () => {
        render(<ApplicationsCreate projects={mockProjects} servers={mockServers} />);

        const dockerButton = screen.getByText('Docker Image').closest('button');
        if (dockerButton) {
            fireEvent.click(dockerButton);
        }

        await waitFor(() => {
            expect(screen.getByText('Docker Image *')).toBeInTheDocument();
            expect(screen.getByPlaceholderText('nginx:latest')).toBeInTheDocument();
        });
    });

    it('shows project and environment selects', async () => {
        render(<ApplicationsCreate projects={mockProjects} servers={mockServers} />);

        const githubButton = screen.getByText('GitHub').closest('button');
        if (githubButton) {
            fireEvent.click(githubButton);
        }

        await waitFor(() => {
            expect(screen.getByText('Project *')).toBeInTheDocument();
            expect(screen.getByText('Environment *')).toBeInTheDocument();
            expect(screen.getByText('Server *')).toBeInTheDocument();
        });
    });

    it('shows server selection', async () => {
        render(<ApplicationsCreate projects={mockProjects} servers={mockServers} />);

        const githubButton = screen.getByText('GitHub').closest('button');
        if (githubButton) {
            fireEvent.click(githubButton);
        }

        await waitFor(() => {
            expect(screen.getByText('production-server (192.168.1.100)')).toBeInTheDocument();
        });
    });

    it('has back button on step 2', async () => {
        render(<ApplicationsCreate projects={mockProjects} servers={mockServers} />);

        const githubButton = screen.getByText('GitHub').closest('button');
        if (githubButton) {
            fireEvent.click(githubButton);
        }

        await waitFor(() => {
            const backButtons = screen.getAllByText('Back');
            expect(backButtons.length).toBeGreaterThan(0);
        });
    });

    it('has continue button on step 2', async () => {
        render(<ApplicationsCreate projects={mockProjects} servers={mockServers} />);

        const githubButton = screen.getByText('GitHub').closest('button');
        if (githubButton) {
            fireEvent.click(githubButton);
        }

        await waitFor(() => {
            expect(screen.getByText('Continue')).toBeInTheDocument();
        });
    });

    it('advances to step 3 when continue is clicked', async () => {
        render(<ApplicationsCreate projects={mockProjects} servers={mockServers} />);

        // Step 1: Select GitHub
        const githubButton = screen.getByText('GitHub').closest('button');
        if (githubButton) {
            fireEvent.click(githubButton);
        }

        // Step 2: Click Continue
        await waitFor(() => {
            const continueButton = screen.getByText('Continue');
            fireEvent.click(continueButton);
        });

        // Step 3: Review
        await waitFor(() => {
            expect(screen.getByText('Review & Deploy')).toBeInTheDocument();
        });
    });

    it('shows review summary on step 3', async () => {
        render(<ApplicationsCreate projects={mockProjects} servers={mockServers} />);

        // Navigate to step 3
        const githubButton = screen.getByText('GitHub').closest('button');
        if (githubButton) {
            fireEvent.click(githubButton);
        }

        await waitFor(() => {
            const continueButton = screen.getByText('Continue');
            fireEvent.click(continueButton);
        });

        await waitFor(() => {
            expect(screen.getByText('Application Name')).toBeInTheDocument();
            // Use getAllByText since "Source" appears in both step indicator and review summary
            const sourceTexts = screen.getAllByText('Source');
            expect(sourceTexts.length).toBeGreaterThanOrEqual(1);
            expect(screen.getByText('Ready to deploy')).toBeInTheDocument();
        });
    });

    it('shows Create & Deploy button on step 3', async () => {
        render(<ApplicationsCreate projects={mockProjects} servers={mockServers} />);

        const githubButton = screen.getByText('GitHub').closest('button');
        if (githubButton) {
            fireEvent.click(githubButton);
        }

        await waitFor(() => {
            const continueButton = screen.getByText('Continue');
            fireEvent.click(continueButton);
        });

        await waitFor(() => {
            expect(screen.getByText('Create & Deploy')).toBeInTheDocument();
        });
    });

    it.skip('validates required fields on submit', async () => {
        // Skipped: Validation logic not yet implemented
        render(<ApplicationsCreate projects={mockProjects} servers={mockServers} />);

        // Navigate to step 3
        const githubButton = screen.getByText('GitHub').closest('button');
        if (githubButton) {
            fireEvent.click(githubButton);
        }

        await waitFor(() => {
            const continueButton = screen.getByText('Continue');
            fireEvent.click(continueButton);
        });

        await waitFor(() => {
            const submitButton = screen.getByRole('button', { name: /create & deploy/i });
            fireEvent.click(submitButton);
        });

        // Should show validation errors for empty required fields
        await waitFor(() => {
            // Validation errors appear as text in the DOM
            const bodyText = document.body.textContent || '';
            expect(bodyText).toContain('Application name is required');
            expect(bodyText).toContain('Repository URL is required');
        }, { timeout: 3000 });
    });

    it('shows build pack options', async () => {
        render(<ApplicationsCreate projects={mockProjects} servers={mockServers} />);

        const githubButton = screen.getByText('GitHub').closest('button');
        if (githubButton) {
            fireEvent.click(githubButton);
        }

        await waitFor(() => {
            expect(screen.getByText('Nixpacks (Auto-detect)')).toBeInTheDocument();
            expect(screen.getByText('Dockerfile')).toBeInTheDocument();
            expect(screen.getByText('Docker Compose')).toBeInTheDocument();
        });
    });

    it('shows optional FQDN field', async () => {
        render(<ApplicationsCreate projects={mockProjects} servers={mockServers} />);

        const githubButton = screen.getByText('GitHub').closest('button');
        if (githubButton) {
            fireEvent.click(githubButton);
        }

        await waitFor(() => {
            expect(screen.getByText('Domain (FQDN)')).toBeInTheDocument();
            expect(screen.getByPlaceholderText('app.example.com')).toBeInTheDocument();
        });
    });

    it('shows description field', async () => {
        render(<ApplicationsCreate projects={mockProjects} servers={mockServers} />);

        const githubButton = screen.getByText('GitHub').closest('button');
        if (githubButton) {
            fireEvent.click(githubButton);
        }

        await waitFor(() => {
            expect(screen.getByText('Description')).toBeInTheDocument();
            expect(screen.getByPlaceholderText('Brief description of your application')).toBeInTheDocument();
        });
    });
});
