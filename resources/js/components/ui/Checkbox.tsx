import * as React from 'react';
import { cn } from '@/lib/utils';

interface CheckboxProps extends React.InputHTMLAttributes<HTMLInputElement> {
    label?: string;
    hint?: string;
    onCheckedChange?: (checked: boolean) => void;
}

export const Checkbox = React.forwardRef<HTMLInputElement, CheckboxProps>(
    ({ className, label, hint, id, onCheckedChange, onChange, ...props }, ref) => {
        const generatedId = React.useId();
        const checkboxId = id || generatedId;

        const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
            onChange?.(e);
            onCheckedChange?.(e.target.checked);
        };

        return (
            <div className="flex items-start gap-2">
                <input
                    type="checkbox"
                    id={checkboxId}
                    className={cn(
                        'mt-0.5 h-4 w-4 rounded border-border bg-background-secondary text-primary',
                        'focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-background',
                        className
                    )}
                    ref={ref}
                    onChange={handleChange}
                    {...props}
                />
                {(label || hint) && (
                    <div>
                        {label && (
                            <label htmlFor={checkboxId} className="text-sm text-foreground">
                                {label}
                            </label>
                        )}
                        {hint && (
                            <p className="text-xs text-foreground-muted">{hint}</p>
                        )}
                    </div>
                )}
            </div>
        );
    }
);
Checkbox.displayName = 'Checkbox';
