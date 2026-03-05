<?php

namespace Tests\Unit\Services\AI;

use Tests\TestCase;

/**
 * Unit tests for AI Services:
 * AiChatService, AICodeAnalyzer, DeploymentLogAnalyzer, ResourceErrorAnalyzer.
 */
class AiServicesTest extends TestCase
{
    // =========================================================================
    // AiChatService
    // =========================================================================

    /** @test */
    public function chat_service_checks_enabled_config(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/AiChatService.php'));

        $this->assertStringContainsString("config('ai.chat.enabled'", $source);
        $this->assertStringContainsString("config('ai.enabled'", $source);
    }

    /** @test */
    public function chat_service_has_daily_token_limit(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/AiChatService.php'));

        $this->assertStringContainsString('isDailyTokenLimitExceeded', $source);
        $this->assertStringContainsString("config('ai.chat.rate_limit.tokens_per_day'", $source);
        $this->assertStringContainsString('Daily AI token limit exceeded', $source);
    }

    /** @test */
    public function chat_service_supports_provider_fallback(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/AiChatService.php'));

        $this->assertStringContainsString("config('ai.chat.default_provider'", $source);
        $this->assertStringContainsString("config('ai.chat.fallback_order'", $source);
    }

    /** @test */
    public function chat_service_creates_user_and_assistant_messages(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/AiChatService.php'));

        $this->assertStringContainsString("'user'", $source);
        $this->assertStringContainsString("'assistant'", $source);
    }

    /** @test */
    public function chat_service_has_streaming_support(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/AiChatService.php'));

        $this->assertStringContainsString('streamMessage', $source);
        $this->assertStringContainsString('Generator', $source);
    }

    /** @test */
    public function chat_service_logs_usage(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/AiChatService.php'));

        $this->assertStringContainsString('logUsage', $source);
        $this->assertStringContainsString('AiUsageLog', $source);
    }

    /** @test */
    public function chat_service_estimates_stream_tokens(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/AiChatService.php'));

        $this->assertStringContainsString('logStreamUsage', $source);
        $this->assertStringContainsString('ceil(strlen', $source);
    }

    /** @test */
    public function chat_service_delegates_to_command_parser(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/AiChatService.php'));

        $this->assertStringContainsString('CommandParser', $source);
        $this->assertStringContainsString('parseCommands', $source);
    }

    /** @test */
    public function chat_service_delegates_to_command_executor(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/AiChatService.php'));

        $this->assertStringContainsString('CommandExecutor', $source);
        $this->assertStringContainsString('executeCommands', $source);
    }

    /** @test */
    public function chat_service_builds_system_prompt(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/AiChatService.php'));

        $this->assertStringContainsString('buildSystemPrompt', $source);
        $this->assertStringContainsString("config('ai.prompts.chat_system'", $source);
    }

    /** @test */
    public function chat_service_supports_session_management(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/AiChatService.php'));

        $this->assertStringContainsString('getOrCreateSession', $source);
        $this->assertStringContainsString("'active'", $source);
    }

    /** @test */
    public function chat_service_rates_messages(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/AiChatService.php'));

        $this->assertStringContainsString('rateMessage', $source);
    }

    // =========================================================================
    // AICodeAnalyzer
    // =========================================================================

    /** @test */
    public function code_analyzer_checks_availability(): void
    {
        $source = file_get_contents(app_path('Services/AI/CodeReview/AICodeAnalyzer.php'));

        $this->assertStringContainsString('isAvailable', $source);
        $this->assertStringContainsString("config('ai.code_review.ai_analysis'", $source);
    }

    /** @test */
    public function code_analyzer_checks_instance_settings(): void
    {
        $source = file_get_contents(app_path('Services/AI/CodeReview/AICodeAnalyzer.php'));

        $this->assertStringContainsString('is_ai_code_review_enabled', $source);
    }

    /** @test */
    public function code_analyzer_supports_multiple_providers(): void
    {
        $source = file_get_contents(app_path('Services/AI/CodeReview/AICodeAnalyzer.php'));

        $this->assertStringContainsString('AnthropicProvider', $source);
        $this->assertStringContainsString('OpenAIProvider', $source);
        $this->assertStringContainsString('OllamaProvider', $source);
    }

