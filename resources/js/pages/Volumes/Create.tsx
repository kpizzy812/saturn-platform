import { useState, FormEvent } from 'react';
import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Button, Input, Select } from '@/components/ui';
import { HardDrive, ArrowLeft, Info, AlertCircle, Check } from 'lucide-react';

const volumeSizes = [
    { value: '1', label: '1 GB', pricePerMonth: 0.10 },
    { value: '5', label: '5 GB', pricePerMonth: 0.50 },
    { value: '10', label: '10 GB', pricePerMonth: 1.00 },
    { value: '20', label: '20 GB', pricePerMonth: 2.00 },
    { value: '50', label: '50 GB', pricePerMonth: 5.00 },
    { value: '100', label: '100 GB', pricePerMonth: 10.00 },
];

interface Service {
    uuid: string;
    name: string;
    type: string;
}

interface Props {
    services?: Service[];
}

export default function VolumeCreate({ services = [] }: Props) {
    const [name, setName] = useState('');
    const [size, setSize] = useState('10');
    const [mountPath, setMountPath] = useState('/data');
    const [serviceId, setServiceId] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    const selectedSize = volumeSizes.find(s => s.value === size);

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();

        // Validate
        const newErrors: Record<string, string> = {};

        if (!name.trim()) {
            newErrors.name = 'Volume name is required';
        } else if (!/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/.test(name)) {
            newErrors.name = 'Name must be lowercase alphanumeric with hyphens';
        }

        if (!mountPath.trim()) {
            newErrors.mountPath = 'Mount path is required';
        } else if (!mountPath.startsWith('/')) {
            newErrors.mountPath = 'Mount path must start with /';
        }

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        setSubmitting(true);
        router.post('/volumes', {
            name,
            size: parseInt(size),
            mount_path: mountPath,
            service_id: serviceId || null,
        }, {
            onError: (errors) => {
                setErrors(errors);
                setSubmitting(false);
            },
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <AppLayout
            title="Create Volume"
            breadcrumbs={[
                { label: 'Volumes', href: '/volumes' },
                { label: 'Create' },
            ]}
        >
            {/* Header */}
            <div className="mb-6">
                <Link href="/volumes" className="mb-4 inline-flex items-center gap-2 text-sm text-foreground-muted hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" />
                    Back to Volumes
                </Link>
                <div className="flex items-center gap-4">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-background-tertiary">
                        <HardDrive className="h-6 w-6 text-primary" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">Create Volume</h1>
                        <p className="text-foreground-muted">Add persistent storage for your services</p>
                    </div>
                </div>
            </div>

            <form onSubmit={handleSubmit}>
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Main Form */}
                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Volume Configuration</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <Input
                                    label="Volume Name"
                                    placeholder="my-volume"
                                    value={name}
                                    onChange={(e) => {
                                        setName(e.target.value.toLowerCase());
                                        if (errors.name) {
                                            setErrors({ ...errors, name: '' });
                                        }
                                    }}
                                    error={errors.name}
                                    hint="Lowercase alphanumeric with hyphens only"
                                />

                                <Input
                                    label="Mount Path"
                                    placeholder="/data"
                                    value={mountPath}
                                    onChange={(e) => {
                                        setMountPath(e.target.value);
                                        if (errors.mountPath) {
                                            setErrors({ ...errors, mountPath: '' });
                                        }
                                    }}
                                    error={errors.mountPath}
                                    hint="The path where this volume will be mounted in the container"
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Size Selection</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-3 md:grid-cols-3">
                                    {volumeSizes.map((sizeOption) => (
                                        <button
                                            key={sizeOption.value}
                                            type="button"
                                            onClick={() => setSize(sizeOption.value)}
                                            className={`rounded-lg border p-4 text-left transition-all ${
                                                size === sizeOption.value
                                                    ? 'border-primary bg-primary/10 shadow-sm shadow-primary/20'
                                                    : 'border-border bg-background-secondary hover:bg-background-tertiary'
                                            }`}
                                        >
                                            <div className="flex items-center justify-between mb-2">
                                                <span className="text-lg font-semibold text-foreground">
                                                    {sizeOption.label}
                                                </span>
                                                {size === sizeOption.value && (
                                                    <Check className="h-5 w-5 text-primary" />
                                                )}
                                            </div>
                                            <p className="text-sm text-foreground-muted">
                                                ${sizeOption.pricePerMonth.toFixed(2)}/month
                                            </p>
                                        </button>
                                    ))}
                                </div>

                                {selectedSize && (
                                    <div className="mt-4 rounded-lg border border-border bg-background-secondary p-4">
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-foreground-muted">Selected size:</span>
                                            <span className="font-medium text-foreground">{selectedSize.label}</span>
                                        </div>
                                        <div className="mt-2 flex items-center justify-between text-sm">
                                            <span className="text-foreground-muted">Estimated cost:</span>
                                            <span className="font-medium text-foreground">
                                                ${selectedSize.pricePerMonth.toFixed(2)}/month
                                            </span>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Service Attachment (Optional)</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <Select
                                    label="Attach to Service"
                                    value={serviceId}
                                    onChange={(e) => setServiceId(e.target.value)}
                                    options={[
                                        { value: '', label: 'None - I\'ll attach it later' },
                                        ...services.map(svc => ({
                                            value: svc.uuid,
                                            label: `${svc.name} (${svc.type})`
                                        }))
                                    ]}
                                />

                                <div className="rounded-md bg-info/10 p-3 text-sm text-info">
                                    <p>
                                        You can attach this volume to a service now or later from the volume details page.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <div className="flex gap-2">
                            <Button type="submit" loading={submitting}>
                                Create Volume
                            </Button>
                            <Link href="/volumes">
                                <Button type="button" variant="secondary">
                                    Cancel
                                </Button>
                            </Link>
                        </div>
                    </div>

                    {/* Sidebar - Info */}
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Info className="h-5 w-5 text-info" />
                                    About Volumes
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-3 text-sm text-foreground-muted">
                                    <p>
                                        Volumes provide persistent storage for your services that survives container restarts and redeployments.
                                    </p>
                                    <div className="rounded-lg border border-border bg-background p-3">
                                        <h4 className="mb-2 font-medium text-foreground">Key Features:</h4>
                                        <ul className="space-y-1 text-xs">
                                            <li className="flex items-start gap-2">
                                                <Check className="mt-0.5 h-3 w-3 flex-shrink-0 text-primary" />
                                                <span>Data persists across deployments</span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <Check className="mt-0.5 h-3 w-3 flex-shrink-0 text-primary" />
                                                <span>Automatic backups available</span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <Check className="mt-0.5 h-3 w-3 flex-shrink-0 text-primary" />
                                                <span>Can be shared across services</span>
                                            </li>
                                            <li className="flex items-start gap-2">
                                                <Check className="mt-0.5 h-3 w-3 flex-shrink-0 text-primary" />
                                                <span>Resize anytime without downtime</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Common Use Cases</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3 text-sm">
                                    <div className="rounded-lg border border-border bg-background p-3">
                                        <h4 className="mb-1 font-medium text-foreground">Database Storage</h4>
                                        <p className="text-xs text-foreground-muted">
                                            Store database files with persistent storage
                                        </p>
                                        <code className="mt-2 block rounded bg-background-tertiary px-2 py-1 text-xs text-foreground">
                                            /var/lib/postgresql/data
                                        </code>
                                    </div>
                                    <div className="rounded-lg border border-border bg-background p-3">
                                        <h4 className="mb-1 font-medium text-foreground">File Uploads</h4>
                                        <p className="text-xs text-foreground-muted">
                                            Store user-uploaded files and media
                                        </p>
                                        <code className="mt-2 block rounded bg-background-tertiary px-2 py-1 text-xs text-foreground">
                                            /app/storage/uploads
                                        </code>
                                    </div>
                                    <div className="rounded-lg border border-border bg-background p-3">
                                        <h4 className="mb-1 font-medium text-foreground">Application Logs</h4>
                                        <p className="text-xs text-foreground-muted">
                                            Persist logs across deployments
                                        </p>
                                        <code className="mt-2 block rounded bg-background-tertiary px-2 py-1 text-xs text-foreground">
                                            /var/log/app
                                        </code>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-warning">
                                    <AlertCircle className="h-5 w-5" />
                                    Important Notes
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul className="space-y-2 text-sm text-foreground-muted">
                                    <li className="flex gap-2">
                                        <span className="text-foreground">•</span>
                                        Volumes are billed hourly based on size
                                    </li>
                                    <li className="flex gap-2">
                                        <span className="text-foreground">•</span>
                                        Data is not automatically backed up
                                    </li>
                                    <li className="flex gap-2">
                                        <span className="text-foreground">•</span>
                                        Mount paths must be absolute
                                    </li>
                                    <li className="flex gap-2">
                                        <span className="text-foreground">•</span>
                                        Deleting a volume is permanent
                                    </li>
                                </ul>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
