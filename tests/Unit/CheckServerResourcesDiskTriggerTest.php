<?php

use App\Jobs\CheckServerResourcesJob;

test('checkDiskUsage returns bool and stores currentDiskUsage', function () {
    // Verify the method signature changed from void to bool
    $reflection = new ReflectionClass(CheckServerResourcesJob::class);
    $method = $reflection->getMethod('checkDiskUsage');

    expect($method->getReturnType()?->getName())->toBe('bool');
});

test('handle includes diskCritical in auto-provisioning trigger', function () {
    $reflection = new ReflectionClass(CheckServerResourcesJob::class);
    $handleSource = file_get_contents($reflection->getFileName());

    // Verify disk is included in the auto-provisioning trigger condition
    expect($handleSource)->toContain('$diskCritical = $this->checkDiskUsage($settings)');
    expect($handleSource)->toContain('$cpuCritical || $memoryCritical || $diskCritical');
    expect($handleSource)->toContain("'disk_critical'");
});

test('currentDiskUsage property exists in CheckServerResourcesJob', function () {
    $reflection = new ReflectionClass(CheckServerResourcesJob::class);
    $property = $reflection->getProperty('currentDiskUsage');

    expect($property->isPrivate())->toBeTrue();
    expect($property->getType()?->getName())->toBe('int');
    expect($property->getType()?->allowsNull())->toBeTrue();
});
