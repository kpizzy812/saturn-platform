<?php

namespace App\Services\AI;

use App\Models\ApplicationDeploymentQueue;
use App\Models\DeploymentLogAnalysis;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\AIAnalysisResult;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\OllamaProvider;
use App\Services\AI\Providers\OpenAIProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class DeploymentLogAnalyzer
{
    /**
     * @var array<string, class-string<AIProviderInterface>>
     */
    private array $providerMap = [
        'claude' => AnthropicProvider::class,
        'openai' => OpenAIProvider::class,
        'ollama' => OllamaProvider::class,
    ];

    private ?AIProviderInterface $provider = null;

    /**
     * Check if AI analysis is available.
     */
    public function isAvailable(): bool
    {
        if (! config('ai.enabled', true)) {
            return false;
        }

        return $this->getAvailableProvider() !== null;
    }

    /**
     * Analyze deployment logs and return structured result.
     */
    public function analyze(ApplicationDeploymentQueue $deployment): AIAnalysisResult
    {
        $provider = $this->getAvailableProvider();

        if ($provider === null) {
            throw new RuntimeException('No AI provider is available');
        }

        $logs = $this->prepareLogs($deployment->logs ?? '');
        $errorHash = $this->computeErrorHash($logs);

        // Check cache for existing analysis
        if ($this->isCacheEnabled()) {
            $cached = $this->getCachedAnalysis($errorHash);
            if ($cached !== null) {
                Log::info('Using cached AI analysis', ['hash' => $errorHash, 'deployment' => $deployment->id]);

                return $cached;
            }
        }

        // Perform analysis
        $prompt = config('ai.prompts.deployment_analysis');
        $result = $provider->analyze($prompt, $logs);

        // Cache the result
        if ($this->isCacheEnabled()) {
            $this->cacheAnalysis($errorHash, $result);
        }

        return $result;
    }

    /**
     * Analyze and save to database.
     */
    public function analyzeAndSave(ApplicationDeploymentQueue $deployment): DeploymentLogAnalysis
    {
        $analysis = DeploymentLogAnalysis::firstOrNew(['deployment_id' => $deployment->id]);
        $analysis->error_hash = $this->computeErrorHash($this->prepareLogs($deployment->logs ?? ''));
        $analysis->status = 'analyzing';
        $analysis->save();

        try {
            $result = $this->analyze($deployment);

            $analysis->fill([
                'root_cause' => $result->rootCause,
                'root_cause_details' => $result->rootCauseDetails,
                'solution' => $result->solution,
                'prevention' => $result->prevention,
                'error_category' => $result->errorCategory,
                'severity' => $result->severity,
                'confidence' => $result->confidence,
                'provider' => $result->provider,
                'model' => $result->model,
                'tokens_used' => $result->tokensUsed,
                'status' => 'completed',
                'error_message' => null,
            ]);
            $analysis->save();

            Log::info('AI analysis completed', [
                'deployment_id' => $deployment->id,
                'provider' => $result->provider,
                'category' => $result->errorCategory,
            ]);
        } catch (\Throwable $e) {
            Log::error('AI analysis failed', [
                'deployment_id' => $deployment->id,
                'error' => $e->getMessage(),
            ]);

            $analysis->status = 'failed';
            $analysis->error_message = $e->getMessage();
            $analysis->save();
        }

        return $analysis;
    }

    /**
     * Get the first available AI provider.
     */
    public function getAvailableProvider(): ?AIProviderInterface
    {
        if ($this->provider !== null) {
            return $this->provider;
        }

        $defaultProvider = config('ai.default_provider', 'claude');
        $fallbackOrder = config('ai.fallback_order', ['claude', 'openai', 'ollama']);

        // Try default provider first
        $provider = $this->createProvider($defaultProvider);
        if ($provider?->isAvailable()) {
            $this->provider = $provider;

            return $provider;
        }

        // Try fallback providers
        foreach ($fallbackOrder as $providerName) {
            if ($providerName === $defaultProvider) {
                continue;
            }

            $provider = $this->createProvider($providerName);
            if ($provider?->isAvailable()) {
                Log::info('Using fallback AI provider', ['provider' => $providerName]);
                $this->provider = $provider;

                return $provider;
            }
        }

        return null;
    }

    /**
     * Compute a hash of the error content for caching purposes.
     * Removes timestamps and variable parts to match similar errors.
     */
    public function computeErrorHash(string $logs): string
    {
        // Remove timestamps in various formats
        $normalized = preg_replace('/\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}[^\s]*/', '', $logs);
        $normalized = preg_replace('/\[\d{2}\/\w+\/\d{4}[^\]]*\]/', '', $normalized);

        // Remove UUIDs
        $normalized = preg_replace('/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i', '', $normalized);

        // Remove container IDs
        $normalized = preg_replace('/[a-f0-9]{64}/i', '', $normalized);
        $normalized = preg_replace('/[a-f0-9]{12}/i', '', $normalized);

        // Remove port numbers that might change
        $normalized = preg_replace('/:\d{4,5}/', '', $normalized);

        // Remove multiple whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        // Extract error lines only
        $errorLines = [];
        foreach (explode("\n", $logs) as $line) {
            if (preg_match('/error|exception|failed|fatal|panic|cannot|unable|not found|denied/i', $line)) {
                $errorLines[] = trim($line);
            }
        }

        // Hash the normalized error content
        return hash('sha256', $normalized.implode("\n", $errorLines));
    }

    /**
     * Prepare logs for AI analysis (truncate if needed).
     */
    private function prepareLogs(string $logs): string
    {
        $maxSize = config('ai.log_processing.max_log_size', 15000);
        $tailLines = config('ai.log_processing.tail_lines', 200);

        if (strlen($logs) <= $maxSize) {
            return $logs;
        }

        // Get last N lines
        $lines = explode("\n", $logs);
        $lines = array_slice($lines, -$tailLines);

        $truncated = implode("\n", $lines);

        // If still too long, truncate from the beginning
        if (strlen($truncated) > $maxSize) {
            $truncated = substr($truncated, -$maxSize);
        }

        return "[LOG TRUNCATED - showing last {$tailLines} lines]\n\n".$truncated;
    }

    /**
     * Create a provider instance by name.
     */
    private function createProvider(string $name): ?AIProviderInterface
    {
        $class = $this->providerMap[$name] ?? null;

        return $class ? new $class : null;
    }

    private function isCacheEnabled(): bool
    {
        return config('ai.cache.enabled', true);
    }

    private function getCacheKey(string $hash): string
    {
        return config('ai.cache.prefix', 'ai_analysis:').$hash;
    }

    private function getCachedAnalysis(string $hash): ?AIAnalysisResult
    {
        $cached = Cache::get($this->getCacheKey($hash));

        if ($cached === null) {
            return null;
        }

        return new AIAnalysisResult(
            rootCause: $cached['root_cause'],
            rootCauseDetails: $cached['root_cause_details'],
            solution: $cached['solution'],
            prevention: $cached['prevention'],
            errorCategory: $cached['error_category'],
            severity: $cached['severity'],
            confidence: $cached['confidence'],
            provider: $cached['provider'].' (cached)',
            model: $cached['model'],
            tokensUsed: $cached['tokens_used'],
        );
    }

    private function cacheAnalysis(string $hash, AIAnalysisResult $result): void
    {
        $ttl = config('ai.cache.ttl', 86400);

        Cache::put($this->getCacheKey($hash), $result->toArray(), $ttl);
    }
}
