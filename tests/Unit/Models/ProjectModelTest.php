<?php

use App\Models\Project;

// team Relationship Accessor Tests
test('team relationship returns belongsTo relation', function () {
    $project = new Project;

    $relation = $project->team();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    expect($relation->getForeignKeyName())->toBe('team_id');
});

// environments Relationship Accessor Tests
test('environments relationship returns hasMany relation', function () {
    $project = new Project;

    $relation = $project->environments();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    expect($relation->getForeignKeyName())->toBe('project_id');
});

// settings Relationship Accessor Tests
test('settings relationship returns hasOne relation', function () {
    $project = new Project;

    $relation = $project->settings();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasOne::class);
    expect($relation->getForeignKeyName())->toBe('project_id');
});

// members Relationship Accessor Tests
test('members relationship returns belongsToMany relation with pivot fields', function () {
    $project = new Project;

    $relation = $project->members();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
    expect($relation->getPivotColumns())->toContain('role');
    expect($relation->getPivotColumns())->toContain('environment_permissions');
});

// admins Relationship Accessor Tests
test('admins relationship returns belongsToMany relation', function () {
    $project = new Project;

    $relation = $project->admins();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
});

// owners Relationship Accessor Tests
test('owners relationship returns belongsToMany relation', function () {
    $project = new Project;

    $relation = $project->owners();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
});

// applications Relationship Accessor Tests
test('applications relationship returns hasManyThrough relation', function () {
    $project = new Project;

    $relation = $project->applications();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasManyThrough::class);
});

// services Relationship Accessor Tests
test('services relationship returns hasManyThrough relation', function () {
    $project = new Project;

    $relation = $project->services();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasManyThrough::class);
});

// postgresqls Relationship Accessor Tests
test('postgresqls relationship returns hasManyThrough relation', function () {
    $project = new Project;

    $relation = $project->postgresqls();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasManyThrough::class);
});

// redis Relationship Accessor Tests
test('redis relationship returns hasManyThrough relation', function () {
    $project = new Project;

    $relation = $project->redis();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasManyThrough::class);
});

// mongodbs Relationship Accessor Tests
test('mongodbs relationship returns hasManyThrough relation', function () {
    $project = new Project;

    $relation = $project->mongodbs();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasManyThrough::class);
});

// mysqls Relationship Accessor Tests
test('mysqls relationship returns hasManyThrough relation', function () {
    $project = new Project;

    $relation = $project->mysqls();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasManyThrough::class);
});

// mariadbs Relationship Accessor Tests
test('mariadbs relationship returns hasManyThrough relation', function () {
    $project = new Project;

    $relation = $project->mariadbs();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasManyThrough::class);
});

// keydbs Relationship Accessor Tests
test('keydbs relationship returns hasManyThrough relation', function () {
    $project = new Project;

    $relation = $project->keydbs();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasManyThrough::class);
});

// dragonflies Relationship Accessor Tests
test('dragonflies relationship returns hasManyThrough relation', function () {
    $project = new Project;

    $relation = $project->dragonflies();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasManyThrough::class);
});

// clickhouses Relationship Accessor Tests
test('clickhouses relationship returns hasManyThrough relation', function () {
    $project = new Project;

    $relation = $project->clickhouses();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasManyThrough::class);
});

// environment_variables Relationship Accessor Tests
test('environment_variables relationship returns hasMany relation', function () {
    $project = new Project;

    $relation = $project->environment_variables();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    expect($relation->getForeignKeyName())->toBe('project_id');
});
