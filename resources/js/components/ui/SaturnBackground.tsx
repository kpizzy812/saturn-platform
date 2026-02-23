interface SaturnBackgroundProps {
    variant?: 'prominent' | 'subtle';
}

export function SaturnBackground({ variant = 'subtle' }: SaturnBackgroundProps) {
    const isProminent = variant === 'prominent';

    return (
        <div
            aria-hidden="true"
            className="pointer-events-none fixed inset-0 z-0 overflow-hidden select-none"
        >
            {/* Glow */}
            <div
                className="absolute will-change-transform"
                style={{
                    bottom: isProminent ? '5%' : '10%',
                    right: isProminent ? '0%' : '-2%',
                    width: isProminent ? '80vmin' : '60vmin',
                    height: isProminent ? '80vmin' : '60vmin',
                    background: 'radial-gradient(circle, rgba(99,102,241,0.18) 0%, rgba(139,92,246,0.08) 40%, transparent 70%)',
                    filter: 'blur(60px)',
                }}
            />
            {/* Planet — dark theme */}
            <img
                src="/svgs/saturn.png"
                alt=""
                draggable={false}
                className="saturn-bg-float absolute hidden will-change-transform dark:block"
                style={{
                    bottom: isProminent ? '10%' : '15%',
                    right: isProminent ? '-3%' : '-3%',
                    width: isProminent ? '55vmin' : '40vmin',
                    maxWidth: isProminent ? '650px' : '450px',
                    opacity: isProminent ? 0.2 : 0.12,
                    filter: isProminent ? 'blur(1px) saturate(0.8)' : 'blur(1.5px) saturate(0.7)',
                }}
            />
            {/* Planet — light theme */}
            <img
                src="/svgs/saturn.png"
                alt=""
                draggable={false}
                className="saturn-bg-float absolute block will-change-transform dark:hidden"
                style={{
                    bottom: isProminent ? '10%' : '15%',
                    right: isProminent ? '-3%' : '-3%',
                    width: isProminent ? '55vmin' : '40vmin',
                    maxWidth: isProminent ? '650px' : '450px',
                    opacity: isProminent ? 0.12 : 0.07,
                    filter: isProminent
                        ? 'blur(1px) saturate(0.6) brightness(0.7)'
                        : 'blur(2px) saturate(0.5) brightness(0.6)',
                }}
            />
        </div>
    );
}
