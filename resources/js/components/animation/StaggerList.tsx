import { m } from 'motion/react';
import { DURATION, EASE, MAX_STAGGER_ITEMS } from './constants';

interface StaggerListProps {
    children: React.ReactNode;
    className?: string;
    staggerDelay?: number;
}

const containerVariants = {
    hidden: {},
    visible: {
        transition: {
            staggerChildren: 0.06,
        },
    },
};

export function StaggerList({
    children,
    className,
    staggerDelay = 0.06,
}: StaggerListProps) {
    const variants = staggerDelay !== 0.06
        ? { hidden: {}, visible: { transition: { staggerChildren: staggerDelay } } }
        : containerVariants;

    return (
        <m.div
            variants={variants}
            initial="hidden"
            animate="visible"
            className={className}
        >
            {children}
        </m.div>
    );
}

interface StaggerItemProps {
    children: React.ReactNode;
    className?: string;
    index?: number;
}

const itemVariants = {
    hidden: { opacity: 0, y: 12 },
    visible: {
        opacity: 1,
        y: 0,
        transition: {
            duration: DURATION.normal,
            ease: EASE.out,
        },
    },
};

const instantVariants = {
    hidden: { opacity: 1, y: 0 },
    visible: { opacity: 1, y: 0 },
};

export function StaggerItem({ children, className, index }: StaggerItemProps) {
    // Items beyond MAX_STAGGER_ITEMS render instantly
    const shouldAnimate = index === undefined || index < MAX_STAGGER_ITEMS;

    return (
        <m.div
            variants={shouldAnimate ? itemVariants : instantVariants}
            className={className}
        >
            {children}
        </m.div>
    );
}
