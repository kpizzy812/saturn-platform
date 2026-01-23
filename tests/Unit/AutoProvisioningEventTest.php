<?php

use App\Models\AutoProvisioningEvent;

describe('AutoProvisioningEvent', function () {
    it('has correct status constants', function () {
        expect(AutoProvisioningEvent::STATUS_PENDING)->toBe('pending');
        expect(AutoProvisioningEvent::STATUS_PROVISIONING)->toBe('provisioning');
        expect(AutoProvisioningEvent::STATUS_INSTALLING)->toBe('installing');
        expect(AutoProvisioningEvent::STATUS_READY)->toBe('ready');
        expect(AutoProvisioningEvent::STATUS_FAILED)->toBe('failed');
    });

    it('has correct trigger reason constants', function () {
        expect(AutoProvisioningEvent::TRIGGER_CPU_CRITICAL)->toBe('cpu_critical');
        expect(AutoProvisioningEvent::TRIGGER_MEMORY_CRITICAL)->toBe('memory_critical');
        expect(AutoProvisioningEvent::TRIGGER_MANUAL)->toBe('manual');
    });

    it('returns correct trigger reason label for cpu_critical', function () {
        $event = new AutoProvisioningEvent;
        $event->trigger_reason = AutoProvisioningEvent::TRIGGER_CPU_CRITICAL;

        expect($event->trigger_reason_label)->toBe('CPU Overload');
    });

    it('returns correct trigger reason label for memory_critical', function () {
        $event = new AutoProvisioningEvent;
        $event->trigger_reason = AutoProvisioningEvent::TRIGGER_MEMORY_CRITICAL;

        expect($event->trigger_reason_label)->toBe('Memory Overload');
    });

    it('returns correct trigger reason label for manual', function () {
        $event = new AutoProvisioningEvent;
        $event->trigger_reason = AutoProvisioningEvent::TRIGGER_MANUAL;

        expect($event->trigger_reason_label)->toBe('Manual Request');
    });

    it('returns correct status label for pending', function () {
        $event = new AutoProvisioningEvent;
        $event->status = AutoProvisioningEvent::STATUS_PENDING;

        expect($event->status_label)->toBe('Pending');
    });

    it('returns correct status label for provisioning', function () {
        $event = new AutoProvisioningEvent;
        $event->status = AutoProvisioningEvent::STATUS_PROVISIONING;

        expect($event->status_label)->toBe('Creating VPS');
    });

    it('returns correct status label for installing', function () {
        $event = new AutoProvisioningEvent;
        $event->status = AutoProvisioningEvent::STATUS_INSTALLING;

        expect($event->status_label)->toBe('Installing Docker');
    });

    it('returns correct status label for ready', function () {
        $event = new AutoProvisioningEvent;
        $event->status = AutoProvisioningEvent::STATUS_READY;

        expect($event->status_label)->toBe('Ready');
    });

    it('returns correct status label for failed', function () {
        $event = new AutoProvisioningEvent;
        $event->status = AutoProvisioningEvent::STATUS_FAILED;

        expect($event->status_label)->toBe('Failed');
    });

    it('casts trigger_metrics to array', function () {
        $event = new AutoProvisioningEvent;
        $event->trigger_metrics = ['cpu' => 87.5, 'memory' => 92.1];

        expect($event->trigger_metrics)->toBeArray();
        expect($event->trigger_metrics['cpu'])->toBe(87.5);
        expect($event->trigger_metrics['memory'])->toBe(92.1);
    });

    it('casts server_config to array', function () {
        $event = new AutoProvisioningEvent;
        $event->server_config = ['type' => 'cx22', 'location' => 'nbg1'];

        expect($event->server_config)->toBeArray();
        expect($event->server_config['type'])->toBe('cx22');
        expect($event->server_config['location'])->toBe('nbg1');
    });

    it('casts timestamps correctly', function () {
        $event = new AutoProvisioningEvent;
        $event->triggered_at = '2026-01-23 12:00:00';
        $event->provisioned_at = '2026-01-23 12:05:00';
        $event->ready_at = '2026-01-23 12:10:00';

        expect($event->triggered_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        expect($event->provisioned_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        expect($event->ready_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });
});
