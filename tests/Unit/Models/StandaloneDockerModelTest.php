<?php

use App\Models\StandaloneDocker;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

// Fillable Security Tests
test('fillable does not contain id', function () {
    $fillable = (new StandaloneDocker)->getFillable();

    expect($fillable)->not->toContain('id');
});

test('fillable includes server_id for creation', function () {
    $fillable = (new StandaloneDocker)->getFillable();

    expect($fillable)->toContain('server_id');
});

test('fillable is not empty', function () {
    $fillable = (new StandaloneDocker)->getFillable();

    expect($fillable)
        ->not->toBeEmpty()
        ->toBeArray();
});

test('fillable contains expected fields', function () {
    $fillable = (new StandaloneDocker)->getFillable();

    expect($fillable)
        ->toContain('name')
        ->toContain('network');
});

// Relationship Tests - BelongsTo
test('server relationship returns BelongsTo', function () {
    $docker = new StandaloneDocker;
    expect($docker->server())->toBeInstanceOf(BelongsTo::class);
});

// Relationship Tests - MorphMany
test('applications relationship returns MorphMany', function () {
    $docker = new StandaloneDocker;
    expect($docker->applications())->toBeInstanceOf(MorphMany::class);
});

test('postgresqls relationship returns MorphMany', function () {
    $docker = new StandaloneDocker;
    expect($docker->postgresqls())->toBeInstanceOf(MorphMany::class);
});

test('redis relationship returns MorphMany', function () {
    $docker = new StandaloneDocker;
    expect($docker->redis())->toBeInstanceOf(MorphMany::class);
});

test('mongodbs relationship returns MorphMany', function () {
    $docker = new StandaloneDocker;
    expect($docker->mongodbs())->toBeInstanceOf(MorphMany::class);
});

test('mysqls relationship returns MorphMany', function () {
    $docker = new StandaloneDocker;
    expect($docker->mysqls())->toBeInstanceOf(MorphMany::class);
});

test('mariadbs relationship returns MorphMany', function () {
    $docker = new StandaloneDocker;
    expect($docker->mariadbs())->toBeInstanceOf(MorphMany::class);
});

test('keydbs relationship returns MorphMany', function () {
    $docker = new StandaloneDocker;
    expect($docker->keydbs())->toBeInstanceOf(MorphMany::class);
});

test('dragonflies relationship returns MorphMany', function () {
    $docker = new StandaloneDocker;
    expect($docker->dragonflies())->toBeInstanceOf(MorphMany::class);
});

test('clickhouses relationship returns MorphMany', function () {
    $docker = new StandaloneDocker;
    expect($docker->clickhouses())->toBeInstanceOf(MorphMany::class);
});

test('services relationship returns MorphMany', function () {
    $docker = new StandaloneDocker;
    expect($docker->services())->toBeInstanceOf(MorphMany::class);
});

// Trait Tests
test('model uses HasSafeStringAttribute trait', function () {
    $docker = new StandaloneDocker;
    expect(class_uses_recursive($docker))
        ->toHaveKey('App\Traits\HasSafeStringAttribute');
});

// Method Tests
test('databases method exists', function () {
    $docker = new StandaloneDocker;
    expect(method_exists($docker, 'databases'))->toBeTrue();
});

test('attachedTo method exists', function () {
    $docker = new StandaloneDocker;
    expect(method_exists($docker, 'attachedTo'))->toBeTrue();
});

test('attachedTo returns false when no applications or databases', function () {
    $docker = new StandaloneDocker;
    expect($docker->attachedTo())->toBeFalse();
});
