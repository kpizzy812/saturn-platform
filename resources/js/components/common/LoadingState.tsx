import * as React from 'react';
import { cn } from '@/lib/utils';
import { Spinner } from '@/components/ui/Spinner';

// Skeleton Components
interface SkeletonProps extends React.HTMLAttributes<HTMLDivElement> {}

export const Skeleton = React.forwardRef<HTMLDivElement, SkeletonProps>(
    ({ className, ...props }, ref) => (
        <div
            ref={ref}
            className={cn(
                'animate-pulse rounded-md bg-background-tertiary/50',
                className
            )}
            {...props}
        />
    )
);
Skeleton.displayName = 'Skeleton';

// Card Skeleton
interface CardSkeletonProps {
    className?: string;
    count?: number;
}

export const CardSkeleton = React.forwardRef<HTMLDivElement, CardSkeletonProps>(
    ({ className, count = 1 }, ref) => {
        return (
            <>
                {Array.from({ length: count }).map((_, index) => (
                    <div
                        key={index}
                        ref={index === 0 ? ref : undefined}
                        className={cn(
                            'rounded-xl border border-border/50 bg-gradient-to-br from-background-secondary to-background-secondary/50 p-5',
                            className
                        )}
                    >
                        <div className="flex items-start justify-between">
                            <div className="flex items-center gap-3">
                                <Skeleton className="h-2.5 w-2.5 rounded-full" />
                                <Skeleton className="h-5 w-32" />
                            </div>
                            <Skeleton className="h-8 w-8 rounded-md" />
                        </div>
                        <div className="mt-4 flex items-center gap-3">
                            <Skeleton className="h-4 w-16" />
                            <Skeleton className="h-4 w-1" />
                            <Skeleton className="h-4 w-24" />
                        </div>
                    </div>
                ))}
            </>
        );
    }
);
CardSkeleton.displayName = 'CardSkeleton';

// Table Skeleton
interface TableSkeletonProps {
    rows?: number;
    columns?: number;
    className?: string;
}

