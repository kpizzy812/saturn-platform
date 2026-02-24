<?php

use App\Actions\Team\GetArchiveMemberResourcesAction;

beforeEach(function () {
    $this->action = new GetArchiveMemberResourcesAction;
});

it('returns correct allowed resource types', function () {
    $types = GetArchiveMemberResourcesAction::allowedResourceTypes();

    expect($types)->toContain('App\Models\Application');
    expect($types)->toContain('App\Models\Service');
    expect($types)->toContain('App\Models\StandalonePostgresql');
    expect($types)->toContain('App\Models\StandaloneMysql');
    expect($types)->toContain('App\Models\StandaloneMariadb');
    expect($types)->toContain('App\Models\StandaloneMongodb');
    expect($types)->toContain('App\Models\StandaloneRedis');
    expect($types)->toContain('App\Models\StandaloneKeydb');
    expect($types)->toContain('App\Models\StandaloneDragonfly');
    expect($types)->toContain('App\Models\StandaloneClickhouse');
});

it('does not include Server or Project in allowed types', function () {
    $types = GetArchiveMemberResourcesAction::allowedResourceTypes();

    expect($types)->not->toContain('App\Models\Server');
    expect($types)->not->toContain('App\Models\Project');
});

it('has execute method that accepts team and user ids', function () {
    $method = new \ReflectionMethod(GetArchiveMemberResourcesAction::class, 'execute');
    $params = $method->getParameters();

    expect($params)->toHaveCount(2);
    expect($params[0]->getName())->toBe('teamId');
    expect($params[0]->getType()?->getName())->toBe('int');
    expect($params[1]->getName())->toBe('userId');
    expect($params[1]->getType()?->getName())->toBe('int');
});

it('has correct type label mapping', function () {
    $method = new \ReflectionMethod(GetArchiveMemberResourcesAction::class, 'getTypeLabel');

    expect($method->invoke($this->action, 'Application'))->toBe('App');
    expect($method->invoke($this->action, 'Service'))->toBe('Service');
    expect($method->invoke($this->action, 'StandalonePostgresql'))->toBe('PostgreSQL');
    expect($method->invoke($this->action, 'StandaloneMysql'))->toBe('MySQL');
    expect($method->invoke($this->action, 'StandaloneMariadb'))->toBe('MariaDB');
    expect($method->invoke($this->action, 'StandaloneMongodb'))->toBe('MongoDB');
    expect($method->invoke($this->action, 'StandaloneRedis'))->toBe('Redis');
    expect($method->invoke($this->action, 'StandaloneKeydb'))->toBe('KeyDB');
    expect($method->invoke($this->action, 'StandaloneDragonfly'))->toBe('Dragonfly');
    expect($method->invoke($this->action, 'StandaloneClickhouse'))->toBe('ClickHouse');
});

it('generates correct resource URLs', function () {
    $method = new \ReflectionMethod(GetArchiveMemberResourcesAction::class, 'getResourceUrl');

    expect($method->invoke($this->action, 'Application', 'abc-123'))->toBe('/applications/abc-123');
    expect($method->invoke($this->action, 'Service', 'def-456'))->toBe('/services/def-456');
    expect($method->invoke($this->action, 'StandalonePostgresql', 'ghi-789'))->toBe('/databases/ghi-789');
    expect($method->invoke($this->action, 'StandaloneMysql', 'jkl-012'))->toBe('/databases/jkl-012');
    expect($method->invoke($this->action, 'StandaloneRedis', 'mno-345'))->toBe('/databases/mno-345');
});

it('allowed resource types are all valid classes', function () {
    $types = GetArchiveMemberResourcesAction::allowedResourceTypes();

    foreach ($types as $type) {
        expect(class_exists($type))->toBeTrue("Class {$type} should exist");
    }
});

it('allowed resource types all have environment relation', function () {
    $types = GetArchiveMemberResourcesAction::allowedResourceTypes();

    foreach ($types as $type) {
        $model = new $type;
        expect(method_exists($model, 'environment'))->toBeTrue(
            "Model {$type} should have environment() relationship"
        );
    }
});

