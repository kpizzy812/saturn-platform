<?php

namespace App\Parsers;

use Symfony\Component\Yaml\Yaml;

/**
 * Parser for Docker volume strings and Docker Compose volume validation.
 *
 * Handles parsing of Docker volume mount syntax (source:target:mode)
 * and validation of Docker Compose files for command injection vulnerabilities.
 */
class DockerVolumeParser
{
    /**
     * Valid Docker volume mount modes.
     */
    private const VALID_MODES = [
        'ro',
        'rw',
        'z',
        'Z',
        'rslave',
        'rprivate',
        'rshared',
        'slave',
        'private',
        'shared',
        'cached',
        'delegated',
        'consistent',
    ];

    /**
     * Validates a Docker Compose YAML string for command injection vulnerabilities.
     * This should be called BEFORE saving to database to prevent malicious data from being stored.
     *
     * @param  string  $composeYaml  The raw Docker Compose YAML content
     *
     * @throws \Exception If the compose file contains command injection attempts
     */
    public static function validateComposeForInjection(string $composeYaml): void
    {
        try {
            $parsed = Yaml::parse($composeYaml);
        } catch (\Exception $e) {
            throw new \Exception('Invalid YAML format: '.$e->getMessage(), 0, $e);
        }

        if (! is_array($parsed) || ! isset($parsed['services']) || ! is_array($parsed['services'])) {
            throw new \Exception('Docker Compose file must contain a "services" section');
        }

        // Validate service names
        foreach ($parsed['services'] as $serviceName => $serviceConfig) {
            try {
                validateShellSafePath($serviceName, 'service name');
            } catch (\Exception $e) {
                throw new \Exception(
                    'Invalid Docker Compose service name: '.$e->getMessage().
                    ' Service names must not contain shell metacharacters.',
                    0,
                    $e
                );
            }

            // Validate volumes in this service (both string and array formats)
            if (isset($serviceConfig['volumes']) && is_array($serviceConfig['volumes'])) {
                foreach ($serviceConfig['volumes'] as $volume) {
                    if (is_string($volume)) {
                        // String format: "source:target" or "source:target:mode"
                        self::validateVolumeStringForInjection($volume);
                    } elseif (is_array($volume)) {
                        // Array format: {type: bind, source: ..., target: ...}
                        self::validateVolumeArrayForInjection($volume);
                    }
                }
            }
        }
    }

    /**
     * Validates a volume definition in array format.
     *
     * @param  array  $volume  The volume array to validate
     *
     * @throws \Exception If the volume contains command injection attempts
     */
    private static function validateVolumeArrayForInjection(array $volume): void
    {
        if (isset($volume['source'])) {
            $source = $volume['source'];
            if (is_string($source)) {
                // Allow env vars and env vars with defaults (validated in parseVolumeString)
                // Also allow env vars followed by safe path concatenation (e.g., ${VAR}/path)
                $isSimpleEnvVar = preg_match('/^\$\{[a-zA-Z_][a-zA-Z0-9_]*\}$/', $source);
                $isEnvVarWithDefault = preg_match('/^\$\{[^}]+:-[^}]*\}$/', $source);
                $isEnvVarWithPath = preg_match('/^\$\{[a-zA-Z_][a-zA-Z0-9_]*\}[\/\w\.\-]*$/', $source);

                if (! $isSimpleEnvVar && ! $isEnvVarWithDefault && ! $isEnvVarWithPath) {
                    try {
                        validateShellSafePath($source, 'volume source');
                    } catch (\Exception $e) {
                        throw new \Exception(
                            'Invalid Docker volume definition (array syntax): '.$e->getMessage().
                            ' Please use safe path names without shell metacharacters.',
                            0,
                            $e
                        );
                    }
                }
            }
        }
        if (isset($volume['target'])) {
            $target = $volume['target'];
            if (is_string($target)) {
                try {
                    validateShellSafePath($target, 'volume target');
                } catch (\Exception $e) {
                    throw new \Exception(
                        'Invalid Docker volume definition (array syntax): '.$e->getMessage().
                        ' Please use safe path names without shell metacharacters.',
                        0,
                        $e
                    );
                }
            }
        }
    }

