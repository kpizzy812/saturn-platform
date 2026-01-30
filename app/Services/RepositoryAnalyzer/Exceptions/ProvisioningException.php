<?php

namespace App\Services\RepositoryAnalyzer\Exceptions;

use Exception;

/**
 * Exception thrown when infrastructure provisioning fails
 */
class ProvisioningException extends Exception
{
    public function __construct(
        string $message,
        int $code = 0,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
