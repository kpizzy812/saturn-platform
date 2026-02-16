import { useRef, useEffect } from 'react';
import { AlertCircle, Sparkles } from 'lucide-react';
import type { AiChatSession, AiChatMessage, ChatContext, ConfirmCommandOptions } from '@/types/ai-chat';
import { AiChatMessage as MessageComponent } from './AiChatMessage';
import { AiChatInput } from './AiChatInput';
import { AiChatSuggestions } from './AiChatSuggestions';
import { AiTypingIndicator } from './AiTypingIndicator';

interface AiChatPanelProps {
    session: AiChatSession | null;
    messages: AiChatMessage[];
    isLoading: boolean;
    isSending: boolean;
    error: string | null;
    context?: ChatContext;
    onSendMessage: (content: string) => Promise<AiChatMessage | null>;
    onConfirmCommand: (options: ConfirmCommandOptions) => Promise<void>;
    onRateMessage: (uuid: string, rating: number) => Promise<void>;
    onClearError: () => void;
}

export function AiChatPanel({
    session,
    messages,
    isLoading,
    isSending,
    error,
    context,
    onSendMessage,
    onConfirmCommand,
    onRateMessage,
    onClearError,
}: AiChatPanelProps) {
    const messagesEndRef = useRef<HTMLDivElement>(null);

    // Auto-scroll to bottom on new messages
    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, isSending]);

    const handleSuggestionClick = async (suggestion: string) => {
        await onSendMessage(suggestion);
    };

    const handleConfirm = async (intent: string, params: Record<string, unknown>) => {
        await onConfirmCommand({ intent, params });
    };

    return (
        <div className="flex flex-1 flex-col overflow-hidden">
            {/* Error Banner */}
            {error && (
                <div className="mx-3 mt-3 flex items-center gap-2 rounded-lg bg-danger/20 px-3 py-2 text-sm text-danger">
                    <AlertCircle className="h-4 w-4 flex-shrink-0" />
                    <span className="flex-1">{error}</span>
                    <button
                        onClick={onClearError}
                        className="text-danger/70 hover:text-danger"
                    >
                        &times;
                    </button>
                </div>
            )}

            {/* Messages Area */}
            <div className="flex-1 overflow-y-auto p-3">
                {isLoading ? (
                    <div className="flex h-full items-center justify-center">
                        <div className="flex flex-col items-center gap-3 text-foreground-muted">
                            <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                            <span className="text-sm">Loading...</span>
                        </div>
                    </div>
                ) : messages.length === 0 ? (
                    <div className="flex h-full flex-col items-center justify-center px-4 text-center">
                        <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/20">
                            <Sparkles className="h-8 w-8 text-primary" />
                        </div>
                        <h4 className="mb-2 text-lg font-semibold text-foreground">
                            Saturn AI Assistant
                        </h4>
                        <p className="mb-6 text-sm text-foreground-muted">
                            I can help you manage your applications, services, and databases.
                            Try asking me to deploy, restart, or check status!
                        </p>
                        <AiChatSuggestions
                            context={context}
                            onSelect={handleSuggestionClick}
                            disabled={isSending}
                        />
                    </div>
                ) : (
                    <div className="space-y-4">
                        {messages.map((message) => (
                            <MessageComponent
                                key={message.uuid}
                                message={message}
                                onConfirm={handleConfirm}
                                onRate={(rating) => onRateMessage(message.uuid, rating)}
                            />
                        ))}
                        {isSending && <AiTypingIndicator />}
                        <div ref={messagesEndRef} />
                    </div>
                )}
            </div>

            {/* Input Area */}
            <div className="border-t border-white/10 p-3">
                <AiChatInput
                    onSend={onSendMessage}
                    disabled={isLoading || !session}
                    isSending={isSending}
                />
            </div>
        </div>
    );
}
