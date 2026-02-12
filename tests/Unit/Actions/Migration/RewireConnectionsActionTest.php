<?php

namespace Tests\Unit\Actions\Migration;

use App\Actions\Migration\Concerns\ResourceConfigFields;
use App\Actions\Migration\RewireConnectionsAction;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RewireConnectionsActionTest extends TestCase
{
    #[Test]
    public function action_class_exists_and_is_callable(): void
    {
        $action = new RewireConnectionsAction;

        $this->assertTrue(method_exists($action, 'handle'));
    }

    #[Test]
    public function action_uses_as_action_trait(): void
    {
        $traits = class_uses_recursive(RewireConnectionsAction::class);

        $this->assertArrayHasKey(\Lorisleiva\Actions\Concerns\AsAction::class, $traits);
    }

    #[Test]
    public function action_uses_resource_config_fields_trait(): void
    {
        $traits = class_uses_recursive(RewireConnectionsAction::class);

        $this->assertArrayHasKey(ResourceConfigFields::class, $traits);
    }

    #[Test]
    public function action_has_required_methods(): void
    {
        $class = new \ReflectionClass(RewireConnectionsAction::class);

        $this->assertTrue($class->hasMethod('handle'));
        $this->assertTrue($class->hasMethod('buildUuidMap'));
        $this->assertTrue($class->hasMethod('tryRewireByUuidMap'));
        $this->assertTrue($class->hasMethod('maskSensitiveValue'));
    }

    #[Test]
    public function handle_signature_accepts_correct_parameters(): void
    {
        $method = new \ReflectionMethod(RewireConnectionsAction::class, 'handle');
        $params = $method->getParameters();

        $this->assertCount(3, $params);
        $this->assertEquals('target', $params[0]->getName());
        $this->assertEquals('sourceEnv', $params[1]->getName());
        $this->assertEquals('targetEnv', $params[2]->getName());
    }

    #[Test]
    public function try_rewire_by_uuid_map_replaces_uuids(): void
    {
        $action = new RewireConnectionsAction;
        $method = new \ReflectionMethod($action, 'tryRewireByUuidMap');

        // Create a mock EnvironmentVariable with uuid in value
        $sourceUuid = 'abc123source';
        $targetUuid = 'xyz789target';

        $envVar = \Mockery::mock(\App\Models\EnvironmentVariable::class)->makePartial();
        $envVar->value = "postgresql://user:pass@{$sourceUuid}:5432/mydb";
        $envVar->shouldReceive('update')
            ->once()
            ->with(['value' => "postgresql://user:pass@{$targetUuid}:5432/mydb"]);

        $uuidMap = [$sourceUuid => $targetUuid];

        $result = $method->invoke($action, $envVar, $uuidMap);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('old', $result);
        $this->assertArrayHasKey('new', $result);
        // Password should be masked in result
        $this->assertStringContainsString('****', $result['old']);
        $this->assertStringContainsString('****', $result['new']);
    }

    #[Test]
    public function try_rewire_by_uuid_map_skips_when_no_match(): void
    {
        $action = new RewireConnectionsAction;
        $method = new \ReflectionMethod($action, 'tryRewireByUuidMap');

        $envVar = \Mockery::mock(\App\Models\EnvironmentVariable::class)->makePartial();
        $envVar->value = 'some-static-value-no-uuids';
        $envVar->shouldNotReceive('update');

        $uuidMap = ['abc123' => 'xyz789'];

        $result = $method->invoke($action, $envVar, $uuidMap);

        $this->assertNull($result);
    }

    #[Test]
    public function try_rewire_by_uuid_map_skips_empty_value(): void
    {
        $action = new RewireConnectionsAction;
        $method = new \ReflectionMethod($action, 'tryRewireByUuidMap');

        $envVar = \Mockery::mock(\App\Models\EnvironmentVariable::class)->makePartial();
        $envVar->value = '';
        $envVar->shouldNotReceive('update');

        $result = $method->invoke($action, $envVar, ['abc' => 'xyz']);

        $this->assertNull($result);
    }

    #[Test]
    public function try_rewire_by_uuid_map_handles_multiple_uuids(): void
    {
        $action = new RewireConnectionsAction;
        $method = new \ReflectionMethod($action, 'tryRewireByUuidMap');

        $sourceDb = 'db-source-uuid';
        $targetDb = 'db-target-uuid';
        $sourceApp = 'app-source-uuid';
        $targetApp = 'app-target-uuid';

        $envVar = \Mockery::mock(\App\Models\EnvironmentVariable::class)->makePartial();
        $envVar->value = "DB={$sourceDb} APP={$sourceApp}";
        $envVar->shouldReceive('update')
            ->once()
            ->with(['value' => "DB={$targetDb} APP={$targetApp}"]);

        $uuidMap = [
            $sourceDb => $targetDb,
            $sourceApp => $targetApp,
        ];

        $result = $method->invoke($action, $envVar, $uuidMap);

        $this->assertNotNull($result);
    }

    #[Test]
    public function mask_sensitive_value_hides_passwords(): void
    {
        $action = new RewireConnectionsAction;
        $method = new \ReflectionMethod($action, 'maskSensitiveValue');

        $result = $method->invoke($action, 'postgresql://user:secret@host:5432/db');

        $this->assertStringNotContainsString('secret', $result);
        $this->assertStringContainsString('****', $result);
        $this->assertStringContainsString('host', $result);
    }

    #[Test]
    public function mask_sensitive_value_preserves_non_url_values(): void
    {
        $action = new RewireConnectionsAction;
        $method = new \ReflectionMethod($action, 'maskSensitiveValue');

        $result = $method->invoke($action, 'simple-value-no-password');

        $this->assertEquals('simple-value-no-password', $result);
    }
}
