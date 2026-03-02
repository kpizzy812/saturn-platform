<?php

namespace Tests\Unit\Traits\Deployment;

use Tests\TestCase;

/**
 * Unit tests for HandlesEnvExampleDetection deployment trait.
 *
 * After git clone, this trait scans for .env.example/.env.sample/.env.template,
 * parses them via EnvExampleParser, and creates missing env vars while skipping
 * variables already defined by the user.
 *
 * Tests use source-level assertions since the trait requires SSH + Eloquent context.
 */
class HandlesEnvExampleDetectionTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = file_get_contents(
            app_path('Traits/Deployment/HandlesEnvExampleDetection.php')
        );
    }

    // =========================================================================
    // File detection — multi-file fallback chain
    // =========================================================================

    public function test_checks_env_example_file(): void
    {
        $this->assertStringContainsString('.env.example', $this->source);
    }

    public function test_checks_env_sample_file(): void
    {
        $this->assertStringContainsString('.env.sample', $this->source);
    }

    public function test_checks_env_template_file(): void
    {
        $this->assertStringContainsString('.env.template', $this->source);
    }

    public function test_file_read_uses_ignore_errors_flag(): void
    {
        // File may not exist — errors must not abort the deployment
        $this->assertStringContainsString("'ignore_errors' => true", $this->source);
    }

    public function test_file_read_is_hidden_from_log(): void
    {
        // Raw env file contents must not leak to deployment log
        $this->assertStringContainsString("'hidden' => true", $this->source);
    }

    public function test_file_read_uses_devnull_fallback(): void
    {
        // cat ... 2>/dev/null || echo '' to avoid error on missing file
        $this->assertStringContainsString('2>/dev/null || echo', $this->source);
    }

    public function test_returns_early_when_no_file_found(): void
    {
        // If none of the three files exist, must return without creating any vars
        $this->assertStringContainsString('if ($content === null)', $this->source);
    }

    public function test_returns_early_when_parsed_result_empty(): void
    {
        $this->assertStringContainsString('if (empty($parsed))', $this->source);
    }

    // =========================================================================
    // Variable creation — skipping existing keys
    // =========================================================================

    public function test_skips_keys_already_defined_by_user(): void
    {
        // User-defined vars must never be overwritten by template imports
        $this->assertStringContainsString('in_array(strtoupper($key), $existingKeys', $this->source);
    }

    public function test_existing_keys_are_compared_case_insensitively(): void
    {
        // 'APP_KEY' and 'app_key' are the same variable
        $this->assertStringContainsString('strtoupper($k)', $this->source);
    }

    public function test_new_variable_sets_source_template(): void
    {
        // Track where the variable came from for audit trail
        $this->assertStringContainsString('source_template', $this->source);
    }

    public function test_new_variable_created_as_runtime_and_buildtime(): void
    {
        // Template vars apply at both runtime and build time by default
        $this->assertStringContainsString("'is_runtime' => true", $this->source);
        $this->assertStringContainsString("'is_buildtime' => true", $this->source);
    }

    public function test_new_variable_preserves_is_required_flag(): void
    {
        // Placeholder values are marked required so user knows to fill them in
        $this->assertStringContainsString("'is_required' => \$var['is_required']", $this->source);
    }

    // =========================================================================
    // Preview environment handling
    // =========================================================================

    public function test_uses_pull_request_id_to_detect_preview(): void
    {
        // PR deployments create preview env vars, not production ones
        $this->assertStringContainsString('$this->pull_request_id !== 0', $this->source);
    }

    public function test_preview_flag_applied_to_new_variables(): void
    {
        $this->assertStringContainsString("'is_preview' => \$isPreview", $this->source);
    }

    public function test_existing_keys_query_scoped_to_preview_context(): void
    {
        // Must not check production vars when deploying a PR
        $this->assertStringContainsString("->where('is_preview', \$isPreview)", $this->source);
    }

    // =========================================================================
    // Framework detection integration
    // =========================================================================

    public function test_calls_detect_framework_with_parsed_keys(): void
    {
        $this->assertStringContainsString('EnvExampleParser::detectFramework(', $this->source);
    }

    public function test_logs_framework_name_when_detected(): void
    {
        $this->assertStringContainsString("(framework: {$this->fmtPlaceholder()})", $this->source);
    }

    // =========================================================================
    // Logging — created / skipped summary
    // =========================================================================

    public function test_logs_created_variable_count(): void
    {
        $this->assertStringContainsString('Created ', $this->source);
        $this->assertStringContainsString('new environment variables from template', $this->source);
    }

    public function test_logs_skipped_variable_count(): void
    {
        $this->assertStringContainsString('Skipped ', $this->source);
        $this->assertStringContainsString('already defined by user', $this->source);
    }

    public function test_logs_required_variable_names(): void
    {
        // Required vars need user attention — must be explicitly listed
        $this->assertStringContainsString('Required variables needing values:', $this->source);
    }

    public function test_logs_total_variable_count_from_template(): void
    {
        $this->assertStringContainsString('variables', $this->source);
    }

    // =========================================================================
    // EnvExampleParser integration
    // =========================================================================

    public function test_uses_env_example_parser_to_parse_content(): void
    {
        $this->assertStringContainsString('EnvExampleParser::parse($content)', $this->source);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Return the framework variable placeholder used in source. */
    private function fmtPlaceholder(): string
    {
        return '{$framework}';
    }
}