export const TableSkeleton = React.forwardRef<HTMLDivElement, TableSkeletonProps>(
    ({ rows = 5, columns = 4, className }, ref) => {
        return (
            <div
                ref={ref}
                className={cn(
                    'w-full overflow-hidden rounded-lg border border-border/50',
                    className
                )}
            >
                {/* Header */}
                <div className="border-b border-border/50 bg-background-secondary p-4">
                    <div className="flex gap-4">
                        {Array.from({ length: columns }).map((_, index) => (
                            <Skeleton key={index} className="h-4 flex-1" />
                        ))}
                    </div>
                </div>
                {/* Rows */}
                <div className="divide-y divide-border/50">
                    {Array.from({ length: rows }).map((_, rowIndex) => (
                        <div key={rowIndex} className="p-4">
                            <div className="flex gap-4">
                                {Array.from({ length: columns }).map((_, colIndex) => (
                                    <Skeleton key={colIndex} className="h-4 flex-1" />
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        );
    }
);
TableSkeleton.displayName = 'TableSkeleton';

// List Skeleton
interface ListSkeletonProps {
    items?: number;
    className?: string;
}

export const ListSkeleton = React.forwardRef<HTMLDivElement, ListSkeletonProps>(
    ({ items = 5, className }, ref) => {
        return (
            <div ref={ref} className={cn('space-y-3', className)}>
                {Array.from({ length: items }).map((_, index) => (
                    <div
                        key={index}
                        className="flex items-center gap-4 rounded-lg border border-border/50 bg-background-secondary p-4"
                    >
                        <Skeleton className="h-10 w-10 rounded-full" />
                        <div className="flex-1 space-y-2">
                            <Skeleton className="h-4 w-3/4" />
                            <Skeleton className="h-3 w-1/2" />
                        </div>
                        <Skeleton className="h-8 w-20 rounded-md" />
                    </div>
                ))}
            </div>
        );
    }
);
ListSkeleton.displayName = 'ListSkeleton';

// Spinner with message
interface SpinnerWithMessageProps {
    message?: string;
    size?: 'sm' | 'default' | 'lg';
    className?: string;
}

export const SpinnerWithMessage = React.forwardRef<HTMLDivElement, SpinnerWithMessageProps>(
    ({ message = 'Loading...', size = 'default', className }, ref) => {
        return (
            <div
                ref={ref}
                className={cn(
                    'flex flex-col items-center justify-center gap-4 py-16',
                    className
                )}
            >
                <Spinner size={size} />
                <p className="text-sm text-foreground-muted">{message}</p>
            </div>
        );
    }
);
SpinnerWithMessage.displayName = 'SpinnerWithMessage';

// Progress Bar
interface ProgressBarProps {
    progress: number; // 0-100
    message?: string;
    showPercentage?: boolean;
    className?: string;
}

export const ProgressBar = React.forwardRef<HTMLDivElement, ProgressBarProps>(
    ({ progress, message, showPercentage = true, className }, ref) => {
        const clampedProgress = Math.min(100, Math.max(0, progress));

        return (
            <div ref={ref} className={cn('w-full', className)}>
                {(message || showPercentage) && (
                    <div className="mb-2 flex items-center justify-between text-sm">
                        {message && <span className="text-foreground-muted">{message}</span>}
                        {showPercentage && (
                            <span className="font-medium text-foreground">
                                {Math.round(clampedProgress)}%
                            </span>
                        )}
                    </div>
                )}
                <div className="h-2 overflow-hidden rounded-full bg-background-tertiary">
                    <div
                        className="h-full rounded-full bg-gradient-to-r from-primary to-emerald-400 transition-all duration-300 ease-out"
                        style={{ width: `${clampedProgress}%` }}
                    />
                </div>
            </div>
        );
    }
);
ProgressBar.displayName = 'ProgressBar';

// Shimmer Effect (for skeleton loaders)
export const ShimmerSkeleton = React.forwardRef<HTMLDivElement, SkeletonProps>(
    ({ className, ...props }, ref) => (
        <div
            ref={ref}
            className={cn(
                'relative overflow-hidden rounded-md bg-background-tertiary/50',
                className
            )}
            {...props}
        >
            <div className="absolute inset-0 -translate-x-full animate-shimmer bg-gradient-to-r from-transparent via-white/10 to-transparent" />
        </div>
    )
);
ShimmerSkeleton.displayName = 'ShimmerSkeleton';

// Page Loading (full screen)
interface PageLoadingProps {
    message?: string;
}

export const PageLoading = React.forwardRef<HTMLDivElement, PageLoadingProps>(
    ({ message = 'Loading...' }, ref) => {
        return (
            <div
                ref={ref}
                className="flex min-h-screen items-center justify-center bg-background"
            >
                <div className="text-center">
                    <div className="mb-6 flex justify-center">
                        <div className="relative">
                            {/* Rotating gradient glow */}
                            <div className="absolute inset-0 animate-spin-slow blur-2xl">
                                <div className="h-24 w-24 rounded-full bg-gradient-to-r from-primary via-purple-500 to-primary opacity-30" />
                            </div>
                            {/* Spinner */}
                            <Spinner size="lg" className="relative" />
                        </div>
                    </div>
                    <p className="text-foreground-muted">{message}</p>
                </div>
            </div>
        );
    }
);
PageLoading.displayName = 'PageLoading';

// Inline Loading (for buttons, etc.)
interface InlineLoadingProps {
    className?: string;
}

export const InlineLoading = React.forwardRef<HTMLSpanElement, InlineLoadingProps>(
    ({ className }, ref) => {
        return (
            <span
                ref={ref}
                className={cn('inline-flex items-center gap-2', className)}
            >
                <Spinner size="sm" />
                <span className="text-foreground-muted">Loading...</span>
            </span>
        );
    }
);
InlineLoading.displayName = 'InlineLoading';
