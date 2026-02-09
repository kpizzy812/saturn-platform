import { useReducedMotion as useMotionReducedMotion } from 'motion/react';

// Re-export motion's useReducedMotion with a consistent API
export function useReducedMotion(): boolean {
    return useMotionReducedMotion() ?? false;
}
