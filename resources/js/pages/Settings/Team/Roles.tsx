import * as React from 'react';
import { router } from '@inertiajs/react';

/**
 * Legacy Roles page - redirects to Permission Sets
 *
 * This page exists for backward compatibility.
 * All role management has been moved to Permission Sets.
 */
export default function TeamRoles() {
    React.useEffect(() => {
        router.replace('/settings/team/permission-sets');
    }, []);

    return (
        <div className="flex items-center justify-center min-h-[400px]">
            <p className="text-foreground-muted">Redirecting to Permission Sets...</p>
        </div>
    );
}
