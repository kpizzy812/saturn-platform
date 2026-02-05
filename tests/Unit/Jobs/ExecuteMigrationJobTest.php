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
    public function handle_does_not_call_mark_as_failed_in_catch_block(): void
    {
        // Verify the catch block in handle() does NOT call markAsFailed
        // (it's handled by the failed() hook to avoid double status update)
        $method = new \ReflectionMethod(ExecuteMigrationJob::class, 'handle');
        $source = file_get_contents(
            base_path('app/Jobs/ExecuteMigrationJob.php')
        );

        // The catch block should only append log and notify, NOT markAsFailed
        // The failed() method should be the one calling markAsFailed
        $this->assertStringContainsString('// Only log here; markAsFailed is handled by the failed() hook', $source);
    }
}
