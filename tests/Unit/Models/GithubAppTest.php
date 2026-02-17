<?php

use App\Models\Application;
use App\Models\GithubApp;
use App\Models\PrivateKey;
use App\Models\Team;

// Fillable Security Tests
test('fillable attributes are defined and not empty', function () {
    $githubApp = new GithubApp;
    expect($githubApp->getFillable())->not->toBeEmpty();
});

test('fillable does not include id or team_id', function () {
    $fillable = (new GithubApp)->getFillable();

    expect($fillable)
        ->not->toContain('id')
        ->not->toContain('team_id');
});

test('fillable does not include relationship management fields', function () {
    $fillable = (new GithubApp)->getFillable();

    expect($fillable)->not->toContain('private_key_id');
});

test('fillable includes expected fields', function () {
    $fillable = (new GithubApp)->getFillable();

    expect($fillable)
        ->toContain('name')
        ->toContain('organization')
        ->toContain('api_url')
        ->toContain('html_url')
        ->toContain('custom_user')
        ->toContain('custom_port')
        ->toContain('app_id')
        ->toContain('installation_id')
        ->toContain('client_id')
        ->toContain('client_secret')
        ->toContain('webhook_secret')
        ->toContain('is_public')
        ->toContain('is_system_wide');
});

// Casts Tests
test('is_public is cast to boolean', function () {
    $casts = (new GithubApp)->getCasts();
    expect($casts['is_public'])->toBe('boolean');
});

test('is_system_wide is cast to boolean', function () {
    $casts = (new GithubApp)->getCasts();
    expect($casts['is_system_wide'])->toBe('boolean');
});

test('client_secret is cast to encrypted', function () {
    $casts = (new GithubApp)->getCasts();
    expect($casts['client_secret'])->toBe('encrypted');
});

test('webhook_secret is cast to encrypted', function () {
    $casts = (new GithubApp)->getCasts();
    expect($casts['webhook_secret'])->toBe('encrypted');
});

// Hidden Attributes Tests
test('client_secret is hidden from JSON', function () {
    $hidden = (new GithubApp)->getHidden();
    expect($hidden)->toContain('client_secret');
});

test('webhook_secret is hidden from JSON', function () {
    $hidden = (new GithubApp)->getHidden();
    expect($hidden)->toContain('webhook_secret');
});

// Relationship Tests
test('team relationship returns belongsTo', function () {
    $relation = (new GithubApp)->team();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Team::class);
});

test('applications relationship returns morphMany', function () {
    $relation = (new GithubApp)->applications();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(Application::class);
});

test('privateKey relationship returns belongsTo', function () {
    $relation = (new GithubApp)->privateKey();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(PrivateKey::class);
});

// Trait Tests
test('uses Auditable trait', function () {
    expect(class_uses_recursive(new GithubApp))->toContain(\App\Traits\Auditable::class);
});

test('uses LogsActivity trait', function () {
    expect(class_uses_recursive(new GithubApp))->toContain(\Spatie\Activitylog\Traits\LogsActivity::class);
});

// Activity Log Tests
test('getActivitylogOptions returns LogOptions', function () {
    $githubApp = new GithubApp;
    $options = $githubApp->getActivitylogOptions();

    expect($options)->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
});

// Appended Attributes Tests
test('type attribute is appended', function () {
    $appends = (new GithubApp)->getAppends();
    expect($appends)->toContain('type');
});

// Type Attribute Tests
test('type accessor returns github', function () {
    $githubApp = new GithubApp;
    expect($githubApp->type)->toBe('github');
});

// Attribute Tests
test('name attribute works', function () {
    $githubApp = new GithubApp;
    $githubApp->name = 'Saturn GitHub App';

    expect($githubApp->name)->toBe('Saturn GitHub App');
});

test('organization attribute works', function () {
    $githubApp = new GithubApp;
    $githubApp->organization = 'saturn-org';

    expect($githubApp->organization)->toBe('saturn-org');
});

test('api_url attribute works', function () {
    $githubApp = new GithubApp;
    $githubApp->api_url = 'https://api.github.com';

    expect($githubApp->api_url)->toBe('https://api.github.com');
});

test('html_url attribute works', function () {
    $githubApp = new GithubApp;
    $githubApp->html_url = 'https://github.com';

    expect($githubApp->html_url)->toBe('https://github.com');
});
