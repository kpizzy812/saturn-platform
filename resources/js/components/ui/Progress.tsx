import * as React from 'react';
import { cn } from '@/lib/utils';

interface ProgressProps extends React.HTMLAttributes<HTMLDivElement> {
    value: number; // 0-100
    max?: number;
    variant?: 'default' | 'success' | 'warning' | 'danger';
    showLabel?: boolean;
    size?: 'sm' | 'default' | 'lg';
}

const variantStyles = {
    default: 'bg-primary',
    success: 'bg-green-500',
    warning: 'bg-yellow-500',
    danger: 'bg-red-500',
};

const sizeStyles = {
    sm: 'h-1',
    default: 'h-2',
    lg: 'h-3',
};

export const Progress = React.forwardRef<HTMLDivElement, ProgressProps>(
    ({ className, value, max = 100, variant = 'default', showLabel = false, size = 'default', ...props }, ref) => {
        const percentage = Math.min(100, Math.max(0, (value / max) * 100));

        // Determine variant based on percentage if default
        const actualVariant = variant === 'default'
            ? percentage >= 90
                ? 'danger'
                : percentage >= 75
                    ? 'warning'
                    : 'success'
            : variant;

        return (
            <div ref={ref} className={cn('w-full', className)} {...props}>
                <div className={cn('relative overflow-hidden rounded-full bg-background-tertiary', sizeStyles[size])}>
                    <div
                        className={cn(
                            'h-full transition-all duration-300 ease-in-out',
                            variantStyles[actualVariant]
                        )}
                        style={{ width: `${percentage}%` }}
                    />
                </div>
                {showLabel && (
                    <div className="mt-1 flex items-center justify-between text-xs text-foreground-muted">
                        <span>{percentage.toFixed(1)}%</span>
                        <span>{value} / {max}</span>
                    </div>
                )}
            </div>
        );
    }
);
Progress.displayName = 'Progress';
