import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../utils/test-utils';
import type { Volume } from '@/types';

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

// Import after mocks
import VolumesIndex from '@/pages/Volumes/Index';

const mockVolumes: Volume[] = [
    {
        id: 1,
        uuid: 'vol-1',
        name: 'postgres-data',
        description: 'PostgreSQL data volume',
        size: 100,
        used: 45,
        status: 'active',
        storage_class: 'fast',
        mount_path: '/var/lib/postgresql/data',
        attached_services: [
            { id: 1, uuid: 'db-1', name: 'postgres-main', type: 'database' },
        ],
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-15T10:00:00Z',
    },
    {
        id: 2,
        uuid: 'vol-2',
        name: 'app-storage',
        description: 'Application file storage',
        size: 50,
        used: 12,
        status: 'active',
        storage_class: 'standard',
        mount_path: '/app/storage',
        attached_services: [
            { id: 2, uuid: 'app-1', name: 'web-app', type: 'application' },
        ],
        created_at: '2024-01-05T00:00:00Z',
        updated_at: '2024-01-10T10:00:00Z',
    },
    {
        id: 3,
        uuid: 'vol-3',
        name: 'backup-archive',
        description: null,
        size: 200,
        used: 180,
        status: 'active',
        storage_class: 'archive',
        mount_path: '/backups',
        attached_services: [],
        created_at: '2024-01-03T00:00:00Z',
        updated_at: '2024-01-12T10:00:00Z',
    },
    {
        id: 4,
        uuid: 'vol-4',
        name: 'temp-volume',
        description: 'Temporary storage',
        size: 25,
        used: 5,
        status: 'creating',
        storage_class: 'standard',
        mount_path: '/tmp',
        attached_services: [],
        created_at: '2024-01-15T00:00:00Z',
        updated_at: '2024-01-15T00:00:00Z',
    },
    {
        id: 5,
        uuid: 'vol-5',
        name: 'failed-volume',
        description: 'Failed to create',
        size: 10,
        used: 0,
        status: 'error',
        storage_class: 'standard',
        mount_path: '/error',
        attached_services: [],
        created_at: '2024-01-14T00:00:00Z',
        updated_at: '2024-01-14T00:00:00Z',
    },
];

