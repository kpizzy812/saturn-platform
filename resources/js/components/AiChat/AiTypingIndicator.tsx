import { Bot } from 'lucide-react';
import { cn } from '@/lib/utils';

interface AiTypingIndicatorProps {
    className?: string;
}

export function AiTypingIndicator({ className }: AiTypingIndicatorProps) {
    return (
        <div className={cn('flex gap-3', className)}>
            {/* Avatar */}
            <div className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-background-tertiary text-foreground-muted">
                <Bot className="h-4 w-4" />
            </div>

            {/* Typing dots */}
            <div className="flex items-center rounded-xl bg-background-secondary/80 px-4 py-3">
                <div className="flex items-center gap-1">
                    <span
                        className="h-2 w-2 animate-bounce rounded-full bg-foreground-muted"
                        style={{ animationDelay: '0ms' }}
                    />
                    <span
                        className="h-2 w-2 animate-bounce rounded-full bg-foreground-muted"
                        style={{ animationDelay: '150ms' }}
                    />
                    <span
                        className="h-2 w-2 animate-bounce rounded-full bg-foreground-muted"
                        style={{ animationDelay: '300ms' }}
                    />
                </div>
            </div>
        </div>
    );
}
