<?php

use App\Models\StatusPageIncident;
use App\Models\StatusPageIncidentUpdate;

describe('StatusPageIncident model', function () {
    it('has correct fillable fields', function () {
        $incident = new StatusPageIncident;
        expect($incident->getFillable())->toBe([
            'title',
            'severity',
            'status',
            'started_at',
            'resolved_at',
            'is_visible',
        ]);
    });

    it('casts fields correctly', function () {
        $incident = new StatusPageIncident;
        $casts = $incident->getCasts();
        expect($casts['started_at'])->toBe('datetime');
        expect($casts['resolved_at'])->toBe('datetime');
        expect($casts['is_visible'])->toBe('boolean');
    });

    it('has updates relationship', function () {
        $incident = new StatusPageIncident;
        $relation = $incident->updates();
        expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    });
});

describe('StatusPageIncidentUpdate model', function () {
    it('has correct fillable fields', function () {
        $update = new StatusPageIncidentUpdate;
        expect($update->getFillable())->toBe([
            'incident_id',
            'status',
            'message',
            'posted_at',
        ]);
    });

    it('casts fields correctly', function () {
        $update = new StatusPageIncidentUpdate;
        $casts = $update->getCasts();
        expect($casts['posted_at'])->toBe('datetime');
    });

    it('has incident relationship', function () {
        $update = new StatusPageIncidentUpdate;
        $relation = $update->incident();
        expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    });
});
