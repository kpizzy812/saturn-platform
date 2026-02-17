import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../../Frontend/utils/test-utils';
import { CommandPalette } from '@/components/ui/CommandPalette';
import type { RecentResource } from '@/hooks/useRecentResources';
import type { FavoriteResource } from '@/hooks/useResourceFrequency';
import { useSearch } from '@/hooks/useSearch';

// Mock useSearch hook
vi.mock('@/hooks/useSearch', () => ({
    useSearch: vi.fn(() => ({ results: [], isLoading: false })),
}));

// Stable function references for usePaletteBrowse mock
const mockFetchBrowse = vi.fn();
const mockClearCache = vi.fn();

// Mock usePaletteBrowse hook
vi.mock('@/hooks/usePaletteBrowse', () => ({
    usePaletteBrowse: vi.fn(() => ({
        items: [],
        isLoading: false,
        fetchBrowse: mockFetchBrowse,
        clearCache: mockClearCache,
    })),
}));

// Mock Inertia router
vi.mock('@inertiajs/react', () => ({
    router: {
        visit: vi.fn(),
    },
}));

const mockUseSearch = useSearch as ReturnType<typeof vi.fn>;

describe('CommandPalette', () => {
    const onClose = vi.fn();

    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('should not render palette content when closed', () => {
        render(
            <CommandPalette open={false} onClose={onClose} />,
        );
        expect(screen.queryByPlaceholderText('Search commands and resources...')).not.toBeInTheDocument();
    });

    it('should render when open', () => {
        render(<CommandPalette open={true} onClose={onClose} />);
        expect(screen.getByPlaceholderText('Search commands and resources...')).toBeInTheDocument();
    });

    it('should not show "Projects" in navigation', () => {
        render(<CommandPalette open={true} onClose={onClose} />);
        expect(screen.getByText('Dashboard')).toBeInTheDocument();
        const buttons = screen.getAllByRole('button');
        const projectButton = buttons.find((b) => b.textContent?.trim() === 'Projects');
        expect(projectButton).toBeUndefined();
    });

    it('should show Dashboard in navigation', () => {
        render(<CommandPalette open={true} onClose={onClose} />);
        expect(screen.getByText('Dashboard')).toBeInTheDocument();
    });

    it('should show Servers in navigation', () => {
        render(<CommandPalette open={true} onClose={onClose} />);
        expect(screen.getByText('Servers')).toBeInTheDocument();
    });

    it('should filter commands by query', () => {
        render(<CommandPalette open={true} onClose={onClose} />);
        const input = screen.getByPlaceholderText('Search commands and resources...');
        fireEvent.change(input, { target: { value: 'deploy' } });
        expect(screen.getByText('Deploy')).toBeInTheDocument();
        expect(screen.queryByText('Dashboard')).not.toBeInTheDocument();
    });

    it('should show recent items when query is empty', () => {
        const recentItems: RecentResource[] = [
            { type: 'server', name: 'My Server', uuid: 'srv-1', href: '/servers/srv-1', timestamp: Date.now() },
            { type: 'application', name: 'My App', uuid: 'app-1', href: '/applications/app-1', timestamp: Date.now() - 1000 },
        ];

        render(<CommandPalette open={true} onClose={onClose} recentItems={recentItems} />);
        expect(screen.getByText('Recent')).toBeInTheDocument();
        expect(screen.getByText('My Server')).toBeInTheDocument();
        expect(screen.getByText('My App')).toBeInTheDocument();
    });

    it('should not show recent items when there is a query', () => {
        const recentItems: RecentResource[] = [
            { type: 'server', name: 'My Server', uuid: 'srv-1', href: '/servers/srv-1', timestamp: Date.now() },
        ];

        render(<CommandPalette open={true} onClose={onClose} recentItems={recentItems} />);
        const input = screen.getByPlaceholderText('Search commands and resources...');
        fireEvent.change(input, { target: { value: 'settings' } });
        expect(screen.queryByText('Recent')).not.toBeInTheDocument();
    });

    it('should call onClose when Escape is pressed', () => {
        render(<CommandPalette open={true} onClose={onClose} />);
        const input = screen.getByPlaceholderText('Search commands and resources...');
        fireEvent.keyDown(input, { key: 'Escape' });
        expect(onClose).toHaveBeenCalledTimes(1);
    });

    it('should call onClose when backdrop is clicked', () => {
        render(<CommandPalette open={true} onClose={onClose} />);
        const backdrop = document.querySelector('.bg-black\\/50');
        expect(backdrop).toBeTruthy();
        fireEvent.click(backdrop!);
        expect(onClose).toHaveBeenCalled();
    });

    it('should show navigate and actions groups', () => {
        render(<CommandPalette open={true} onClose={onClose} />);
        expect(screen.getByText('Navigate')).toBeInTheDocument();
        expect(screen.getByText('Actions')).toBeInTheDocument();
        // "Settings" appears as both group label and command name
        expect(screen.getAllByText('Settings')).toHaveLength(2);
    });

    // Drill-down tests

    it('should show drill-in chevron on drillable items', () => {
        render(<CommandPalette open={true} onClose={onClose} />);
        // Dashboard, Servers, Applications, Services, Databases should all have chevrons
        const buttons = screen.getAllByRole('button');
        const dashboardBtn = buttons.find((b) => b.textContent?.includes('Dashboard'));
        expect(dashboardBtn).toBeTruthy();
        // ChevronRight SVG should be inside drillable items
        const svgs = dashboardBtn!.querySelectorAll('svg');
        expect(svgs.length).toBeGreaterThanOrEqual(1);
    });

    it('should show keyboard hints in footer', () => {
        render(<CommandPalette open={true} onClose={onClose} />);
        expect(screen.getByText('navigate')).toBeInTheDocument();
        expect(screen.getByText('select')).toBeInTheDocument();
        expect(screen.getByText('drill in')).toBeInTheDocument();
    });

    // Favorites tests

    it('should show favorites when provided', () => {
        const favorites: FavoriteResource[] = [
            { type: 'server', id: 'srv-1', name: 'Favorite Server', href: '/servers/srv-1', score: 5 },
        ];

        render(<CommandPalette open={true} onClose={onClose} favorites={favorites} />);
        expect(screen.getByText('Favorites')).toBeInTheDocument();
        expect(screen.getByText('Favorite Server')).toBeInTheDocument();
    });

    it('should not show favorites when there is a query', () => {
        const favorites: FavoriteResource[] = [
            { type: 'server', id: 'srv-1', name: 'Favorite Server', href: '/servers/srv-1', score: 5 },
        ];

        render(<CommandPalette open={true} onClose={onClose} favorites={favorites} />);
        const input = screen.getByPlaceholderText('Search commands and resources...');
        fireEvent.change(input, { target: { value: 'settings' } });
        expect(screen.queryByText('Favorites')).not.toBeInTheDocument();
    });

    it('should not show favorites when empty', () => {
        render(<CommandPalette open={true} onClose={onClose} favorites={[]} />);
        expect(screen.queryByText('Favorites')).not.toBeInTheDocument();
    });

    // Search context tests

    it('should show search context from useSearch', () => {
        mockUseSearch.mockReturnValue({
            results: [
                {
                    type: 'application',
                    uuid: 'app-1',
                    name: 'API Service',
                    description: 'Main API',
                    href: '/applications/app-1',
                    project_name: 'Saturn',
                    environment_name: 'production',
                },
            ],
            isLoading: false,
        });

        render(<CommandPalette open={true} onClose={onClose} />);
        const input = screen.getByPlaceholderText('Search commands and resources...');
        fireEvent.change(input, { target: { value: 'API Service' } });

        expect(screen.getByText('Saturn / production')).toBeInTheDocument();
    });
});
