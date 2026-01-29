import * as React from 'react';
import { cn } from '@/lib/utils';

interface SliderProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type' | 'onChange'> {
    label?: string;
    hint?: string;
    error?: string;
    min?: number;
    max?: number;
    step?: number;
    value: number;
    onChange: (value: number) => void;
    showValue?: boolean;
    formatValue?: (value: number) => string;
}

export const Slider = React.forwardRef<HTMLInputElement, SliderProps>(
    ({
        className,
        label,
        hint,
        error,
        id,
        min = 0,
        max = 100,
        step = 1,
        value,
        onChange,
        showValue = true,
        formatValue,
        ...props
    }, ref) => {
        const generatedId = React.useId();
        const sliderId = id || generatedId;
        const percentage = ((value - min) / (max - min)) * 100;

        return (
            <div className="space-y-2">
                {label && (
                    <div className="flex items-center justify-between">
                        <label htmlFor={sliderId} className="text-sm font-medium text-foreground">
                            {label}
                        </label>
                        {showValue && (
                            <span className="text-sm font-medium text-foreground">
                                {formatValue ? formatValue(value) : value}
                            </span>
                        )}
                    </div>
                )}
                <div className="relative">
                    <input
                        id={sliderId}
                        ref={ref}
                        type="range"
                        min={min}
                        max={max}
                        step={step}
                        value={value}
                        onChange={(e) => onChange(Number(e.target.value))}
                        className={cn(
                            'w-full cursor-pointer appearance-none bg-transparent',
                            '[&::-webkit-slider-runnable-track]:h-2 [&::-webkit-slider-runnable-track]:rounded-full [&::-webkit-slider-runnable-track]:bg-background-tertiary',
                            '[&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-primary',
                            '[&::-webkit-slider-thumb]:transition-all [&::-webkit-slider-thumb]:hover:scale-110',
                            '[&::-moz-range-track]:h-2 [&::-moz-range-track]:rounded-full [&::-moz-range-track]:bg-background-tertiary',
                            '[&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:w-4 [&::-moz-range-thumb]:appearance-none [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-0 [&::-moz-range-thumb]:bg-primary',
                            'disabled:cursor-not-allowed disabled:opacity-50',
                            className
                        )}
                        style={{
                            background: `linear-gradient(to right, rgb(var(--primary)) 0%, rgb(var(--primary)) ${percentage}%, rgb(var(--background-tertiary)) ${percentage}%, rgb(var(--background-tertiary)) 100%)`
                        }}
                        {...props}
                    />
                </div>
                {error && <p className="text-sm text-danger">{error}</p>}
                {hint && !error && <p className="text-sm text-foreground-muted">{hint}</p>}
            </div>
        );
    }
);
Slider.displayName = 'Slider';
