<?php

use App\Jobs\StatusPageSnapshotJob;
use App\Models\StatusPageDailySnapshot;
use App\Models\StatusPageResource;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Mock the snapshot model to avoid actual DB operations
    StatusPageDailySnapshot::unguard();
});

afterEach(function () {
    StatusPageDailySnapshot::reguard();
    Mockery::close();
});

describe('StatusPageSnapshotJob', function () {
    it('can be instantiated', function () {
        $job = new StatusPageSnapshotJob;
        expect($job)->toBeInstanceOf(StatusPageSnapshotJob::class);
    });

    it('has retry configuration', function () {
        $job = new StatusPageSnapshotJob;
        expect($job->tries)->toBe(3);
        expect($job->backoff)->toBe([10, 30, 60]);
    });
});

describe('StatusPageDailySnapshot model', function () {
    it('has correct fillable fields', function () {
        $snapshot = new StatusPageDailySnapshot;
        expect($snapshot->getFillable())->toBe([
            'resource_type',
            'resource_id',
            'snapshot_date',
            'status',
            'uptime_percent',
            'total_checks',
            'healthy_checks',
            'degraded_checks',
            'down_checks',
        ]);
    });

    it('casts fields correctly', function () {
        $snapshot = new StatusPageDailySnapshot;
        $casts = $snapshot->getCasts();
        expect($casts['snapshot_date'])->toBe('date');
        expect($casts['uptime_percent'])->toBe('float');
        expect($casts['total_checks'])->toBe('integer');
    });
});

describe('StatusPageResource normalizeStatus', function () {
    it('normalizes simple statuses', function () {
        expect(StatusPageResource::normalizeStatus('running'))->toBe('operational');
        expect(StatusPageResource::normalizeStatus('healthy'))->toBe('operational');
        expect(StatusPageResource::normalizeStatus('degraded'))->toBe('degraded');
        expect(StatusPageResource::normalizeStatus('exited'))->toBe('major_outage');
        expect(StatusPageResource::normalizeStatus('stopped'))->toBe('major_outage');
        expect(StatusPageResource::normalizeStatus('down'))->toBe('major_outage');
        expect(StatusPageResource::normalizeStatus('unreachable'))->toBe('major_outage');
        expect(StatusPageResource::normalizeStatus('failed'))->toBe('major_outage');
        expect(StatusPageResource::normalizeStatus('restarting'))->toBe('maintenance');
        expect(StatusPageResource::normalizeStatus('in_progress'))->toBe('maintenance');
        expect(StatusPageResource::normalizeStatus('starting'))->toBe('maintenance');
        expect(StatusPageResource::normalizeStatus('something_else'))->toBe('unknown');
    });

    it('normalizes compound colon statuses', function () {
        expect(StatusPageResource::normalizeStatus('running:healthy'))->toBe('operational');
        expect(StatusPageResource::normalizeStatus('running:unhealthy'))->toBe('operational');
        expect(StatusPageResource::normalizeStatus('exited:unknown'))->toBe('major_outage');
        expect(StatusPageResource::normalizeStatus('degraded:unhealthy'))->toBe('degraded');
        expect(StatusPageResource::normalizeStatus('starting:unknown'))->toBe('maintenance');
    });

    it('normalizes compound parenthesis statuses', function () {
        expect(StatusPageResource::normalizeStatus('running(healthy)'))->toBe('operational');
        expect(StatusPageResource::normalizeStatus('exited(unknown)'))->toBe('major_outage');
    });

    it('computes overall status correctly', function () {
        expect(StatusPageResource::computeOverallStatus([]))->toBe('unknown');
        expect(StatusPageResource::computeOverallStatus(['operational', 'operational']))->toBe('operational');
        expect(StatusPageResource::computeOverallStatus(['operational', 'degraded']))->toBe('partial_outage');
        expect(StatusPageResource::computeOverallStatus(['operational', 'major_outage']))->toBe('major_outage');
        expect(StatusPageResource::computeOverallStatus(['operational', 'maintenance']))->toBe('maintenance');
        expect(StatusPageResource::computeOverallStatus(['major_outage', 'degraded']))->toBe('major_outage');
    });
});
