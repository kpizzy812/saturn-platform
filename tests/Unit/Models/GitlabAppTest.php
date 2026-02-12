<?php

use App\Models\Application;
use App\Models\GitlabApp;
use App\Models\PrivateKey;

// Casts Tests
test('webhook_token is cast to encrypted', function () {
    $casts = (new GitlabApp)->getCasts();
    expect($casts['webhook_token'])->toBe('encrypted');
});

test('app_secret is cast to encrypted', function () {
    $casts = (new GitlabApp)->getCasts();
    expect($casts['app_secret'])->toBe('encrypted');
});

// Hidden Attributes Tests
test('webhook_token is hidden from JSON', function () {
    $hidden = (new GitlabApp)->getHidden();
    expect($hidden)->toContain('webhook_token');
});

test('app_secret is hidden from JSON', function () {
    $hidden = (new GitlabApp)->getHidden();
    expect($hidden)->toContain('app_secret');
});

// Relationship Tests
test('applications relationship returns morphMany', function () {
    $relation = (new GitlabApp)->applications();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Application::class);
});

test('privateKey relationship returns belongsTo', function () {
    $relation = (new GitlabApp)->privateKey();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(PrivateKey::class);
});

// Trait Tests
test('uses Auditable trait', function () {
    expect(class_uses_recursive(new GitlabApp))->toContain(\App\Traits\Auditable::class);
});

test('uses LogsActivity trait', function () {
    expect(class_uses_recursive(new GitlabApp))->toContain(\Spatie\Activitylog\Traits\LogsActivity::class);
});

// Activity Log Tests
test('getActivitylogOptions returns LogOptions', function () {
    $gitlabApp = new GitlabApp;
    $options = $gitlabApp->getActivitylogOptions();

    expect($options)->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
});

// Note: GitlabApp extends BaseModel which handles team_id and other base fields
// The model doesn't have explicit $fillable, inheriting from BaseModel
test('model instantiation works', function () {
    $gitlabApp = new GitlabApp;
    expect($gitlabApp)->toBeInstanceOf(GitlabApp::class);
});