    /**
     * Validates a Docker volume string (format: "source:target" or "source:target:mode")
     *
     * @param  string  $volumeString  The volume string to validate
     *
     * @throws \Exception If the volume string contains command injection attempts
     */
    public static function validateVolumeStringForInjection(string $volumeString): void
    {
        // Canonical parsing also validates and throws on unsafe input
        self::parseVolumeString($volumeString);
    }

    /**
     * Parses a Docker volume string into its components.
     *
     * Handles various formats:
     * - "volume_name" (named volume)
     * - "source:target"
     * - "source:target:mode"
     * - Windows paths (C:\path:target)
     * - Environment variables (${VAR:-default}:target)
     *
     * @param  string  $volumeString  The volume string to parse
     * @return array{source: \Illuminate\Support\Stringable|null, target: \Illuminate\Support\Stringable|null, mode: \Illuminate\Support\Stringable|null}
     *
     * @throws \Exception If the volume string contains command injection attempts
     */
    public static function parseVolumeString(string $volumeString): array
    {
        $volumeString = trim($volumeString);
        $source = null;
        $target = null;
        $mode = null;

        // First, check if the source contains an environment variable with default value
        // This needs to be done before counting colons because ${VAR:-value} contains a colon
        $envVarPattern = '/^\$\{[^}]+:-[^}]*\}/';
        $hasEnvVarWithDefault = false;
        $envVarEndPos = 0;

        if (preg_match($envVarPattern, $volumeString, $matches)) {
            $hasEnvVarWithDefault = true;
            $envVarEndPos = strlen($matches[0]);
        }

        // Count colons, but exclude those inside environment variables
        $effectiveVolumeString = $volumeString;
        if ($hasEnvVarWithDefault) {
            // Temporarily replace the env var to count colons correctly
            $effectiveVolumeString = substr($volumeString, $envVarEndPos);
            $colonCount = substr_count($effectiveVolumeString, ':');
        } else {
            $colonCount = substr_count($volumeString, ':');
        }

        if ($colonCount === 0) {
            // Named volume without target (unusual but valid)
            // Example: "myvolume"
            $source = $volumeString;
            $target = $volumeString;
        } elseif ($colonCount === 1) {
            // Simple volume mapping
            $result = self::parseOneColon($volumeString, $hasEnvVarWithDefault, $envVarEndPos);
            $source = $result['source'];
            $target = $result['target'];
        } elseif ($colonCount === 2) {
            // Volume with mode OR Windows path OR env var with mode
            $result = self::parseTwoColons($volumeString, $hasEnvVarWithDefault, $envVarEndPos);
            $source = $result['source'];
            $target = $result['target'];
            $mode = $result['mode'];
        } else {
            // More than 2 colons - likely Windows paths or complex cases
            $result = self::parseMultipleColons($volumeString);
            $source = $result['source'];
            $target = $result['target'];
            $mode = $result['mode'];
        }

        // Handle environment variable expansion in source
        $source = self::expandEnvVarInSource($source);

        // Validate source path for command injection attempts
        self::validateSourcePath($source);

        // Also validate target path
        self::validateTargetPath($target);

        return [
            'source' => $source !== null ? str($source) : null,
            'target' => $target !== null ? str($target) : null,
            'mode' => $mode !== null ? str($mode) : null,
        ];
    }

    /**
     * Parses volume string with exactly one colon.
     */
    private static function parseOneColon(string $volumeString, bool $hasEnvVarWithDefault, int $envVarEndPos): array
    {
        $source = null;
        $target = null;

        // Simple volume mapping
        // Examples: "gitea:/data" or "./data:/app/data" or "${VAR:-default}:/data"
        if ($hasEnvVarWithDefault) {
            $source = substr($volumeString, 0, $envVarEndPos);
            $remaining = substr($volumeString, $envVarEndPos);
            if (strlen($remaining) > 0 && $remaining[0] === ':') {
                $target = substr($remaining, 1);
            } else {
                $target = $remaining;
            }
        } else {
            $parts = explode(':', $volumeString);
            $source = $parts[0];
            $target = $parts[1];
        }

        return ['source' => $source, 'target' => $target];
    }

