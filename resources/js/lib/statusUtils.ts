import * as React from 'react';
import {
    CheckCircle,
    XCircle,
    AlertCircle,
    AlertTriangle,
    Clock,
    Loader2,
    RefreshCw,
    Ban,
    ShieldCheck,
    ShieldAlert,
    type LucideIcon,
} from 'lucide-react';
import { cn } from './utils';

// === TYPE DEFINITIONS ===

// Badge variant types (matching Badge.tsx)
export type BadgeVariant = 'default' | 'primary' | 'success' | 'danger' | 'warning' | 'info';

// Icon names for type-safety
export type StatusIconName =
    | 'CheckCircle'
    | 'XCircle'
    | 'AlertCircle'
    | 'AlertTriangle'
    | 'Clock'
    | 'Loader2'
    | 'RefreshCw'
    | 'Ban'
    | 'ShieldCheck'
    | 'ShieldAlert';

// Options for icon customization
export interface IconOptions {
    size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
    animate?: boolean;
    className?: string;
}

// Status configuration
export interface StatusConfig {
    label: string;
    variant: BadgeVariant;
    iconName: StatusIconName;
    colorClass: string;       // bg-* class for dots/indicators
    textColorClass: string;   // text-* class for icons/text
    animate?: boolean;        // pulse animation for in-progress states
    dotClass?: string;        // CSS class for StatusBadge dot
}

// === ICON MAP ===

const iconMap: Record<StatusIconName, LucideIcon> = {
    CheckCircle,
    XCircle,
    AlertCircle,
    AlertTriangle,
    Clock,
    Loader2,
    RefreshCw,
    Ban,
    ShieldCheck,
    ShieldAlert,
};

// === SIZE MAPPINGS ===

const iconSizes: Record<NonNullable<IconOptions['size']>, string> = {
    xs: 'h-3 w-3',
    sm: 'h-4 w-4',
    md: 'h-5 w-5',
    lg: 'h-6 w-6',
    xl: 'h-8 w-8',
};

// === MASTER STATUS REGISTRY ===

