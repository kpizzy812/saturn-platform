<?php

namespace App\Services\AI\CodeReview\DTOs;

/**
 * Represents a single line from a diff.
 */
readonly class DiffLine
{
    public function __construct(
        public string $file,
        public int $number,
        public string $content,
        public string $type, // 'added', 'removed', 'context'
    ) {}

    /**
     * Check if this is an added line.
     */
    public function isAdded(): bool
    {
        return $this->type === 'added';
    }

    /**
     * Check if this is a removed line.
     */
    public function isRemoved(): bool
    {
        return $this->type === 'removed';
    }
}
