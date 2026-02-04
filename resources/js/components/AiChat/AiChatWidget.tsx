import React, { useState, useCallback } from 'react';
import { MessageSquare, X, Minimize2, Maximize2 } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { cn } from '@/lib/utils';
import { useAiChat } from '@/hooks/useAiChat';
import { AiChatPanel } from './AiChatPanel';
import type { ChatContext } from '@/types/ai-chat';

interface AiChatWidgetProps {
    context?: ChatContext;
    defaultOpen?: boolean;
    position?: 'bottom-right' | 'bottom-left';
}

export function AiChatWidget({
    context,
    defaultOpen = false,
    position = 'bottom-right',
}: AiChatWidgetProps) {
    const [isOpen, setIsOpen] = useState(defaultOpen);
    const [isExpanded, setIsExpanded] = useState(false);

    const chat = useAiChat({ context, autoConnect: true });

    const handleToggle = useCallback(() => {
        if (!isOpen) {
            setIsOpen(true);
            setIsExpanded(false);
            // Create session on first open
            if (!chat.session && chat.status?.available) {
                chat.createSession();
            }
        } else {
            setIsOpen(false);
        }
    }, [isOpen, chat]);

    const handleToggleExpand = useCallback(() => {
        setIsExpanded((prev) => !prev);
    }, []);

    const handleClose = useCallback(() => {
        setIsOpen(false);
        setIsExpanded(false);
    }, []);

    // Don't render only if explicitly disabled (status checked and not available)
    // Show widget by default while status is loading
    if (chat.status !== null && chat.status.available === false) {
        return null;
    }

    const positionClasses =
        position === 'bottom-right' ? 'right-4 bottom-4' : 'left-4 bottom-4';

    return (
        <>
            {/* Floating Button */}
            {!isOpen && (
                <button
                    onClick={handleToggle}
                    className={cn(
                        'fixed z-50 flex h-14 w-14 items-center justify-center',
                        'rounded-full bg-primary text-white shadow-lg',
                        'transition-all duration-200 hover:scale-110 hover:shadow-glow-primary',
                        'focus:outline-none focus:ring-2 focus:ring-primary/50',
                        positionClasses
                    )}
                    title="Open AI Chat"
                >
                    <MessageSquare className="h-6 w-6" />
                </button>
            )}

            {/* Chat Panel */}
            {isOpen && (
                <div
                    className={cn(
                        'fixed z-50 flex flex-col',
                        'bg-background border border-white/10 rounded-xl shadow-2xl',
                        'transition-all duration-200',
                        isExpanded
                            ? 'inset-4 sm:inset-8'
                            : cn(positionClasses, 'h-[500px] w-[380px] sm:h-[600px] sm:w-[420px]')
                    )}
                >
                    {/* Header */}
                    <div
                        className={cn(
                            'flex items-center justify-between px-4 py-3',
                            'border-b border-white/10 rounded-t-xl',
                            'bg-background-secondary/50'
                        )}
                    >
                        <div className="flex items-center gap-2">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/20">
                                <MessageSquare className="h-4 w-4 text-primary" />
                            </div>
                            <div>
                                <h3 className="text-sm font-semibold text-foreground">
                                    Saturn AI
                                </h3>
                                <p className="text-xs text-foreground-muted">
                                    {chat.status?.provider || 'AI Assistant'}
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-1">
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                onClick={handleToggleExpand}
                                title={isExpanded ? 'Minimize' : 'Expand'}
                            >
                                {isExpanded ? (
                                    <Minimize2 className="h-4 w-4" />
                                ) : (
                                    <Maximize2 className="h-4 w-4" />
                                )}
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                onClick={handleClose}
                                title="Close"
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    {/* Content */}
                    <AiChatPanel
                        session={chat.session}
                        messages={chat.messages}
                        isLoading={chat.isLoading}
                        isSending={chat.isSending}
                        error={chat.error}
                        context={context}
                        onSendMessage={chat.sendMessage}
                        onConfirmCommand={chat.confirmCommand}
                        onRateMessage={chat.rateMessage}
                        onClearError={chat.clearError}
                    />
                </div>
            )}
        </>
    );
}

export default AiChatWidget;
