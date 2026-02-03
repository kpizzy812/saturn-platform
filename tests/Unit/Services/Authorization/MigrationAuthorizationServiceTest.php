<?php

namespace Tests\Unit\Services\Authorization;

use App\Models\Environment;
use App\Services\Authorization\MigrationAuthorizationService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MigrationAuthorizationServiceTest extends TestCase
{
    private MigrationAuthorizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MigrationAuthorizationService;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create a mock Environment with specified type.
     * Uses Mockery alias to avoid Eloquent magic method issues.
     */
    protected function createMockEnvironment(int $id, string $type): Environment
    {
        $env = Mockery::mock(Environment::class)->makePartial();
        $env->forceFill(['id' => $id, 'type' => $type]);

        return $env;
    }

    // ==================== isValidMigrationChain tests ====================

    #[Test]
    public function valid_chain_dev_to_uat(): void
    {
        $sourceEnv = $this->createMockEnvironment(1, 'development');
        $targetEnv = $this->createMockEnvironment(2, 'uat');

        $result = $this->service->isValidMigrationChain($sourceEnv, $targetEnv);

        $this->assertTrue($result);
    }

    #[Test]
    public function valid_chain_uat_to_production(): void
    {
        $sourceEnv = $this->createMockEnvironment(1, 'uat');
        $targetEnv = $this->createMockEnvironment(2, 'production');

        $result = $this->service->isValidMigrationChain($sourceEnv, $targetEnv);

        $this->assertTrue($result);
    }

    #[Test]
    public function invalid_chain_dev_to_production(): void
    {
        $sourceEnv = $this->createMockEnvironment(1, 'development');
        $targetEnv = $this->createMockEnvironment(2, 'production');

        $result = $this->service->isValidMigrationChain($sourceEnv, $targetEnv);

        $this->assertFalse($result, 'Skipping uat in chain should be invalid');
    }

    #[Test]
    public function invalid_chain_production_to_dev(): void
    {
        $sourceEnv = $this->createMockEnvironment(1, 'production');
        $targetEnv = $this->createMockEnvironment(2, 'development');

        $result = $this->service->isValidMigrationChain($sourceEnv, $targetEnv);

        $this->assertFalse($result, 'Reverse migration should be invalid');
    }

    #[Test]
    public function invalid_chain_same_type(): void
    {
        $sourceEnv = $this->createMockEnvironment(1, 'development');
        $targetEnv = $this->createMockEnvironment(2, 'development');

        $result = $this->service->isValidMigrationChain($sourceEnv, $targetEnv);

        $this->assertFalse($result, 'Same type migration should be invalid');
    }

    // ==================== getNextEnvironmentType tests ====================

    #[Test]
    public function next_type_from_dev_is_uat(): void
    {
        $environment = $this->createMockEnvironment(1, 'development');

        $result = $this->service->getNextEnvironmentType($environment);

        $this->assertEquals('uat', $result);
    }

    #[Test]
    public function next_type_from_uat_is_production(): void
    {
        $environment = $this->createMockEnvironment(1, 'uat');

        $result = $this->service->getNextEnvironmentType($environment);

        $this->assertEquals('production', $result);
    }

    #[Test]
    public function next_type_from_production_is_null(): void
    {
        $environment = $this->createMockEnvironment(1, 'production');

        $result = $this->service->getNextEnvironmentType($environment);

        $this->assertNull($result, 'Production is the end of chain');
    }

    // ==================== canMigrateFrom tests ====================

    #[Test]
    public function can_migrate_from_dev(): void
    {
        $environment = $this->createMockEnvironment(1, 'development');

        $result = $this->service->canMigrateFrom($environment);

        $this->assertTrue($result);
    }

    #[Test]
    public function can_migrate_from_uat(): void
    {
        $environment = $this->createMockEnvironment(1, 'uat');

        $result = $this->service->canMigrateFrom($environment);

        $this->assertTrue($result);
    }

    #[Test]
    public function cannot_migrate_from_production(): void
    {
        $environment = $this->createMockEnvironment(1, 'production');

        $result = $this->service->canMigrateFrom($environment);

        $this->assertFalse($result, 'Cannot migrate from production - end of chain');
    }
}