const statusRegistry: Record<string, StatusConfig> = {
    // Deployment statuses
    finished: {
        label: 'Finished',
        variant: 'success',
        iconName: 'CheckCircle',
        colorClass: 'bg-green-500',
        textColorClass: 'text-primary',
        dotClass: 'status-online',
    },
    in_progress: {
        label: 'In Progress',
        variant: 'warning',
        iconName: 'AlertCircle',
        colorClass: 'bg-yellow-500',
        textColorClass: 'text-warning',
        animate: true,
        dotClass: 'status-deploying',
    },
    queued: {
        label: 'Queued',
        variant: 'info',
        iconName: 'Clock',
        colorClass: 'bg-blue-500',
        textColorClass: 'text-info',
        dotClass: 'status-initializing',
    },
    failed: {
        label: 'Failed',
        variant: 'danger',
        iconName: 'XCircle',
        colorClass: 'bg-red-500',
        textColorClass: 'text-danger',
        dotClass: 'status-error',
    },
    cancelled: {
        label: 'Cancelled',
        variant: 'default',
        iconName: 'XCircle',
        colorClass: 'bg-gray-500',
        textColorClass: 'text-foreground-muted',
        dotClass: 'status-stopped',
    },

    // Application statuses
    running: {
        label: 'Running',
        variant: 'success',
        iconName: 'CheckCircle',
        colorClass: 'bg-green-500',
        textColorClass: 'text-primary',
        dotClass: 'status-online',
    },
    stopped: {
        label: 'Stopped',
        variant: 'default',
        iconName: 'Clock',
        colorClass: 'bg-gray-500',
        textColorClass: 'text-foreground-muted',
        dotClass: 'status-stopped',
    },
    building: {
        label: 'Building',
        variant: 'warning',
        iconName: 'Loader2',
        colorClass: 'bg-yellow-500',
        textColorClass: 'text-warning',
        animate: true,
        dotClass: 'status-deploying',
    },
    deploying: {
        label: 'Deploying',
        variant: 'warning',
        iconName: 'RefreshCw',
        colorClass: 'bg-yellow-500',
        textColorClass: 'text-warning',
        animate: true,
        dotClass: 'status-deploying',
    },
    exited: {
        label: 'Exited',
        variant: 'danger',
        iconName: 'XCircle',
        colorClass: 'bg-red-500',
        textColorClass: 'text-danger',
        dotClass: 'status-error',
    },

    // Preview statuses
    deleting: {
        label: 'Deleting',
        variant: 'warning',
        iconName: 'Loader2',
        colorClass: 'bg-orange-500',
        textColorClass: 'text-warning',
        animate: true,
    },

    // Resource statuses
    online: {
        label: 'Online',
        variant: 'success',
        iconName: 'CheckCircle',
        colorClass: 'bg-green-500',
        textColorClass: 'text-primary',
        dotClass: 'status-online',
    },
    offline: {
        label: 'Offline',
        variant: 'default',
        iconName: 'XCircle',
        colorClass: 'bg-gray-500',
        textColorClass: 'text-foreground-muted',
        dotClass: 'status-stopped',
    },
    error: {
        label: 'Error',
        variant: 'danger',
        iconName: 'XCircle',
        colorClass: 'bg-red-500',
        textColorClass: 'text-danger',
        dotClass: 'status-error',
    },
    initializing: {
        label: 'Initializing',
        variant: 'info',
        iconName: 'Loader2',
        colorClass: 'bg-blue-500',
        textColorClass: 'text-info',
        animate: true,
        dotClass: 'status-initializing',
    },
    starting: {
        label: 'Starting',
        variant: 'warning',
        iconName: 'Loader2',
        colorClass: 'bg-yellow-500',
        textColorClass: 'text-warning',
        animate: true,
        dotClass: 'status-deploying',
    },
    restarting: {
        label: 'Restarting',
        variant: 'warning',
        iconName: 'RefreshCw',
        colorClass: 'bg-yellow-500',
        textColorClass: 'text-warning',
        animate: true,
        dotClass: 'status-deploying',
    },

    // CronJob statuses
    enabled: {
        label: 'Enabled',
        variant: 'success',
        iconName: 'CheckCircle',
        colorClass: 'bg-green-500',
        textColorClass: 'text-primary',
    },
    disabled: {
        label: 'Disabled',
        variant: 'default',
        iconName: 'Ban',
        colorClass: 'bg-gray-500',
        textColorClass: 'text-foreground-muted',
    },

    // ScheduledTask / Execution statuses
    pending: {
        label: 'Pending',
        variant: 'info',
        iconName: 'Clock',
        colorClass: 'bg-blue-500',
        textColorClass: 'text-info',
    },
    completed: {
        label: 'Completed',
        variant: 'success',
        iconName: 'CheckCircle',
        colorClass: 'bg-green-500',
        textColorClass: 'text-primary',
    },
    success: {
        label: 'Success',
        variant: 'success',
        iconName: 'CheckCircle',
        colorClass: 'bg-green-500',
        textColorClass: 'text-primary',
    },

    // Volume statuses
    active: {
        label: 'Active',
        variant: 'success',
        iconName: 'CheckCircle',
        colorClass: 'bg-green-500',
        textColorClass: 'text-primary',
    },
    creating: {
        label: 'Creating',
        variant: 'warning',
        iconName: 'Loader2',
        colorClass: 'bg-yellow-500',
        textColorClass: 'text-warning',
        animate: true,
    },

    // Domain statuses
    verifying: {
        label: 'Verifying',
        variant: 'info',
        iconName: 'RefreshCw',
        colorClass: 'bg-blue-500',
        textColorClass: 'text-info',
        animate: true,
    },

    // SSL statuses
    expired: {
        label: 'Expired',
        variant: 'danger',
        iconName: 'ShieldAlert',
        colorClass: 'bg-red-500',
        textColorClass: 'text-danger',
    },
    expiring_soon: {
        label: 'Expiring Soon',
        variant: 'warning',
        iconName: 'AlertTriangle',
        colorClass: 'bg-orange-500',
        textColorClass: 'text-warning',
    },

    // Health statuses
    healthy: {
        label: 'Healthy',
        variant: 'success',
        iconName: 'CheckCircle',
        colorClass: 'bg-green-500',
        textColorClass: 'text-primary',
    },
    unhealthy: {
        label: 'Unhealthy',
        variant: 'danger',
        iconName: 'XCircle',
        colorClass: 'bg-red-500',
        textColorClass: 'text-danger',
    },
    degraded: {
        label: 'Degraded',
        variant: 'warning',
        iconName: 'AlertTriangle',
        colorClass: 'bg-yellow-500',
        textColorClass: 'text-warning',
    },

    // Rollback specific
    rolled_back: {
        label: 'Rolled Back',
        variant: 'warning',
        iconName: 'AlertCircle',
        colorClass: 'bg-orange-500',
        textColorClass: 'text-warning',
    },
};

