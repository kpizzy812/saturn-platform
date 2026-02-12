<?php

use App\Models\Environment;

// getEnvironmentType Tests
test('getEnvironmentType returns type when set', function () {
    $environment = new Environment;
    $environment->type = 'production';

    expect($environment->getEnvironmentType())->toBe('production');
});

test('getEnvironmentType returns development when type is null', function () {
    $environment = new Environment;
    $environment->type = null;

    expect($environment->getEnvironmentType())->toBe('development');
});

test('getEnvironmentType returns uat when type is uat', function () {
    $environment = new Environment;
    $environment->type = 'uat';

    expect($environment->getEnvironmentType())->toBe('uat');
});

// isProduction Tests
test('isProduction returns true when type is production', function () {
    $environment = new Environment;
    $environment->type = 'production';

    expect($environment->isProduction())->toBeTrue();
});

test('isProduction returns false when type is development', function () {
    $environment = new Environment;
    $environment->type = 'development';

    expect($environment->isProduction())->toBeFalse();
});

test('isProduction returns false when type is null', function () {
    $environment = new Environment;
    $environment->type = null;

    expect($environment->isProduction())->toBeFalse();
});

// isUat Tests
test('isUat returns true when type is uat', function () {
    $environment = new Environment;
    $environment->type = 'uat';

    expect($environment->isUat())->toBeTrue();
});

test('isUat returns false when type is production', function () {
    $environment = new Environment;
    $environment->type = 'production';

    expect($environment->isUat())->toBeFalse();
});

test('isUat returns false when type is development', function () {
    $environment = new Environment;
    $environment->type = 'development';

    expect($environment->isUat())->toBeFalse();
});

// isDevelopment Tests
test('isDevelopment returns true when type is development', function () {
    $environment = new Environment;
    $environment->type = 'development';

    expect($environment->isDevelopment())->toBeTrue();
});

test('isDevelopment returns true when type is null', function () {
    $environment = new Environment;
    $environment->type = null;

    expect($environment->isDevelopment())->toBeTrue();
});

test('isDevelopment returns false when type is production', function () {
    $environment = new Environment;
    $environment->type = 'production';

    expect($environment->isDevelopment())->toBeFalse();
});

test('isDevelopment returns false when type is uat', function () {
    $environment = new Environment;
    $environment->type = 'uat';

    expect($environment->isDevelopment())->toBeFalse();
});

// isProtected Tests
test('isProtected returns true when environment is production', function () {
    $environment = new Environment;
    $environment->type = 'production';

    expect($environment->isProtected())->toBeTrue();
});

test('isProtected returns false when environment is development', function () {
    $environment = new Environment;
    $environment->type = 'development';

    expect($environment->isProtected())->toBeFalse();
});

test('isProtected returns false when environment is uat', function () {
    $environment = new Environment;
    $environment->type = 'uat';

    expect($environment->isProtected())->toBeFalse();
});

// requiresDeploymentApproval Tests
test('requiresDeploymentApproval returns true when requires_approval is true', function () {
    $environment = new Environment;
    $environment->requires_approval = true;

    expect($environment->requiresDeploymentApproval())->toBeTrue();
});

test('requiresDeploymentApproval returns false when requires_approval is false', function () {
    $environment = new Environment;
    $environment->requires_approval = false;

    expect($environment->requiresDeploymentApproval())->toBeFalse();
});

test('requiresDeploymentApproval returns false when requires_approval is null', function () {
    $environment = new Environment;
    $environment->requires_approval = null;

    expect($environment->requiresDeploymentApproval())->toBeFalse();
});

// project Relationship Accessor Tests
test('project relationship returns belongsTo relation', function () {
    $environment = new Environment;

    $relation = $environment->project();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    expect($relation->getForeignKeyName())->toBe('project_id');
});

// defaultServer Relationship Accessor Tests
test('defaultServer relationship returns belongsTo relation', function () {
    $environment = new Environment;

    $relation = $environment->defaultServer();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    expect($relation->getForeignKeyName())->toBe('default_server_id');
});

// applications Relationship Accessor Tests
test('applications relationship returns hasMany relation', function () {
    $environment = new Environment;

    $relation = $environment->applications();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    expect($relation->getForeignKeyName())->toBe('environment_id');
});

// services Relationship Accessor Tests
test('services relationship returns hasMany relation', function () {
    $environment = new Environment;

    $relation = $environment->services();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    expect($relation->getForeignKeyName())->toBe('environment_id');
});

// postgresqls Relationship Accessor Tests
test('postgresqls relationship returns hasMany relation', function () {
    $environment = new Environment;

    $relation = $environment->postgresqls();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    expect($relation->getForeignKeyName())->toBe('environment_id');
});

// redis Relationship Accessor Tests
test('redis relationship returns hasMany relation', function () {
    $environment = new Environment;

    $relation = $environment->redis();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    expect($relation->getForeignKeyName())->toBe('environment_id');
});

// mongodbs Relationship Accessor Tests
test('mongodbs relationship returns hasMany relation', function () {
    $environment = new Environment;

    $relation = $environment->mongodbs();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    expect($relation->getForeignKeyName())->toBe('environment_id');
});

// mysqls Relationship Accessor Tests
test('mysqls relationship returns hasMany relation', function () {
    $environment = new Environment;

    $relation = $environment->mysqls();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    expect($relation->getForeignKeyName())->toBe('environment_id');
});

// mariadbs Relationship Accessor Tests
test('mariadbs relationship returns hasMany relation', function () {
    $environment = new Environment;

    $relation = $environment->mariadbs();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    expect($relation->getForeignKeyName())->toBe('environment_id');
});

// keydbs Relationship Accessor Tests
test('keydbs relationship returns hasMany relation', function () {
    $environment = new Environment;

    $relation = $environment->keydbs();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    expect($relation->getForeignKeyName())->toBe('environment_id');
});

// dragonflies Relationship Accessor Tests
test('dragonflies relationship returns hasMany relation', function () {
    $environment = new Environment;

    $relation = $environment->dragonflies();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    expect($relation->getForeignKeyName())->toBe('environment_id');
});

// clickhouses Relationship Accessor Tests
test('clickhouses relationship returns hasMany relation', function () {
    $environment = new Environment;

    $relation = $environment->clickhouses();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    expect($relation->getForeignKeyName())->toBe('environment_id');
});

// environment_variables Relationship Accessor Tests
test('environment_variables relationship returns hasMany relation', function () {
    $environment = new Environment;

    $relation = $environment->environment_variables();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    expect($relation->getForeignKeyName())->toBe('environment_id');
});