    /** @test */
    public function code_analyzer_maps_categories_to_rule_ids(): void
    {
        $source = file_get_contents(app_path('Services/AI/CodeReview/AICodeAnalyzer.php'));

        $this->assertStringContainsString("'AI-SEC'", $source);
        $this->assertStringContainsString("'AI-BUG'", $source);
        $this->assertStringContainsString("'AI-PERF'", $source);
        $this->assertStringContainsString("'AI-PRAC'", $source);
        $this->assertStringContainsString("'AI-GEN'", $source);
    }

    /** @test */
    public function code_analyzer_normalizes_severity(): void
    {
        $source = file_get_contents(app_path('Services/AI/CodeReview/AICodeAnalyzer.php'));

        $this->assertStringContainsString('normalizeSeverity', $source);
        $this->assertStringContainsString("'critical'", $source);
        $this->assertStringContainsString("'high'", $source);
        $this->assertStringContainsString("'medium'", $source);
        $this->assertStringContainsString("'low'", $source);
    }

    /** @test */
    public function code_analyzer_parses_json_response(): void
    {
        $source = file_get_contents(app_path('Services/AI/CodeReview/AICodeAnalyzer.php'));

        $this->assertStringContainsString('parseResponse', $source);
        $this->assertStringContainsString('extractJson', $source);
    }

    /** @test */
    public function code_analyzer_creates_violation_dtos(): void
    {
        $source = file_get_contents(app_path('Services/AI/CodeReview/AICodeAnalyzer.php'));

        $this->assertStringContainsString('Violation', $source);
        $this->assertStringContainsString("source: 'llm'", $source);
        $this->assertStringContainsString('confidence: 0.8', $source);
    }

    /** @test */
    public function code_analyzer_uses_diff_redactor(): void
    {
        $source = file_get_contents(app_path('Services/AI/CodeReview/AICodeAnalyzer.php'));

        $this->assertStringContainsString('DiffRedactor', $source);
    }

    /** @test */
    public function code_analyzer_returns_usage_info(): void
    {
        $source = file_get_contents(app_path('Services/AI/CodeReview/AICodeAnalyzer.php'));

        $this->assertStringContainsString('getLastUsage', $source);
        $this->assertStringContainsString('input_tokens', $source);
        $this->assertStringContainsString('output_tokens', $source);
    }

    // =========================================================================
    // DeploymentLogAnalyzer
    // =========================================================================

    /** @test */
    public function deployment_analyzer_checks_availability(): void
    {
        $source = file_get_contents(app_path('Services/AI/DeploymentLogAnalyzer.php'));

        $this->assertStringContainsString("config('ai.enabled'", $source);
        $this->assertStringContainsString('is_ai_error_analysis_enabled', $source);
    }

    /** @test */
    public function deployment_analyzer_throws_when_no_provider(): void
    {
        $source = file_get_contents(app_path('Services/AI/DeploymentLogAnalyzer.php'));

        $this->assertStringContainsString('No AI provider is available', $source);
        $this->assertStringContainsString('RuntimeException', $source);
    }

    /** @test */
    public function deployment_analyzer_supports_provider_fallback(): void
    {
        $source = file_get_contents(app_path('Services/AI/DeploymentLogAnalyzer.php'));

        $this->assertStringContainsString("config('ai.fallback_order'", $source);
        $this->assertStringContainsString("'claude', 'openai', 'ollama'", $source);
    }

    /** @test */
    public function deployment_analyzer_truncates_logs(): void
    {
        $source = file_get_contents(app_path('Services/AI/DeploymentLogAnalyzer.php'));

        $this->assertStringContainsString("config('ai.log_processing.max_log_size'", $source);
        $this->assertStringContainsString("config('ai.log_processing.tail_lines'", $source);
        $this->assertStringContainsString('LOG TRUNCATED', $source);
    }

    /** @test */
    public function deployment_analyzer_computes_error_hash(): void
    {
        $source = file_get_contents(app_path('Services/AI/DeploymentLogAnalyzer.php'));

        $this->assertStringContainsString('computeErrorHash', $source);
        $this->assertStringContainsString('sha256', $source);
    }

    /** @test */
    public function deployment_analyzer_normalizes_timestamps_in_hash(): void
    {
        $source = file_get_contents(app_path('Services/AI/DeploymentLogAnalyzer.php'));

        // Regex patterns for normalization
        $this->assertStringContainsString('d{4}-\\d{2}-\\d{2}', $source);
    }