// Default config for unknown statuses
const defaultStatusConfig: StatusConfig = {
    label: 'Unknown',
    variant: 'default',
    iconName: 'AlertCircle',
    colorClass: 'bg-gray-500',
    textColorClass: 'text-foreground-muted',
};

// === CORE FUNCTIONS ===

/**
 * Get full status configuration
 */
export function getStatusConfig(status: string): StatusConfig {
    const normalizedStatus = status.toLowerCase();
    return statusRegistry[normalizedStatus] ?? defaultStatusConfig;
}

/**
 * Get status icon as React element
 */
export function getStatusIcon(
    status: string,
    options: IconOptions = {}
): React.ReactElement {
    const { size = 'md', animate, className } = options;
    const config = getStatusConfig(status);
    const Icon = iconMap[config.iconName];

    const shouldAnimate = animate ?? config.animate;
    const sizeClass = iconSizes[size];

    return React.createElement(Icon, {
        className: cn(
            sizeClass,
            config.textColorClass,
            shouldAnimate && 'animate-pulse',
            className
        ),
    });
}

/**
 * Get Badge variant for status
 */
export function getStatusVariant(status: string): BadgeVariant {
    return getStatusConfig(status).variant;
}

/**
 * Get dot/indicator color class (bg-*)
 */
export function getStatusColor(status: string): string {
    return getStatusConfig(status).colorClass;
}

/**
 * Get text color class (text-*)
 */
export function getStatusTextColor(status: string): string {
    return getStatusConfig(status).textColorClass;
}

/**
 * Get human-readable label for status
 */
export function getStatusLabel(status: string): string {
    return getStatusConfig(status).label;
}

/**
 * Check if status requires animation (loading states)
 */
export function isAnimatedStatus(status: string): boolean {
    return getStatusConfig(status).animate ?? false;
}

/**
 * Get StatusBadge dot CSS class
 */
export function getStatusDotClass(status: string): string {
    return getStatusConfig(status).dotClass ?? '';
}

// === UTILITY FUNCTIONS ===

const positiveStatuses = new Set([
    'finished', 'running', 'online', 'active', 'enabled', 'completed', 'success', 'healthy',
]);

const negativeStatuses = new Set([
    'failed', 'error', 'expired', 'unhealthy', 'offline', 'cancelled',
]);

const loadingStatuses = new Set([
    'in_progress', 'deploying', 'building', 'creating', 'deleting',
    'initializing', 'starting', 'restarting', 'verifying', 'queued', 'pending',
]);

/**
 * Check if status is considered "positive" (success state)
 */
export function isPositiveStatus(status: string): boolean {
    return positiveStatuses.has(status.toLowerCase());
}

/**
 * Check if status is considered "negative" (error state)
 */
export function isNegativeStatus(status: string): boolean {
    return negativeStatuses.has(status.toLowerCase());
}

/**
 * Check if status is considered "in progress" (loading state)
 */
export function isLoadingStatus(status: string): boolean {
    return loadingStatuses.has(status.toLowerCase());
}

/**
 * Group multiple resource statuses into aggregate status
 */
export function getAggregateStatus(statuses: string[]): string {
    if (statuses.length === 0) return 'stopped';
    if (statuses.some(s => isNegativeStatus(s))) return 'error';
    if (statuses.some(s => isLoadingStatus(s))) return 'deploying';
    if (statuses.every(s => isPositiveStatus(s))) return 'running';
    return 'stopped';
}

// === COMPONENT HELPERS ===

/**
 * Props for StatusIndicator component helper
 */
export interface StatusIndicatorProps {
    icon: React.ReactElement;
    color: string;
    label: string;
    variant: BadgeVariant;
    animate: boolean;
}

/**
 * Get all props needed to render a status indicator
 */
export function getStatusIndicatorProps(
    status: string,
    options: IconOptions = {}
): StatusIndicatorProps {
    const config = getStatusConfig(status);
    return {
        icon: getStatusIcon(status, options),
        color: config.colorClass,
        label: config.label,
        variant: config.variant,
        animate: config.animate ?? false,
    };
}

/**
 * Backward compatible with existing StatusBadge statusConfig
 */
export function getStatusBadgeConfig(status: string) {
    const config = getStatusConfig(status);
    return {
        label: config.label,
        variant: config.variant,
        dotClass: config.dotClass ?? 'status-stopped',
    };
}

// Export the registry for Badge.tsx integration
export { statusRegistry };
