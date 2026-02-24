import { Link, router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import { Button } from '@/components/ui';
import { Plus, Database as DatabaseIcon } from 'lucide-react';
import { StaggerList, StaggerItem, FadeIn } from '@/components/animation';
import { DatabaseCard } from '@/components/features/DatabaseCard';
import { useRealtimeStatus } from '@/hooks/useRealtimeStatus';
import type { StandaloneDatabase } from '@/types';

interface Props {
    databases: StandaloneDatabase[];
}

export default function DatabasesIndex({ databases = [] }: Props) {
    // Real-time status updates via WebSocket
    useRealtimeStatus({
        onDatabaseStatusChange: () => {
            // Reload database list when any database status changes
            router.reload({ only: ['databases'] });
        },
    });
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
                    <Button className="group">
                        <Plus className="mr-2 h-4 w-4 group-hover:animate-wiggle" />
                        New Database
                    </Button>
                </Link>
            </div>

            {/* Databases Grid */}
            {databases.length === 0 ? (
                <EmptyState />
            ) : (
                <StaggerList className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {databases.map((database, i) => (
                        <StaggerItem key={database.id} index={i}>
                            <DatabaseCard database={database} />
                        </StaggerItem>
                    ))}
                </StaggerList>
            )}
            </div>
        </AppLayout>
    );
}

function EmptyState() {
    return (
        <FadeIn>
            <div className="flex flex-col items-center justify-center rounded-xl border border-border/50 bg-background-secondary/30 py-16">
                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary/50">
                    <DatabaseIcon className="h-8 w-8 text-foreground-muted animate-pulse-soft" />
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
        </FadeIn>
    );
}
