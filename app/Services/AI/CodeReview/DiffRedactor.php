<?php

namespace App\Services\AI\CodeReview;

use App\Services\AI\CodeReview\DTOs\DiffResult;

/**
 * Redacts sensitive data from diffs before sending to LLM.
 *
 * Security: We NEVER send raw secrets to LLM providers.
 * All sensitive patterns are replaced with placeholders.
 */
class DiffRedactor
{
    /**
     * Patterns that should be completely stripped (not just masked).
     * These are too sensitive to send even as context.
     */
    private array $stripPatterns = [
        // Private keys - strip entire block
        '/-----BEGIN (?:RSA |DSA |EC |OPENSSH )?PRIVATE KEY-----[\s\S]*?-----END (?:RSA |DSA |EC |OPENSSH )?PRIVATE KEY-----/',
        '/-----BEGIN PGP PRIVATE KEY BLOCK-----[\s\S]*?-----END PGP PRIVATE KEY BLOCK-----/',
        '/-----BEGIN CERTIFICATE-----[\s\S]*?-----END CERTIFICATE-----/',
    ];

    /**
     * Patterns that should be masked with placeholders.
     * The LLM can still see the context but not the actual value.
     */
    private array $maskPatterns = [
        // GitHub tokens
        '/ghp_[a-zA-Z0-9]{36}/' => '[GITHUB_TOKEN]',
        '/gho_[a-zA-Z0-9]{36}/' => '[GITHUB_OAUTH_TOKEN]',
        '/ghs_[a-zA-Z0-9]{36}/' => '[GITHUB_SERVER_TOKEN]',
        '/ghu_[a-zA-Z0-9]{36}/' => '[GITHUB_USER_TOKEN]',
        '/github_pat_[a-zA-Z0-9]{22}_[a-zA-Z0-9]{59}/' => '[GITHUB_PAT]',

        // OpenAI / AI providers
        '/sk-[a-zA-Z0-9]{48}/' => '[OPENAI_API_KEY]',
        '/sk-ant-api[a-zA-Z0-9\-_]{90,}/' => '[ANTHROPIC_API_KEY]',
        '/sk-ant-[a-zA-Z0-9\-]{95}/' => '[ANTHROPIC_API_KEY]',

        // AWS
        '/AKIA[0-9A-Z]{16}/' => '[AWS_ACCESS_KEY_ID]',
        '/(aws_secret_access_key|aws_secret)\s*[:=]\s*["\']?([a-zA-Z0-9\/+]{40})["\']?/' => '$1=[AWS_SECRET_KEY]',

        // Slack
        '/xox[baprs]-[0-9a-zA-Z\-]{10,}/' => '[SLACK_TOKEN]',

        // Stripe
        '/sk_live_[a-zA-Z0-9]{24,}/' => '[STRIPE_LIVE_KEY]',
        '/sk_test_[a-zA-Z0-9]{24,}/' => '[STRIPE_TEST_KEY]',
        '/rk_live_[a-zA-Z0-9]{24,}/' => '[STRIPE_RESTRICTED_KEY]',
        '/pk_live_[a-zA-Z0-9]{24,}/' => '[STRIPE_PUBLISHABLE_KEY]',

        // Google
        '/AIza[0-9A-Za-z\-_]{35}/' => '[GOOGLE_API_KEY]',

        // Twilio
        '/SK[a-f0-9]{32}/' => '[TWILIO_API_KEY]',

        // SendGrid
        '/SG\.[a-zA-Z0-9\-_]{22}\.[a-zA-Z0-9\-_]{43}/' => '[SENDGRID_API_KEY]',

        // Discord
        '/[MN][A-Za-z\d]{23,}\.[\w-]{6}\.[\w-]{27}/' => '[DISCORD_BOT_TOKEN]',

        // JWT tokens
        '/eyJ[A-Za-z0-9_-]*\.eyJ[A-Za-z0-9_-]*\.[A-Za-z0-9_-]{43,}/' => '[JWT_TOKEN]',

        // Generic patterns - be careful, these can have false positives
        '/(api[_-]?key|apikey)\s*[:=]\s*["\']([a-zA-Z0-9_\-]{20,})["\']/' => '$1="[REDACTED_API_KEY]"',
        '/(api[_-]?secret|apisecret)\s*[:=]\s*["\']([a-zA-Z0-9_\-]{20,})["\']/' => '$1="[REDACTED_API_SECRET]"',
        '/(password|passwd|pwd)\s*[:=]\s*["\']([^"\']{8,})["\']/' => '$1="[REDACTED_PASSWORD]"',
        '/(secret[_-]?key|secretkey)\s*[:=]\s*["\']([a-zA-Z0-9_\-]{20,})["\']/' => '$1="[REDACTED_SECRET_KEY]"',
        '/(auth[_-]?token|authtoken)\s*[:=]\s*["\']([a-zA-Z0-9_\-]{20,})["\']/' => '$1="[REDACTED_AUTH_TOKEN]"',
        '/(access[_-]?token|accesstoken)\s*[:=]\s*["\']([a-zA-Z0-9_\-]{20,})["\']/' => '$1="[REDACTED_ACCESS_TOKEN]"',

        // Database connection strings
        '/(mysql|postgres|mongodb|redis):\/\/[^:]+:[^@]+@/' => '$1://[USER]:[PASSWORD]@',
    ];

    /**
     * Redact sensitive data from the diff.
     *
     * @return array{diff: string, redactions_count: int}
     */
    public function redact(string $diff): array
    {
        $redactionsCount = 0;

        // First, strip dangerous content entirely
        foreach ($this->stripPatterns as $pattern) {
            $count = 0;
            $diff = preg_replace($pattern, '[STRIPPED_SENSITIVE_CONTENT]', $diff, -1, $count);
            $redactionsCount += $count;
        }

        // Then, mask other sensitive patterns
        foreach ($this->maskPatterns as $pattern => $replacement) {
            $count = 0;
            $diff = preg_replace($pattern, $replacement, $diff, -1, $count);
            $redactionsCount += $count;
        }

        return [
            'diff' => $diff,
            'redactions_count' => $redactionsCount,
        ];
    }

    /**
     * Redact sensitive data from a DiffResult.
     * Returns a new raw diff string suitable for LLM.
     */
    public function redactDiffResult(DiffResult $diff): string
    {
        $result = $this->redact($diff->rawDiff);

        return $result['diff'];
    }

    /**
     * Check if content contains potentially sensitive data.
     */
    public function containsSensitiveData(string $content): bool
    {
        // Check strip patterns
        foreach ($this->stripPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        // Check mask patterns
        foreach ($this->maskPatterns as $pattern => $replacement) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }
}
