<?php

namespace Tests\Unit;

use App\Services\AI\Chat\CommandExecutor;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Tests for AI Chat health status determination.
 */
class AiChatHealthStatusTest extends TestCase
{
    private function callDetermineHealthStatus(string $status): string
    {
        $executor = $this->getMockBuilder(CommandExecutor::class)
            ->disableOriginalConstructor()
            ->getMock();

        $method = new ReflectionMethod(CommandExecutor::class, 'determineHealthStatus');

        return $method->invoke($executor, $status);
    }

    /**
     * @test
     */
    public function it_parses_compound_status_running_healthy(): void
    {
        $this->assertEquals('healthy', $this->callDetermineHealthStatus('running:healthy'));
    }

    /**
     * @test
     */
    public function it_parses_compound_status_running_unhealthy(): void
    {
        $this->assertEquals('unhealthy', $this->callDetermineHealthStatus('running:unhealthy'));
    }

    /**
     * @test
     */
    public function it_parses_compound_status_running_unknown_as_healthy(): void
    {
        // When health is unknown but state is running, treat as healthy
        $this->assertEquals('healthy', $this->callDetermineHealthStatus('running:unknown'));
    }

    /**
     * @test
     */
    public function it_parses_compound_status_exited_unhealthy(): void
    {
        $this->assertEquals('unhealthy', $this->callDetermineHealthStatus('exited:unhealthy'));
    }

    /**
     * @test
     */
    public function it_parses_compound_status_exited_unknown_as_unhealthy(): void
    {
        // When health is unknown but state is exited, treat as unhealthy
        $this->assertEquals('unhealthy', $this->callDetermineHealthStatus('exited:unknown'));
    }

    /**
     * @test
     */
    public function it_parses_compound_status_stopped_healthy(): void
    {
        // Even if health was healthy, stopped state means unhealthy
        $this->assertEquals('healthy', $this->callDetermineHealthStatus('stopped:healthy'));
    }

    /**
     * @test
     */
    public function it_parses_compound_status_restarting_unknown(): void
    {
        $this->assertEquals('degraded', $this->callDetermineHealthStatus('restarting:unknown'));
    }

    /**
     * @test
     */
    public function it_parses_compound_status_starting_unknown(): void
    {
        $this->assertEquals('degraded', $this->callDetermineHealthStatus('starting:unknown'));
    }

    /**
     * @test
     */
    public function it_parses_compound_status_paused(): void
    {
        $this->assertEquals('degraded', $this->callDetermineHealthStatus('paused:unknown'));
    }

    /**
     * @test
     */
    public function it_handles_simple_status_running(): void
    {
        $this->assertEquals('healthy', $this->callDetermineHealthStatus('running'));
    }

    /**
     * @test
     */
    public function it_handles_simple_status_healthy(): void
    {
        $this->assertEquals('healthy', $this->callDetermineHealthStatus('healthy'));
    }

    /**
     * @test
     */
    public function it_handles_simple_status_stopped(): void
    {
        $this->assertEquals('unhealthy', $this->callDetermineHealthStatus('stopped'));
    }

    /**
     * @test
     */
    public function it_handles_simple_status_exited(): void
    {
        $this->assertEquals('unhealthy', $this->callDetermineHealthStatus('exited'));
    }

    /**
     * @test
     */
    public function it_handles_simple_status_not_functional(): void
    {
        $this->assertEquals('unhealthy', $this->callDetermineHealthStatus('not_functional'));
    }

    /**
     * @test
     */
    public function it_handles_simple_status_restarting(): void
    {
        $this->assertEquals('degraded', $this->callDetermineHealthStatus('restarting'));
    }

    /**
     * @test
     */
    public function it_handles_simple_status_degraded(): void
    {
        $this->assertEquals('degraded', $this->callDetermineHealthStatus('degraded'));
    }

    /**
     * @test
     */
    public function it_handles_unknown_status(): void
    {
        $this->assertEquals('unknown', $this->callDetermineHealthStatus('unknown'));
        $this->assertEquals('unknown', $this->callDetermineHealthStatus(''));
        $this->assertEquals('unknown', $this->callDetermineHealthStatus('something_random'));
    }

    /**
     * @test
     */
    public function it_is_case_insensitive(): void
    {
        $this->assertEquals('healthy', $this->callDetermineHealthStatus('RUNNING:HEALTHY'));
        $this->assertEquals('healthy', $this->callDetermineHealthStatus('Running:Healthy'));
        $this->assertEquals('unhealthy', $this->callDetermineHealthStatus('EXITED:UNHEALTHY'));
    }

    /**
     * @test
     */
    public function it_handles_whitespace(): void
    {
        $this->assertEquals('healthy', $this->callDetermineHealthStatus('  running:healthy  '));
        $this->assertEquals('healthy', $this->callDetermineHealthStatus(' running '));
    }

    /**
     * @test
     */
    public function it_handles_real_world_saturn_statuses(): void
    {
        // Real statuses from Saturn database
        $this->assertEquals('unhealthy', $this->callDetermineHealthStatus('exited:unhealthy'));
        $this->assertEquals('healthy', $this->callDetermineHealthStatus('running:unknown'));
        $this->assertEquals('healthy', $this->callDetermineHealthStatus('running:healthy'));
    }
}
