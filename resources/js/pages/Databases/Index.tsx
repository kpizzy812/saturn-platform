import { Link } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Button } from '@/components/ui';
import { Plus, Database as DatabaseIcon } from 'lucide-react';
import { DatabaseCard } from '@/components/features/DatabaseCard';
import type { StandaloneDatabase } from '@/types';

interface Props {
    databases: StandaloneDatabase[];
}

export default function DatabasesIndex({ databases = [] }: Props) {
    return (
        <AppLayout
            title="Databases"
            breadcrumbs={[{ label: 'Databases' }]}
        >
            <div className="mx-auto max-w-6xl">
            {/* Header */}
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">Databases</h1>
                    <p className="text-foreground-muted">Manage your database instances</p>
                </div>
                <Link href="/databases/create">
                    <Button>
                        <Plus className="mr-2 h-4 w-4" />
                        New Database
                    </Button>
                </Link>
            </div>

            {/* Databases Grid */}
            {databases.length === 0 ? (
                <EmptyState />
            ) : (
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {databases.map((database) => (
                        <DatabaseCard key={database.id} database={database} />
                    ))}
                </div>
            )}
            </div>
        </AppLayout>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-border/50 bg-background-secondary/30 py-16">
            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary/50">
                <DatabaseIcon className="h-8 w-8 text-foreground-muted" />
            </div>
            <h3 className="mt-4 text-lg font-medium text-foreground">No databases yet</h3>
            <p className="mt-1 text-sm text-foreground-muted">
                Create your first database to get started with PostgreSQL, MySQL, MongoDB, or Redis.
            </p>
            <Link href="/databases/create" className="mt-6">
                <Button>
                    <Plus className="mr-2 h-4 w-4" />
                    Create Database
                </Button>
            </Link>
        </div>
    );
}
