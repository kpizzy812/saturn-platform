import { useEffect, useRef, useState } from 'react';
import { m, useSpring, useTransform } from 'motion/react';

interface AnimatedNumberProps {
    value: number;
    className?: string;
    formatFn?: (n: number) => string;
}

export function AnimatedNumber({
    value,
    className,
    formatFn = (n) => Math.round(n).toString(),
}: AnimatedNumberProps) {
    const spring = useSpring(value, { stiffness: 100, damping: 20 });
    const display = useTransform(spring, (current) => formatFn(current));
    const [displayValue, setDisplayValue] = useState(formatFn(value));
    const isFirstRender = useRef(true);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        spring.set(value);
    }, [value, spring]);

    useEffect(() => {
        const unsubscribe = display.on('change', (v) => {
            setDisplayValue(v);
        });
        return unsubscribe;
    }, [display]);

    return <m.span className={className}>{displayValue}</m.span>;
}
