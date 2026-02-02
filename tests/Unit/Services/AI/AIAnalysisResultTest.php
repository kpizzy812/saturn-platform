<?php

use App\Services\AI\DTOs\AIAnalysisResult;

describe('AIAnalysisResult', function () {
    it('creates result from valid JSON', function () {
        $json = json_encode([
            'root_cause' => 'Missing Dockerfile',
            'root_cause_details' => 'The Dockerfile was not found in the repository root.',
            'solution' => ['Create a Dockerfile in the repository root', 'Ensure the file is named exactly "Dockerfile"'],
            'prevention' => ['Always verify Dockerfile exists before deployment'],
            'error_category' => 'dockerfile',
            'severity' => 'high',
            'confidence' => 0.95,
        ]);

        $result = AIAnalysisResult::fromJson($json, 'claude', 'claude-sonnet-4-20250514', 500);

        expect($result->rootCause)->toBe('Missing Dockerfile')
            ->and($result->rootCauseDetails)->toBe('The Dockerfile was not found in the repository root.')
            ->and($result->solution)->toHaveCount(2)
            ->and($result->prevention)->toHaveCount(1)
            ->and($result->errorCategory)->toBe('dockerfile')
            ->and($result->severity)->toBe('high')
            ->and($result->confidence)->toBe(0.95)
            ->and($result->provider)->toBe('claude')
            ->and($result->model)->toBe('claude-sonnet-4-20250514')
            ->and($result->tokensUsed)->toBe(500);
    });

    it('normalizes invalid severity to medium', function () {
        $json = json_encode([
            'root_cause' => 'Test error',
            'severity' => 'invalid_severity',
        ]);

        $result = AIAnalysisResult::fromJson($json, 'test', 'test-model');

        expect($result->severity)->toBe('medium');
    });

    it('normalizes invalid category to unknown', function () {
        $json = json_encode([
            'root_cause' => 'Test error',
            'error_category' => 'invalid_category',
        ]);

        $result = AIAnalysisResult::fromJson($json, 'test', 'test-model');

        expect($result->errorCategory)->toBe('unknown');
    });

    it('clamps confidence to valid range', function () {
        $json = json_encode([
            'root_cause' => 'Test error',
            'confidence' => 1.5,
        ]);

        $result = AIAnalysisResult::fromJson($json, 'test', 'test-model');

        expect($result->confidence)->toBe(1.0);

        $json = json_encode([
            'root_cause' => 'Test error',
            'confidence' => -0.5,
        ]);

        $result = AIAnalysisResult::fromJson($json, 'test', 'test-model');

        expect($result->confidence)->toBe(0.0);
    });

    it('throws exception for invalid JSON', function () {
        AIAnalysisResult::fromJson('invalid json{', 'test', 'test-model');
    })->throws(InvalidArgumentException::class);

    it('creates failed result', function () {
        $result = AIAnalysisResult::failed('API timeout', 'claude', 'claude-sonnet-4-20250514');

        expect($result->rootCause)->toBe('Analysis failed')
            ->and($result->rootCauseDetails)->toBe('API timeout')
            ->and($result->confidence)->toBe(0.0)
            ->and($result->solution)->toBeEmpty()
            ->and($result->prevention)->toBeEmpty();
    });

    it('converts to array correctly', function () {
        $json = json_encode([
            'root_cause' => 'Test error',
            'root_cause_details' => 'Details',
            'solution' => ['Fix it'],
            'prevention' => ['Prevent it'],
            'error_category' => 'build',
            'severity' => 'low',
            'confidence' => 0.8,
        ]);

        $result = AIAnalysisResult::fromJson($json, 'openai', 'gpt-4o', 100);
        $array = $result->toArray();

        expect($array)->toHaveKeys([
            'root_cause', 'root_cause_details', 'solution', 'prevention',
            'error_category', 'severity', 'confidence', 'provider', 'model', 'tokens_used',
        ])
            ->and($array['root_cause'])->toBe('Test error')
            ->and($array['provider'])->toBe('openai')
            ->and($array['tokens_used'])->toBe(100);
    });

    it('handles missing optional fields', function () {
        $json = json_encode([
            'root_cause' => 'Test error',
        ]);

        $result = AIAnalysisResult::fromJson($json, 'test', 'test-model');

        expect($result->rootCause)->toBe('Test error')
            ->and($result->rootCauseDetails)->toBe('')
            ->and($result->solution)->toBeEmpty()
            ->and($result->prevention)->toBeEmpty()
            ->and($result->errorCategory)->toBe('unknown')
            ->and($result->severity)->toBe('medium')
            ->and($result->confidence)->toBe(0.5);
    });
});
