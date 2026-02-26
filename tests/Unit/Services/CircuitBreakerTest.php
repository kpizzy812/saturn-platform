<?php

namespace Tests\Unit\Services;

use App\Services\CircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/*
 * Unit tests for CircuitBreaker.
 *
 * Uses the array cache driver (in-memory) — no Redis required.
 */
class CircuitBreakerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset both known services before every test
        CircuitBreaker::reset('hetzner');
        CircuitBreaker::reset('github');
        CircuitBreaker::reset('test-service');
    }

    // =========================================================================
    // 1. Initial state
    // =========================================================================

    public function test_circuit_is_closed_by_default(): void
    {
        $this->assertFalse(CircuitBreaker::isOpen('hetzner'));
        $this->assertFalse(CircuitBreaker::isOpen('github'));
    }

    public function test_failure_count_is_zero_initially(): void
    {
        $this->assertSame(0, CircuitBreaker::failureCount('hetzner'));
    }

    // =========================================================================
    // 2. recordFailure — incrementing counter
    // =========================================================================

    public function test_single_failure_does_not_open_circuit(): void
    {
        CircuitBreaker::recordFailure('hetzner');

        $this->assertFalse(CircuitBreaker::isOpen('hetzner'));
        $this->assertSame(1, CircuitBreaker::failureCount('hetzner'));
    }

    public function test_failure_count_increments_with_each_failure(): void
    {
        CircuitBreaker::recordFailure('hetzner');
        CircuitBreaker::recordFailure('hetzner');
        CircuitBreaker::recordFailure('hetzner');

        $this->assertSame(3, CircuitBreaker::failureCount('hetzner'));
    }

    public function test_circuit_opens_at_threshold_of_5_failures(): void
    {
        for ($i = 0; $i < 5; $i++) {
            CircuitBreaker::recordFailure('hetzner');
        }

        $this->assertTrue(CircuitBreaker::isOpen('hetzner'));
    }

    public function test_circuit_does_not_open_before_threshold(): void
    {
        for ($i = 0; $i < 4; $i++) {
            CircuitBreaker::recordFailure('hetzner');
        }

        $this->assertFalse(CircuitBreaker::isOpen('hetzner'));
    }

    public function test_opening_circuit_logs_a_warning(): void
    {
        Log::shouldReceive('warning')->once()->with('Circuit breaker OPEN', \Mockery::on(function ($ctx) {
            return $ctx['service'] === 'hetzner'
                && $ctx['failures'] === 5
                && $ctx['cooldown_seconds'] === 60;
        }));

        for ($i = 0; $i < 5; $i++) {
            CircuitBreaker::recordFailure('hetzner');
        }
    }

    public function test_failures_beyond_threshold_do_not_re_log_warning(): void
    {
        // Warning logged only once when circuit first opens
        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->zeroOrMoreTimes();

        for ($i = 0; $i < 8; $i++) {
            CircuitBreaker::recordFailure('hetzner');
        }
    }

    // =========================================================================
    // 3. recordSuccess — resets everything
    // =========================================================================

    public function test_success_clears_failure_count(): void
    {
        CircuitBreaker::recordFailure('hetzner');
        CircuitBreaker::recordFailure('hetzner');

        CircuitBreaker::recordSuccess('hetzner');

        $this->assertSame(0, CircuitBreaker::failureCount('hetzner'));
    }

    public function test_success_closes_open_circuit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            CircuitBreaker::recordFailure('hetzner');
        }
        $this->assertTrue(CircuitBreaker::isOpen('hetzner'));

        CircuitBreaker::recordSuccess('hetzner');

        $this->assertFalse(CircuitBreaker::isOpen('hetzner'));
    }

    public function test_success_on_open_circuit_logs_recovery(): void
    {
        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->once()->with('Circuit breaker CLOSED (service recovered)', ['service' => 'hetzner']);

        for ($i = 0; $i < 5; $i++) {
            CircuitBreaker::recordFailure('hetzner');
        }
        CircuitBreaker::recordSuccess('hetzner');
    }

    public function test_success_on_closed_circuit_does_not_log_recovery(): void
    {
        Log::shouldReceive('info')->never();

        CircuitBreaker::recordSuccess('hetzner');
    }

    // =========================================================================
    // 4. reset — manual reset
    // =========================================================================

    public function test_reset_clears_failure_count(): void
    {
        CircuitBreaker::recordFailure('hetzner');
        CircuitBreaker::recordFailure('hetzner');

        CircuitBreaker::reset('hetzner');

        $this->assertSame(0, CircuitBreaker::failureCount('hetzner'));
    }

    public function test_reset_closes_open_circuit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            CircuitBreaker::recordFailure('hetzner');
        }

        CircuitBreaker::reset('hetzner');

        $this->assertFalse(CircuitBreaker::isOpen('hetzner'));
    }

    // =========================================================================
    // 5. Service isolation — failures on one service don't affect another
    // =========================================================================

    public function test_hetzner_failures_do_not_affect_github_circuit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            CircuitBreaker::recordFailure('hetzner');
        }

        $this->assertTrue(CircuitBreaker::isOpen('hetzner'));
        $this->assertFalse(CircuitBreaker::isOpen('github'));
    }

    public function test_github_failures_do_not_affect_hetzner_circuit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            CircuitBreaker::recordFailure('github');
        }

        $this->assertTrue(CircuitBreaker::isOpen('github'));
        $this->assertFalse(CircuitBreaker::isOpen('hetzner'));
    }

    // =========================================================================
    // 6. Unknown service — falls back to default config (threshold 5)
    // =========================================================================

    public function test_unknown_service_uses_default_threshold_of_5(): void
    {
        for ($i = 0; $i < 4; $i++) {
            CircuitBreaker::recordFailure('test-service');
        }
        $this->assertFalse(CircuitBreaker::isOpen('test-service'));

        CircuitBreaker::recordFailure('test-service');
        $this->assertTrue(CircuitBreaker::isOpen('test-service'));
    }

    // =========================================================================
    // 7. GitHub service — different cooldown (30s vs 60s)
    // =========================================================================

    public function test_github_circuit_opens_at_threshold_of_5(): void
    {
        for ($i = 0; $i < 5; $i++) {
            CircuitBreaker::recordFailure('github');
        }

        $this->assertTrue(CircuitBreaker::isOpen('github'));
    }

    public function test_github_opening_logs_30_second_cooldown(): void
    {
        Log::shouldReceive('warning')->once()->with('Circuit breaker OPEN', \Mockery::on(function ($ctx) {
            return $ctx['service'] === 'github' && $ctx['cooldown_seconds'] === 30;
        }));

        for ($i = 0; $i < 5; $i++) {
            CircuitBreaker::recordFailure('github');
        }
    }

    // =========================================================================
    // 8. Cache key isolation
    // =========================================================================

    public function test_open_key_and_fail_key_are_separate_cache_entries(): void
    {
        CircuitBreaker::recordFailure('hetzner');

        // fail key exists, open key does not (below threshold)
        $this->assertTrue(Cache::has('circuit:fail:hetzner'));
        $this->assertFalse(Cache::has('circuit:open:hetzner'));
    }

    public function test_open_key_is_set_when_circuit_opens(): void
    {
        for ($i = 0; $i < 5; $i++) {
            CircuitBreaker::recordFailure('hetzner');
        }

        $this->assertTrue(Cache::has('circuit:open:hetzner'));
    }

    public function test_reset_removes_both_cache_keys(): void
    {
        for ($i = 0; $i < 5; $i++) {
            CircuitBreaker::recordFailure('hetzner');
        }

        CircuitBreaker::reset('hetzner');

        $this->assertFalse(Cache::has('circuit:fail:hetzner'));
        $this->assertFalse(Cache::has('circuit:open:hetzner'));
    }

    // =========================================================================
    // 9. Recovery — can re-open after being reset
    // =========================================================================

    public function test_circuit_can_reopen_after_success_resets_it(): void
    {
        // Open the circuit
        for ($i = 0; $i < 5; $i++) {
            CircuitBreaker::recordFailure('hetzner');
        }
        // Recover
        CircuitBreaker::recordSuccess('hetzner');
        $this->assertFalse(CircuitBreaker::isOpen('hetzner'));

        // Fail again — should open again
        Log::shouldReceive('warning')->once();
        for ($i = 0; $i < 5; $i++) {
            CircuitBreaker::recordFailure('hetzner');
        }
        $this->assertTrue(CircuitBreaker::isOpen('hetzner'));
    }
}
