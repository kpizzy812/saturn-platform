interface SaturnBackgroundProps {
    variant?: 'prominent' | 'subtle';
}

export function SaturnBackground({ variant = 'subtle' }: SaturnBackgroundProps) {
    const isProminent = variant === 'prominent';

    const size = isProminent ? '110vmin' : '85vmin';
    const maxW = isProminent ? '1400px' : '1100px';

    return (
        <div
            aria-hidden="true"
            className="pointer-events-none fixed inset-0 z-0 overflow-hidden select-none"
        >
            {/* Glow */}
            <div
                className="absolute will-change-transform"
                style={{
                    bottom: '-5%',
                    right: '-5%',
                    width: isProminent ? '90vmin' : '70vmin',
                    height: isProminent ? '90vmin' : '70vmin',
                    background: 'radial-gradient(circle, rgba(99,102,241,0.15) 0%, rgba(139,92,246,0.06) 40%, transparent 70%)',
                    filter: 'blur(60px)',
                }}
            />

            {/* Planet — dark theme: diagonal gradient blur via mask */}
            <div
                className="absolute hidden dark:block"
                style={{
                    bottom: '-8%',
                    right: '-10%',
                    width: size,
                    maxWidth: maxW,
                    aspectRatio: '1',
                }}
            >
                {/* Sharp half (bottom-right) */}
                <img
                    src="/svgs/saturn.png"
                    alt=""
                    draggable={false}
                    className="saturn-bg-float absolute inset-0 h-full w-full object-contain will-change-transform"
                    style={{
                        opacity: isProminent ? 0.15 : 0.09,
                        filter: 'saturate(0.7)',
                        maskImage: 'linear-gradient(135deg, transparent 30%, black 70%)',
                        WebkitMaskImage: 'linear-gradient(135deg, transparent 30%, black 70%)',
                    }}
                />
                {/* Blurred half (top-left) */}
                <img
                    src="/svgs/saturn.png"
                    alt=""
                    draggable={false}
                    className="saturn-bg-float absolute inset-0 h-full w-full object-contain will-change-transform"
                    style={{
                        opacity: isProminent ? 0.15 : 0.09,
                        filter: 'blur(8px) saturate(0.5)',
                        maskImage: 'linear-gradient(135deg, black 30%, transparent 70%)',
                        WebkitMaskImage: 'linear-gradient(135deg, black 30%, transparent 70%)',
                    }}
                />
            </div>

            {/* Planet — light theme: larger, more detail, subtle tint */}
            <div
                className="absolute block dark:hidden"
                style={{
                    bottom: '-8%',
                    right: '-10%',
                    width: size,
                    maxWidth: maxW,
                    aspectRatio: '1',
                }}
            >
                <img
                    src="/svgs/saturn.png"
                    alt=""
                    draggable={false}
                    className="saturn-bg-float absolute inset-0 h-full w-full object-contain will-change-transform"
                    style={{
                        opacity: isProminent ? 0.08 : 0.05,
                        filter: 'saturate(0.4) brightness(0.5) contrast(1.1)',
                    }}
                />
            </div>
        </div>
    );
}
