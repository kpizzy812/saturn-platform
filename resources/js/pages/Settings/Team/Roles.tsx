import * as React from 'react';
import { router } from '@inertiajs/react';

/**
 * Legacy Roles page - redirects to Roles management
 *
 * This page exists for backward compatibility.
 */
export default function TeamRoles() {
    React.useEffect(() => {
        router.visit('/settings/team/permission-sets', { replace: true });
    }, []);

    return (
        <div className="flex items-center justify-center min-h-[400px]">
            <p className="text-foreground-muted">Redirecting to Roles...</p>
        </div>
    );
}
