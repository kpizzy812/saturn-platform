import * as React from 'react';
import { cn } from '@/lib/utils';

interface CheckboxProps extends React.InputHTMLAttributes<HTMLInputElement> {
    label?: string;
}

export const Checkbox = React.forwardRef<HTMLInputElement, CheckboxProps>(
    ({ className, label, id, ...props }, ref) => {
        const checkboxId = id || React.useId();

        return (
            <div className="flex items-center gap-2">
                <input
                    type="checkbox"
                    id={checkboxId}
                    className={cn(
                        'h-4 w-4 rounded border-border bg-background-secondary text-primary',
                        'focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background',
                        className
                    )}
                    ref={ref}
                    {...props}
                />
                {label && (
                    <label htmlFor={checkboxId} className="text-sm text-foreground">
                        {label}
                    </label>
                )}
            </div>
        );
    }
);
Checkbox.displayName = 'Checkbox';
