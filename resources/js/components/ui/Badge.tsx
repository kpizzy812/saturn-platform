import * as React from 'react';
import { cn } from '@/lib/utils';

const badgeVariants = {
    default: 'bg-white/[0.08] text-foreground-muted border-white/[0.06]',
    primary: 'bg-primary/15 text-primary border-primary/20',
    success: 'bg-success/15 text-success border-success/20',
    danger: 'bg-danger/15 text-danger border-danger/20',
    error: 'bg-danger/15 text-danger border-danger/20', // Alias for danger
    destructive: 'bg-danger/15 text-danger border-danger/20', // Alias for danger
    warning: 'bg-warning/15 text-warning border-warning/20',
    info: 'bg-info/15 text-info border-info/20',
    outline: 'bg-transparent text-foreground border-border',
    secondary: 'bg-white/[0.08] text-foreground-muted border-white/[0.06]', // Alias for default
    // Solid variants for more emphasis
    'primary-solid': 'bg-primary text-white border-transparent',
    'success-solid': 'bg-success text-white border-transparent',
    'danger-solid': 'bg-danger text-white border-transparent',
    'warning-solid': 'bg-warning text-white border-transparent',
    'info-solid': 'bg-info text-white border-transparent',
};

const badgeSizes = {
    sm: 'px-1.5 py-0.5 text-[10px]',
    default: 'px-2.5 py-0.5 text-xs',
    lg: 'px-3 py-1 text-sm',
};

interface BadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
    variant?: keyof typeof badgeVariants;
    size?: keyof typeof badgeSizes;
    dot?: boolean;
    pulse?: boolean;
    icon?: React.ReactNode;
}

export const Badge = React.forwardRef<HTMLSpanElement, BadgeProps>(
    ({ className, variant = 'default', size = 'default', dot, pulse, icon, children, ...props }, ref) => (
        <span
            ref={ref}
            className={cn(
                // Base styles
                'inline-flex items-center gap-1.5 font-medium',
                'rounded-full border',
                // Transition
                'transition-colors duration-150',
                // Variants
                badgeVariants[variant],
                badgeSizes[size],
                className
            )}
            {...props}
        >
            {dot && (
                <span
                    className={cn(
                        'h-1.5 w-1.5 rounded-full',
                        // Color based on variant
                        variant === 'success' || variant === 'success-solid' ? 'bg-success' : '',
                        variant === 'danger' || variant === 'danger-solid' ? 'bg-danger' : '',
                        variant === 'warning' || variant === 'warning-solid' ? 'bg-warning' : '',
                        variant === 'info' || variant === 'info-solid' ? 'bg-info' : '',
                        variant === 'primary' || variant === 'primary-solid' ? 'bg-primary' : '',
                        variant === 'default' ? 'bg-foreground-muted' : '',
                        // Solid variants use white dot
                        variant.includes('-solid') && 'bg-white',
                        // Pulse animation
                        pulse && 'animate-pulse-soft'
                    )}
                />
            )}
            {icon && <span className="flex-shrink-0">{icon}</span>}
            {children}
        </span>
    )
);
Badge.displayName = 'Badge';

// Status Badge - special variant for service status
interface StatusBadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
    status: 'online' | 'offline' | 'deploying' | 'error' | 'stopped' | 'initializing' | 'running' | 'failed' | 'queued' | 'in_progress' | 'finished' | 'cancelled';
    size?: 'sm' | 'default' | 'lg';
}

const statusConfig = {
    online: {
        label: 'Online',
        variant: 'success' as const,
        dotClass: 'status-online',
    },
    running: {
        label: 'running',
        variant: 'success' as const,
        dotClass: 'status-online',
    },
    offline: {
        label: 'Offline',
        variant: 'default' as const,
        dotClass: 'status-stopped',
    },
    deploying: {
        label: 'Deploying',
        variant: 'warning' as const,
        dotClass: 'status-deploying',
    },
    error: {
        label: 'Error',
        variant: 'danger' as const,
        dotClass: 'status-error',
    },
    failed: {
        label: 'failed',
        variant: 'danger' as const,
        dotClass: 'status-error',
    },
    stopped: {
        label: 'Stopped',
        variant: 'default' as const,
        dotClass: 'status-stopped',
    },
    initializing: {
        label: 'Initializing',
        variant: 'info' as const,
        dotClass: 'status-initializing',
    },
    queued: {
        label: 'queued',
        variant: 'info' as const,
        dotClass: 'status-initializing',
    },
    in_progress: {
        label: 'in_progress',
        variant: 'warning' as const,
        dotClass: 'status-deploying',
    },
    finished: {
        label: 'finished',
        variant: 'success' as const,
        dotClass: 'status-online',
    },
    cancelled: {
        label: 'cancelled',
        variant: 'default' as const,
        dotClass: 'status-stopped',
    },
};

export const StatusBadge = React.forwardRef<HTMLSpanElement, StatusBadgeProps>(
    ({ status, size = 'default', className, ...props }, ref) => {
        const config = statusConfig[status] || statusConfig['stopped'];

        return (
            <span
                ref={ref}
                className={cn(
                    'inline-flex items-center gap-1.5 font-medium rounded-full border',
                    badgeVariants[config.variant],
                    badgeSizes[size],
                    className
                )}
                {...props}
            >
                <span className={cn('h-2 w-2 rounded-full', config.dotClass)} />
                {config.label}
            </span>
        );
    }
);
StatusBadge.displayName = 'StatusBadge';
