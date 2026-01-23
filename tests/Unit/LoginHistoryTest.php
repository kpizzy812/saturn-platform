<?php

namespace Tests\Unit;

use App\Models\LoginHistory;
use App\Models\User;
use Mockery;
use PHPUnit\Framework\TestCase;

class LoginHistoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_login_history_has_correct_fillable_fields(): void
    {
        $loginHistory = new LoginHistory;

        $this->assertEquals([
            'user_id',
            'ip_address',
            'user_agent',
            'status',
            'location',
            'failure_reason',
            'logged_at',
        ], $loginHistory->getFillable());
    }

    public function test_login_history_has_correct_casts(): void
    {
        $loginHistory = new LoginHistory;

        $casts = $loginHistory->getCasts();

        $this->assertEquals('datetime', $casts['logged_at']);
        $this->assertEquals('datetime', $casts['created_at']);
        $this->assertEquals('datetime', $casts['updated_at']);
    }

    public function test_login_history_table_name(): void
    {
        $loginHistory = new LoginHistory;

        $this->assertEquals('login_history', $loginHistory->getTable());
    }

    public function test_login_history_has_user_relationship(): void
    {
        $loginHistory = new LoginHistory;

        $this->assertTrue(method_exists($loginHistory, 'user'));

        // Test that user() returns a BelongsTo relationship
        $reflection = new \ReflectionMethod($loginHistory, 'user');
        $this->assertTrue($reflection->isPublic());
    }

    public function test_login_history_has_scope_methods(): void
    {
        $loginHistory = new LoginHistory;

        // Verify all scope methods exist
        $this->assertTrue(method_exists($loginHistory, 'scopeForUser'));
        $this->assertTrue(method_exists($loginHistory, 'scopeByStatus'));
        $this->assertTrue(method_exists($loginHistory, 'scopeSuccessful'));
        $this->assertTrue(method_exists($loginHistory, 'scopeFailed'));
        $this->assertTrue(method_exists($loginHistory, 'scopeRecent'));
        $this->assertTrue(method_exists($loginHistory, 'scopeLatest'));
    }

    public function test_login_history_has_static_record_method(): void
    {
        $this->assertTrue(method_exists(LoginHistory::class, 'record'));

        $reflection = new \ReflectionMethod(LoginHistory::class, 'record');
        $this->assertTrue($reflection->isStatic());
        $this->assertTrue($reflection->isPublic());

        // Check parameters
        $params = $reflection->getParameters();
        $this->assertCount(3, $params);
        $this->assertEquals('user', $params[0]->getName());
        $this->assertEquals('status', $params[1]->getName());
        $this->assertEquals('reason', $params[2]->getName());

        // Check default values
        $this->assertEquals('success', $params[1]->getDefaultValue());
        $this->assertNull($params[2]->getDefaultValue());
    }

    public function test_login_history_has_cleanup_method(): void
    {
        $this->assertTrue(method_exists(LoginHistory::class, 'cleanupOld'));

        $reflection = new \ReflectionMethod(LoginHistory::class, 'cleanupOld');
        $this->assertTrue($reflection->isStatic());
        $this->assertTrue($reflection->isPublic());

        // Check parameter with default value
        $params = $reflection->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('keepLast', $params[0]->getName());
        $this->assertEquals(100, $params[0]->getDefaultValue());
    }

    public function test_login_history_has_suspicious_activity_method(): void
    {
        $this->assertTrue(method_exists(LoginHistory::class, 'hasSuspiciousActivity'));

        $reflection = new \ReflectionMethod(LoginHistory::class, 'hasSuspiciousActivity');
        $this->assertTrue($reflection->isStatic());
        $this->assertTrue($reflection->isPublic());

        // Check parameters with defaults
        $params = $reflection->getParameters();
        $this->assertCount(3, $params);
        $this->assertEquals('userId', $params[0]->getName());
        $this->assertEquals('threshold', $params[1]->getName());
        $this->assertEquals('hours', $params[2]->getName());
        $this->assertEquals(5, $params[1]->getDefaultValue());
        $this->assertEquals(24, $params[2]->getDefaultValue());
    }
}
