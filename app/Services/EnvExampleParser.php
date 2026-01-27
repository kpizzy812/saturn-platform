<?php

namespace App\Services;

/**
 * Parses .env.example / .env.sample / .env.template files
 * into structured environment variable definitions.
 */
class EnvExampleParser
{
    /**
     * Common placeholder values that indicate a variable needs user input.
     */
    private const PLACEHOLDER_PATTERNS = [
        'CHANGE_ME',
        'changeme',
        'change_me',
        'your_',
        'YOUR_',
        'REPLACE_',
        'replace_',
        'xxx',
        'XXX',
        'TODO',
        'todo',
        'FIXME',
        'fixme',
        'example',
        'EXAMPLE',
        'placeholder',
        'PLACEHOLDER',
        'INSERT_',
        'insert_',
        'SET_THIS',
        'set_this',
        'fill_in',
        'FILL_IN',
    ];

    /**
     * Framework detection patterns mapping env keys to framework names.
     *
     * @var array<string, array<string>>
     */
    private const FRAMEWORK_SIGNATURES = [
        'laravel' => ['APP_KEY', 'APP_ENV', 'DB_CONNECTION', 'BROADCAST_DRIVER', 'CACHE_DRIVER', 'QUEUE_CONNECTION'],
        'nextjs' => ['NEXT_PUBLIC_', 'NEXTAUTH_SECRET', 'NEXTAUTH_URL'],
        'django' => ['DJANGO_SECRET_KEY', 'DJANGO_SETTINGS_MODULE', 'DJANGO_DEBUG', 'DJANGO_ALLOWED_HOSTS'],
        'rails' => ['RAILS_ENV', 'SECRET_KEY_BASE', 'RAILS_MASTER_KEY', 'RAILS_LOG_TO_STDOUT'],
        'spring' => ['SPRING_DATASOURCE_URL', 'SPRING_PROFILES_ACTIVE', 'SERVER_PORT'],
        'flask' => ['FLASK_APP', 'FLASK_ENV', 'FLASK_DEBUG'],
        'express' => ['NODE_ENV', 'PORT', 'SESSION_SECRET'],
    ];

    /**
     * Parse .env.example content into structured array.
     *
     * @return array<int, array{key: string, value: ?string, comment: ?string, is_required: bool}>
     */
    public static function parse(string $content): array
    {
        if (trim($content) === '') {
            return [];
        }

        $lines = explode("\n", $content);
        $results = [];
        $currentComment = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Skip empty lines
            if ($trimmed === '') {
                $currentComment = null;

                continue;
            }

            // Collect comments (may describe the next variable)
            if (str_starts_with($trimmed, '#')) {
                $currentComment = ltrim(substr($trimmed, 1));

                continue;
            }

            // Parse KEY=VALUE
            $equalsPos = strpos($trimmed, '=');
            if ($equalsPos === false) {
                $currentComment = null;

                continue;
            }

            $key = trim(substr($trimmed, 0, $equalsPos));
            $rawValue = substr($trimmed, $equalsPos + 1);

            // Validate key format (must be valid env var name)
            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                $currentComment = null;

                continue;
            }

            // Parse value (handle quotes)
            $value = self::parseValue($rawValue);

            // Determine if required
            $isRequired = self::isPlaceholder($value);

            $results[] = [
                'key' => $key,
                'value' => $value,
                'comment' => $currentComment,
                'is_required' => $isRequired,
            ];

            $currentComment = null;
        }

        return $results;
    }

    /**
     * Parse a raw value string, handling quotes and inline comments.
     */
    private static function parseValue(string $raw): ?string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        // Double-quoted value
        if (str_starts_with($raw, '"')) {
            if (preg_match('/^"((?:[^"\\\\]|\\\\.)*)"/', $raw, $matches)) {
                return stripcslashes($matches[1]);
            }

            // Unclosed quote - take everything after first quote
            return substr($raw, 1);
        }

        // Single-quoted value
        if (str_starts_with($raw, "'")) {
            if (preg_match("/^'([^']*)'/", $raw, $matches)) {
                return $matches[1];
            }

            // Unclosed quote
            return substr($raw, 1);
        }

        // Unquoted value - strip inline comments
        $value = $raw;
        // Only treat # as comment if preceded by whitespace
        if (preg_match('/^(\S+)\s+#/', $value, $matches)) {
            $value = $matches[1];
        }

        return $value;
    }

    /**
     * Check if a value is a placeholder that needs user input.
     */
    public static function isPlaceholder(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        foreach (self::PLACEHOLDER_PATTERNS as $pattern) {
            if (stripos($value, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect framework from a list of environment variable keys.
     */
    public static function detectFramework(array $keys): ?string
    {
        $scores = [];

        foreach (self::FRAMEWORK_SIGNATURES as $framework => $signatures) {
            $score = 0;
            foreach ($signatures as $sig) {
                // Check for prefix match (e.g., NEXT_PUBLIC_)
                if (str_ends_with($sig, '_')) {
                    foreach ($keys as $key) {
                        if (str_starts_with($key, $sig)) {
                            $score++;
                        }
                    }
                } else {
                    if (in_array($sig, $keys, true)) {
                        $score++;
                    }
                }
            }
            if ($score > 0) {
                $scores[$framework] = $score;
            }
        }

        if (empty($scores)) {
            return null;
        }

        arsort($scores);

        return array_key_first($scores);
    }
}
