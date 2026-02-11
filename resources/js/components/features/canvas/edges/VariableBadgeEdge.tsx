import { memo, useState, useRef } from 'react';
import { createPortal } from 'react-dom';
import { BaseEdge, EdgeLabelRenderer, getBezierPath, type Position } from '@xyflow/react';
import { cn } from '@/lib/utils';
import { Link2, Zap, ArrowRight, ArrowLeft, Globe, Container } from 'lucide-react';

interface ResourceLink {
    id: number;
    env_key: string;
    auto_inject: boolean;
    source_name?: string;
    target_name?: string;
    target_type: string;
    use_external_url?: boolean;
}

interface VariableBadgeEdgeData {
    linkId: number;
    link: ResourceLink;
    reverseLinkId?: number;
    reverseLink?: ResourceLink;
}

interface VariableBadgeEdgeProps {
    id: string;
    sourceX: number;
    sourceY: number;
    targetX: number;
    targetY: number;
    sourcePosition: Position;
    targetPosition: Position;
    data?: VariableBadgeEdgeData;
    selected?: boolean;
    style?: React.CSSProperties;
}

export const VariableBadgeEdge = memo(({
    id,
    sourceX,
    sourceY,
    targetX,
    targetY,
    sourcePosition,
    targetPosition,
    data,
    selected,
    style,
}: VariableBadgeEdgeProps) => {
    const [isHovered, setIsHovered] = useState(false);
    const badgeRef = useRef<HTMLDivElement>(null);
    const [tooltipPosition, setTooltipPosition] = useState<{ x: number; y: number } | null>(null);

    // Update tooltip position when hovered
    const updateTooltipPosition = () => {
        if (badgeRef.current) {
            const rect = badgeRef.current.getBoundingClientRect();
            setTooltipPosition({
                x: rect.left + rect.width / 2,
                y: rect.bottom + 8,
            });
        }
    };

    // Use bezier path for smooth curves
    const [edgePath, labelX, labelY] = getBezierPath({
        sourceX,
        sourceY,
        sourcePosition,
        targetX,
        targetY,
        targetPosition,
        curvature: 0.25,
    });

    const link = data?.link;
    const reverseLink = data?.reverseLink;
    const isBidirectional = !!reverseLink;
    const isAutoInject = link?.auto_inject || reverseLink?.auto_inject;
    const isDbConnection = link?.target_type && link.target_type !== 'application';
    const isAppToApp = link?.target_type === 'application';
    const isExternal = link?.use_external_url ?? false;

    // Determine edge color
    const getEdgeColor = () => {
        if (selected) return '#7c3aed';
        if (isDbConnection && isAutoInject) return '#22c55e';
        if (isBidirectional) return '#7c3aed';
        if (!isAutoInject) return '#4a4a5e';
        return '#7c3aed';
    };

    const edgeColor = getEdgeColor();

    return (
        <>
            {/* Glow effect layer (behind main edge) */}
            <path
                d={edgePath}
                fill="none"
                stroke={edgeColor}
                strokeWidth={selected ? 8 : isHovered ? 6 : 0}
                strokeOpacity={0.3}
                style={{ transition: 'stroke-width 0.15s ease, stroke-opacity 0.15s ease' }}
                className="pointer-events-none"
            />

            {/* Main edge path */}
            <BaseEdge
                id={id}
                path={edgePath}
                style={{
                    ...style,
                    stroke: edgeColor,
                    strokeWidth: selected ? 3 : isHovered ? 2.5 : 2,
                    transition: 'stroke 0.15s ease, stroke-width 0.15s ease',
                }}
                interactionWidth={20}
            />

            {/* Invisible wider path for hover detection */}
            <path
                d={edgePath}
                fill="none"
                stroke="transparent"
                strokeWidth={24}
                onMouseEnter={() => setIsHovered(true)}
                onMouseLeave={() => setIsHovered(false)}
                className="cursor-pointer"
            />

            {/* Badge Label */}
            {link && (
                <EdgeLabelRenderer>
                    <div
                        style={{
                            position: 'absolute',
                            transform: `translate(-50%, -50%) translate(${labelX}px, ${labelY}px)`,
                            pointerEvents: 'all',
                        }}
                        className="nodrag nopan"
                        onMouseEnter={() => {
                            setIsHovered(true);
                            updateTooltipPosition();
                        }}
                        onMouseLeave={() => setIsHovered(false)}
                        ref={badgeRef}
                    >
                        {/* Main Badge */}
                        <div
                            className={cn(
                                'relative group rounded-lg border transition-all duration-200',
                                'bg-background-secondary/95 backdrop-blur-sm shadow-lg',
                                selected
                                    ? 'border-primary shadow-[0_0_12px_rgba(124,58,237,0.4)] scale-105'
                                    : isHovered
                                        ? 'border-primary/60 shadow-[0_0_8px_rgba(124,58,237,0.3)] scale-102'
                                        : 'border-border hover:border-primary/40'
                            )}
                        >
                            {/* Badge Content */}
                            <div className="px-2.5 py-1.5">
                                {isBidirectional ? (
                                    // Bidirectional: two rows with external/internal indicator
                                    <div className="flex flex-col gap-1">
                                        <div className="flex items-center gap-1.5">
                                            <ArrowLeft className="w-3 h-3 text-primary flex-shrink-0" />
                                            <code className="text-[10px] font-mono text-success truncate max-w-[120px]">
                                                {link.env_key}
                                            </code>
                                            {isAppToApp && (
                                                isExternal
                                                    ? <Globe className="w-2.5 h-2.5 text-sky-400 flex-shrink-0" />
                                                    : <Container className="w-2.5 h-2.5 text-foreground-subtle flex-shrink-0" />
                                            )}
                                        </div>
                                        <div className="flex items-center gap-1.5">
                                            <ArrowRight className="w-3 h-3 text-pink-500 flex-shrink-0" />
                                            <code className="text-[10px] font-mono text-success truncate max-w-[120px]">
                                                {reverseLink.env_key}
                                            </code>
                                        </div>
                                    </div>
                                ) : (
                                    // Single direction
                                    <div className="flex items-center gap-1.5">
                                        <Link2 className={cn(
                                            'w-3 h-3 flex-shrink-0',
                                            isDbConnection ? 'text-success' : 'text-primary'
                                        )} />
                                        <code className="text-[10px] font-mono text-success truncate max-w-[120px]">
                                            {link.env_key}
                                        </code>
                                        {isAppToApp && (
                                            isExternal
                                                ? <Globe className="w-2.5 h-2.5 text-sky-400 flex-shrink-0" />
                                                : <Container className="w-2.5 h-2.5 text-foreground-subtle flex-shrink-0" />
                                        )}
                                        {isAutoInject && (
                                            <Zap className="w-3 h-3 text-warning flex-shrink-0" />
                                        )}
                                    </div>
                                )}
                            </div>

                        </div>

                        {/* Tooltip rendered via Portal to appear above nodes */}
                        {isHovered && tooltipPosition && createPortal(
                            <div
                                className="fixed pointer-events-none animate-in fade-in duration-200"
                                style={{
                                    left: tooltipPosition.x,
                                    top: tooltipPosition.y,
                                    transform: 'translateX(-50%)',
                                    zIndex: 99999,
                                }}
                            >
                                <div className="bg-background border border-border rounded-lg shadow-xl p-3 min-w-[200px]">
                                    <div className="text-[10px] uppercase tracking-wider text-foreground-muted font-medium mb-2">
                                        {isBidirectional ? 'Bidirectional Link' : 'Environment Variable'}
                                    </div>

                                    <div className="space-y-2">
                                        {/* First variable: URL from target injected into source */}
                                        <div className="flex items-start gap-2">
                                            <div className={cn(
                                                'w-1.5 h-1.5 rounded-full mt-1.5 flex-shrink-0',
                                                link.auto_inject ? 'bg-success' : 'bg-foreground-subtle'
                                            )} />
                                            <div className="flex-1 min-w-0">
                                                <code className="text-xs font-mono text-primary block truncate">
                                                    {link.env_key}
                                                </code>
                                                <div className="text-[10px] text-foreground-muted mt-0.5">
                                                    {link.target_name || 'Target'} → {link.source_name || 'Source'}
                                                </div>
                                            </div>
                                        </div>

                                        {/* Second variable (if bidirectional): URL from target injected into source */}
                                        {reverseLink && (
                                            <div className="flex items-start gap-2">
                                                <div className={cn(
                                                    'w-1.5 h-1.5 rounded-full mt-1.5 flex-shrink-0',
                                                    reverseLink.auto_inject ? 'bg-success' : 'bg-foreground-subtle'
                                                )} />
                                                <div className="flex-1 min-w-0">
                                                    <code className="text-xs font-mono text-pink-500 block truncate">
                                                        {reverseLink.env_key}
                                                    </code>
                                                    <div className="text-[10px] text-foreground-muted mt-0.5">
                                                        {reverseLink.target_name || 'Target'} → {reverseLink.source_name || 'Source'}
                                                    </div>
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    {/* Status */}
                                    <div className="mt-2 pt-2 border-t border-border space-y-1.5">
                                        <div className="flex items-center gap-2">
                                            <div className={cn(
                                                'w-2 h-2 rounded-full',
                                                isAutoInject ? 'bg-success animate-pulse' : 'bg-foreground-subtle'
                                            )} />
                                            <span className="text-[10px] text-foreground-muted">
                                                {isAutoInject ? 'Auto-inject enabled' : 'Manual injection'}
                                            </span>
                                        </div>
                                        {isAppToApp && (
                                            <div className="flex items-center gap-2">
                                                {isExternal
                                                    ? <Globe className="w-3 h-3 text-sky-400" />
                                                    : <Container className="w-3 h-3 text-foreground-subtle" />
                                                }
                                                <span className="text-[10px] text-foreground-muted">
                                                    {isExternal ? 'External URL (domain)' : 'Internal URL (Docker)'}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>,
                            document.body
                        )}
                    </div>
                </EdgeLabelRenderer>
            )}
        </>
    );
});

VariableBadgeEdge.displayName = 'VariableBadgeEdge';
