<?php

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

// Create a test model class that uses the Auditable trait
class AuditableTestModel extends Model
{
    use Auditable;

    protected $table = 'test_models';
}

it('getAuditResourceName returns name attribute from model', function () {
    $model = new AuditableTestModel;
    $model->forceFill(['name' => 'Test App']);

    $reflection = new ReflectionMethod($model, 'getAuditResourceName');

    expect($reflection->invoke($model))->toBe('Test App');
});

it('getAuditResourceName returns key attribute when name is absent', function () {
    $model = new AuditableTestModel;
    $model->forceFill(['key' => 'API_KEY']);

    $reflection = new ReflectionMethod($model, 'getAuditResourceName');

    expect($reflection->invoke($model))->toBe('API_KEY');
});

it('getAuditResourceName falls back to id when no name attributes exist', function () {
    $model = new AuditableTestModel;
    $model->forceFill(['id' => 42]);

    $reflection = new ReflectionMethod($model, 'getAuditResourceName');

    expect($reflection->invoke($model))->toBe('42');
});

it('getAuditResourceName returns Unknown when model has no identifiable attributes', function () {
    $model = new AuditableTestModel;

    $reflection = new ReflectionMethod($model, 'getAuditResourceName');

    expect($reflection->invoke($model))->toBe('Unknown');
});
