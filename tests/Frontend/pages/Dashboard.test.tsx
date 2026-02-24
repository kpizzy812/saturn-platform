import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '../utils/test-utils';

// Mock the @inertiajs/react module
vi.mock('@inertiajs/react', () => ({
    Head: ({ children, title }: { children?: React.ReactNode; title?: string }) => (
        <title>{title}</title>
    ),
    Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    usePage: () => ({
        props: {
            auth: {
                user: { id: 1, name: 'Test User', email: 'test@example.com' },
            },
        },
    }),
}));

// Import after mock
import Dashboard from '@/pages/Dashboard';

// Mock projects data for testing
const mockProjects = [
    { id: 1, name: 'production-api', lastActivity: '2 hours ago', servicesCount: 3, status: 'active' as const },
    { id: 2, name: 'staging-frontend', lastActivity: '5 hours ago', servicesCount: 2, status: 'active' as const },
    { id: 3, name: 'analytics-service', lastActivity: '1 day ago', servicesCount: 4, status: 'deploying' as const },
    { id: 4, name: 'dev-environment', lastActivity: '3 days ago', servicesCount: 1, status: 'inactive' as const },
    { id: 5, name: 'marketing-site', lastActivity: '1 week ago', servicesCount: 2, status: 'active' as const },
    { id: 6, name: 'internal-tools', lastActivity: '2 weeks ago', servicesCount: 5, status: 'active' as const },
];

describe('Dashboard Page', () => {
    it('renders the workspace header', () => {
        render(<Dashboard projects={mockProjects} />);
        expect(screen.getByText('My Workspace')).toBeInTheDocument();
    });

    it('shows project cards', () => {
        render(<Dashboard projects={mockProjects} />);
        expect(screen.getByText('production-api')).toBeInTheDocument();
        expect(screen.getByText('staging-frontend')).toBeInTheDocument();
        expect(screen.getByText('analytics-service')).toBeInTheDocument();
    });

    it('shows project stats', () => {
        render(<Dashboard projects={mockProjects} />);
        // Check for project count in subtitle
        expect(screen.getByText(/6 projects/)).toBeInTheDocument();
    });

    it('shows New Project button', () => {
        render(<Dashboard projects={mockProjects} />);
        // There should be multiple "New Project" buttons
        const newProjectButtons = screen.getAllByText('New Project');
        expect(newProjectButtons.length).toBeGreaterThan(0);
    });

    it('shows resource counts on project cards', () => {
        render(<Dashboard projects={mockProjects} />);
        // Resource counts shown as "N resources" text
        expect(screen.getByText('3 resources')).toBeInTheDocument();
        expect(screen.getAllByText('2 resources').length).toBeGreaterThan(0);
        expect(screen.getByText('4 resources')).toBeInTheDocument();
        expect(screen.getByText('1 resource')).toBeInTheDocument();
    });

    it('shows empty state when no projects', () => {
        render(<Dashboard projects={[]} />);
        expect(screen.getByText('No projects yet')).toBeInTheDocument();
        expect(screen.getByText('Create your first project to get started')).toBeInTheDocument();
    });
});