    /**
     * Parses volume string with exactly two colons.
     */
    private static function parseTwoColons(string $volumeString, bool $hasEnvVarWithDefault, int $envVarEndPos): array
    {
        $source = null;
        $target = null;
        $mode = null;

        // Volume with mode OR Windows path OR env var with mode
        // Handle env var with mode first
        if ($hasEnvVarWithDefault) {
            // ${VAR:-default}:/path:mode
            $source = substr($volumeString, 0, $envVarEndPos);
            $remaining = substr($volumeString, $envVarEndPos);

            if (strlen($remaining) > 0 && $remaining[0] === ':') {
                $remaining = substr($remaining, 1);
                $lastColon = strrpos($remaining, ':');

                if ($lastColon !== false) {
                    $possibleMode = substr($remaining, $lastColon + 1);

                    if (in_array($possibleMode, self::VALID_MODES)) {
                        $mode = $possibleMode;
                        $target = substr($remaining, 0, $lastColon);
                    } else {
                        $target = $remaining;
                    }
                } else {
                    $target = $remaining;
                }
            }
        } elseif (preg_match('/^[A-Za-z]:/', $volumeString)) {
            // Windows path as source (C:/, D:/, etc.)
            // Find the second colon which is the real separator
            $secondColon = strpos($volumeString, ':', 2);
            if ($secondColon !== false) {
                $source = substr($volumeString, 0, $secondColon);
                $target = substr($volumeString, $secondColon + 1);
            } else {
                // Malformed, treat as is
                $source = $volumeString;
                $target = $volumeString;
            }
        } else {
            // Not a Windows path, check for mode
            $lastColon = strrpos($volumeString, ':');
            $possibleMode = substr($volumeString, $lastColon + 1);

            if (in_array($possibleMode, self::VALID_MODES)) {
                // It's a mode
                // Examples: "gitea:/data:ro" or "./data:/app/data:rw"
                $mode = $possibleMode;
                $volumeWithoutMode = substr($volumeString, 0, $lastColon);
                $colonPos = strpos($volumeWithoutMode, ':');

                if ($colonPos !== false) {
                    $source = substr($volumeWithoutMode, 0, $colonPos);
                    $target = substr($volumeWithoutMode, $colonPos + 1);
                } else {
                    // Shouldn't happen for valid volume strings
                    $source = $volumeWithoutMode;
                    $target = $volumeWithoutMode;
                }
            } else {
                // The last colon is part of the path
                // For now, treat the first occurrence of : as the separator
                $firstColon = strpos($volumeString, ':');
                $source = substr($volumeString, 0, $firstColon);
                $target = substr($volumeString, $firstColon + 1);
            }
        }

        return ['source' => $source, 'target' => $target, 'mode' => $mode];
    }

    /**
     * Parses volume string with more than two colons.
     */
    private static function parseMultipleColons(string $volumeString): array
    {
        $source = null;
        $target = null;
        $mode = null;

        // More than 2 colons - likely Windows paths or complex cases
        // Use a heuristic: find the most likely separator colon
        // Look for patterns like "C:" at the beginning (Windows drive)
        if (preg_match('/^[A-Za-z]:/', $volumeString)) {
            // Windows path as source
            // Find the next colon after the drive letter
            $secondColon = strpos($volumeString, ':', 2);
            if ($secondColon !== false) {
                $source = substr($volumeString, 0, $secondColon);
                $remaining = substr($volumeString, $secondColon + 1);

                // Check if there's a mode at the end
                $lastColon = strrpos($remaining, ':');
                if ($lastColon !== false) {
                    $possibleMode = substr($remaining, $lastColon + 1);

                    if (in_array($possibleMode, self::VALID_MODES)) {
                        $mode = $possibleMode;
                        $target = substr($remaining, 0, $lastColon);
                    } else {
                        $target = $remaining;
                    }
                } else {
                    $target = $remaining;
                }
            } else {
                // Malformed, treat as is
                $source = $volumeString;
                $target = $volumeString;
            }
        } else {
            // Try to parse normally, treating first : as separator
            $firstColon = strpos($volumeString, ':');
            $source = substr($volumeString, 0, $firstColon);
            $remaining = substr($volumeString, $firstColon + 1);

            // Check for mode at the end
            $lastColon = strrpos($remaining, ':');
            if ($lastColon !== false) {
                $possibleMode = substr($remaining, $lastColon + 1);

                if (in_array($possibleMode, self::VALID_MODES)) {
                    $mode = $possibleMode;
                    $target = substr($remaining, 0, $lastColon);
                } else {
                    $target = $remaining;
                }
            } else {
                $target = $remaining;
            }
        }

        return ['source' => $source, 'target' => $target, 'mode' => $mode];
    }

