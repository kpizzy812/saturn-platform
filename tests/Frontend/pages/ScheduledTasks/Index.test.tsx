import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../utils/test-utils';
import { router } from '@inertiajs/react';

// Import after mock setup
import ScheduledTasksIndex from '@/pages/ScheduledTasks/Index';

const mockTasks = [
    {
        id: 1,
        uuid: 'task-1',
        name: 'Database Migration',
        description: 'Migrate production database schema',
        command: 'php artisan migrate --force',
        scheduled_for: new Date(Date.now() + 3600000).toISOString(), // 1 hour from now
        status: 'pending' as const,
        executed_at: null,
        output: null,
        error: null,
    },
    {
        id: 2,
        uuid: 'task-2',
        name: 'Cache Warmup',
        description: 'Warm up application cache',
        command: 'php artisan cache:warmup',
        scheduled_for: new Date(Date.now() + 7200000).toISOString(), // 2 hours from now
        status: 'running' as const,
        executed_at: new Date().toISOString(),
        output: 'Starting cache warmup...\n',
        error: null,
    },
    {
        id: 3,
        uuid: 'task-3',
        name: 'Data Export',
        description: null,
        command: 'php artisan export:data --format=csv',
        scheduled_for: new Date(Date.now() - 3600000).toISOString(), // 1 hour ago
        status: 'completed' as const,
        executed_at: new Date(Date.now() - 3600000).toISOString(),
        output: 'Export completed successfully\nExported 10000 records',
        error: null,
    },
    {
        id: 4,
        uuid: 'task-4',
        name: 'Failed Task',
        description: 'This task has failed',
        command: 'php artisan broken:command',
        scheduled_for: new Date(Date.now() - 7200000).toISOString(), // 2 hours ago
        status: 'failed' as const,
        executed_at: new Date(Date.now() - 7200000).toISOString(),
        output: 'Starting task...\n',
        error: 'Command not found: broken:command',
    },
    {
        id: 5,
        uuid: 'task-5',
        name: 'Cancelled Task',
        description: 'User cancelled this task',
        command: 'php artisan long:running:task',
        scheduled_for: new Date(Date.now() + 86400000).toISOString(), // 1 day from now
        status: 'cancelled' as const,
        executed_at: null,
        output: null,
        error: null,
    },
];

const mockResources = [
    { id: 1, uuid: 'app-1', name: 'API Server', type: 'application' as const },
    { id: 2, uuid: 'svc-1', name: 'Redis Cache', type: 'service' as const },
    { id: 3, uuid: 'app-2', name: 'Worker', type: 'application' as const },
];

