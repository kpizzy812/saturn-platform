<?php

/**
 * Validation and sanitization helper functions.
 *
 * Contains functions for input validation, sanitization,
 * and security-related path validation.
 */

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Poliander\Cron\CronExpression;

/**
 * Sanitize a string by removing HTML tags, control characters, and encoding special chars.
 */
function sanitize_string(?string $input = null): ?string
{
    if (is_null($input)) {
        return null;
    }
    // Remove any HTML/PHP tags
    $sanitized = strip_tags($input);

    // Convert special characters to HTML entities
    $sanitized = htmlspecialchars($sanitized, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Remove any control characters
    $sanitized = preg_replace('/[\x00-\x1F\x7F]/u', '', $sanitized);

    // Trim whitespace
    $sanitized = trim($sanitized);

    return $sanitized;
}

/**
 * Validate that a path or identifier is safe for use in shell commands.
 *
 * This function prevents command injection by rejecting strings that contain
 * shell metacharacters or command substitution patterns.
 *
 * @param  string  $input  The path or identifier to validate
 * @param  string  $context  Descriptive name for error messages (e.g., 'volume source', 'service name')
 * @return string The validated input (unchanged if valid)
 *
 * @throws \Exception If dangerous characters are detected
 */
function validateShellSafePath(string $input, string $context = 'path'): string
{
    // List of dangerous shell metacharacters that enable command injection
    $dangerousChars = [
        '`' => 'backtick (command substitution)',
        '$(' => 'command substitution',
        '${' => 'variable substitution with potential command injection',
        '|' => 'pipe operator',
        '&' => 'background/AND operator',
        ';' => 'command separator',
        "\n" => 'newline (command separator)',
        "\r" => 'carriage return',
        "\t" => 'tab (token separator)',
        '>' => 'output redirection',
        '<' => 'input redirection',
    ];

    // Check for dangerous characters
    foreach ($dangerousChars as $char => $description) {
        if (str_contains($input, $char)) {
            throw new \Exception(
                "Invalid {$context}: contains forbidden character '{$char}' ({$description}). ".
                'Shell metacharacters are not allowed for security reasons.'
            );
        }
    }

    return $input;
}

/**
 * Translate cron expression aliases to standard cron format.
 */
function translate_cron_expression($expression_to_validate): string
{
    if (isset(VALID_CRON_STRINGS[$expression_to_validate])) {
        return VALID_CRON_STRINGS[$expression_to_validate];
    }

    return $expression_to_validate;
}

/**
 * Validate a cron expression.
 */
function validate_cron_expression($expression_to_validate): bool
{
    if (empty($expression_to_validate)) {
        return false;
    }
    $isValid = false;
    $expression = new CronExpression($expression_to_validate);
    $isValid = $expression->isValid();

    if (isset(VALID_CRON_STRINGS[$expression_to_validate])) {
        $isValid = true;
    }

    return $isValid;
}

/**
 * Validate a timezone identifier.
 */
function validate_timezone(string $timezone): bool
{
    return in_array($timezone, timezone_identifiers_list());
}

/**
 * Custom API validator for collections or arrays.
 */
function customApiValidator(Collection|array $item, array $rules)
{
    if (is_array($item)) {
        $item = collect($item);
    }

    return Validator::make($item->toArray(), $rules, [
        'required' => 'This field is required.',
    ]);
}

/**
 * Check if a value is base64 encoded.
 */
function isBase64Encoded($strValue)
{
    return base64_encode(base64_decode($strValue, true)) === $strValue;
}

/**
 * Validate a webhook URL for SSRF protection.
 *
 * Checks that the URL is safe to send HTTP requests to by blocking:
 * - Private IP ranges (10.x, 172.16-31.x, 192.168.x)
 * - Localhost (127.x, ::1)
 * - Cloud metadata endpoints (169.254.169.254)
 * - Link-local addresses (169.254.x)
 * - IPv6 local addresses
 *
 * @param  string  $url  The URL to validate
 * @return array{valid: bool, error: string|null} Validation result with error message if invalid
 */
function validateWebhookUrl(string $url): array
{
    // Parse the URL
    $parsed = parse_url($url);

    if (! $parsed || ! isset($parsed['host'])) {
        return ['valid' => false, 'error' => 'Invalid URL format'];
    }

    // Only allow http and https schemes
    $scheme = strtolower($parsed['scheme'] ?? '');
    if (! in_array($scheme, ['http', 'https'])) {
        return ['valid' => false, 'error' => 'Only HTTP and HTTPS URLs are allowed'];
    }

    $host = strtolower($parsed['host']);

    // Block localhost variations
    $localhostPatterns = [
        'localhost',
        '127.0.0.1',
        '::1',
        '[::1]',
        '0.0.0.0',
        '0177.0.0.1', // Octal localhost
        '2130706433', // Decimal localhost
        '0x7f.0x0.0x0.0x1', // Hex localhost
    ];

    foreach ($localhostPatterns as $pattern) {
        if ($host === $pattern || str_starts_with($host, $pattern.':')) {
            return ['valid' => false, 'error' => 'Localhost URLs are not allowed'];
        }
    }

    // Resolve hostname to IP address for further checks
    $ip = $host;
    if (! filter_var($host, FILTER_VALIDATE_IP)) {
        // It's a hostname, try to resolve it
        $resolved = gethostbyname($host);
        if ($resolved === $host) {
            // Resolution failed, but we'll allow it (might resolve later)
            // For stricter security, uncomment below:
            // return ['valid' => false, 'error' => 'Could not resolve hostname'];
        } else {
            $ip = $resolved;
        }
    }

    // Validate IP address if we have one
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        // Block private IP ranges (RFC 1918)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false) {
            return ['valid' => false, 'error' => 'Private IP addresses are not allowed'];
        }

        // Block reserved IP ranges
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE) === false) {
            return ['valid' => false, 'error' => 'Reserved IP addresses are not allowed'];
        }

        // Block link-local addresses (169.254.x.x) - includes AWS metadata endpoint
        if (str_starts_with($ip, '169.254.')) {
            return ['valid' => false, 'error' => 'Link-local addresses are not allowed'];
        }

        // Block loopback range (127.x.x.x)
        if (str_starts_with($ip, '127.')) {
            return ['valid' => false, 'error' => 'Loopback addresses are not allowed'];
        }

        // Block IPv6 private/reserved if applicable
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // fe80::/10 - link-local
            // fc00::/7 - unique local
            $ipv6Prefix = strtolower(substr($ip, 0, 4));
            if (in_array($ipv6Prefix, ['fe80', 'fc00', 'fd00'])) {
                return ['valid' => false, 'error' => 'IPv6 local addresses are not allowed'];
            }
        }
    }

    // Additional checks for specific dangerous hosts
    $dangerousHosts = [
        'metadata.google.internal',
        'metadata.google',
        '169.254.169.254', // AWS/GCP/Azure metadata
        'instance-data', // AWS instance data
    ];

    foreach ($dangerousHosts as $dangerous) {
        if ($host === $dangerous || str_ends_with($host, '.'.$dangerous)) {
            return ['valid' => false, 'error' => 'Cloud metadata endpoints are not allowed'];
        }
    }

    return ['valid' => true, 'error' => null];
}

