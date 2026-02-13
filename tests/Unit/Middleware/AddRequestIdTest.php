<?php

use App\Http\Middleware\AddRequestId;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

test('adds X-Request-ID header to response', function () {
    $middleware = new AddRequestId;
    $request = Request::create('/test', 'GET');

    $response = $middleware->handle($request, function () {
        return new Response('OK');
    });

    expect($response->headers->has('X-Request-ID'))->toBeTrue();
    expect($response->headers->get('X-Request-ID'))->toBeString();
});

test('preserves incoming X-Request-ID header', function () {
    $middleware = new AddRequestId;
    $request = Request::create('/test', 'GET');
    $request->headers->set('X-Request-ID', 'test-id-123');

    $response = $middleware->handle($request, function () {
        return new Response('OK');
    });

    expect($response->headers->get('X-Request-ID'))->toBe('test-id-123');
});

test('generates UUID when no X-Request-ID provided', function () {
    $middleware = new AddRequestId;
    $request = Request::create('/test', 'GET');

    $response = $middleware->handle($request, function () {
        return new Response('OK');
    });

    $requestId = $response->headers->get('X-Request-ID');
    // UUID v4 format
    expect($requestId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

test('stores request ID in app container', function () {
    $middleware = new AddRequestId;
    $request = Request::create('/test', 'GET');
    $request->headers->set('X-Request-ID', 'container-test-id');

    $middleware->handle($request, function () {
        return new Response('OK');
    });

    expect(app('request-id'))->toBe('container-test-id');
});
