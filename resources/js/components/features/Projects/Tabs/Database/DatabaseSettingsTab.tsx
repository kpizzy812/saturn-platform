import { Button } from '@/components/ui';
import type { SelectedService } from '../../types';

interface DatabaseSettingsTabProps {
    service: SelectedService;
}

export function DatabaseSettingsTab({ service }: DatabaseSettingsTabProps) {
    return (
        <div className="space-y-6">
            {/* Database Info */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Database</h3>
                <div className="space-y-2">
                    <div className="rounded-lg border border-border bg-background-secondary p-3">
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-foreground-muted">Type</span>
                            <span className="text-sm text-foreground capitalize">{service.dbType}</span>
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-background-secondary p-3">
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-foreground-muted">Version</span>
                            <span className="text-sm text-foreground">15.4</span>
                        </div>
                    </div>
                    <div className="rounded-lg border border-border bg-background-secondary p-3">
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-foreground-muted">Region</span>
                            <span className="text-sm text-foreground">us-east4</span>
                        </div>
                    </div>
                </div>
            </div>

            {/* Storage */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-foreground">Storage</h3>
                <div className="rounded-lg border border-border bg-background-secondary p-3">
                    <div className="flex items-center justify-between mb-2">
                        <span className="text-sm text-foreground-muted">Volume</span>
                        <span className="text-sm text-foreground">postgresql-data</span>
                    </div>
                    <div className="flex items-center justify-between">
                        <span className="text-sm text-foreground-muted">Used</span>
                        <span className="text-sm text-foreground">2.4 GB / 10 GB</span>
                    </div>
                    <div className="mt-2 h-2 rounded-full bg-background">
                        <div className="h-2 w-1/4 rounded-full bg-primary" />
                    </div>
                </div>
            </div>

            {/* Danger Zone */}
            <div>
                <h3 className="mb-3 text-sm font-medium text-red-500">Danger Zone</h3>
                <div className="space-y-2">
                    <Button variant="danger" size="sm">
                        Reset Database
                    </Button>
                    <Button variant="danger" size="sm" className="ml-2">
                        Delete Database
                    </Button>
                </div>
            </div>
        </div>
    );
}
