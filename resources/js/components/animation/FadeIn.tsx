import { m } from 'motion/react';
import { DURATION, EASE } from './constants';

type Direction = 'up' | 'down' | 'left' | 'right' | 'none';

interface FadeInProps {
    children: React.ReactNode;
    direction?: Direction;
    delay?: number;
    duration?: number;
    className?: string;
}

const offsets: Record<Direction, { x?: number; y?: number }> = {
    up: { y: 12 },
    down: { y: -12 },
    left: { x: 12 },
    right: { x: -12 },
    none: {},
};

export function FadeIn({
    children,
    direction = 'up',
    delay = 0,
    duration = DURATION.normal,
    className,
}: FadeInProps) {
    const offset = offsets[direction];

    return (
        <m.div
            initial={{ opacity: 0, ...offset }}
            animate={{ opacity: 1, x: 0, y: 0 }}
            transition={{
                duration,
                delay,
                ease: EASE.out,
            }}
            className={className}
        >
            {children}
        </m.div>
    );
}
