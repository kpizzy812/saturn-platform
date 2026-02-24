import * as React from 'react';
import { cn } from '@/lib/utils';

interface SelectProps extends React.SelectHTMLAttributes<HTMLSelectElement> {
    label?: string;
    error?: string;
    hint?: string;
    options?: { value: string; label: string }[];
}

export const Select = React.forwardRef<HTMLSelectElement, SelectProps>(
    ({ className, label, error, hint, options, id, children, ...props }, ref) => {
        const generatedId = React.useId();
        const selectId = id || generatedId;

        return (
            <div className="space-y-1.5">
                {label && (
                    <label htmlFor={selectId} className="text-sm font-medium text-foreground">
                        {label}
                    </label>
                )}
                <select
                    id={selectId}
                    className={cn(
                        'flex h-10 w-full rounded-md border bg-white/[0.03] backdrop-blur-lg px-3 py-2 text-sm text-foreground',
                        'focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background',
                        'disabled:cursor-not-allowed disabled:opacity-50',
                        error ? 'border-danger' : 'border-border',
                        className
                    )}
                    ref={ref}
                    {...props}
                >
                    {options ? (
                        options.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))
                    ) : (
                        children
                    )}
                </select>
                {hint && !error && <p className="text-xs text-foreground-muted">{hint}</p>}
                {error && <p className="text-sm text-danger">{error}</p>}
            </div>
        );
    }
);
Select.displayName = 'Select';
