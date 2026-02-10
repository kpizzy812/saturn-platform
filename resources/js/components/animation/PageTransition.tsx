import { usePage } from '@inertiajs/react';
import { AnimatePresence, m } from 'motion/react';
import { DURATION, EASE } from './constants';

interface PageTransitionProps {
    children: React.ReactNode;
}

export function PageTransition({ children }: PageTransitionProps) {
    const { url } = usePage();

    return (
        <AnimatePresence mode="popLayout" initial={false}>
            <m.div
                key={url}
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                exit={{ opacity: 0 }}
                transition={{
                    duration: DURATION.fast,
                    ease: EASE.out,
                }}
            >
                {children}
            </m.div>
        </AnimatePresence>
    );
}
