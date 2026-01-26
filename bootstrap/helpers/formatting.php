<?php

/**
 * Formatting helper functions.
 *
 * Contains functions for string formatting, data conversion,
 * and display helpers.
 */

use App\Models\InstanceSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Stringable;

/**
 * Get data and convert to a Stringable.
 */
function data_get_str($data, $key, $default = null): Stringable
{
    $str = data_get($data, $key, $default) ?? $default;

    return str($str);
}

/**
 * Remove ANSI color codes from text.
 */
function removeAnsiColors($text)
{
    return preg_replace('/\e[[][A-Za-z0-9];?[0-9]*m?/', '', $text);
}

/**
 * Sanitize logs for export by removing sensitive information.
 */
function sanitizeLogsForExport(string $text): string
{
    // All sanitization is now handled by remove_iip()
    return remove_iip($text);
}

/**
 * Convert a collection or array to a standard array recursively.
 */
function convertToArray($collection)
{
    if ($collection instanceof Collection) {
        return $collection->map(function ($item) {
            return convertToArray($item);
        })->toArray();
    } elseif ($collection instanceof Stringable) {
        return (string) $collection;
    } elseif (is_array($collection)) {
        return array_map(function ($item) {
            return convertToArray($item);
        }, $collection);
    }

    return $collection;
}

/**
 * Check if a source path is local.
 */
function sourceIsLocal(Stringable $source)
{
    if ($source->startsWith('./') || $source->startsWith('/') || $source->startsWith('~') || $source->startsWith('..') || $source->startsWith('~/') || $source->startsWith('../')) {
        return true;
    }

    return false;
}

/**
 * Replace local source path with a new path.
 */
function replaceLocalSource(Stringable $source, Stringable $replacedWith)
{
    if ($source->startsWith('.')) {
        $source = $source->replaceFirst('.', $replacedWith->value());
    }
    if ($source->startsWith('~')) {
        $source = $source->replaceFirst('~', $replacedWith->value());
    }
    if ($source->startsWith('..')) {
        $source = $source->replaceFirst('..', $replacedWith->value());
    }
    if ($source->endsWith('/') && $source->value() !== '/') {
        $source = $source->replaceLast('/', '');
    }

    return $source;
}

/**
 * Generate Fluentd configuration array.
 */
function generate_fluentd_configuration(): array
{
    return [
        'driver' => 'fluentd',
        'options' => [
            'fluentd-address' => 'tcp://127.0.0.1:24224',
            'fluentd-sub-second-precision' => 'true',
        ],
    ];
}

/**
 * Check if an array is associative.
 */
function isAssociativeArray($array)
{
    if (! is_array($array)) {
        return false;
    }

    // Empty arrays are not considered associative
    if (empty($array)) {
        return false;
    }

    // Get all keys
    $keys = array_keys($array);

    // Check if all keys are integers starting from 0
    $isSequential = ($keys === range(0, count($array) - 1));

    return ! $isSequential;
}

/**
 * Convert environment variables to a key-value collection.
 */
function convertToKeyValueCollection($environment)
{
    $keyValue = collect([]);

    foreach ($environment as $variable) {
        $parts = explode('=', $variable, 2);

        if (count($parts) === 2) {
            $key = $parts[0];
            $value = $parts[1];
        } else {
            $key = $parts[0];
            $value = '';
        }

        $keyValue->put($key, $value);
    }

    return $keyValue;
}

/**
 * Get the helper version from the Saturn platform.
 */
function getHelperVersion(): string
{
    return config('constants.saturn.version', '0.0.0');
}

/**
 * Get wire:navigate attribute based on instance settings.
 */
function wireNavigate(): string
{
    $settings = instanceSettings();
    if ($settings->livewire_navigate ?? true) {
        return 'wire:navigate';
    }

    return '';
}

/**
 * Get the instance settings.
 */
function instanceSettings()
{
    return InstanceSettings::get();
}

/**
 * Format bytes to human-readable format.
 */
function formatBytes(?int $bytes, int $precision = 2): string
{
    if ($bytes === null) {
        return '0 B';
    }

    // Handle negative numbers
    if ($bytes < 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $base = 1024;
    $exponent = floor(log($bytes) / log($base));
    $exponent = min($exponent, count($units) - 1);

    $value = $bytes / pow($base, $exponent);

    return round($value, $precision).' '.$units[$exponent];
}

/**
 * Transform colon-delimited status format to human-readable parentheses format.
 *
 * Handles Docker container status formats with optional health check status and exclusion modifiers.
 *
 * Examples:
 * - running:healthy -> Running (healthy)
 * - running:unhealthy:excluded -> Running (unhealthy, excluded)
 * - exited:excluded -> Exited (excluded)
 * - Proxy:running -> Proxy:running (preserved as-is for headline formatting)
 * - running -> Running
 *
 * @param  string  $status  The status string to format
 * @return string The formatted status string
 */
function formatContainerStatus(string $status): string
{
    // Preserve Proxy statuses as-is (they follow different format)
    if (str($status)->startsWith('Proxy')) {
        return str($status)->headline()->value();
    }

    // Check for :excluded suffix
    $isExcluded = str($status)->endsWith(':excluded');
    $parts = explode(':', $status);

    if ($isExcluded) {
        if (count($parts) === 3) {
            // Has health status: running:unhealthy:excluded -> Running (unhealthy, excluded)
            return str($parts[0])->headline().' ('.$parts[1].', excluded)';
        } else {
            // No health status: exited:excluded -> Exited (excluded)
            return str($parts[0])->headline().' (excluded)';
        }
    } elseif (count($parts) >= 2) {
        // Regular colon format: running:healthy -> Running (healthy)
        return str($parts[0])->headline().' ('.$parts[1].')';
    } else {
        // Simple status: running -> Running
        return str($status)->headline()->value();
    }
}

/**
 * Parse Dockerfile interval string to seconds.
 */
function parseDockerfileInterval(string $something)
{
    $interval = 0;
    $regex = '/(\d+)(ns|us|ms|s|m|h)/';
    preg_match_all($regex, $something, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $value = (int) $match[1];
        $unit = $match[2];

        switch ($unit) {
            case 'ns':
                $interval += $value / 1_000_000_000;
                break;
            case 'us':
                $interval += $value / 1_000_000;
                break;
            case 'ms':
                $interval += $value / 1_000;
                break;
            case 's':
                $interval += $value;
                break;
            case 'm':
                $interval += $value * 60;
                break;
            case 'h':
                $interval += $value * 3600;
                break;
        }
    }

    return $interval;
}

/**
 * Log a message using the configured logging method.
 */
function loggy($message = null, array $context = [])
{
    if (is_null($message)) {
        return;
    }
    if (config('app.debug')) {
        ray($message, $context);
    }
    logger()->debug($message, $context);
}
