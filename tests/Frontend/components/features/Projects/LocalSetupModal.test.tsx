import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../../utils/test-utils';
import { LocalSetupModal } from '@/components/features/Projects';
import type { Environment } from '@/types';

const mockEnvironment: Environment = {
    id: 1,
    uuid: 'env-1',
    name: 'production',
    project_id: 1,
    applications: [
        {
            id: 1,
            uuid: 'app-1',
            name: 'api-server',
            description: null,
            fqdn: 'https://api.example.com',
            repository_project_id: null,
            git_repository: 'https://github.com/org/api-server.git',
            git_branch: 'main',
            build_pack: 'nixpacks',
            status: 'running',
            environment_id: 1,
            destination_id: 1,
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        },
        {
            id: 2,
            uuid: 'app-2',
            name: 'frontend',
            description: null,
            fqdn: 'https://app.example.com',
            repository_project_id: null,
            git_repository: 'https://github.com/org/frontend.git',
            git_branch: 'develop',
            build_pack: 'nixpacks',
            status: 'running',
            environment_id: 1,
            destination_id: 1,
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        },
    ],
    databases: [
        {
            id: 1,
            uuid: 'db-1',
            name: 'main-postgres',
            description: null,
            database_type: 'postgresql',
            status: 'running',
            environment_id: 1,
            postgres_user: 'admin',
            postgres_password: 'secret123',
            postgres_db: 'myapp',
            is_public: true,
            public_port: 5432,
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        },
        {
            id: 2,
            uuid: 'db-2',
            name: 'cache-redis',
            description: null,
            database_type: 'redis',
            status: 'running',
            environment_id: 1,
            redis_password: 'redis-pass',
            is_public: false,
            created_at: '2024-01-01',
            updated_at: '2024-01-01',
        },
    ],
    services: [],
    created_at: '2024-01-01',
    updated_at: '2024-01-01',
};

const emptyEnvironment: Environment = {
    id: 2,
    uuid: 'env-2',
    name: 'staging',
    project_id: 1,
    applications: [],
    databases: [],
    services: [],
    created_at: '2024-01-01',
    updated_at: '2024-01-01',
};

// Mock fetch for env vars API
beforeEach(() => {
    vi.restoreAllMocks();
    global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve([
            { id: 1, uuid: 'ev-1', key: 'DATABASE_URL', value: 'postgres://localhost/myapp', is_preview: false, created_at: '', updated_at: '' },
            { id: 2, uuid: 'ev-2', key: 'REDIS_URL', value: 'redis://localhost:6379', is_preview: false, created_at: '', updated_at: '' },
        ]),
    });
});

