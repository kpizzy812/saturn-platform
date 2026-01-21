import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '../../utils/test-utils';

// Mock the @inertiajs/react module
vi.mock('@inertiajs/react', () => ({
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
import { Header } from '@/components/layout/Header';

describe('Header Component', () => {
    it('renders Saturn logo and brand name', () => {
        render(<Header />);
        expect(screen.getByText('Saturn')).toBeInTheDocument();
        // Logo is an SVG element (SaturnLogo component), not text
        const logoLink = screen.getByRole('link', { name: /saturn/i });
        expect(logoLink.querySelector('svg')).toBeInTheDocument();
    });

    it('shows New button by default', () => {
        render(<Header />);
        expect(screen.getByText('New')).toBeInTheDocument();
    });

    it('hides New button when showNewProject is false', () => {
        render(<Header showNewProject={false} />);
        expect(screen.queryByText('New')).not.toBeInTheDocument();
    });

    it('shows Help button', () => {
        render(<Header />);
        expect(screen.getByText('Help')).toBeInTheDocument();
    });

    it('shows user avatar with first letter of name', () => {
        render(<Header />);
        expect(screen.getByText('T')).toBeInTheDocument(); // First letter of "Test User"
    });

    it('links logo to dashboard', () => {
        render(<Header />);
        const logoLink = screen.getByRole('link', { name: /saturn/i });
        expect(logoLink).toHaveAttribute('href', '/dashboard');
    });

    it('links New button to create project page', () => {
        render(<Header />);
        const newLink = screen.getByRole('link', { name: /new/i });
        expect(newLink).toHaveAttribute('href', '/projects/create');
    });
});
