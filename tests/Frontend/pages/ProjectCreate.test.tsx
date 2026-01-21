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
import ProjectCreate from '@/pages/Projects/Create';

describe('Project Create Page', () => {
    it('renders page title', () => {
        render(<ProjectCreate />);
        expect(screen.getByText('Create a new project')).toBeInTheDocument();
    });

    it('renders subtitle', () => {
        render(<ProjectCreate />);
        expect(screen.getByText('Choose how you want to deploy your project')).toBeInTheDocument();
    });

    it('shows GitHub Repository option', () => {
        render(<ProjectCreate />);
        expect(screen.getByText('GitHub Repository')).toBeInTheDocument();
        expect(screen.getByText('Deploy from a GitHub repository')).toBeInTheDocument();
    });

    it('shows Database option', () => {
        render(<ProjectCreate />);
        expect(screen.getByText('Database')).toBeInTheDocument();
        expect(screen.getByText('PostgreSQL, MySQL, MongoDB, Redis')).toBeInTheDocument();
    });

    it('shows Template option', () => {
        render(<ProjectCreate />);
        expect(screen.getByText('Template')).toBeInTheDocument();
        expect(screen.getByText('Start from a pre-built template')).toBeInTheDocument();
    });

    it('shows Docker Image option', () => {
        render(<ProjectCreate />);
        expect(screen.getByText('Docker Image')).toBeInTheDocument();
        expect(screen.getByText('Deploy from Docker Hub or private registry')).toBeInTheDocument();
    });

    it('shows Function option with Coming Soon badge', () => {
        render(<ProjectCreate />);
        expect(screen.getByText('Function')).toBeInTheDocument();
        expect(screen.getByText('Coming Soon')).toBeInTheDocument();
    });

    it('shows Empty Project option', () => {
        render(<ProjectCreate />);
        expect(screen.getByText('Empty Project')).toBeInTheDocument();
        expect(screen.getByText('Start with a blank project')).toBeInTheDocument();
    });

    it('has back link to dashboard', () => {
        render(<ProjectCreate />);
        const backLink = screen.getByText('Back to Dashboard');
        expect(backLink).toBeInTheDocument();
        expect(backLink.closest('a')).toHaveAttribute('href', '/dashboard');
    });

    it('renders all 6 deploy options', () => {
        render(<ProjectCreate />);
        // Count deploy option titles
        const options = ['GitHub Repository', 'Database', 'Template', 'Docker Image', 'Function', 'Empty Project'];
        options.forEach(option => {
            expect(screen.getByText(option)).toBeInTheDocument();
        });
    });
});
