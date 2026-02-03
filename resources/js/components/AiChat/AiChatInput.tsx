import React, { useState, useRef, useCallback } from 'react';
import { Send, Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { AiChatMessage } from '@/types/ai-chat';

interface AiChatInputProps {
    onSend: (content: string) => Promise<AiChatMessage | null>;
    disabled?: boolean;
    isSending?: boolean;
    placeholder?: string;
}

export function AiChatInput({
    onSend,
    disabled = false,
    isSending = false,
    placeholder = 'Ask me anything...',
}: AiChatInputProps) {
    const [value, setValue] = useState('');
    const textareaRef = useRef<HTMLTextAreaElement>(null);

    const handleSubmit = useCallback(
        async (e?: React.FormEvent) => {
            e?.preventDefault();
            const trimmed = value.trim();
            if (!trimmed || disabled || isSending) return;

            setValue('');
            // Reset textarea height
            if (textareaRef.current) {
                textareaRef.current.style.height = 'auto';
            }
            await onSend(trimmed);
        },
        [value, disabled, isSending, onSend]
    );

    const handleKeyDown = useCallback(
        (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                handleSubmit();
            }
        },
        [handleSubmit]
    );

    const handleChange = useCallback(
        (e: React.ChangeEvent<HTMLTextAreaElement>) => {
            setValue(e.target.value);
            // Auto-resize textarea
            if (textareaRef.current) {
                textareaRef.current.style.height = 'auto';
                textareaRef.current.style.height = `${Math.min(
                    textareaRef.current.scrollHeight,
                    120
                )}px`;
            }
        },
        []
    );

    const isDisabled = disabled || isSending;
    const canSend = value.trim().length > 0 && !isDisabled;

    return (
        <form onSubmit={handleSubmit} className="relative">
            <textarea
                ref={textareaRef}
                value={value}
                onChange={handleChange}
                onKeyDown={handleKeyDown}
                placeholder={placeholder}
                disabled={isDisabled}
                rows={1}
                className={cn(
                    'w-full resize-none rounded-xl',
                    'bg-background-secondary/50 border border-white/10',
                    'px-4 py-3 pr-12',
                    'text-sm text-foreground placeholder:text-foreground-muted',
                    'focus:border-primary/50 focus:outline-none focus:ring-1 focus:ring-primary/50',
                    'disabled:cursor-not-allowed disabled:opacity-50',
                    'transition-all duration-200'
                )}
                style={{ minHeight: '48px', maxHeight: '120px' }}
            />
            <button
                type="submit"
                disabled={!canSend}
                className={cn(
                    'absolute bottom-3 right-3',
                    'flex h-8 w-8 items-center justify-center rounded-lg',
                    'transition-all duration-200',
                    canSend
                        ? 'bg-primary text-white hover:bg-primary-hover'
                        : 'bg-white/5 text-foreground-muted',
                    'disabled:cursor-not-allowed'
                )}
            >
                {isSending ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                    <Send className="h-4 w-4" />
                )}
            </button>
        </form>
    );
}
