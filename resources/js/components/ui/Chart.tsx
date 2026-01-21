import * as React from 'react';
import { cn } from '@/lib/utils';

interface DataPoint {
    label: string;
    value: number;
}

interface ChartProps {
    data: DataPoint[];
    height?: number;
    color?: string;
    className?: string;
}

export function LineChart({ data, height = 200, color = 'rgb(99, 102, 241)', className }: ChartProps) {
    const maxValue = Math.max(...data.map(d => d.value), 1);
    const points = data.map((d, i) => {
        const x = (i / (data.length - 1)) * 100;
        const y = 100 - (d.value / maxValue) * 100;
        return `${x},${y}`;
    }).join(' ');

    return (
        <div className={cn('relative', className)} style={{ height }}>
            <svg width="100%" height="100%" preserveAspectRatio="none" className="overflow-visible">
                {/* Grid lines */}
                <g className="opacity-10">
                    {[0, 25, 50, 75, 100].map((y) => (
                        <line
                            key={y}
                            x1="0"
                            y1={`${y}%`}
                            x2="100%"
                            y2={`${y}%`}
                            stroke="currentColor"
                            strokeWidth="1"
                        />
                    ))}
                </g>

                {/* Area fill */}
                <polygon
                    points={`0,100 ${points} 100,100`}
                    fill={color}
                    fillOpacity="0.1"
                />

                {/* Line */}
                <polyline
                    points={points}
                    fill="none"
                    stroke={color}
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />

                {/* Points */}
                {data.map((d, i) => {
                    const x = (i / (data.length - 1)) * 100;
                    const y = 100 - (d.value / maxValue) * 100;
                    return (
                        <circle
                            key={i}
                            cx={`${x}%`}
                            cy={`${y}%`}
                            r="3"
                            fill={color}
                            className="transition-all hover:r-5"
                        />
                    );
                })}
            </svg>
        </div>
    );
}

export function BarChart({ data, height = 200, color = 'rgb(99, 102, 241)', className }: ChartProps) {
    const maxValue = Math.max(...data.map(d => d.value), 1);
    const barWidth = 100 / data.length;

    return (
        <div className={cn('relative', className)} style={{ height }}>
            <svg width="100%" height="100%" className="overflow-visible">
                {/* Grid lines */}
                <g className="opacity-10">
                    {[0, 25, 50, 75, 100].map((y) => (
                        <line
                            key={y}
                            x1="0"
                            y1={`${y}%`}
                            x2="100%"
                            y2={`${y}%`}
                            stroke="currentColor"
                            strokeWidth="1"
                        />
                    ))}
                </g>

                {/* Bars */}
                {data.map((d, i) => {
                    const barHeight = (d.value / maxValue) * 100;
                    const x = i * barWidth;
                    return (
                        <g key={i}>
                            <rect
                                x={`${x + barWidth * 0.1}%`}
                                y={`${100 - barHeight}%`}
                                width={`${barWidth * 0.8}%`}
                                height={`${barHeight}%`}
                                fill={color}
                                fillOpacity="0.8"
                                className="transition-all hover:fill-opacity-100"
                                rx="2"
                            />
                        </g>
                    );
                })}
            </svg>

            {/* Labels */}
            <div className="mt-2 flex justify-between text-xs text-foreground-muted">
                {data.map((d, i) => (
                    <span key={i} className="flex-1 text-center">
                        {d.label}
                    </span>
                ))}
            </div>
        </div>
    );
}

interface SparklineProps {
    data: number[];
    color?: string;
    className?: string;
}

export function Sparkline({ data, color = 'rgb(99, 102, 241)', className }: SparklineProps) {
    const maxValue = Math.max(...data, 1);
    const points = data.map((value, i) => {
        const x = (i / (data.length - 1)) * 100;
        const y = 100 - (value / maxValue) * 100;
        return `${x},${y}`;
    }).join(' ');

    return (
        <svg width="100%" height="32" preserveAspectRatio="none" className={cn('overflow-visible', className)}>
            <polyline
                points={points}
                fill="none"
                stroke={color}
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
