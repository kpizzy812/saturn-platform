import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../utils/test-utils';
import StorageBackups from '@/pages/Storage/Backups';

const mockBackups = [
    {
        id: 1,
        uuid: 'backup-uuid-1',
        filename: 'backup-2024-01-15.sql.gz',
        size: '1.2 GB',
        status: 'completed' as const,
        created_at: '2024-01-15T00:00:00Z',
    },
    {
        id: 2,
        uuid: 'backup-uuid-2',
        filename: 'backup-2024-01-16.sql.gz',
        size: '1.3 GB',
        status: 'in_progress' as const,
        created_at: '2024-01-16T00:00:00Z',
    },
    {
        id: 3,
        uuid: 'backup-uuid-3',
        filename: 'backup-2024-01-17.sql.gz',
        size: '1.1 GB',
        status: 'failed' as const,
        created_at: '2024-01-17T00:00:00Z',
    },
];

describe('Storage Backups Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page title and description', () => {
            render(<StorageBackups volumeId="vol-1" volumeName="Test Volume" backups={mockBackups} />);

            expect(screen.getByText('Storage Backups')).toBeInTheDocument();
            expect(screen.getByText('Manage backups for Test Volume')).toBeInTheDocument();
        });

        it('should render back button', () => {
            render(<StorageBackups volumeId="vol-1" volumeName="Test Volume" backups={mockBackups} />);

            expect(screen.getByText('Back to Test Volume')).toBeInTheDocument();
        });

        it('should render refresh button', () => {
            render(<StorageBackups volumeId="vol-1" volumeName="Test Volume" backups={mockBackups} />);

            expect(screen.getByText('Refresh')).toBeInTheDocument();
        });

        it('should render create backup button', () => {
            render(<StorageBackups volumeId="vol-1" volumeName="Test Volume" backups={mockBackups} />);

            expect(screen.getByText('Create Backup')).toBeInTheDocument();
        });
    });

    describe('backup schedule section', () => {
        it('should render backup schedule configuration', () => {
            render(<StorageBackups volumeId="vol-1" volumeName="Test Volume" backups={mockBackups} />);

            expect(screen.getByText('Backup Schedule')).toBeInTheDocument();
            expect(screen.getByText('Configure automatic backup schedule')).toBeInTheDocument();
        });

        it('should render backup frequency and retention inputs', () => {
            render(<StorageBackups volumeId="vol-1" volumeName="Test Volume" backups={mockBackups} />);

            expect(screen.getByText('Backup Frequency')).toBeInTheDocument();
            expect(screen.getByText('Retention Period (days)')).toBeInTheDocument();
        });

        it('should render save schedule button', () => {
            render(<StorageBackups volumeId="vol-1" volumeName="Test Volume" backups={mockBackups} />);

            expect(screen.getByText('Save Schedule')).toBeInTheDocument();
        });
    });

    describe('retention policy section', () => {
        it('should render retention policy section', () => {
            render(<StorageBackups volumeId="vol-1" volumeName="Test Volume" backups={mockBackups} />);

            expect(screen.getByText('Retention Policy')).toBeInTheDocument();
        });

        it('should display active retention info', () => {
            render(<StorageBackups volumeId="vol-1" volumeName="Test Volume" backups={mockBackups} />);

            expect(screen.getByText('Active')).toBeInTheDocument();
        });
    });

    describe('backups list', () => {
        it('should render backup history header', () => {
            render(<StorageBackups volumeId="vol-1" volumeName="Test Volume" backups={mockBackups} />);

            expect(screen.getByText('Backup History')).toBeInTheDocument();
        });

        it('should render all backups', () => {
            render(<StorageBackups volumeId="vol-1" volumeName="Test Volume" backups={mockBackups} />);

            expect(screen.getByText('backup-2024-01-15.sql.gz')).toBeInTheDocument();
            expect(screen.getByText('backup-2024-01-16.sql.gz')).toBeInTheDocument();
            expect(screen.getByText('backup-2024-01-17.sql.gz')).toBeInTheDocument();
        });

        it('should display backup sizes', () => {
            render(<StorageBackups volumeId="vol-1" volumeName="Test Volume" backups={mockBackups} />);

            expect(screen.getByText('1.2 GB')).toBeInTheDocument();
            expect(screen.getByText('1.3 GB')).toBeInTheDocument();
            expect(screen.getByText('1.1 GB')).toBeInTheDocument();
        });

        it('should render action buttons for completed backups', () => {
            render(<StorageBackups volumeId="vol-1" volumeName="Test Volume" backups={mockBackups} />);

            expect(screen.getByText('Download')).toBeInTheDocument();
            expect(screen.getByText('Restore')).toBeInTheDocument();
        });
    });

    describe('empty state', () => {
        it('should render no backups message when empty', () => {
            render(<StorageBackups volumeId="vol-1" volumeName="Test Volume" backups={[]} />);

            expect(screen.getByText('No backups yet')).toBeInTheDocument();
            expect(screen.getByText('Create your first backup to get started')).toBeInTheDocument();
        });

        it('should render create backup button in empty state', () => {
            render(<StorageBackups volumeId="vol-1" volumeName="Test Volume" backups={[]} />);

            const createButtons = screen.getAllByText('Create Backup');
            expect(createButtons.length).toBeGreaterThan(0);
        });
    });

    describe('restore modal', () => {
        it('should open restore modal when restore is clicked', async () => {
            const { user } = render(<StorageBackups volumeId="vol-1" volumeName="Test Volume" backups={mockBackups} />);

            const restoreButton = screen.getByText('Restore');
            await user.click(restoreButton);

            await waitFor(() => {
                expect(screen.getByText('Are you sure you want to restore this backup?')).toBeInTheDocument();
            });
        });

        it('should show warning in restore modal', async () => {
            const { user } = render(<StorageBackups volumeId="vol-1" volumeName="Test Volume" backups={mockBackups} />);

            const restoreButton = screen.getByText('Restore');
            await user.click(restoreButton);

            await waitFor(() => {
                expect(screen.getByText('Warning')).toBeInTheDocument();
            });
        });
    });

    describe('edge cases', () => {
        it('should handle without volumeId', () => {
            render(<StorageBackups backups={mockBackups} />);

            expect(screen.getByText('Storage Backups')).toBeInTheDocument();
        });

        it('should not show backup schedule without volumeId', () => {
            render(<StorageBackups backups={mockBackups} />);

            expect(screen.queryByText('Backup Schedule')).not.toBeInTheDocument();
        });

        it('should not show retention policy without volumeId', () => {
            render(<StorageBackups backups={mockBackups} />);

            expect(screen.queryByText('Retention Policy')).not.toBeInTheDocument();
        });

        it('should handle backups with name field instead of filename', () => {
            const backupsWithName = [
                {
                    id: 1,
                    name: 'My Backup',
                    size: '1.2 GB',
                    status: 'completed' as const,
                    created_at: '2024-01-15T00:00:00Z',
                },
            ];

            render(<StorageBackups volumeId="vol-1" volumeName="Test Volume" backups={backupsWithName} />);

            expect(screen.getByText('My Backup')).toBeInTheDocument();
        });

        it('should normalize success status to completed', () => {
            const backupsWithSuccess = [
                {
                    id: 1,
                    filename: 'backup-success.sql.gz',
                    size: '1.2 GB',
                    status: 'success' as const,
                    created_at: '2024-01-15T00:00:00Z',
                },
            ];

            render(<StorageBackups volumeId="vol-1" volumeName="Test Volume" backups={backupsWithSuccess} />);

            expect(screen.getByText('backup-success.sql.gz')).toBeInTheDocument();
        });
    });
});
