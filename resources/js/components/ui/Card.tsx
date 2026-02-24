import * as React from 'react';
import { cn } from '@/lib/utils';

// Context to auto-apply admin styling to cards inside admin layout
export const CardThemeContext = React.createContext<'default' | 'admin'>('default');

interface CardProps extends React.HTMLAttributes<HTMLDivElement> {
    variant?: 'default' | 'glass' | 'elevated' | 'outline' | 'admin';
    hover?: boolean;
    glow?: 'primary' | 'success' | 'warning' | 'danger' | 'none';
}

const cardVariants = {
    default: 'bg-primary/[0.04] backdrop-blur-xl border-primary/[0.10] backdrop-saturate-[1.2]',
    glass: 'bg-primary/[0.06] backdrop-blur-2xl border-primary/[0.12] backdrop-saturate-150',
    elevated: 'bg-primary/[0.08] backdrop-blur-2xl border-primary/[0.14] shadow-lg backdrop-saturate-150',
    outline: 'bg-transparent backdrop-blur-sm border-primary/[0.12]',
    admin: 'bg-primary/[0.06] backdrop-blur-xl border-primary/[0.14] backdrop-saturate-[1.2]',
};

const glowVariants = {
    primary: 'shadow-glow-primary',
    success: 'shadow-glow-success',
    warning: 'shadow-glow-warning',
    danger: 'shadow-glow-danger',
    none: '',
};

export const Card = React.forwardRef<HTMLDivElement, CardProps>(
    ({ className, variant = 'default', hover = false, glow = 'none', ...props }, ref) => {
        const theme = React.useContext(CardThemeContext);
        // Auto-promote glass/default to admin variant inside admin layout
        const resolvedVariant = theme === 'admin' && (variant === 'glass' || variant === 'default')
            ? 'admin'
            : variant;

        return (
            <div
                ref={ref}
                className={cn(
                    // Base styles
                    'rounded-xl border p-6',
                    // Transitions
                    'transition-all duration-200 ease-out',
                    // Variant styles
                    cardVariants[resolvedVariant],
                    // Hover effects
                    hover && [
                        'hover:border-primary/20',
                        'hover:shadow-card-hover',
                        'hover:-translate-y-0.5',
                        'cursor-pointer',
                    ],
                    // Glow effect
                    glowVariants[glow],
                    className
                )}
                {...props}
            />
        );
    }
);
Card.displayName = 'Card';

interface CardHeaderProps extends React.HTMLAttributes<HTMLDivElement> {
    compact?: boolean;
}

export const CardHeader = React.forwardRef<HTMLDivElement, CardHeaderProps>(
    ({ className, compact = false, ...props }, ref) => (
        <div
            ref={ref}
            className={cn(
                'flex flex-col space-y-1.5',
                compact ? 'mb-3' : 'mb-4',
                className
            )}
            {...props}
        />
    )
);
CardHeader.displayName = 'CardHeader';

interface CardTitleProps extends React.HTMLAttributes<HTMLHeadingElement> {
    as?: 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6';
}

export const CardTitle = React.forwardRef<HTMLHeadingElement, CardTitleProps>(
    ({ className, as: Tag = 'h3', ...props }, ref) => (
        <Tag
            ref={ref}
            className={cn(
                'text-lg font-semibold tracking-tight text-foreground',
                className
            )}
            {...props}
        />
    )
);
CardTitle.displayName = 'CardTitle';

export const CardDescription = React.forwardRef<HTMLParagraphElement, React.HTMLAttributes<HTMLParagraphElement>>(
    ({ className, ...props }, ref) => (
        <p
            ref={ref}
            className={cn('text-sm text-foreground-muted leading-relaxed', className)}
            {...props}
        />
    )
);
CardDescription.displayName = 'CardDescription';

export const CardContent = React.forwardRef<HTMLDivElement, React.HTMLAttributes<HTMLDivElement>>(
    ({ className, ...props }, ref) => (
        <div ref={ref} className={cn('', className)} {...props} />
    )
);
CardContent.displayName = 'CardContent';

interface CardFooterProps extends React.HTMLAttributes<HTMLDivElement> {
    border?: boolean;
}

export const CardFooter = React.forwardRef<HTMLDivElement, CardFooterProps>(
    ({ className, border = false, ...props }, ref) => (
        <div
            ref={ref}
            className={cn(
                'mt-4 flex items-center gap-3',
                border && 'pt-4 border-t border-primary/[0.08]',
                className
            )}
            {...props}
        />
    )
);
CardFooter.displayName = 'CardFooter';
