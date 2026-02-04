<?php

namespace App\Services\AI\DTOs;

use InvalidArgumentException;

final readonly class AIAnalysisResult
{
    public function __construct(
        public string $rootCause,
        public string $rootCauseDetails,
        public array $solution,
        public array $prevention,
        public string $errorCategory,
        public string $severity,
        public float $confidence,
        public string $provider,
        public string $model,
        public ?int $tokensUsed = null,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
    ) {}

    /**
     * Get total tokens used.
     */
    public function getTotalTokens(): ?int
    {
        if ($this->inputTokens !== null && $this->outputTokens !== null) {
            return $this->inputTokens + $this->outputTokens;
        }

        return $this->tokensUsed;
    }

    /**
     * Create from AI provider JSON response.
     */
    public static function fromJson(
        string $json,
        string $provider,
        string $model,
        ?int $tokensUsed = null,
        ?int $inputTokens = null,
        ?int $outputTokens = null,
    ): self {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON response from AI provider: '.json_last_error_msg());
        }

        return new self(
            rootCause: $data['root_cause'] ?? 'Unknown error',
            rootCauseDetails: $data['root_cause_details'] ?? '',
            solution: $data['solution'] ?? [],
            prevention: $data['prevention'] ?? [],
            errorCategory: self::normalizeCategory($data['error_category'] ?? 'unknown'),
            severity: self::normalizeSeverity($data['severity'] ?? 'medium'),
            confidence: self::normalizeConfidence($data['confidence'] ?? 0.5),
            provider: $provider,
            model: $model,
            tokensUsed: $tokensUsed ?? ($inputTokens !== null && $outputTokens !== null ? $inputTokens + $outputTokens : null),
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
        );
    }

    /**
     * Create a failed result when analysis couldn't be completed.
     */
    public static function failed(string $reason, string $provider, string $model): self
    {
        return new self(
            rootCause: 'Analysis failed',
            rootCauseDetails: $reason,
            solution: [],
            prevention: [],
            errorCategory: 'unknown',
            severity: 'medium',
            confidence: 0.0,
            provider: $provider,
            model: $model,
            tokensUsed: null,
            inputTokens: null,
            outputTokens: null,
        );
    }

    /**
     * Convert to array for storage.
     */
    public function toArray(): array
    {
        return [
            'root_cause' => $this->rootCause,
            'root_cause_details' => $this->rootCauseDetails,
            'solution' => $this->solution,
            'prevention' => $this->prevention,
            'error_category' => $this->errorCategory,
            'severity' => $this->severity,
            'confidence' => $this->confidence,
            'provider' => $this->provider,
            'model' => $this->model,
            'tokens_used' => $this->tokensUsed,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
        ];
    }

    private static function normalizeCategory(string $category): string
    {
        $validCategories = ['dockerfile', 'dependency', 'build', 'runtime', 'network', 'resource', 'config', 'unknown'];

        return in_array($category, $validCategories, true) ? $category : 'unknown';
    }

    private static function normalizeSeverity(string $severity): string
    {
        $validSeverities = ['low', 'medium', 'high', 'critical'];

        return in_array($severity, $validSeverities, true) ? $severity : 'medium';
    }

    private static function normalizeConfidence(mixed $confidence): float
    {
        $value = (float) $confidence;

        return max(0.0, min(1.0, $value));
    }
}
