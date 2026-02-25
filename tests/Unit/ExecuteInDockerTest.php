<?php

it('wraps simple command in docker exec', function () {
    $result = executeInDocker('container123', 'ls -la /app');

    expect($result)->toBe("docker exec container123 bash -c 'ls -la /app'");
});

it('escapes single quotes in command for safe bash embedding', function () {
    $command = "nixpacks plan --env 'KEY=VALUE' /path";
    $result = executeInDocker('container123', $command);

    // Single quotes inside should be escaped as '\'' for correct bash parsing
    expect($result)->toBe("docker exec container123 bash -c 'nixpacks plan --env '\\''KEY=VALUE'\\'' /path'");
});

it('handles multiple single-quoted env args without losing PATH argument', function () {
    // This is the exact pattern that was failing in production deployments
    $command = "nixpacks plan -f json --env 'SATURN_URL=https://app.example.com' --env 'SATURN_BRANCH=main' --build-cmd 'npm run build' --start-cmd 'npm start' /artifacts/uuid123/app";
    $result = executeInDocker('build-container', $command);

    // The PATH argument must be included in the final command
    expect($result)->toContain('/artifacts/uuid123/app');

    // Each single quote should be escaped
    expect($result)->not->toContain("bash -c 'nixpacks plan -f json --env 'SATURN_URL");
});

it('leaves commands without single quotes unchanged', function () {
    $command = 'cat /app/package.json 2>/dev/null || echo {}';
    $result = executeInDocker('container123', $command);

    expect($result)->toBe("docker exec container123 bash -c 'cat /app/package.json 2>/dev/null || echo {}'");
});

it('handles escapeshellarg output correctly', function () {
    // escapeshellarg wraps in single quotes on Unix
    $key = 'DATABASE_URL';
    $value = 'postgres://user:pass@host:5432/db';
    $envArg = '--env '.escapeshellarg("{$key}={$value}");

    $command = "nixpacks plan -f json {$envArg} /workdir";
    $result = executeInDocker('build-uuid', $command);

    // The /workdir path must be present in the result
    expect($result)->toContain('/workdir');
    // The command must be a valid shell string (no unmatched quotes)
    expect(substr_count($result, "'") % 2)->toBe(0, 'Unmatched single quotes detected');
});
