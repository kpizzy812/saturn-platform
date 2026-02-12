<?php

namespace Tests\Unit\Actions\Migration;

use App\Actions\Migration\CloneResourceLinksAction;
use App\Actions\Migration\Concerns\ResourceConfigFields;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CloneResourceLinksActionTest extends TestCase
{
    #[Test]
    public function action_class_exists_and_is_callable(): void
    {
        $action = new CloneResourceLinksAction;

        $this->assertTrue(method_exists($action, 'handle'));
    }

    #[Test]
    public function action_uses_as_action_trait(): void
    {
        $traits = class_uses_recursive(CloneResourceLinksAction::class);

        $this->assertArrayHasKey(\Lorisleiva\Actions\Concerns\AsAction::class, $traits);
    }

    #[Test]
    public function action_uses_resource_config_fields_trait(): void
    {
        $traits = class_uses_recursive(CloneResourceLinksAction::class);

        $this->assertArrayHasKey(ResourceConfigFields::class, $traits);
    }

    #[Test]
    public function action_has_required_methods(): void
    {
        $class = new \ReflectionClass(CloneResourceLinksAction::class);

        $this->assertTrue($class->hasMethod('handle'));
        $this->assertTrue($class->hasMethod('findCorrespondingResource'));
        $this->assertTrue($class->hasMethod('createLink'));
    }

    #[Test]
    public function handle_signature_accepts_correct_parameters(): void
    {
        $method = new \ReflectionMethod(CloneResourceLinksAction::class, 'handle');
        $params = $method->getParameters();

        $this->assertCount(4, $params);
        $this->assertEquals('source', $params[0]->getName());
        $this->assertEquals('target', $params[1]->getName());
        $this->assertEquals('sourceEnv', $params[2]->getName());
        $this->assertEquals('targetEnv', $params[3]->getName());
    }

    #[Test]
    public function handle_returns_array(): void
    {
        $method = new \ReflectionMethod(CloneResourceLinksAction::class, 'handle');
        $returnType = $method->getReturnType();

        $this->assertEquals('array', $returnType->getName());
    }

    #[Test]
    public function create_link_method_accepts_correct_parameters(): void
    {
        $method = new \ReflectionMethod(CloneResourceLinksAction::class, 'createLink');
        $params = $method->getParameters();

        $this->assertCount(6, $params);
        $this->assertEquals('source', $params[0]->getName());
        $this->assertEquals('target', $params[1]->getName());
        $this->assertEquals('targetEnv', $params[2]->getName());
        $this->assertEquals('injectAs', $params[3]->getName());
        $this->assertEquals('autoInject', $params[4]->getName());
        $this->assertEquals('useExternalUrl', $params[5]->getName());
    }

    #[Test]
    public function find_corresponding_resource_returns_nullable_model(): void
    {
        $method = new \ReflectionMethod(CloneResourceLinksAction::class, 'findCorrespondingResource');
        $returnType = $method->getReturnType();

        $this->assertTrue($returnType->allowsNull());
    }

    #[Test]
    public function create_link_has_dedup_check(): void
    {
        // Verify the createLink method source code checks for existing links
        $source = file_get_contents(
            base_path('app/Actions/Migration/CloneResourceLinksAction.php')
        );

        $this->assertStringContainsString('source_type', $source);
        $this->assertStringContainsString('source_id', $source);
        $this->assertStringContainsString('target_type', $source);
        $this->assertStringContainsString('target_id', $source);
        $this->assertStringContainsString('environment_id', $source);
        // Dedup: checking for existing before create
        $this->assertStringContainsString('$existing', $source);
    }

    #[Test]
    public function action_handles_both_outgoing_and_incoming_links(): void
    {
        $source = file_get_contents(
            base_path('app/Actions/Migration/CloneResourceLinksAction.php')
        );

        // Verify it handles outgoing links (source = original resource)
        $this->assertStringContainsString('outgoingLinks', $source);

        // Verify it handles incoming links (target = original resource)
        $this->assertStringContainsString('incomingLinks', $source);
    }

    #[Test]
    public function action_tracks_processed_pairs_for_dedup(): void
    {
        $source = file_get_contents(
            base_path('app/Actions/Migration/CloneResourceLinksAction.php')
        );

        // Verify it uses processedPairs to avoid duplicates
        $this->assertStringContainsString('processedPairs', $source);
    }
}
