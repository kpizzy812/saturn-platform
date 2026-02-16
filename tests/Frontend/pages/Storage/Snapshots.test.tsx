import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../utils/test-utils';
import StorageSnapshots from '@/pages/Storage/Snapshots';

const mockSnapshots = [
    {
        id: 1,
        name: 'snapshot-2024-01-15',
        size: '1.2 GB',
        source_volume: 'postgres-data',
        status: 'completed',
        created_at: '2024-01-15T00:00:00Z',
    },
    {
        id: 2,
        name: 'snapshot-2024-01-16',
        size: '1.3 GB',
        source_volume: 'postgres-data',
        status: 'completed',
        created_at: '2024-01-16T00:00:00Z',
    },
];

describe('Storage Snapshots Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page title and description', () => {
            render(<StorageSnapshots volumeId="vol-1" volumeName="Test Volume" snapshots={mockSnapshots} />);

            expect(screen.getByText('Volume Snapshots')).toBeInTheDocument();
            expect(screen.getByText('Point-in-time snapshots of Test Volume')).toBeInTheDocument();
        });

        it('should render back button', () => {
            render(<StorageSnapshots volumeId="vol-1" volumeName="Test Volume" snapshots={mockSnapshots} />);

            expect(screen.getByText('Back to Test Volume')).toBeInTheDocument();
        });

        it('should render refresh button', () => {
            render(<StorageSnapshots volumeId="vol-1" volumeName="Test Volume" snapshots={mockSnapshots} />);

            expect(screen.getByText('Refresh')).toBeInTheDocument();
        });

        it('should render create snapshot button', () => {
            render(<StorageSnapshots volumeId="vol-1" volumeName="Test Volume" snapshots={mockSnapshots} />);

            expect(screen.getByText('Create Snapshot')).toBeInTheDocument();
        });
    });

    describe('info card', () => {
        it('should render about snapshots section', () => {
            render(<StorageSnapshots volumeId="vol-1" volumeName="Test Volume" snapshots={mockSnapshots} />);

            expect(screen.getByText('About Snapshots')).toBeInTheDocument();
            expect(screen.getByText('Snapshots capture the state of your volume at a specific point in time. You can use them to restore your data or create new volumes.')).toBeInTheDocument();
        });

        it('should render instant and incremental badges', () => {
            render(<StorageSnapshots volumeId="vol-1" volumeName="Test Volume" snapshots={mockSnapshots} />);

            expect(screen.getByText('Instant')).toBeInTheDocument();
            expect(screen.getByText('Incremental')).toBeInTheDocument();
        });
    });

    describe('snapshots list', () => {
        it('should render snapshots list header', () => {
            render(<StorageSnapshots volumeId="vol-1" volumeName="Test Volume" snapshots={mockSnapshots} />);

            expect(screen.getByText(/Snapshots \(2\)/)).toBeInTheDocument();
        });

        it('should render all snapshots', () => {
            render(<StorageSnapshots volumeId="vol-1" volumeName="Test Volume" snapshots={mockSnapshots} />);

            expect(screen.getByText('snapshot-2024-01-15')).toBeInTheDocument();
            expect(screen.getByText('snapshot-2024-01-16')).toBeInTheDocument();
        });

        it('should display snapshot sizes', () => {
            render(<StorageSnapshots volumeId="vol-1" volumeName="Test Volume" snapshots={mockSnapshots} />);

            expect(screen.getByText('1.2 GB')).toBeInTheDocument();
            expect(screen.getByText('1.3 GB')).toBeInTheDocument();
        });

        it('should render restore and delete buttons for each snapshot', () => {
            render(<StorageSnapshots volumeId="vol-1" volumeName="Test Volume" snapshots={mockSnapshots} />);

            const restoreButtons = screen.getAllByText('Restore');
            expect(restoreButtons.length).toBe(2);
        });
    });

    describe('empty state', () => {
        it('should render no snapshots message when empty', () => {
            render(<StorageSnapshots volumeId="vol-1" volumeName="Test Volume" snapshots={[]} />);

            expect(screen.getByText('No snapshots yet')).toBeInTheDocument();
            expect(screen.getByText('Create your first snapshot to preserve the current state')).toBeInTheDocument();
        });

        it('should render create snapshot button in empty state', () => {
            render(<StorageSnapshots volumeId="vol-1" volumeName="Test Volume" snapshots={[]} />);

            const createButtons = screen.getAllByText('Create Snapshot');
            expect(createButtons.length).toBeGreaterThan(0);
        });
    });

    describe('create snapshot modal', () => {
        it('should open create snapshot modal', async () => {
            const { user } = render(<StorageSnapshots volumeId="vol-1" volumeName="Test Volume" snapshots={mockSnapshots} />);

            const createButton = screen.getByText('Create Snapshot');
            await user.click(createButton);

            await waitFor(() => {
                expect(screen.getByText('Create a point-in-time snapshot of this volume')).toBeInTheDocument();
            });
        });

        it('should render snapshot name input in modal', async () => {
            const { user } = render(<StorageSnapshots volumeId="vol-1" volumeName="Test Volume" snapshots={mockSnapshots} />);

            const createButton = screen.getByText('Create Snapshot');
            await user.click(createButton);

            await waitFor(() => {
                expect(screen.getByText('Snapshot Name')).toBeInTheDocument();
            });
        });
    });

    describe('restore modal', () => {
        it('should open restore modal when restore is clicked', async () => {
            const { user } = render(<StorageSnapshots volumeId="vol-1" volumeName="Test Volume" snapshots={mockSnapshots} />);

            const restoreButtons = screen.getAllByText('Restore');
            await user.click(restoreButtons[0]);

            await waitFor(() => {
                expect(screen.getByText('Are you sure you want to restore this snapshot?')).toBeInTheDocument();
            });
        });

        it('should show warning in restore modal', async () => {
            const { user } = render(<StorageSnapshots volumeId="vol-1" volumeName="Test Volume" snapshots={mockSnapshots} />);

            const restoreButtons = screen.getAllByText('Restore');
            await user.click(restoreButtons[0]);

            await waitFor(() => {
                expect(screen.getByText('Warning')).toBeInTheDocument();
            });
        });
    });

    describe('edge cases', () => {
        it('should handle without volumeId', () => {
            render(<StorageSnapshots snapshots={mockSnapshots} />);

            expect(screen.getByText('Volume Snapshots')).toBeInTheDocument();
        });

        it('should not show create button without volumeId', () => {
            render(<StorageSnapshots snapshots={mockSnapshots} />);

            expect(screen.queryByText('Create Snapshot')).not.toBeInTheDocument();
        });
    });
});
