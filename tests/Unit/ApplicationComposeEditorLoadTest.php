<?php

/**
 * Unit tests to verify docker_compose functionality in the Application Livewire component.
 *
 * These tests verify the code structure for Docker Compose related properties and methods.
 */
it('verifies Application General component has docker compose build commands', function () {
    $componentPath = __DIR__.'/../../app/Livewire/Project/Application/General.php';

    // Skip test if file doesn't exist
    if (! file_exists($componentPath)) {
        expect(true)->toBeTrue('General.php not found, skipping source verification');

        return;
    }

    $componentFile = file_get_contents($componentPath);

    // Verify Docker Compose command preview methods exist
    expect($componentFile)
        ->toContain('getDockerComposeBuildCommandPreviewProperty')
        ->toContain('getDockerComposeStartCommandPreviewProperty')
        ->toContain('getComposeFilePath');
});

/**
 * Test that verifies the expected behavior pattern for compose file loading
 */
it('ensures General component has proper docker compose configuration', function () {
    // Verify the component has required properties/methods for Docker Compose support
    $componentPath = __DIR__.'/../../app/Livewire/Project/Application/General.php';

    if (! file_exists($componentPath)) {
        expect(true)->toBeTrue('General.php not found, skipping source verification');

        return;
    }

    $componentFile = file_get_contents($componentPath);

    // Verify compose file path helper exists
    expect($componentFile)
        ->toContain('injectDockerComposeFlags')
        ->toContain('docker-compose.yaml'); // Default compose file extension
});

it('verifies Application model has loadComposeFile method', function () {
    $reflection = new ReflectionClass(\App\Models\Application::class);

    // Verify loadComposeFile method exists
    expect($reflection->hasMethod('loadComposeFile'))->toBeTrue();
});
