import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Card, CardContent, Button, Input, Select } from '@/components/ui';
import { ArrowLeft, ChevronRight, Database, Check } from 'lucide-react';
import type { DatabaseType } from '@/types';

interface DatabaseTypeOption {
    type: DatabaseType;
    displayName: string;
    description: string;
    iconBg: string;
    iconColor: string;
    versions: string[];
}

const databaseTypes: DatabaseTypeOption[] = [
    {
        type: 'postgresql',
        displayName: 'PostgreSQL',
        description: 'Advanced open-source relational database',
        iconBg: 'bg-gradient-to-br from-blue-500 to-blue-600',
        iconColor: 'text-white',
        versions: ['16', '15', '14', '13', '12'],
    },
    {
        type: 'mysql',
        displayName: 'MySQL',
        description: 'Popular open-source relational database',
        iconBg: 'bg-gradient-to-br from-orange-500 to-orange-600',
        iconColor: 'text-white',
        versions: ['8.0', '5.7'],
    },
    {
        type: 'mariadb',
        displayName: 'MariaDB',
        description: 'MySQL-compatible relational database',
        iconBg: 'bg-gradient-to-br from-orange-600 to-orange-700',
        iconColor: 'text-white',
        versions: ['11', '10.11', '10.6'],
    },
    {
        type: 'mongodb',
        displayName: 'MongoDB',
        description: 'Document-oriented NoSQL database',
        iconBg: 'bg-gradient-to-br from-green-500 to-green-600',
        iconColor: 'text-white',
        versions: ['7', '6', '5'],
    },
    {
        type: 'redis',
        displayName: 'Redis',
        description: 'In-memory data structure store',
        iconBg: 'bg-gradient-to-br from-red-500 to-red-600',
        iconColor: 'text-white',
        versions: ['7', '6'],
    },
];

type Step = 1 | 2 | 3;

