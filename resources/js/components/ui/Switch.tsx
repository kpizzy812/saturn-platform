import * as React from 'react';
import { cn } from '@/lib/utils';

interface SwitchProps {
    checked: boolean;
    onChange: (checked: boolean) => void;
    label?: string;
    description?: string;
    disabled?: boolean;
    size?: 'sm' | 'md';
    className?: string;
    id?: string;
}

export const Switch = React.forwardRef<HTMLButtonElement, SwitchProps>(
    ({ checked, onChange, label, description, disabled = false, size = 'md', className, id }, ref) => {
        const generatedId = React.useId();
        const switchId = id || generatedId;

        const trackSize = size === 'sm' ? 'h-5 w-9' : 'h-6 w-11';
        const thumbSize = size === 'sm' ? 'h-3.5 w-3.5' : 'h-4.5 w-4.5';
        const thumbTranslate = size === 'sm'
            ? (checked ? 'translate-x-4' : 'translate-x-0.5')
            : (checked ? 'translate-x-5' : 'translate-x-0.5');

        return (
            <div className={cn('flex items-center gap-3', className)}>
                {(label || description) && (
                    <div className="flex-1">
                        {label && (
                            <label htmlFor={switchId} className="cursor-pointer text-sm font-medium text-foreground">
                                {label}
                            </label>
                        )}
                        {description && (
                            <p className="text-xs text-foreground-muted">{description}</p>
                        )}
                    </div>
                )}
                <button
                    ref={ref}
                    id={switchId}
                    type="button"
                    role="switch"
                    aria-checked={checked}
                    disabled={disabled}
                    onClick={() => onChange(!checked)}
                    className={cn(
                        'relative inline-flex shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out',
                        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                        trackSize,
                        checked
                            ? 'bg-primary'
                            : 'bg-foreground-subtle/30',
                        disabled && 'cursor-not-allowed opacity-50',
                    )}
                >
                    <span
                        className={cn(
                            'pointer-events-none inline-block transform rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out',
                            thumbSize,
                            thumbTranslate,
                        )}
                    />
                </button>
            </div>
        );
    }
);
Switch.displayName = 'Switch';
