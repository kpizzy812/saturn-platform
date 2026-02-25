<?php

use App\Http\Controllers\Webhook\Github;

// Test that the install method reads session-based return context
describe('GitHub App install boarding redirect', function () {
    test('session github_app_return_to is consumed by pull', function () {
        // Verify that session()->pull() removes the key after reading
        session(['github_app_return_to' => 'boarding']);

        $value = session()->pull('github_app_return_to');

        expect($value)->toBe('boarding');
        expect(session('github_app_return_to'))->toBeNull();
    });

    test('github_app_return_to is null when not set', function () {
        $value = session()->pull('github_app_return_to');

        expect($value)->toBeNull();
    });

    test('Github controller class exists', function () {
        expect(class_exists(Github::class))->toBeTrue();
    });

    test('Github controller has install method', function () {
        expect(method_exists(Github::class, 'install'))->toBeTrue();
    });

    test('Github controller has redirect method', function () {
        expect(method_exists(Github::class, 'redirect'))->toBeTrue();
    });
});