export default function DatabaseCreate() {
    const [step, setStep] = useState<Step>(1);
    const [selectedType, setSelectedType] = useState<DatabaseType | null>(null);
    const [name, setName] = useState('');
    const [version, setVersion] = useState('');
    const [description, setDescription] = useState('');

    const selectedDbType = databaseTypes.find(db => db.type === selectedType);

    const handleTypeSelect = (type: DatabaseType) => {
        setSelectedType(type);
        const dbType = databaseTypes.find(db => db.type === type);
        if (dbType) {
            setVersion(dbType.versions[0]);
        }
        setStep(2);
    };

    const handleSubmit = () => {
        // In a real app, this would submit to the backend
        router.post('/databases', {
            name,
            database_type: selectedType,
            version,
            description,
        });
    };

    return (
        <AppLayout title="Create Database" showNewProject={false}>
            <div className="flex min-h-full items-start justify-center py-12">
                <div className="w-full max-w-2xl px-4">
                    {/* Back link */}
                    <Link
                        href="/databases"
                        className="mb-6 inline-flex items-center text-sm text-foreground-muted transition-colors hover:text-foreground"
                    >
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to Databases
                    </Link>

                    {/* Header */}
                    <div className="mb-8 text-center">
                        <h1 className="text-2xl font-semibold text-foreground">Create a new database</h1>
                        <p className="mt-2 text-foreground-muted">
                            Choose a database type and configure your instance
                        </p>
                    </div>

                    {/* Progress Indicator */}
                    <div className="mb-8 flex items-center justify-center gap-2">
                        <StepIndicator step={1} currentStep={step} label="Type" />
                        <div className="h-px w-12 bg-border" />
                        <StepIndicator step={2} currentStep={step} label="Configure" />
                        <div className="h-px w-12 bg-border" />
                        <StepIndicator step={3} currentStep={step} label="Review" />
                    </div>

                    {/* Step Content */}
                    <div className="space-y-3">
                        {step === 1 && (
                            <div className="space-y-3">
                                {databaseTypes.map((db) => (
                                    <button
                                        key={db.type}
                                        onClick={() => handleTypeSelect(db.type)}
                                        className="group w-full text-left"
                                    >
                                        <Card className="transition-all duration-300 hover:-translate-y-0.5 hover:border-border hover:shadow-xl hover:shadow-black/20">
                                            <CardContent className="flex items-center justify-between p-4">
                                                <div className="flex items-center gap-4">
                                                    <div className={`flex h-11 w-11 items-center justify-center rounded-xl ${db.iconBg} ${db.iconColor} shadow-lg transition-transform duration-300 group-hover:scale-110`}>
                                                        <Database className="h-5 w-5" />
                                                    </div>
                                                    <div>
                                                        <h3 className="font-medium text-foreground transition-colors group-hover:text-white">
                                                            {db.displayName}
                                                        </h3>
                                                        <p className="mt-0.5 text-sm text-foreground-muted">
                                                            {db.description}
                                                        </p>
                                                    </div>
                                                </div>
                                                <ChevronRight className="h-5 w-5 text-foreground-subtle transition-transform duration-300 group-hover:translate-x-1 group-hover:text-foreground-muted" />
                                            </CardContent>
                                        </Card>
                                    </button>
                                ))}
                            </div>
                        )}

                        {step === 2 && selectedDbType && (
                            <Card>
                                <CardContent className="p-6">
                                    <div className="mb-6 flex items-center gap-3">
                                        <div className={`flex h-11 w-11 items-center justify-center rounded-xl ${selectedDbType.iconBg} ${selectedDbType.iconColor} shadow-lg`}>
                                            <Database className="h-5 w-5" />
                                        </div>
                                        <div>
                                            <h3 className="font-medium text-foreground">{selectedDbType.displayName}</h3>
                                            <p className="text-sm text-foreground-muted">{selectedDbType.description}</p>
                                        </div>
                                    </div>

                                    <div className="space-y-4">
                                        <Input
                                            label="Database Name"
                                            placeholder="my-database"
                                            value={name}
                                            onChange={(e) => setName(e.target.value)}
                                            hint="A unique name for your database instance"
                                        />

                                        <Select
                                            label="Version"
                                            value={version}
                                            onChange={(e) => setVersion(e.target.value)}
                                        >
                                            {selectedDbType.versions.map((v) => (
                                                <option key={v} value={v}>
                                                    {v}
                                                </option>
                                            ))}
                                        </Select>

                                        <Input
                                            label="Description (Optional)"
                                            placeholder="Production database for main application"
                                            value={description}
                                            onChange={(e) => setDescription(e.target.value)}
                                        />
                                    </div>

                                    <div className="mt-6 flex gap-3">
                                        <Button variant="secondary" onClick={() => setStep(1)}>
                                            Back
                                        </Button>
                                        <Button
                                            onClick={() => setStep(3)}
                                            disabled={!name || !version}
                                            className="flex-1"
                                        >
                                            Continue
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {step === 3 && selectedDbType && (
                            <Card>
                                <CardContent className="p-6">
                                    <h3 className="mb-4 text-lg font-medium text-foreground">Review Configuration</h3>

                                    <div className="space-y-4">
                                        <div className="flex items-center gap-3 rounded-lg border border-border bg-background-secondary p-4">
                                            <div className={`flex h-11 w-11 items-center justify-center rounded-xl ${selectedDbType.iconBg} ${selectedDbType.iconColor} shadow-lg`}>
                                                <Database className="h-5 w-5" />
                                            </div>
                                            <div className="flex-1">
                                                <p className="font-medium text-foreground">{name}</p>
                                                <p className="text-sm text-foreground-muted">
                                                    {selectedDbType.displayName} {version}
                                                </p>
                                            </div>
                                        </div>

                                        {description && (
                                            <div className="rounded-lg border border-border bg-background-secondary p-4">
                                                <label className="mb-1 block text-sm font-medium text-foreground-muted">
                                                    Description
                                                </label>
                                                <p className="text-sm text-foreground">{description}</p>
                                            </div>
                                        )}

                                        <div className="rounded-lg border border-border bg-background-secondary p-4">
                                            <h4 className="mb-2 font-medium text-foreground">What happens next?</h4>
                                            <ul className="space-y-2 text-sm text-foreground-muted">
                                                <li className="flex items-start gap-2">
                                                    <Check className="mt-0.5 h-4 w-4 text-green-500" />
                                                    <span>Database container will be created and started</span>
                                                </li>
                                                <li className="flex items-start gap-2">
                                                    <Check className="mt-0.5 h-4 w-4 text-green-500" />
                                                    <span>Connection credentials will be generated</span>
                                                </li>
                                                <li className="flex items-start gap-2">
                                                    <Check className="mt-0.5 h-4 w-4 text-green-500" />
                                                    <span>You'll be redirected to the database dashboard</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div className="mt-6 flex gap-3">
                                        <Button variant="secondary" onClick={() => setStep(2)}>
                                            Back
                                        </Button>
                                        <Button onClick={handleSubmit} className="flex-1">
                                            Create Database
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function StepIndicator({ step, currentStep, label }: { step: Step; currentStep: Step; label: string }) {
    const isActive = step === currentStep;
    const isCompleted = step < currentStep;

    return (
        <div className="flex flex-col items-center gap-1">
            <div
                className={`flex h-8 w-8 items-center justify-center rounded-full border-2 transition-colors ${
                    isActive
                        ? 'border-primary bg-primary text-white'
                        : isCompleted
                          ? 'border-primary bg-primary text-white'
                          : 'border-border bg-background text-foreground-muted'
                }`}
            >
                {isCompleted ? <Check className="h-4 w-4" /> : <span className="text-sm">{step}</span>}
            </div>
            <span
                className={`text-xs ${
                    isActive ? 'text-foreground' : isCompleted ? 'text-foreground-muted' : 'text-foreground-subtle'
                }`}
            >
                {label}
            </span>
        </div>
    );
}
