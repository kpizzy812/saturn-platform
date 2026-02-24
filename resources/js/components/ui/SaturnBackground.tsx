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
            {/* Stars layer — dark theme only */}
            <div className="saturn-stars absolute inset-0 hidden dark:block" />
            <div className="saturn-stars-sm absolute inset-0 hidden dark:block" />

            {/* Nebula blobs — soft purple/violet glows */}
            <div
                className="absolute hidden dark:block"
                style={{
                    top: '8%',
                    left: '12%',
                    width: isProminent ? '45vw' : '35vw',
                    height: isProminent ? '45vw' : '35vw',
                    maxWidth: '700px',
                    maxHeight: '700px',
                    background: 'radial-gradient(ellipse at center, rgba(139,92,246,0.08) 0%, rgba(99,102,241,0.03) 40%, transparent 70%)',
                    filter: 'blur(80px)',
                    animation: 'nebulaFloat 30s ease-in-out infinite',
                }}
            />
            <div
                className="absolute hidden dark:block"
                style={{
                    top: '55%',
                    right: '20%',
                    width: isProminent ? '40vw' : '28vw',
                    height: isProminent ? '40vw' : '28vw',
                    maxWidth: '600px',
                    maxHeight: '600px',
                    background: 'radial-gradient(ellipse at center, rgba(124,58,237,0.06) 0%, rgba(139,92,246,0.02) 45%, transparent 70%)',
                    filter: 'blur(70px)',
                    animation: 'nebulaFloat 25s ease-in-out infinite reverse',
                }}
            />
            <div
                className="absolute hidden dark:block"
                style={{
                    top: '30%',
                    left: '55%',
                    width: isProminent ? '30vw' : '22vw',
                    height: isProminent ? '30vw' : '22vw',
                    maxWidth: '500px',
                    maxHeight: '500px',
                    background: 'radial-gradient(ellipse at center, rgba(99,102,241,0.05) 0%, rgba(168,85,247,0.02) 50%, transparent 70%)',
                    filter: 'blur(60px)',
                    animation: 'nebulaFloat 35s ease-in-out infinite 5s',
                }}
            />

            {/* Primary glow — bottom right */}
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

            {/* Planet — light theme: natural colors, no desaturation */}
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
                        opacity: isProminent ? 0.14 : 0.1,
                    }}
                />
            </div>
        </div>
    );
}
