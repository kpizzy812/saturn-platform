<?php

namespace App\Helpers;

use App\Traits\SshRetryable;

/**
 * Helper class to use SshRetryable trait in non-class contexts
 */
class SshRetryHandler
{
    use SshRetryable;

    /**
     * Static method to get a singleton instance
     */
    public static function instance(): self
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new self;
        }

        return $instance;
    }

    /**
     * Convenience static method for retry execution
     */
    public static function retry(callable $callback, array $context = [], bool $throwError = true)
    {
        return self::instance()->executeWithSshRetry($callback, $context, $throwError);
    }

    /**
     * No-op implementation for SshRetryable trait compatibility.
     * Deployment log entries are only relevant in ApplicationDeploymentJob context.
     */
    protected function addRetryLogEntry(int $attempt, int $maxRetries, int $delay, string $errorMessage): void
    {
        // No deployment queue in this context
    }
}
