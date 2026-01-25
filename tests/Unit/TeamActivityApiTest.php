<?php

use App\Http\Controllers\Api\TeamController;

/**
 * Unit tests for TeamController::current_team_activities endpoint
 *
 * This tests the structure and implementation of the team activities API
 * without requiring database connections.
 */
it('has current_team_activities method in TeamController', function () {
    $reflection = new ReflectionClass(TeamController::class);

    expect($reflection->hasMethod('current_team_activities'))->toBeTrue(
        'TeamController should have current_team_activities method'
    );
});

it('current_team_activities method has correct OpenAPI documentation', function () {
    $reflection = new ReflectionClass(TeamController::class);
    $method = $reflection->getMethod('current_team_activities');

    $attributes = $method->getAttributes();

    $hasGetAttribute = false;
    foreach ($attributes as $attribute) {
        if (str_contains($attribute->getName(), 'Get')) {
            $hasGetAttribute = true;
            break;
        }
    }

    expect($hasGetAttribute)->toBeTrue(
        'current_team_activities should have OpenAPI Get attribute'
    );
});

it('has helper methods for action mapping', function () {
    $reflection = new ReflectionClass(TeamController::class);

    expect($reflection->hasMethod('mapCreatedAction'))->toBeTrue(
        'TeamController should have mapCreatedAction helper method'
    );
    expect($reflection->hasMethod('mapUpdatedAction'))->toBeTrue(
        'TeamController should have mapUpdatedAction helper method'
    );
    expect($reflection->hasMethod('mapDeletedAction'))->toBeTrue(
        'TeamController should have mapDeletedAction helper method'
    );
});

it('helper methods return string for null subject', function () {
    $controller = new TeamController;
    $reflection = new ReflectionClass($controller);

    $mapCreatedAction = $reflection->getMethod('mapCreatedAction');
    $mapUpdatedAction = $reflection->getMethod('mapUpdatedAction');
    $mapDeletedAction = $reflection->getMethod('mapDeletedAction');

    // Test with null subject - should return 'settings_updated'
    expect($mapCreatedAction->invoke($controller, null))->toBe('settings_updated');
    expect($mapUpdatedAction->invoke($controller, null))->toBe('settings_updated');
    expect($mapDeletedAction->invoke($controller, null))->toBe('settings_updated');
});

it('current_team_activities supports required query parameters', function () {
    $reflection = new ReflectionClass(TeamController::class);
    $source = file_get_contents($reflection->getFileName());

    // Check that filter parameters are documented
    expect($source)
        ->toContain("name: 'action'")
        ->toContain("name: 'member'")
        ->toContain("name: 'date_range'")
        ->toContain("name: 'search'")
        ->toContain("name: 'per_page'");
});

it('current_team_activities returns paginated response', function () {
    $reflection = new ReflectionClass(TeamController::class);
    $source = file_get_contents($reflection->getFileName());

    // Check that response includes pagination meta
    expect($source)
        ->toContain('current_page')
        ->toContain('last_page')
        ->toContain('per_page')
        ->toContain('total');
});
