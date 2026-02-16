import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import SourcesIndex from '@/pages/Sources/Index';

describe('Sources Index Page', () => {
    const mockSources = [
        {
            id: 1,
            uuid: 'uuid-1',
            name: 'My GitHub App',
            type: 'github' as const,
            status: 'connected' as const,
            repos_count: 5,
            last_synced_at: '2024-01-15T10:30:00Z',
        },
        {
            id: 2,
            uuid: 'uuid-2',
            name: 'GitLab Instance',
            type: 'gitlab' as const,
            status: 'disconnected' as const,
            repos_count: 3,
        },
    ];

    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page title and description', () => {
            render(<SourcesIndex sources={[]} />);

            expect(screen.getByText('Git Sources')).toBeInTheDocument();
            expect(screen.getByText('Connect your Git providers to deploy applications')).toBeInTheDocument();
        });

        it('should render add new source section', () => {
            render(<SourcesIndex sources={[]} />);

            expect(screen.getByText('Add New Source')).toBeInTheDocument();
        });

        it('should render all three source type options', () => {
            render(<SourcesIndex sources={[]} />);

            expect(screen.getByText('GitHub')).toBeInTheDocument();
            expect(screen.getByText('GitLab')).toBeInTheDocument();
            expect(screen.getByText('Bitbucket')).toBeInTheDocument();
        });

        it('should render source descriptions', () => {
            render(<SourcesIndex sources={[]} />);

            expect(screen.getByText(/connect via github app/i)).toBeInTheDocument();
            expect(screen.getByText(/connect to gitlab.com or self-hosted/i)).toBeInTheDocument();
            expect(screen.getByText(/connect to bitbucket cloud/i)).toBeInTheDocument();
        });

        it('should render help section', () => {
            render(<SourcesIndex sources={[]} />);

            expect(screen.getByText('Why connect a Git source?')).toBeInTheDocument();
            expect(screen.getByText(/automatically deploy on push/i)).toBeInTheDocument();
            expect(screen.getByText(/preview deployments for pull requests/i)).toBeInTheDocument();
        });
    });

    describe('connected sources', () => {
        it('should render connected sources section when sources exist', () => {
            render(<SourcesIndex sources={mockSources} />);

            expect(screen.getByText('Connected Sources')).toBeInTheDocument();
        });

        it('should display source names', () => {
            render(<SourcesIndex sources={mockSources} />);

            expect(screen.getByText('My GitHub App')).toBeInTheDocument();
            expect(screen.getByText('GitLab Instance')).toBeInTheDocument();
        });

        it('should display source types', () => {
            render(<SourcesIndex sources={mockSources} />);

            expect(screen.getByText('github')).toBeInTheDocument();
            expect(screen.getByText('gitlab')).toBeInTheDocument();
        });

        it('should display repository counts', () => {
            render(<SourcesIndex sources={mockSources} />);

            expect(screen.getByText('5 repositories')).toBeInTheDocument();
            expect(screen.getByText('3 repositories')).toBeInTheDocument();
        });

        it('should display connected status badge', () => {
            render(<SourcesIndex sources={mockSources} />);

            expect(screen.getByText('Connected')).toBeInTheDocument();
        });

        it('should display disconnected status badge', () => {
            render(<SourcesIndex sources={mockSources} />);

            expect(screen.getByText('Disconnected')).toBeInTheDocument();
        });

        it('should render manage links for sources', () => {
            render(<SourcesIndex sources={mockSources} />);

            const manageLinks = screen.getAllByText('Manage');
            expect(manageLinks.length).toBe(2);
        });

        it('should have correct manage link URLs', () => {
            render(<SourcesIndex sources={mockSources} />);

            const manageLinks = screen.getAllByText('Manage');
            expect(manageLinks[0].closest('a')).toHaveAttribute('href', '/sources/github/uuid-1');
            expect(manageLinks[1].closest('a')).toHaveAttribute('href', '/sources/gitlab/uuid-2');
        });
    });

    describe('source type cards', () => {
        it('should have correct links for each source type', () => {
            render(<SourcesIndex sources={[]} />);

            const githubCard = screen.getByText('GitHub').closest('a');
            const gitlabCard = screen.getByText('GitLab').closest('a');
            const bitbucketCard = screen.getByText('Bitbucket').closest('a');

            expect(githubCard).toHaveAttribute('href', '/sources/github');
            expect(gitlabCard).toHaveAttribute('href', '/sources/gitlab');
            expect(bitbucketCard).toHaveAttribute('href', '/sources/bitbucket');
        });

        it('should render connect buttons for all source types', () => {
            render(<SourcesIndex sources={[]} />);

            const connectButtons = screen.getAllByRole('button', { name: /connect/i });
            expect(connectButtons.length).toBe(3);
        });
    });

    describe('empty state', () => {
        it('should not show connected sources section when empty', () => {
            render(<SourcesIndex sources={[]} />);

            expect(screen.queryByText('Connected Sources')).not.toBeInTheDocument();
        });
    });

    describe('error status', () => {
        it('should display error status badge', () => {
            const errorSource = {
                id: 3,
                uuid: 'uuid-3',
                name: 'Error Source',
                type: 'github' as const,
                status: 'error' as const,
                repos_count: 0,
            };

            render(<SourcesIndex sources={[errorSource]} />);

            expect(screen.getByText('Error')).toBeInTheDocument();
        });
    });

    describe('edge cases', () => {
        it('should handle empty sources array', () => {
            render(<SourcesIndex sources={[]} />);

            expect(screen.getByText('Git Sources')).toBeInTheDocument();
            expect(screen.getByText('Add New Source')).toBeInTheDocument();
        });

        it('should handle missing sources prop', () => {
            render(<SourcesIndex sources={undefined as any} />);

            expect(screen.getByText('Git Sources')).toBeInTheDocument();
        });
    });
});
