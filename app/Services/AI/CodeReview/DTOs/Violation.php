<?php

namespace App\Services\AI\CodeReview\DTOs;

use App\Models\CodeReviewViolation;

/**
 * Represents a detected violation from a detector.
 */
readonly class Violation
{
    public function __construct(
        public string $ruleId,
        public string $source,
        public string $severity,
        public float $confidence,
        public string $file,
        public ?int $line,
        public string $message,
        public ?string $snippet = null,
        public ?string $suggestion = null,
        public bool $containsSecret = false,
    ) {}

    /**
     * Convert to array for model creation.
     */
    public function toArray(): array
    {
        return [
            'rule_id' => $this->ruleId,
            'source' => $this->source,
            'severity' => $this->severity,
            'confidence' => $this->confidence,
            'file_path' => $this->file,
            'line_number' => $this->line,
            'message' => $this->message,
            'snippet' => $this->snippet,
            'suggestion' => $this->suggestion,
            'contains_secret' => $this->containsSecret,
            'fingerprint' => $this->generateFingerprint(),
        ];
    }

    /**
     * Generate fingerprint for deduplication.
     */
    public function generateFingerprint(): string
    {
        return CodeReviewViolation::generateFingerprint(
            $this->ruleId,
            $this->file,
            $this->line,
            $this->message
        );
    }

    /**
     * Create new violation with added suggestion (from LLM enrichment).
     */
    public function withSuggestion(string $suggestion): self
    {
        return new self(
            ruleId: $this->ruleId,
            source: $this->source,
            severity: $this->severity,
            confidence: $this->confidence,
            file: $this->file,
            line: $this->line,
            message: $this->message,
            snippet: $this->snippet,
            suggestion: $suggestion,
            containsSecret: $this->containsSecret,
        );
    }

    /**
     * Check if this is a critical violation.
     */
    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    /**
     * Check if this is deterministic (high confidence from regex/AST).
     */
    public function isDeterministic(): bool
    {
        return in_array($this->source, ['regex', 'ast']) && $this->confidence >= 1.0;
    }
}
