<?php

use App\Models\AuditLog;

it('has resource_name in fillable', function () {
    $model = new AuditLog;
    expect($model->getFillable())->toContain('resource_name');
});

it('has correct fillable fields', function () {
    $model = new AuditLog;
    $fillable = $model->getFillable();

    expect($fillable)->toContain('user_id');
    expect($fillable)->toContain('team_id');
    expect($fillable)->toContain('action');
    expect($fillable)->toContain('resource_type');
    expect($fillable)->toContain('resource_id');
    expect($fillable)->toContain('resource_name');
    expect($fillable)->toContain('description');
    expect($fillable)->toContain('metadata');
});

it('formatted_action returns correct labels', function () {
    $log = new AuditLog;

    $log->action = 'create';
    expect($log->formatted_action)->toBe('Created');

    $log->action = 'update';
    expect($log->formatted_action)->toBe('Updated');

    $log->action = 'delete';
    expect($log->formatted_action)->toBe('Deleted');

    $log->action = 'deploy';
    expect($log->formatted_action)->toBe('Deployed');
});

it('resource_type_name returns class basename', function () {
    $log = new AuditLog;

    $log->resource_type = 'App\\Models\\Application';
    expect($log->resource_type_name)->toBe('Application');

    $log->resource_type = 'App\\Models\\EnvironmentVariable';
    expect($log->resource_type_name)->toBe('EnvironmentVariable');

    $log->resource_type = null;
    expect($log->resource_type_name)->toBeNull();
});

it('log method extracts name from Eloquent getAttribute', function () {
    // Verify the log method uses getAttribute() instead of property_exists()
    // by checking the source code pattern
    $reflection = new ReflectionMethod(AuditLog::class, 'log');
    $source = file_get_contents($reflection->getFileName());

    // Should use getAttribute pattern
    expect($source)->toContain("\$resource->getAttribute('name')");
    expect($source)->toContain("\$resource->getAttribute('title')");
    expect($source)->toContain("\$resource->getAttribute('key')");

    // Should NOT use property_exists for resource name extraction
    // (property_exists doesn't work with Eloquent dynamic attributes)
    expect($source)->not->toContain("property_exists(\$resource, 'name')");
    expect($source)->not->toContain("property_exists(\$resource, 'title')");
});
