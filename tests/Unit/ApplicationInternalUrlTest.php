<?php

use App\Models\Application;

describe('Application::internalAppUrl', function () {
    it('generates internal URL with first exposed port', function () {
        $app = new Application;
        $app->uuid = 'abc123';
        $app->ports_exposes = '3000';

        expect($app->internal_app_url)->toBe('http://abc123:3000');
    });

    it('uses first port when multiple ports exposed', function () {
        $app = new Application;
        $app->uuid = 'def456';
        $app->ports_exposes = '8080,3000,5000';

        expect($app->internal_app_url)->toBe('http://def456:8080');
    });

    it('defaults to port 80 when ports_exposes is empty string', function () {
        $app = new Application;
        $app->uuid = 'ghi789';
        $app->ports_exposes = '';

        expect($app->internal_app_url)->toBe('http://ghi789:80');
    });

    it('defaults to port 80 when ports_exposes is null', function () {
        $app = new Application;
        $app->uuid = 'jkl012';

        expect($app->internal_app_url)->toBe('http://jkl012:80');
    });
});