    /**
     * Expands environment variable in source if present.
     */
    private static function expandEnvVarInSource(?string $source): ?string
    {
        if (! $source) {
            return $source;
        }

        // Handle environment variable expansion in source
        // Example: ${VOLUME_DB_PATH:-db} should extract default value if present
        if (preg_match('/^\$\{([^}]+)\}$/', $source, $matches)) {
            $varContent = $matches[1];

            // Check if there's a default value with :-
            if (strpos($varContent, ':-') !== false) {
                $parts = explode(':-', $varContent, 2);
                $varName = $parts[0];
                $defaultValue = isset($parts[1]) ? $parts[1] : '';

                // If there's a non-empty default value, use it for source
                if ($defaultValue !== '') {
                    $source = $defaultValue;
                } else {
                    // Empty default value, keep the variable reference for env resolution
                    $source = '${'.$varName.'}';
                }
            }
            // Otherwise keep the variable as-is for later expansion (no default value)
        }

        return $source;
    }

    /**
     * Validates source path for command injection attempts.
     *
     * @throws \Exception If the source path contains command injection attempts
     */
    private static function validateSourcePath(?string $source): void
    {
        if ($source === null) {
            return;
        }

        // Allow environment variables like ${VAR_NAME} or ${VAR}
        // Also allow env vars followed by safe path concatenation (e.g., ${VAR}/path)
        $sourceStr = is_string($source) ? $source : $source;

        // Skip validation for simple environment variable references
        // Pattern 1: ${WORD_CHARS} with no special characters inside
        // Pattern 2: ${WORD_CHARS}/path/to/file (env var with path concatenation)
        $isSimpleEnvVar = preg_match('/^\$\{[a-zA-Z_][a-zA-Z0-9_]*\}$/', $sourceStr);
        $isEnvVarWithPath = preg_match('/^\$\{[a-zA-Z_][a-zA-Z0-9_]*\}[\/\w\.\-]*$/', $sourceStr);

        if (! $isSimpleEnvVar && ! $isEnvVarWithPath) {
            try {
                validateShellSafePath($sourceStr, 'volume source');
            } catch (\Exception $e) {
                // Re-throw with more context about the volume string
                throw new \Exception(
                    'Invalid Docker volume definition: '.$e->getMessage().
                    ' Please use safe path names without shell metacharacters.'
                );
            }
        }
    }

    /**
     * Validates target path for command injection attempts.
     *
     * @throws \Exception If the target path contains command injection attempts
     */
    private static function validateTargetPath(?string $target): void
    {
        if ($target === null) {
            return;
        }

        $targetStr = is_string($target) ? $target : $target;
        // Target paths in containers are typically absolute paths, so we validate them too
        // but they're less likely to be dangerous since they're not used in host commands
        // Still, defense in depth is important
        try {
            validateShellSafePath($targetStr, 'volume target');
        } catch (\Exception $e) {
            throw new \Exception(
                'Invalid Docker volume definition: '.$e->getMessage().
                ' Please use safe path names without shell metacharacters.'
            );
        }
    }
}
