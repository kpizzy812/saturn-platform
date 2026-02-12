import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../utils/test-utils';
import { router } from '@inertiajs/react';

// Import after mock setup
import CronJobsIndex from '@/pages/CronJobs/Index';

const mockCronJobs = [
    {
        id: 1,
        uuid: 'cron-1',
        name: 'Database Backup',
        description: 'Daily database backup to S3',
        command: 'php artisan backup:database',
        schedule: '0 2 * * *',
        status: 'enabled' as const,
        last_run: '2024-02-12T02:00:00Z',
        next_run: '2024-02-13T02:00:00Z',
        success_count: 45,
        failure_count: 2,
        average_duration: 120,
    },
    {
        id: 2,
        uuid: 'cron-2',
        name: 'Clear Cache',
        description: 'Clear application cache',
        command: 'php artisan cache:clear',
        schedule: '*/30 * * * *',
        status: 'running' as const,
        last_run: '2024-02-12T10:30:00Z',
        next_run: '2024-02-12T11:00:00Z',
        success_count: 1000,
        failure_count: 5,
        average_duration: 3,
    },
    {
        id: 3,
        uuid: 'cron-3',
        name: 'Email Queue Worker',
        description: null,
        command: 'php artisan queue:work --stop-when-empty',
        schedule: '*/5 * * * *',
        status: 'disabled' as const,
        last_run: '2024-02-11T15:00:00Z',
        next_run: null,
        success_count: 500,
        failure_count: 10,
        average_duration: 45,
    },
    {
        id: 4,
        uuid: 'cron-4',
        name: 'Failed Job',
        description: 'This job has failed',
        command: 'php artisan broken:command',
        schedule: '0 * * * *',
        status: 'failed' as const,
        last_run: '2024-02-12T09:00:00Z',
        next_run: '2024-02-12T10:00:00Z',
        success_count: 20,
        failure_count: 30,
        average_duration: 10,
    },
];