/**
 * Check if a webhook URL is safe for SSRF.
 *
 * Convenience wrapper around validateWebhookUrl() that returns a boolean.
 *
 * @param  string  $url  The URL to check
 * @return bool True if the URL is safe, false otherwise
 */
function isWebhookUrlSafe(string $url): bool
{
    $result = validateWebhookUrl($url);

    return $result['valid'];
}

/**
 * Validates that a file path is safely within the /tmp/ directory.
 * Protects against path traversal attacks by resolving the real path
 * and verifying it stays within /tmp/.
 *
 * Note: On macOS, /tmp is often a symlink to /private/tmp, which is handled.
 */
function isSafeTmpPath(?string $path): bool
{
    if (blank($path)) {
        return false;
    }

    // URL decode to catch encoded traversal attempts
    $decodedPath = urldecode($path);

    // Minimum length check - /tmp/x is 6 chars
    if (strlen($decodedPath) < 6) {
        return false;
    }

    // Must start with /tmp/
    if (! str($decodedPath)->startsWith('/tmp/')) {
        return false;
    }

    // Quick check for obvious traversal attempts
    if (str($decodedPath)->contains('..')) {
        return false;
    }

    // Check for null bytes (directory traversal technique)
    if (str($decodedPath)->contains("\0")) {
        return false;
    }

    // Remove any trailing slashes for consistent validation
    $normalizedPath = rtrim($decodedPath, '/');

    // Normalize the path by removing redundant separators and resolving . and ..
    // We'll do this manually since realpath() requires the path to exist
    $parts = explode('/', $normalizedPath);
    $resolvedParts = [];

    foreach ($parts as $part) {
        if ($part === '' || $part === '.') {
            // Skip empty parts (from //) and current directory references
            continue;
        } elseif ($part === '..') {
            // Parent directory - this should have been caught earlier but double-check
            return false;
        } else {
            $resolvedParts[] = $part;
        }
    }

    $resolvedPath = '/'.implode('/', $resolvedParts);

    // Final check: resolved path must start with /tmp/
    // And must have at least one component after /tmp/
    if (! str($resolvedPath)->startsWith('/tmp/') || $resolvedPath === '/tmp') {
        return false;
    }

    // Resolve the canonical /tmp path (handles symlinks like /tmp -> /private/tmp on macOS)
    $canonicalTmpPath = realpath('/tmp');
    if ($canonicalTmpPath === false) {
        // If /tmp doesn't exist, something is very wrong, but allow non-existing paths
        $canonicalTmpPath = '/tmp';
    }

    // Calculate dirname once to avoid redundant calls
    $dirPath = dirname($resolvedPath);

    // If the directory exists, resolve it via realpath to catch symlink attacks
    if (is_dir($dirPath)) {
        // For existing paths, resolve to absolute path to catch symlinks
        $realDir = realpath($dirPath);
        if ($realDir === false) {
            return false;
        }

        // Check if the real directory is within /tmp (or its canonical path)
        if (! str($realDir)->startsWith('/tmp') && ! str($realDir)->startsWith($canonicalTmpPath)) {
            return false;
        }
    }

    return true;
}
