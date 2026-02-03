import { useState, useCallback, useEffect, useRef } from 'react';
import type {
    AiChatSession,
    AiChatMessage,
    AiChatStatus,
    ChatContext,
    SendMessageOptions,
    ConfirmCommandOptions,
} from '@/types/ai-chat';

interface UseAiChatOptions {
    context?: ChatContext;
    autoConnect?: boolean;
}

interface UseAiChatReturn {
    // State
    status: AiChatStatus | null;
    session: AiChatSession | null;
    messages: AiChatMessage[];
    sessions: AiChatSession[];
    isLoading: boolean;
    isSending: boolean;
    error: string | null;

    // Actions
    checkStatus: () => Promise<void>;
    createSession: () => Promise<AiChatSession | null>;
    loadSession: (uuid: string) => Promise<void>;
    loadSessions: () => Promise<void>;
    sendMessage: (content: string, options?: SendMessageOptions) => Promise<AiChatMessage | null>;
    confirmCommand: (options: ConfirmCommandOptions) => Promise<void>;
    rateMessage: (uuid: string, rating: number) => Promise<void>;
    archiveSession: (uuid: string) => Promise<void>;
    clearError: () => void;
}

export function useAiChat(options: UseAiChatOptions = {}): UseAiChatReturn {
    const { context, autoConnect = true } = options;

    const [status, setStatus] = useState<AiChatStatus | null>(null);
    const [session, setSession] = useState<AiChatSession | null>(null);
    const [messages, setMessages] = useState<AiChatMessage[]>([]);
    const [sessions, setSessions] = useState<AiChatSession[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isSending, setIsSending] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const echoChannelRef = useRef<unknown>(null);

    // Check AI chat status
    const checkStatus = useCallback(async () => {
        try {
            const response = await fetch('/web-api/ai-chat/status');
            const data = await response.json();
            setStatus(data);
        } catch (err) {
            console.error('Failed to check AI chat status:', err);
            setError('Failed to check AI chat status');
        }
    }, []);

    // Create or get session
    const createSession = useCallback(async (): Promise<AiChatSession | null> => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch('/web-api/ai-chat/sessions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    context_type: context?.type,
                    context_id: context?.id,
                    context_name: context?.name,
                }),
            });

            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.error || 'Failed to create session');
            }

            const data = await response.json();
            setSession(data.session);
            setMessages([]);
            return data.session;
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Failed to create session';
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [context]);

    // Load existing session
    const loadSession = useCallback(async (uuid: string) => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch(`/web-api/ai-chat/sessions/${uuid}/messages`);
            if (!response.ok) {
                throw new Error('Session not found');
            }

            const data = await response.json();
            setSession(data.session);
            setMessages(data.messages);
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Failed to load session';
            setError(message);
        } finally {
            setIsLoading(false);
        }
    }, []);

    // Load user's sessions list
    const loadSessions = useCallback(async () => {
        try {
            const response = await fetch('/web-api/ai-chat/sessions');
            if (!response.ok) {
                throw new Error('Failed to load sessions');
            }

            const data = await response.json();
            setSessions(data.sessions);
        } catch (err) {
            console.error('Failed to load sessions:', err);
        }
    }, []);

    // Send message
    const sendMessage = useCallback(
        async (content: string, sendOptions: SendMessageOptions = {}): Promise<AiChatMessage | null> => {
            if (!session) {
                setError('No active session');
                return null;
            }

            try {
                setIsSending(true);
                setError(null);

                // Optimistically add user message
                const tempUserMessage: AiChatMessage = {
                    uuid: `temp-${Date.now()}`,
                    role: 'user',
                    content,
                    intent: null,
                    intent_label: null,
                    command_status: null,
                    command_result: null,
                    rating: null,
                    created_at: new Date().toISOString(),
                };
                setMessages((prev) => [...prev, tempUserMessage]);

                const response = await fetch(`/web-api/ai-chat/sessions/${session.uuid}/messages`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN':
                            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({
                        content,
                        execute_commands: sendOptions.execute_commands ?? true,
                    }),
                });

                if (!response.ok) {
                    const data = await response.json();
                    throw new Error(data.error || 'Failed to send message');
                }

                const data = await response.json();

                // Replace temp message and add assistant response
                setMessages((prev) => {
                    const filtered = prev.filter((m) => m.uuid !== tempUserMessage.uuid);
                    return [
                        ...filtered,
                        { ...tempUserMessage, uuid: `user-${Date.now()}` },
                        data.message,
                    ];
                });

                return data.message;
            } catch (err) {
                // Remove temp message on error
                setMessages((prev) => prev.filter((m) => !m.uuid.startsWith('temp-')));
                const message = err instanceof Error ? err.message : 'Failed to send message';
                setError(message);
                return null;
            } finally {
                setIsSending(false);
            }
        },
        [session]
    );

    // Confirm and execute command
    const confirmCommand = useCallback(
        async (confirmOptions: ConfirmCommandOptions) => {
            if (!session) {
                setError('No active session');
                return;
            }

            try {
                setIsSending(true);
                setError(null);

                const response = await fetch(`/web-api/ai-chat/sessions/${session.uuid}/confirm`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN':
                            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify(confirmOptions),
                });

                if (!response.ok) {
                    const data = await response.json();
                    throw new Error(data.error || 'Failed to confirm command');
                }

                const data = await response.json();
                setMessages((prev) => [...prev, data.message]);
            } catch (err) {
                const message = err instanceof Error ? err.message : 'Failed to confirm command';
                setError(message);
            } finally {
                setIsSending(false);
            }
        },
        [session]
    );

    // Rate a message
    const rateMessage = useCallback(async (uuid: string, rating: number) => {
        try {
            const response = await fetch(`/web-api/ai-chat/messages/${uuid}/rate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ rating }),
            });

            if (!response.ok) {
                throw new Error('Failed to rate message');
            }

            // Update message in state
            setMessages((prev) =>
                prev.map((m) => (m.uuid === uuid ? { ...m, rating } : m))
            );
        } catch (err) {
            console.error('Failed to rate message:', err);
        }
    }, []);

    // Archive session
    const archiveSession = useCallback(async (uuid: string) => {
        try {
            const response = await fetch(`/web-api/ai-chat/sessions/${uuid}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to archive session');
            }

            // Remove from sessions list
            setSessions((prev) => prev.filter((s) => s.uuid !== uuid));

            // Clear current session if it's the one being archived
            if (session?.uuid === uuid) {
                setSession(null);
                setMessages([]);
            }
        } catch (err) {
            console.error('Failed to archive session:', err);
        }
    }, [session]);

    // Clear error
    const clearError = useCallback(() => {
        setError(null);
    }, []);

    // Subscribe to WebSocket updates
    useEffect(() => {
        if (!session || typeof window === 'undefined') return;

        // Check if Echo is available
        const echo = (window as { Echo?: unknown }).Echo as { private?: (channel: string) => {
            listen: (event: string, callback: (data: { message: AiChatMessage }) => void) => void;
            stopListening: (event: string) => void;
        } } | undefined;

        if (!echo?.private) return;

        const channel = echo.private(`ai-chat.${session.uuid}`);
        echoChannelRef.current = channel;

        // Listen for new messages
        channel.listen('AiChatMessageReceived', (data: { message: AiChatMessage }) => {
            setMessages((prev) => {
                // Avoid duplicates
                if (prev.some((m) => m.uuid === data.message.uuid)) {
                    return prev.map((m) =>
                        m.uuid === data.message.uuid ? data.message : m
                    );
                }
                return [...prev, data.message];
            });
        });

        // Listen for command execution updates
        const commandChannel = channel as unknown as {
            listen: (event: string, callback: (data: { message_uuid: string; success: boolean; result: string; command_status: string }) => void) => void;
            stopListening: (event: string) => void;
        };
        commandChannel.listen('AiCommandExecuted', (data) => {
            setMessages((prev) =>
                prev.map((m) =>
                    m.uuid === data.message_uuid
                        ? {
                              ...m,
                              command_status: data.command_status as AiChatMessage['command_status'],
                              command_result: data.result,
                          }
                        : m
                )
            );
        });

        return () => {
            channel.stopListening('AiChatMessageReceived');
            channel.stopListening('AiCommandExecuted');
        };
    }, [session]);

    // Auto-check status on mount
    useEffect(() => {
        if (autoConnect) {
            checkStatus();
        }
    }, [autoConnect, checkStatus]);

    return {
        status,
        session,
        messages,
        sessions,
        isLoading,
        isSending,
        error,
        checkStatus,
        createSession,
        loadSession,
        loadSessions,
        sendMessage,
        confirmCommand,
        rateMessage,
        archiveSession,
        clearError,
    };
}
