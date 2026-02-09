import { LazyMotion, domAnimation } from 'motion/react';

interface MotionProviderProps {
    children: React.ReactNode;
}

export function MotionProvider({ children }: MotionProviderProps) {
    return (
        <LazyMotion features={domAnimation} strict>
            {children}
        </LazyMotion>
    );
}
