interface SaturnBackgroundProps {
    /** Visual intensity: 'prominent' for auth pages, 'subtle' for app pages */
    variant?: 'prominent' | 'subtle';
}

/**
 * Decorative Saturn planet background element.
 * GPU-accelerated, non-interactive, accessible-hidden.
 */
export function SaturnBackground({ variant = 'subtle' }: SaturnBackgroundProps) {
    const isProminent = variant === 'prominent';

    return (
        <div
            aria-hidden="true"
            className="pointer-events-none fixed inset-0 z-0 overflow-hidden select-none"
        >
            {/* Ambient glow behind the planet */}
            <div
                className="absolute will-change-transform"
                style={{
                    ...(isProminent
                        ? {
                              bottom: '-10%',
                              right: '-5%',
                              width: '70vmin',
                              height: '70vmin',
                          }
                        : {
                              bottom: '-15%',
                              right: '-10%',
                              width: '50vmin',
                              height: '50vmin',
                          }),
                    background:
                        'radial-gradient(circle, rgba(99, 102, 241, 0.12) 0%, rgba(139, 92, 246, 0.06) 40%, transparent 70%)',
                    filter: 'blur(60px)',
                }}
            />

            {/* Saturn planet image */}
            <img
                src="/svgs/saturn.png"
                alt=""
                draggable={false}
                loading="lazy"
                className="saturn-bg-float absolute will-change-transform"
                style={{
                    ...(isProminent
                        ? {
                              bottom: '-8%',
                              right: '-3%',
                              width: '55vmin',
                              maxWidth: '600px',
                              opacity: 0.08,
                              filter: 'blur(2px) saturate(0.7)',
                          }
                        : {
                              bottom: '-12%',
                              right: '-8%',
                              width: '40vmin',
                              maxWidth: '450px',
                              opacity: 0.04,
                              filter: 'blur(3px) saturate(0.5)',
                          }),
                }}
            />
        </div>
    );
}
