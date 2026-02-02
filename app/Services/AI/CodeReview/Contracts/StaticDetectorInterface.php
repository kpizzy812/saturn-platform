<?php

namespace App\Services\AI\CodeReview\Contracts;

use App\Services\AI\CodeReview\DTOs\DiffResult;
use App\Services\AI\CodeReview\DTOs\Violation;
use Illuminate\Support\Collection;

/**
 * Interface for static code analysis detectors.
 *
 * Detectors analyze diff results and return violations.
 * They should be deterministic where possible (regex, AST-based).
 */
interface StaticDetectorInterface
{
    /**
     * Analyze the diff and return found violations.
     *
     * @return Collection<int, Violation>
     */
    public function detect(DiffResult $diff): Collection;

    /**
     * Get the detector name for logging.
     */
    public function getName(): string;

    /**
     * Get the detector version for cache invalidation.
     */
    public function getVersion(): string;

    /**
     * Check if this detector is enabled.
     */
    public function isEnabled(): bool;
}
