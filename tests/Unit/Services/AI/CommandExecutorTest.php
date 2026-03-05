<?php

namespace Tests\Unit\Services\AI;

use Tests\TestCase;

/**
 * Unit tests for AI Chat CommandExecutor and CommandParser.
 */
class CommandExecutorTest extends TestCase
{
    // =========================================================================
    // CommandExecutor
    // =========================================================================

    /** @test */
    public function executor_has_rate_limit_configuration(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandExecutor.php'));

        $this->assertStringContainsString('RATE_LIMITS', $source);
        $this->assertStringContainsString("'deploy'", $source);
        $this->assertStringContainsString("'delete'", $source);
        $this->assertStringContainsString("'restart'", $source);
        $this->assertStringContainsString("'stop'", $source);
    }

    /** @test */
    public function executor_checks_rate_limit(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandExecutor.php'));

        $this->assertStringContainsString('checkRateLimit', $source);
        $this->assertStringContainsString('RateLimiter', $source);
        $this->assertStringContainsString('tooManyAttempts', $source);
    }

    /** @test */
    public function executor_escapes_ilike_for_sql_safety(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandExecutor.php'));

        $this->assertStringContainsString('escapeIlike', $source);
    }

    /** @test */
    public function executor_uses_gate_authorization(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandExecutor.php'));

        $this->assertStringContainsString('Gate::forUser(', $source);
        $this->assertStringContainsString('authorize', $source);
    }

    /** @test */
    public function executor_handles_deploy_action(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandExecutor.php'));

        $this->assertStringContainsString('handleDeploy', $source);
        $this->assertStringContainsString('Deploy', $source);
    }

    /** @test */
    public function executor_handles_restart_action(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandExecutor.php'));

        $this->assertStringContainsString('handleRestart', $source);
    }

    /** @test */
    public function executor_handles_stop_action(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandExecutor.php'));

        $this->assertStringContainsString('handleStop', $source);
    }

    /** @test */
    public function executor_handles_start_action(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandExecutor.php'));

        $this->assertStringContainsString('handleStart', $source);
    }

    /** @test */
    public function executor_handles_delete_action(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandExecutor.php'));

        $this->assertStringContainsString('handleDelete', $source);
    }

    /** @test */
    public function executor_handles_logs_action(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandExecutor.php'));

        $this->assertStringContainsString('handleLogs', $source);
    }

    /** @test */
    public function executor_handles_status_action(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandExecutor.php'));

        $this->assertStringContainsString('handleStatus', $source);
    }

    /** @test */
    public function executor_finds_resources_by_name(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandExecutor.php'));

        $this->assertStringContainsString('findResourceByName', $source);
        $this->assertStringContainsString('findApplicationByName', $source);
        $this->assertStringContainsString('findServiceByName', $source);
        $this->assertStringContainsString('findServerByName', $source);
    }

    /** @test */
    public function executor_knows_all_database_types(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandExecutor.php'));

        $this->assertStringContainsString('postgresql', $source);
        $this->assertStringContainsString('mysql', $source);
        $this->assertStringContainsString('mariadb', $source);
        $this->assertStringContainsString('mongodb', $source);
        $this->assertStringContainsString('redis', $source);
        $this->assertStringContainsString('keydb', $source);
        $this->assertStringContainsString('dragonfly', $source);
        $this->assertStringContainsString('clickhouse', $source);
    }

    /** @test */
    public function executor_provides_not_found_suggestions(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandExecutor.php'));

        $this->assertStringContainsString('notFoundWithSuggestions', $source);
    }

    /** @test */
    public function executor_validates_deploy_only_for_applications(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandExecutor.php'));

        $this->assertStringContainsString('Deploy', $source);
    }

    /** @test */
    public function executor_returns_command_result(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandExecutor.php'));

        $this->assertStringContainsString('CommandResult', $source);
    }

    // =========================================================================
    // CommandParser
    // =========================================================================

    /** @test */
    public function parser_defines_dangerous_actions(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandParser.php'));

        $this->assertStringContainsString('DANGEROUS_ACTIONS', $source);
        $this->assertStringContainsString("'deploy'", $source);
        $this->assertStringContainsString("'stop'", $source);
        $this->assertStringContainsString("'delete'", $source);
    }

    /** @test */
    public function parser_supports_all_action_types(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandParser.php'));

        $this->assertStringContainsString("'restart'", $source);
        $this->assertStringContainsString("'start'", $source);
        $this->assertStringContainsString("'logs'", $source);
        $this->assertStringContainsString("'status'", $source);
        $this->assertStringContainsString("'analyze_errors'", $source);
        $this->assertStringContainsString("'code_review'", $source);
        $this->assertStringContainsString("'health_check'", $source);
        $this->assertStringContainsString("'metrics'", $source);
        $this->assertStringContainsString("'help'", $source);
    }

    /** @test */
    public function parser_builds_system_prompt(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandParser.php'));

        $this->assertStringContainsString('buildSystemPrompt', $source);
        $this->assertStringContainsString('Saturn', $source);
    }

    /** @test */
    public function parser_extracts_json_from_response(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandParser.php'));

        $this->assertStringContainsString('extractJson', $source);
    }

    /** @test */
    public function parser_normalizes_null_values(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandParser.php'));

        $this->assertStringContainsString('normalizeNull', $source);
    }

    /** @test */
    public function parser_returns_parsed_intent(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandParser.php'));

        $this->assertStringContainsString('ParsedIntent', $source);
        $this->assertStringContainsString('ParsedCommand', $source);
    }

    /** @test */
    public function parser_supports_openai_and_anthropic(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandParser.php'));

        $this->assertStringContainsString('parseWithOpenAI', $source);
        $this->assertStringContainsString('parseWithAnthropic', $source);
    }

    /** @test */
    public function parser_handles_confirmation_for_dangerous_actions(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandParser.php'));

        $this->assertStringContainsString('confirmation', $source);
        $this->assertStringContainsString('DANGEROUS_ACTIONS', $source);
    }

    /** @test */
    public function parser_handles_no_provider_gracefully(): void
    {
        $source = file_get_contents(app_path('Services/AI/Chat/CommandParser.php'));

        $this->assertStringContainsString('CommandParser: No AI provider available', $source);
    }
}
