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
import TemplatesCategories from '@/pages/Templates/Categories';

describe('Templates Categories Page', () => {
    it('renders the page header', () => {
        render(<TemplatesCategories />);
        expect(screen.getByText('Browse by Category')).toBeInTheDocument();
        expect(
            screen.getByText(/Find the perfect template for your next project/)
        ).toBeInTheDocument();
    });

    it('shows back to templates link', () => {
        render(<TemplatesCategories />);
        const backLink = screen.getByText('Back to Templates');
        expect(backLink).toBeInTheDocument();
    });

    it('displays featured categories section', () => {
        render(<TemplatesCategories />);
        expect(screen.getByText('Featured Categories')).toBeInTheDocument();
    });

    it('displays all categories section', () => {
        render(<TemplatesCategories />);
        expect(screen.getByText('All Categories')).toBeInTheDocument();
    });

    it('shows category cards with names', () => {
        render(<TemplatesCategories />);
        expect(screen.getByText('Web Apps')).toBeInTheDocument();
        expect(screen.getByText('Databases')).toBeInTheDocument();
        expect(screen.getByText('APIs')).toBeInTheDocument();
        expect(screen.getByText('Full Stack')).toBeInTheDocument();
        expect(screen.getByText('Gaming')).toBeInTheDocument();
        expect(screen.getByText('CMS')).toBeInTheDocument();
    });

    it('shows category descriptions', () => {
        render(<TemplatesCategories />);
        expect(screen.getByText('Full-stack web applications and SPAs')).toBeInTheDocument();
        expect(
            screen.getByText('PostgreSQL, MySQL, MongoDB, Redis and more')
        ).toBeInTheDocument();
        expect(screen.getByText('RESTful and GraphQL API backends')).toBeInTheDocument();
    });

    it('displays template counts for each category', () => {
        render(<TemplatesCategories />);
        // Each category should show a count like "42 templates"
        expect(screen.getByText(/42 templates/)).toBeInTheDocument();
        expect(screen.getByText(/28 templates/)).toBeInTheDocument();
        expect(screen.getByText(/35 templates/)).toBeInTheDocument();
    });

    it('shows featured badge on featured categories', () => {
        render(<TemplatesCategories />);
        const featuredBadges = screen.getAllByText('Featured');
        // Web Apps and Databases are featured
        expect(featuredBadges.length).toBe(2);
    });

    it('displays statistics section', () => {
        render(<TemplatesCategories />);
        expect(screen.getByText('Total Templates')).toBeInTheDocument();
        expect(screen.getByText('Categories')).toBeInTheDocument();
        expect(screen.getByText('Total Deployments')).toBeInTheDocument();
    });

    it('shows correct total template count in stats', () => {
        render(<TemplatesCategories />);
        // Sum of all categories: 42 + 28 + 35 + 19 + 12 + 16 = 152
        expect(screen.getByText('152')).toBeInTheDocument();
    });

    it('shows correct category count in stats', () => {
        render(<TemplatesCategories />);
        // 6 categories total
        const categoryCountElements = screen.getAllByText('6');
        expect(categoryCountElements.length).toBeGreaterThan(0);
    });

    it('displays 1M+ total deployments', () => {
        render(<TemplatesCategories />);
        expect(screen.getByText('1M+')).toBeInTheDocument();
    });

    it('category cards have proper links', () => {
        render(<TemplatesCategories />);
        const links = screen.getAllByRole('link');
        const categoryLinks = links.filter((link) =>
            (link as HTMLAnchorElement).href.includes('category=')
        );
        // Should have at least 6 category links
        expect(categoryLinks.length).toBeGreaterThanOrEqual(6);
    });

    it('web apps category links to correct URL', () => {
        render(<TemplatesCategories />);
        const links = screen.getAllByRole('link');
        const webAppsLink = links.find((link) =>
            (link as HTMLAnchorElement).href.includes('category=web-apps')
        );
        expect(webAppsLink).toBeInTheDocument();
    });
});
