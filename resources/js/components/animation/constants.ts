// Animation timing constants synced with CSS design tokens
export const DURATION = {
    fast: 0.15,
    normal: 0.2,
    slow: 0.3,
} as const;

export const EASE = {
    out: [0, 0, 0.2, 1] as const,
    inOut: [0.4, 0, 0.2, 1] as const,
    bounce: [0.68, -0.55, 0.265, 1.55] as const,
} as const;

// Maximum number of items to stagger animate (rest render instantly)
export const MAX_STAGGER_ITEMS = 12;
