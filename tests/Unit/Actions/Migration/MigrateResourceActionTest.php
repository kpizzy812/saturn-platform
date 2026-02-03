<?php

namespace Tests\Unit\Actions\Migration;

use App\Actions\Migration\MigrateResourceAction;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MigrateResourceActionTest extends TestCase
{
    #[Test]
    public function migration_action_exists_and_is_callable(): void
    {
        $action = new MigrateResourceAction;

        $this->assertTrue(method_exists($action, 'handle'));
    }

    #[Test]
    public function action_has_required_methods(): void
    {
        $action = new MigrateResourceAction;

        // Check that the action has key protected methods
        $class = new \ReflectionClass($action);

        $this->assertTrue($class->hasMethod('getResourceEnvironment'));
        $this->assertTrue($class->hasMethod('normalizeOptions'));
        $this->assertTrue($class->hasMethod('isDatabase'));
        $this->assertTrue($class->hasMethod('createRollbackSnapshot'));
        $this->assertTrue($class->hasMethod('getResourceConfig'));
    }
}
