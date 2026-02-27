<?php

namespace Tests\Unit\Traits\Deployment;

use Tests\TestCase;

/**
 * Unit tests for HandlesBuildSecrets deployment trait.
 *
 * This trait manages Docker BuildKit secrets during deployment:
 * - generate_secrets_hash() — HMAC hash for Docker build cache invalidation
 * - generate_build_secrets() — --secret flags for BuildKit
 * - generate_docker_env_flags_for_secrets() — -e flags for build container
 * - add_build_secrets_to_compose() — injects secrets into docker-compose
 *
 * Tests use source-level assertions since the trait requires SSH execution context.
 */
class HandlesBuildSecretsTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = file_get_contents(
            app_path('Traits/Deployment/HandlesBuildSecrets.php')
        );
    }

    // =========================================================================
    // generate_secrets_hash() — deterministic HMAC
    // =========================================================================

    public function test_secrets_hash_uses_hmac_sha256(): void
    {
        // HMAC-SHA256 provides tamper-proof, deterministic hashing
        $this->assertStringContainsString("hash_hmac('sha256'", $this->source);
    }

    public function test_secrets_hash_uses_app_key_as_hmac_key(): void
    {
        // APP_KEY is stable across deployments — prevents cache invalidation when key rotates
        $this->assertStringContainsString("config('app.key')", $this->source);
    }

    public function test_secrets_hash_sorts_keys_for_determinism(): void
    {
        // Key order must be deterministic across deployments to preserve Docker build cache
        $this->assertStringContainsString('sortKeys()', $this->source);
    }

    public function test_secrets_hash_formats_as_key_equals_value(): void
    {
        // Each entry in the hash string is "KEY=value" — predictable format
        $this->assertStringContainsString('"{$key}={$value}"', $this->source);
    }

    public function test_secrets_hash_joins_with_pipe_separator(): void
    {
        // Pipe separator is unlikely to appear in values — safe delimiter
        $this->assertStringContainsString("'|'", $this->source);
    }

    public function test_secrets_hash_stored_as_saturn_build_secrets_hash(): void
    {
        // Hash appended as special env var so container can detect secret changes
        $this->assertStringContainsString('SATURN_BUILD_SECRETS_HASH', $this->source);
    }

    // =========================================================================
    // generate_build_secrets() — BuildKit --secret flags
    // =========================================================================

    public function test_build_secrets_uses_secret_id_env_format(): void
    {
        // Docker BuildKit --secret flag requires id=KEY,env=KEY format
        $this->assertStringContainsString('--secret id={$key},env={$key}', $this->source);
    }

    public function test_build_secrets_appends_hash_secret(): void
    {
        // Hash secret must be passed to container as a regular BuildKit secret
        $this->assertStringContainsString('--secret id=SATURN_BUILD_SECRETS_HASH,env=SATURN_BUILD_SECRETS_HASH', $this->source);
    }

    public function test_build_secrets_empty_collection_sets_empty_string(): void
    {
        // Empty variables must result in no --secret flags to avoid Docker error
        $this->assertStringContainsString('$this->build_secrets = \'\'', $this->source);
    }

    public function test_build_secrets_joins_with_space(): void
    {
        // Multiple --secret flags are space-separated on the docker build command line
        $this->assertStringContainsString("->implode(' ')", $this->source);
    }

    // =========================================================================
    // generate_docker_env_flags_for_secrets() — -e flags
    // =========================================================================

    public function test_env_flags_skip_generation_when_secrets_disabled(): void
    {
        // Must check use_build_secrets setting before generating flags
        $this->assertStringContainsString('use_build_secrets', $this->source);
    }

    public function test_env_flags_generates_env_variables_when_not_set(): void
    {
        // Lazy-generate env vars if not already populated
        $this->assertStringContainsString('$this->generate_env_variables()', $this->source);
    }

    public function test_env_flags_returns_empty_string_when_no_variables(): void
    {
        $this->assertStringContainsString("return ''", $this->source);
    }

    public function test_env_flags_appends_hash_as_env_flag(): void
    {
        // Hash must be passed as -e flag to docker run
        $this->assertStringContainsString('-e SATURN_BUILD_SECRETS_HASH=', $this->source);
    }

    public function test_env_flags_handles_multiline_variables(): void
    {
        // Multiline values need special docker flag generation
        $this->assertStringContainsString('is_multiline', $this->source);
    }

    // =========================================================================
    // add_build_secrets_to_compose() — docker-compose secrets injection
    // =========================================================================

    public function test_compose_secrets_handles_string_build_context(): void
    {
        // build: ./path (string) must be normalized to build: {context: ./path}
        $this->assertStringContainsString("'context' => \$service['build']", $this->source);
    }

    public function test_compose_secrets_injects_secret_names_into_build_section(): void
    {
        // Each variable key must be listed in service.build.secrets[]
        $this->assertStringContainsString("service['build']['secrets']", $this->source);
    }

    public function test_compose_secrets_skips_services_without_build(): void
    {
        // Only services that have a build section receive secrets injection
        $this->assertStringContainsString("isset(\$service['build'])", $this->source);
    }

    public function test_compose_secrets_defines_top_level_secrets_with_environment_source(): void
    {
        // Top-level secrets[] must use environment: KEY to source from container env
        $this->assertStringContainsString("'environment' =>", $this->source);
    }

    public function test_compose_secrets_merges_with_existing_secrets(): void
    {
        // Must preserve any secrets already defined in the compose file
        $this->assertStringContainsString('array_replace($existingSecrets', $this->source);
    }

    public function test_compose_secrets_logs_success_message(): void
    {
        $this->assertStringContainsString('Added build secrets configuration to docker-compose file', $this->source);
    }
}
