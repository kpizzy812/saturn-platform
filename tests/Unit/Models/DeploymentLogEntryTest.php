<?php

namespace Tests\Unit\Models;

use App\Models\DeploymentLogEntry;
use PHPUnit\Framework\TestCase;

class DeploymentLogEntryTest extends TestCase
{
    public function test_to_legacy_format_returns_expected_structure(): void
    {
        $entry = new DeploymentLogEntry([
            'order' => 1,
            'command' => null,
            'output' => 'Test output',
            'type' => 'stdout',
            'hidden' => false,
            'batch' => 1,
            'stage' => 'build',
        ]);

        $result = $entry->toLegacyFormat();

        $this->assertArrayHasKey('order', $result);
        $this->assertArrayHasKey('command', $result);
        $this->assertArrayHasKey('output', $result);
        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('hidden', $result);
        $this->assertArrayHasKey('batch', $result);
        $this->assertArrayHasKey('stage', $result);

        $this->assertEquals(1, $result['order']);
        $this->assertEquals('Test output', $result['output']);
        $this->assertEquals('stdout', $result['type']);
        $this->assertEquals('build', $result['stage']);
        // Note: hidden returns [] for uninitialized model, false when loaded from DB
    }

    public function test_fillable_attributes_are_correct(): void
    {
        $entry = new DeploymentLogEntry;

        $this->assertEquals([
            'deployment_id',
            'order',
            'command',
            'output',
            'type',
            'hidden',
            'batch',
            'stage',
        ], $entry->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $entry = new DeploymentLogEntry([
            'hidden' => 1,
            'batch' => '2',
            'order' => '5',
        ]);

        // Verify casts convert to correct types
        $this->assertIsBool($entry->hidden);
        $this->assertIsInt($entry->batch);
        $this->assertIsInt($entry->order);
    }
}
