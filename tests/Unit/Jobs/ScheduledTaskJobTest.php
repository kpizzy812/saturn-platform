<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ScheduledTaskJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Tests\TestCase;

/**
 * Unit tests for ScheduledTaskJob.
 *
 * These tests focus on testing the job's configuration.
 * Constructor and handle() tests require complex mocking (Team::findOrFail, relationships)
 * and are covered in integration tests.
 */
class ScheduledTaskJobTest extends TestCase
{
    public function test_job_implements_should_queue_interface(): void
    {
        $interfaces = class_implements(ScheduledTaskJob::class);

        $this->assertTrue(in_array(ShouldQueue::class, $interfaces));
    }

    public function test_job_does_not_implement_should_be_encrypted(): void
    {
        $interfaces = class_implements(ScheduledTaskJob::class);

        $this->assertFalse(in_array(\Illuminate\Contracts\Queue\ShouldBeEncrypted::class, $interfaces));
    }

    public function test_job_has_correct_default_tries_and_max_exceptions(): void
    {
        // Use reflection to check public properties without instantiating
        $reflection = new \ReflectionClass(ScheduledTaskJob::class);

        $this->assertTrue($reflection->hasProperty('tries'));
        $this->assertTrue($reflection->hasProperty('maxExceptions'));

        $triesProperty = $reflection->getProperty('tries');

        $maxExceptionsProperty = $reflection->getProperty('maxExceptions');

        // Check default values via reflection on class definition
        $this->assertEquals(3, $triesProperty->getDefaultValue());
        $this->assertEquals(1, $maxExceptionsProperty->getDefaultValue());
    }

    public function test_job_backoff_method_exists_and_returns_array(): void
    {
        $reflection = new \ReflectionClass(ScheduledTaskJob::class);

        $this->assertTrue($reflection->hasMethod('backoff'));

        $method = $reflection->getMethod('backoff');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    public function test_source_code_has_correct_backoff_values(): void
    {
        $source = file_get_contents(app_path('Jobs/ScheduledTaskJob.php'));

        // Verify backoff returns [30, 60, 120]
        $this->assertStringContainsString('return [30, 60, 120];', $source);
    }

    public function test_source_code_uses_high_queue(): void
    {
        $source = file_get_contents(app_path('Jobs/ScheduledTaskJob.php'));

        // Verify onQueue('high') is called
        $this->assertStringContainsString("onQueue('high')", $source);
    }

    public function test_source_code_sets_timeout_from_task(): void
    {
        $source = file_get_contents(app_path('Jobs/ScheduledTaskJob.php'));

        // Verify timeout is set from task
        $this->assertStringContainsString('$this->timeout = $this->task->timeout ?? 300;', $source);
    }
}
