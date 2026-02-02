<?php

namespace App\Services\AI\CodeReview\DTOs;

use Illuminate\Support\Collection;

/**
 * Represents the complete diff result from a commit comparison.
 */
readonly class DiffResult
{
    /**
     * @param  Collection<int, DiffFile>  $files
     * @param  Collection<int, DiffLine>  $addedLines  All added lines across files
     */
    public function __construct(
        public string $commitSha,
        public ?string $baseCommitSha,
        public Collection $files,
        public Collection $addedLines,
        public int $totalAdditions,
        public int $totalDeletions,
        public string $rawDiff, // For LLM enrichment, redacted before sending
    ) {}

    /**
     * Get file paths as array.
     */
    public function getFilePaths(): array
    {
        return $this->files->pluck('path')->toArray();
    }

    /**
     * Get total lines changed.
     */
    public function getTotalChanges(): int
    {
        return $this->totalAdditions + $this->totalDeletions;
    }

    /**
     * Check if diff is within acceptable limits.
     */
    public function isWithinLimits(int $maxLines = 3000): bool
    {
        return $this->getTotalChanges() <= $maxLines;
    }

    /**
     * Check if diff is empty.
     */
    public function isEmpty(): bool
    {
        return $this->files->isEmpty();
    }
}
