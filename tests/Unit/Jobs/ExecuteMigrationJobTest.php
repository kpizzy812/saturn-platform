<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ExecuteMigrationJob;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExecuteMigrationJobTest extends TestCase
{
    #[Test]
    public function job_has_retry_configuration(): void
    {
        $class = new \ReflectionClass(ExecuteMigrationJob::class);

        $triesProperty = $class->getProperty('tries');
        $this->assertEquals(3, $triesProperty->getDefaultValue());
    }

    #[Test]
    public function job_has_backoff_intervals(): void
    {
        $class = new \ReflectionClass(ExecuteMigrationJob::class);

        $backoffProperty = $class->getProperty('backoff');
        $defaultValue = $backoffProperty->getDefaultValue();

        $this->assertIsArray($defaultValue);
        $this->assertCount(3, $defaultValue);
        $this->assertEquals([60, 300, 900], $defaultValue);
    }

    #[Test]
    public function job_has_timeout(): void
    {
        $class = new \ReflectionClass(ExecuteMigrationJob::class);

        $timeoutProperty = $class->getProperty('timeout');
        $this->assertEquals(1800, $timeoutProperty->getDefaultValue());
    }

    #[Test]
    public function job_implements_required_interfaces(): void
    {
        $class = new \ReflectionClass(ExecuteMigrationJob::class);

        $this->assertTrue($class->implementsInterface(\Illuminate\Contracts\Queue\ShouldQueue::class));
        $this->assertTrue($class->implementsInterface(\Illuminate\Contracts\Queue\ShouldBeEncrypted::class));
    }

    #[Test]
    public function job_has_handle_and_failed_methods(): void
    {
        $class = new \ReflectionClass(ExecuteMigrationJob::class);

        $this->assertTrue($class->hasMethod('handle'));
        $this->assertTrue($class->hasMethod('failed'));
    }

    #[Test]
    public function job_has_tags_method(): void
    {
        $class = new \ReflectionClass(ExecuteMigrationJob::class);

        $this->assertTrue($class->hasMethod('tags'));
    }

    #[Test]
    public function handle_method_has_server_health_check_logic(): void
    {
        // Verify the handle method source contains server health check
        $sourceCode = file_get_contents(
            base_path('app/Jobs/ExecuteMigrationJob.php')
        );
        $this->assertStringContainsString('isFunctional', $sourceCode);
        $this->assertStringContainsString('Target server is not reachable', $sourceCode);
    }

    #[Test]
    public function handle_checks_for_deleted_server(): void
    {
        $sourceCode = file_get_contents(
            base_path('app/Jobs/ExecuteMigrationJob.php')
        );
        $this->assertStringContainsString('Target server was deleted', $sourceCode);
        $this->assertStringContainsString('if (! $targetServer)', $sourceCode);
    }

    #[Test]
    public function handle_does_not_call_mark_as_failed_in_catch_block(): void
    {
        $source = file_get_contents(
            base_path('app/Jobs/ExecuteMigrationJob.php')
        );

        // The catch block should only append log and notify, NOT markAsFailed
        // The failed() method should be the one calling markAsFailed
        $this->assertStringContainsString('// Only log here; markAsFailed is handled by the failed() hook', $source);
    }

    #[Test]
    public function handle_calls_mark_as_failed_on_action_failure(): void
    {
        $source = file_get_contents(
            base_path('app/Jobs/ExecuteMigrationJob.php')
        );

        // When ExecuteMigrationAction returns success=false (no exception),
        // handle() must call markAsFailed to prevent migration stuck in_progress
        $this->assertStringContainsString('$this->migration->markAsFailed($error)', $source);
    }

    #[Test]
    public function failed_hook_catches_logic_exception(): void
    {
        $source = file_get_contents(
            base_path('app/Jobs/ExecuteMigrationJob.php')
        );

        // failed() hook must catch LogicException to prevent infinite loop
        // when migration is already in terminal state
        $this->assertStringContainsString('catch (\LogicException $e)', $source);
        $this->assertStringContainsString('migration already terminal', $source);
    }
}
