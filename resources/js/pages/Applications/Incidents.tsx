import { AppLayout } from '@/components/layout';
import { IncidentTimeline } from '@/components/features/IncidentTimeline';
import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui';
import { ArrowLeft, ExternalLink } from 'lucide-react';

interface Props {
    application: {
        id: number;
        uuid: string;
        name: string;
        status: string;
    };
    projectUuid: string;
    environmentUuid: string;
}

export default function ApplicationIncidents({ application }: Props) {
    return (
        <AppLayout
            title={`Incidents - ${application.name}`}
            breadcrumbs={[
                { label: 'Applications', href: '/applications' },
                { label: application.name, href: `/applications/${application.uuid}` },
                { label: 'Incidents' },
            ]}
        >
            <div className="mx-auto max-w-5xl">
                {/* Header */}
                <div className="mb-6 flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href={`/applications/${application.uuid}`}>
                            <Button variant="ghost" size="sm">
                                <ArrowLeft className="h-4 w-4 mr-2" />
                                Back
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold text-foreground">Incident Timeline</h1>
                            <p className="text-sm text-foreground-muted mt-1">
                                Monitor events, alerts, and incidents for {application.name}
                            </p>
                        </div>
                    </div>
                    <Link href={`/applications/${application.uuid}/logs`}>
                        <Button variant="outline" size="sm">
                            View Logs
                            <ExternalLink className="h-4 w-4 ml-2" />
                        </Button>
                    </Link>
                </div>

                {/* Incident Timeline Component */}
                <IncidentTimeline applicationUuid={application.uuid} />
            </div>
        </AppLayout>
    );
}