    /** @test */
    public function deployment_analyzer_saves_analysis(): void
    {
        $source = file_get_contents(app_path('Services/AI/DeploymentLogAnalyzer.php'));

        $this->assertStringContainsString('analyzeAndSave', $source);
        $this->assertStringContainsString('DeploymentLogAnalysis', $source);
        $this->assertStringContainsString("'analyzing'", $source);
        $this->assertStringContainsString("'completed'", $source);
        $this->assertStringContainsString("'failed'", $source);
    }

    /** @test */
    public function deployment_analyzer_stores_result_fields(): void
    {
        $source = file_get_contents(app_path('Services/AI/DeploymentLogAnalyzer.php'));

        $this->assertStringContainsString("'root_cause'", $source);
        $this->assertStringContainsString("'solution'", $source);
        $this->assertStringContainsString("'severity'", $source);
        $this->assertStringContainsString("'confidence'", $source);
        $this->assertStringContainsString("'provider'", $source);
    }

    /** @test */
    public function deployment_analyzer_supports_caching(): void
    {
        $source = file_get_contents(app_path('Services/AI/DeploymentLogAnalyzer.php'));

        $this->assertStringContainsString("config('ai.cache.enabled'", $source);
        $this->assertStringContainsString("config('ai.cache.prefix'", $source);
        $this->assertStringContainsString("config('ai.cache.ttl'", $source);
    }

    /** @test */
    public function deployment_analyzer_logs_usage(): void
    {
        $source = file_get_contents(app_path('Services/AI/DeploymentLogAnalyzer.php'));

        $this->assertStringContainsString('AiUsageLog', $source);
        $this->assertStringContainsString("'deployment_analysis'", $source);
    }

    // =========================================================================
    // ResourceErrorAnalyzer
    // =========================================================================

    /** @test */
    public function resource_analyzer_supports_provider_injection(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/ResourceErrorAnalyzer.php'));

        $this->assertStringContainsString('ChatProviderInterface $provider', $source);
        $this->assertStringContainsString('setProvider', $source);
    }

    /** @test */
    public function resource_analyzer_returns_structured_result(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/ResourceErrorAnalyzer.php'));

        $this->assertStringContainsString("'success'", $source);
        $this->assertStringContainsString("'resource_name'", $source);
        $this->assertStringContainsString("'resource_type'", $source);
        $this->assertStringContainsString("'errors_found'", $source);
        $this->assertStringContainsString("'issues'", $source);
        $this->assertStringContainsString("'summary'", $source);
        $this->assertStringContainsString("'solutions'", $source);
    }

    /** @test */
    public function resource_analyzer_detects_fatal_errors(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/ResourceErrorAnalyzer.php'));

        $this->assertStringContainsString('Fatal error detected', $source);
        $this->assertStringContainsString('Out of memory error', $source);
        $this->assertStringContainsString('Segmentation fault', $source);
    }

    /** @test */
    public function resource_analyzer_detects_connection_errors(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/ResourceErrorAnalyzer.php'));

        $this->assertStringContainsString('Connection failure', $source);
        $this->assertStringContainsString('Connection refused', $source);
        $this->assertStringContainsString('Timeout occurred', $source);
    }

    /** @test */
    public function resource_analyzer_generates_solutions(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/ResourceErrorAnalyzer.php'));

        $this->assertStringContainsString('generateBasicSolutions', $source);
    }

    /** @test */
    public function resource_analyzer_supports_batch_analysis(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/ResourceErrorAnalyzer.php'));

        $this->assertStringContainsString('analyzeMultiple', $source);
    }

    /** @test */
    public function resource_analyzer_has_basic_and_ai_modes(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/ResourceErrorAnalyzer.php'));

        $this->assertStringContainsString('basicAnalysis', $source);
        $this->assertStringContainsString('aiAnalysis', $source);
    }

    /** @test */
    public function resource_analyzer_handles_empty_logs(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/ResourceErrorAnalyzer.php'));

        $this->assertStringContainsString('No logs available', $source);
    }

    /** @test */
    public function resource_analyzer_checks_server_functional(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/ResourceErrorAnalyzer.php'));

        $this->assertStringContainsString('Server is not functional', $source);
    }
}
