import * as React from 'react';
import { cn } from '@/lib/utils';
interface AlertProps {
    children: React.ReactNode;
    variant?: 'default' | 'info' | 'success' | 'warning' | 'danger';
    className?: string;
}

const variantStyles = {
    default: 'bg-background-secondary border-border text-foreground',
    info: 'bg-blue-500/10 border-blue-500/30 text-blue-400',
    success: 'bg-success/10 border-success/30 text-success',
    warning: 'bg-warning/10 border-warning/30 text-warning',
    danger: 'bg-danger/10 border-danger/30 text-danger',
};

export function Alert({ children, variant = 'default', className }: AlertProps) {
    return (
        <div
            className={cn(
                'flex items-center gap-3 rounded-lg border p-3 text-sm',
                variantStyles[variant],
                className
            )}
            role="alert"
        >
            {children}
        </div>
    );
}
