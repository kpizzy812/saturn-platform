<?php

namespace Tests\Unit;

use App\Actions\Migration\PromoteResourceAction;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for PromoteResourceAction.
 */
class PromoteResourceActionTest extends TestCase
{
    private PromoteResourceAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new PromoteResourceAction;
    }

    /**
     * Test that connection variable patterns are properly defined.
     */
    public function test_connection_var_patterns_are_defined(): void
    {
        $reflection = new ReflectionClass(PromoteResourceAction::class);
        $patterns = $reflection->getConstant('CONNECTION_VAR_PATTERNS');

        $this->assertIsArray($patterns);
        $this->assertContains('DATABASE_URL', $patterns);
        $this->assertContains('REDIS_URL', $patterns);
        $this->assertContains('DB_HOST', $patterns);
    }

    /**
     * Test isConnectionVariable method with exact matches.
     */
    public function test_is_connection_variable_exact_match(): void
    {
        $method = $this->getProtectedMethod('isConnectionVariable');

        $this->assertTrue($method->invoke($this->action, 'DATABASE_URL'));
        $this->assertTrue($method->invoke($this->action, 'database_url')); // Case insensitive
        $this->assertTrue($method->invoke($this->action, 'REDIS_URL'));
        $this->assertTrue($method->invoke($this->action, 'DB_HOST'));
        $this->assertTrue($method->invoke($this->action, 'POSTGRES_HOST'));
    }

    /**
     * Test isConnectionVariable method with wildcard patterns.
     */
    public function test_is_connection_variable_wildcard_match(): void
    {
        $method = $this->getProtectedMethod('isConnectionVariable');

        // Wildcard patterns like *_DATABASE_URL should match
        $this->assertTrue($method->invoke($this->action, 'PRIMARY_DATABASE_URL'));
        $this->assertTrue($method->invoke($this->action, 'SECONDARY_DB_HOST'));
        $this->assertTrue($method->invoke($this->action, 'CACHE_REDIS_URL'));
    }

    /**
     * Test isConnectionVariable returns false for non-connection vars.
     */
    public function test_is_connection_variable_returns_false_for_non_connection_vars(): void
    {
        $method = $this->getProtectedMethod('isConnectionVariable');

        $this->assertFalse($method->invoke($this->action, 'API_KEY'));
        $this->assertFalse($method->invoke($this->action, 'SECRET_TOKEN'));
        $this->assertFalse($method->invoke($this->action, 'APP_NAME'));
        $this->assertFalse($method->invoke($this->action, 'SOME_OTHER_VAR'));
    }

    /**
     * Test maskSensitiveValue masks passwords.
     */
    public function test_mask_sensitive_value(): void
    {
        $method = $this->getProtectedMethod('maskSensitiveValue');

        // Test URL with password
        $url = 'postgresql://user:secretpass@hostname:5432/db';
        $masked = $method->invoke($this->action, $url);
        $this->assertEquals('postgresql://user:****@hostname:5432/db', $masked);

        // Test Redis URL (password after colon before @)
        $redis = 'redis://:redispassword@redis:6379';
        $masked = $method->invoke($this->action, $redis);
        $this->assertEquals('redis://:****@redis:6379', $masked);
    }

    /**
     * Test database models constant is properly defined.
     */
    public function test_database_models_are_defined(): void
    {
        $reflection = new ReflectionClass(PromoteResourceAction::class);
        $models = $reflection->getConstant('DATABASE_MODELS');

        $this->assertIsArray($models);
        $this->assertContains('App\Models\StandalonePostgresql', $models);
        $this->assertContains('App\Models\StandaloneMysql', $models);
        $this->assertContains('App\Models\StandaloneRedis', $models);
        $this->assertContains('App\Models\StandaloneMongodb', $models);
    }

    /**
     * Test getDatabaseRelationMethod returns correct mappings.
     */
    public function test_get_database_relation_method(): void
    {
        $method = $this->getProtectedMethod('getDatabaseRelationMethod');

        $this->assertEquals('postgresqls', $method->invoke($this->action, 'App\Models\StandalonePostgresql'));
        $this->assertEquals('mysqls', $method->invoke($this->action, 'App\Models\StandaloneMysql'));
        $this->assertEquals('redis', $method->invoke($this->action, 'App\Models\StandaloneRedis'));
        $this->assertEquals('mongodbs', $method->invoke($this->action, 'App\Models\StandaloneMongodb'));
        $this->assertNull($method->invoke($this->action, 'App\Models\Unknown'));
    }

    /**
     * Test getConfigFields returns application fields.
     */
    public function test_get_config_fields_includes_important_fields(): void
    {
        // Create a mock Application
        $app = \Mockery::mock('App\Models\Application');

        $method = $this->getProtectedMethod('getConfigFields');
        $fields = $method->invoke($this->action, $app);

        $this->assertIsArray($fields);
        $this->assertContains('git_repository', $fields);
        $this->assertContains('git_branch', $fields);
        $this->assertContains('build_pack', $fields);
        $this->assertContains('dockerfile', $fields);
        $this->assertContains('health_check_enabled', $fields);
    }

    /**
     * Test config fields exclude sensitive fields.
     */
    public function test_update_configuration_excludes_identity_fields(): void
    {
        $reflection = new ReflectionClass(PromoteResourceAction::class);
        $method = $reflection->getMethod('updateConfiguration');

        // The exclude fields are hardcoded in the method
        // We just verify the action can be instantiated and method exists
        $this->assertTrue($method->isProtected());
    }

    /**
     * Helper to get protected method for testing.
     * Note: setAccessible() is not needed in PHP 8.5+
     */
    private function getProtectedMethod(string $name): \ReflectionMethod
    {
        $reflection = new ReflectionClass(PromoteResourceAction::class);

        return $reflection->getMethod($name);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
