import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '../../utils/test-utils';

// Mock the @inertiajs/react module
vi.mock('@inertiajs/react', () => ({
    Head: ({ children, title }: { children?: React.ReactNode; title?: string }) => (
        <title>{title}</title>
    ),
    Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    router: {
        visit: vi.fn(),
        post: vi.fn(),
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
import PreviewsIndex from '@/pages/Applications/Previews/Index';
import PreviewShow from '@/pages/Applications/Previews/Show';
import PreviewSettings from '@/pages/Applications/Previews/Settings';
import type { Application, PreviewDeployment, PreviewDeploymentSettings } from '@/types';

const mockApplication: Application = {
    id: 1,
    uuid: 'app-uuid-1',
    name: 'test-app',
    description: 'Test application',
    fqdn: 'app.example.com',
    repository_project_id: null,
    git_repository: 'https://github.com/user/repo',
    git_branch: 'main',
    build_pack: 'nixpacks',
    status: 'running',
    environment_id: 1,
    destination_id: 1,
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-15T00:00:00Z',
};

const mockPreviews: PreviewDeployment[] = [
    {
        id: 1,
        uuid: 'preview-1',
        application_id: 1,
        pull_request_id: 101,
        pull_request_number: 42,
        pull_request_title: 'feat: Add user authentication',
        branch: 'feature/auth',
        commit: 'a1b2c3d4e5f6',
        commit_message: 'feat: Add JWT token support',
        preview_url: 'https://pr-42-app.preview.saturn.io',
        status: 'running',
        auto_delete_at: new Date(Date.now() + 1000 * 60 * 60 * 24 * 7).toISOString(),
        created_at: new Date(Date.now() - 1000 * 60 * 60 * 2).toISOString(),
        updated_at: new Date(Date.now() - 1000 * 60 * 60 * 2).toISOString(),
    },
    {
        id: 2,
        uuid: 'preview-2',
        application_id: 1,
        pull_request_id: 102,
        pull_request_number: 38,
        pull_request_title: 'fix: Resolve memory leak',
        branch: 'bugfix/memory-leak',
        commit: 'b2c3d4e5f6g7',
        commit_message: 'fix: Clear event listeners',
        preview_url: 'https://pr-38-app.preview.saturn.io',
        status: 'deploying',
        auto_delete_at: null,
        created_at: new Date(Date.now() - 1000 * 60 * 60 * 24).toISOString(),
        updated_at: new Date(Date.now() - 1000 * 60 * 60 * 24).toISOString(),
    },
    {
        id: 3,
        uuid: 'preview-3',
        application_id: 1,
        pull_request_id: 103,
        pull_request_number: 35,
        pull_request_title: 'refactor: Update database schema',
        branch: 'feature/db-refactor',
        commit: 'c3d4e5f6g7h8',
        commit_message: 'refactor: Normalize user tables',
        preview_url: 'https://pr-35-app.preview.saturn.io',
        status: 'failed',
        auto_delete_at: null,
        created_at: new Date(Date.now() - 1000 * 60 * 60 * 6).toISOString(),
        updated_at: new Date(Date.now() - 1000 * 60 * 60 * 6).toISOString(),
    },
];

const mockSettings: PreviewDeploymentSettings = {
    enabled: true,
    auto_deploy_on_pr: true,
    url_template: 'pr-{pr_number}-{app_name}.preview.saturn.io',
    auto_delete_days: 7,
    resource_limits: {
        cpu: '1',
        memory: '512M',
    },
};

describe('Previews Index Page', () => {
    it('renders the page header', () => {
        render(<PreviewsIndex application={mockApplication} previews={[]} />);
        const heading = screen.getByRole('heading', { name: 'Preview Deployments' });
        expect(heading).toBeInTheDocument();
        expect(screen.getByText('Manage preview deployments for pull requests')).toBeInTheDocument();
    });

    it('shows Settings button', () => {
        render(<PreviewsIndex application={mockApplication} previews={[]} />);
        const settingsButtons = screen.getAllByText('Settings');
        expect(settingsButtons.length).toBeGreaterThan(0);
    });

    it('shows empty state when no previews exist', () => {
        render(<PreviewsIndex application={mockApplication} previews={[]} />);
        expect(screen.getByText('No preview deployments found')).toBeInTheDocument();
        expect(screen.getByText(/No preview deployments have been created yet/)).toBeInTheDocument();
    });

    it('shows preview cards when previews exist', () => {
        render(<PreviewsIndex application={mockApplication} previews={mockPreviews} />);
        expect(screen.getByText('PR #42')).toBeInTheDocument();
        expect(screen.getByText('PR #38')).toBeInTheDocument();
        expect(screen.getByText('PR #35')).toBeInTheDocument();
    });

    it('shows preview titles', () => {
        render(<PreviewsIndex application={mockApplication} previews={mockPreviews} />);
        expect(screen.getByText('feat: Add user authentication')).toBeInTheDocument();
        expect(screen.getByText('fix: Resolve memory leak')).toBeInTheDocument();
    });

    it('shows filter buttons', () => {
        render(<PreviewsIndex application={mockApplication} previews={mockPreviews} />);
        expect(screen.getByText('All')).toBeInTheDocument();
        expect(screen.getByText('Running')).toBeInTheDocument();
        expect(screen.getByText('Deploying')).toBeInTheDocument();
        expect(screen.getByText('Failed')).toBeInTheDocument();
    });

    it('shows search input', () => {
        render(<PreviewsIndex application={mockApplication} previews={mockPreviews} />);
        const searchInput = screen.getByPlaceholderText(/Search by PR number/i);
        expect(searchInput).toBeInTheDocument();
    });
});

describe('Preview Show Page', () => {
    const mockPreview = mockPreviews[0];

    it('renders preview details', () => {
        render(
            <PreviewShow
                application={mockApplication}
                preview={mockPreview}
                previewUuid="preview-1"
            />
        );
        const prTitle = screen.getAllByText('PR #42');
        expect(prTitle.length).toBeGreaterThan(0);
        expect(screen.getByText('feat: Add user authentication')).toBeInTheDocument();
    });

    it('shows preview URL', () => {
        render(
            <PreviewShow
                application={mockApplication}
                preview={mockPreview}
                previewUuid="preview-1"
            />
        );
        const urlLinks = screen.getAllByText('https://pr-42-app.preview.saturn.io');
        expect(urlLinks.length).toBeGreaterThan(0);
    });

    it('shows action buttons', () => {
        render(
            <PreviewShow
                application={mockApplication}
                preview={mockPreview}
                previewUuid="preview-1"
            />
        );
        expect(screen.getByText('Open Preview')).toBeInTheDocument();
        expect(screen.getByText('Redeploy')).toBeInTheDocument();
        expect(screen.getByText('Delete')).toBeInTheDocument();
    });

    it('shows preview information section', () => {
        render(
            <PreviewShow
                application={mockApplication}
                preview={mockPreview}
                previewUuid="preview-1"
            />
        );
        expect(screen.getByText('Preview Information')).toBeInTheDocument();
        expect(screen.getByText('Preview URL')).toBeInTheDocument();
    });

    it('shows deployment logs section', () => {
        render(
            <PreviewShow
                application={mockApplication}
                preview={mockPreview}
                previewUuid="preview-1"
            />
        );
        expect(screen.getByText('Deployment Logs')).toBeInTheDocument();
    });

    it('shows environment variables section', () => {
        render(
            <PreviewShow
                application={mockApplication}
                preview={mockPreview}
                previewUuid="preview-1"
            />
        );
        expect(screen.getByText('Environment Variables')).toBeInTheDocument();
    });

    it('shows status card', () => {
        render(
            <PreviewShow
                application={mockApplication}
                preview={mockPreview}
                previewUuid="preview-1"
            />
        );
        expect(screen.getByText('Status')).toBeInTheDocument();
    });

    it('shows resource limits', () => {
        render(
            <PreviewShow
                application={mockApplication}
                preview={mockPreview}
                previewUuid="preview-1"
            />
        );
        expect(screen.getByText('Resource Limits')).toBeInTheDocument();
    });
});

describe('Preview Settings Page', () => {
    it('renders settings page header', () => {
        render(
            <PreviewSettings
                application={mockApplication}
                settings={mockSettings}
            />
        );
        expect(screen.getByText('Preview Deployment Settings')).toBeInTheDocument();
    });

    it('shows general settings section', () => {
        render(
            <PreviewSettings
                application={mockApplication}
                settings={mockSettings}
            />
        );
        expect(screen.getByText('General Settings')).toBeInTheDocument();
        expect(screen.getByText('Enable Preview Deployments')).toBeInTheDocument();
        expect(screen.getByText('Auto-deploy on Pull Request')).toBeInTheDocument();
    });

    it('shows URL configuration section', () => {
        render(
            <PreviewSettings
                application={mockApplication}
                settings={mockSettings}
            />
        );
        expect(screen.getByText('URL Configuration')).toBeInTheDocument();
        expect(screen.getByText('Preview URL Template')).toBeInTheDocument();
    });

    it('shows lifecycle settings section', () => {
        render(
            <PreviewSettings
                application={mockApplication}
                settings={mockSettings}
            />
        );
        expect(screen.getByText('Lifecycle Settings')).toBeInTheDocument();
        expect(screen.getByText('Auto-delete after (days)')).toBeInTheDocument();
    });

    it('shows resource limits section', () => {
        render(
            <PreviewSettings
                application={mockApplication}
                settings={mockSettings}
            />
        );
        expect(screen.getByText('Resource Limits')).toBeInTheDocument();
        expect(screen.getByText('CPU Limit')).toBeInTheDocument();
        expect(screen.getByText('Memory Limit')).toBeInTheDocument();
    });

    it('shows save button', () => {
        render(
            <PreviewSettings
                application={mockApplication}
                settings={mockSettings}
            />
        );
        expect(screen.getByText('Save Settings')).toBeInTheDocument();
    });

    it('shows cancel button', () => {
        render(
            <PreviewSettings
                application={mockApplication}
                settings={mockSettings}
            />
        );
        expect(screen.getByText('Cancel')).toBeInTheDocument();
    });

    it('shows info card', () => {
        render(
            <PreviewSettings
                application={mockApplication}
                settings={mockSettings}
            />
        );
        expect(screen.getByText('About Preview Deployments')).toBeInTheDocument();
    });
});
