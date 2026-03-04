<?php

use App\Models\PersonalAccessToken;

describe('PersonalAccessToken::effectiveRateLimit', function () {
    test('returns explicit limit when rate_limit_per_minute is set', function () {
        $token = new PersonalAccessToken;
        $token->rate_limit_per_minute = 42;
        $token->abilities = ['read'];

        expect($token->effectiveRateLimit())->toBe(42);
    });

    test('returns root default (200) for wildcard abilities', function () {
        $token = new PersonalAccessToken;
        $token->abilities = ['*'];

        expect($token->effectiveRateLimit())->toBe(200);
    });

    test('returns root default (200) for root ability', function () {
        $token = new PersonalAccessToken;
        $token->abilities = ['root', 'read'];

        expect($token->effectiveRateLimit())->toBe(200);
    });

    test('returns write default (30) for write ability', function () {
        $token = new PersonalAccessToken;
        $token->abilities = ['write', 'read'];

        expect($token->effectiveRateLimit())->toBe(30);
    });

    test('returns deploy default (10) for deploy ability', function () {
        $token = new PersonalAccessToken;
        $token->abilities = ['deploy', 'read'];

        expect($token->effectiveRateLimit())->toBe(10);
    });

    test('returns read:sensitive default (60) for read:sensitive ability', function () {
        $token = new PersonalAccessToken;
        $token->abilities = ['read:sensitive'];

        expect($token->effectiveRateLimit())->toBe(60);
    });

    test('returns read default (120) for read-only ability', function () {
        $token = new PersonalAccessToken;
        $token->abilities = ['read'];

        expect($token->effectiveRateLimit())->toBe(120);
    });

    test('returns config fallback for empty abilities', function () {
        config(['api.token_rate_limit' => 77]);

        $token = new PersonalAccessToken;
        $token->abilities = [];

        expect($token->effectiveRateLimit())->toBe(77);
    });

    test('uses priority order: root wins over read', function () {
        $token = new PersonalAccessToken;
        $token->abilities = ['read', 'root'];

        expect($token->effectiveRateLimit())->toBe(200);
    });

    test('uses priority order: write wins over read', function () {
        $token = new PersonalAccessToken;
        $token->abilities = ['read', 'write'];

        expect($token->effectiveRateLimit())->toBe(30);
    });

    test('uses priority order: deploy wins over read', function () {
        $token = new PersonalAccessToken;
        $token->abilities = ['read', 'deploy'];

        expect($token->effectiveRateLimit())->toBe(10);
    });

    test('explicit limit overrides ability-based default', function () {
        $token = new PersonalAccessToken;
        $token->rate_limit_per_minute = 5;
        $token->abilities = ['root'];

        expect($token->effectiveRateLimit())->toBe(5);
    });

    test('null rate_limit_per_minute falls through to ability default', function () {
        $token = new PersonalAccessToken;
        $token->rate_limit_per_minute = null;
        $token->abilities = ['deploy'];

        expect($token->effectiveRateLimit())->toBe(10);
    });

    test('handles null abilities gracefully', function () {
        config(['api.token_rate_limit' => 60]);

        $token = new PersonalAccessToken;
        $token->abilities = null;

        expect($token->effectiveRateLimit())->toBe(60);
    });
});
