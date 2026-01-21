<?php

use App\Actions\Server\UpdateSaturn;
use App\Models\InstanceSettings;
use App\Models\Server;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Mock Server
    $this->mockServer = Mockery::mock(Server::class)->makePartial();
    $this->mockServer->shouldReceive('getAttribute')->with('id')->andReturn(0);
    $this->mockServer->shouldReceive('setAttribute')->andReturnSelf();

    // Mock InstanceSettings - use shouldIgnoreMissing to allow any attribute access
    $this->settings = Mockery::mock(InstanceSettings::class)->shouldIgnoreMissing();
    $this->settings->shouldReceive('getAttribute')->with('is_auto_update_enabled')->andReturn(true);
    $this->settings->shouldReceive('save')->andReturn(true);
});

afterEach(function () {
    Mockery::close();
});

it('has UpdateSaturn action class', function () {
    expect(class_exists(UpdateSaturn::class))->toBeTrue();
});

it('validates cache against running version before fallback', function () {
    // Mock Server::find to return our mock server
    Server::shouldReceive('find')
        ->with(0)
        ->andReturn($this->mockServer);

    // Mock instanceSettings
    $this->app->instance('App\Models\InstanceSettings', function () {
        return $this->settings;
    });

    // CDN fails
    Http::fake(['*' => Http::response(null, 500)]);

    // Mock cache returning older version
    Cache::shouldReceive('remember')
        ->andReturn(['saturn' => ['v4' => ['version' => '4.0.5']]]);

    config(['constants.saturn.version' => '4.0.10']);

    $action = new UpdateSaturn;

    // Should throw exception - cache is older than running
    try {
        $action->handle(manual_update: false);
        expect(false)->toBeTrue('Expected exception was not thrown');
    } catch (\Exception $e) {
        expect($e->getMessage())->toContain('cache version');
        expect($e->getMessage())->toContain('4.0.5');
        expect($e->getMessage())->toContain('4.0.10');
    }
})->skip('Cannot mock Server::find() static method - needs refactoring to use database factory');

it('uses validated cache when CDN fails and cache is newer', function () {
    // Mock Server::find
    Server::shouldReceive('find')
        ->with(0)
        ->andReturn($this->mockServer);

    // Mock instanceSettings
    $this->app->instance('App\Models\InstanceSettings', function () {
        return $this->settings;
    });

    // CDN fails
    Http::fake(['*' => Http::response(null, 500)]);

    // Cache has newer version than current
    Cache::shouldReceive('remember')
        ->andReturn(['saturn' => ['v4' => ['version' => '4.0.10']]]);

    config(['constants.saturn.version' => '4.0.5']);

    // Mock the update method to prevent actual update
    $action = Mockery::mock(UpdateSaturn::class)->makePartial();
    $action->shouldReceive('update')->once();
    $action->server = $this->mockServer;

    \Illuminate\Support\Facades\Log::shouldReceive('warning')
        ->once()
        ->with('Failed to fetch fresh version from CDN, using validated cache', Mockery::type('array'));

    // Should not throw - cache (4.0.10) > running (4.0.5)
    $action->handle(manual_update: false);

    expect($action->latestVersion)->toBe('4.0.10');
})->skip('Cannot mock Server::find() static method - needs refactoring to use database factory');

it('prevents downgrade even with manual update', function () {
    // Mock Server::find
    Server::shouldReceive('find')
        ->with(0)
        ->andReturn($this->mockServer);

    // Mock instanceSettings
    $this->app->instance('App\Models\InstanceSettings', function () {
        return $this->settings;
    });

    // CDN returns older version
    Http::fake([
        '*' => Http::response([
            'saturn' => ['v4' => ['version' => '4.0.0']],
        ], 200),
    ]);

    // Current version is newer
    config(['constants.saturn.version' => '4.0.10']);

    $action = new UpdateSaturn;

    \Illuminate\Support\Facades\Log::shouldReceive('error')
        ->once()
        ->with('Downgrade prevented', Mockery::type('array'));

    // Should throw exception even for manual updates
    try {
        $action->handle(manual_update: true);
        expect(false)->toBeTrue('Expected exception was not thrown');
    } catch (\Exception $e) {
        expect($e->getMessage())->toContain('Cannot downgrade');
        expect($e->getMessage())->toContain('4.0.10');
        expect($e->getMessage())->toContain('4.0.0');
    }
})->skip('Cannot mock Server::find() static method - needs refactoring to use database factory');
