import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';

const mockRouterDelete = vi.fn();
const mockRouterVisit = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title?: string }) => <title>{title}</title>,
    Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    router: {
        delete: mockRouterDelete,
        visit: mockRouterVisit,
    },
    usePage: () => ({
        props: {
            auth: {
                user: { id: 1, name: 'Test User', email: 'test@example.com' },
            },
        },
    }),
}));

import ProjectsIndex from '@/pages/Projects/Index';
import type { Project } from '@/types';

const mockProjects: Project[] = [
    {
        id: 1,
        uuid: 'proj-1',
        name: 'PIXEL',
        description: 'Main project',
        team_id: 1,
        environments: [
            {
                id: 1,
                uuid: 'env-1',
                name: 'development',
                project_id: 1,
                applications: [
                    { id: 1, uuid: 'app-1', name: 'frontend', status: 'running', fqdn: '', build_pack: 'nixpacks' },
                    { id: 2, uuid: 'app-2', name: 'backend', status: 'running', fqdn: '', build_pack: 'dockerfile' },
                    { id: 3, uuid: 'app-3', name: 'worker', status: 'stopped', fqdn: '', build_pack: 'nixpacks' },
                ],
                databases: [
                    { id: 1, uuid: 'db-1', name: 'postgres', status: 'running', database_type: 'postgresql' },
                    { id: 2, uuid: 'db-2', name: 'redis', status: 'running', database_type: 'redis' },
                ],
                services: [
                    { id: 1, uuid: 'svc-1', name: 'minio' },
                ],
                created_at: '2026-01-01T00:00:00Z',
                updated_at: '2026-02-20T10:00:00Z',
            },
            {
                id: 2,
                uuid: 'env-2',
                name: 'uat',
                project_id: 1,
                applications: [],
                databases: [],
                services: [],
                created_at: '2026-01-01T00:00:00Z',
                updated_at: '2026-02-20T10:00:00Z',
            },
            {
                id: 3,
                uuid: 'env-3',
                name: 'production',
                project_id: 1,
                applications: [
                    { id: 4, uuid: 'app-4', name: 'api', status: 'running', fqdn: '', build_pack: 'dockerfile' },
                ],
                databases: [
                    { id: 3, uuid: 'db-3', name: 'pg-prod', status: 'running', database_type: 'postgresql' },
                ],
                services: [
                    { id: 2, uuid: 'svc-2', name: 'traefik' },
                    { id: 3, uuid: 'svc-3', name: 'monitoring' },
                ],
                created_at: '2026-01-01T00:00:00Z',
                updated_at: '2026-02-20T10:00:00Z',
            },
        ],
        created_at: '2026-01-01T00:00:00Z',
        updated_at: '2026-02-20T10:00:00Z',
    },
];

