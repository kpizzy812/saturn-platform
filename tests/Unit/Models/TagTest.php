<?php

use App\Models\Application;
use App\Models\Service;
use App\Models\Tag;

// Fillable Security Tests
test('fillable attributes are defined and not empty', function () {
    $tag = new Tag;
    expect($tag->getFillable())->not->toBeEmpty();
});

test('fillable does not include id or team_id', function () {
    $fillable = (new Tag)->getFillable();

    expect($fillable)
        ->not->toContain('id')
        ->not->toContain('team_id');
});

test('fillable includes expected fields', function () {
    $fillable = (new Tag)->getFillable();

    expect($fillable)
        ->toContain('name')
        ->toContain('description');
});

// Relationship Tests
test('applications relationship returns morphedByMany', function () {
    $relation = (new Tag)->applications();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphToMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Application::class);
});

test('services relationship returns morphedByMany', function () {
    $relation = (new Tag)->services();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphToMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Service::class);
});

// Trait Tests
test('uses HasSafeStringAttribute trait', function () {
    expect(class_uses_recursive(new Tag))->toContain(\App\Traits\HasSafeStringAttribute::class);
});

// Attribute Tests
test('name attribute works with raw attributes', function () {
    $tag = new Tag;
    $tag->setRawAttributes(['name' => 'Production'], true);

    expect($tag->name)->toBe('Production');
});

test('description attribute works', function () {
    $tag = new Tag;
    $tag->description = 'Production environment applications';

    expect($tag->description)->toBe('Production environment applications');
});

// Model instantiation test
test('model instantiation works', function () {
    $tag = new Tag;
    expect($tag)->toBeInstanceOf(Tag::class);
});
