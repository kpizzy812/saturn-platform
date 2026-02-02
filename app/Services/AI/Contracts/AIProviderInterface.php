<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\DTOs\AIAnalysisResult;

interface AIProviderInterface
{
    /**
     * Check if the provider is available and configured.
     */
    public function isAvailable(): bool;

    /**
     * Get the provider name.
     */
    public function getName(): string;

    /**
     * Analyze deployment logs and return structured result.
     */
    public function analyze(string $prompt, string $logContent): AIAnalysisResult;

    /**
     * Get raw JSON response from AI (for custom parsing).
     */
    public function rawAnalyze(string $systemPrompt, string $userPrompt): string;

    /**
     * Get the model being used.
     */
    public function getModel(): string;
}
