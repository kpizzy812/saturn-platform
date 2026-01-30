<?php

namespace App\Services\RepositoryAnalyzer\DTOs;

/**
 * Represents monorepo detection result
 */
class MonorepoInfo
{
    public function __construct(
        public bool $isMonorepo,
        public ?string $type = null,
        public array $workspacePaths = [],
    ) {}

    public static function notMonorepo(): self
    {
        return new self(isMonorepo: false);
    }
}
