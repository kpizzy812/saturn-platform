
import { cn } from '@/lib/utils';

interface SaturnLogoProps {
    size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
    className?: string;
    animate?: boolean;
}

const sizeClasses = {
    xs: 'h-4 w-4',
    sm: 'h-6 w-6',
    md: 'h-8 w-8',
    lg: 'h-10 w-10',
    xl: 'h-12 w-12',
};

export function SaturnLogo({ size = 'md', className, animate = false }: SaturnLogoProps) {
    return (
        <svg
            viewBox="0 0 32 32"
            fill="none"
            className={cn(
                sizeClasses[size],
                animate && 'animate-spin-slow',
                className
            )}
        >
            {/* Glow effect */}
            <defs>
                <radialGradient id="saturn-glow" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stopColor="#6366f1" stopOpacity="0.6" />
                    <stop offset="100%" stopColor="#6366f1" stopOpacity="0" />
                </radialGradient>
                <linearGradient id="saturn-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stopColor="#6366f1" />
                    <stop offset="100%" stopColor="#8b5cf6" />
                </linearGradient>
            </defs>

            {/* Background glow */}
            <circle cx="16" cy="16" r="15" fill="url(#saturn-glow)" />

            {/* Planet body */}
            <circle
                cx="16"
                cy="16"
                r="7"
                fill="url(#saturn-gradient)"
            />

            {/* Planet highlight */}
            <circle
                cx="13"
                cy="13"
                r="2"
                fill="white"
                fillOpacity="0.3"
            />

            {/* Ring - front part (passes over planet) */}
            <ellipse
                cx="16"
                cy="16"
                rx="13"
                ry="4"
                stroke="url(#saturn-gradient)"
                strokeWidth="1.5"
                fill="none"
                strokeDasharray="0 20 25 0"
            />

            {/* Ring - back part (behind planet) */}
            <ellipse
                cx="16"
                cy="16"
                rx="13"
                ry="4"
                stroke="url(#saturn-gradient)"
                strokeWidth="1.5"
                fill="none"
                opacity="0.4"
                strokeDasharray="25 0 0 20"
            />
        </svg>
    );
}

interface SaturnBrandProps {
    size?: 'sm' | 'md' | 'lg';
    showLogo?: boolean;
    className?: string;
}

const brandSizes = {
    sm: {
        text: 'text-lg',
        logo: 'sm' as const,
        gap: 'gap-2',
    },
    md: {
        text: 'text-xl',
        logo: 'md' as const,
        gap: 'gap-2.5',
    },
    lg: {
        text: 'text-2xl',
        logo: 'lg' as const,
        gap: 'gap-3',
    },
};

export function SaturnBrand({ size = 'md', showLogo = true, className }: SaturnBrandProps) {
    const config = brandSizes[size];

    return (
        <div className={cn('flex items-center', config.gap, className)}>
            {showLogo && <SaturnLogo size={config.logo} />}
            <span
                className={cn(
                    config.text,
                    'font-bold tracking-tight',
                    'saturn-gradient'
                )}
            >
                Saturn
            </span>
        </div>
    );
}

// Minimal logo variant for tight spaces
interface SaturnIconProps {
    size?: 'xs' | 'sm' | 'md' | 'lg';
    className?: string;
}

export function SaturnIcon({ size = 'md', className }: SaturnIconProps) {
    const sizeMap = {
        xs: 'h-4 w-4',
        sm: 'h-5 w-5',
        md: 'h-6 w-6',
        lg: 'h-8 w-8',
    };

    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            className={cn(sizeMap[size], className)}
        >
            <defs>
                <linearGradient id="saturn-icon-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stopColor="#6366f1" />
                    <stop offset="100%" stopColor="#8b5cf6" />
                </linearGradient>
            </defs>
            {/* Simple planet + ring */}
            <circle cx="12" cy="12" r="5" fill="url(#saturn-icon-grad)" />
            <ellipse
                cx="12"
                cy="12"
                rx="10"
                ry="3"
                stroke="url(#saturn-icon-grad)"
                strokeWidth="1.5"
                fill="none"
                opacity="0.8"
            />
        </svg>
    );
}