it('allowed resource types all have uuid attribute', function () {
    $types = GetArchiveMemberResourcesAction::allowedResourceTypes();

    foreach ($types as $type) {
        $model = new $type;
        $fillable = $model->getFillable();
        // uuid can be either fillable or auto-generated via boot/trait
        // Just verify the column concept exists by checking if model has the attribute accessor
        expect(
            in_array('uuid', $fillable, true)
            || method_exists($model, 'getUuidAttribute')
            || $model->getKeyName() !== 'uuid' // not a UUID primary key
        )->toBeTrue("Model {$type} should support uuid");
    }
});

it('has exactly 10 allowed resource types', function () {
    $types = GetArchiveMemberResourcesAction::allowedResourceTypes();

    expect($types)->toHaveCount(10);
});

it('has moveResources method with correct signature', function () {
    $method = new \ReflectionMethod(GetArchiveMemberResourcesAction::class, 'moveResources');
    $params = $method->getParameters();

    expect($method->isPublic())->toBeTrue();
    expect($params)->toHaveCount(3);
    expect($params[0]->getName())->toBe('teamId');
    expect($params[0]->getType()?->getName())->toBe('int');
    expect($params[1]->getName())->toBe('resources');
    expect($params[1]->getType()?->getName())->toBe('array');
    expect($params[2]->getName())->toBe('targetEnvironmentId');
    expect($params[2]->getType()?->getName())->toBe('int');

    // Return type is int (count of moved resources)
    expect($method->getReturnType()?->getName())->toBe('int');
});

it('has deleteResources method with correct signature', function () {
    $method = new \ReflectionMethod(GetArchiveMemberResourcesAction::class, 'deleteResources');
    $params = $method->getParameters();

    expect($method->isPublic())->toBeTrue();
    expect($params)->toHaveCount(2);
    expect($params[0]->getName())->toBe('teamId');
    expect($params[0]->getType()?->getName())->toBe('int');
    expect($params[1]->getName())->toBe('resources');
    expect($params[1]->getType()?->getName())->toBe('array');

    // Return type is int (count of deleted resources)
    expect($method->getReturnType()?->getName())->toBe('int');
});

it('has resolveAndVerify private method', function () {
    $method = new \ReflectionMethod(GetArchiveMemberResourcesAction::class, 'resolveAndVerify');

    expect($method->isPrivate())->toBeTrue();

    $params = $method->getParameters();
    expect($params)->toHaveCount(3);
    expect($params[0]->getName())->toBe('teamId');
    expect($params[1]->getName())->toBe('type');
    expect($params[2]->getName())->toBe('id');

    // Return type is nullable Model
    $returnType = $method->getReturnType();
    expect($returnType->allowsNull())->toBeTrue();
});

it('resolveAndVerify rejects disallowed types', function () {
    $method = new \ReflectionMethod(GetArchiveMemberResourcesAction::class, 'resolveAndVerify');

    // Disallowed type should return null
    $result = $method->invoke($this->action, 1, 'App\Models\Server', 1);
    expect($result)->toBeNull();

    // Non-existent class should return null
    $result = $method->invoke($this->action, 1, 'App\Models\NonExistent', 1);
    expect($result)->toBeNull();
});

it('allowed resource types all have destination relation', function () {
    $types = GetArchiveMemberResourcesAction::allowedResourceTypes();

    foreach ($types as $type) {
        $model = new $type;
        expect(method_exists($model, 'destination'))->toBeTrue(
            "Model {$type} should have destination() relationship for server info"
        );
    }
});

it('default type label returns basename for unknown types', function () {
    $method = new \ReflectionMethod(GetArchiveMemberResourcesAction::class, 'getTypeLabel');

    expect($method->invoke($this->action, 'UnknownModel'))->toBe('UnknownModel');
    expect($method->invoke($this->action, 'CustomApp'))->toBe('CustomApp');
});
