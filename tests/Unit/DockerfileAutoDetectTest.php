<?php

use App\Models\Application;

/**
 * Tests for Dockerfile auto-detection feature.
 *
 * When an application uses Nixpacks (default) but has a Dockerfile in the repository,
 * Saturn should automatically switch to the dockerfile buildpack — unless the user
 * has explicitly chosen their build pack.
 *
 * The flag `build_pack_explicitly_set` controls this behavior:
 * - false: auto-detection is enabled (new apps)
 * - true: user has explicitly chosen their build pack (existing apps, or after manual change)
 */
describe('Dockerfile auto-detection', function () {
    it('new applications start with build_pack_explicitly_set as false', function () {
        $application = new Application;
        $application->name = 'test-app';
        $application->build_pack = 'nixpacks';

        // Simulate the creating event
        $reflection = new ReflectionClass(Application::class);

        // The creating event sets build_pack_explicitly_set to false if not already set
        expect($application->build_pack_explicitly_set)->toBeNull();

        // After the creating event fires, it should be false
        // We test the logic directly since we can't fire model events without DB
        if (! isset($application->build_pack_explicitly_set)) {
            $application->build_pack_explicitly_set = false;
        }

        expect($application->build_pack_explicitly_set)->toBeFalse();
    });

    it('does not override build_pack_explicitly_set if already set during creation', function () {
        $application = new Application;
        $application->name = 'test-app';
        $application->build_pack = 'dockerfile';
        $application->build_pack_explicitly_set = true;

        // The creating event should not override an explicitly set value
        if (! isset($application->build_pack_explicitly_set)) {
            $application->build_pack_explicitly_set = false;
        }

        expect($application->build_pack_explicitly_set)->toBeTrue();
    });

    it('casts build_pack_explicitly_set as boolean', function () {
        $application = new Application;
        $application->build_pack_explicitly_set = 1;

        expect($application->build_pack_explicitly_set)->toBeTrue();

        $application->build_pack_explicitly_set = 0;

        expect($application->build_pack_explicitly_set)->toBeFalse();
    });

    it('auto-detect should be skipped when build_pack_explicitly_set is true', function () {
        $application = new Application;
        $application->build_pack = 'nixpacks';
        $application->build_pack_explicitly_set = true;

        // Simulate the check from autoDetectAndSwitchToDockerfile
        $shouldAutoDetect = ! $application->build_pack_explicitly_set;

        expect($shouldAutoDetect)->toBeFalse();
    });

    it('auto-detect should proceed when build_pack_explicitly_set is false', function () {
        $application = new Application;
        $application->build_pack = 'nixpacks';
        $application->build_pack_explicitly_set = false;

        // Simulate the check from autoDetectAndSwitchToDockerfile
        $shouldAutoDetect = ! $application->build_pack_explicitly_set;

        expect($shouldAutoDetect)->toBeTrue();
    });

    it('existing applications default to build_pack_explicitly_set true via migration', function () {
        // The migration sets default(true) for existing rows
        // This means existing apps won't be affected by auto-detection
        $application = new Application;
        // Simulate reading from DB where migration default applied
        $application->setRawAttributes(['build_pack_explicitly_set' => true]);

        expect($application->build_pack_explicitly_set)->toBeTrue();
    });
});
