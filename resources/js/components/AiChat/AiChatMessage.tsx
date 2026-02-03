import React, { useState } from 'react';
import {
    User,
    Bot,
    Star,
    Play,
    CheckCircle,
    XCircle,
    Loader2,
    Clock,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/Button';
import type { AiChatMessage as MessageType } from '@/types/ai-chat';

interface AiChatMessageProps {
    message: MessageType;
    onConfirm?: (intent: string, params: Record<string, unknown>) => void;
    onRate?: (rating: number) => void;
}

export function AiChatMessage({ message, onConfirm, onRate }: AiChatMessageProps) {
    const [showRating, setShowRating] = useState(false);
    const [hoveredRating, setHoveredRating] = useState(0);

    const isUser = message.role === 'user';
    const isAssistant = message.role === 'assistant';

    // Check if message requires confirmation
    const needsConfirmation =
        message.content.includes('confirm') &&
        message.intent &&
        !message.command_status;

    const handleConfirm = () => {
        if (message.intent && onConfirm) {
            onConfirm(message.intent, message.intent_params || {});
        }
    };

    const handleRate = (rating: number) => {
        if (onRate) {
            onRate(rating);
            setShowRating(false);
        }
    };

    const renderCommandStatus = () => {
        if (!message.command_status) return null;

        const statusConfig: Record<string, { icon: typeof Clock; color: string; label: string; animate?: boolean }> = {
            pending: {
                icon: Clock,
                color: 'text-yellow-500',
                label: 'Pending...',
            },
            executing: {
                icon: Loader2,
                color: 'text-blue-500',
                label: 'Executing...',
                animate: true,
            },
            completed: {
                icon: CheckCircle,
                color: 'text-green-500',
                label: 'Completed',
            },
            failed: {
                icon: XCircle,
                color: 'text-red-500',
                label: 'Failed',
            },
        };

        const config = statusConfig[message.command_status];
        if (!config) return null;

        const Icon = config.icon;

        return (
            <div
                className={cn(
                    'mt-2 flex items-center gap-2 text-xs',
                    config.color
                )}
            >
                <Icon
                    className={cn('h-4 w-4', config.animate && 'animate-spin')}
                />
                <span>{config.label}</span>
                {message.intent_label && (
                    <span className="rounded bg-white/10 px-1.5 py-0.5">
                        {message.intent_label}
                    </span>
                )}
            </div>
        );
    };

    return (
        <div
            className={cn(
                'flex gap-3',
                isUser && 'flex-row-reverse'
            )}
        >
            {/* Avatar */}
            <div
                className={cn(
                    'flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg',
                    isUser
                        ? 'bg-primary/20 text-primary'
                        : 'bg-background-tertiary text-foreground-muted'
                )}
            >
                {isUser ? (
                    <User className="h-4 w-4" />
                ) : (
                    <Bot className="h-4 w-4" />
                )}
            </div>

            {/* Content */}
            <div
                className={cn(
                    'flex max-w-[80%] flex-col',
                    isUser && 'items-end'
                )}
            >
                <div
                    className={cn(
                        'rounded-xl px-4 py-2.5',
                        isUser
                            ? 'bg-primary text-white'
                            : 'bg-background-secondary/80 text-foreground'
                    )}
                >
                    {/* Message content with markdown-like formatting */}
                    <div className="prose prose-sm prose-invert max-w-none break-words">
                        {message.content.split('\n').map((line, i) => (
                            <p key={i} className={cn('mb-1 last:mb-0', !line && 'h-2')}>
                                {line.split(/(\*\*[^*]+\*\*|`[^`]+`)/g).map((part, j) => {
                                    if (part.startsWith('**') && part.endsWith('**')) {
                                        return (
                                            <strong key={j}>
                                                {part.slice(2, -2)}
                                            </strong>
                                        );
                                    }
                                    if (part.startsWith('`') && part.endsWith('`')) {
                                        return (
                                            <code
                                                key={j}
                                                className="rounded bg-black/30 px-1 py-0.5 text-xs"
                                            >
                                                {part.slice(1, -1)}
                                            </code>
                                        );
                                    }
                                    return part;
                                })}
                            </p>
                        ))}
                    </div>

                    {/* Command status */}
                    {renderCommandStatus()}

                    {/* Command result */}
                    {message.command_result && (
                        <div className="mt-2 rounded bg-black/20 p-2 text-xs">
                            <pre className="whitespace-pre-wrap font-mono">
                                {message.command_result}
                            </pre>
                        </div>
                    )}
                </div>

                {/* Action buttons */}
                {isAssistant && (
                    <div className="mt-2 flex items-center gap-2">
                        {/* Confirmation button */}
                        {needsConfirmation && (
                            <Button
                                size="sm"
                                variant="primary"
                                onClick={handleConfirm}
                                className="h-7 gap-1 text-xs"
                            >
                                <Play className="h-3 w-3" />
                                Confirm
                            </Button>
                        )}

                        {/* Rating */}
                        {!message.rating && !showRating && (
                            <button
                                onClick={() => setShowRating(true)}
                                className="text-xs text-foreground-muted hover:text-foreground"
                            >
                                Rate this response
                            </button>
                        )}

                        {showRating && !message.rating && (
                            <div className="flex items-center gap-1">
                                {[1, 2, 3, 4, 5].map((rating) => (
                                    <button
                                        key={rating}
                                        onClick={() => handleRate(rating)}
                                        onMouseEnter={() => setHoveredRating(rating)}
                                        onMouseLeave={() => setHoveredRating(0)}
                                        className="p-0.5"
                                    >
                                        <Star
                                            className={cn(
                                                'h-4 w-4 transition-colors',
                                                rating <= hoveredRating
                                                    ? 'fill-yellow-400 text-yellow-400'
                                                    : 'text-foreground-muted'
                                            )}
                                        />
                                    </button>
                                ))}
                            </div>
                        )}

                        {message.rating && (
                            <div className="flex items-center gap-1 text-xs text-foreground-muted">
                                <span>Rated:</span>
                                <div className="flex">
                                    {[1, 2, 3, 4, 5].map((rating) => (
                                        <Star
                                            key={rating}
                                            className={cn(
                                                'h-3 w-3',
                                                rating <= message.rating!
                                                    ? 'fill-yellow-400 text-yellow-400'
                                                    : 'text-foreground-muted'
                                            )}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* Timestamp */}
                <span className="mt-1 text-[10px] text-foreground-muted">
                    {new Date(message.created_at).toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit',
                    })}
                </span>
            </div>
        </div>
    );
}
