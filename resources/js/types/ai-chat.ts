/**
 * AI Chat Types
 */

export interface AiChatSession {
    uuid: string;
    title: string | null;
    context_type: string | null;
    context_id: number | null;
    context_name: string | null;
    last_message?: string | null;
    created_at: string;
    updated_at: string;
}

export interface AiChatMessage {
    uuid: string;
    role: 'user' | 'assistant' | 'system';
    content: string;
    intent: string | null;
    intent_label: string | null;
    intent_params?: Record<string, unknown> | null;
    command_status: 'pending' | 'executing' | 'completed' | 'failed' | null;
    command_result: string | null;
    rating: number | null;
    created_at: string;
}

export interface ChatContext {
    type: string;
    id: number;
    name: string;
    uuid?: string;
}

export interface AiChatStatus {
    enabled: boolean;
    available: boolean;
    provider: string | null;
    model: string | null;
}

export interface AiUsageStats {
    total_requests: number;
    successful_requests: number;
    failed_requests: number;
    total_tokens: number;
    total_cost_usd: number;
    avg_response_time_ms: number;
    by_provider: Record<string, { count: number; total_cost: number }>;
}

export interface AiCommandStats {
    intent: string;
    count: number;
}

export interface AiRatingStats {
    ratings: Record<string, number>;
    total: number;
    average: number;
}

export interface AiDailyStats {
    date: string;
    requests: number;
    tokens: number;
    cost: number;
}

export interface SendMessageOptions {
    execute_commands?: boolean;
}

export interface ConfirmCommandOptions {
    intent: string;
    params: Record<string, unknown>;
}