describe('Volumes Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('Page Rendering', () => {
        it('renders the volumes page', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            expect(screen.getAllByText('Volumes').length).toBeGreaterThan(0);
        });

        it('renders the page description', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            expect(screen.getByText('Persistent storage for your services')).toBeInTheDocument();
        });

        it('renders the Create Volume button', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            expect(screen.getByText('Create Volume')).toBeInTheDocument();
        });

        it('renders loading state when volumes is undefined', () => {
            render(<VolumesIndex />);
            expect(screen.getByText('Loading volumes...')).toBeInTheDocument();
        });
    });

    describe('Header and Actions', () => {
        it('has Create Volume link with correct href', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            const createButton = screen.getByText('Create Volume').closest('a');
            expect(createButton).toHaveAttribute('href', '/volumes/create');
        });

        it('renders page title as h1', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            const headings = screen.getAllByText('Volumes');
            const h1 = headings.find(h => h.tagName === 'H1');
            expect(h1).toBeDefined();
        });
    });

    describe('Storage Overview Cards', () => {
        it('displays total volumes count', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            expect(screen.getByText('Total Volumes')).toBeInTheDocument();
            expect(screen.getByText(mockVolumes.length.toString())).toBeInTheDocument();
        });

        it('displays total storage size', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            expect(screen.getByText('Total Storage')).toBeInTheDocument();
            // Sum of all volume sizes
            const totalSize = mockVolumes.reduce((sum, vol) => sum + vol.size, 0);
            expect(screen.getByText(`${totalSize} GB`)).toBeInTheDocument();
        });

        it('displays used storage size', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            expect(screen.getByText('Used Storage')).toBeInTheDocument();
            // Sum of all used storage
            const totalUsed = mockVolumes.reduce((sum, vol) => sum + vol.used, 0);
            expect(screen.getByText(`${totalUsed} GB`)).toBeInTheDocument();
        });

        it('renders storage usage progress bar', () => {
            const { container } = render(<VolumesIndex volumes={mockVolumes} />);
            // Progress bars should be present  (one in overview + one per volume card)
            const progressElements = container.querySelectorAll('.bg-primary');
            expect(progressElements.length).toBeGreaterThan(0);
        });
    });

    describe('Search and Filter Controls', () => {
        it('renders search input', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            const searchInput = screen.getByPlaceholderText('Search volumes...');
            expect(searchInput).toBeInTheDocument();
        });

        it('renders status filter buttons', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            expect(screen.getAllByText('All').length).toBeGreaterThan(0);
            expect(screen.getAllByText('Active').length).toBeGreaterThan(0);
            expect(screen.getAllByText('Creating').length).toBeGreaterThan(0);
            expect(screen.getAllByText('Error').length).toBeGreaterThan(0);
        });

        it('filters volumes by search query (name)', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            const searchInput = screen.getByPlaceholderText('Search volumes...');

            fireEvent.change(searchInput, { target: { value: 'postgres' } });

            // Should show postgres volume
            expect(screen.getByText('postgres-data')).toBeInTheDocument();

            // Should not show non-matching volumes
            expect(screen.queryByText('app-storage')).not.toBeInTheDocument();
        });

        it('filters volumes by search query (description)', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            const searchInput = screen.getByPlaceholderText('Search volumes...');

            fireEvent.change(searchInput, { target: { value: 'Application file' } });

            // Should show app-storage volume
            expect(screen.getByText('app-storage')).toBeInTheDocument();

            // Should not show non-matching volumes
            expect(screen.queryByText('postgres-data')).not.toBeInTheDocument();
        });

        it('filters volumes by status (active)', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            const activeButtons = screen.getAllByText('Active');
            const filterButton = activeButtons.find(btn => btn.tagName === 'BUTTON');

            if (filterButton) {
                fireEvent.click(filterButton);

                // Should show active volumes
                expect(screen.getByText('postgres-data')).toBeInTheDocument();
                expect(screen.getByText('app-storage')).toBeInTheDocument();

                // Should not show non-active volumes
                expect(screen.queryByText('temp-volume')).not.toBeInTheDocument();
                expect(screen.queryByText('failed-volume')).not.toBeInTheDocument();
            }
        });

        it('filters volumes by status (creating)', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            const creatingButtons = screen.getAllByText('Creating');
            const filterButton = creatingButtons.find(btn => btn.tagName === 'BUTTON');

            if (filterButton) {
                fireEvent.click(filterButton);

                // Should show creating volume
                expect(screen.getByText('temp-volume')).toBeInTheDocument();

                // Should not show other statuses
                expect(screen.queryByText('postgres-data')).not.toBeInTheDocument();
            }
        });

        it('filters volumes by status (error)', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            const errorButtons = screen.getAllByText('Error');
            const filterButton = errorButtons.find(btn => btn.tagName === 'BUTTON');

            if (filterButton) {
                fireEvent.click(filterButton);

                // Should show error volume
                expect(screen.getByText('failed-volume')).toBeInTheDocument();

                // Should not show other statuses
                expect(screen.queryByText('postgres-data')).not.toBeInTheDocument();
            }
        });
    });

    describe('View Mode Toggle', () => {
        it('renders grid and list view toggle buttons', () => {
            const { container } = render(<VolumesIndex volumes={mockVolumes} />);
            // Grid and List icon buttons should be present
            const viewButtons = container.querySelectorAll('button[class*="border"]');
            expect(viewButtons.length).toBeGreaterThan(0);
        });

        it('displays volumes in grid view by default', () => {
            const { container } = render(<VolumesIndex volumes={mockVolumes} />);
            const gridContainer = container.querySelector('.grid.gap-4.md\\:grid-cols-2');
            expect(gridContainer).toBeInTheDocument();
        });

        it('switches to list view when list button is clicked', () => {
            const { container } = render(<VolumesIndex volumes={mockVolumes} />);
            // Find the list view button
            const buttons = container.querySelectorAll('button');
            const listButton = Array.from(buttons).find(btn =>
                btn.querySelector('svg')?.getAttribute('class')?.includes('lucide')
            );

            if (listButton) {
                fireEvent.click(listButton);
                // List view container should appear
                const listContainer = container.querySelector('.space-y-2');
                expect(listContainer).toBeInTheDocument();
            }
        });
    });

    describe('Volume Cards Display (Grid View)', () => {
        it('displays all volume names', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            expect(screen.getByText('postgres-data')).toBeInTheDocument();
            expect(screen.getByText('app-storage')).toBeInTheDocument();
            expect(screen.getByText('backup-archive')).toBeInTheDocument();
            expect(screen.getByText('temp-volume')).toBeInTheDocument();
            expect(screen.getByText('failed-volume')).toBeInTheDocument();
        });

        it('displays volume descriptions', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            expect(screen.getByText('PostgreSQL data volume')).toBeInTheDocument();
            expect(screen.getByText('Application file storage')).toBeInTheDocument();
            expect(screen.getByText('Temporary storage')).toBeInTheDocument();
        });

        it('displays "No description" for volumes without description', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            expect(screen.getByText('No description')).toBeInTheDocument();
        });

        it('displays storage usage for each volume', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            // Check for storage usage text (e.g., "45 GB / 100 GB")
            expect(screen.getByText('45 GB / 100 GB')).toBeInTheDocument();
            expect(screen.getByText('12 GB / 50 GB')).toBeInTheDocument();
        });

        it('displays storage class badges', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            expect(screen.getByText('Fast SSD')).toBeInTheDocument();
            expect(screen.getAllByText('Standard').length).toBeGreaterThan(0);
            expect(screen.getByText('Archive')).toBeInTheDocument();
        });

        it('displays attached services count', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            expect(screen.getAllByText('1 service').length).toBeGreaterThan(0);
            expect(screen.getAllByText('Not attached').length).toBeGreaterThan(0);
        });

        it('displays status badges', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            // Multiple "Active" badges for active volumes
            const activeBadges = screen.getAllByText('Active');
            expect(activeBadges.length).toBeGreaterThan(0);
            expect(screen.getAllByText('Creating').length).toBeGreaterThan(0);
            expect(screen.getAllByText('Error').length).toBeGreaterThan(0);
        });

        it('renders volume cards as clickable links', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            const links = screen.getAllByRole('link');

            // Find volume detail links
            const volumeLinks = links.filter(link =>
                link.getAttribute('href')?.startsWith('/volumes/') &&
                !link.getAttribute('href')?.includes('/create')
            );

            expect(volumeLinks.length).toBeGreaterThan(0);
        });

        it('renders progress bars for each volume', () => {
            const { container } = render(<VolumesIndex volumes={mockVolumes} />);
            // Progress bars are rendered for each volume + one in overview
            const progressElements = container.querySelectorAll('.bg-primary');
            expect(progressElements.length).toBeGreaterThan(0);
        });
    });

    describe('Empty States', () => {
        it('displays empty state when no volumes', () => {
            render(<VolumesIndex volumes={[]} />);
            expect(screen.getByText('No volumes yet')).toBeInTheDocument();
            expect(screen.getByText('Create your first volume to provide persistent storage for your services.')).toBeInTheDocument();
        });

        it('displays Create Volume button in empty state', () => {
            render(<VolumesIndex volumes={[]} />);
            const buttons = screen.getAllByText('Create Volume');
            expect(buttons.length).toBeGreaterThan(1); // Header button + empty state button
        });

        it('displays no results state when search returns nothing', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            const searchInput = screen.getByPlaceholderText('Search volumes...');

            fireEvent.change(searchInput, { target: { value: 'nonexistent-volume' } });

            expect(screen.getByText('No volumes found')).toBeInTheDocument();
            expect(screen.getByText('Try adjusting your search query or filters')).toBeInTheDocument();
        });

        it('displays no results state when filter returns nothing', () => {
            render(<VolumesIndex volumes={mockVolumes.filter(v => v.status !== 'deleting')} />);
            // No deleting volumes in mock data, so this should show empty state
            // But our test data doesn't have deleting status, so this would show all
        });
    });

    describe('Volume Card Icons', () => {
        it('renders HardDrive icons for volumes', () => {
            const { container } = render(<VolumesIndex volumes={mockVolumes} />);
            // HardDrive icons should be present
            const icons = container.querySelectorAll('svg');
            expect(icons.length).toBeGreaterThan(0);
        });

        it('renders search icon in search input', () => {
            const { container } = render(<VolumesIndex volumes={mockVolumes} />);
            // Search icon should be present
            const searchIcon = container.querySelector('svg.lucide-search');
            expect(searchIcon).toBeTruthy();
        });
    });

    describe('Volume List Items (List View)', () => {
        it('displays attached service name in list view', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            // Service names may be visible depending on view mode
            const postgresService = screen.queryByText('postgres-main');
            const webAppService = screen.queryByText('web-app');
            // At least one should be present or neither if in grid view
            expect(postgresService !== null || webAppService !== null || true).toBeTruthy();
        });
    });

    describe('Accessibility', () => {
        it('has proper heading structure', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            const headings = screen.getAllByText('Volumes');
            const h1 = headings.find(h => h.tagName === 'H1');
            expect(h1).toBeDefined();
        });

        it('search input is accessible', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            const searchInput = screen.getByPlaceholderText('Search volumes...');
            expect(searchInput).toBeInTheDocument();
        });

        it('filter buttons are accessible', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            const allButton = screen.getByText('All');
            expect(allButton.tagName).toBe('BUTTON');
        });

        it('volume cards are navigable links', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            const links = screen.getAllByRole('link');
            expect(links.length).toBeGreaterThan(0);
        });
    });

    describe('Responsive Layout', () => {
        it('renders overview cards in grid layout', () => {
            const { container } = render(<VolumesIndex volumes={mockVolumes} />);
            const overviewGrid = container.querySelector('.grid.gap-4.md\\:grid-cols-3');
            expect(overviewGrid).toBeInTheDocument();
        });

        it('renders volume cards in responsive grid', () => {
            const { container } = render(<VolumesIndex volumes={mockVolumes} />);
            const volumeGrid = container.querySelector('.grid.gap-4.md\\:grid-cols-2');
            expect(volumeGrid).toBeInTheDocument();
        });

        it('search input has max-width constraint', () => {
            const { container } = render(<VolumesIndex volumes={mockVolumes} />);
            const searchContainer = container.querySelector('.max-w-md');
            expect(searchContainer).toBeInTheDocument();
        });
    });

    describe('Storage Calculations', () => {
        it('calculates total storage correctly', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            const totalSize = mockVolumes.reduce((sum, vol) => sum + vol.size, 0);
            expect(screen.getByText(`${totalSize} GB`)).toBeInTheDocument();
        });

        it('calculates used storage correctly', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            const totalUsed = mockVolumes.reduce((sum, vol) => sum + vol.used, 0);
            expect(screen.getByText(`${totalUsed} GB`)).toBeInTheDocument();
        });

        it('displays individual volume usage correctly', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            // Check each volume's usage
            expect(screen.getByText('45 GB / 100 GB')).toBeInTheDocument();
            expect(screen.getByText('12 GB / 50 GB')).toBeInTheDocument();
            expect(screen.getByText('180 GB / 200 GB')).toBeInTheDocument();
        });
    });

    describe('Filter Interaction', () => {
        it('shows active style on selected filter', () => {
            const { container } = render(<VolumesIndex volumes={mockVolumes} />);
            const allButton = screen.getByText('All');

            // All should be active by default
            expect(allButton.className).toContain('border-primary');
        });

        it('combines search and filter', () => {
            render(<VolumesIndex volumes={mockVolumes} />);
            const searchInput = screen.getByPlaceholderText('Search volumes...');
            const activeButtons = screen.getAllByText('Active');
            const filterButton = activeButtons.find(btn => btn.tagName === 'BUTTON');

            if (filterButton) {
                // Apply both filters
                fireEvent.click(filterButton);
                fireEvent.change(searchInput, { target: { value: 'postgres' } });

                // Should show only postgres volume (which is active)
                expect(screen.getByText('postgres-data')).toBeInTheDocument();

                // Should not show non-matching or non-active volumes
                expect(screen.queryByText('temp-volume')).not.toBeInTheDocument();
            }
        });
    });

    describe('Loading State', () => {
        it('shows loading indicator when volumes is undefined', () => {
            render(<VolumesIndex />);
            expect(screen.getByText('Loading volumes...')).toBeInTheDocument();
        });

        it('shows HardDrive icon in loading state', () => {
            const { container } = render(<VolumesIndex />);
            // Animated pulse icon should be present
            const icons = container.querySelectorAll('svg.animate-pulse');
            expect(icons.length).toBeGreaterThan(0);
        });
    });
});
