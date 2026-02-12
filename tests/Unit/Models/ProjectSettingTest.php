<?php

use App\Models\Project;
use App\Models\ProjectSetting;
use App\Models\Server;

// Fillable Security Tests
test('fillable attributes are defined and not empty', function () {
    $setting = new ProjectSetting;
    expect($setting->getFillable())->not->toBeEmpty();
});

test('fillable does not include id', function () {
    $fillable = (new ProjectSetting)->getFillable();

    expect($fillable)->not->toContain('id');
});

test('fillable includes expected fields', function () {
    $fillable = (new ProjectSetting)->getFillable();

    expect($fillable)
        ->toContain('project_id')
        ->toContain('default_server_id');
});

// Relationship Tests
test('project relationship returns belongsTo', function () {
    $relation = (new ProjectSetting)->project();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Project::class);
});

test('defaultServer relationship returns belongsTo', function () {
    $relation = (new ProjectSetting)->defaultServer();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Server::class);
});

// Trait Tests
test('uses Auditable trait', function () {
    expect(class_uses_recursive(new ProjectSetting))->toContain(\App\Traits\Auditable::class);
});

test('uses LogsActivity trait', function () {
    expect(class_uses_recursive(new ProjectSetting))->toContain(\Spatie\Activitylog\Traits\LogsActivity::class);
});

// Activity Log Tests
test('getActivitylogOptions returns LogOptions', function () {
    $setting = new ProjectSetting;
    $options = $setting->getActivitylogOptions();

    expect($options)->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
});

// Attribute Tests
test('project_id attribute works', function () {
    $setting = new ProjectSetting;
    $setting->project_id = 1;

    expect($setting->project_id)->toBe(1);
});

test('default_server_id attribute works', function () {
    $setting = new ProjectSetting;
    $setting->default_server_id = 5;

    expect($setting->default_server_id)->toBe(5);
});
