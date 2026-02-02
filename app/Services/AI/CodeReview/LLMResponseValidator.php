<?php

namespace App\Services\AI\CodeReview;

/**
 * Validates LLM responses for code review enrichment.
 *
 * Security: We don't trust LLM output blindly.
 * Invalid responses result in graceful degradation, not errors.
 */
class LLMResponseValidator
{
    /**
     * Expected schema for enrichment response.
     */
    private array $expectedSchema = [
        'violations' => 'array',
    ];

    /**
     * Expected schema for each violation enrichment.
     */
    private array $violationSchema = [
        'rule_id' => 'string',
        'suggestion' => 'string',
    ];

    /**
     * Validate the LLM response.
     *
     * @return array{valid: bool, data: ?array, error: ?string}
     */
    public function validate(string $response): array
    {
        // Try to parse JSON
        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try to extract JSON from markdown code blocks
            if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $response, $matches)) {
                $data = json_decode(trim($matches[1]), true);
            }
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'valid' => false,
                'data' => null,
                'error' => 'JSON parse failed: '.json_last_error_msg(),
            ];
        }

        if (! is_array($data)) {
            return [
                'valid' => false,
                'data' => null,
                'error' => 'Response is not an object',
            ];
        }

        // Check top-level schema
        if (! isset($data['violations']) || ! is_array($data['violations'])) {
            return [
                'valid' => false,
                'data' => null,
                'error' => 'Missing or invalid violations array',
            ];
        }

        // Validate each violation
        $validViolations = [];
        foreach ($data['violations'] as $violation) {
            if ($this->isValidViolation($violation)) {
                $validViolations[] = $this->sanitizeViolation($violation);
            }
        }

        return [
            'valid' => true,
            'data' => ['violations' => $validViolations],
            'error' => null,
        ];
    }

    /**
     * Check if violation object is valid.
     */
    private function isValidViolation(mixed $violation): bool
    {
        if (! is_array($violation)) {
            return false;
        }

        // Must have rule_id
        if (! isset($violation['rule_id']) || ! is_string($violation['rule_id'])) {
            return false;
        }

        // Must have suggestion
        if (! isset($violation['suggestion']) || ! is_string($violation['suggestion'])) {
            return false;
        }

        return true;
    }

    /**
     * Sanitize violation data to prevent injection.
     */
    private function sanitizeViolation(array $violation): array
    {
        return [
            'rule_id' => $this->sanitizeString($violation['rule_id'], 20),
            'suggestion' => $this->sanitizeString($violation['suggestion'], 2000),
        ];
    }

    /**
     * Sanitize a string value.
     */
    private function sanitizeString(string $value, int $maxLength): string
    {
        // Remove control characters
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

        // Truncate if too long
        if (strlen($value) > $maxLength) {
            $value = substr($value, 0, $maxLength).'...';
        }

        return $value;
    }
}
