<?php

namespace Tests\Unit\Traits;

use App\Traits\ValidatesPublicPort;
use Mockery;
use PHPUnit\Framework\TestCase;

class ValidatesPublicPortTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_trait_exists_and_has_boot_method(): void
    {
        $this->assertTrue(trait_exists(ValidatesPublicPort::class));
        $this->assertTrue(method_exists(ValidatesPublicPort::class, 'bootValidatesPublicPort'));
    }

    public function test_trait_is_used_by_all_standalone_database_models(): void
    {
        $models = [
            \App\Models\StandalonePostgresql::class,
            \App\Models\StandaloneMysql::class,
            \App\Models\StandaloneMariadb::class,
            \App\Models\StandaloneMongodb::class,
            \App\Models\StandaloneRedis::class,
            \App\Models\StandaloneKeydb::class,
            \App\Models\StandaloneDragonfly::class,
            \App\Models\StandaloneClickhouse::class,
        ];

        foreach ($models as $model) {
            $traits = class_uses_recursive($model);
            $this->assertArrayHasKey(
                ValidatesPublicPort::class,
                $traits,
                "{$model} must use ValidatesPublicPort trait"
            );
        }
    }

    public function test_boot_method_is_static_and_returns_void(): void
    {
        $reflection = new \ReflectionMethod(ValidatesPublicPort::class, 'bootValidatesPublicPort');
        $this->assertTrue($reflection->isStatic());

        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('void', $returnType->getName());
    }
}
