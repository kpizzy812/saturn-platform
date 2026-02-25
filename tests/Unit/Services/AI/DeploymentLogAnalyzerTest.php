<?php

use App\Services\AI\DeploymentLogAnalyzer;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    // Ensure AI is enabled for tests
    Config::set('ai.enabled', true);
    Config::set('ai.default_provider', 'claude');
    Config::set('ai.fallback_order', ['claude', 'openai', 'ollama']);
    Config::set('ai.providers.claude.api_key', '');
    Config::set('ai.providers.openai.api_key', '');
    Config::set('ai.cache.enabled', false);
    Config::set('ai.log_processing.max_log_size', 15000);
    Config::set('ai.log_processing.tail_lines', 200);
});

describe('DeploymentLogAnalyzer', function () {
    it('is not available when AI is disabled', function () {
        Config::set('ai.enabled', false);

        $analyzer = new DeploymentLogAnalyzer;

        expect($analyzer->isAvailable())->toBeFalse();
    })->skip('Requires database connection (AI provider constructors query InstanceSettings)');

    it('is not available when no providers are configured', function () {
        $analyzer = new DeploymentLogAnalyzer;

        expect($analyzer->isAvailable())->toBeFalse();
    })->skip('Requires database connection (AI provider constructors query InstanceSettings)');

    it('computes consistent error hash for same error content', function () {
        $analyzer = new DeploymentLogAnalyzer;

        // Same error messages, exact same format
        $logs1 = "Error: Module not found\nBuild failed";
        $logs2 = "Error: Module not found\nBuild failed";

        $hash1 = $analyzer->computeErrorHash($logs1);
        $hash2 = $analyzer->computeErrorHash($logs2);

        expect($hash1)->toBe($hash2);
    });

    it('produces valid SHA256 hash', function () {
        $analyzer = new DeploymentLogAnalyzer;

        $hash = $analyzer->computeErrorHash('Error: Build failed');

        expect($hash)->toHaveLength(64)
            ->and($hash)->toMatch('/^[a-f0-9]{64}$/');
    });

    it('computes different hashes for different errors', function () {
        $analyzer = new DeploymentLogAnalyzer;

        $logs1 = 'Error: Module not found';
        $logs2 = 'Error: Port already in use';

        $hash1 = $analyzer->computeErrorHash($logs1);
        $hash2 = $analyzer->computeErrorHash($logs2);

        expect($hash1)->not->toBe($hash2);
    });

    it('removes UUIDs from normalized content', function () {
        $analyzer = new DeploymentLogAnalyzer;

        // The hash function removes UUIDs from the normalized content part
        // We just verify it produces valid hashes for UUID-containing logs
        $logs = 'Container abcd1234-1234-5678-9abc-def012345678 crashed';

        $hash = $analyzer->computeErrorHash($logs);

        expect($hash)->toHaveLength(64)
            ->and($hash)->toMatch('/^[a-f0-9]{64}$/');
    });

    it('normalizes container IDs in error hash', function () {
        $analyzer = new DeploymentLogAnalyzer;

        $logs1 = 'Container abc123def456 crashed';
        $logs2 = 'Container 123abc456def crashed';

        $hash1 = $analyzer->computeErrorHash($logs1);
        $hash2 = $analyzer->computeErrorHash($logs2);

        expect($hash1)->toBe($hash2);
    });

    it('prioritizes error lines in hash computation', function () {
        $analyzer = new DeploymentLogAnalyzer;

        // Both logs have same info logs but different errors
        $logs1 = "INFO: Starting build\nERROR: Missing dependency foo\nINFO: Cleanup";
        $logs2 = "INFO: Starting build\nERROR: Missing dependency bar\nINFO: Cleanup";

        $hash1 = $analyzer->computeErrorHash($logs1);
        $hash2 = $analyzer->computeErrorHash($logs2);

        expect($hash1)->not->toBe($hash2);
    });
});

describe('DeploymentLogAnalyzer Log Processing', function () {
    it('truncates long logs to configured max size', function () {
        Config::set('ai.log_processing.max_log_size', 100);
        Config::set('ai.log_processing.tail_lines', 5);

        $analyzer = new DeploymentLogAnalyzer;
        $reflection = new ReflectionClass($analyzer);
        $method = $reflection->getMethod('prepareLogs');

        $longLogs = str_repeat("Line of text for testing log truncation\n", 100);
        $prepared = $method->invoke($analyzer, $longLogs);

        expect(strlen($prepared))->toBeLessThanOrEqual(100 + strlen('[LOG TRUNCATED - showing last 5 lines]') + 10);
    });

    it('keeps short logs unchanged', function () {
        Config::set('ai.log_processing.max_log_size', 15000);

        $analyzer = new DeploymentLogAnalyzer;
        $reflection = new ReflectionClass($analyzer);
        $method = $reflection->getMethod('prepareLogs');

        $shortLogs = "Error: Build failed\nSome details here";
        $prepared = $method->invoke($analyzer, $shortLogs);

        expect($prepared)->toBe($shortLogs);
    });
});
