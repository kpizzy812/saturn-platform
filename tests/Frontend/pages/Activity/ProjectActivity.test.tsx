import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import ProjectActivity from '@/pages/Activity/ProjectActivity';
import type { Project, ActivityLog, Environment } from '@/types';

const mockProject: Project = {
    id: 1,
    uuid: 'proj-uuid-1',
    name: 'Production API',
    description: 'Main production API',
    environments: [],
};

const mockActivities: ActivityLog[] = [
    {
        id: '1',
        action: 'deployment_started',
        description: 'started deployment',
        user: {
            id: 1,
            name: 'John Doe',
            email: 'john@example.com',
            avatar: null,
        },
        resource: null,
        timestamp: '2024-01-15T10:00:00Z',
    },
];

const mockEnvironments: Environment[] = [
    {
        id: 1,
        uuid: 'env-uuid-1',
        name: 'production',
        project_id: 1,
        applications: [],
        databases: [],
        services: [],
    },
];

describe('Project Activity Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render project name', () => {
            render(<ProjectActivity project={mockProject} activities={mockActivities} environments={mockEnvironments} />);

            expect(screen.getByText('Production API')).toBeInTheDocument();
            expect(screen.getByText('Project Activity Timeline')).toBeInTheDocument();
        });

        it('should render back button', () => {
            render(<ProjectActivity project={mockProject} activities={mockActivities} environments={mockEnvironments} />);

            expect(screen.getByText('Back to Project')).toBeInTheDocument();
        });

        it('should render statistics cards', () => {
            render(<ProjectActivity project={mockProject} activities={mockActivities} environments={mockEnvironments} />);

            expect(screen.getByText('Deployments')).toBeInTheDocument();
            expect(screen.getByText('Settings Changes')).toBeInTheDocument();
            expect(screen.getByText('Database Actions')).toBeInTheDocument();
        });

        it('should render search input', () => {
            render(<ProjectActivity project={mockProject} activities={mockActivities} environments={mockEnvironments} />);

            expect(screen.getByPlaceholderText('Search activities...')).toBeInTheDocument();
        });

        it('should render environment filter', () => {
            render(<ProjectActivity project={mockProject} activities={mockActivities} environments={mockEnvironments} />);

            expect(screen.getByText('All Environments')).toBeInTheDocument();
            expect(screen.getByText('production')).toBeInTheDocument();
        });

        it('should render filter buttons', () => {
            render(<ProjectActivity project={mockProject} activities={mockActivities} environments={mockEnvironments} />);

            expect(screen.getByText('All Activities')).toBeInTheDocument();
            expect(screen.getByText('Deployments')).toBeInTheDocument();
            expect(screen.getByText('Applications')).toBeInTheDocument();
            expect(screen.getByText('Databases')).toBeInTheDocument();
            expect(screen.getByText('Settings')).toBeInTheDocument();
        });
    });

    describe('not found state', () => {
        it('should render not found message when project is undefined', () => {
            render(<ProjectActivity project={undefined} activities={mockActivities} environments={mockEnvironments} />);

            expect(screen.getByText('Project not found')).toBeInTheDocument();
        });
    });

    describe('empty state', () => {
        it('should render empty state when no activities', () => {
            render(<ProjectActivity project={mockProject} activities={[]} environments={mockEnvironments} />);

            expect(screen.getByText('No activities found')).toBeInTheDocument();
        });
    });

    describe('pagination', () => {
        it('should show results count', () => {
            render(<ProjectActivity project={mockProject} activities={mockActivities} environments={mockEnvironments} />);

            expect(screen.getByText(/Showing .* of .* activities/)).toBeInTheDocument();
        });
    });
});
