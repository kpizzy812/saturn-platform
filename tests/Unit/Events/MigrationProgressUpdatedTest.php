<?php

namespace Tests\Unit\Events;

use App\Events\MigrationProgressUpdated;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MigrationProgressUpdatedTest extends TestCase
{
    #[Test]
    public function event_implements_should_broadcast(): void
    {
        $event = new MigrationProgressUpdated(
            'test-uuid',
            'in_progress',
            50,
            'Cloning...',
            null,
            null,
            1
        );

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    #[Test]
    public function broadcasts_on_migration_and_team_channels(): void
    {
        $event = new MigrationProgressUpdated(
            'test-uuid',
            'in_progress',
            50,
            'Cloning...',
            null,
            null,
            42
        );

        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertInstanceOf(PrivateChannel::class, $channels[1]);

        // Check channel names
        $this->assertEquals('private-migration.test-uuid', $channels[0]->name);
        $this->assertEquals('private-team.42', $channels[1]->name);
    }

    #[Test]
    public function broadcasts_only_on_migration_channel_without_team(): void
    {
        $event = new MigrationProgressUpdated(
            'test-uuid',
            'in_progress',
            50,
            'Cloning...',
            null,
            null,
            null
        );

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertEquals('private-migration.test-uuid', $channels[0]->name);
    }

    #[Test]
    public function broadcast_with_returns_correct_data(): void
    {
        $event = new MigrationProgressUpdated(
            'test-uuid',
            'in_progress',
            75,
            'Rewiring connections...',
            '[2025-01-01 12:00:00] Rewired DATABASE_URL',
            null,
            1
        );

        $data = $event->broadcastWith();

        $this->assertEquals('test-uuid', $data['uuid']);
        $this->assertEquals('in_progress', $data['status']);
        $this->assertEquals(75, $data['progress']);
        $this->assertEquals('Rewiring connections...', $data['current_step']);
        $this->assertEquals('[2025-01-01 12:00:00] Rewired DATABASE_URL', $data['log_entry']);
        $this->assertNull($data['error_message']);
    }

    #[Test]
    public function broadcast_with_includes_error_message_on_failure(): void
    {
        $event = new MigrationProgressUpdated(
            'test-uuid',
            'failed',
            50,
            'Migration failed',
            null,
            'Connection refused',
            1
        );

        $data = $event->broadcastWith();

        $this->assertEquals('failed', $data['status']);
        $this->assertEquals('Connection refused', $data['error_message']);
    }

    #[Test]
    public function broadcast_with_includes_all_required_fields(): void
    {
        $event = new MigrationProgressUpdated(
            'uuid-123',
            'completed',
            100,
            'Done',
            null,
            null,
            1
        );

        $data = $event->broadcastWith();

        $this->assertArrayHasKey('uuid', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('progress', $data);
        $this->assertArrayHasKey('current_step', $data);
        $this->assertArrayHasKey('log_entry', $data);
        $this->assertArrayHasKey('error_message', $data);
    }
}
