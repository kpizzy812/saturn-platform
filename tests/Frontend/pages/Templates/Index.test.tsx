import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '../../utils/test-utils';

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
import TemplatesIndex from '@/pages/Templates/Index';

// Mock templates data
const mockTemplates = [
    {
        id: '1',
        name: 'Next.js Starter',
        description: 'A production-ready Next.js template with TypeScript',
        category: 'Web Apps',
        tags: ['Next.js', 'React', 'TypeScript'],
        deployCount: 12500,
        featured: true,
        icon: '🚀',
    },
    {
        id: '2',
        name: 'Django + PostgreSQL',
        description: 'Full-stack Django application with PostgreSQL database',
        category: 'Full Stack',
        tags: ['Django', 'Python', 'PostgreSQL'],
        deployCount: 8300,
        featured: true,
        icon: '🐍',
    },
    {
        id: '3',
        name: 'Node.js API + MongoDB',
        description: 'RESTful API with Express.js and MongoDB',
        category: 'APIs',
        tags: ['Node.js', 'Express', 'MongoDB'],
        deployCount: 6200,
        featured: false,
        icon: '📡',
    },
    {
        id: '4',
        name: 'Redis Cache',
        description: 'Redis caching solution',
        category: 'Databases',
        tags: ['Redis', 'Cache'],
        deployCount: 4100,
        featured: false,
        icon: '🔴',
    },
    {
        id: '5',
        name: 'PostgreSQL Database',
        description: 'PostgreSQL database instance',
        category: 'Databases',
        tags: ['PostgreSQL', 'SQL'],
        deployCount: 5500,
        featured: false,
        icon: '🐘',
    },
    {
        id: '6',
        name: 'WordPress + MySQL',
        description: 'WordPress with MySQL database',
        category: 'Web Apps',
        tags: ['WordPress', 'PHP', 'MySQL'],
        deployCount: 3200,
        featured: false,
        icon: '📝',
    },
];

describe('Templates Index Page', () => {
    it('renders the page title', () => {
        render(<TemplatesIndex templates={mockTemplates} />);
        expect(screen.getByText('Template Marketplace')).toBeInTheDocument();
    });

    it('renders the page description', () => {
        render(<TemplatesIndex templates={mockTemplates} />);
        expect(
            screen.getByText(/Deploy production-ready applications in seconds/)
        ).toBeInTheDocument();
    });

    it('shows search bar', () => {
        render(<TemplatesIndex templates={mockTemplates} />);
        const searchInput = screen.getByPlaceholderText('Search templates...');
        expect(searchInput).toBeInTheDocument();
    });

    it('shows category filters', () => {
        render(<TemplatesIndex templates={mockTemplates} />);
        // Check for filter buttons by getting all instances and verifying at least one exists
        expect(screen.getAllByText('All').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Web Apps').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Databases').length).toBeGreaterThan(0);
        expect(screen.getAllByText('APIs').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Full Stack').length).toBeGreaterThan(0);
    });

    it('displays featured templates section', () => {
        render(<TemplatesIndex templates={mockTemplates} />);
        expect(screen.getByText('Featured Templates')).toBeInTheDocument();
    });

    it('displays template cards', () => {
        render(<TemplatesIndex templates={mockTemplates} />);
        expect(screen.getByText('Next.js Starter')).toBeInTheDocument();
        expect(screen.getByText('Django + PostgreSQL')).toBeInTheDocument();
        expect(screen.getByText('Node.js API + MongoDB')).toBeInTheDocument();
    });

    it('shows deploy counts', () => {
        render(<TemplatesIndex templates={mockTemplates} />);
        // Check for "deploys" text which appears on each card
        const deployLabels = screen.getAllByText(/deploys/);
        expect(deployLabels.length).toBeGreaterThan(0);
    });

    it('filters templates by search query', () => {
        render(<TemplatesIndex templates={mockTemplates} />);
        const searchInput = screen.getByPlaceholderText('Search templates...');

        // Search for "Next.js"
        fireEvent.change(searchInput, { target: { value: 'Next.js' } });

        // Should show Next.js template
        expect(screen.getByText('Next.js Starter')).toBeInTheDocument();

        // Should not show Django template (it's filtered out)
        expect(screen.queryByText('Django + PostgreSQL')).not.toBeInTheDocument();
    });

    it('filters templates by category', () => {
        render(<TemplatesIndex templates={mockTemplates} />);

        // Click on "Databases" category - get all instances and click the first button
        const databasesButtons = screen.getAllByText('Databases');
        const filterButton = databasesButtons.find(el => el.tagName === 'BUTTON');
        if (filterButton) {
            fireEvent.click(filterButton);
        }

        // Should show database templates
        expect(screen.getByText('Redis Cache')).toBeInTheDocument();
        expect(screen.getByText('PostgreSQL Database')).toBeInTheDocument();

        // Should not show web app templates
        expect(screen.queryByText('WordPress + MySQL')).not.toBeInTheDocument();
    });

    it('shows template count', () => {
        render(<TemplatesIndex templates={mockTemplates} />);
        // The count text should be visible
        expect(screen.getByText(/templates found/)).toBeInTheDocument();
    });

    it('shows no results message when search has no matches', () => {
        render(<TemplatesIndex templates={mockTemplates} />);
        const searchInput = screen.getByPlaceholderText('Search templates...');

        // Search for something that doesn't exist
        fireEvent.change(searchInput, { target: { value: 'nonexistenttemplate123' } });

        expect(screen.getByText('No templates found')).toBeInTheDocument();
        expect(
            screen.getByText(/Try adjusting your search or filters/)
        ).toBeInTheDocument();
    });

    it('shows back to create project link', () => {
        render(<TemplatesIndex templates={mockTemplates} />);
        expect(screen.getByText('Back to Create Project')).toBeInTheDocument();
    });

    it('template cards have correct category badges', () => {
        render(<TemplatesIndex templates={mockTemplates} />);

        // Check for category badges
        expect(screen.getAllByText('Web Apps').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Databases').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Full Stack').length).toBeGreaterThan(0);
    });

    it('displays template tags', () => {
        render(<TemplatesIndex templates={mockTemplates} />);

        // Check for some common tags - they may appear multiple times
        expect(screen.getAllByText('Next.js').length).toBeGreaterThan(0);
        expect(screen.getAllByText('React').length).toBeGreaterThan(0);
        expect(screen.getAllByText('TypeScript').length).toBeGreaterThan(0);
    });

    it('featured templates are marked', () => {
        render(<TemplatesIndex templates={mockTemplates} />);

        // Featured badges should exist
        const featuredBadges = screen.getAllByText('Featured');
        expect(featuredBadges.length).toBeGreaterThan(0);
    });

    it('combines search and category filters', () => {
        render(<TemplatesIndex templates={mockTemplates} />);
        const searchInput = screen.getByPlaceholderText('Search templates...');

        // First filter by Web Apps category - get all instances and click the first button
        const webAppsButtons = screen.getAllByText('Web Apps');
        const filterButton = webAppsButtons.find(el => el.tagName === 'BUTTON');
        if (filterButton) {
            fireEvent.click(filterButton);
        }

        // Then search for "Next"
        fireEvent.change(searchInput, { target: { value: 'Next' } });

        // Should show Next.js (Web App that matches search)
        expect(screen.getByText('Next.js Starter')).toBeInTheDocument();

        // Should not show Redis (Database category)
        expect(screen.queryByText('Redis Cache')).not.toBeInTheDocument();
    });

    it('shows loading state when no templates provided', () => {
        render(<TemplatesIndex />);
        expect(screen.getByText('Loading templates...')).toBeInTheDocument();
    });
});
