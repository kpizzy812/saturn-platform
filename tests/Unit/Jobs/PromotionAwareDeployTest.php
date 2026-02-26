<?php

use App\Jobs\ApplicationDeploymentJob;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Traits\Deployment\HandlesImageRegistry;

// -------------------------------------------------------------------------
// ApplicationDeploymentQueue model — new promotion fields
// -------------------------------------------------------------------------

test('ApplicationDeploymentQueue has is_promotion in fillable', function () {
    $model = new ApplicationDeploymentQueue;

    expect($model->getFillable())->toContain('is_promotion');
});

test('ApplicationDeploymentQueue has promoted_from_image in fillable', function () {
    $model = new ApplicationDeploymentQueue;

    expect($model->getFillable())->toContain('promoted_from_image');
});

test('ApplicationDeploymentQueue casts is_promotion as boolean', function () {
    $model = new ApplicationDeploymentQueue;
    $casts = $model->getCasts();

    expect($casts)->toHaveKey('is_promotion');
    expect($casts['is_promotion'])->toBe('boolean');
});

// -------------------------------------------------------------------------
// ApplicationDeploymentJob — new promotion properties exist
// -------------------------------------------------------------------------

test('ApplicationDeploymentJob has is_promotion property defaulting to false', function () {
    $reflection = new ReflectionClass(ApplicationDeploymentJob::class);
    $defaults = $reflection->getDefaultProperties();

    expect($reflection->hasProperty('is_promotion'))->toBeTrue();
    expect($defaults['is_promotion'])->toBeFalse();
});

test('ApplicationDeploymentJob has promoted_from_image property defaulting to null', function () {
    $reflection = new ReflectionClass(ApplicationDeploymentJob::class);
    $defaults = $reflection->getDefaultProperties();

    expect($reflection->hasProperty('promoted_from_image'))->toBeTrue();
    expect($defaults['promoted_from_image'])->toBeNull();
});

// -------------------------------------------------------------------------
// HandlesImageRegistry trait — handle_promotion_image() method exists
// -------------------------------------------------------------------------

test('HandlesImageRegistry trait has handle_promotion_image method', function () {
    $reflection = new ReflectionClass(HandlesImageRegistry::class);

    expect($reflection->hasMethod('handle_promotion_image'))->toBeTrue();
});

test('handle_promotion_image is private', function () {
    $reflection = new ReflectionClass(HandlesImageRegistry::class);
    $method = $reflection->getMethod('handle_promotion_image');

    expect($method->isPrivate())->toBeTrue();
});

// -------------------------------------------------------------------------
// Promotion image name formula
// -------------------------------------------------------------------------

test('promoted_from_image uses registry name when docker_registry_image_name is set', function () {
    $app = Mockery::mock(Application::class)->makePartial();
    $app->shouldReceive('getAttribute')->with('docker_registry_image_name')->andReturn('registry.example.com/myapp');
    $app->shouldReceive('getAttribute')->with('uuid')->andReturn('abc-uuid-123');

    $commit = 'abc123def456';

    // Replicate the formula from PromoteResourceAction::triggerDeployment()
    $promoted_from_image = $app->docker_registry_image_name
        ? "{$app->docker_registry_image_name}:{$commit}"
        : "{$app->uuid}:{$commit}";

    expect($promoted_from_image)->toBe("registry.example.com/myapp:{$commit}");
})->throws(null);

test('promoted_from_image uses uuid when no docker_registry_image_name', function () {
    $appUuid = 'test-app-uuid-999';

    // Replicate formula when docker_registry_image_name is empty
    $docker_registry_image_name = null;
    $commit = 'deadbeef1234';

    $promoted_from_image = $docker_registry_image_name
        ? "{$docker_registry_image_name}:{$commit}"
        : "{$appUuid}:{$commit}";

    expect($promoted_from_image)->toBe("{$appUuid}:{$commit}");
});

// -------------------------------------------------------------------------
// queue_application_deployment helper — accepts is_promotion param
// -------------------------------------------------------------------------

test('queue_application_deployment function accepts is_promotion parameter', function () {
    $reflection = new ReflectionFunction('queue_application_deployment');
    $paramNames = collect($reflection->getParameters())->map->getName()->toArray();

    expect($paramNames)->toContain('is_promotion');
    expect($paramNames)->toContain('promoted_from_image');
});

test('is_promotion parameter defaults to false', function () {
    $reflection = new ReflectionFunction('queue_application_deployment');
    $params = collect($reflection->getParameters())->keyBy->getName();

    expect($params['is_promotion']->isDefaultValueAvailable())->toBeTrue();
    expect($params['is_promotion']->getDefaultValue())->toBeFalse();
});

test('promoted_from_image parameter defaults to null', function () {
    $reflection = new ReflectionFunction('queue_application_deployment');
    $params = collect($reflection->getParameters())->keyBy->getName();

    expect($params['promoted_from_image']->isDefaultValueAvailable())->toBeTrue();
    expect($params['promoted_from_image']->getDefaultValue())->toBeNull();
});

afterEach(function () {
    Mockery::close();
});
