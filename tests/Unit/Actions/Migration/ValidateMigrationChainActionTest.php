<?php

namespace Tests\Unit\Actions\Migration;

use App\Actions\Migration\ValidateMigrationChainAction;
use App\Models\Environment;
use App\Services\Authorization\MigrationAuthorizationService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ValidateMigrationChainActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create a mock Environment with specified properties using makePartial + forceFill.
     */
    protected function createMockEnvironment(
        int $id = 1,
        string $type = 'development',
        int $projectId = 1
    ): Environment {
        $environment = Mockery::mock(Environment::class)->makePartial();
        $environment->forceFill(['id' => $id, 'type' => $type, 'project_id' => $projectId]);

        return $environment;
    }

    /**
     * Mock the MigrationAuthorizationService.
     */
    protected function mockAuthService(bool $isValidChain = true, ?string $nextType = 'uat'): void
    {
        $authService = Mockery::mock(MigrationAuthorizationService::class);
        $authService->shouldReceive('isValidMigrationChain')
            ->andReturn($isValidChain);
        $authService->shouldReceive('getNextEnvironmentType')
            ->andReturn($nextType);

        $this->app->instance(MigrationAuthorizationService::class, $authService);
    }

    #[Test]
    public function it_validates_same_environment_migration_as_invalid(): void
    {
        $sourceEnv = $this->createMockEnvironment(1, 'development', 1);
        $targetEnv = $this->createMockEnvironment(1, 'development', 1); // Same ID

        $action = new ValidateMigrationChainAction;
        $result = $action->handle($sourceEnv, $targetEnv);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('same environment', $result['error']);
    }

    #[Test]
    public function it_validates_cross_project_migration_as_invalid(): void
    {
        $sourceEnv = $this->createMockEnvironment(1, 'development', 1);
        $targetEnv = $this->createMockEnvironment(2, 'uat', 2); // Different project

        $action = new ValidateMigrationChainAction;
        $result = $action->handle($sourceEnv, $targetEnv);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('same project', $result['error']);
    }

    #[Test]
    public function it_validates_dev_to_uat_as_valid(): void
    {
        $sourceEnv = $this->createMockEnvironment(1, 'development', 1);
        $targetEnv = $this->createMockEnvironment(2, 'uat', 1);

        $this->mockAuthService(isValidChain: true);

        $action = new ValidateMigrationChainAction;
        $result = $action->handle($sourceEnv, $targetEnv);

        $this->assertTrue($result['valid']);
        $this->assertEquals('development', $result['source_type']);
        $this->assertEquals('uat', $result['target_type']);
    }

    #[Test]
    public function it_validates_uat_to_production_as_valid(): void
    {
        $sourceEnv = $this->createMockEnvironment(1, 'uat', 1);
        $targetEnv = $this->createMockEnvironment(2, 'production', 1);

        $this->mockAuthService(isValidChain: true);

        $action = new ValidateMigrationChainAction;
        $result = $action->handle($sourceEnv, $targetEnv);

        $this->assertTrue($result['valid']);
        $this->assertEquals('uat', $result['source_type']);
        $this->assertEquals('production', $result['target_type']);
    }

    #[Test]
    public function it_rejects_skipping_uat_dev_to_production(): void
    {
        $sourceEnv = $this->createMockEnvironment(1, 'development', 1);
        $targetEnv = $this->createMockEnvironment(2, 'production', 1);

        $this->mockAuthService(isValidChain: false, nextType: 'uat');

        $action = new ValidateMigrationChainAction;
        $result = $action->handle($sourceEnv, $targetEnv);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Invalid migration chain', $result['error']);
        $this->assertStringContainsString('uat', $result['error']);
    }

    #[Test]
    public function it_rejects_migration_from_production(): void
    {
        $sourceEnv = $this->createMockEnvironment(1, 'production', 1);
        $targetEnv = $this->createMockEnvironment(2, 'development', 1);

        $this->mockAuthService(isValidChain: false, nextType: null);

        $action = new ValidateMigrationChainAction;
        $result = $action->handle($sourceEnv, $targetEnv);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('final environment', $result['error']);
    }

    #[Test]
    public function it_returns_correct_source_and_target_types(): void
    {
        $sourceEnv = $this->createMockEnvironment(1, 'uat', 1);
        $targetEnv = $this->createMockEnvironment(2, 'production', 1);

        $this->mockAuthService(isValidChain: true);

        $action = new ValidateMigrationChainAction;
        $result = $action->handle($sourceEnv, $targetEnv);

        $this->assertArrayHasKey('source_type', $result);
        $this->assertArrayHasKey('target_type', $result);
        $this->assertEquals('uat', $result['source_type']);
        $this->assertEquals('production', $result['target_type']);
    }
}
