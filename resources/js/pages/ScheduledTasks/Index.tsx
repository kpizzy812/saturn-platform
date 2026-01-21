import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import {
    Card,
    CardContent,
    Button,
    Badge,
    Input,
    Select,
    Modal,
    ModalFooter,
    Textarea,
} from '@/components/ui';
import {
    Plus,
    Clock,
    Calendar,
    Play,
    Trash2,
    MoreVertical,
    CheckCircle,
    XCircle,
    AlertCircle,
    Loader,
    Ban,
    Eye,
} from 'lucide-react';
import type { ScheduledTask } from '@/types';

interface Props {
    tasks?: ScheduledTask[];
}

export default function ScheduledTasksIndex({ tasks = [] }: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<'all' | ScheduledTask['status']>('all');
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [viewTaskId, setViewTaskId] = useState<number | null>(null);

    // Create task form state
    const [taskName, setTaskName] = useState('');
    const [taskDescription, setTaskDescription] = useState('');
    const [taskCommand, setTaskCommand] = useState('');
    const [scheduledDate, setScheduledDate] = useState('');
    const [scheduledTime, setScheduledTime] = useState('');
    const [selectedService, setSelectedService] = useState('');

    // Filter tasks
    const filteredTasks = tasks.filter((task) => {
        const matchesSearch =
            task.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            task.description?.toLowerCase().includes(searchQuery.toLowerCase()) ||
            task.command.toLowerCase().includes(searchQuery.toLowerCase());
        const matchesStatus = statusFilter === 'all' || task.status === statusFilter;
        return matchesSearch && matchesStatus;
    });

    const handleCancelTask = (uuid: string) => {
        if (confirm('Are you sure you want to cancel this task?')) {
            router.post(`/scheduled-tasks/${uuid}/cancel`);
        }
    };

    const handleDeleteTask = (uuid: string) => {
        if (confirm('Are you sure you want to delete this task?')) {
            router.delete(`/scheduled-tasks/${uuid}`);
        }
    };

    const handleCreateTask = (e: React.FormEvent) => {
        e.preventDefault();
        // In real app, this would call an API endpoint
        router.post('/scheduled-tasks', {
            name: taskName,
            description: taskDescription,
            command: taskCommand,
            scheduled_for: `${scheduledDate}T${scheduledTime}`,
            service_id: selectedService,
        });
        setIsCreateModalOpen(false);
        // Reset form
        setTaskName('');
        setTaskDescription('');
        setTaskCommand('');
        setScheduledDate('');
        setScheduledTime('');
        setSelectedService('');
    };

    const getStatusBadge = (status: ScheduledTask['status']) => {
        switch (status) {
            case 'pending':
                return (
                    <Badge variant="default" className="flex items-center gap-1">
                        <Clock className="h-3 w-3" />
                        Pending
                    </Badge>
                );
            case 'running':
                return (
                    <Badge variant="info" className="flex items-center gap-1">
                        <Loader className="h-3 w-3 animate-spin" />
                        Running
                    </Badge>
                );
            case 'completed':
                return (
                    <Badge variant="success" className="flex items-center gap-1">
                        <CheckCircle className="h-3 w-3" />
                        Completed
                    </Badge>
                );
            case 'failed':
                return (
                    <Badge variant="danger" className="flex items-center gap-1">
                        <XCircle className="h-3 w-3" />
                        Failed
                    </Badge>
                );
            case 'cancelled':
                return (
                    <Badge variant="default" className="flex items-center gap-1">
                        <Ban className="h-3 w-3" />
                        Cancelled
                    </Badge>
                );
        }
    };

    const formatScheduledTime = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = date.getTime() - now.getTime();
        const diffMins = Math.floor(diffMs / 60000);

        if (diffMins < 0) return 'Overdue';
        if (diffMins < 60) return `In ${diffMins}m`;
        if (diffMins < 1440) return `In ${Math.floor(diffMins / 60)}h`;
        if (diffMins < 10080) return `In ${Math.floor(diffMins / 1440)}d`;
        return date.toLocaleDateString();
    };

    const viewingTask = tasks.find((t) => t.id === viewTaskId);

    return (
        <AppLayout
            title="Scheduled Tasks"
            breadcrumbs={[{ label: 'Scheduled Tasks' }]}
        >
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Scheduled Tasks</h1>
                    <p className="text-foreground-muted">
                        Schedule one-time tasks to run at specific times
                    </p>
                </div>
                <div className="flex items-center gap-3">
                    <Link href="/scheduled-tasks/history">
                        <Button variant="ghost">
                            <Calendar className="mr-2 h-4 w-4" />
                            View History
                        </Button>
                    </Link>
                    <Button onClick={() => setIsCreateModalOpen(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Schedule Task
                    </Button>
                </div>
            </div>

            {/* Filters */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <Input
                            placeholder="Search tasks..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="md:max-w-xs"
                        />
                        <div className="flex gap-2">
                            <Button
                                variant={statusFilter === 'all' ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => setStatusFilter('all')}
                            >
                                All
                            </Button>
                            <Button
                                variant={statusFilter === 'pending' ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => setStatusFilter('pending')}
                            >
                                Pending
                            </Button>
                            <Button
                                variant={statusFilter === 'running' ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => setStatusFilter('running')}
                            >
                                Running
                            </Button>
                            <Button
                                variant={statusFilter === 'completed' ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => setStatusFilter('completed')}
                            >
                                Completed
                            </Button>
                            <Button
                                variant={statusFilter === 'failed' ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => setStatusFilter('failed')}
                            >
                                Failed
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Tasks List */}
            {filteredTasks.length === 0 ? (
                <EmptyState searchQuery={searchQuery} />
            ) : (
                <div className="space-y-4">
                    {filteredTasks.map((task) => (
                        <Card
                            key={task.id}
                            className="transition-colors hover:border-border/80"
                        >
                            <CardContent className="p-6">
                                <div className="flex items-start justify-between">
                                    <div className="flex-1">
                                        <div className="mb-2 flex items-center gap-3">
                                            <h3 className="text-lg font-semibold text-foreground">
                                                {task.name}
                                            </h3>
                                            {getStatusBadge(task.status)}
                                        </div>
                                        {task.description && (
                                            <p className="mb-3 text-foreground-muted">
                                                {task.description}
                                            </p>
                                        )}
                                        <div className="mb-3">
                                            <code className="block rounded-md bg-background-tertiary px-3 py-2 text-sm text-foreground">
                                                {task.command}
                                            </code>
                                        </div>
                                        <div className="flex flex-wrap gap-4 text-sm">
                                            <div className="flex items-center gap-1.5">
                                                <Clock className="h-4 w-4 text-foreground-muted" />
                                                <span className="text-foreground-muted">
                                                    Scheduled:
                                                </span>
                                                <span className="text-foreground">
                                                    {formatScheduledTime(task.scheduled_for)}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-1.5">
                                                <Calendar className="h-4 w-4 text-foreground-muted" />
                                                <span className="text-foreground">
                                                    {new Date(
                                                        task.scheduled_for
                                                    ).toLocaleString()}
                                                </span>
                                            </div>
                                            {task.executed_at && (
                                                <div className="flex items-center gap-1.5">
                                                    <Play className="h-4 w-4 text-foreground-muted" />
                                                    <span className="text-foreground-muted">
                                                        Started:
                                                    </span>
                                                    <span className="text-foreground">
                                                        {new Date(
                                                            task.executed_at
                                                        ).toLocaleString()}
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                    <div className="ml-4 flex items-center gap-2">
                                        {task.output && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setViewTaskId(task.id)}
                                            >
                                                <Eye className="h-4 w-4" />
                                            </Button>
                                        )}
                                        {task.status === 'pending' && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleCancelTask(task.uuid)}
                                            >
                                                <Ban className="h-4 w-4" />
                                            </Button>
                                        )}
                                        {(task.status === 'completed' ||
                                            task.status === 'failed' ||
                                            task.status === 'cancelled') && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleDeleteTask(task.uuid)}
                                            >
                                                <Trash2 className="h-4 w-4 text-danger" />
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}

            {/* Create Task Modal */}
            <Modal
                isOpen={isCreateModalOpen}
                onClose={() => setIsCreateModalOpen(false)}
                title="Schedule New Task"
                description="Schedule a one-time task to run at a specific time"
                size="lg"
            >
                <form onSubmit={handleCreateTask}>
                    <div className="space-y-4">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground">
                                Task Name
                            </label>
                            <Input
                                placeholder="e.g., Database Migration"
                                value={taskName}
                                onChange={(e) => setTaskName(e.target.value)}
                                required
                            />
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground">
                                Description (optional)
                            </label>
                            <Textarea
                                placeholder="What does this task do?"
                                value={taskDescription}
                                onChange={(e) => setTaskDescription(e.target.value)}
                                rows={2}
                            />
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground">
                                Command
                            </label>
                            <Textarea
                                placeholder="php artisan migrate --force"
                                value={taskCommand}
                                onChange={(e) => setTaskCommand(e.target.value)}
                                required
                                rows={3}
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-foreground">
                                    Date
                                </label>
                                <Input
                                    type="date"
                                    value={scheduledDate}
                                    onChange={(e) => setScheduledDate(e.target.value)}
                                    required
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-foreground">
                                    Time
                                </label>
                                <Input
                                    type="time"
                                    value={scheduledTime}
                                    onChange={(e) => setScheduledTime(e.target.value)}
                                    required
                                />
                            </div>
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground">
                                Service
                            </label>
                            <Select
                                value={selectedService}
                                onChange={(e) => setSelectedService(e.target.value)}
                                required
                            >
                                <option value="">Select a service</option>
                                <option value="prod-api">Production API</option>
                                <option value="staging-api">Staging API</option>
                                <option value="worker">Background Worker</option>
                                <option value="db-postgres">PostgreSQL Database</option>
                            </Select>
                        </div>
                    </div>

                    <ModalFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setIsCreateModalOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit">Schedule Task</Button>
                    </ModalFooter>
                </form>
            </Modal>

            {/* View Task Output Modal */}
            {viewingTask && (
                <Modal
                    isOpen={!!viewTaskId}
                    onClose={() => setViewTaskId(null)}
                    title={viewingTask.name}
                    description="Task output and logs"
                    size="xl"
                >
                    <div className="space-y-4">
                        <div>
                            <h4 className="mb-2 text-sm font-medium text-foreground">Output</h4>
                            <div className="rounded-md bg-background-tertiary p-4">
                                <pre className="text-sm text-foreground">
                                    {viewingTask.output || 'No output yet...'}
                                </pre>
                            </div>
                        </div>
                        {viewingTask.error && (
                            <div>
                                <h4 className="mb-2 text-sm font-medium text-danger">Error</h4>
                                <div className="rounded-md bg-danger/10 p-4">
                                    <pre className="text-sm text-danger">
                                        {viewingTask.error}
                                    </pre>
                                </div>
                            </div>
                        )}
                    </div>
                    <ModalFooter>
                        <Button onClick={() => setViewTaskId(null)}>Close</Button>
                    </ModalFooter>
                </Modal>
            )}
        </AppLayout>
    );
}

function EmptyState({ searchQuery }: { searchQuery: string }) {
    if (searchQuery) {
        return (
            <Card className="p-12 text-center">
                <AlertCircle className="mx-auto h-12 w-12 text-foreground-muted" />
                <h3 className="mt-4 text-lg font-medium text-foreground">No tasks found</h3>
                <p className="mt-2 text-foreground-muted">
                    Try adjusting your search query or filters.
                </p>
            </Card>
        );
    }

    return (
        <Card className="p-12 text-center">
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                <Clock className="h-8 w-8 text-foreground-muted" />
            </div>
            <h3 className="mt-4 text-lg font-medium text-foreground">No scheduled tasks</h3>
            <p className="mt-2 text-foreground-muted">
                Schedule one-time tasks to run at specific times.
            </p>
            <Button className="mt-6">
                <Plus className="mr-2 h-4 w-4" />
                Schedule First Task
            </Button>
        </Card>
    );
}
