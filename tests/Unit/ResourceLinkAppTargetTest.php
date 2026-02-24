<?php

use App\Models\Application;
use App\Models\ResourceLink;
use App\Models\StandaloneClickhouse;
use App\Models\StandaloneMongodb;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;

describe('ResourceLink::getDefaultEnvKey', function () {
    it('returns DATABASE_URL for postgresql', function () {
        expect(ResourceLink::getDefaultEnvKey(StandalonePostgresql::class))
            ->toBe('DATABASE_URL');
    });

    it('returns REDIS_URL for redis', function () {
        expect(ResourceLink::getDefaultEnvKey(StandaloneRedis::class))
            ->toBe('REDIS_URL');
    });

    it('returns MONGODB_URL for mongodb', function () {
        expect(ResourceLink::getDefaultEnvKey(StandaloneMongodb::class))
            ->toBe('MONGODB_URL');
    });

    it('returns CLICKHOUSE_URL for clickhouse', function () {
        expect(ResourceLink::getDefaultEnvKey(StandaloneClickhouse::class))
            ->toBe('CLICKHOUSE_URL');
    });

    it('returns APP_URL for application', function () {
        expect(ResourceLink::getDefaultEnvKey(Application::class))
            ->toBe('APP_URL');
    });

    it('returns CONNECTION_URL for unknown type', function () {
        expect(ResourceLink::getDefaultEnvKey('UnknownClass'))
            ->toBe('CONNECTION_URL');
    });
});

describe('ResourceLink::getEnvKey', function () {
    it('returns inject_as when set', function () {
        $link = new ResourceLink;
        $link->inject_as = 'CUSTOM_DB_URL';
        $link->target_type = StandalonePostgresql::class;

        expect($link->getEnvKey())->toBe('CUSTOM_DB_URL');
    });

    it('returns default key when inject_as is null', function () {
        $link = new ResourceLink;
        $link->inject_as = null;
        $link->target_type = StandaloneRedis::class;

        expect($link->getEnvKey())->toBe('REDIS_URL');
    });
});

describe('ResourceLink::getSmartAppEnvKey', function () {
    it('returns inject_as when set', function () {
        $link = new ResourceLink;
        $link->inject_as = 'MY_CUSTOM_KEY';
        $link->target_type = Application::class;

        expect($link->getSmartAppEnvKey())->toBe('MY_CUSTOM_KEY');
    });

    it('generates smart key from target app name', function () {
        $app = Mockery::mock(Application::class);
        $app->shouldReceive('getAttribute')->with('name')->andReturn('my-backend');

        $link = new ResourceLink;
        $link->inject_as = null;
        $link->target_type = Application::class;

        // Use reflection to set the target relation
        $reflection = new ReflectionClass($link);
        $property = $reflection->getProperty('relations');
        $property->setValue($link, ['target' => $app]);

        expect($link->getSmartAppEnvKey())->toBe('MY_BACKEND_URL');
    });

    it('converts spaces and dots to underscores', function () {
        $app = Mockery::mock(Application::class);
        $app->shouldReceive('getAttribute')->with('name')->andReturn('my app.service');

        $link = new ResourceLink;
        $link->inject_as = null;
        $link->target_type = Application::class;

        $reflection = new ReflectionClass($link);
        $property = $reflection->getProperty('relations');
        $property->setValue($link, ['target' => $app]);

        expect($link->getSmartAppEnvKey())->toBe('MY_APP_SERVICE_URL');
    });

    it('falls back to APP_URL when target is null', function () {
        $link = new ResourceLink;
        $link->inject_as = null;
        $link->target_type = Application::class;

        expect($link->getSmartAppEnvKey())->toBe('APP_URL');
    });

    it('falls back to default key for non-application target', function () {
        $link = new ResourceLink;
        $link->inject_as = null;
        $link->target_type = StandalonePostgresql::class;

        expect($link->getSmartAppEnvKey())->toBe('DATABASE_URL');
    });
});

describe('ResourceLink casts', function () {
    it('casts auto_inject to boolean', function () {
        $link = new ResourceLink;
        $link->auto_inject = 1;
        expect($link->auto_inject)->toBeTrue();

        $link->auto_inject = 0;
        expect($link->auto_inject)->toBeFalse();
    });

    it('casts use_external_url to boolean', function () {
        $link = new ResourceLink;
        $link->use_external_url = 1;
        expect($link->use_external_url)->toBeTrue();

        $link->use_external_url = 0;
        expect($link->use_external_url)->toBeFalse();
    });
});