describe('LocalSetupModal', () => {
    it('renders modal with title when open', () => {
        render(
            <LocalSetupModal isOpen={true} onClose={vi.fn()} environment={mockEnvironment} />
        );

        expect(screen.getByText('Set up your project locally')).toBeInTheDocument();
    });

    it('does not render content when closed', () => {
        render(
            <LocalSetupModal isOpen={false} onClose={vi.fn()} environment={mockEnvironment} />
        );

        expect(screen.queryByText('Set up your project locally')).not.toBeInTheDocument();
    });

    it('shows section headers', () => {
        render(
            <LocalSetupModal isOpen={true} onClose={vi.fn()} environment={mockEnvironment} />
        );

        expect(screen.getByText('Clone Repositories')).toBeInTheDocument();
        expect(screen.getByText('Environment Variables')).toBeInTheDocument();
        expect(screen.getByText('Database Connections')).toBeInTheDocument();
    });

    describe('Git Clone Section', () => {
        it('shows clone commands for apps with git repos', () => {
            render(
                <LocalSetupModal isOpen={true} onClose={vi.fn()} environment={mockEnvironment} />
            );

            expect(screen.getByText('api-server')).toBeInTheDocument();
            expect(screen.getByText('frontend')).toBeInTheDocument();
            expect(screen.getByText('main')).toBeInTheDocument();
            expect(screen.getByText('develop')).toBeInTheDocument();
        });

        it('shows git clone command with correct repo URL', () => {
            render(
                <LocalSetupModal isOpen={true} onClose={vi.fn()} environment={mockEnvironment} />
            );

            const codeBlocks = document.querySelectorAll('code');
            const codeTexts = Array.from(codeBlocks).map(el => el.textContent);
            expect(codeTexts.some(t => t?.includes('git clone https://github.com/org/api-server.git'))).toBe(true);
            expect(codeTexts.some(t => t?.includes('git checkout main'))).toBe(true);
        });

        it('shows empty state when no apps have repos', () => {
            render(
                <LocalSetupModal isOpen={true} onClose={vi.fn()} environment={emptyEnvironment} />
            );

            expect(screen.getByText('No applications with Git repositories found in this environment.')).toBeInTheDocument();
        });
    });

    describe('Environment Variables Section', () => {
        it('fetches env vars from API for each application', async () => {
            render(
                <LocalSetupModal isOpen={true} onClose={vi.fn()} environment={mockEnvironment} />
            );

            await waitFor(() => {
                expect(global.fetch).toHaveBeenCalledWith(
                    '/api/v1/applications/app-1/envs',
                    expect.objectContaining({
                        headers: { 'Accept': 'application/json' },
                        credentials: 'include',
                    })
                );
            });

            await waitFor(() => {
                expect(global.fetch).toHaveBeenCalledWith(
                    '/api/v1/applications/app-2/envs',
                    expect.objectContaining({
                        headers: { 'Accept': 'application/json' },
                        credentials: 'include',
                    })
                );
            });
        });

        it('shows env vars after loading', async () => {
            render(
                <LocalSetupModal isOpen={true} onClose={vi.fn()} environment={mockEnvironment} />
            );

            await waitFor(() => {
                const codeBlocks = document.querySelectorAll('code');
                const codeTexts = Array.from(codeBlocks).map(el => el.textContent);
                // Values should be hidden by default
                expect(codeTexts.some(t => t?.includes('DATABASE_URL=********'))).toBe(true);
            });
        });

        it('shows empty state when no apps exist', () => {
            render(
                <LocalSetupModal isOpen={true} onClose={vi.fn()} environment={emptyEnvironment} />
            );

            expect(screen.getByText('No applications found in this environment.')).toBeInTheDocument();
        });
    });

    describe('Database Connections Section', () => {
        it('shows database names and types', () => {
            render(
                <LocalSetupModal isOpen={true} onClose={vi.fn()} environment={mockEnvironment} />
            );

            expect(screen.getByText('main-postgres')).toBeInTheDocument();
            expect(screen.getByText('cache-redis')).toBeInTheDocument();
        });

        it('shows connection fields for databases', () => {
            render(
                <LocalSetupModal isOpen={true} onClose={vi.fn()} environment={mockEnvironment} />
            );

            // Multiple databases render Host/Port fields
            expect(screen.getAllByText('Host').length).toBeGreaterThanOrEqual(2);
            expect(screen.getAllByText('Port').length).toBeGreaterThanOrEqual(2);
        });

        it('shows "Not public" warning for private databases', () => {
            render(
                <LocalSetupModal isOpen={true} onClose={vi.fn()} environment={mockEnvironment} />
            );

            expect(screen.getByText('Not public')).toBeInTheDocument();
        });

        it('shows SSH tunnel hint for private databases', () => {
            render(
                <LocalSetupModal isOpen={true} onClose={vi.fn()} environment={mockEnvironment} />
            );

            expect(screen.getByText(/SSH tunnel/)).toBeInTheDocument();
        });

        it('shows empty state when no databases exist', () => {
            render(
                <LocalSetupModal isOpen={true} onClose={vi.fn()} environment={emptyEnvironment} />
            );

            expect(screen.getByText('No databases found in this environment.')).toBeInTheDocument();
        });
    });
});
