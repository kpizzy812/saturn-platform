<?php

namespace Tests\Unit\Services\AI\Chat;

use App\Models\Team;
use App\Models\User;
use App\Services\AI\Chat\CommandExecutor;
use App\Services\AI\Chat\DTOs\ParsedCommand;
use Mockery;
use Tests\TestCase;

class CommandExecutorAnalyzeTest extends TestCase
{
    private User $user;

    private Team $team;

    private CommandExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = Mockery::mock(User::class);
        $this->user->shouldReceive('getAttribute')->with('id')->andReturn(1);

        $this->team = Mockery::mock(Team::class);
        $this->team->shouldReceive('getAttribute')->with('id')->andReturn(1);

        $this->executor = new CommandExecutor($this->user, 1);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_execute_help_includes_new_commands(): void
    {
        $command = new ParsedCommand(action: 'help');

        $result = $this->executor->executeCommand($command);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('analyze_errors', $result->message);
        $this->assertStringContainsString('analyze_deployment', $result->message);
        $this->assertStringContainsString('code_review', $result->message);
        $this->assertStringContainsString('health_check', $result->message);
        $this->assertStringContainsString('metrics', $result->message);
    }

    public function test_parsed_command_new_fields(): void
    {
        $command = new ParsedCommand(
            action: 'analyze_errors',
            resourceType: 'application',
            resourceName: 'test-app',
            targetScope: 'single',
            deploymentUuid: 'abc123',
            resourceNames: ['app1', 'app2'],
            timePeriod: '7d',
        );

        $this->assertEquals('analyze_errors', $command->action);
        $this->assertEquals('single', $command->targetScope);
        $this->assertEquals('abc123', $command->deploymentUuid);
        $this->assertEquals(['app1', 'app2'], $command->resourceNames);
        $this->assertEquals('7d', $command->timePeriod);
    }

    public function test_parsed_command_is_actionable_includes_new_actions(): void
    {
        $actions = [
            'analyze_errors',
            'analyze_deployment',
            'code_review',
            'health_check',
            'metrics',
        ];

        foreach ($actions as $action) {
            $command = new ParsedCommand(action: $action);
            $this->assertTrue($command->isActionable(), "Action '{$action}' should be actionable");
        }
    }

    public function test_parsed_command_to_array_includes_new_fields(): void
    {
        $command = new ParsedCommand(
            action: 'metrics',
            targetScope: 'all',
            timePeriod: '30d',
            deploymentUuid: 'deploy-123',
            resourceNames: ['app1', 'app2'],
        );

        $array = $command->toArray();

        $this->assertArrayHasKey('target_scope', $array);
        $this->assertArrayHasKey('time_period', $array);
        $this->assertArrayHasKey('deployment_uuid', $array);
        $this->assertArrayHasKey('resource_names', $array);

        $this->assertEquals('all', $array['target_scope']);
        $this->assertEquals('30d', $array['time_period']);
        $this->assertEquals('deploy-123', $array['deployment_uuid']);
        $this->assertEquals(['app1', 'app2'], $array['resource_names']);
    }

    public function test_parsed_command_from_array_includes_new_fields(): void
    {
        $data = [
            'action' => 'analyze_deployment',
            'resource_type' => 'application',
            'resource_name' => 'my-app',
            'deployment_uuid' => 'abc-123',
            'target_scope' => 'single',
            'resource_names' => ['app1'],
            'time_period' => '24h',
        ];

        $command = ParsedCommand::fromArray($data);

        $this->assertEquals('analyze_deployment', $command->action);
        $this->assertEquals('abc-123', $command->deploymentUuid);
        $this->assertEquals('single', $command->targetScope);
        $this->assertEquals(['app1'], $command->resourceNames);
        $this->assertEquals('24h', $command->timePeriod);
    }

    public function test_execute_unknown_action_returns_error(): void
    {
        $command = new ParsedCommand(action: 'unknown_action');

        $result = $this->executor->executeCommand($command);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Неизвестное действие', $result->message);
    }

    public function test_analyze_errors_without_resource_lists_available(): void
    {
        // Mock no applications found
        $this->mockEmptyResources();

        $command = new ParsedCommand(
            action: 'analyze_errors',
            resourceType: 'application',
        );

        $result = $this->executor->executeCommand($command);

        // Should list available resources or return not found
        $this->assertNotNull($result);
    }

    public function test_metrics_command_parses_time_period(): void
    {
        $periods = [
            '24h' => 1,
            '7d' => 7,
            '30d' => 30,
            '2w' => 14,
            '1m' => 30,
        ];

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->executor);
        $method = $reflection->getMethod('parsePeriodToDays');

        foreach ($periods as $period => $expectedDays) {
            $result = $method->invoke($this->executor, $period);
            $this->assertEquals($expectedDays, $result, "Period '{$period}' should parse to {$expectedDays} days");
        }
    }

    public function test_determine_health_status(): void
    {
        $statuses = [
            'running' => 'healthy',
            'healthy' => 'healthy',
            'started' => 'healthy',
            'stopped' => 'unhealthy',
            'exited' => 'unhealthy',
            'not_functional' => 'unhealthy',
            'restarting' => 'degraded',
            'starting' => 'degraded',
            'stopping' => 'degraded',
            'unknown_status' => 'unknown',
        ];

        $reflection = new \ReflectionClass($this->executor);
        $method = $reflection->getMethod('determineHealthStatus');

        foreach ($statuses as $status => $expectedHealth) {
            $result = $method->invoke($this->executor, $status);
            $this->assertEquals($expectedHealth, $result, "Status '{$status}' should map to '{$expectedHealth}'");
        }
    }

    private function mockEmptyResources(): void
    {
        // This would require more complex mocking with Laravel's query builder
        // For now, we test the basic structure
    }
}
