import React from 'react';
import { Rocket, RefreshCw, Activity, FileText, Search, Stethoscope, BarChart3, FileCode } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { ChatContext } from '@/types/ai-chat';

interface AiChatSuggestionsProps {
    context?: ChatContext;
    onSelect: (suggestion: string) => void;
    disabled?: boolean;
}

interface Suggestion {
    icon: React.ElementType;
    text: string;
    query: string;
}

const defaultSuggestions: Suggestion[] = [
    {
        icon: Activity,
        text: 'Get help',
        query: 'What can you help me with?',
    },
    {
        icon: Stethoscope,
        text: 'Health check',
        query: 'Check health of all services',
    },
    {
        icon: BarChart3,
        text: 'Metrics',
        query: 'Show metrics for last week',
    },
    {
        icon: FileText,
        text: 'Commands',
        query: 'Show me available commands',
    },
];

function getSuggestionsForContext(context?: ChatContext): Suggestion[] {
    if (!context) return defaultSuggestions;

    const resourceName = context.name || context.type;

    switch (context.type) {
        case 'application':
            return [
                {
                    icon: Rocket,
                    text: 'Deploy',
                    query: `Deploy ${resourceName}`,
                },
                {
                    icon: Search,
                    text: 'Analyze errors',
                    query: `Analyze errors in ${resourceName}`,
                },
                {
                    icon: FileCode,
                    text: 'Code review',
                    query: `Show code review for ${resourceName}`,
                },
                {
                    icon: FileText,
                    text: 'Show logs',
                    query: `Show logs for ${resourceName}`,
                },
            ];

        case 'service':
            return [
                {
                    icon: RefreshCw,
                    text: 'Restart service',
                    query: `Restart ${resourceName}`,
                },
                {
                    icon: Search,
                    text: 'Analyze errors',
                    query: `Analyze errors in ${resourceName}`,
                },
                {
                    icon: FileText,
                    text: 'Show logs',
                    query: `Show logs for ${resourceName}`,
                },
                {
                    icon: Activity,
                    text: 'Check status',
                    query: `What is the status of ${resourceName}?`,
                },
            ];

        case 'database':
            return [
                {
                    icon: RefreshCw,
                    text: 'Restart database',
                    query: `Restart ${resourceName}`,
                },
                {
                    icon: Search,
                    text: 'Analyze errors',
                    query: `Analyze errors in ${resourceName}`,
                },
                {
                    icon: Activity,
                    text: 'Check status',
                    query: `What is the status of ${resourceName}?`,
                },
                {
                    icon: FileText,
                    text: 'Show logs',
                    query: `Show logs for ${resourceName}`,
                },
            ];

        case 'server':
            return [
                {
                    icon: Activity,
                    text: 'Server status',
                    query: `Check server ${resourceName} status`,
                },
                {
                    icon: FileText,
                    text: 'Server info',
                    query: `Tell me about server ${resourceName}`,
                },
            ];

        default:
            return defaultSuggestions;
    }
}

export function AiChatSuggestions({ context, onSelect, disabled = false }: AiChatSuggestionsProps) {
    const suggestions = getSuggestionsForContext(context);

    return (
        <div className="flex flex-wrap justify-center gap-2">
            {suggestions.map((suggestion) => {
                const Icon = suggestion.icon;
                return (
                    <button
                        key={suggestion.text}
                        onClick={() => !disabled && onSelect(suggestion.query)}
                        disabled={disabled}
                        className={cn(
                            'flex items-center gap-2 rounded-full',
                            'bg-background-secondary/50 border border-white/10',
                            'px-3 py-2 text-xs text-foreground-muted',
                            'transition-all duration-200',
                            disabled
                                ? 'cursor-not-allowed opacity-50'
                                : 'hover:border-primary/50 hover:bg-primary/10 hover:text-foreground'
                        )}
                    >
                        <Icon className="h-3.5 w-3.5" />
                        <span>{suggestion.text}</span>
                    </button>
                );
            })}
        </div>
    );
}