describe('Scheduled Tasks Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('Page Rendering', () => {
        it('renders the page title and description', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            expect(screen.getAllByText('Scheduled Tasks').length).toBeGreaterThan(0);
            expect(
                screen.getByText('Schedule one-time tasks to run at specific times')
            ).toBeInTheDocument();
        });

        it('renders action buttons', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            expect(screen.getByText('View History')).toBeInTheDocument();
            expect(screen.getByText('Schedule Task')).toBeInTheDocument();
        });

        it('renders with empty arrays', () => {
            render(<ScheduledTasksIndex tasks={[]} resources={[]} />);
            expect(screen.getAllByText('Scheduled Tasks').length).toBeGreaterThan(0);
        });
    });

    describe('Filter Functionality', () => {
        it('renders search input', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            expect(screen.getByPlaceholderText('Search tasks...')).toBeInTheDocument();
        });

        it('renders status filter buttons', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            expect(screen.getByRole('button', { name: 'All' })).toBeInTheDocument();
            expect(screen.getByRole('button', { name: 'Pending' })).toBeInTheDocument();
            expect(screen.getByRole('button', { name: 'Running' })).toBeInTheDocument();
            expect(screen.getByRole('button', { name: 'Completed' })).toBeInTheDocument();
            expect(screen.getByRole('button', { name: 'Failed' })).toBeInTheDocument();
        });

        it('filters by search query (name)', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            const searchInput = screen.getByPlaceholderText('Search tasks...');
            fireEvent.change(searchInput, { target: { value: 'migration' } });

            expect(screen.getByText('Database Migration')).toBeInTheDocument();
            expect(screen.queryByText('Cache Warmup')).not.toBeInTheDocument();
        });

        it('filters by search query (command)', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            const searchInput = screen.getByPlaceholderText('Search tasks...');
            fireEvent.change(searchInput, { target: { value: 'cache:warmup' } });

            expect(screen.getByText('Cache Warmup')).toBeInTheDocument();
            expect(screen.queryByText('Database Migration')).not.toBeInTheDocument();
        });

        it('filters by status', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            const pendingButton = screen.getByRole('button', { name: 'Pending' });
            fireEvent.click(pendingButton);

            expect(screen.getByText('Database Migration')).toBeInTheDocument();
            expect(screen.queryByText('Cache Warmup')).not.toBeInTheDocument();
        });

        it('shows completed tasks when filter is active', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            const completedButton = screen.getByRole('button', { name: 'Completed' });
            fireEvent.click(completedButton);

            expect(screen.getByText('Data Export')).toBeInTheDocument();
            expect(screen.queryByText('Database Migration')).not.toBeInTheDocument();
        });
    });

    describe('Task Display', () => {
        it('displays all tasks', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            expect(screen.getByText('Database Migration')).toBeInTheDocument();
            expect(screen.getByText('Cache Warmup')).toBeInTheDocument();
            expect(screen.getByText('Data Export')).toBeInTheDocument();
            expect(screen.getByText('Failed Task')).toBeInTheDocument();
            expect(screen.getByText('Cancelled Task')).toBeInTheDocument();
        });

        it('displays task descriptions', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            expect(screen.getByText('Migrate production database schema')).toBeInTheDocument();
            expect(screen.getByText('Warm up application cache')).toBeInTheDocument();
        });

        it('displays commands', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            expect(screen.getByText('php artisan migrate --force')).toBeInTheDocument();
            expect(screen.getByText('php artisan cache:warmup')).toBeInTheDocument();
        });

        it('displays status badges', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            // Status badges appear both in filter buttons and task cards
            expect(screen.getAllByText('Pending').length).toBeGreaterThan(0);
            expect(screen.getAllByText('Running').length).toBeGreaterThan(0);
            expect(screen.getAllByText('Completed').length).toBeGreaterThan(0);
            expect(screen.getAllByText('Failed').length).toBeGreaterThan(0);
            // Cancelled status badge appears in task card
            expect(screen.getByText('Cancelled')).toBeInTheDocument();
        });

        it('displays scheduled time', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            expect(screen.getAllByText(/Scheduled:/i).length).toBeGreaterThan(0);
        });

        it('displays executed time for running/completed tasks', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            expect(screen.getAllByText(/Started:/i).length).toBeGreaterThan(0);
        });
    });

    describe('Task Actions', () => {
        it('shows view output button for tasks with output', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            const allButtons = screen.getAllByRole('button');
            const viewButtons = allButtons.filter(btn =>
                btn.querySelector('svg')?.classList.contains('lucide-eye')
            );
            expect(viewButtons.length).toBeGreaterThan(0);
        });

        it('shows cancel button for pending tasks', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            const allButtons = screen.getAllByRole('button');
            const cancelButtons = allButtons.filter(btn =>
                btn.querySelector('svg')?.classList.contains('lucide-ban')
            );
            expect(cancelButtons.length).toBeGreaterThan(0);
        });

        it('shows delete button for completed/failed/cancelled tasks', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            const allButtons = screen.getAllByRole('button');
            const deleteButtons = allButtons.filter(btn =>
                btn.querySelector('svg')?.classList.contains('lucide-trash-2')
            );
            expect(deleteButtons.length).toBeGreaterThan(0);
        });

        it('opens output modal when view button is clicked', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            const allButtons = screen.getAllByRole('button');
            const viewButton = allButtons.find(btn =>
                btn.querySelector('svg')?.classList.contains('lucide-eye')
            );

            if (viewButton) {
                fireEvent.click(viewButton);
                expect(screen.getByText('Task output and logs')).toBeInTheDocument();
            }
        });
    });

    describe('Schedule Task Modal', () => {
        it('opens schedule task modal', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            const scheduleButton = screen.getByText('Schedule Task');
            fireEvent.click(scheduleButton);

            expect(screen.getByText('Schedule New Task')).toBeInTheDocument();
            expect(
                screen.getByText('Schedule a one-time task to run at a specific time')
            ).toBeInTheDocument();
        });

        it('renders form fields', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            const scheduleButton = screen.getAllByText('Schedule Task')[0];
            fireEvent.click(scheduleButton);

            expect(screen.getByPlaceholderText('e.g., Database Migration')).toBeInTheDocument();
            expect(screen.getByPlaceholderText('What does this task do?')).toBeInTheDocument();
            expect(screen.getByPlaceholderText('php artisan migrate --force')).toBeInTheDocument();
        });

        it('populates service options', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            const scheduleButton = screen.getByText('Schedule Task');
            fireEvent.click(scheduleButton);

            expect(screen.getByText('API Server (application)')).toBeInTheDocument();
            expect(screen.getByText('Redis Cache (service)')).toBeInTheDocument();
            expect(screen.getByText('Worker (application)')).toBeInTheDocument();
        });

        it('submits the form', () => {
            const { container } = render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            const scheduleButton = screen.getAllByText('Schedule Task')[0];
            fireEvent.click(scheduleButton);

            // Fill form - find inputs by their type attributes
            const nameInput = screen.getByPlaceholderText('e.g., Database Migration');
            const commandInput = screen.getByPlaceholderText('php artisan migrate --force');
            const dateInput = container.querySelector('input[type="date"]');
            const timeInput = container.querySelector('input[type="time"]');
            const serviceSelect = container.querySelector('select');

            fireEvent.change(nameInput, { target: { value: 'New Task' } });
            fireEvent.change(commandInput, { target: { value: 'php artisan test' } });
            if (dateInput) fireEvent.change(dateInput, { target: { value: '2024-02-20' } });
            if (timeInput) fireEvent.change(timeInput, { target: { value: '14:30' } });
            if (serviceSelect) fireEvent.change(serviceSelect, { target: { value: 'app-1' } });

            // Find and submit the form
            const form = container.querySelector('form');
            if (form) {
                fireEvent.submit(form);

                expect(router.post).toHaveBeenCalledWith(
                    '/scheduled-tasks',
                    expect.objectContaining({
                        name: 'New Task',
                        command: 'php artisan test',
                    })
                );
            }
        });

        it('closes modal on cancel', async () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            const scheduleButton = screen.getAllByText('Schedule Task')[0];
            fireEvent.click(scheduleButton);

            // Modal should be open
            expect(screen.getByText('Schedule New Task')).toBeInTheDocument();

            const cancelButton = screen.getByRole('button', { name: /cancel/i });
            fireEvent.click(cancelButton);

            // Modal should close (need to wait for state update)
            await waitFor(() => {
                expect(screen.queryByText('Schedule New Task')).not.toBeInTheDocument();
            });
        });
    });

    describe('View Output Modal', () => {
        it('displays task output', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            const allButtons = screen.getAllByRole('button');
            const viewButton = allButtons.find(btn =>
                btn.querySelector('svg')?.classList.contains('lucide-eye')
            );

            if (viewButton) {
                fireEvent.click(viewButton);
                expect(screen.getByText('Output')).toBeInTheDocument();
            }
        });

        it('displays error if task failed', () => {
            const failedTask = mockTasks.find(t => t.status === 'failed');
            render(<ScheduledTasksIndex tasks={[failedTask!]} resources={mockResources} />);

            const allButtons = screen.getAllByRole('button');
            const viewButton = allButtons.find(btn =>
                btn.querySelector('svg')?.classList.contains('lucide-eye')
            );

            if (viewButton) {
                fireEvent.click(viewButton);
                expect(screen.getByText('Error')).toBeInTheDocument();
                expect(screen.getByText('Command not found: broken:command')).toBeInTheDocument();
            }
        });

        it('closes output modal', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            const allButtons = screen.getAllByRole('button');
            const viewButton = allButtons.find(btn =>
                btn.querySelector('svg')?.classList.contains('lucide-eye')
            );

            if (viewButton) {
                fireEvent.click(viewButton);
                const closeButton = screen.getByRole('button', { name: /close/i });
                fireEvent.click(closeButton);
                expect(screen.queryByText('Task output and logs')).not.toBeInTheDocument();
            }
        });
    });

    describe('Empty State', () => {
        it('displays empty state when no tasks', () => {
            render(<ScheduledTasksIndex tasks={[]} resources={mockResources} />);
            expect(screen.getByText('No scheduled tasks')).toBeInTheDocument();
            expect(
                screen.getByText('Schedule one-time tasks to run at specific times.')
            ).toBeInTheDocument();
        });

        it('displays empty state when search returns no results', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            const searchInput = screen.getByPlaceholderText('Search tasks...');
            fireEvent.change(searchInput, { target: { value: 'nonexistent' } });

            expect(screen.getByText('No tasks found')).toBeInTheDocument();
            expect(
                screen.getByText('Try adjusting your search query or filters.')
            ).toBeInTheDocument();
        });

        it('has schedule button in empty state', () => {
            render(<ScheduledTasksIndex tasks={[]} resources={mockResources} />);
            expect(screen.getByText('Schedule First Task')).toBeInTheDocument();
        });
    });

    describe('Navigation', () => {
        it('links to history page', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            const historyLink = screen.getByText('View History').closest('a');
            expect(historyLink).toHaveAttribute('href', '/scheduled-tasks/history');
        });
    });

    describe('Time Formatting', () => {
        it('formats upcoming tasks correctly', () => {
            render(<ScheduledTasksIndex tasks={mockTasks} resources={mockResources} />);
            // Should show "In Xh" for upcoming tasks
            expect(screen.getAllByText(/In \d+h/).length).toBeGreaterThan(0);
        });

        it('shows overdue for past pending tasks', () => {
            const overdueTask = {
                ...mockTasks[0],
                scheduled_for: new Date(Date.now() - 86400000).toISOString(), // 1 day ago
            };
            render(<ScheduledTasksIndex tasks={[overdueTask]} resources={mockResources} />);
            expect(screen.getByText('Overdue')).toBeInTheDocument();
        });
    });
});
