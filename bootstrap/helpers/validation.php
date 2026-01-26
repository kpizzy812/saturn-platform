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
