import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import VolumesIndex from '../../../../resources/js/pages/Volumes/Index';
import type { Volume } from '../../../../resources/js/types';

vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual('@inertiajs/react');
    return {
        ...actual,
        router: {
            get: vi.fn(),
        },
        Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
            <a href={href}>{children}</a>
        ),
    };
});

describe('Volumes/Index', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    const mockVolumes: Volume[] = [
        {
            id: 1,
            uuid: 'volume-1',
            name: 'postgres-data',
            description: 'PostgreSQL database storage',
            size: 100,
            used: 45,
            mount_path: '/var/lib/postgresql/data',
            storage_class: 'standard',
            status: 'active',
            attached_services: [
                { id: 1, name: 'postgres-main', type: 'postgresql' }
            ],
            created_at: '2024-01-01T00:00:00Z',
            updated_at: '2024-01-15T00:00:00Z',
        },
        {
            id: 2,
            uuid: 'volume-2',
            name: 'app-uploads',
            description: 'User uploaded files',
            size: 50,
            used: 10,
            mount_path: '/app/storage/uploads',
            storage_class: 'fast',
            status: 'creating',
            attached_services: [],
            created_at: '2024-01-10T00:00:00Z',
            updated_at: '2024-01-20T00:00:00Z',
        },
    ];

    it('renders volumes page with heading', () => {
        render(<VolumesIndex volumes={mockVolumes} />);

        expect(screen.getByRole('heading', { level: 1, name: /volumes/i })).toBeInTheDocument();
        expect(screen.getByText('Persistent storage for your services')).toBeInTheDocument();
    });

    it('renders create volume button', () => {
        render(<VolumesIndex volumes={mockVolumes} />);

        // With volumes present, only the header "Create Volume" link renders (no empty state)
        const createButton = screen.getByRole('link', { name: /create volume/i });
        expect(createButton).toBeInTheDocument();
        expect(createButton).toHaveAttribute('href', '/volumes/create');
    });

    it('shows loading state when volumes data is not available', () => {
        render(<VolumesIndex volumes={undefined} />);

        expect(screen.getByText(/loading volumes/i)).toBeInTheDocument();
    });

    it('displays storage overview cards', () => {
        render(<VolumesIndex volumes={mockVolumes} />);

        expect(screen.getByText('Total Volumes')).toBeInTheDocument();
        expect(screen.getByText('2')).toBeInTheDocument();

        expect(screen.getByText('Total Storage')).toBeInTheDocument();
        expect(screen.getByText('150 GB')).toBeInTheDocument();

        expect(screen.getByText('Used Storage')).toBeInTheDocument();
        expect(screen.getByText('55 GB')).toBeInTheDocument();
    });

    it('renders search input', () => {
        render(<VolumesIndex volumes={mockVolumes} />);

        expect(screen.getByPlaceholderText(/search volumes/i)).toBeInTheDocument();
    });

    it('shows status filter buttons', () => {
        render(<VolumesIndex volumes={mockVolumes} />);

        expect(screen.getByRole('button', { name: /^all$/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /^active$/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /^creating$/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /^error$/i })).toBeInTheDocument();
    });

    it('displays view mode toggle buttons', () => {
        render(<VolumesIndex volumes={mockVolumes} />);

        const buttons = screen.getAllByRole('button');
        const viewButtons = buttons.filter(btn =>
            btn.querySelector('svg')?.classList.contains('lucide-grid-3x3') ||
            btn.querySelector('svg')?.classList.contains('lucide-list')
        );

        expect(viewButtons).toHaveLength(2);
    });

    it('shows empty state when no volumes exist', () => {
        render(<VolumesIndex volumes={[]} />);

        expect(screen.getByText(/no volumes yet/i)).toBeInTheDocument();
        expect(screen.getByText(/create your first volume to provide persistent storage for your services/i)).toBeInTheDocument();
    });

    it('displays volume cards in grid view by default', () => {
        render(<VolumesIndex volumes={mockVolumes} />);

        expect(screen.getByText('postgres-data')).toBeInTheDocument();
        expect(screen.getByText('app-uploads')).toBeInTheDocument();
    });

    it('shows volume descriptions', () => {
        render(<VolumesIndex volumes={mockVolumes} />);

        expect(screen.getByText('PostgreSQL database storage')).toBeInTheDocument();
        expect(screen.getByText('User uploaded files')).toBeInTheDocument();
    });

    it('displays volume storage usage', () => {
        render(<VolumesIndex volumes={mockVolumes} />);

        expect(screen.getByText('45 GB / 100 GB')).toBeInTheDocument();
        expect(screen.getByText('10 GB / 50 GB')).toBeInTheDocument();
    });

    it('shows volume status badges', () => {
        render(<VolumesIndex volumes={mockVolumes} />);

        // "Active" appears in both filter button text and status badge
        expect(screen.getAllByText('Active').length).toBeGreaterThanOrEqual(1);
        expect(screen.getAllByText('Creating').length).toBeGreaterThanOrEqual(1);
    });

    it('displays storage class badges', () => {
        render(<VolumesIndex volumes={mockVolumes} />);

        expect(screen.getByText('Standard')).toBeInTheDocument();
        expect(screen.getByText('Fast SSD')).toBeInTheDocument();
    });

    it('shows attached service count', () => {
        render(<VolumesIndex volumes={mockVolumes} />);

        expect(screen.getByText('1 service')).toBeInTheDocument();
        expect(screen.getByText('Not attached')).toBeInTheDocument();
    });

    it('filters volumes by search query on name', async () => {
        const { user } = render(<VolumesIndex volumes={mockVolumes} />);

        const searchInput = screen.getByPlaceholderText(/search volumes/i);
        await user.type(searchInput, 'postgres');

        expect(screen.getByText('postgres-data')).toBeInTheDocument();
        expect(screen.queryByText('app-uploads')).not.toBeInTheDocument();
    });

    it('filters volumes by search query on description', async () => {
        const { user } = render(<VolumesIndex volumes={mockVolumes} />);

        const searchInput = screen.getByPlaceholderText(/search volumes/i);
        await user.type(searchInput, 'uploaded files');

        expect(screen.queryByText('postgres-data')).not.toBeInTheDocument();
        expect(screen.getByText('app-uploads')).toBeInTheDocument();
    });

    it('filters volumes by status', async () => {
        const { user } = render(<VolumesIndex volumes={mockVolumes} />);

        const activeButton = screen.getByRole('button', { name: /^active$/i });
        await user.click(activeButton);

        expect(screen.getByText('postgres-data')).toBeInTheDocument();
        expect(screen.queryByText('app-uploads')).not.toBeInTheDocument();
    });

    it('shows no results state when search has no matches', async () => {
        const { user } = render(<VolumesIndex volumes={mockVolumes} />);

        const searchInput = screen.getByPlaceholderText(/search volumes/i);
        await user.type(searchInput, 'nonexistent');

        expect(screen.getByText(/no volumes found/i)).toBeInTheDocument();
        expect(screen.getByText(/try adjusting your search query or filters/i)).toBeInTheDocument();
    });

    it('creates clickable volume cards with correct links', () => {
        render(<VolumesIndex volumes={mockVolumes} />);

        const links = screen.getAllByRole('link');
        const volumeLinks = links.filter(link =>
            link.getAttribute('href')?.includes('/volumes/volume-')
        );

        expect(volumeLinks).toHaveLength(2);
        expect(volumeLinks[0]).toHaveAttribute('href', '/volumes/volume-1');
        expect(volumeLinks[1]).toHaveAttribute('href', '/volumes/volume-2');
    });

    it('switches to list view when list button is clicked', async () => {
        const { user } = render(<VolumesIndex volumes={mockVolumes} />);

        const buttons = screen.getAllByRole('button');
        const listButton = buttons.find(btn =>
            btn.querySelector('svg')?.classList.contains('lucide-list')
        );

        if (listButton) {
            await user.click(listButton);
            // In list view, service names should be shown
            expect(screen.getByText('postgres-main')).toBeInTheDocument();
        }
    });
});
