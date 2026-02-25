import { usePage } from '@inertiajs/react';

/**
 * Hook to check granular permissions from the PermissionService system.
 *
 * Usage:
 *   const { can } = usePermissions();
 *   if (can('applications.terminal')) { ... }
 *   if (can('databases.credentials')) { ... }
 */
export function usePermissions() {
    const { auth } = usePage().props;

    const can = (permission: string): boolean => {
        return auth?.permissions?.granular?.[permission] ?? false;
    };

    return { can };
}
