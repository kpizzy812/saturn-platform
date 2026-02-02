<?php

namespace App\Services\AI\CodeReview\DTOs;

use Illuminate\Support\Collection;

/**
 * Represents a file in a diff.
 */
readonly class DiffFile
{
    /**
     * @param  Collection<int, DiffLine>  $lines
     */
    public function __construct(
        public string $path,
        public string $status, // 'added', 'modified', 'deleted', 'renamed'
        public ?string $previousPath,
        public Collection $lines,
    ) {}

    /**
     * Get only added lines.
     *
     * @return Collection<int, DiffLine>
     */
    public function getAddedLines(): Collection
    {
        return $this->lines->filter(fn (DiffLine $line) => $line->isAdded());
    }

    /**
     * Get only removed lines.
     *
     * @return Collection<int, DiffLine>
     */
    public function getRemovedLines(): Collection
    {
        return $this->lines->filter(fn (DiffLine $line) => $line->isRemoved());
    }

    /**
     * Check if file is a new file.
     */
    public function isNew(): bool
    {
        return $this->status === 'added';
    }

    /**
     * Check if file was deleted.
     */
    public function isDeleted(): bool
    {
        return $this->status === 'deleted';
    }
}
