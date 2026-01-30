<?php

namespace App\Services\RepositoryAnalyzer\Exceptions;

/**
 * Exception thrown when infrastructure provisioning fails
 *
 * Extends RepositoryAnalysisException so it can be caught by the same
 * catch block in controllers.
 */
class ProvisioningException extends RepositoryAnalysisException {}
