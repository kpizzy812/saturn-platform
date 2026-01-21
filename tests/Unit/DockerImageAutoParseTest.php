<?php

/**
 * Tests for DockerImage Livewire component auto-parsing functionality.
 */

use App\Livewire\Project\New\DockerImage;

it('auto-parses complete docker image reference with tag', function () {
    $component = new DockerImage;
    $component->imageName = 'nginx:stable-alpine3.21-perl';
    $component->updatedImageName();

    expect($component->imageName)->toBe('nginx');
    expect($component->imageTag)->toBe('stable-alpine3.21-perl');
    expect($component->imageSha256)->toBe('');
});

it('auto-parses complete docker image reference with sha256 digest', function () {
    $component = new DockerImage;
    $component->imageName = 'nginx@sha256:abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890';
    $component->updatedImageName();

    expect($component->imageName)->toBe('nginx');
    expect($component->imageTag)->toBe('');
    expect($component->imageSha256)->toBe('abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890');
});

it('auto-parses complete docker image reference with tag and sha256 digest', function () {
    $component = new DockerImage;
    $component->imageName = 'nginx:latest@sha256:abcdef1234567890';
    $component->updatedImageName();

    // When both tag and sha256 are present, sha256 is extracted and image keeps the tag
    expect($component->imageName)->toBe('nginx:latest');
    expect($component->imageSha256)->toBe('abcdef1234567890');
});

it('auto-parses registry image with port and tag', function () {
    $component = new DockerImage;
    $component->imageName = 'registry.io:5000/myapp:v1.2.3';
    $component->updatedImageName();

    expect($component->imageName)->toBe('registry.io:5000/myapp');
    expect($component->imageTag)->toBe('v1.2.3');
    expect($component->imageSha256)->toBe('');
});

it('auto-parses ghcr image with sha256 digest', function () {
    $component = new DockerImage;
    $component->imageName = 'ghcr.io/user/app@sha256:abcdef123456';
    $component->updatedImageName();

    expect($component->imageName)->toBe('ghcr.io/user/app');
    expect($component->imageTag)->toBe('');
    expect($component->imageSha256)->toBe('abcdef123456');
});

it('does not auto-parse if user has manually filled tag field', function () {
    $component = new DockerImage;
    $component->imageTag = 'manual-tag';
    $component->imageName = 'nginx:latest';
    $component->updatedImageName();

    // Should not auto-parse when tag is already set
    expect($component->imageName)->toBe('nginx:latest');
    expect($component->imageTag)->toBe('manual-tag');
});

it('does not auto-parse if user has manually filled sha256 field', function () {
    $component = new DockerImage;
    $component->imageSha256 = 'manual-sha256';
    $component->imageName = 'nginx@sha256:other';
    $component->updatedImageName();

    // Should not auto-parse when sha256 is already set
    expect($component->imageName)->toBe('nginx@sha256:other');
    expect($component->imageSha256)->toBe('manual-sha256');
});

it('does not auto-parse plain image name without tag or digest', function () {
    $component = new DockerImage;
    $component->imageName = 'nginx';
    $component->updatedImageName();

    expect($component->imageName)->toBe('nginx');
    expect($component->imageTag)->toBe('');
    expect($component->imageSha256)->toBe('');
});

it('handles image with organization and tag', function () {
    $component = new DockerImage;
    $component->imageName = 'organization/myapp:v2.0.0';
    $component->updatedImageName();

    expect($component->imageName)->toBe('organization/myapp');
    expect($component->imageTag)->toBe('v2.0.0');
});
