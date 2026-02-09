import { AnimatePresence, m } from 'motion/react';
import { DURATION, EASE } from './constants';

interface CollapseSectionProps {
    isOpen: boolean;
    children: React.ReactNode;
    className?: string;
}

export function CollapseSection({ isOpen, children, className }: CollapseSectionProps) {
    return (
        <AnimatePresence initial={false}>
            {isOpen && (
                <m.div
                    initial={{ height: 0, opacity: 0 }}
                    animate={{ height: 'auto', opacity: 1 }}
                    exit={{ height: 0, opacity: 0 }}
                    transition={{
                        height: { duration: DURATION.normal, ease: EASE.inOut },
                        opacity: { duration: DURATION.fast, ease: EASE.out },
                    }}
                    style={{ overflow: 'hidden' }}
                    className={className}
                >
                    {children}
                </m.div>
            )}
        </AnimatePresence>
    );
}
