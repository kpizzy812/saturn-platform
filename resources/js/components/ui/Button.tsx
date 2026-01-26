import * as React from 'react';
import { cn } from '@/lib/utils';

const buttonVariants = {
    variant: {
        default: [
            'bg-primary text-white',
            'hover:bg-primary-hover hover:shadow-glow-primary',
            'active:scale-[0.98]',
        ].join(' '),
        primary: [
            'bg-primary text-white',
            'hover:bg-primary-hover hover:shadow-glow-primary',
            'active:scale-[0.98]',
        ].join(' '),
        secondary: [
            'bg-background-secondary/80 text-foreground border border-white/[0.06]',
            'backdrop-blur-sm',
            'hover:bg-background-tertiary hover:border-white/[0.12]',
            'active:scale-[0.98]',
        ].join(' '),
        danger: [
            'bg-danger text-white',
            'hover:bg-danger-hover hover:shadow-glow-danger',
            'active:scale-[0.98]',
        ].join(' '),
        success: [
            'bg-success text-white',
            'hover:bg-success-hover hover:shadow-glow-success',
            'active:scale-[0.98]',
        ].join(' '),
        warning: [
            'bg-warning text-white',
            'hover:bg-warning-hover hover:shadow-glow-warning',
            'active:scale-[0.98]',
        ].join(' '),
        ghost: [
            'text-foreground-muted',
            'hover:bg-white/[0.06] hover:text-foreground',
            'active:bg-white/[0.08]',
        ].join(' '),
        link: [
            'text-primary underline-offset-4',
            'hover:underline hover:text-primary-hover',
        ].join(' '),
        outline: [
            'bg-transparent text-foreground border border-white/[0.12]',
            'hover:bg-white/[0.04] hover:border-white/[0.2]',
            'active:scale-[0.98]',
        ].join(' '),
        premium: [
            'bg-gradient-to-r from-primary to-purple-500 text-white',
            'hover:from-primary-hover hover:to-purple-600',
            'hover:shadow-[0_0_30px_rgba(99,102,241,0.4)]',
            'active:scale-[0.98]',
        ].join(' '),
    },
    size: {
        default: 'h-10 px-4 py-2',
        sm: 'h-8 px-3 text-sm',
        lg: 'h-12 px-6 text-lg',
        xl: 'h-14 px-8 text-lg font-semibold',
        icon: 'h-10 w-10',
        'icon-sm': 'h-8 w-8',
        'icon-lg': 'h-12 w-12',
    },
};

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: keyof typeof buttonVariants.variant;
    size?: keyof typeof buttonVariants.size;
    loading?: boolean;
    glow?: boolean;
}

export const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
    ({ className, variant = 'default', size = 'default', loading, glow, children, disabled, ...props }, ref) => {
        return (
            <button
                className={cn(
                    // Base styles
                    'inline-flex items-center justify-center gap-2 font-medium',
                    'rounded-lg',
                    // Transitions
                    'transition-all duration-200 ease-out',
                    // Focus states
                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/50 focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                    // Disabled states
                    'disabled:pointer-events-none disabled:opacity-50',
                    // Variant and size
                    buttonVariants.variant[variant],
                    buttonVariants.size[size],
                    // Optional glow effect
                    glow && 'shadow-glow-primary animate-glow-pulse',
                    className
                )}
                ref={ref}
                disabled={disabled || loading}
                {...props}
            >
                {loading && (
                    <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle
                            className="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            strokeWidth="3"
                        />
                        <path
                            className="opacity-90"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                        />
                    </svg>
                )}
                {children}
            </button>
        );
    }
);
Button.displayName = 'Button';
