import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import { router } from '@inertiajs/react';
import VolumeShow from '../../../../resources/js/pages/Volumes/Show';
import type { Volume, VolumeSnapshot } from '../../../../resources/js/types';

vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual('@inertiajs/react');
    return {
        ...actual,
        router: {
            post: vi.fn(),
            delete: vi.fn(),
        },
        Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
            <a href={href}>{children}</a>
        ),
    };
});

describe('Volumes/Show', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    const mockVolume: Volume = {
        id: 1,
        uuid: 'volume-123',
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
    };

    const mockSnapshots: VolumeSnapshot[] = [
        {
            id: 1,
            name: 'pre-upgrade-backup',
            size: '42 GB',
            status: 'completed',
            created_at: '2024-01-15T10:00:00Z',
        },
        {
            id: 2,
            name: 'weekly-backup',
            size: '40 GB',
            status: 'creating',
            created_at: '2024-01-14T10:00:00Z',
        },
    ];

    it('renders volume details page with heading', () => {
        render(<VolumeShow volume={mockVolume} />);

        expect(screen.getByRole('heading', { level: 1, name: /postgres-data/i })).toBeInTheDocument();
        expect(screen.getByText(/postgresql database storage/i)).toBeInTheDocument();
    });

    it('renders back link to volumes page', () => {
        render(<VolumeShow volume={mockVolume} />);

        const backLink = screen.getByRole('link', { name: /back to volumes/i });
        expect(backLink).toBeInTheDocument();
        expect(backLink).toHaveAttribute('href', '/volumes');
    });

    it('displays volume status badge', () => {
        render(<VolumeShow volume={mockVolume} />);

        expect(screen.getByText('Active')).toBeInTheDocument();
    });

    it('shows action buttons', () => {
        render(<VolumeShow volume={mockVolume} />);

        expect(screen.getByRole('button', { name: /create snapshot/i })).toBeInTheDocument();
        const deleteButtons = screen.getAllByRole('button');
        const hasDeleteButton = deleteButtons.some(btn => btn.querySelector('.lucide-trash-2'));
        expect(hasDeleteButton).toBe(true);
    });

    it('displays overview statistics cards', () => {
        render(<VolumeShow volume={mockVolume} />);

        expect(screen.getByText('Total Size')).toBeInTheDocument();
        expect(screen.getByText('100 GB')).toBeInTheDocument();

        expect(screen.getByText('Used')).toBeInTheDocument();
        expect(screen.getByText('45 GB')).toBeInTheDocument();

        expect(screen.getByText('Storage Class')).toBeInTheDocument();
        expect(screen.getByText('Standard')).toBeInTheDocument();

        expect(screen.getByText('Growth Rate')).toBeInTheDocument();
    });

    it('shows storage usage section with progress bar', () => {
        render(<VolumeShow volume={mockVolume} />);

        expect(screen.getByText('Storage Usage')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /resize/i })).toBeInTheDocument();
    });

    it('displays usage trend chart', () => {
        render(<VolumeShow volume={mockVolume} />);

        expect(screen.getByText(/usage trend \(last 7 days\)/i)).toBeInTheDocument();
    });

    it('shows attached services section', () => {
        render(<VolumeShow volume={mockVolume} />);

        expect(screen.getByText('Attached Services')).toBeInTheDocument();
        expect(screen.getByText('postgres-main')).toBeInTheDocument();
        // "postgresql" appears as service type AND within mount path text
        expect(screen.getAllByText(/postgresql/i).length).toBeGreaterThanOrEqual(1);
    });

    it('displays mount path in attached services', () => {
        render(<VolumeShow volume={mockVolume} />);

        // Mount path appears in both attached services badge and sidebar details
        expect(screen.getAllByText('/var/lib/postgresql/data').length).toBeGreaterThanOrEqual(2);
    });

    it('shows empty state when no services attached', () => {
        const volumeNoServices = { ...mockVolume, attached_services: [] };
        render(<VolumeShow volume={volumeNoServices} />);

        expect(screen.getByText(/no services attached to this volume/i)).toBeInTheDocument();
    });

    it('displays snapshots section', () => {
        render(<VolumeShow volume={mockVolume} snapshots={mockSnapshots} />);

        expect(screen.getByText('Snapshots')).toBeInTheDocument();
        expect(screen.getByText('pre-upgrade-backup')).toBeInTheDocument();
        expect(screen.getByText('weekly-backup')).toBeInTheDocument();
    });

    it('shows snapshot details with size and date', () => {
        render(<VolumeShow volume={mockVolume} snapshots={mockSnapshots} />);

        expect(screen.getByText('42 GB')).toBeInTheDocument();
        expect(screen.getByText('40 GB')).toBeInTheDocument();
    });

    it('displays snapshot status badges', () => {
        render(<VolumeShow volume={mockVolume} snapshots={mockSnapshots} />);

        expect(screen.getByText('Completed')).toBeInTheDocument();
        expect(screen.getByText('Creating')).toBeInTheDocument();
    });

    it('shows empty state when no snapshots exist', () => {
        render(<VolumeShow volume={mockVolume} snapshots={[]} />);

        expect(screen.getByText('No snapshots yet')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /create first snapshot/i })).toBeInTheDocument();
    });

    it('displays volume details in sidebar', () => {
        render(<VolumeShow volume={mockVolume} />);

        expect(screen.getByText('Details')).toBeInTheDocument();
        expect(screen.getByText('Volume ID')).toBeInTheDocument();
        expect(screen.getByText('volume-123')).toBeInTheDocument();
        expect(screen.getByText('Mount Path')).toBeInTheDocument();
        expect(screen.getByText('Created')).toBeInTheDocument();
        expect(screen.getByText('Last Updated')).toBeInTheDocument();
    });

    it('shows backup configuration section', () => {
        render(<VolumeShow volume={mockVolume} />);

        expect(screen.getByText('Backup')).toBeInTheDocument();
        const backupLink = screen.getByRole('link', { name: /configure backups/i });
        expect(backupLink).toHaveAttribute('href', '/storage/backups?volume=volume-123');
    });

    it('opens resize modal when resize button is clicked', async () => {
        const { user } = render(<VolumeShow volume={mockVolume} />);

        const resizeButton = screen.getByRole('button', { name: /resize/i });
        await user.click(resizeButton);

        // "Resize Volume" appears as modal title AND modal action button
        expect(screen.getAllByText('Resize Volume').length).toBeGreaterThanOrEqual(1);
        expect(screen.getByText(/increase the volume size/i)).toBeInTheDocument();
    });

    it('shows resize slider in modal', async () => {
        const { user } = render(<VolumeShow volume={mockVolume} />);

        const resizeButton = screen.getByRole('button', { name: /resize/i });
        await user.click(resizeButton);

        expect(screen.getByText('New Size')).toBeInTheDocument();
    });

    it('opens delete modal when delete button is clicked', async () => {
        const { user } = render(<VolumeShow volume={mockVolume} />);

        // The header delete button has only a Trash icon (no text), find it by variant class
        const dangerButtons = screen.getAllByRole('button').filter(
            btn => btn.querySelector('.lucide-trash-2')
        );
        expect(dangerButtons.length).toBeGreaterThanOrEqual(1);
        await user.click(dangerButtons[0]);

        // "Delete Volume" appears as modal title AND modal action button
        expect(screen.getAllByText('Delete Volume').length).toBeGreaterThanOrEqual(1);
        expect(screen.getByText(/this action cannot be undone/i)).toBeInTheDocument();
    });

    it('shows warning information in delete modal', async () => {
        const { user } = render(<VolumeShow volume={mockVolume} snapshots={mockSnapshots} />);

        const dangerButtons = screen.getAllByRole('button').filter(
            btn => btn.querySelector('.lucide-trash-2')
        );
        await user.click(dangerButtons[0]);

        expect(screen.getByText(/warning: this will permanently delete:/i)).toBeInTheDocument();
        expect(screen.getByText(/volume: postgres-data/i)).toBeInTheDocument();
        expect(screen.getByText(/all data: 45 gb/i)).toBeInTheDocument();
        expect(screen.getByText(/all snapshots: 2 snapshots/i)).toBeInTheDocument();
    });

    it('opens snapshot creation modal when create snapshot is clicked', async () => {
        const { user } = render(<VolumeShow volume={mockVolume} />);

        const createButton = screen.getByRole('button', { name: /create snapshot/i });
        await user.click(createButton);

        // "Create Snapshot" appears as modal title, header button, and modal action button
        expect(screen.getAllByText('Create Snapshot').length).toBeGreaterThanOrEqual(2);
        expect(screen.getByText(/create a point-in-time snapshot of this volume/i)).toBeInTheDocument();
    });

    it('shows snapshot name input in creation modal', async () => {
        const { user } = render(<VolumeShow volume={mockVolume} />);

        const createButton = screen.getByRole('button', { name: /create snapshot/i });
        await user.click(createButton);

        expect(screen.getByLabelText(/snapshot name/i)).toBeInTheDocument();
    });

    it('displays view all snapshots link', () => {
        render(<VolumeShow volume={mockVolume} snapshots={mockSnapshots} />);

        const viewAllLink = screen.getByRole('link', { name: /view all/i });
        expect(viewAllLink).toHaveAttribute('href', '/storage/snapshots?volume=volume-123');
    });

    it('shows different storage classes correctly', () => {
        const fastVolume = { ...mockVolume, storage_class: 'fast' as const };
        const { rerender } = render(<VolumeShow volume={fastVolume} />);

        expect(screen.getByText('Fast SSD')).toBeInTheDocument();

        const archiveVolume = { ...mockVolume, storage_class: 'archive' as const };
        rerender(<VolumeShow volume={archiveVolume} />);

        expect(screen.getByText('Archive')).toBeInTheDocument();
    });

    it('handles volume without description', () => {
        const volumeNoDesc = { ...mockVolume, description: undefined };
        render(<VolumeShow volume={volumeNoDesc} />);

        expect(screen.getByText('No description')).toBeInTheDocument();
    });
});
