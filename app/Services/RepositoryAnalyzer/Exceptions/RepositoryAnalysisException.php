<?php

namespace App\Services\RepositoryAnalyzer\Exceptions;

use Exception;

/**
 * Exception thrown when repository analysis fails
 */
class RepositoryAnalysisException extends Exception
{
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
