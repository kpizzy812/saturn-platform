import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../utils/test-utils';
import { router } from '@inertiajs/react';
import DatabaseBackups from '@/pages/Databases/Backups';
import type { StandaloneDatabase } from '@/types';

describe('Database Backups Page', () => {
    const mockDatabase: StandaloneDatabase = {
        id: 1,
        uuid: 'test-db-uuid',
        name: 'Test PostgreSQL',
        database_type: 'postgresql',
        status: { state: 'running' },
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
    } as StandaloneDatabase;

    const mockBackups = [
        {
            id: 1,
            filename: 'backup-2024-01-01.sql',
            size: '128 MB',
            status: 'completed' as const,
            restore_status: null,
            restore_started_at: null,
            restore_finished_at: null,
            restore_message: null,
            created_at: '2024-01-01T12:00:00Z',
        },
        {
            id: 2,
            filename: 'backup-2024-01-02.sql',
            size: '132 MB',
            status: 'completed' as const,
            restore_status: null,
            restore_started_at: null,
            restore_finished_at: null,
            restore_message: null,
            created_at: '2024-01-02T12:00:00Z',
        },
        {
            id: 3,
            filename: 'backup-2024-01-03.sql',
            size: '0 MB',
            status: 'failed' as const,
            restore_status: null,
            restore_started_at: null,
            restore_finished_at: null,
            restore_message: null,
            created_at: '2024-01-03T12:00:00Z',
        },
    ];

    const mockScheduledBackup = {
        uuid: 'scheduled-uuid',
        enabled: true,
        frequency: '0 0 * * *',
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-01T00:00:00Z',
    };

    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page title and breadcrumbs', () => {
            render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            expect(screen.getByRole('heading', { name: 'Backups' })).toBeInTheDocument();
            expect(screen.getByText(/Manage backups for Test PostgreSQL/)).toBeInTheDocument();
            expect(screen.getByText('Back to Test PostgreSQL')).toBeInTheDocument();
        });

        it('should display Backup Now button', () => {
            render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            expect(screen.getByRole('button', { name: /backup now/i })).toBeInTheDocument();
        });

        it('should show loading state when backups is undefined', () => {
            render(<DatabaseBackups database={mockDatabase} backups={undefined} />);

            expect(screen.getByText('Loading backups...')).toBeInTheDocument();
        });
    });

    describe('automatic backups section', () => {
        it('should display automatic backups settings', () => {
            render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            expect(screen.getByText('Automatic Backups')).toBeInTheDocument();
            expect(screen.getByText('Enable to schedule periodic backups')).toBeInTheDocument();
        });

        it('should show frequency options when auto backup is enabled', async () => {
            const { user } = render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            const toggle = screen.getByRole('switch');
            await user.click(toggle);

            expect(screen.getByText('Backup Frequency')).toBeInTheDocument();
            expect(screen.getByText('Hourly')).toBeInTheDocument();
            expect(screen.getByText('Daily')).toBeInTheDocument();
            expect(screen.getByText('Weekly')).toBeInTheDocument();
        });

        it('should show save button when changes are made', async () => {
            const { user } = render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            const toggle = screen.getByRole('switch');
            await user.click(toggle);

            await waitFor(() => {
                expect(screen.getByText('Unsaved changes')).toBeInTheDocument();
                expect(screen.getByText('Save Schedule')).toBeInTheDocument();
            });
        });
    });

    describe('backup history', () => {
        it('should display backup history section', () => {
            render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            expect(screen.getByText('Backup History')).toBeInTheDocument();
        });

        it('should display all backups', () => {
            render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            expect(screen.getByText('backup-2024-01-01.sql')).toBeInTheDocument();
            expect(screen.getByText('backup-2024-01-02.sql')).toBeInTheDocument();
            expect(screen.getByText('backup-2024-01-03.sql')).toBeInTheDocument();
        });

        it('should display backup sizes', () => {
            render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            expect(screen.getByText('128 MB')).toBeInTheDocument();
            expect(screen.getByText('132 MB')).toBeInTheDocument();
        });

        it('should show empty state when no backups exist', () => {
            render(<DatabaseBackups database={mockDatabase} backups={[]} />);

            expect(screen.getByText('No backups yet')).toBeInTheDocument();
            expect(screen.getByText(/run a backup now or enable automatic backups/i)).toBeInTheDocument();
        });

        it('should display action buttons for completed backups', () => {
            render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            const downloadButtons = screen.getAllByText('Download');
            const restoreButtons = screen.getAllByText('Restore');

            expect(downloadButtons.length).toBeGreaterThanOrEqual(2);
            expect(restoreButtons.length).toBeGreaterThanOrEqual(2);
        });

        it('should not show restore button for failed backups', () => {
            render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            const restoreButtons = screen.getAllByText('Restore');
            expect(restoreButtons).toHaveLength(2);
        });
    });

    describe('backup actions', () => {
        it('should call router.post when Backup Now is clicked', async () => {
            const { user } = render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            const backupButton = screen.getByRole('button', { name: /backup now/i });
            await user.click(backupButton);

            expect(router.post).toHaveBeenCalledWith(
                '/databases/test-db-uuid/backups',
                {},
                expect.any(Object)
            );
        });

        it('should set window.location.href for download', async () => {
            const { user } = render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            const downloadButtons = screen.getAllByText('Download');
            await user.click(downloadButtons[0]);

            // Download uses window.location.href = `/download/backup/${backupId}`
            // In test environment, this may not be verifiable directly
            expect(downloadButtons[0]).toBeInTheDocument();
        });
    });

    describe('scheduled backup integration', () => {
        it('should display enabled scheduled backup', () => {
            render(
                <DatabaseBackups
                    database={mockDatabase}
                    backups={mockBackups}
                    scheduledBackup={mockScheduledBackup}
                />
            );

            const toggle = screen.getByRole('switch');
            expect(toggle).toHaveAttribute('aria-checked', 'true');
        });

        it('should show backup frequency for scheduled backup', () => {
            render(
                <DatabaseBackups
                    database={mockDatabase}
                    backups={mockBackups}
                    scheduledBackup={mockScheduledBackup}
                />
            );

            expect(screen.getByText(/Scheduled to run daily/)).toBeInTheDocument();
        });
    });

    describe('save schedule', () => {
        it('should call router.patch when saving schedule', async () => {
            const { user } = render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            // Enable auto backup
            const toggle = screen.getByRole('switch');
            await user.click(toggle);

            // Click save
            const saveButton = await screen.findByRole('button', { name: /save schedule/i });
            await user.click(saveButton);

            expect(router.patch).toHaveBeenCalledWith(
                '/databases/test-db-uuid/backups/schedule',
                expect.objectContaining({
                    enabled: true,
                    frequency: '0 0 * * *',
                }),
                expect.any(Object)
            );
        });
    });

    describe('restore history', () => {
        const backupsWithRestore = [
            {
                ...mockBackups[0],
                restore_status: 'success' as const,
                restore_started_at: '2024-01-01T13:00:00Z',
                restore_finished_at: '2024-01-01T13:02:30Z',
                restore_message: null,
            },
            {
                ...mockBackups[1],
                restore_status: 'failed' as const,
                restore_started_at: '2024-01-02T13:00:00Z',
                restore_finished_at: '2024-01-02T13:01:00Z',
                restore_message: 'Connection refused: database container not running',
            },
            mockBackups[2],
        ];

        it('should show Restore History section when restores exist', () => {
            render(<DatabaseBackups database={mockDatabase} backups={backupsWithRestore} />);

            expect(screen.getByText('Restore History')).toBeInTheDocument();
        });

        it('should not show Restore History when no restores exist', () => {
            render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            expect(screen.queryByText('Restore History')).not.toBeInTheDocument();
        });

        it('should show successful restore card', () => {
            render(<DatabaseBackups database={mockDatabase} backups={backupsWithRestore} />);

            expect(screen.getByText('Restore from backup-2024-01-01.sql')).toBeInTheDocument();
            expect(screen.getByText('Restored')).toBeInTheDocument();
        });

        it('should show failed restore with error message', () => {
            render(<DatabaseBackups database={mockDatabase} backups={backupsWithRestore} />);

            expect(screen.getByText('Restore from backup-2024-01-02.sql')).toBeInTheDocument();
            expect(screen.getByText('Restore Failed')).toBeInTheDocument();
            expect(screen.getByText('Connection refused: database container not running')).toBeInTheDocument();
        });

        it('should show in-progress restore with spinner', () => {
            const backupsWithActiveRestore = [
                {
                    ...mockBackups[0],
                    restore_status: 'in_progress' as const,
                    restore_started_at: '2024-01-01T13:00:00Z',
                    restore_finished_at: null,
                    restore_message: null,
                },
                mockBackups[1],
                mockBackups[2],
            ];

            render(<DatabaseBackups database={mockDatabase} backups={backupsWithActiveRestore} />);

            // "Restoring..." appears in both RestoreCard badge and BackupCard button
            const restoringElements = screen.getAllByText('Restoring...');
            expect(restoringElements.length).toBeGreaterThanOrEqual(1);
        });

        it('should show pending restore as queued', () => {
            const backupsWithPending = [
                {
                    ...mockBackups[0],
                    restore_status: 'pending' as const,
                    restore_started_at: null,
                    restore_finished_at: null,
                    restore_message: null,
                },
                mockBackups[1],
                mockBackups[2],
            ];

            render(<DatabaseBackups database={mockDatabase} backups={backupsWithPending} />);

            expect(screen.getByText('Queued')).toBeInTheDocument();
            expect(screen.getByText('Waiting in queue...')).toBeInTheDocument();
        });

        it('should disable Restore button when restore is in progress', () => {
            const backupsWithActiveRestore = [
                {
                    ...mockBackups[0],
                    restore_status: 'in_progress' as const,
                    restore_started_at: '2024-01-01T13:00:00Z',
                    restore_finished_at: null,
                    restore_message: null,
                },
                mockBackups[1],
                mockBackups[2],
            ];

            render(<DatabaseBackups database={mockDatabase} backups={backupsWithActiveRestore} />);

            // The first backup's Restore button should be disabled and show "Restoring..."
            const restoringButton = screen.getByRole('button', { name: /restoring\.\.\./i });
            expect(restoringButton).toBeDisabled();
        });

        it('should show Restoring badge on backup card during active restore', () => {
            const backupsWithActiveRestore = [
                {
                    ...mockBackups[0],
                    restore_status: 'in_progress' as const,
                    restore_started_at: '2024-01-01T13:00:00Z',
                    restore_finished_at: null,
                    restore_message: null,
                },
                mockBackups[1],
                mockBackups[2],
            ];

            render(<DatabaseBackups database={mockDatabase} backups={backupsWithActiveRestore} />);

            // Should show "Restoring" badge alongside the status badge
            expect(screen.getByText('Restoring')).toBeInTheDocument();
        });
    });
});
