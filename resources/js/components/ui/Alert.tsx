import * as React from 'react';
import { cn } from '@/lib/utils';
interface AlertProps {
    children: React.ReactNode;
    variant?: 'default' | 'info' | 'success' | 'warning' | 'danger';
    className?: string;
}

const variantStyles = {
    default: 'bg-primary/[0.04] backdrop-blur-xl border-primary/[0.10] text-foreground',
    info: 'bg-blue-500/10 backdrop-blur-xl border-blue-500/30 text-blue-400',
    success: 'bg-success/10 backdrop-blur-xl border-success/30 text-success',
    warning: 'bg-warning/10 backdrop-blur-xl border-warning/30 text-warning',
    danger: 'bg-danger/10 backdrop-blur-xl border-danger/30 text-danger',
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
