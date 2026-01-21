<?php

use App\Jobs\SaturnTask;

it('SaturnTask has failed method that handles cleanup', function () {
    $reflection = new ReflectionClass(SaturnTask::class);

    // Verify failed method exists
    expect($reflection->hasMethod('failed'))->toBeTrue();

    // Get the failed method
    $failedMethod = $reflection->getMethod('failed');

    // Read the method source to verify it dispatches events
    $filename = $reflection->getFileName();
    $startLine = $failedMethod->getStartLine();
    $endLine = $failedMethod->getEndLine();

    $source = file($filename);
    $methodSource = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));

    // Verify the implementation contains event dispatch logic
    expect($methodSource)
        ->toContain('call_event_on_finish')
        ->and($methodSource)->toContain('event(new $eventClass')
        ->and($methodSource)->toContain('call_event_data')
        ->and($methodSource)->toContain('Log::info');
});

it('SaturnTask failed method updates activity status to ERROR', function () {
    $reflection = new ReflectionClass(SaturnTask::class);
    $failedMethod = $reflection->getMethod('failed');

    // Read the method source
    $filename = $reflection->getFileName();
    $startLine = $failedMethod->getStartLine();
    $endLine = $failedMethod->getEndLine();

    $source = file($filename);
    $methodSource = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));

    // Verify activity status is set to ERROR
    expect($methodSource)
        ->toContain("'status' => ProcessStatus::ERROR->value")
        ->and($methodSource)->toContain("'error' =>")
        ->and($methodSource)->toContain("'failed_at' =>");
});

it('SaturnTask failed method has proper error handling for event dispatch', function () {
    $reflection = new ReflectionClass(SaturnTask::class);
    $failedMethod = $reflection->getMethod('failed');

    // Read the method source
    $filename = $reflection->getFileName();
    $startLine = $failedMethod->getStartLine();
    $endLine = $failedMethod->getEndLine();

    $source = file($filename);
    $methodSource = implode('', array_slice($source, $startLine - 1, $endLine - $startLine + 1));

    // Verify try-catch around event dispatch
    expect($methodSource)
        ->toContain('try {')
        ->and($methodSource)->toContain('} catch (\Throwable $e) {')
        ->and($methodSource)->toContain("Log::error('Error dispatching cleanup event");
});

it('SaturnTask constructor stores call_event_on_finish and call_event_data', function () {
    $reflection = new ReflectionClass(SaturnTask::class);
    $constructor = $reflection->getConstructor();

    // Get constructor parameters
    $parameters = $constructor->getParameters();
    $paramNames = array_map(fn ($p) => $p->getName(), $parameters);

    // Verify both parameters exist
    expect($paramNames)
        ->toContain('call_event_on_finish')
        ->and($paramNames)->toContain('call_event_data');

    // Verify they are public properties (constructor property promotion)
    expect($reflection->hasProperty('call_event_on_finish'))->toBeTrue();
    expect($reflection->hasProperty('call_event_data'))->toBeTrue();
});
