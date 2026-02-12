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
            created_at: '2024-01-01T12:00:00Z',
        },
        {
            id: 2,
            filename: 'backup-2024-01-02.sql',
            size: '132 MB',
            status: 'completed' as const,
            created_at: '2024-01-02T12:00:00Z',
        },
        {
            id: 3,
            filename: 'backup-2024-01-03.sql',
            size: '0 MB',
            status: 'failed' as const,
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
        // Provide default fetch mock that returns empty array (for scheduled backup fetch)
        global.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ([]),
        });
    });

    describe('rendering', () => {
        it('should render page title and breadcrumbs', () => {
            render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            expect(screen.getByRole('heading', { name: 'Backups' })).toBeInTheDocument();
            expect(screen.getByText(/Manage backups for Test PostgreSQL/)).toBeInTheDocument();
            expect(screen.getByText('Back to Test PostgreSQL')).toBeInTheDocument();
        });

        it('should display create backup button', () => {
            render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            expect(screen.getByText('Create Backup')).toBeInTheDocument();
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
            expect(screen.getByText('Schedule automatic backups to run periodically')).toBeInTheDocument();
        });

        it('should show frequency options when auto backup is enabled', async () => {
            const { user } = render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            const checkbox = screen.getByRole('checkbox');
            await user.click(checkbox);

            expect(screen.getByText('Backup Frequency')).toBeInTheDocument();
            expect(screen.getByText('Hourly')).toBeInTheDocument();
            expect(screen.getByText('Daily')).toBeInTheDocument();
            expect(screen.getByText('Weekly')).toBeInTheDocument();
        });

        it('should show save button when changes are made', async () => {
            const { user } = render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            const checkbox = screen.getByRole('checkbox');
            await user.click(checkbox);

            await waitFor(() => {
                expect(screen.getByText('Unsaved changes')).toBeInTheDocument();
                expect(screen.getByText('Save Schedule')).toBeInTheDocument();
            });
        });

        it('should allow selecting different backup frequencies', async () => {
            const { user } = render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            const checkbox = screen.getByRole('checkbox');
            await user.click(checkbox);

            const weeklyButton = screen.getByText('Weekly');
            await user.click(weeklyButton);

            expect(weeklyButton).toHaveClass('border-primary');
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
            expect(screen.getByText('Create your first backup to get started')).toBeInTheDocument();
        });

        it('should display action buttons for completed backups', () => {
            render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            const downloadButtons = screen.getAllByText('Download');
            const restoreButtons = screen.getAllByText('Restore');

            // Should have 2 completed backups with Download and Restore buttons
            expect(downloadButtons.length).toBeGreaterThanOrEqual(2);
            expect(restoreButtons.length).toBeGreaterThanOrEqual(2);
        });

        it('should not show restore button for failed backups', () => {
            render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            // Only 2 Restore buttons for 2 completed backups (not 3)
            const restoreButtons = screen.getAllByText('Restore');
            expect(restoreButtons).toHaveLength(2);
        });
    });

    describe('backup actions', () => {
        it('should call router.post when create backup is clicked', async () => {
            const { user } = render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            const createButton = screen.getAllByRole('button', { name: 'Create Backup' })[0];
            await user.click(createButton);

            await waitFor(() => {
                expect(router.post).toHaveBeenCalledWith('/databases/test-db-uuid/backups');
            });
        });

        it('should download backup when download button is clicked', async () => {
            const { user } = render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            // Mock window.location.href
            const originalLocation = window.location;
            delete (window as any).location;
            window.location = { ...originalLocation, href: '' } as any;

            const downloadButtons = screen.getAllByText('Download');
            await user.click(downloadButtons[0]);

            expect(window.location.href).toBe('/databases/test-db-uuid/backups/1/download');

            // Restore
            window.location = originalLocation;
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

            const checkbox = screen.getByRole('checkbox');
            expect(checkbox).toBeChecked();
        });

        it('should show backup frequency for scheduled backup', () => {
            render(
                <DatabaseBackups
                    database={mockDatabase}
                    backups={mockBackups}
                    scheduledBackup={mockScheduledBackup}
                />
            );

            expect(screen.getByText(/Backup runs daily/)).toBeInTheDocument();
        });
    });

    describe('API integration', () => {
        it('should fetch scheduled backup on mount if not provided', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => [mockScheduledBackup],
            });

            render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            await waitFor(() => {
                expect(global.fetch).toHaveBeenCalledWith('/api/v1/databases/test-db-uuid/backups');
            });
        });

        it('should save scheduled backup when save is clicked', async () => {
            (global.fetch as any).mockResolvedValueOnce({
                ok: true,
                json: async () => ({ uuid: 'new-uuid' }),
            });

            const { user } = render(<DatabaseBackups database={mockDatabase} backups={mockBackups} />);

            // Enable auto backup
            const checkbox = screen.getByRole('checkbox');
            await user.click(checkbox);

            // Click save
            const saveButton = await screen.findByRole('button', { name: 'Save Schedule' });
            await user.click(saveButton);

            await waitFor(() => {
                expect(global.fetch).toHaveBeenCalledWith(
                    '/api/v1/databases/test-db-uuid/backups',
                    expect.objectContaining({
                        method: 'POST',
                        body: expect.stringContaining('0 0 * * *'), // daily cron expression
                    })
                );
            }, { timeout: 3000 });
        });
    });
});
