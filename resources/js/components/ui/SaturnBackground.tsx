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
            {/* Stars layer — dark theme only, masked to fade out over planet area (bottom-right) */}
            <div
                className="saturn-stars absolute inset-0 hidden dark:block"
                style={{
                    maskImage: 'radial-gradient(ellipse 60% 60% at 85% 85%, transparent 20%, black 60%)',
                    WebkitMaskImage: 'radial-gradient(ellipse 60% 60% at 85% 85%, transparent 20%, black 60%)',
                }}
            />
            <div
                className="saturn-stars-sm absolute inset-0 hidden dark:block"
                style={{
                    maskImage: 'radial-gradient(ellipse 60% 60% at 85% 85%, transparent 20%, black 60%)',
                    WebkitMaskImage: 'radial-gradient(ellipse 60% 60% at 85% 85%, transparent 20%, black 60%)',
                }}
            />

            {/* Nebula blobs — violet/indigo/purple only (Saturn brand) */}
            <div
                className="absolute hidden dark:block"
                style={{
                    top: '5%',
                    left: '8%',
                    width: isProminent ? '50vw' : '40vw',
                    height: isProminent ? '50vw' : '40vw',
                    maxWidth: '800px',
                    maxHeight: '800px',
                    background: 'radial-gradient(ellipse at center, rgba(139,92,246,0.18) 0%, rgba(99,102,241,0.08) 35%, rgba(124,58,237,0.03) 60%, transparent 75%)',
                    filter: 'blur(80px)',
                    animation: 'nebulaFloat 30s ease-in-out infinite',
                }}
            />
            <div
                className="absolute hidden dark:block"
                style={{
                    top: '50%',
                    right: '15%',
                    width: isProminent ? '45vw' : '32vw',
                    height: isProminent ? '45vw' : '32vw',
                    maxWidth: '700px',
                    maxHeight: '700px',
                    background: 'radial-gradient(ellipse at center, rgba(124,58,237,0.15) 0%, rgba(139,92,246,0.06) 40%, rgba(99,102,241,0.02) 60%, transparent 75%)',
                    filter: 'blur(70px)',
                    animation: 'nebulaFloat 25s ease-in-out infinite reverse',
                }}
            />
            <div
                className="absolute hidden dark:block"
                style={{
                    top: '25%',
                    left: '50%',
                    width: isProminent ? '35vw' : '26vw',
                    height: isProminent ? '35vw' : '26vw',
                    maxWidth: '550px',
                    maxHeight: '550px',
                    background: 'radial-gradient(ellipse at center, rgba(168,85,247,0.12) 0%, rgba(99,102,241,0.06) 40%, rgba(139,92,246,0.02) 60%, transparent 75%)',
                    filter: 'blur(60px)',
                    animation: 'nebulaFloat 35s ease-in-out infinite 5s',
                }}
            />
            {/* Deep violet accent — top-right corner */}
            <div
                className="absolute hidden dark:block"
                style={{
                    top: '-5%',
                    right: '5%',
                    width: isProminent ? '35vw' : '25vw',
                    height: isProminent ? '35vw' : '25vw',
                    maxWidth: '500px',
                    maxHeight: '500px',
                    background: 'radial-gradient(ellipse at center, rgba(109,40,217,0.12) 0%, rgba(139,92,246,0.05) 40%, transparent 70%)',
                    filter: 'blur(80px)',
                    animation: 'nebulaFloat 28s ease-in-out infinite 3s',
                }}
            />

            {/* Primary glow — bottom right */}
            <div
                className="absolute hidden dark:block will-change-transform"
                style={{
                    bottom: '-5%',
                    right: '-5%',
                    width: isProminent ? '90vmin' : '70vmin',
                    height: isProminent ? '90vmin' : '70vmin',
                    background: 'radial-gradient(circle, rgba(99,102,241,0.20) 0%, rgba(139,92,246,0.10) 35%, rgba(124,58,237,0.03) 60%, transparent 75%)',
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
