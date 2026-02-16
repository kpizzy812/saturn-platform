
import { ArrowUpRight } from 'lucide-react';
import { Button } from '@/components/ui';
import type { EnvironmentType } from '@/types';

interface MigrateButtonProps {
    environmentType?: EnvironmentType;
    onMigrate: () => void;
    disabled?: boolean;
    tooltipContent?: string;
}

/**
 * Button to initiate resource migration to the next environment.
 * Only shows for non-production environments.
 */
export function MigrateButton({
    environmentType = 'development',
    onMigrate,
    disabled = false,
}: MigrateButtonProps) {
    // Don't show migrate button for production (final environment)
    if (environmentType === 'production') {
        return null;
    }

    return (
        <Button
            variant="secondary"
            size="sm"
            onClick={onMigrate}
            disabled={disabled}
            title={`Migrate to ${environmentType === 'development' ? 'UAT' : 'Production'}`}
        >
            <ArrowUpRight className="mr-2 h-4 w-4" />
            Migrate
        </Button>
    );
}
