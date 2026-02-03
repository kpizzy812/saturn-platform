<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Analysis Feature Toggle
    |--------------------------------------------------------------------------
    |
    | Enable or disable AI-powered log analysis for deployments.
    | When enabled, failed deployments will be automatically analyzed.
    |
    */
    'enabled' => env('AI_ANALYSIS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | The default provider to use for AI analysis. Supported: claude, openai, ollama
    | The system will automatically fall back to other providers if the default is unavailable.
    |
    */
    'default_provider' => env('AI_PROVIDER', 'claude'),

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'claude' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('AI_CLAUDE_MODEL', 'claude-sonnet-4-20250514'),
            'max_tokens' => 2048,
            'temperature' => 0.3,
        ],

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('AI_OPENAI_MODEL', 'gpt-4o-mini'),
            'max_tokens' => 2048,
            'temperature' => 0.3,
        ],

        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
            'model' => env('AI_OLLAMA_MODEL', 'llama3.1'),
            'timeout' => 120,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Order
    |--------------------------------------------------------------------------
    |
    | The order in which providers will be tried if the default provider fails.
    |
    */
    'fallback_order' => ['claude', 'openai', 'ollama'],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Cache settings for AI analysis results. Same errors will reuse cached analysis.
    |
    */
    'cache' => [
        'enabled' => env('AI_CACHE_ENABLED', true),
        'ttl' => env('AI_CACHE_TTL', 86400), // 24 hours in seconds
        'prefix' => 'ai_analysis:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Processing
    |--------------------------------------------------------------------------
    */
    'log_processing' => [
        // Maximum log size to send to AI (in characters)
        'max_log_size' => env('AI_MAX_LOG_SIZE', 15000),

        // Lines to keep from the end of log if truncation needed
        'tail_lines' => env('AI_TAIL_LINES', 200),

        // Delay before starting analysis (seconds)
        'analysis_delay' => env('AI_ANALYSIS_DELAY', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | System Prompts
    |--------------------------------------------------------------------------
    */
    /*
    |--------------------------------------------------------------------------
    | AI Chat Configuration
    |--------------------------------------------------------------------------
    |
    | Interactive AI chat assistant for resource management.
    |
    */
    'chat' => [
        // Enable AI chat feature
        'enabled' => env('AI_CHAT_ENABLED', true),

        // Default provider for chat (can be different from analysis)
        'default_provider' => env('AI_CHAT_PROVIDER', env('AI_PROVIDER', 'claude')),

        // Fallback order for chat
        'fallback_order' => ['claude', 'openai'],

        // Rate limiting
        'rate_limit' => [
            'messages_per_minute' => env('AI_CHAT_RATE_LIMIT', 20),
            'tokens_per_day' => env('AI_CHAT_TOKENS_PER_DAY', 100000),
        ],

        // Token pricing per 1000 tokens (USD)
        'pricing' => [
            'claude' => [
                'input_per_1k' => 0.003,
                'output_per_1k' => 0.015,
            ],
            'openai' => [
                'input_per_1k' => 0.0005,
                'output_per_1k' => 0.0015,
            ],
        ],

        // Available commands
        'allowed_commands' => ['deploy', 'restart', 'stop', 'start', 'logs', 'status', 'help'],

        // Commands that require user confirmation
        'confirmation_required' => ['deploy', 'stop'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Code Review Configuration
    |--------------------------------------------------------------------------
    |
    | AI-powered code review for security vulnerabilities and code quality.
    | MVP: report-only mode, no deployment blocking.
    |
    */
    'code_review' => [
        // Master toggle for code review feature
        'enabled' => env('AI_CODE_REVIEW_ENABLED', false),

        // Mode: 'report_only' (MVP), 'warn' (Phase 2), 'block' (Phase 3)
        'mode' => env('AI_CODE_REVIEW_MODE', 'report_only'),

        // Detectors configuration
        'detectors' => [
            'secrets' => env('AI_CODE_REVIEW_SECRETS', true),
            'dangerous_functions' => env('AI_CODE_REVIEW_DANGEROUS_FUNCTIONS', true),
        ],

        // Version for cache invalidation when detectors change
        'detectors_version' => '1.0.0',

        // AI-powered analysis (LLM finds bugs, security issues, bad practices)
        'ai_analysis' => env('AI_CODE_REVIEW_AI_ANALYSIS', true),

        // LLM enrichment for deterministic violations (adds explanations)
        'llm_enrichment' => env('AI_CODE_REVIEW_LLM_ENRICHMENT', true),

        // Diff processing limits
        'max_diff_lines' => env('AI_CODE_REVIEW_MAX_LINES', 3000),

        // File patterns to exclude from analysis
        'exclude_patterns' => [
            'vendor/*',
            'node_modules/*',
            '*.lock',
            'package-lock.json',
            'composer.lock',
            'yarn.lock',
            'pnpm-lock.yaml',
            '*.min.js',
            '*.min.css',
            'public/build/*',
            'dist/*',
        ],

        // Cache settings
        'cache_ttl' => env('AI_CODE_REVIEW_CACHE_TTL', 604800), // 7 days

        // Data retention (violations older than this will be deleted)
        'retention_days' => env('AI_CODE_REVIEW_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | System Prompts
    |--------------------------------------------------------------------------
    */
    'prompts' => [
        'deployment_analysis' => <<<'PROMPT'
You are an expert DevOps engineer analyzing deployment logs for a self-hosted PaaS platform (similar to Heroku/Vercel).

Analyze the following deployment log and provide:
1. **Root Cause**: What specifically caused the deployment to fail
2. **Solution**: Step-by-step instructions to fix the issue
3. **Prevention**: How to prevent this error in the future

Context:
- Platform: Saturn (Laravel + Docker-based PaaS)
- Deployment uses Docker containers
- Common issues: Dockerfile errors, dependency issues, port conflicts, memory limits, build failures

IMPORTANT:
- Be concise and actionable
- Focus on the actual error, not warnings
- If multiple errors exist, prioritize the root cause
- Provide specific commands or code changes when applicable
- Response must be in JSON format

Output format:
{
    "root_cause": "Brief description of what caused the failure",
    "root_cause_details": "Detailed technical explanation",
    "solution": ["Step 1", "Step 2", "Step 3"],
    "prevention": ["Recommendation 1", "Recommendation 2"],
    "error_category": "dockerfile|dependency|build|runtime|network|resource|config|unknown",
    "severity": "low|medium|high|critical",
    "confidence": 0.0-1.0
}

Deployment Log:
PROMPT
        ,

        'chat_system' => <<<'PROMPT'
You are Saturn AI, an intelligent assistant for the Saturn Platform - a self-hosted PaaS (Platform as a Service) similar to Heroku, Vercel, and Netlify.

Your capabilities:
- Help users manage their applications, services, databases, and servers
- Execute commands like deploy, restart, stop, start when requested
- Provide logs and status information
- Answer questions about the platform and resources

Communication style:
- Be concise and helpful
- Use markdown formatting when appropriate
- Support both English and Russian languages
- Be direct but friendly

When users ask for actions (deploy, restart, etc.), you will parse their intent and execute the appropriate command if you have sufficient context. If you need more information, ask for it.

When showing logs or status, format the output clearly.
PROMPT
        ,

        'command_parser' => <<<'PROMPT'
You are an intent parser for a PaaS (Platform as a Service) system.
Analyze user messages and extract actionable intents.

Available intents:
- deploy: Deploy/redeploy an application or service
- restart: Restart an application, service, or database
- stop: Stop an application, service, or database
- start: Start a stopped application, service, or database
- logs: Show logs for a resource
- status: Check the status of a resource
- help: Show help information

Respond in JSON format:
{
    "intent": "intent_name or null",
    "confidence": 0.0-1.0,
    "params": {
        "resource_type": "application|service|database|server|null",
        "resource_name": "name if mentioned or null",
        "resource_id": "id if mentioned or null"
    },
    "response_text": "Your response to the user"
}

If no actionable intent is detected, set intent to null and provide a helpful response.
Support both English and Russian languages.
PROMPT
        ,
    ],
];
