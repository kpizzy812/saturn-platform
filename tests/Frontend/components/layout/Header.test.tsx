import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '../../utils/test-utils';

// Mock ThemeProvider useTheme hook
vi.mock('@/components/ui/ThemeProvider', async (importOriginal) => {
    const actual = await importOriginal() as any;
    return {
        ...actual,
        useTheme: () => ({
            isDark: false,
            toggleTheme: vi.fn(),
        }),
    };
});

// Mock TeamSwitcher
vi.mock('@/components/ui/TeamSwitcher', () => ({
    TeamSwitcher: () => <div>Team Switcher</div>,
}));

// Mock NotificationDropdown
vi.mock('@/components/ui/NotificationDropdown', () => ({
    NotificationDropdown: () => <div>Notifications</div>,
}));

// Import after mocks
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

    it('shows theme toggle button', () => {
        render(<Header />);
        // Theme toggle has title attribute
        const themeButton = document.querySelector('button[title*="theme"]');
        expect(themeButton).toBeInTheDocument();
    });

    it('shows user avatar with first letter of name', () => {
        render(<Header />);
        // Avatar is rendered in a gradient div element
        const avatarDiv = document.querySelector('.bg-gradient-to-br');
        expect(avatarDiv).toBeInTheDocument();
        expect(avatarDiv?.textContent).toBe('T'); // First letter of "Test User"
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
