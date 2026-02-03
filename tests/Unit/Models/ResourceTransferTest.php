<?php

namespace Tests\Unit\Models;

use App\Models\ResourceTransfer;
use Tests\TestCase;

class ResourceTransferTest extends TestCase
{
    /** @test */
    public function it_has_correct_status_constants(): void
    {
        $this->assertEquals('pending', ResourceTransfer::STATUS_PENDING);
        $this->assertEquals('preparing', ResourceTransfer::STATUS_PREPARING);
        $this->assertEquals('transferring', ResourceTransfer::STATUS_TRANSFERRING);
        $this->assertEquals('restoring', ResourceTransfer::STATUS_RESTORING);
        $this->assertEquals('completed', ResourceTransfer::STATUS_COMPLETED);
        $this->assertEquals('failed', ResourceTransfer::STATUS_FAILED);
        $this->assertEquals('cancelled', ResourceTransfer::STATUS_CANCELLED);
    }

    /** @test */
    public function it_has_correct_mode_constants(): void
    {
        $this->assertEquals('clone', ResourceTransfer::MODE_CLONE);
        $this->assertEquals('data_only', ResourceTransfer::MODE_DATA_ONLY);
        $this->assertEquals('partial', ResourceTransfer::MODE_PARTIAL);
    }

    /** @test */
    public function it_returns_all_statuses(): void
    {
        $statuses = ResourceTransfer::getAllStatuses();

        $this->assertContains('pending', $statuses);
        $this->assertContains('preparing', $statuses);
        $this->assertContains('transferring', $statuses);
        $this->assertContains('restoring', $statuses);
        $this->assertContains('completed', $statuses);
        $this->assertContains('failed', $statuses);
        $this->assertContains('cancelled', $statuses);
        $this->assertCount(7, $statuses);
    }

    /** @test */
    public function it_returns_all_modes(): void
    {
        $modes = ResourceTransfer::getAllModes();

        $this->assertContains('clone', $modes);
        $this->assertContains('data_only', $modes);
        $this->assertContains('partial', $modes);
        $this->assertCount(3, $modes);
    }

    /** @test */
    public function it_returns_correct_status_label(): void
    {
        $transfer = new ResourceTransfer;
        $transfer->status = 'pending';
        $this->assertEquals('Pending', $transfer->status_label);

        $transfer->status = 'preparing';
        $this->assertEquals('Preparing', $transfer->status_label);

        $transfer->status = 'transferring';
        $this->assertEquals('Transferring', $transfer->status_label);

        $transfer->status = 'restoring';
        $this->assertEquals('Restoring', $transfer->status_label);

        $transfer->status = 'completed';
        $this->assertEquals('Completed', $transfer->status_label);

        $transfer->status = 'failed';
        $this->assertEquals('Failed', $transfer->status_label);

        $transfer->status = 'cancelled';
        $this->assertEquals('Cancelled', $transfer->status_label);
    }

    /** @test */
    public function it_returns_correct_mode_label(): void
    {
        $transfer = new ResourceTransfer;
        $transfer->transfer_mode = 'clone';
        $this->assertEquals('Full Clone', $transfer->mode_label);

        $transfer->transfer_mode = 'data_only';
        $this->assertEquals('Data Only', $transfer->mode_label);

        $transfer->transfer_mode = 'partial';
        $this->assertEquals('Partial', $transfer->mode_label);
    }

    /** @test */
    public function it_can_be_cancelled_only_in_correct_states(): void
    {
        $transfer = new ResourceTransfer;

        $transfer->status = 'pending';
        $this->assertTrue($transfer->canBeCancelled());

        $transfer->status = 'preparing';
        $this->assertTrue($transfer->canBeCancelled());

        $transfer->status = 'transferring';
        $this->assertTrue($transfer->canBeCancelled());

        $transfer->status = 'restoring';
        $this->assertFalse($transfer->canBeCancelled());

        $transfer->status = 'completed';
        $this->assertFalse($transfer->canBeCancelled());

        $transfer->status = 'failed';
        $this->assertFalse($transfer->canBeCancelled());

        $transfer->status = 'cancelled';
        $this->assertFalse($transfer->canBeCancelled());
    }

    /** @test */
    public function it_formats_progress_correctly(): void
    {
        $transfer = new ResourceTransfer;
        $transfer->status = 'transferring';
        $transfer->progress = 45;

        $this->assertEquals('45%', $transfer->formatted_progress);

        $transfer->progress = 100;
        $this->assertEquals('100%', $transfer->formatted_progress);

        $transfer->progress = 0;
        $this->assertEquals('0%', $transfer->formatted_progress);
    }

    /** @test */
    public function it_checks_if_transfer_is_active(): void
    {
        $transfer = new ResourceTransfer;

        $transfer->status = 'pending';
        $this->assertTrue($transfer->isActive());

        $transfer->status = 'preparing';
        $this->assertTrue($transfer->isActive());

        $transfer->status = 'transferring';
        $this->assertTrue($transfer->isActive());

        $transfer->status = 'restoring';
        $this->assertTrue($transfer->isActive());

        $transfer->status = 'completed';
        $this->assertFalse($transfer->isActive());

        $transfer->status = 'failed';
        $this->assertFalse($transfer->isActive());

        $transfer->status = 'cancelled';
        $this->assertFalse($transfer->isActive());
    }
}
