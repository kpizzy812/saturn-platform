import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../../Frontend/utils/test-utils';
import { CommandPalette } from '@/components/ui/CommandPalette';
import type { RecentResource } from '@/hooks/useRecentResources';

// Mock useSearch hook
vi.mock('@/hooks/useSearch', () => ({
    useSearch: vi.fn(() => ({ results: [], isLoading: false })),
}));

// Mock Inertia router
vi.mock('@inertiajs/react', () => ({
    router: {
        visit: vi.fn(),
    },
}));

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
        // Check that there's no button containing "Projects" text
        const allText = document.body.textContent;
        // Dashboard should be present but not Projects as a standalone entry
        expect(screen.getByText('Dashboard')).toBeInTheDocument();
        // Projects should not appear as a navigation item
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
        // Dashboard should be filtered out
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
        // Click the backdrop (first element with bg-black/50)
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
});
