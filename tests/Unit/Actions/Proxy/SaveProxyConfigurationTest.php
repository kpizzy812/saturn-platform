<?php

// Security regression tests: command injection prevention in proxy configuration actions.
// These tests verify that shell metacharacters in user-controlled values cannot
// escape the argument context and execute arbitrary shell commands.

// ═══════════════════════════════════════════
// escapeshellarg — neutralisation of payloads
// ═══════════════════════════════════════════

test('escapeshellarg wraps value in single quotes', function () {
    $result = escapeshellarg('/data/saturn/proxy');

    expect($result)->toBe("'/data/saturn/proxy'");
});

test('escapeshellarg neutralizes semicolon injection', function () {
    $malicious = '/data/proxy; rm -rf /';
    $escaped = escapeshellarg($malicious);

    // Must be wrapped as a single argument — semicolon is not a separator
    expect($escaped)->not->toContain('; rm');
    expect($escaped)->toStartWith("'");
    expect($escaped)->toEndWith("'");
});

test('escapeshellarg neutralizes backtick command substitution', function () {
    $malicious = '/data/proxy`id`';
    $escaped = escapeshellarg($malicious);

    expect($escaped)->not->toContain('`id`');
    expect($escaped)->toStartWith("'");
});

test('escapeshellarg neutralizes dollar sign variable expansion', function () {
    $malicious = '/data/proxy$(id)';
    $escaped = escapeshellarg($malicious);

    expect($escaped)->not->toContain('$(id)');
    expect($escaped)->toStartWith("'");
});

test('escapeshellarg neutralizes pipe injection', function () {
    $malicious = '/data/proxy | cat /etc/passwd';
    $escaped = escapeshellarg($malicious);

    expect($escaped)->not->toMatch('/\|\s*cat/');
    expect($escaped)->toStartWith("'");
});

test('escapeshellarg neutralizes newline injection', function () {
    $malicious = "/data/proxy\nrm -rf /";
    $escaped = escapeshellarg($malicious);

    // Newline must be inside the quoted string, not breaking command
    $unquoted = trim($escaped, "'");
    expect($escaped)->toStartWith("'");
    expect($escaped)->toEndWith("'");
    // The whole escaped value should be on one shell token
    expect(substr_count($escaped, "'"))->toBe(2);
});

test('escapeshellarg handles single quotes by converting to safe representation', function () {
    $malicious = "'; rm -rf /; echo '";
    $escaped = escapeshellarg($malicious);

    // PHP replaces single quotes with '\'' to safely embed them
    expect($escaped)->not->toBe("''; rm -rf /; echo ''");
    expect($escaped)->toStartWith("'");
});

// ═══════════════════════════════════════════
// SaveProxyConfiguration — source code audit
// ═══════════════════════════════════════════

test('SaveProxyConfiguration uses escapeshellarg for proxy path', function () {
    $source = file_get_contents(app_path('Actions/Proxy/SaveProxyConfiguration.php'));

    expect($source)->toContain('escapeshellarg($proxy_path)');
});

test('SaveProxyConfiguration uses escapeshellarg for base64 content', function () {
    $source = file_get_contents(app_path('Actions/Proxy/SaveProxyConfiguration.php'));

    expect($source)->toContain('escapeshellarg($docker_compose_yml_base64)');
});

test('SaveProxyConfiguration does not interpolate proxy_path without escaping', function () {
    $source = file_get_contents(app_path('Actions/Proxy/SaveProxyConfiguration.php'));

    // Should not contain raw interpolation like "$proxy_path" outside of escapeshellarg()
    // Allow: escapeshellarg($proxy_path) assigned to $escaped_path, then use $escaped_path
    $lines = explode("\n", $source);
    foreach ($lines as $line) {
        // Skip lines that call escapeshellarg() — those are safe
        if (str_contains($line, 'escapeshellarg')) {
            continue;
        }
        // Skip comments
        if (str_contains(trim($line), '//')) {
            continue;
        }
        // No raw $proxy_path should appear in command strings
        expect($line)->not->toMatch('/".*\$proxy_path.*"/');
    }
});

// ═══════════════════════════════════════════
// StartProxy — source code audit
// ═══════════════════════════════════════════

test('StartProxy uses escapeshellarg for proxy path in swarm mode', function () {
    $source = file_get_contents(app_path('Actions/Proxy/StartProxy.php'));

    expect($source)->toContain('escapeshellarg($proxy_path');
});

test('StartProxy does not interpolate raw proxy_path into mkdir commands', function () {
    $source = file_get_contents(app_path('Actions/Proxy/StartProxy.php'));

    // Verify that mkdir and cd commands use escaped variables
    expect($source)->toContain('mkdir -p $escaped_dynamic_path');
    expect($source)->toContain('cd $escaped_proxy_path');
});

test('StartProxy does not use raw proxy_path in shell commands', function () {
    $source = file_get_contents(app_path('Actions/Proxy/StartProxy.php'));
    $lines = explode("\n", $source);

    foreach ($lines as $lineNum => $line) {
        // Skip lines that define or assign escape variables
        if (str_contains($line, 'escapeshellarg') || str_contains($line, '$proxy_path =')) {
            continue;
        }
        // Skip comments and blank lines
        if (str_contains(trim($line), '//') || trim($line) === '') {
            continue;
        }
        // Commands concatenated into $commands array must not use raw $proxy_path
        if (str_contains($line, '"') && str_contains($line, '$proxy_path')) {
            // This would be a raw interpolation into a shell command string
            expect(true)->toBeFalse("Line {$lineNum}: raw \$proxy_path found in command string: {$line}");
        }
    }
});

// ═══════════════════════════════════════════
// StopProxy — source code audit
// ═══════════════════════════════════════════

test('StopProxy uses escapeshellarg for container name in docker stop', function () {
    $source = file_get_contents(app_path('Actions/Proxy/StopProxy.php'));

    expect($source)->toContain('escapeshellarg($containerName)');
    expect($source)->toContain('docker stop -t=$timeout $escapedName');
});
