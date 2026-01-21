import { useState, FormEvent } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Input, Textarea, Select, Checkbox } from '@/components/ui';
import { Clock, ArrowLeft, Info, Play } from 'lucide-react';

const cronPresets = [
    { label: 'Every minute', value: '* * * * *' },
    { label: 'Every 5 minutes', value: '*/5 * * * *' },
    { label: 'Every 15 minutes', value: '*/15 * * * *' },
    { label: 'Every 30 minutes', value: '*/30 * * * *' },
    { label: 'Hourly', value: '0 * * * *' },
    { label: 'Daily at midnight', value: '0 0 * * *' },
    { label: 'Daily at 2:00 AM', value: '0 2 * * *' },
    { label: 'Daily at 9:00 AM', value: '0 9 * * *' },
    { label: 'Weekly (Sunday midnight)', value: '0 0 * * 0' },
    { label: 'Monthly (1st at midnight)', value: '0 0 1 * *' },
];

const timezones = [
    'UTC',
    'America/New_York',
    'America/Chicago',
    'America/Denver',
    'America/Los_Angeles',
    'Europe/London',
    'Europe/Paris',
    'Europe/Berlin',
    'Asia/Tokyo',
    'Asia/Singapore',
    'Australia/Sydney',
];

export default function CronJobCreate() {
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [command, setCommand] = useState('');
    const [schedulePreset, setSchedulePreset] = useState('custom');
    const [customSchedule, setCustomSchedule] = useState('0 * * * *');
    const [timezone, setTimezone] = useState('UTC');
    const [timeout, setTimeout] = useState('3600');
    const [enabled, setEnabled] = useState(true);
    const [notifyOnFailure, setNotifyOnFailure] = useState(true);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    const schedule = schedulePreset === 'custom' ? customSchedule : schedulePreset;

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();

        // Validate
        const newErrors: Record<string, string> = {};

        if (!name.trim()) {
            newErrors.name = 'Name is required';
        }

        if (!command.trim()) {
            newErrors.command = 'Command is required';
        }

        if (schedulePreset === 'custom' && !customSchedule.trim()) {
            newErrors.schedule = 'Schedule is required';
        }

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        setSubmitting(true);
        router.post('/cron-jobs', {
            name,
            description,
            command,
            schedule,
            timezone,
            timeout: parseInt(timeout),
            enabled,
            notify_on_failure: notifyOnFailure,
        }, {
            onError: (errors) => {
                setErrors(errors);
                setSubmitting(false);
            },
            onFinish: () => setSubmitting(false),
        });
    };

    const getCronDescription = (cronExpression: string) => {
        const preset = cronPresets.find(p => p.value === cronExpression);
        return preset ? preset.label : 'Custom schedule';
    };

    return (
        <AppLayout
            title="Create Cron Job"
            breadcrumbs={[
                { label: 'Cron Jobs', href: '/cron-jobs' },
                { label: 'Create' },
            ]}
        >
            {/* Header */}
            <div className="mb-6">
                <Link href="/cron-jobs" className="mb-4 inline-flex items-center gap-2 text-sm text-foreground-muted hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" />
                    Back to Cron Jobs
                </Link>
                <div className="flex items-center gap-4">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-background-tertiary">
                        <Clock className="h-6 w-6 text-primary" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Create Cron Job</h1>
                        <p className="text-foreground-muted">Schedule a new recurring task</p>
                    </div>
                </div>
            </div>

            <form onSubmit={handleSubmit}>
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Main Form */}
                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Basic Information</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <Input
                                    label="Job Name"
                                    placeholder="Database Backup"
                                    value={name}
                                    onChange={(e) => {
                                        setName(e.target.value);
                                        if (errors.name) {
                                            setErrors({ ...errors, name: '' });
                                        }
                                    }}
                                    error={errors.name}
                                    hint="A descriptive name for this cron job"
                                />

                                <div>
                                    <label className="mb-1.5 block text-sm font-medium text-foreground">
                                        Command
                                    </label>
                                    <div className="relative">
                                        <Textarea
                                            value={command}
                                            onChange={(e) => {
                                                setCommand(e.target.value);
                                                if (errors.command) {
                                                    setErrors({ ...errors, command: '' });
                                                }
                                            }}
                                            placeholder="php artisan backup:run --only-db"
                                            rows={3}
                                            className="font-mono text-sm"
                                        />
                                    </div>
                                    {errors.command && (
                                        <p className="mt-1 text-sm text-danger">{errors.command}</p>
                                    )}
                                    <p className="mt-1 text-sm text-foreground-muted">
                                        The command to execute (will be run in the service container)
                                    </p>
                                </div>

                                <Input
                                    label="Description (Optional)"
                                    placeholder="Daily backup of all databases"
                                    value={description}
                                    onChange={(e) => setDescription(e.target.value)}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Schedule</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <label className="mb-1.5 block text-sm font-medium text-foreground">
                                        Schedule Preset
                                    </label>
                                    <div className="grid grid-cols-2 gap-2 md:grid-cols-3">
                                        {cronPresets.map((preset) => (
                                            <button
                                                key={preset.value}
                                                type="button"
                                                onClick={() => setSchedulePreset(preset.value)}
                                                className={`rounded-md border px-3 py-2 text-sm transition-colors text-left ${
                                                    schedulePreset === preset.value
                                                        ? 'border-primary bg-primary/10 text-primary'
                                                        : 'border-border bg-background-secondary text-foreground hover:bg-background-tertiary'
                                                }`}
                                            >
                                                {preset.label}
                                            </button>
                                        ))}
                                        <button
                                            type="button"
                                            onClick={() => setSchedulePreset('custom')}
                                            className={`rounded-md border px-3 py-2 text-sm transition-colors text-left ${
                                                schedulePreset === 'custom'
                                                    ? 'border-primary bg-primary/10 text-primary'
                                                    : 'border-border bg-background-secondary text-foreground hover:bg-background-tertiary'
                                            }`}
                                        >
                                            Custom
                                        </button>
                                    </div>
                                </div>

                                {schedulePreset === 'custom' && (
                                    <Input
                                        label="Cron Expression"
                                        placeholder="0 * * * *"
                                        value={customSchedule}
                                        onChange={(e) => {
                                            setCustomSchedule(e.target.value);
                                            if (errors.schedule) {
                                                setErrors({ ...errors, schedule: '' });
                                            }
                                        }}
                                        error={errors.schedule}
                                        hint="Format: minute hour day month weekday"
                                        className="font-mono"
                                    />
                                )}

                                <div className="rounded-lg border border-border bg-background-secondary p-4">
                                    <div className="flex items-center gap-2 text-sm">
                                        <Clock className="h-4 w-4 text-foreground-muted" />
                                        <span className="font-medium text-foreground">Current schedule:</span>
                                        <code className="px-2 py-0.5 bg-background-tertiary rounded text-foreground text-xs">
                                            {schedule}
                                        </code>
                                        <span className="text-foreground-muted">({getCronDescription(schedule)})</span>
                                    </div>
                                </div>

                                <Select
                                    label="Timezone"
                                    value={timezone}
                                    onChange={(e) => setTimezone(e.target.value)}
                                    options={timezones.map(tz => ({ value: tz, label: tz }))}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Advanced Settings</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <Input
                                    label="Timeout (seconds)"
                                    type="number"
                                    placeholder="3600"
                                    value={timeout}
                                    onChange={(e) => setTimeout(e.target.value)}
                                    hint="Maximum execution time before the job is terminated"
                                    min="1"
                                />

                                <Checkbox
                                    id="enabled"
                                    checked={enabled}
                                    onChange={(e) => setEnabled(e.target.checked)}
                                    label="Enable job immediately"
                                />

                                <Checkbox
                                    id="notify_on_failure"
                                    checked={notifyOnFailure}
                                    onChange={(e) => setNotifyOnFailure(e.target.checked)}
                                    label="Send notifications on failure"
                                />
                            </CardContent>
                        </Card>

                        <div className="flex gap-2">
                            <Button type="submit" loading={submitting}>
                                <Play className="mr-2 h-4 w-4" />
                                Create Cron Job
                            </Button>
                            <Link href="/cron-jobs">
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </Link>
                        </div>
                    </div>

                    {/* Sidebar - Help */}
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Info className="h-5 w-5 text-info" />
                                    Cron Expression Guide
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2 text-sm">
                                    <p className="text-foreground-muted">
                                        Cron expressions consist of 5 fields:
                                    </p>
                                    <div className="space-y-1 font-mono text-xs">
                                        <div className="flex justify-between">
                                            <span className="text-foreground-muted">*</span>
                                            <span className="text-foreground">Minute (0-59)</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-foreground-muted">*</span>
                                            <span className="text-foreground">Hour (0-23)</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-foreground-muted">*</span>
                                            <span className="text-foreground">Day (1-31)</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-foreground-muted">*</span>
                                            <span className="text-foreground">Month (1-12)</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-foreground-muted">*</span>
                                            <span className="text-foreground">Weekday (0-7)</span>
                                        </div>
                                    </div>
                                </div>

                                <div className="border-t border-border pt-4">
                                    <p className="mb-2 text-sm font-medium text-foreground">Special Characters:</p>
                                    <div className="space-y-1 text-xs">
                                        <div className="flex gap-2">
                                            <code className="text-foreground-muted">*</code>
                                            <span className="text-foreground">Any value</span>
                                        </div>
                                        <div className="flex gap-2">
                                            <code className="text-foreground-muted">,</code>
                                            <span className="text-foreground">Value list (1,3,5)</span>
                                        </div>
                                        <div className="flex gap-2">
                                            <code className="text-foreground-muted">-</code>
                                            <span className="text-foreground">Range (1-5)</span>
                                        </div>
                                        <div className="flex gap-2">
                                            <code className="text-foreground-muted">/</code>
                                            <span className="text-foreground">Step (*/5 = every 5)</span>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Common Examples</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3 text-sm">
                                    <div className="rounded-lg border border-border bg-background p-3">
                                        <code className="block text-xs text-foreground font-mono mb-1">
                                            */5 * * * *
                                        </code>
                                        <p className="text-foreground-muted text-xs">Every 5 minutes</p>
                                    </div>
                                    <div className="rounded-lg border border-border bg-background p-3">
                                        <code className="block text-xs text-foreground font-mono mb-1">
                                            0 2 * * *
                                        </code>
                                        <p className="text-foreground-muted text-xs">Daily at 2:00 AM</p>
                                    </div>
                                    <div className="rounded-lg border border-border bg-background p-3">
                                        <code className="block text-xs text-foreground font-mono mb-1">
                                            0 9 * * 1-5
                                        </code>
                                        <p className="text-foreground-muted text-xs">Weekdays at 9:00 AM</p>
                                    </div>
                                    <div className="rounded-lg border border-border bg-background p-3">
                                        <code className="block text-xs text-foreground font-mono mb-1">
                                            0 0 1,15 * *
                                        </code>
                                        <p className="text-foreground-muted text-xs">1st and 15th at midnight</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
