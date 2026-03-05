<?php

namespace Tests\Unit\Actions\Deployment;

use App\Actions\Deployment\PromoteImageAction;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\Environment;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for PromoteImageAction.
 *
 * Uses Mockery for model mocking and source-level assertions for
 * static Eloquent calls (queue_application_deployment).
 */
class PromoteImageActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Validation tests
    // -------------------------------------------------------------------------

    /** @test */
    public function it_throws_when_source_deployment_is_not_finished(): void
    {
        $deployment = Mockery::mock(ApplicationDeploymentQueue::class)->makePartial();
        $deployment->status = 'in_progress';

        $environment = Mockery::mock(Environment::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Source deployment must have finished successfully');

        (new PromoteImageAction)->execute($deployment, $environment);
    }

    /** @test */
    public function it_throws_when_source_deployment_has_no_commit(): void
    {
        $deployment = Mockery::mock(ApplicationDeploymentQueue::class)->makePartial();
        $deployment->status = 'finished';
        $deployment->commit = '';

        $environment = Mockery::mock(Environment::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no commit hash');

        (new PromoteImageAction)->execute($deployment, $environment);
    }

    /** @test */
    public function it_throws_when_source_deployment_has_no_application(): void
    {
        $deployment = Mockery::mock(ApplicationDeploymentQueue::class)->makePartial();
        $deployment->status = 'finished';
        $deployment->commit = 'abc123';
        $deployment->shouldReceive('getAttribute')->with('application')->andReturn(null);

        $environment = Mockery::mock(Environment::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no associated application');

        (new PromoteImageAction)->execute($deployment, $environment);
    }

    /** @test */
    public function it_throws_when_source_environment_has_no_project(): void
    {
        $sourceEnv = Mockery::mock(Environment::class)->makePartial();
        $sourceEnv->project_id = null;

        $app = Mockery::mock(Application::class)->makePartial();
        $app->shouldReceive('getAttribute')->with('environment')->andReturn($sourceEnv);

        $deployment = Mockery::mock(ApplicationDeploymentQueue::class)->makePartial();
        $deployment->status = 'finished';
        $deployment->commit = 'abc123';
        $deployment->shouldReceive('getAttribute')->with('application')->andReturn($app);

        $targetEnv = Mockery::mock(Environment::class)->makePartial();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot determine source application project');

        (new PromoteImageAction)->execute($deployment, $targetEnv);
    }

    /** @test */
    public function it_throws_when_target_environment_is_in_different_project(): void
    {
        $sourceEnv = Mockery::mock(Environment::class)->makePartial();
        $sourceEnv->project_id = 1;
        $sourceEnv->id = 10;

        $app = Mockery::mock(Application::class)->makePartial();
        $app->shouldReceive('getAttribute')->with('environment')->andReturn($sourceEnv);
        $app->name = 'my-app';

        $deployment = Mockery::mock(ApplicationDeploymentQueue::class)->makePartial();
        $deployment->status = 'finished';
        $deployment->commit = 'abc123';
        $deployment->shouldReceive('getAttribute')->with('application')->andReturn($app);

        $targetEnv = Mockery::mock(Environment::class)->makePartial();
        $targetEnv->project_id = 2;
        $targetEnv->id = 20;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('same project');

        (new PromoteImageAction)->execute($deployment, $targetEnv);
    }

    /** @test */
    public function it_throws_when_target_environment_is_same_as_source(): void
    {
        $sourceEnv = Mockery::mock(Environment::class)->makePartial();
        $sourceEnv->project_id = 1;
        $sourceEnv->id = 10;

        $app = Mockery::mock(Application::class)->makePartial();
        $app->shouldReceive('getAttribute')->with('environment')->andReturn($sourceEnv);
        $app->name = 'my-app';

        $deployment = Mockery::mock(ApplicationDeploymentQueue::class)->makePartial();
        $deployment->status = 'finished';
        $deployment->commit = 'abc123';
        $deployment->shouldReceive('getAttribute')->with('application')->andReturn($app);

        $targetEnv = Mockery::mock(Environment::class)->makePartial();
        $targetEnv->project_id = 1;
        $targetEnv->id = 10;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be the same');

        (new PromoteImageAction)->execute($deployment, $targetEnv);
    }

    // -------------------------------------------------------------------------
    // Source-level assertions for success path
    // -------------------------------------------------------------------------

    /** @test */
    public function it_source_calls_queue_application_deployment(): void
    {
        $source = file_get_contents(app_path('Actions/Deployment/PromoteImageAction.php'));

        $this->assertStringContainsString('queue_application_deployment(', $source);
        $this->assertStringContainsString('is_promotion: true', $source);
        $this->assertStringContainsString('promoted_from_image:', $source);
    }

    /** @test */
    public function it_source_checks_production_for_approval_requirement(): void
    {
        $source = file_get_contents(app_path('Actions/Deployment/PromoteImageAction.php'));

        $this->assertStringContainsString('$targetEnvironment->isProduction()', $source);
        $this->assertStringContainsString('requires_approval:', $source);
    }

    /** @test */
    public function it_source_builds_image_name_from_registry_or_uuid(): void
    {
        $source = file_get_contents(app_path('Actions/Deployment/PromoteImageAction.php'));

        $this->assertStringContainsString('docker_registry_image_name', $source);
        $this->assertStringContainsString('$sourceApp->uuid', $source);
    }

    /** @test */
    public function it_source_returns_deployment_uuid_and_promoted_image(): void
    {
        $source = file_get_contents(app_path('Actions/Deployment/PromoteImageAction.php'));

        $this->assertStringContainsString("'deployment_uuid'", $source);
        $this->assertStringContainsString("'promoted_image'", $source);
    }

    /** @test */
    public function it_source_uses_cuid2_for_deployment_uuid(): void
    {
        $source = file_get_contents(app_path('Actions/Deployment/PromoteImageAction.php'));

        $this->assertStringContainsString('new Cuid2', $source);
    }

    /** @test */
    public function it_source_finds_target_app_by_name_and_environment(): void
    {
        $source = file_get_contents(app_path('Actions/Deployment/PromoteImageAction.php'));

        $this->assertStringContainsString("Application::where('environment_id', \$targetEnvironment->id)", $source);
        $this->assertStringContainsString("->where('name', \$sourceApp->name)", $source);
    }

    /** @test */
    public function it_throws_when_target_app_not_found(): void
    {
        $source = file_get_contents(app_path('Actions/Deployment/PromoteImageAction.php'));

        $this->assertStringContainsString('not found in target environment', $source);
        $this->assertStringContainsString('Create it first, then promote', $source);
    }
}
