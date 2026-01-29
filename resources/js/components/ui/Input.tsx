import * as React from 'react';
import { cn } from '@/lib/utils';

interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
    label?: string;
    error?: string;
    hint?: string;
    icon?: React.ReactNode;
    iconPosition?: 'left' | 'right';
}

export const Input = React.forwardRef<HTMLInputElement, InputProps>(
    ({ className, label, error, hint, icon, iconPosition = 'left', id, type, ...props }, ref) => {
        const generatedId = React.useId();
        const inputId = id || generatedId;

        return (
            <div className="space-y-2">
                {label && (
                    <label
                        htmlFor={inputId}
                        className="text-sm font-medium text-foreground"
                    >
                        {label}
                    </label>
                )}
                <div className="relative">
                    {icon && iconPosition === 'left' && (
                        <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <span className="text-foreground-subtle">{icon}</span>
                        </div>
                    )}
                    <input
                        id={inputId}
                        type={type}
                        className={cn(
                            // Base styles
                            'flex h-10 w-full rounded-lg px-3 py-2 text-sm',
                            // Colors
                            'bg-background text-foreground',
                            'placeholder:text-foreground-subtle',
                            // Border
                            'border transition-all duration-200',
                            error
                                ? 'border-danger focus:border-danger focus:ring-danger/30'
                                : 'border-white/[0.08] focus:border-primary/50',
                            // Focus states
                            'focus:outline-none focus:ring-2 focus:ring-primary/20',
                            'focus:bg-background-secondary',
                            // Hover
                            'hover:border-white/[0.12]',
                            // Disabled
                            'disabled:cursor-not-allowed disabled:opacity-50 disabled:bg-background-tertiary',
                            // Icon padding
                            icon && iconPosition === 'left' && 'pl-10',
                            icon && iconPosition === 'right' && 'pr-10',
                            className
                        )}
                        ref={ref}
                        {...props}
                    />
                    {icon && iconPosition === 'right' && (
                        <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                            <span className="text-foreground-subtle">{icon}</span>
                        </div>
                    )}
                </div>
                {error && (
                    <p className="text-sm text-danger flex items-center gap-1.5">
                        <svg className="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                        </svg>
                        {error}
                    </p>
                )}
                {hint && !error && (
                    <p className="text-sm text-foreground-subtle">{hint}</p>
                )}
            </div>
        );
    }
);
Input.displayName = 'Input';

// Textarea component
interface TextareaProps extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {
    label?: string;
    error?: string;
    hint?: string;
}

export const Textarea = React.forwardRef<HTMLTextAreaElement, TextareaProps>(
    ({ className, label, error, hint, id, ...props }, ref) => {
        const generatedId = React.useId();
        const textareaId = id || generatedId;

        return (
            <div className="space-y-2">
                {label && (
                    <label
                        htmlFor={textareaId}
                        className="text-sm font-medium text-foreground"
                    >
                        {label}
                    </label>
                )}
                <textarea
                    id={textareaId}
                    className={cn(
                        // Base styles
                        'flex min-h-[100px] w-full rounded-lg px-3 py-2 text-sm',
                        // Colors
                        'bg-background text-foreground',
                        'placeholder:text-foreground-subtle',
                        // Border
                        'border transition-all duration-200',
                        error
                            ? 'border-danger focus:border-danger focus:ring-danger/30'
                            : 'border-white/[0.08] focus:border-primary/50',
                        // Focus states
                        'focus:outline-none focus:ring-2 focus:ring-primary/20',
                        'focus:bg-background-secondary',
                        // Hover
                        'hover:border-white/[0.12]',
                        // Disabled
                        'disabled:cursor-not-allowed disabled:opacity-50',
                        // Resize
                        'resize-none',
                        className
                    )}
                    ref={ref}
                    {...props}
                />
                {error && (
                    <p className="text-sm text-danger flex items-center gap-1.5">
                        <svg className="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                        </svg>
                        {error}
                    </p>
                )}
                {hint && !error && (
                    <p className="text-sm text-foreground-subtle">{hint}</p>
                )}
            </div>
        );
    }
);
Textarea.displayName = 'Textarea';