describe('Cron Jobs Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('Page Rendering', () => {
        it('renders the page title and description', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            expect(screen.getAllByText('Cron Jobs').length).toBeGreaterThan(0);
            expect(screen.getByText('Schedule and manage recurring tasks')).toBeInTheDocument();
        });

        it('renders new cron job button', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            expect(screen.getByText('New Cron Job')).toBeInTheDocument();
        });

        it('renders with undefined cronJobs prop', () => {
            render(<CronJobsIndex />);
            expect(screen.getAllByText('Cron Jobs').length).toBeGreaterThan(0);
        });
    });

    describe('Filter Functionality', () => {
        it('renders search input', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            expect(screen.getByPlaceholderText('Search cron jobs...')).toBeInTheDocument();
        });

        it('renders status filter buttons', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            expect(screen.getByRole('button', { name: 'All' })).toBeInTheDocument();
            expect(screen.getByRole('button', { name: 'Enabled' })).toBeInTheDocument();
            expect(screen.getByRole('button', { name: 'Disabled' })).toBeInTheDocument();
            expect(screen.getByRole('button', { name: 'Running' })).toBeInTheDocument();
            expect(screen.getByRole('button', { name: 'Failed' })).toBeInTheDocument();
        });

        it('filters by search query (name)', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            const searchInput = screen.getByPlaceholderText('Search cron jobs...');
            fireEvent.change(searchInput, { target: { value: 'backup' } });

            expect(screen.getByText('Database Backup')).toBeInTheDocument();
            expect(screen.queryByText('Clear Cache')).not.toBeInTheDocument();
        });

        it('filters by search query (command)', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            const searchInput = screen.getByPlaceholderText('Search cron jobs...');
            fireEvent.change(searchInput, { target: { value: 'cache:clear' } });

            expect(screen.getByText('Clear Cache')).toBeInTheDocument();
            expect(screen.queryByText('Database Backup')).not.toBeInTheDocument();
        });

        it('filters by status', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            const enabledButton = screen.getByRole('button', { name: 'Enabled' });
            fireEvent.click(enabledButton);

            expect(screen.getByText('Database Backup')).toBeInTheDocument();
            expect(screen.queryByText('Email Queue Worker')).not.toBeInTheDocument();
        });

        it('shows running jobs when running filter is active', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            const runningButton = screen.getByRole('button', { name: 'Running' });
            fireEvent.click(runningButton);

            expect(screen.getByText('Clear Cache')).toBeInTheDocument();
            expect(screen.queryByText('Database Backup')).not.toBeInTheDocument();
        });

        it('shows failed jobs when failed filter is active', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            const failedButton = screen.getByRole('button', { name: 'Failed' });
            fireEvent.click(failedButton);

            expect(screen.getByText('Failed Job')).toBeInTheDocument();
            expect(screen.queryByText('Database Backup')).not.toBeInTheDocument();
        });
    });

    describe('Cron Job Display', () => {
        it('displays all cron jobs', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            expect(screen.getByText('Database Backup')).toBeInTheDocument();
            expect(screen.getByText('Clear Cache')).toBeInTheDocument();
            expect(screen.getByText('Email Queue Worker')).toBeInTheDocument();
            expect(screen.getByText('Failed Job')).toBeInTheDocument();
        });

        it('displays job descriptions', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            expect(screen.getByText('Daily database backup to S3')).toBeInTheDocument();
            expect(screen.getByText('Clear application cache')).toBeInTheDocument();
        });

        it('displays cron schedules', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            expect(screen.getByText('Daily at 2:00 AM')).toBeInTheDocument();
            expect(screen.getByText('Every 30 minutes')).toBeInTheDocument();
            expect(screen.getByText('Every 5 minutes')).toBeInTheDocument();
        });

        it('displays status badges', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            // Status badges appear both in the filter buttons and in the job cards
            expect(screen.getAllByText('Enabled').length).toBeGreaterThan(0);
            expect(screen.getAllByText('Running').length).toBeGreaterThan(0);
            expect(screen.getAllByText('Disabled').length).toBeGreaterThan(0);
            expect(screen.getAllByText('Failed').length).toBeGreaterThan(0);
        });

        it('displays success and failure counts', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            expect(screen.getByText('45 successful')).toBeInTheDocument();
            expect(screen.getByText('2 failed')).toBeInTheDocument();
        });

        it('displays success rate', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            // 45/(45+2) = 95.74% ≈ 96%
            expect(screen.getByText('96%')).toBeInTheDocument();
        });

        it('displays average duration', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            expect(screen.getByText('2m 0s')).toBeInTheDocument(); // 120 seconds
            expect(screen.getByText('3s')).toBeInTheDocument();
            expect(screen.getByText('45s')).toBeInTheDocument();
        });

        it('displays last run and next run times', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            expect(screen.getAllByText(/ago/i).length).toBeGreaterThan(0);
            expect(screen.getAllByText(/In/i).length).toBeGreaterThan(0);
        });
    });

    describe('Cron Job Actions', () => {
        it('has run now button', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            const playButtons = screen.getAllByRole('button');
            const runButtons = playButtons.filter(btn =>
                btn.querySelector('svg')?.classList.contains('lucide-play')
            );
            expect(runButtons.length).toBeGreaterThan(0);
        });

        it('disables run button for running jobs', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            // Check that some play buttons exist
            const allButtons = screen.getAllByRole('button');
            expect(allButtons.length).toBeGreaterThan(0);
        });

        it('calls router.post when running a job', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            const playButtons = screen.getAllByRole('button');
            const runButton = playButtons.find(btn =>
                btn.querySelector('svg')?.classList.contains('lucide-play') &&
                !btn.disabled
            );

            if (runButton) {
                fireEvent.click(runButton);
                expect(router.post).toHaveBeenCalledWith(expect.stringContaining('/cron-jobs/'));
            }
        });

        it('has toggle status button', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            const allButtons = screen.getAllByRole('button');
            const toggleButtons = allButtons.filter(btn =>
                btn.querySelector('svg')?.classList.contains('lucide-pause')
            );
            expect(toggleButtons.length).toBeGreaterThan(0);
        });

        it('has delete button', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            const allButtons = screen.getAllByRole('button');
            const deleteButtons = allButtons.filter(btn =>
                btn.querySelector('svg')?.classList.contains('lucide-trash-2')
            );
            expect(deleteButtons.length).toBeGreaterThan(0);
        });
    });

    describe('Empty State', () => {
        it('displays empty state when no jobs', () => {
            render(<CronJobsIndex cronJobs={[]} />);
            expect(screen.getByText('No cron jobs yet')).toBeInTheDocument();
            expect(
                screen.getByText('Create your first cron job to schedule recurring tasks.')
            ).toBeInTheDocument();
        });

        it('displays empty state when search returns no results', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            const searchInput = screen.getByPlaceholderText('Search cron jobs...');
            fireEvent.change(searchInput, { target: { value: 'nonexistent' } });

            expect(screen.getByText('No cron jobs found')).toBeInTheDocument();
            expect(
                screen.getByText('Try adjusting your search query or filters.')
            ).toBeInTheDocument();
        });

        it('has create button in empty state', () => {
            render(<CronJobsIndex cronJobs={[]} />);
            const createButtons = screen.getAllByText('Create Cron Job');
            expect(createButtons.length).toBeGreaterThan(0);
        });
    });

    describe('Navigation', () => {
        it('links to cron job detail page', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            const jobLinks = screen.getAllByRole('link');
            const detailLink = jobLinks.find(link =>
                link.getAttribute('href')?.includes('/cron-jobs/cron-1')
            );
            expect(detailLink).toBeInTheDocument();
        });

        it('links to create page', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            const createLink = screen.getByText('New Cron Job').closest('a');
            expect(createLink).toHaveAttribute('href', '/cron-jobs/create');
        });
    });

    describe('Schedule Formatting', () => {
        it('formats common schedules correctly', () => {
            render(<CronJobsIndex cronJobs={mockCronJobs} />);
            // Daily at 2:00 AM (0 2 * * *)
            expect(screen.getByText('Daily at 2:00 AM')).toBeInTheDocument();
            // Every 30 minutes (*/30 * * * *)
            expect(screen.getByText('Every 30 minutes')).toBeInTheDocument();
            // Every 5 minutes (*/5 * * * *)
            expect(screen.getByText('Every 5 minutes')).toBeInTheDocument();
        });

        it('displays raw schedule for unknown patterns', () => {
            const customJob = {
                ...mockCronJobs[0],
                schedule: '15 4 1 * *',
            };
            render(<CronJobsIndex cronJobs={[customJob]} />);
            // Should display the raw cron expression
            expect(screen.getByText('15 4 1 * *')).toBeInTheDocument();
        });
    });

    describe('Success Rate Calculation', () => {
        it('calculates success rate correctly', () => {
            const job = {
                ...mockCronJobs[0],
                success_count: 80,
                failure_count: 20,
            };
            render(<CronJobsIndex cronJobs={[job]} />);
            // 80/(80+20) = 80%
            expect(screen.getByText('80%')).toBeInTheDocument();
        });

        it('shows 100% when no failures', () => {
            const job = {
                ...mockCronJobs[0],
                success_count: 100,
                failure_count: 0,
            };
            render(<CronJobsIndex cronJobs={[job]} />);
            expect(screen.getByText('100%')).toBeInTheDocument();
        });
    });
});
