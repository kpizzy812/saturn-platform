<?php

namespace App\Services\AI\CodeReview\Detectors;

use App\Services\AI\CodeReview\Contracts\StaticDetectorInterface;
use App\Services\AI\CodeReview\DTOs\DiffResult;
use App\Services\AI\CodeReview\DTOs\Violation;
use Illuminate\Support\Collection;

/**
 * Detects hardcoded secrets in code changes.
 *
 * This is a deterministic detector (regex-based) with confidence=1.0.
 * Secrets are masked in snippets before storage.
 */
class SecretsDetector implements StaticDetectorInterface
{
    private const VERSION = '1.0.0';

    /**
     * Secret patterns with their rules.
     * Each pattern has: name, patterns array, and optional severity override.
     */
    private array $rules = [
        'SEC001' => [
            'name' => 'API Key/Token',
            'severity' => 'critical',
            'patterns' => [
                // Generic API key patterns
                '/(?:api[_-]?key|apikey)\s*[:=]\s*["\']([a-zA-Z0-9_\-]{20,})["\']/' => 'API key',
                '/(?:api[_-]?secret|apisecret)\s*[:=]\s*["\']([a-zA-Z0-9_\-]{20,})["\']/' => 'API secret',
            ],
        ],
        'SEC002' => [
            'name' => 'Hardcoded Password',
            'severity' => 'critical',
            'patterns' => [
                '/(?:password|passwd|pwd)\s*[:=]\s*["\']([^"\']{8,})["\']/' => 'password',
            ],
        ],
        'SEC003' => [
            'name' => 'Database Connection String',
            'severity' => 'critical',
            'patterns' => [
                '/(?:mysql|postgres|mongodb|redis):\/\/[^:]+:[^@]+@[^\s"\']+/' => 'connection string',
                '/DATABASE_URL\s*[:=]\s*["\']([^"\']+)["\']/' => 'database URL',
            ],
        ],
        'SEC007' => [
            'name' => 'Private Key',
            'severity' => 'critical',
            'patterns' => [
                '/-----BEGIN (?:RSA |DSA |EC |OPENSSH )?PRIVATE KEY-----/' => 'private key',
                '/-----BEGIN PGP PRIVATE KEY BLOCK-----/' => 'PGP private key',
            ],
        ],
        'SEC008' => [
            'name' => 'Known Token Format',
            'severity' => 'critical',
            'patterns' => [
                // GitHub tokens
                '/ghp_[a-zA-Z0-9]{36}/' => 'GitHub Personal Access Token',
                '/gho_[a-zA-Z0-9]{36}/' => 'GitHub OAuth Token',
                '/ghs_[a-zA-Z0-9]{36}/' => 'GitHub Server Token',
                '/ghu_[a-zA-Z0-9]{36}/' => 'GitHub User Token',
                '/github_pat_[a-zA-Z0-9]{22}_[a-zA-Z0-9]{59}/' => 'GitHub Fine-grained PAT',

                // OpenAI / AI providers
                '/sk-[a-zA-Z0-9]{48}/' => 'OpenAI API Key',
                '/sk-ant-api[a-zA-Z0-9\-_]{90,}/' => 'Anthropic API Key',
                '/sk-ant-[a-zA-Z0-9\-]{95}/' => 'Anthropic API Key (legacy)',

                // AWS
                '/AKIA[0-9A-Z]{16}/' => 'AWS Access Key ID',
                '/(?:aws_secret_access_key|aws_secret)\s*[:=]\s*["\']([a-zA-Z0-9\/+]{40})["\']/' => 'AWS Secret Key',

                // Slack
                '/xox[baprs]-[0-9a-zA-Z\-]{10,}/' => 'Slack Token',

                // Stripe
                '/sk_live_[a-zA-Z0-9]{24,}/' => 'Stripe Live Secret Key',
                '/sk_test_[a-zA-Z0-9]{24,}/' => 'Stripe Test Secret Key',
                '/rk_live_[a-zA-Z0-9]{24,}/' => 'Stripe Live Restricted Key',
                '/pk_live_[a-zA-Z0-9]{24,}/' => 'Stripe Live Publishable Key',

                // Google
                '/AIza[0-9A-Za-z\-_]{35}/' => 'Google API Key',

                // Twilio
                '/SK[a-f0-9]{32}/' => 'Twilio API Key',

                // SendGrid
                '/SG\.[a-zA-Z0-9\-_]{22}\.[a-zA-Z0-9\-_]{43}/' => 'SendGrid API Key',

                // Discord
                '/[MN][A-Za-z\d]{23,}\.[\w-]{6}\.[\w-]{27}/' => 'Discord Bot Token',

                // JWT tokens (generic)
                '/eyJ[A-Za-z0-9_-]*\.eyJ[A-Za-z0-9_-]*\.[A-Za-z0-9_-]{43,}/' => 'JWT Token',

                // Generic secret patterns
                '/(?:secret[_-]?key|secretkey)\s*[:=]\s*["\']([a-zA-Z0-9_\-]{20,})["\']/' => 'secret key',
                '/(?:auth[_-]?token|authtoken)\s*[:=]\s*["\']([a-zA-Z0-9_\-]{20,})["\']/' => 'auth token',
                '/(?:access[_-]?token|accesstoken)\s*[:=]\s*["\']([a-zA-Z0-9_\-]{20,})["\']/' => 'access token',
            ],
        ],
    ];