describe('Projects Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('Page Header', () => {
        it('renders page title and subtitle', () => {
            render(<ProjectsIndex projects={mockProjects} />);
            expect(screen.getByRole('heading', { name: 'Projects', level: 1 })).toBeInTheDocument();
            expect(screen.getByText('Manage your applications and services')).toBeInTheDocument();
        });

        it('renders New Project button with link', () => {
            render(<ProjectsIndex projects={mockProjects} />);
            expect(screen.getByText('New Project')).toBeInTheDocument();
            const link = screen.getByRole('link', { name: /New Project/i });
            expect(link).toHaveAttribute('href', '/projects/create');
        });
    });

    describe('Empty State', () => {
        it('shows empty state when no projects', () => {
            render(<ProjectsIndex projects={[]} />);
            expect(screen.getByText('No projects yet')).toBeInTheDocument();
            expect(screen.getByText('Create your first project to start deploying applications.')).toBeInTheDocument();
        });

        it('shows Create Project button in empty state', () => {
            render(<ProjectsIndex projects={[]} />);
            expect(screen.getByText('Create Project')).toBeInTheDocument();
        });
    });

    describe('Project Card', () => {
        it('renders project name', () => {
            render(<ProjectsIndex projects={mockProjects} />);
            expect(screen.getByText('PIXEL')).toBeInTheDocument();
        });

        it('shows total resource count', () => {
            // 3 apps + 2 dbs + 1 svc + 1 app + 1 db + 2 svcs = 10
            render(<ProjectsIndex projects={mockProjects} />);
            expect(screen.getByText('10 resources')).toBeInTheDocument();
        });

        it('renders resource breakdown with dots', () => {
            render(<ProjectsIndex projects={mockProjects} />);
            expect(screen.getByText('4 apps')).toBeInTheDocument();
            expect(screen.getByText('3 dbs')).toBeInTheDocument();
            expect(screen.getByText('3 svcs')).toBeInTheDocument();
        });

        it('renders color-coded environment badges', () => {
            render(<ProjectsIndex projects={mockProjects} />);
            expect(screen.getByText('development')).toBeInTheDocument();
            expect(screen.getByText('uat')).toBeInTheDocument();
            expect(screen.getByText('production')).toBeInTheDocument();
        });

        it('links to project detail page', () => {
            render(<ProjectsIndex projects={mockProjects} />);
            const projectLink = screen.getByRole('link', { name: /PIXEL/i });
            expect(projectLink).toHaveAttribute('href', '/projects/proj-1');
        });

        it('shows relative updated time', () => {
            render(<ProjectsIndex projects={mockProjects} />);
            // Should show relative time (e.g., "3d ago" or similar)
            const timeElement = screen.getByText(/ago|just now/);
            expect(timeElement).toBeInTheDocument();
        });
    });

    describe('Project Card with no resources', () => {
        it('shows singular resource text for 0 resources', () => {
            const emptyProject: Project = {
                id: 2,
                uuid: 'proj-empty',
                name: 'Empty Project',
                description: null,
                team_id: 1,
                environments: [],
                created_at: '2026-01-01T00:00:00Z',
                updated_at: '2026-02-23T12:00:00Z',
            };
            render(<ProjectsIndex projects={[emptyProject]} />);
            expect(screen.getByText('0 resources')).toBeInTheDocument();
        });

        it('does not render resource breakdown when no resources', () => {
            const emptyProject: Project = {
                id: 2,
                uuid: 'proj-empty',
                name: 'Empty Project',
                description: null,
                team_id: 1,
                environments: [],
                created_at: '2026-01-01T00:00:00Z',
                updated_at: '2026-02-23T12:00:00Z',
            };
            render(<ProjectsIndex projects={[emptyProject]} />);
            expect(screen.queryByText(/apps/)).not.toBeInTheDocument();
            expect(screen.queryByText(/dbs/)).not.toBeInTheDocument();
            expect(screen.queryByText(/svcs/)).not.toBeInTheDocument();
        });
    });

    describe('Environment Badge Variants', () => {
        it('shows overflow badge when more than 4 environments', () => {
            const manyEnvs: Project = {
                id: 3,
                uuid: 'proj-many',
                name: 'Multi-Env',
                description: null,
                team_id: 1,
                environments: [
                    { id: 1, uuid: 'e1', name: 'dev', project_id: 3, applications: [], databases: [], services: [], created_at: '', updated_at: '' },
                    { id: 2, uuid: 'e2', name: 'staging', project_id: 3, applications: [], databases: [], services: [], created_at: '', updated_at: '' },
                    { id: 3, uuid: 'e3', name: 'uat', project_id: 3, applications: [], databases: [], services: [], created_at: '', updated_at: '' },
                    { id: 4, uuid: 'e4', name: 'production', project_id: 3, applications: [], databases: [], services: [], created_at: '', updated_at: '' },
                    { id: 5, uuid: 'e5', name: 'demo', project_id: 3, applications: [], databases: [], services: [], created_at: '', updated_at: '' },
                ],
                created_at: '2026-01-01T00:00:00Z',
                updated_at: '2026-02-23T12:00:00Z',
            };
            render(<ProjectsIndex projects={[manyEnvs]} />);
            expect(screen.getByText('+1')).toBeInTheDocument();
        });
    });

    describe('Grid Layout', () => {
        it('renders multiple project cards', () => {
            const twoProjects = [
                ...mockProjects,
                {
                    id: 2,
                    uuid: 'proj-2',
                    name: 'ATLAS',
                    description: null,
                    team_id: 1,
                    environments: [],
                    created_at: '2026-01-01T00:00:00Z',
                    updated_at: '2026-02-22T08:00:00Z',
                },
            ];
            render(<ProjectsIndex projects={twoProjects} />);
            expect(screen.getByText('PIXEL')).toBeInTheDocument();
            expect(screen.getByText('ATLAS')).toBeInTheDocument();
        });
    });
});