    /**
     * File patterns to skip (unlikely to contain real secrets).
     */
    private array $skipPatterns = [
        '/\.test\.(ts|js|php)$/',
        '/\.spec\.(ts|js|php)$/',
        '/tests?\//',
        '/fixtures?\//',
        '/mocks?\//',
        '/__mocks__\//',
        '/\.example$/',
        '/\.sample$/',
        '/\.md$/',
        '/\.txt$/',
        '/CHANGELOG/',
        '/README/',
        '/package-lock\.json$/',
        '/composer\.lock$/',
        '/yarn\.lock$/',
    ];

    public function detect(DiffResult $diff): Collection
    {
        $violations = collect();

        foreach ($diff->addedLines as $line) {
            // Skip test files and examples
            if ($this->shouldSkipFile($line->file)) {
                continue;
            }

            // Skip lines that are comments
            if ($this->isCommentLine($line->content)) {
                continue;
            }

            foreach ($this->rules as $ruleId => $rule) {
                foreach ($rule['patterns'] as $pattern => $description) {
                    if (preg_match($pattern, $line->content, $matches)) {
                        // Check for false positives
                        if ($this->isFalsePositive($line->content, $matches)) {
                            continue;
                        }

                        $violations->push(new Violation(
                            ruleId: $ruleId,
                            source: 'regex',
                            severity: $rule['severity'] ?? 'critical',
                            confidence: 1.0, // Deterministic
                            file: $line->file,
                            line: $line->number,
                            message: "Potential {$description} detected: {$rule['name']}",
                            snippet: $this->maskSecret($line->content, $matches),
                            containsSecret: true,
                        ));

                        // One violation per line per rule is enough
                        break;
                    }
                }
            }
        }

        return $violations;
    }

    public function getName(): string
    {
        return 'SecretsDetector';
    }

    public function getVersion(): string
    {
        return self::VERSION;
    }

    public function isEnabled(): bool
    {
        return config('ai.code_review.detectors.secrets', true);
    }

    /**
     * Check if file should be skipped based on path patterns.
     */
    private function shouldSkipFile(string $filePath): bool
    {
        foreach ($this->skipPatterns as $pattern) {
            if (preg_match($pattern, $filePath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if line is a comment.
     */
    private function isCommentLine(string $content): bool
    {
        $trimmed = trim($content);

        // PHP/JS single-line comments
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '#')) {
            return true;
        }

        // Multi-line comment markers
        if (str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '*')) {
            return true;
        }

        return false;
    }

    /**
     * Check for common false positive patterns.
     */
    private function isFalsePositive(string $content, array $matches): bool
    {
        $lowerContent = strtolower($content);

        // Placeholder patterns
        $placeholders = [
            'your-api-key',
            'your_api_key',
            'api-key-here',
            'xxx',
            'placeholder',
            'example',
            'sample',
            'test',
            'dummy',
            'fake',
            'mock',
            'todo',
            'fixme',
            'changeme',
            'replace',
            '<your',
            '[your',
            '{your',
            'process.env',
            'env(',
            'getenv(',
            '$_env',
            'config(',
        ];

        foreach ($placeholders as $placeholder) {
            if (str_contains($lowerContent, $placeholder)) {
                return true;
            }
        }

        // Environment variable references
        if (preg_match('/\$\{?[A-Z_]+\}?/', $content)) {
            return true;
        }

        // Short matches are likely false positives
        if (isset($matches[1]) && strlen($matches[1]) < 10) {
            return true;
        }

        return false;
    }

    /**
     * Mask the secret in the snippet for safe storage.
     */
    private function maskSecret(string $content, array $matches): string
    {
        if (isset($matches[0])) {
            $secret = $matches[0];
            $visibleChars = min(4, (int) floor(strlen($secret) / 4));

            if ($visibleChars > 0) {
                $masked = substr($secret, 0, $visibleChars).'[REDACTED]'.substr($secret, -$visibleChars);
            } else {
                $masked = '[REDACTED]';
            }

            return str_replace($secret, $masked, $content);
        }

        return $content;
    }
}
